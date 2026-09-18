<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;
use modules\eventdates\fields\Repeat;

/**
 * Puts the Repeats field on the `event` entry type, next to the dates it modifies — the point at which recurrence
 * stops being dormant and an editor can make an event repeat (docs/recurring-events-spec.md, step 5).
 *
 * `eventStart`/`eventEnd` keep their meaning for a one-off event. For a repeating one they describe the FIRST
 * occurrence: its start, and its end, whose difference is every occurrence's length. Their instructions are updated
 * to say so, because that reading isn't obvious from the labels.
 *
 * No data migration: every existing event has no rule, which is exactly "does not repeat", and its single
 * occurrence row is already correct.
 */
class m260918_160000_addEventRepeatField extends Migration
{
    private const TYPE = 'event';
    private const HANDLE = 'eventRepeat';

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();
        $type = $entries->getEntryTypeByHandle(self::TYPE);

        if ($type === null) {
            echo "  > No '" . self::TYPE . "' entry type on this site — nothing to do.\n";

            return true;
        }

        $field = $fields->getFieldByHandle(self::HANDLE);

        if ($field === null) {
            $field = new Repeat();
            $field->name = 'Repeats';
            $field->handle = self::HANDLE;
            $field->instructions = 'Leave as “Does not repeat” for a one-off event.';

            if (!$fields->saveField($field)) {
                throw new \Exception("Couldn't save the repeat field: " . implode(', ', $field->getErrorSummary(true)));
            }

            echo "  > Created the '" . self::HANDLE . "' field.\n";
        }

        $this->explainDates($fields);

        $layout = $type->getFieldLayout();

        if ($layout->getFieldByHandle(self::HANDLE)) {
            echo "  > '" . self::TYPE . "' already has it.\n";

            return true;
        }

        $tabs = $layout->getTabs();
        $target = $tabs[0] ?? null;

        if ($target === null) {
            throw new \Exception("'" . self::TYPE . "' has no field layout tabs.");
        }

        // Straight after the end date: the three of them are one idea, and a rule read before the dates it repeats
        // makes no sense.
        $elements = [];

        foreach ($target->getElements() as $element) {
            $elements[] = $element;

            if ($element instanceof CustomField && $element->getField()->handle === 'eventEnd') {
                $elements[] = new CustomField($field);
            }
        }

        if (count($elements) === count($target->getElements())) {
            $elements[] = new CustomField($field);
        }

        $layout->setTabs($tabs);
        $target->setElements($elements);
        $type->setFieldLayout($layout);

        if (!$entries->saveEntryType($type)) {
            throw new \Exception("Couldn't add " . self::HANDLE . ": " . implode(', ', $type->getErrorSummary(true)));
        }

        if (!$entries->getEntryTypeById($type->id)->getFieldLayout()->getFieldByHandle(self::HANDLE)) {
            throw new \Exception('Saved without ' . self::HANDLE . ' on the layout; stopped.');
        }

        echo "  > Added " . self::HANDLE . " to '" . self::TYPE . "'.\n";

        return true;
    }

    /** What the two date fields mean once an event can repeat. */
    private function explainDates(\craft\services\Fields $fields): void
    {
        $notes = [
            'eventStart' => 'When it starts. For a repeating event, when the FIRST one starts.',
            'eventEnd' => 'When it ends. For a repeating event, when the first one ends — the gap between the two is how long each occurrence lasts.',
        ];

        foreach ($notes as $handle => $instructions) {
            $field = $fields->getFieldByHandle($handle);

            if ($field && $field->instructions !== $instructions) {
                $field->instructions = $instructions;
                $fields->saveField($field);
                echo "  > Updated {$handle}'s instructions.\n";
            }
        }
    }

    public function safeDown(): bool
    {
        echo "  > m260918_160000_addEventRepeatField can be reverted by removing the field in the CP.\n";

        return false;
    }
}
