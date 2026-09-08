<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Dropdown;

/**
 * Adds a 'Slider Navigation' dropdown to the 'slider' entry type, beside the
 * layout selector, so an editor picks how a hero slider is navigated.
 *
 * A lightswitch was the first idea, but there are two distinct navigations
 * rather than one that is on or off — a thumbnail strip, or tabs carrying a
 * label and a progress bar — so a switch could not express it without a second
 * field. Arrows are deliberately not part of the choice: they are the keyboard
 * and screen-reader affordance and stay in all three.
 *
 * Defaults to thumbnails, matching what the hero layout was designed around.
 */
class m260908_120000_addSliderNavField extends Migration
{
    private const FIELD_HANDLE = 'sliderNav';
    private const ENTRY_TYPE_HANDLE = 'slider';
    private const AFTER_FIELD_HANDLE = 'layoutSliders';

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();

        $field = $fieldsService->getFieldByHandle(self::FIELD_HANDLE);

        if (!$field) {
            $field = new Dropdown([
                'name' => 'Slider Navigation',
                'handle' => self::FIELD_HANDLE,
                'instructions' => 'How this slider is navigated. Arrows are always shown.',
                'options' => [
                    ['label' => 'None', 'value' => 'none', 'default' => ''],
                    ['label' => 'Thumbnails', 'value' => 'thumbnails', 'default' => '1'],
                    ['label' => 'Tabs', 'value' => 'tabs', 'default' => ''],
                ],
            ]);

            if (!$fieldsService->saveField($field)) {
                throw new \Exception("Couldn't save the 'sliderNav' field: " . implode(', ', $field->getErrorSummary(true)));
            }
        }

        $entryType = $entriesService->getEntryTypeByHandle(self::ENTRY_TYPE_HANDLE);

        if (!$entryType) {
            throw new \Exception("Couldn't find the 'slider' entry type — expected it to already exist.");
        }

        $fieldLayout = $entryType->getFieldLayout();
        $tabs = $fieldLayout->getTabs();

        // Every tab, not just the one this migration would add to. Project
        // config applies BEFORE content migrations, so on any environment that
        // deploys the config the field is already in the layout — and on
        // stables it has since been moved to Content. Checking only the target
        // tab would see none there and add a second copy.
        foreach ($tabs as $tab) {
            foreach ($tab->getElements() as $element) {
                if ($element instanceof CustomField && $element->getField()?->handle === self::FIELD_HANDLE) {
                    return true;
                }
            }
        }

        if (!$tabs) {
            throw new \Exception("The 'slider' entry type has no field layout tabs.");
        }

        // Placed straight after the layout selector, wherever that lives: the
        // nav only shows on the hero layout (generated CSS, see
        // config/stables/blockfields.php), so it belongs beside the control
        // that reveals it. Naming a tab instead would be brittle — a site is
        // free to have reorganised or removed one.
        $targetTab = reset($tabs);
        $elements = array_values($targetTab->getElements());
        $insertAt = count($elements);

        foreach ($tabs as $tab) {
            foreach (array_values($tab->getElements()) as $i => $element) {
                if ($element instanceof CustomField && $element->getField()?->handle === self::AFTER_FIELD_HANDLE) {
                    $targetTab = $tab;
                    $elements = array_values($tab->getElements());
                    $insertAt = $i + 1;
                    break 2;
                }
            }
        }

        array_splice($elements, $insertAt, 0, [new CustomField($field)]);

        // The tab already belongs to this layout (it came from getTabs()), so
        // setElements() is safe here — see the root CLAUDE.md note.
        $targetTab->setElements($elements);
        $fieldLayout->setTabs($tabs);
        $entryType->setFieldLayout($fieldLayout);

        if (!$entriesService->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'slider' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        // Removes the field entirely, which also drops it from the layout.
        $field = Craft::$app->getFields()->getFieldByHandle(self::FIELD_HANDLE);

        if ($field) {
            Craft::$app->getFields()->deleteField($field);
        }

        return true;
    }
}
