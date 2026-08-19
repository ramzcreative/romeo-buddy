<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;

/**
 * Merges the `imageItem` entry type into the shared `item` type, so the
 * imageText block uses the same `items` field as cards and slider.
 *
 * The point is interchangeability: Craft carries a block's content across an
 * entry type change for every field handle the two types share, so blocks that
 * share a nested field can be swapped without retyping anything. imageText was
 * the odd one out with its own `imageItems` field and `imageItem` type, which
 * meant switching a cards block to imageText left its items behind.
 *
 * imageItem differs from item by exactly one field, `text`, so this adds that
 * to `item` first.
 *
 * The boilerplate's version of this re-typed the existing entries in place and
 * remapped their stored content by hand — element content is keyed by field
 * layout ELEMENT uid, not field uid, so re-typing orphans every value. That
 * worked, but it also hand-assigned `entries.fieldId`, and about ten minutes
 * later garbage collection soft-deleted three of the four entries as orphans;
 * the pages rendered fine in between, which is the worst possible failure
 * shape. It took two follow-up migrations to repair.
 *
 * So this builds new item entries instead and copies the values across with
 * getFieldValue()/setFieldValue(). Craft does the layout-element-uid keying
 * and writes the ownership rows itself, which is the same approach that went
 * through cleanly for banner and spotlight. The cost is that the items get new
 * element ids — fine for nested page-builder content, which nothing links to.
 */
class m260819_160000_consolidateImageItemsIntoItems extends Migration
{
    /** imageItem fields that carry over. All exist on `item` after step 1. */
    private const MOVE = ['image', 'preheading', 'heading', 'intro', 'text'];

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();
        $elements = Craft::$app->getElements();

        $itemType = $entriesService->getEntryTypeByHandle('item');
        $imageItemType = $entriesService->getEntryTypeByHandle('imageItem');
        $imageText = $entriesService->getEntryTypeByHandle('imageText');
        $itemsField = $fields->getFieldByHandle('items');
        $imageItemsField = $fields->getFieldByHandle('imageItems');
        $textField = $fields->getFieldByHandle('text');

        if (!$itemType || !$itemsField) {
            throw new \Exception("The shared 'item' entry type or 'items' field is missing.");
        }

        // 1. `text` onto the shared item type — imageText's layouts render it.
        if ($textField && !$itemType->getFieldLayout()->getFieldByHandle('text')) {
            $layout = $itemType->getFieldLayout();
            $tabs = $layout->getTabs();
            $tab = $tabs[0] ?? null;
            if (!$tab) {
                throw new \Exception('The item field layout has no tabs.');
            }

            // setTabs() before setElements() — a tab that isn't attached to its
            // layout yet throws "Field layout tab is missing its field layout."
            $layout->setTabs($tabs);
            $tab->setElements(array_merge($tab->getElements(), [new CustomField($textField)]));
            $itemType->setFieldLayout($layout);

            if (!$entriesService->saveEntryType($itemType)) {
                throw new \Exception("Couldn't add 'text' to the item entry type: " . implode(', ', $itemType->getErrorSummary(true)));
            }
        }

        // 2. `items` alongside `imageItems` on imageText, so the old values are
        //    still readable while the new ones are being written.
        if ($imageText && !$imageText->getFieldLayout()->getFieldByHandle('items')) {
            $this->addField($imageText, $itemsField);
        }

        $expected = [];

