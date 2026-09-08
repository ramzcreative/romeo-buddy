<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;

/**
 * Removes the 'Slant' option from the slider layout picker.
 *
 * The layout it selected has been deleted — it was a 1.5-slides-per-view
 * material carousel that the rebuilt Carousel layout now covers, and its
 * template had been broken for a while: it included a partial that does not
 * exist, through an argument list with a missing value, so anything rendering
 * it errored rather than degrading.
 *
 * Order matters. Any block still set to 'slants' is moved to 'carousels'
 * FIRST, while the option is still valid — a block left on a value the field
 * no longer offers keeps that value, and the template lookup for it would
 * throw. Nothing on stables uses it, but a site branched from here might.
 */
class m260908_170000_removeSlantsSliderLayout extends Migration
{
    private const FIELD_HANDLE = 'layoutSliders';
    private const ENTRY_TYPE_HANDLE = 'slider';
    private const REMOVED = 'slants';
    private const REPLACEMENT = 'carousels';

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $field = $fieldsService->getFieldByHandle(self::FIELD_HANDLE);

        if (!$field) {
            // Nothing to do — a site that never had the picker.
            return true;
        }

        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle(self::ENTRY_TYPE_HANDLE);

        if ($entryType) {
            $this->rehomeBlocks($entryType->id);
        }

        $options = array_values(array_filter(
            $field->options,
            fn($option) => ($option['value'] ?? null) !== self::REMOVED,
        ));

        if (count($options) === count($field->options)) {
            // Already gone.
            return true;
        }

        $field->options = $options;

        if (!$fieldsService->saveField($field)) {
            throw new \Exception("Couldn't save the '" . self::FIELD_HANDLE . "' field: " . implode(', ', $field->getErrorSummary(true)));
        }

        return true;
    }

    /**
     * Moves every slider block still on the removed layout, drafts included —
     * a provisional draft carrying it would break the moment someone opened
     * the entry.
     */
    private function rehomeBlocks(int $entryTypeId): void
    {
        $elements = Craft::$app->getElements();

        $blocks = Entry::find()
            ->typeId($entryTypeId)
            ->status(null)
            ->drafts(null)
            ->siteId('*')
            ->unique(false)
            ->limit(null)
            ->all();

        $moved = 0;

        foreach ($blocks as $block) {
            $value = $block->getFieldValue(self::FIELD_HANDLE);

            if ((string)($value->value ?? '') !== self::REMOVED) {
                continue;
            }

            $block->setFieldValue(self::FIELD_HANDLE, self::REPLACEMENT);

            // No validation: these are nested blocks whose owner is not being
            // saved, and a required field elsewhere on the block should not
            // stop a layout value being corrected.
            if (!$elements->saveElement($block, false)) {
                throw new \Exception("Couldn't move slider block {$block->id} off the '" . self::REMOVED . "' layout.");
            }

            $moved += 1;
        }

        if ($moved) {
            echo "    > moved $moved slider block(s) from '" . self::REMOVED . "' to '" . self::REPLACEMENT . "'\n";
        }
    }

    public function safeDown(): bool
    {
        echo "m260908_170000_removeSlantsSliderLayout cannot be reverted — the layout's template and styles are gone.\n";

        return false;
    }
}
