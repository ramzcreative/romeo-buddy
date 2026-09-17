<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;
use modules\themepicker\fields\ThemeOverride;
use modules\themepicker\services\ThemeRegistry;

/**
 * Lets an ordinary page pick a page theme — a set of colors and marks
 * layered over whichever site theme is active (see craft-modules'
 * docs/page-themes-spec.md).
 *
 * Deliberately restricted to page themes via allowedTypes. A site theme
 * swaps the whole bundle, which only a section built for it can do — that
 * stays the landing pages' `themeOverride` field, which is left alone here
 * and keeps offering both kinds.
 *
 * Placed to match that field: in the Settings tab, immediately after
 * `showFooterForm`, which is where a landing page's Theme Override sits.
 * Both answer the same question ("how does this page look?"), so an editor
 * should find them in the same place rather than having to learn that one
 * of them lives somewhere else.
 */
class m260909_220000_addPageThemeField extends Migration
{
    private const FIELD_HANDLE = 'pageTheme';
    private const ENTRY_TYPE_HANDLE = 'page';

    /** Insert immediately after this field, mirroring landingPage's own Theme Override placement. */
    private const ANCHOR_HANDLE = 'showFooterForm';

    /**
     * A layout element's field handle, or null if it isn't a custom field
     * or its field no longer exists.
     *
     * CustomField::getField() THROWS FieldNotFoundException for a deleted
     * field rather than returning null, so `?->handle` is not a guard —
     * any scan over a layout has to catch. A layout that has ever had a
     * field deleted out from under it will otherwise take this migration
     * down with it.
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

    private function isOrphan(mixed $element): bool
    {
        return $element instanceof CustomField && $this->handleOf($element) === null;
    }

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();

        $field = $fieldsService->getFieldByHandle(self::FIELD_HANDLE);

        if (!$field) {
            $field = new ThemeOverride([
                'name' => 'Page Theme',
                'handle' => self::FIELD_HANDLE,
                'allowedTypes' => [ThemeRegistry::TYPE_PAGE],
            ]);

            if (!$fieldsService->saveField($field)) {
                throw new \Exception("Couldn't save the 'pageTheme' field: " . implode(', ', $field->getErrorSummary(true)));
            }
        }

        $entryType = $entriesService->getEntryTypeByHandle(self::ENTRY_TYPE_HANDLE);

        if (!$entryType) {
            throw new \Exception("Couldn't find the 'page' entry type.");
        }

        $fieldLayout = $entryType->getFieldLayout();
        $tabs = $fieldLayout->getTabs();

        foreach ($tabs as $tab) {
            foreach ($tab->getElements() as $element) {
                if ($this->handleOf($element) === self::FIELD_HANDLE) {
                    return true;
                }
            }
        }

        if (!$tabs) {
            throw new \Exception("The 'page' entry type has no field layout tabs to add to.");
        }

        // Land it beside the field it's a sibling of: a landing page's
        // Theme Override sits immediately after showFooterForm in
        // Settings, so a page's Page Theme goes in the same place. Found
        // by handle rather than by tab name or index, so a site that has
        // renamed or reordered its tabs still gets it right; a site
        // without that field at all falls back to the end of the first
        // tab, which is still Settings.
        $targetTab = $tabs[array_key_first($tabs)];
        $insertAt = null;

        // Sweep dead references first — a layout element whose field has
        // been deleted can never render, and leaving one in place would
        // also shift the insert index computed below. Cheap, and makes
        // this migration safe to re-run after a failed attempt.
        $sweptTabs = [];

        foreach ($tabs as $tab) {
            $kept = array_values(array_filter(
                $tab->getElements(),
                fn(mixed $element): bool => !$this->isOrphan($element)
            ));

            if (count($kept) !== count($tab->getElements())) {
                $sweptTabs[spl_object_id($tab)] = $kept;
            }
        }

        foreach ($tabs as $tab) {
            foreach (array_values($sweptTabs[spl_object_id($tab)] ?? $tab->getElements()) as $i => $element) {
                if ($this->handleOf($element) === self::ANCHOR_HANDLE) {
                    $targetTab = $tab;
                    $insertAt = $i + 1;
                    break 2;
                }
            }
        }

        $elements = array_values($sweptTabs[spl_object_id($targetTab)] ?? $targetTab->getElements());
        array_splice($elements, $insertAt ?? count($elements), 0, [new CustomField($field)]);

        // setElements() only after setTabs() — a tab that isn't attached to
        // its layout yet throws "Field layout tab is missing its field
        // layout." See the boilerplate's CLAUDE.md.
        $fieldLayout->setTabs($tabs);

        foreach ($tabs as $tab) {
            if (isset($sweptTabs[spl_object_id($tab)]) && $tab !== $targetTab) {
                $tab->setElements($sweptTabs[spl_object_id($tab)]);
            }
        }

        $targetTab->setElements($elements);
        $entryType->setFieldLayout($fieldLayout);

        if (!$entriesService->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'page' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        $field = Craft::$app->getFields()->getFieldByHandle(self::FIELD_HANDLE);

        // getFieldByHandle() returning null must not become deleteField(null)
        // or anything else that treats "no match" as "match everything" —
        // see the boilerplate's CLAUDE.md on the migration that wiped a
        // production site exactly that way.
        if (!$field instanceof ThemeOverride) {
            return true;
        }

        // Take the layout element out BEFORE the field: deleting the field
        // first leaves the entry type pointing at a UID that no longer
        // resolves, and CustomField::getField() throws on it — which is
        // exactly what broke a re-run of this migration during development.
        $entriesService = Craft::$app->getEntries();
        $entryType = $entriesService->getEntryTypeByHandle(self::ENTRY_TYPE_HANDLE);

        if ($entryType) {
            $fieldLayout = $entryType->getFieldLayout();
            $tabs = $fieldLayout->getTabs();
            $changed = false;

            foreach ($tabs as $tab) {
                $kept = array_values(array_filter(
                    $tab->getElements(),
                    fn(mixed $element): bool => $this->handleOf($element) !== self::FIELD_HANDLE
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

        Craft::$app->getFields()->deleteField($field);

        return true;
    }
}