        // 3. Rebuild each block's items. Read first, write second — nothing is
        //    removed until all of the content is somewhere else.
        if ($imageItemsField) {
            $old = Entry::find()
                ->fieldId($imageItemsField->id)
                ->status(null)
                ->orderBy(['sortOrder' => SORT_ASC])
                ->all();

            foreach ($old as $i => $source) {
                $ownerId = $source->getOwnerId();
                $expected[$ownerId] = ($expected[$ownerId] ?? 0) + 1;

                $item = new Entry();
                $item->typeId = $itemType->id;
                $item->fieldId = $itemsField->id;
                $item->primaryOwnerId = $ownerId;
                $item->setOwner($source->getOwner());
                $item->siteId = $source->siteId;
                $item->sortOrder = $source->getSortOrder() ?? ($i + 1);
                $item->enabled = $source->enabled;

                foreach (self::MOVE as $handle) {
                    if (!$source->getFieldLayout()?->getFieldByHandle($handle)) {
                        continue;
                    }

                    $value = $source->getFieldValue($handle);

                    // Relation fields hand back a query; the setter wants ids.
                    if ($value instanceof \craft\elements\db\ElementQuery) {
                        $value = $value->ids();
                    } elseif ($value instanceof \craft\elements\ElementCollection) {
                        $value = $value->map(fn($el) => $el->id)->all();
                    }

                    $item->setFieldValue($handle, $value);
                }

                if (!$elements->saveElement($item)) {
                    throw new \Exception(
                        "Couldn't rebuild imageItem #{$source->id} as an item: " .
                        implode(', ', $item->getErrorSummary(true))
                    );
                }
            }
        }

        // 4. Verify before anything is destroyed. Every block that had
        //    imageItems must now have the same number of items.
        $elements->invalidateAllCaches();

        foreach ($expected as $ownerId => $count) {
            $got = (int)Entry::find()
                ->fieldId($itemsField->id)
                ->ownerId($ownerId)
                ->status(null)
                ->count();

            if ($got !== $count) {
                throw new \Exception(
                    "Block #$ownerId had $count imageItems but has $got items. " .
                    'Nothing has been deleted yet — restore from the backup and investigate.'
                );
            }
        }

        // 5. Only now retire the old field and type.
        if ($imageText) {
            $this->removeField($imageText, 'imageItems');
        }

        foreach (Entry::find()->fieldId($imageItemsField?->id ?? 0)->status(null)->all() as $stale) {
            $elements->deleteElement($stale, true);
        }

        if ($imageItemsField) {
            $fields->deleteField($imageItemsField);
        }
        if ($imageItemType) {
            $entriesService->deleteEntryType($imageItemType);
        }

        return true;
    }

    private function addField(\craft\models\EntryType $type, \craft\base\FieldInterface $field): void
    {
        $layout = $type->getFieldLayout();
        $tabs = $layout->getTabs();
        $tab = $tabs[0] ?? null;
        if (!$tab) {
            throw new \Exception("The {$type->handle} field layout has no tabs.");
        }

        $layout->setTabs($tabs);
        $tab->setElements(array_merge($tab->getElements(), [new CustomField($field)]));
        $type->setFieldLayout($layout);

        if (!Craft::$app->getEntries()->saveEntryType($type)) {
            throw new \Exception("Couldn't add '{$field->handle}' to {$type->handle}: " . implode(', ', $type->getErrorSummary(true)));
        }
    }

    private function removeField(\craft\models\EntryType $type, string $handle): void
    {
        $layout = $type->getFieldLayout();
        $changed = false;

        foreach ($layout->getTabs() as $tab) {
            $out = [];
            foreach ($tab->getElements() as $element) {
                if ($element instanceof CustomField && $element->getField()->handle === $handle) {
                    $changed = true;
                    continue;
                }
                $out[] = $element;
            }
            $tab->setElements($out);
        }

        if (!$changed) {
            return;
        }

        $type->setFieldLayout($layout);

        if (!Craft::$app->getEntries()->saveEntryType($type)) {
            throw new \Exception("Couldn't remove '$handle' from {$type->handle}: " . implode(', ', $type->getErrorSummary(true)));
        }
    }

    public function safeDown(): bool
    {
        // Not reversible. Re-creating the field and entry type is easy; working
        // out which of the now-shared `item` entries used to be imageItems is
        // not, because the distinction stops existing the moment they're
        // rebuilt. Restore from a database backup instead.
        return false;
    }
}
