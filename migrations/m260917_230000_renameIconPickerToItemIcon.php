<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use modules\iconpicker\fields\IconPicker;

/**
 * Renames this site's `iconPicker` field to `itemIcon`, the handle the boilerplate uses (stables
 * docs/romeo-buddy-port-plan.md, D3). Same field, same settings, same icon sets — only the handle and label change, so
 * every shared template and block-field rule that names `itemIcon` works here without a fork.
 *
 * Content is stored per layout element uid, not per handle, so the values ride along untouched. This checks that: it
 * records every item's icon before the rename and compares after, and throws (rolling the migration back) if one moved.
 */
class m260917_230000_renameIconPickerToItemIcon extends Migration
{
    private const OLD = 'iconPicker';
    private const NEW = 'itemIcon';

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $field = $fields->getFieldByHandle(self::OLD);

        if ($field === null) {
            echo "  > No '" . self::OLD . "' field on this site — nothing to rename.\n";

            return true;
        }

        if (!$field instanceof IconPicker) {
            throw new \Exception("'" . self::OLD . "' isn't an Icon Picker field; stopped rather than renaming it.");
        }

        if ($fields->getFieldByHandle(self::NEW) !== null) {
            throw new \Exception("This site already has an '" . self::NEW . "' field; stopped rather than creating a second one.");
        }

        $before = $this->values($field->id);

        $field->handle = self::NEW;
        $field->name = 'Icon';

        if (!$fields->saveField($field)) {
            throw new \Exception("Couldn't rename '" . self::OLD . "': " . implode(', ', $field->getErrorSummary(true)));
        }

        $after = $this->values($field->id);

        if ($before !== $after) {
            throw new \Exception('Icon values changed during the rename; nothing was kept. Before: ' . json_encode($before) . ' after: ' . json_encode($after));
        }

        echo '  > Renamed ' . self::OLD . ' to ' . self::NEW . '; ' . count(array_filter($before)) . " icon value(s) intact.\n";

        return true;
    }

    public function safeDown(): bool
    {
        $fields = Craft::$app->getFields();
        $field = $fields->getFieldByHandle(self::NEW);

        if ($field instanceof IconPicker) {
            $field->handle = self::OLD;
            $field->name = 'Icon Picker';
            $fields->saveField($field);
        }

        return true;
    }

    /**
     * Every entry's icon for this field, by entry id — read through the layout so it doesn't depend on the handle.
     *
     * @return array<int, string>
     */
    private function values(int $fieldId): array
    {
        $values = [];

        foreach (Entry::find()->status(null)->drafts(null)->provisionalDrafts(null)->site('*')->unique()->all() as $entry) {
            $layout = $entry->getFieldLayout();

            if ($layout === null) {
                continue;
            }

            foreach ($layout->getCustomFieldElements() as $element) {
                if ($element->getField()->id === $fieldId) {
                    $values[$entry->id] = (string)$entry->getFieldValue($element->getField()->handle);
                }
            }
        }

        ksort($values);

        return $values;
    }
}
