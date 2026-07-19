<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use modules\themepicker\fields\ThemeOverride;

/**
 * Adds a 'Landing Page' entry type — a copy of 'Page''s field layout (same
 * shared 'heading'/'excerpt'/'image'/'pageBuilder'/'seo' fields, so existing
 * content-editing conventions carry over unchanged) plus a new 'Theme
 * Override' field. Kept as its own entry type rather than added to 'Page'
 * itself so the theme override only ever surfaces on landing pages, not
 * every page on the site — see modules/themepicker/services/ThemeRegistry
 * ::resolveActiveThemeHandle() (craft-modules) for how it's consumed.
 *
 * The 'landingPages' section itself may already exist (created via the CP
 * while prototyping this) — safeUp() adopts it if so, only creating it
 * fresh when it doesn't, so this migration is safe to run on any site.
 */
class m260718_190000_addLandingPageEntryType extends Migration
{
    private const SECTION_HANDLE = 'landingPages';
    private const ENTRY_TYPE_HANDLE = 'landingPage';
    private const FIELD_HANDLE = 'themeOverride';

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();

        if (!$fieldsService->getFieldByHandle(self::FIELD_HANDLE)) {
            $field = new ThemeOverride([
                'name' => 'Theme Override',
                'handle' => self::FIELD_HANDLE,
                'instructions' => 'Renders this landing page with a different theme than the sitewide active one. Leave blank to inherit the sitewide theme.',
            ]);

            if (!$fieldsService->saveField($field)) {
                throw new \Exception("Couldn't save the 'themeOverride' field: " . implode(', ', $field->getErrorSummary(true)));
            }
        }

        // Same adopt-if-present pattern as the section below — running
        // `php craft up` (the correct production deploy command) applies
        // pending project config *before* content migrations run, and
        // this entry type's field layout was already captured into
        // config/project/entryTypes/*.yaml the first time this migration
        // ran (in dev), then committed — so project-config/apply may well
        // have already created it by the time we get here. Skipping the
        // whole layout rebuild in that case isn't just an optimization:
        // building a second EntryType object and calling saveEntryType()
        // unconditionally fails validation ("Handle already taken") if
        // one already exists, which is exactly what happened on this
        // migration's first production deploy attempt.
        $entryType = $entriesService->getEntryTypeByHandle(self::ENTRY_TYPE_HANDLE);

        if (!$entryType) {
            $headingField = $fieldsService->getFieldByHandle('heading');
            $excerptField = $fieldsService->getFieldByHandle('excerpt');
            $imageField = $fieldsService->getFieldByHandle('image');
            $pageBuilderField = $fieldsService->getFieldByHandle('pageBuilder');
            $seoField = $fieldsService->getFieldByHandle('seo');
            $themeOverrideField = $fieldsService->getFieldByHandle(self::FIELD_HANDLE);

            // setElements() reads the tab's own $layout back-reference, so
            // the tabs have to be attached to the FieldLayout via
            // setTabs() first — see m260717_014509_addBooksSection.php's
            // identical note.
            $settingsTab = new FieldLayoutTab(['name' => 'Settings']);
            $contentTab = new FieldLayoutTab(['name' => 'Content']);
            $tabs = [$settingsTab, $contentTab];

            if ($seoField) {
                $seoTab = new FieldLayoutTab(['name' => 'SEO']);
                $tabs[] = $seoTab;
            }

            $fieldLayout = new FieldLayout(['type' => Entry::class]);
            $fieldLayout->setTabs($tabs);

            $settingsElements = [new EntryTitleField(['required' => true])];
            if ($headingField) {
                $settingsElements[] = new CustomField($headingField, ['label' => 'Alternative Title', 'instructions' => 'Override the main title']);
            }
            if ($excerptField) {
                $settingsElements[] = new CustomField($excerptField);
            }
            if ($imageField) {
                $settingsElements[] = new CustomField($imageField);
            }
            if ($themeOverrideField) {
                $settingsElements[] = new CustomField($themeOverrideField);
            }
            $settingsTab->setElements($settingsElements);

            if ($pageBuilderField) {
                $contentTab->setElements([new CustomField($pageBuilderField)]);
            }

            if ($seoField) {
                $seoTab->setElements([new CustomField($seoField)]);
            }

            $entryType = new EntryType([
                'name' => 'Landing Page',
                'handle' => self::ENTRY_TYPE_HANDLE,
                'hasTitleField' => true,
                'showSlugField' => true,
                'showStatusField' => true,
                'icon' => 'rectangle-list',
            ]);
            $entryType->setFieldLayout($fieldLayout);

            if (!$entriesService->saveEntryType($entryType)) {
                throw new \Exception("Couldn't save the 'Landing Page' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
            }
        }

        $section = $entriesService->getSectionByHandle(self::SECTION_HANDLE);

        if (!$section) {
            $section = new Section([
                'name' => 'Landing Pages',
                'handle' => self::SECTION_HANDLE,
                'type' => Section::TYPE_STRUCTURE,
                'maxLevels' => 2,
                'enableVersioning' => true,
                'propagationMethod' => PropagationMethod::All,
            ]);

            $siteSettings = [];
            foreach (Craft::$app->getSites()->getAllSites() as $site) {
                $siteSettings[$site->id] = new Section_SiteSettings([
                    'siteId' => $site->id,
                    'enabledByDefault' => true,
                    'hasUrls' => true,
                    'template' => '_router.twig',
                    'uriFormat' => '{parent.uri}/{slug}',
                ]);
            }
            $section->setSiteSettings($siteSettings);
        }

        $section->setEntryTypes([$entryType]);

        if (!$entriesService->saveSection($section)) {
            throw new \Exception("Couldn't save the 'Landing Pages' section: " . implode(', ', $section->getErrorSummary(true)));
        }

        // Re-point any entries already sitting in this section (e.g. a
        // prototype test entry created before this entry type existed) at
        // the new entry type. Shared fields (heading/excerpt/image/
        // pageBuilder/seo) keep their content since both entry types
        // reference the same field IDs — only themeOverride starts blank.
        $existingEntries = Entry::find()->section(self::SECTION_HANDLE)->status(null)->all();
        foreach ($existingEntries as $existingEntry) {
            if ($existingEntry->getTypeId() !== $entryType->id) {
                $existingEntry->setTypeId($entryType->id);
                if (!Craft::$app->getElements()->saveElement($existingEntry)) {
                    throw new \Exception("Couldn't re-type entry #{$existingEntry->id} to 'Landing Page': " . implode(', ', $existingEntry->getErrorSummary(true)));
                }
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        $entriesService = Craft::$app->getEntries();
        $fieldsService = Craft::$app->getFields();

        // Deletes the section's entries along with it — expected for a
        // down migration, not something to run against real landing-page
        // content (see m260717_014509_addBooksSection.php's identical
        // note).
        if ($section = $entriesService->getSectionByHandle(self::SECTION_HANDLE)) {
            $entriesService->deleteSection($section);
        }

        if ($entryType = $entriesService->getEntryTypeByHandle(self::ENTRY_TYPE_HANDLE)) {
            $entriesService->deleteEntryType($entryType);
        }

        if ($field = $fieldsService->getFieldByHandle(self::FIELD_HANDLE)) {
            $fieldsService->deleteField($field);
        }

        return true;
    }
}
