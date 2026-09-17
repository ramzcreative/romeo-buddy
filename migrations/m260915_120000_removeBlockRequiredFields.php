<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Matrix;
use craft\models\FieldLayout;

/**
 * Page-builder blocks require nothing: any field can then be hidden per theme, and templates render nothing for an
 * empty block. See docs/theme-designer-blocks-spec.md §3.
 *
 * Flips flags on the existing layout elements and field, never rebuilding a layout (content is keyed by layout
 * element uid), and asserts both afterwards. Also drops stale Title elements from layouts whose hasTitleField is
 * false. Section entry types keep their required fields.
 */
class m260915_120000_removeBlockRequiredFields extends Migration
{
    /** Entry type handle => field handle whose layout element stops being required. */
    private const REQUIRED = [
        'blockquote' => 'textPlain',
        'image' => 'image',
        'video' => 'video',
        'accordionItem' => 'heading',
    ];

    private const ITEMS_FIELD = 'items';
    private const ITEMS_MIN_ENTRIES = 1;

    public function safeUp(): bool
    {
        foreach (self::REQUIRED as $entryTypeHandle => $fieldHandle) {
            $this->setRequired($entryTypeHandle, $fieldHandle, false);
        }

        $this->setItemsMinEntries(null);

        return true;
    }

    public function safeDown(): bool
    {
        foreach (self::REQUIRED as $entryTypeHandle => $fieldHandle) {
            $this->setRequired($entryTypeHandle, $fieldHandle, true);
        }

        $this->setItemsMinEntries(self::ITEMS_MIN_ENTRIES);

        return true;
    }

    /**
     * Skips an entry type or field that doesn't exist, or a flag already in the wanted state (project config may
     * have applied it first).
     */
    private function setRequired(string $entryTypeHandle, string $fieldHandle, bool $required): void
    {
        $entriesService = Craft::$app->getEntries();
        $entryType = $entriesService->getEntryTypeByHandle($entryTypeHandle);

        if ($entryType === null) {
            return;
        }

        $fieldLayout = $entryType->getFieldLayout();
        $element = $this->elementFor($fieldLayout->getCustomFieldElements(), $fieldHandle);

        if ($element === null || $element->required === $required) {
            return;
        }

        $uid = $element->uid;
        $hadTitleField = $entryType->hasTitleField;
        $element->required = $required;

        // saveEntryType() sets hasTitleField from whether the layout holds a Title element. Three of these layouts
        // kept one while hasTitleField is false, so saving would add a required Title to the block.
        if (!$hadTitleField) {
            $this->removeTitleElements($fieldLayout);
        }

        $entryType->setFieldLayout($fieldLayout);

        if (!$entriesService->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the '{$entryTypeHandle}' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        $savedType = $entriesService->getEntryTypeById($entryType->id);
        $saved = $this->elementFor($savedType->getFieldLayout()->getCustomFieldElements(), $fieldHandle);

        if ($saved === null || $saved->uid !== $uid || $saved->required !== $required) {
            throw new \Exception("'{$entryTypeHandle}.{$fieldHandle}' didn't save as expected: its layout element changed or kept its required flag.");
        }

        if ($savedType->hasTitleField !== $hadTitleField) {
            throw new \Exception("'{$entryTypeHandle}' changed hasTitleField on save.");
        }
    }

    private function removeTitleElements(FieldLayout $fieldLayout): void
    {
        $tabs = $fieldLayout->getTabs();

        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $fieldLayout->setTabs($tabs);

        foreach ($tabs as $tab) {
            $kept = array_values(array_filter(
                $tab->getElements(),
                static fn(mixed $element): bool => !$element instanceof EntryTitleField,
            ));

            if (count($kept) !== count($tab->getElements())) {
                $tab->setElements($kept);
            }
        }
    }

    private function setItemsMinEntries(?int $minEntries): void
    {
        $fieldsService = Craft::$app->getFields();
        $field = $fieldsService->getFieldByHandle(self::ITEMS_FIELD);

        if (!$field instanceof Matrix || $field->minEntries === $minEntries) {
            return;
        }

        $field->minEntries = $minEntries;

        if (!$fieldsService->saveField($field)) {
            throw new \Exception("Couldn't save the '" . self::ITEMS_FIELD . "' field: " . implode(', ', $field->getErrorSummary(true)));
        }
    }

    /**
     * @param CustomField[] $elements
     */
    private function elementFor(array $elements, string $fieldHandle): ?CustomField
    {
        foreach ($elements as $element) {
            try {
                if ($element->getField()->handle === $fieldHandle) {
                    return $element;
                }
            } catch (\Throwable) {
                // A layout element whose field was deleted can't be the one we want.
            }
        }

        return null;
    }
}
