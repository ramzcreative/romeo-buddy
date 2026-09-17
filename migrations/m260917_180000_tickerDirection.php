<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;
use verbb\buttonbox\fields\Buttons;

/**
 * `tickerDirection` (left, right) on the `logos` and `gallery` blocks, straight after their layout picker; Base's
 * blockfields.json shows it on the ticker layout only. `left` is the default and what a ticker did before.
 *
 * Adopt-if-present, and every layout save is checked to keep its existing element uids — content is keyed by them.
 */
class m260917_180000_tickerDirection extends Migration
{
    private const BLOCKS = ['logos' => 'layoutLogos', 'gallery' => 'layoutGallery'];

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();

        $field = $fields->getFieldByHandle('tickerDirection') ?? new Buttons([
            'name' => 'Ticker Direction',
            'handle' => 'tickerDirection',
            'instructions' => 'Which way the row moves.',
            'displayAsGraphic' => false,
            'options' => [
                ['label' => 'Left', 'showLabel' => '1', 'value' => 'left', 'imageUrl' => '', 'imageAlign' => 'left', 'default' => '1'],
                ['label' => 'Right', 'showLabel' => '1', 'value' => 'right', 'imageUrl' => '', 'imageAlign' => 'left', 'default' => ''],
            ],
        ]);

        if (!$field->id && !$fields->saveField($field)) {
            throw new \Exception("Couldn't save the 'tickerDirection' field: " . implode(', ', $field->getErrorSummary(true)));
        }

        foreach (self::BLOCKS as $typeHandle => $after) {
            $this->attach($typeHandle, $field, $after);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $entries = Craft::$app->getEntries();

        foreach (array_keys(self::BLOCKS) as $typeHandle) {
            $type = $entries->getEntryTypeByHandle($typeHandle);

            if ($type === null) {
                continue;
            }

            $layout = $type->getFieldLayout();
            $tabs = $layout->getTabs();

            foreach ($tabs as $tab) {
                $tab->setElements(array_values(array_filter(
                    $tab->getElements(),
                    static fn($el) => !($el instanceof CustomField && $el->getField()->handle === 'tickerDirection')
                )));
            }

            $layout->setTabs($tabs);
            $type->setFieldLayout($layout);
            $entries->saveEntryType($type);
        }

        // A null match must never reach a delete: see the stables CLAUDE.md on the migration that wiped a production site.
        $field = Craft::$app->getFields()->getFieldByHandle('tickerDirection');

        if ($field instanceof Buttons) {
            Craft::$app->getFields()->deleteField($field);
        }

        return true;
    }

    private function attach(string $typeHandle, \craft\base\FieldInterface $field, string $after): void
    {
        $entries = Craft::$app->getEntries();
        $type = $entries->getEntryTypeByHandle($typeHandle);

        if ($type === null) {
            echo "  > No '{$typeHandle}' entry type on this site — skipped.\n";

            return;
        }

        $layout = $type->getFieldLayout();

        if ($layout->getFieldByHandle('tickerDirection')) {
            return;
        }

        $tabs = $layout->getTabs();
        $target = null;
        $insertAt = 0;

        foreach ($tabs as $tab) {
            foreach (array_values($tab->getElements()) as $i => $element) {
                if ($element instanceof CustomField && $element->getField()->handle === $after) {
                    $target = $tab;
                    $insertAt = $i + 1;
                    break 2;
                }
            }
        }

        $target ??= $tabs[array_key_first($tabs)];
        $elements = array_values($target->getElements());
        $uidsBefore = array_map(static fn(CustomField $el) => $el->uid, $layout->getCustomFieldElements());
        array_splice($elements, $insertAt, 0, [new CustomField($field)]);

        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs($tabs);
        $target->setElements($elements);
        $type->setFieldLayout($layout);

        if (!$entries->saveEntryType($type)) {
            throw new \Exception("Couldn't add tickerDirection to '{$typeHandle}': " . implode(', ', $type->getErrorSummary(true)));
        }

        $saved = $entries->getEntryTypeById($type->id);
        $uidsAfter = array_map(static fn(CustomField $el) => $el->uid, $saved->getFieldLayout()->getCustomFieldElements());

        if (array_diff($uidsBefore, $uidsAfter) !== []) {
            throw new \Exception("Adding tickerDirection to '{$typeHandle}' changed its existing layout; stopped.");
        }

        echo "  > Added tickerDirection to '{$typeHandle}'.\n";
    }
}
