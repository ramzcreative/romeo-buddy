<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Date;
use craft\fields\Lightswitch;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;

/**
 * A generic dated-listing section: camps, workshops, clinics, open houses,
 * tours, tastings.
 *
 * The handle is deliberately `events`, not `camps`. A handle is an internal
 * identifier that templates and the postType dropdown reference, so it should
 * never need changing; the two things a client *does* vary — the section name
 * an editor sees, and the URL — are both freely editable in the CP with no
 * code impact. So this ships as "Events" at /events/ and a youth sports client
 * renames it to "Camps" at /camps/ without touching a template.
 *
 * The section's element source is created **disabled**, so a site that doesn't
 * run events never sees it. Enabling is one toggle in the entries index's
 * "Customize sources" panel. That's why this can live in the boilerplate at
 * all — an inherited content type nobody asked for is only acceptable if it's
 * invisible until wanted.
 *
 * `eventStart` is the field the SEO module gates its Event structured data on
 * (see StructuredDataBuilder::buildEvent() in craft-modules) — no schema is
 * published for an entry without one.
 */
class m260819_200000_addEventsSection extends Migration
{
    private const SECTION_HANDLE = 'events';
    private const ENTRY_TYPE_HANDLE = 'event';

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();

        $this->createEventFields($fields);

        // Adopt-if-present, same as the landing page migration: `php craft up`
        // applies pending project config before content migrations run, so
        // this may already exist from a committed entryTypes/*.yaml. Building
        // a second EntryType and saving it unconditionally fails validation
        // with "Handle already taken".
        $entryType = $entries->getEntryTypeByHandle(self::ENTRY_TYPE_HANDLE)
            ?? $this->createEntryType($fields, $entries);

        $section = $entries->getSectionByHandle(self::SECTION_HANDLE);

        if (!$section) {
            $section = new Section([
                'name' => 'Events',
                'handle' => self::SECTION_HANDLE,
                // A channel, not a structure: dated entries are a flat,
                // chronological list with no parent/child meaning.
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
                    // Change this per client — the SEO module derives the
                    // breadcrumb trail from whatever prefix is set here, so
                    // renaming it to camps/{slug} keeps the trail correct.
                    'uriFormat' => 'events/{slug}',
                ]);
            }
            $section->setSiteSettings($siteSettings);
        }

        $section->setEntryTypes([$entryType]);

        if (!$entries->saveSection($section)) {
            throw new \Exception("Couldn't save the 'Events' section: " . implode(', ', $section->getErrorSummary(true)));
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

        // Both of these reference the section, so they have to go while it
        // still exists to be identified. Leaving either behind is worse than
        // it sounds: the postType dropdown would offer a section that no
        // longer exists, and the orphaned element source would sit in project
        // config pointing at a dead uid.
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

    private function removeElementSource(Section $section): void
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $path = 'elementSources.' . Entry::class;
        $sources = $projectConfig->get($path) ?? [];
        $key = 'section:' . $section->uid;

        $remaining = array_values(array_filter($sources, fn($source) => ($source['key'] ?? null) !== $key));

        if (count($remaining) !== count($sources)) {
            $projectConfig->set($path, $remaining, "Remove the Events source");
        }
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

    /**
     * @return array<string, array{class: class-string, name: string, instructions: string, settings: array}>
     */
    private static function fieldDefinitions(): array
    {
        return [
            'eventStart' => [
                'class' => Date::class,
                'name' => 'Starts',
                'instructions' => 'Required. Nothing is published as an event without a start.',
                'settings' => ['showDate' => true, 'showTime' => true],
            ],
            'eventEnd' => [
                'class' => Date::class,
                'name' => 'Ends',
                'instructions' => 'Optional, for anything running more than one day. Listings stop showing an event after this passes — or after the start date when this is blank — so nothing has to be unpublished by hand.',
                'settings' => ['showDate' => true, 'showTime' => true],
            ],
            'eventLocationName' => [
                'class' => PlainText::class,
                'name' => 'Venue',
                'instructions' => 'Where it happens, e.g. “Rincon Vista Sports Center”.',
                'settings' => [],
            ],
            'eventAddress' => [
                'class' => PlainText::class,
                'name' => 'Venue Address',
                'instructions' => 'Street address of the venue, on one line.',
                'settings' => ['multiline' => true, 'initialRows' => 2],
            ],
            'eventIsOnline' => [
                'class' => Lightswitch::class,
                'name' => 'Online Event',
                'instructions' => 'On for something attended remotely. The venue fields above are then ignored.',
                'settings' => ['default' => false],
            ],
            'eventPrice' => [
                'class' => Number::class,
                'name' => 'Price',
                'instructions' => 'Leave blank if there’s no set price. Enter 0 for a free event — that publishes as free rather than as unpriced.',
                'settings' => ['decimals' => 2, 'min' => 0],
            ],
            'eventUrl' => [
                'class' => PlainText::class,
                'name' => 'Registration URL',
                'instructions' => 'Where people sign up, if that happens somewhere else.',
                'settings' => [],
            ],
        ];
    }

    private function createEventFields($fields): void
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

        // Tabs must be attached to the layout via setTabs() before
        // setElements() — the tab reads its own $layout back-reference and
        // throws "Field layout tab is missing its field layout" otherwise.
        $settingsTab = new FieldLayoutTab(['name' => 'Settings']);
        $eventTab = new FieldLayoutTab(['name' => 'Event Details']);
        $contentTab = new FieldLayoutTab(['name' => 'Content']);
        $tabs = [$settingsTab, $eventTab, $contentTab];

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

        $eventElements = [];
        foreach (array_keys(self::fieldDefinitions()) as $handle) {
            if ($field = $get($handle)) {
                $eventElements[] = new CustomField($field, $handle === 'eventStart' ? ['required' => true] : []);
            }
        }
        $eventTab->setElements($eventElements);

        if ($pageBuilder = $get('pageBuilder')) {
            $contentTab->setElements([new CustomField($pageBuilder)]);
        }
        if ($seoField) {
            $seoTab->setElements([new CustomField($seoField)]);
        }

        $entryType = new EntryType([
            'name' => 'Event',
            'handle' => self::ENTRY_TYPE_HANDLE,
            'hasTitleField' => true,
            'showSlugField' => true,
            'showStatusField' => true,
            'icon' => 'calendar-days',
        ]);
        $entryType->setFieldLayout($layout);

        if (!$entries->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'Event' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        return $entryType;
    }

    /**
     * Ships the section's entries-index source disabled, so a site that
     * doesn't run events never sees it. Craft only honours an explicit source
     * list, so this appends to whatever's already configured rather than
     * replacing it — reordering or dropping a site's own customisations here
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

        $projectConfig->set($path, array_values($sources), "Hide the Events source until a site enables it");
    }

    /**
     * Lets the `posts` page-builder block list events, the same way it already
     * lists blog and news. The block resolves _sections/{postType}, so the
     * option value has to match the template folder.
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

        $field->options = array_merge($field->options, [[
            'label' => 'Events',
            'value' => self::SECTION_HANDLE,
            'default' => '',
        ]]);

        if (!$fields->saveField($field)) {
            throw new \Exception("Couldn't add the Events option to 'postType': " . implode(', ', $field->getErrorSummary(true)));
        }
    }
}
