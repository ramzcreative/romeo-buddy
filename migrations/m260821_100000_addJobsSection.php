<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Date;
use craft\fields\Dropdown;
use craft\fields\Lightswitch;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;

/**
 * A careers section, for the JobPosting structured data in craft-modules'
 * seo module.
 *
 * Modelled on m260818_200000_addEventsSection, including the part that makes
 * shipping this in the boilerplate acceptable at all: **the element source is
 * created disabled**, so a site that isn't hiring never sees the section.
 * Enabling it is one toggle in the entries index's "Customize sources" panel.
 *
 * Built as real fields rather than annotations on the `seo` field, unlike
 * serviceType and courseSubject. Those describe a page to a search engine; a
 * salary and a closing date are content a careers page actually renders.
 * That means a migration, which is the cost — and the reason it lives here
 * once rather than being re-derived per client, along with the memory of
 * which properties Google requires.
 *
 * `jobDescription` is the field the seo module gates on (see
 * StructuredDataBuilder::buildJobPosting()) — Google requires a description,
 * so an entry without one could not produce a valid posting anyway.
 *
 * Salary, location and employment type are all optional. Google recommends
 * them and pay-range disclosure is legally required in a growing number of
 * places, but a posting is still valid without them.
 */
class m260821_100000_addJobsSection extends Migration
{
    private const SECTION_HANDLE = 'jobs';
    private const ENTRY_TYPE_HANDLE = 'job';

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();

        $this->createJobFields($fields);

        // Adopt-if-present: `php craft up` applies pending project config
        // before content migrations, so this may already exist from a
        // committed entryTypes/*.yaml. Saving a second one fails with
        // "Handle already taken".
        $entryType = $entries->getEntryTypeByHandle(self::ENTRY_TYPE_HANDLE)
            ?? $this->createEntryType($fields, $entries);

        $section = $entries->getSectionByHandle(self::SECTION_HANDLE);

