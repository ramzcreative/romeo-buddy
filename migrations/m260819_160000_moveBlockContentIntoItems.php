<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\FieldInterface;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\models\EntryType;

/**
 * Phase 1 of putting cards, slider, imageText, banner and spotlight on one
 * shared `items` field: move the content, and only the content.
 *
 * This migration is deliberately ADDITIVE. It adds `items` where it's missing
 * and fills it; it does not remove the fields it copies from, delete the
 * `imageItems` field, or retire the `imageItem` entry type. Phase 2 does that,
 * in a later deploy, once this one has been verified in production.
 *
 * The split is not caution for its own sake. `php craft up` runs
 * project-config/apply BEFORE content migrations (see Craft's UpController).
 * So anything a migration does to the content model arrives on production
 * twice: once as project config, applied first, and once as the migration,
 * applied second. If the committed project config REMOVES a field, production
 * deletes that field — and a Matrix field takes its nested entries with it —
 * before the migration that was supposed to move the content ever runs.
 *
 * That is exactly what happened here, and it is invisible locally: running
 * `php craft migrate/up` by hand skips project config entirely, so the field
 * is always still there and the migration always works. It only fails on the
 * path nobody runs locally.
 *
 * Hence: phase 1's project config changes are all additions, which are safe to
 * apply before the migration. The destructive half waits until the content is
 * confirmed to be somewhere else.
 */
class m260819_160000_moveBlockContentIntoItems extends Migration
{
    /**
     * Blocks whose content lives on the block itself, and which of their
     * fields become item fields.
     *
     * `blockHeading` and the layout selectors are absent on purpose — those
     * describe the block, not the item.
     */
    private const BLOCK_FIELDS = [
        'banner' => ['image', 'heading', 'intro', 'buttons'],
        'spotlight' => ['image', 'heading', 'subheading', 'intro', 'buttons'],
    ];

    /** imageItem fields that carry over to `item`. */
    private const NESTED_FIELDS = ['image', 'preheading', 'heading', 'intro', 'text'];

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();

        $itemType = $entries->getEntryTypeByHandle('item');
        $itemsField = $fields->getFieldByHandle('items');

        if (!$itemType || !$itemsField) {
            throw new \Exception("The shared 'item' entry type or 'items' field is missing.");
        }

        // 1. `text` onto the shared item type — imageText's layouts render it.
        $textField = $fields->getFieldByHandle('text');
        if ($textField && !$itemType->getFieldLayout()->getFieldByHandle('text')) {
            $this->addField($itemType, $textField);
            $itemType = $entries->getEntryTypeByHandle('item');
        }

        // 2. `items` onto every block that needs it, alongside whatever it
        //    already has. Nothing is taken away here.
        foreach (['imageText', 'banner', 'spotlight'] as $handle) {
            $type = $entries->getEntryTypeByHandle($handle);
            if ($type && !$type->getFieldLayout()->getFieldByHandle('items')) {
                $this->addField($type, $itemsField);
            }
        }

        // 3. imageText: rebuild each nested imageItem as an item.
        $this->moveNested($itemType, $itemsField);

        // 4. banner + spotlight: their own fields become one item.
        foreach (self::BLOCK_FIELDS as $handle => $handles) {
            $this->moveBlockFields($handle, $handles, $itemType, $itemsField);
        }

