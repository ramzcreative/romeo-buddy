<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;
use modules\themepicker\fields\ThemeOverride;
use modules\themepicker\fields\ThemeVariantKeep;

/**
 * The keep toggle for sitewide Theme variants, beside each page's variant field, and the `pageTheme` field's
 * new name. See craft-modules' docs/theme-variants-spec.md §4.3 and §4.6.
 *
 * Adopt-if-present and guarded: project config applies before content migrations, so the field or its
 * layout placement may already exist when this runs.
 */
class m260914_130000_addKeepThemeVariantField extends Migration
{
    private const FIELD_HANDLE = 'keepThemeVariant';
    private const VARIANT_FIELD_HANDLE = 'pageTheme';

    /** Entry type handle => the field the toggle goes directly after. */
    private const PLACEMENTS = [
        'page' => 'pageTheme',
        'landingPage' => 'themeOverride',
    ];

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $field = $fieldsService->getFieldByHandle(self::FIELD_HANDLE);

        if ($field !== null && !$field instanceof ThemeVariantKeep) {
            throw new \Exception("A '" . self::FIELD_HANDLE . "' field already exists and isn't a Keep variant field.");
        }

        if ($field === null) {
            $field = new ThemeVariantKeep([
                'name' => 'Keep this page’s look during sitewide variants',
                'handle' => self::FIELD_HANDLE,
                'instructions' => 'When a sitewide Theme variant is on, this page keeps its own variant, or the site theme if it has none.',
                'default' => false,
            ]);

            if (!$fieldsService->saveField($field)) {
                throw new \Exception("Couldn't save the '" . self::FIELD_HANDLE . "' field: " . implode(', ', $field->getErrorSummary(true)));
            }
        }

        foreach (self::PLACEMENTS as $entryTypeHandle => $anchorHandle) {
            $this->placeAfter($entryTypeHandle, $anchorHandle, $field);
        }

        $variantField = $fieldsService->getFieldByHandle(self::VARIANT_FIELD_HANDLE);

        if ($variantField instanceof ThemeOverride && $variantField->name !== 'Theme Variant') {
            $variantField->name = 'Theme Variant';

            if (!$fieldsService->saveField($variantField)) {
                throw new \Exception("Couldn't rename the '" . self::VARIANT_FIELD_HANDLE . "' field: " . implode(', ', $variantField->getErrorSummary(true)));
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $field = $fieldsService->getFieldByHandle(self::FIELD_HANDLE);

        // A null match must never reach deleteField(): see the stables CLAUDE.md on the migration that wiped
        // a production site.
        if ($field instanceof ThemeVariantKeep) {
            // Out of the layouts first: a layout pointing at a deleted field throws on the next read.
            foreach (array_keys(self::PLACEMENTS) as $entryTypeHandle) {
                $this->removeFrom($entryTypeHandle);
            }

            $fieldsService->deleteField($field);
        }

        $variantField = $fieldsService->getFieldByHandle(self::VARIANT_FIELD_HANDLE);

        if ($variantField instanceof ThemeOverride && $variantField->name === 'Theme Variant') {
            $variantField->name = 'Page Theme';
            $fieldsService->saveField($variantField);
        }

        return true;
    }

    /**
     * Puts the field directly after the anchor, in the anchor's tab. Skips an entry type that doesn't exist or
     * already has the field; a layout without the anchor gets it at the end of its first tab.
     */
    private function placeAfter(string $entryTypeHandle, string $anchorHandle, ThemeVariantKeep $field): void
    {
        $entriesService = Craft::$app->getEntries();
        $entryType = $entriesService->getEntryTypeByHandle($entryTypeHandle);

        if ($entryType === null) {
            return;
        }

        $fieldLayout = $entryType->getFieldLayout();
        $tabs = $fieldLayout->getTabs();

        if ($tabs === []) {
            return;
        }

        foreach ($tabs as $tab) {
            foreach ($tab->getElements() as $element) {
                if ($this->handleOf($element) === self::FIELD_HANDLE) {
                    return;
                }
            }
        }

        $targetTab = $tabs[array_key_first($tabs)];
        $insertAt = null;

        foreach ($tabs as $tab) {
            foreach (array_values($tab->getElements()) as $i => $element) {
                if ($this->handleOf($element) === $anchorHandle) {
                    $targetTab = $tab;
                    $insertAt = $i + 1;
                    break 2;
                }
            }
        }

        // Half width, so it pairs with the variant field or sits beside it rather than splitting a row.
        $element = new CustomField($field);
        $element->width = 50;

        $elements = array_values($targetTab->getElements());
        array_splice($elements, $insertAt ?? count($elements), 0, [$element]);

        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $fieldLayout->setTabs($tabs);
        $targetTab->setElements($elements);
        $entryType->setFieldLayout($fieldLayout);

        if (!$entriesService->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the '{$entryTypeHandle}' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
        }
    }

    private function removeFrom(string $entryTypeHandle): void
    {
        $entriesService = Craft::$app->getEntries();
        $entryType = $entriesService->getEntryTypeByHandle($entryTypeHandle);

        if ($entryType === null) {
            return;
        }

        $fieldLayout = $entryType->getFieldLayout();
        $tabs = $fieldLayout->getTabs();
        $changed = false;

        foreach ($tabs as $tab) {
            $kept = array_values(array_filter(
                $tab->getElements(),
                fn(mixed $element): bool => $this->handleOf($element) !== self::FIELD_HANDLE,
            ));

            if (count($kept) !== count($tab->getElements())) {
                $changed = true;
                $fieldLayout->setTabs($tabs);
                $tab->setElements($kept);
            }
        }

        if ($changed) {
            $entryType->setFieldLayout($fieldLayout);
            $entriesService->saveEntryType($entryType);
        }
    }

    /**
     * A layout element's field handle, or null. CustomField::getField() throws for a deleted field, so it has
     * to be caught.
     */
    private function handleOf(mixed $element): ?string
    {
        if (!$element instanceof CustomField) {
            return null;
        }

        try {
            return $element->getField()->handle;
        } catch (\Throwable) {
            return null;
        }
    }
}