        if (!$section) {
            $section = new Section([
                'name' => 'Jobs',
                'handle' => self::SECTION_HANDLE,
                // A channel: openings are a flat list with no parent/child
                // meaning, and they come and go.
                'type' => Section::TYPE_CHANNEL,
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
                    // Rename per client — "careers" is the more common label,
                    // and the seo module derives the breadcrumb trail from
                    // whatever prefix is set here.
                    'uriFormat' => 'jobs/{slug}',
                ]);
            }
            $section->setSiteSettings($siteSettings);
        }

        $section->setEntryTypes([$entryType]);

        if (!$entries->saveSection($section)) {
            throw new \Exception("Couldn't save the 'Jobs' section: " . implode(', ', $section->getErrorSummary(true)));
        }

        $this->hideElementSource($section);
        $this->addPostTypeOption($fields);

        return true;
    }

    public function safeDown(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();
        $section = $entries->getSectionByHandle(self::SECTION_HANDLE);

        // The element source references the section by uid, so it has to go
        // while the section still exists to be identified — otherwise project
        // config keeps a source pointing at a dead uid.
        // Both reference the section, so they go while it still exists to be
        // identified — an orphaned postType option would offer a section that
        // isn't there.
        if ($section) {
            $this->removeElementSource($section);
        }
        $this->removePostTypeOption($fields);

        if ($section) {
            $entries->deleteSection($section);
        }
        if ($entryType = $entries->getEntryTypeByHandle(self::ENTRY_TYPE_HANDLE)) {
            $entries->deleteEntryType($entryType);
        }
        foreach (array_keys(self::fieldDefinitions()) as $handle) {
            if ($field = $fields->getFieldByHandle($handle)) {
                $fields->deleteField($field);
            }
        }

        return true;
    }

    /**
     * @return array<string, array{class: class-string, name: string, instructions: string, settings: array}>
     */
    private static function fieldDefinitions(): array
    {
        return [
            'jobDescription' => [
                'class' => PlainText::class,
                'name' => 'Job Description',
                'instructions' => 'Required. The full description — responsibilities, requirements, benefits. Nothing is published as a job posting without one, because Google won’t accept a posting that has no description.',
                'settings' => ['multiline' => true, 'initialRows' => 10],
            ],
            'jobEmploymentType' => [
                'class' => Dropdown::class,
                'name' => 'Employment Type',
                'instructions' => 'Optional but worth setting — job search filters on it.',
                'settings' => [
                    'options' => [
                        ['label' => '', 'value' => '', 'default' => true],
                        ['label' => 'Full time', 'value' => 'FULL_TIME', 'default' => false],
                        ['label' => 'Part time', 'value' => 'PART_TIME', 'default' => false],
                        ['label' => 'Contractor', 'value' => 'CONTRACTOR', 'default' => false],
                        ['label' => 'Temporary', 'value' => 'TEMPORARY', 'default' => false],
                        ['label' => 'Intern', 'value' => 'INTERN', 'default' => false],
                        ['label' => 'Volunteer', 'value' => 'VOLUNTEER', 'default' => false],
                        ['label' => 'Per diem', 'value' => 'PER_DIEM', 'default' => false],
                        ['label' => 'Other', 'value' => 'OTHER', 'default' => false],
                    ],
                ],
            ],
            'jobValidThrough' => [
                'class' => Date::class,
                'name' => 'Closing Date',
                'instructions' => 'When applications close. Worth setting: Google drops a posting with no closing date after a while anyway, and a stale opening is worse than none.',
                'settings' => ['showDate' => true, 'showTime' => false],
            ],
            'jobIsRemote' => [
                'class' => Lightswitch::class,
                'name' => 'Remote',
                'instructions' => 'On for a role done entirely remotely. The location below is then ignored.',
                'settings' => ['default' => false],
            ],
            'jobLocation' => [
                'class' => PlainText::class,
                'name' => 'Location',
                'instructions' => 'Where the job is based, on one line. Leave blank to use the business address from the SEO settings, which is right for most openings.',
                'settings' => ['multiline' => true, 'initialRows' => 2],
            ],
            'jobSalaryMin' => [
                'class' => Number::class,
                'name' => 'Salary From',
                'instructions' => 'Optional. Publishing a range is legally required in a growing number of places, and postings that show one get more applicants.',
                'settings' => ['decimals' => 2, 'min' => 0],
            ],
            'jobSalaryMax' => [
                'class' => Number::class,
                'name' => 'Salary To',
                'instructions' => 'Leave blank for a single figure rather than a range.',
                'settings' => ['decimals' => 2, 'min' => 0],
            ],
            'jobSalaryUnit' => [
                'class' => Dropdown::class,
                'name' => 'Salary Per',
                'instructions' => 'What the figures above are per.',
                'settings' => [
                    'options' => [
                        ['label' => 'Year', 'value' => 'YEAR', 'default' => true],
                        ['label' => 'Month', 'value' => 'MONTH', 'default' => false],
                        ['label' => 'Week', 'value' => 'WEEK', 'default' => false],
                        ['label' => 'Day', 'value' => 'DAY', 'default' => false],
                        ['label' => 'Hour', 'value' => 'HOUR', 'default' => false],
                    ],
                ],
            ],
            'jobApplyUrl' => [
                'class' => PlainText::class,
                'name' => 'Apply URL',
                'instructions' => 'Where people apply, if that happens somewhere else.',
                'settings' => [],
            ],
        ];
    }

    private function createJobFields($fields): void
    {
        foreach (self::fieldDefinitions() as $handle => $definition) {
            if ($fields->getFieldByHandle($handle)) {
                continue;
            }

            /** @var \craft\base\Field $field */
            $field = new $definition['class'](array_merge([
                'name' => $definition['name'],
                'handle' => $handle,
                'instructions' => $definition['instructions'],
            ], $definition['settings']));

            if (!$fields->saveField($field)) {
                throw new \Exception("Couldn't save the '{$handle}' field: " . implode(', ', $field->getErrorSummary(true)));
            }
        }
    }

    private function createEntryType($fields, $entries): EntryType
    {
        $get = fn(string $handle) => $fields->getFieldByHandle($handle);

        // Tabs must be attached via setTabs() BEFORE setElements() — a tab
        // reads its own layout back-reference and throws "Field layout tab is
        // missing its field layout" otherwise.
        $settingsTab = new FieldLayoutTab(['name' => 'Settings']);
        $jobTab = new FieldLayoutTab(['name' => 'Job Details']);
        $contentTab = new FieldLayoutTab(['name' => 'Content']);
        $tabs = [$settingsTab, $jobTab, $contentTab];

        $seoField = $get('seo');
        if ($seoField) {
            $seoTab = new FieldLayoutTab(['name' => 'SEO']);
            $tabs[] = $seoTab;
        }

        $layout = new FieldLayout(['type' => Entry::class]);
        $layout->setTabs($tabs);

        $settingsElements = [new EntryTitleField(['required' => true])];
        foreach ([
            ['heading', ['label' => 'Alternative Title', 'instructions' => 'Override the main title']],
            ['excerpt', []],
            ['image', []],
        ] as [$handle, $config]) {
            if ($field = $get($handle)) {
                $settingsElements[] = new CustomField($field, $config);
            }
        }
        $settingsTab->setElements($settingsElements);

        $jobElements = [];
        foreach (array_keys(self::fieldDefinitions()) as $handle) {
            if ($field = $get($handle)) {
                $jobElements[] = new CustomField($field, $handle === 'jobDescription' ? ['required' => true] : []);
            }
        }
        $jobTab->setElements($jobElements);

        if ($pageBuilder = $get('pageBuilder')) {
            $contentTab->setElements([new CustomField($pageBuilder)]);
        }
        if ($seoField) {
            $seoTab->setElements([new CustomField($seoField)]);
        }

        $entryType = new EntryType([
            'name' => 'Job',
            'handle' => self::ENTRY_TYPE_HANDLE,
            'hasTitleField' => true,
            'showSlugField' => true,
            'showStatusField' => true,
            'icon' => 'briefcase',
        ]);
        $entryType->setFieldLayout($layout);

        if (!$entries->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'Job' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        return $entryType;
    }

    /**
     * Ships the section's entries-index source disabled, so a site that isn't
     * hiring never sees it. Appends rather than replaces — Craft only honours
     * an explicit source list, and dropping a site's own customisations here
     * would be a nasty surprise.
     */
    private function hideElementSource(Section $section): void
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $path = 'elementSources.' . Entry::class;
        $sources = $projectConfig->get($path) ?? [];
        $key = 'section:' . $section->uid;

        foreach ($sources as $source) {
            if (($source['key'] ?? null) === $key) {
                return;
            }
        }

        $sources[] = [
            'key' => $key,
            'type' => 'native',
            'disabled' => true,
        ];

        $projectConfig->set($path, array_values($sources), "Hide the Jobs source until a site enables it");
    }

    /**
     * Lets the `posts` page-builder block list jobs, the same way it already
     * lists blog, news and events. The block resolves _sections/{postType},
     * so the option value has to match the template folder.
     */
    private function addPostTypeOption($fields): void
    {
        $field = $fields->getFieldByHandle('postType');
        if (!$field instanceof \craft\fields\Dropdown) {
            return;
        }

        foreach ($field->options as $option) {
            if (($option['value'] ?? null) === self::SECTION_HANDLE) {
                return;
            }
        }

        $options = $field->options;
        $options[] = ['label' => 'Jobs', 'value' => self::SECTION_HANDLE, 'default' => false];
        $field->options = $options;
        $fields->saveField($field);
    }

    private function removePostTypeOption($fields): void
    {
        $field = $fields->getFieldByHandle('postType');
        if (!$field instanceof \craft\fields\Dropdown) {
            return;
        }

        $remaining = array_values(array_filter(
            $field->options,
            fn($option) => ($option['value'] ?? null) !== self::SECTION_HANDLE
        ));

        if (count($remaining) !== count($field->options)) {
            $field->options = $remaining;
            $fields->saveField($field);
        }
    }

    private function removeElementSource(Section $section): void
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $path = 'elementSources.' . Entry::class;
        $sources = $projectConfig->get($path) ?? [];
        $key = 'section:' . $section->uid;

        $remaining = array_values(array_filter($sources, fn($source) => ($source['key'] ?? null) !== $key));

        if (count($remaining) !== count($sources)) {
            $projectConfig->set($path, $remaining, "Remove the Jobs source");
        }
    }
}