        return true;
    }

    /**
     * imageText's `imageItems` entries, rebuilt as `item` entries under
     * `items` on the same block.
     *
     * Rebuilt rather than re-typed in place. Element content is keyed by field
     * layout ELEMENT uid, not field uid, so re-typing orphans every value and
     * the content has to be remapped by hand. Worse, re-typing means
     * hand-assigning `entries.fieldId`, and doing that on this site's sibling
     * boilerplate led to garbage collection soft-deleting three of four
     * entries about ten minutes later — long after the pages had rendered
     * correctly. Building new elements and letting Craft write the content
     * keys and the ownership rows avoids both. The cost is new element ids,
     * which is fine for nested page-builder content that nothing links to.
     */
    private function moveNested(EntryType $itemType, FieldInterface $itemsField): void
    {
        $imageItemsField = Craft::$app->getFields()->getFieldByHandle('imageItems');

        if (!$imageItemsField) {
            // Already retired, or never existed here. Either way there is
            // nothing to read — but say so rather than reporting success on a
            // migration that silently did nothing.
            echo "    > no 'imageItems' field; nothing to move for imageText\n";
            return;
        }

        $sources = Entry::find()
            ->fieldId($imageItemsField->id)
            ->status(null)
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();

        // Which owners were already done, sampled BEFORE anything is written.
        //
        // Checking this inside the loop looks equivalent and is not: the first
        // item created for a block makes its own count non-zero, so every
        // later source for that same block reads as "already done" and is
        // skipped. A block with two imageItems silently ended up with one.
        // The count has to be a snapshot of the starting state, not a live
        // read of a set this loop is mutating.
        $done = [];
        foreach ($sources as $source) {
            $ownerId = $source->getOwnerId();
            if ($ownerId !== null && !array_key_exists($ownerId, $done)) {
                $done[$ownerId] = $this->itemCount($itemsField, $ownerId) > 0;
            }
        }

        $expected = [];

        foreach ($sources as $i => $source) {
            $owner = $source->getOwner();
            if (!$owner || ($done[$owner->id] ?? false)) {
                continue;
            }

            $expected[$owner->id] = ($expected[$owner->id] ?? 0) + 1;

            $item = $this->newItem($itemType, $itemsField, $owner, $source->siteId);
            $item->sortOrder = $source->getSortOrder() ?? ($i + 1);
            $item->enabled = $source->enabled;

            foreach (self::NESTED_FIELDS as $handle) {
                if ($source->getFieldLayout()?->getFieldByHandle($handle)) {
                    $item->setFieldValue($handle, $this->readable($source->getFieldValue($handle)));
                }
            }

            $this->save($item, "imageItem #{$source->id}");
        }

        $this->verify($itemsField, $expected);
    }

    /**
     * A block's own fields, moved onto one item beneath it.
     */
    private function moveBlockFields(string $blockHandle, array $handles, EntryType $itemType, FieldInterface $itemsField): void
    {
        $blockType = Craft::$app->getEntries()->getEntryTypeByHandle($blockHandle);
        if (!$blockType) {
            return;
        }

        $layout = $itemType->getFieldLayout();
        foreach ($handles as $handle) {
            if (!$layout->getFieldByHandle($handle)) {
                throw new \Exception("The item entry type has no '$handle' field, which $blockHandle needs.");
            }
        }

        $expected = [];

        foreach (Entry::find()->typeId($blockType->id)->status(null)->all() as $block) {
            if ($this->itemCount($itemsField, $block->id) > 0) {
                continue;
            }

            $item = $this->newItem($itemType, $itemsField, $block, $block->siteId);
            $item->sortOrder = 1;
            $buttons = [];

            foreach ($handles as $handle) {
                if (!$block->getFieldLayout()?->getFieldByHandle($handle)) {
                    continue;
                }

                $value = $block->getFieldValue($handle);

                // Matrix values are nested elements with their own ownership
                // rows — re-owned below, once the item has an id to own them.
                if ($handle === 'buttons') {
                    $buttons = $value ? $value->status(null)->all() : [];
                    continue;
                }

                $item->setFieldValue($handle, $this->readable($value));
            }

            $this->save($item, "$blockHandle block #{$block->id}");
            $expected[$block->id] = 1;

            // Duplicated with the same attribute shape Craft's own
            // NestedElementManager uses, so the ownership rows are written by
            // Craft rather than by hand. The originals stay on the block until
            // phase 2 removes the field.
            foreach ($buttons as $i => $button) {
                Craft::$app->getElements()->duplicateElement($button, [
                    'canonicalId' => null,
                    'primaryOwner' => $item,
                    'owner' => $item,
                    'siteId' => $item->siteId,
                    'sortOrder' => $i + 1,
                    'propagating' => false,
                    'resaving' => false,
                ]);
            }
        }

        $this->verify($itemsField, $expected);
    }

    private function newItem(EntryType $itemType, FieldInterface $itemsField, Entry $owner, int $siteId): Entry
    {
        $item = new Entry();
        $item->typeId = $itemType->id;
        $item->fieldId = $itemsField->id;
        $item->primaryOwnerId = $owner->id;
        $item->setOwner($owner);
        $item->siteId = $siteId;

        return $item;
    }

    /** Relation fields hand back a query or a collection; the setter wants ids. */
    private function readable(mixed $value): mixed
    {
        if ($value instanceof \craft\elements\db\ElementQuery) {
            return $value->ids();
        }

        if ($value instanceof \craft\elements\ElementCollection) {
            return $value->map(fn($el) => $el->id)->all();
        }

        return $value;
    }

    private function save(Entry $item, string $what): void
    {
        if (!Craft::$app->getElements()->saveElement($item)) {
            throw new \Exception("Couldn't build an item from $what: " . implode(', ', $item->getErrorSummary(true)));
        }
    }

    /**
     * The item count for one owner.
     *
     * Both ids are passed straight through with no `?? 0` fallback, because a
     * falsy fieldId or ownerId does not narrow this query — it removes the
     * nested-element filtering altogether and matches EVERY entry on the site.
     * That is what turned the first version of this migration into a
     * site-wide delete. Anything that could be null must be checked before it
     * gets here, never defaulted to zero.
     */
    private function itemCount(FieldInterface $itemsField, int $ownerId): int
    {
        return (int)Entry::find()
            ->fieldId($itemsField->id)
            ->ownerId($ownerId)
            ->status(null)
            ->count();
    }

    /** @param array<int, int> $expected ownerId => item count */
    private function verify(FieldInterface $itemsField, array $expected): void
    {
        if (!$expected) {
            return;
        }

        Craft::$app->getElements()->invalidateAllCaches();

        foreach ($expected as $ownerId => $count) {
            $got = $this->itemCount($itemsField, $ownerId);

            if ($got !== $count) {
                throw new \Exception(
                    "Block #$ownerId should have $count item(s), got $got. Nothing has been " .
                    'deleted — the original fields are all still in place, so restore from a ' .
                    'backup only if you want the partial items gone.'
                );
            }
        }
    }

    private function addField(EntryType $type, FieldInterface $field): void
    {
        $layout = $type->getFieldLayout();
        $tabs = $layout->getTabs();
        $tab = $tabs[0] ?? null;

        if (!$tab) {
            throw new \Exception("The {$type->handle} field layout has no tabs.");
        }

        // setTabs() before setElements() — a tab that isn't attached to its
        // layout yet throws "Field layout tab is missing its field layout."
        $layout->setTabs($tabs);
        $tab->setElements(array_merge($tab->getElements(), [new CustomField($field)]));
        $type->setFieldLayout($layout);

        if (!Craft::$app->getEntries()->saveEntryType($type)) {
            throw new \Exception("Couldn't add '{$field->handle}' to {$type->handle}: " . implode(', ', $type->getErrorSummary(true)));
        }
    }

    public function safeDown(): bool
    {
        // Reversible in the sense that matters: this migration deletes
        // nothing, so the original fields and their values are all still
        // there. Removing the items it created is left to a backup restore
        // rather than guessed at here.
        return false;
    }
}
