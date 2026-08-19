<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;

/**
 * Moves banner and spotlight onto the shared `items` field.
 *
 * Both blocks kept their content on the block itself — heading, intro, image,
 * buttons sat directly in the banner/spotlight field layout. That made them
 * dead ends: a cards block could become a slider or an imageText because all
 * three keep their content in `items`, but nothing could become a banner
 * without the editor retyping it.
 *
 * This is the harder direction of the m260819_120000 merge. There, the nested
 * entries already existed and only had to be re-pointed. Here there are no
 * nested entries yet, so one has to be created per block and the block's own
 * values moved into it.
 *
 * Deliberately done through the element API rather than by rewriting content
 * rows: the values are read with getFieldValue() and written with
 * setFieldValue(), so Craft does the layout-element-uid keying itself. That
 * detail is what m260819_130000 had to repair by hand, and the same migration
 * hand-assigned `fieldId` on existing entries, which is very likely why
 * garbage collection soft-deleted three of them ten minutes later. Creating
 * new elements and letting Craft write the ownership rows avoids both.
 *
 * Banner and spotlight only ever hold one item. They loop in the template
 * anyway, so the count is a template concern rather than a content-model one,
 * and nothing has to change here if one of them later wants more.
 */
class m260819_170000_consolidateBannerAndSpotlightIntoItems extends Migration
{
    /**
     * Which of each block's own fields become item fields.
     *
     * Every one of these is the same global field the item type already uses,
     * so the values transfer as-is. `blockHeading` and the layout selectors
     * are absent on purpose — those describe the block, not the item.
     */
    private const MOVE = [
        'banner' => ['image', 'heading', 'intro', 'buttons'],
        'spotlight' => ['image', 'heading', 'subheading', 'intro', 'buttons'],
    ];

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();
        $elements = Craft::$app->getElements();

        $itemType = $entriesService->getEntryTypeByHandle('item');
        $itemsField = $fields->getFieldByHandle('items');

        if (!$itemType || !$itemsField) {
            throw new \Exception("The shared 'item' entry type or 'items' field is missing.");
        }

        // Every field being moved has to exist on the item type already,
        // otherwise the value would be set and silently dropped on save.
        $itemLayout = $itemType->getFieldLayout();
        foreach (self::MOVE as $blockHandle => $handles) {
            foreach ($handles as $handle) {
                if (!$itemLayout->getFieldByHandle($handle)) {
                    throw new \Exception("The item entry type has no '$handle' field, which $blockHandle needs.");
                }
            }
        }

        $expected = [];

        foreach (self::MOVE as $blockHandle => $handles) {
            $blockType = $entriesService->getEntryTypeByHandle($blockHandle);
            if (!$blockType) {
                continue;
            }

            // 1. Read every block's values before the layout is touched.
            //    Reading and writing are separated for the same reason the
            //    backup exists: once a field leaves the layout its values stop
            //    being readable, so nothing may be removed until all of the
            //    content is safely somewhere else.
            $blocks = Entry::find()
                ->typeId($blockType->id)
                ->status(null)
                ->all();

            foreach ($blocks as $block) {
                // Re-running shouldn't produce a second item.
                $already = Entry::find()
                    ->fieldId($itemsField->id)
                    ->ownerId($block->id)
                    ->status(null)
                    ->count();

                if ($already) {
                    continue;
                }

                $item = new Entry();
                $item->typeId = $itemType->id;
                $item->fieldId = $itemsField->id;
                $item->primaryOwnerId = $block->id;
                $item->setOwner($block);
                $item->siteId = $block->siteId;
                $item->sortOrder = 1;

                $buttons = [];

                foreach ($handles as $handle) {
                    $value = $block->getFieldValue($handle);

                    // Matrix values are nested elements with their own
                    // ownership rows — they get re-owned below, once the item
                    // has an id to own them.
                    if ($handle === 'buttons') {
                        $buttons = $value ? $value->status(null)->all() : [];
                        continue;
                    }

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
                        "Couldn't create the item for $blockHandle block #{$block->id}: " .
                        implode(', ', $item->getErrorSummary(true))
                    );
                }

                // 2. Re-own the buttons. Duplicated rather than re-pointed:
                //    the same attribute shape Craft's own NestedElementManager
                //    uses, so ownership rows are written by Craft rather than
                //    by hand. The originals are left on the block and become
                //    unreachable when the field leaves its layout below.
                foreach ($buttons as $i => $button) {
                    $elements->duplicateElement($button, [
                        'canonicalId' => null,
                        'primaryOwner' => $item,
                        'owner' => $item,
                        'siteId' => $item->siteId,
                        'sortOrder' => $i + 1,
                        'propagating' => false,
                        'resaving' => false,
                    ]);
                }

                $expected[$block->id] = count($buttons);
            }

            // 3. Swap the moved fields out for `items` on the block layout.
            $this->rewriteLayout($blockType, $handles, $itemsField);
        }

        // 4. Check the outcome rather than trusting the steps — see the note
        //    in m260819_120000 about content that looked fine and wasn't.
        $elements->invalidateAllCaches();

        foreach ($expected as $blockId => $buttonCount) {
            $item = Entry::find()
                ->fieldId($itemsField->id)
                ->ownerId($blockId)
                ->status(null)
                ->one();

            if (!$item) {
                throw new \Exception("Block #$blockId has no item after the move. Restore from the backup.");
            }

            $got = (int)$item->buttons->status(null)->count();
            if ($got !== $buttonCount) {
                throw new \Exception(
                    "Block #$blockId should have $buttonCount buttons on its item, got $got. " .
                    'Restore from the backup.'
                );
            }
        }

        return true;
    }

    /**
     * Replaces the moved fields with `items` in a block's field layout.
     *
     * `items` takes the position of the first field removed, so it lands where
     * the content used to be rather than at the end of the tab.
     */
    private function rewriteLayout(\craft\models\EntryType $blockType, array $handles, \craft\base\FieldInterface $itemsField): void
    {
        $layout = $blockType->getFieldLayout();
        $moving = array_flip($handles);
        $placed = $layout->getFieldByHandle('items') !== null;
        $changed = false;

        foreach ($layout->getTabs() as $tab) {
            $out = [];

            foreach ($tab->getElements() as $element) {
                if (
                    $element instanceof CustomField &&
                    isset($moving[$element->getField()->handle])
                ) {
                    $changed = true;

                    if (!$placed) {
                        $out[] = new CustomField($itemsField);
                        $placed = true;
                    }

                    continue;
                }

                $out[] = $element;
            }

            $tab->setElements($out);
        }

        if (!$changed) {
            return;
        }

        $blockType->setFieldLayout($layout);

        if (!Craft::$app->getEntries()->saveEntryType($blockType)) {
            throw new \Exception(
                "Couldn't rewrite the {$blockType->handle} field layout: " .
                implode(', ', $blockType->getErrorSummary(true))
            );
        }
    }

    public function safeDown(): bool
    {
        // Not reversible. Putting the fields back on the block is trivial;
        // working out which item belonged to which block once they're all the
        // same shared type is not, and a half-restored block reads as an empty
        // one. Restore from a database backup instead.
        return false;
    }
}
