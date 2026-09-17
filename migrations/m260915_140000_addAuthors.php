<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Entries;
use craft\fields\PlainText;
use craft\fields\Table;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;

/**
 * Authors: public profiles as entries (not CP users), credited on posts through a `postAuthors` field (`authors` is
 * reserved: it's Craft's own CP-user authors attribute on entries). See
 * docs/business-content-spec.md §4.
 *
 * Adopt-if-present throughout: project config applies before content migrations, so any of it may already exist.
 * Adds the field to blogPost in place, never rebuilding its layout (content is keyed by layout element uid).
 */
class m260915_140000_addAuthors extends Migration
{
    private const SECTION = 'authors';
    private const ENTRY_TYPE = 'author';
    private const AUTHORS_FIELD = 'postAuthors';

    /** Entry type handle => the field `postAuthors` goes directly after. */
    private const CREDITED = ['blogPost' => 'excerpt'];

    public function safeUp(): bool
    {
        $this->ensureField('jobTitle', fn() => new PlainText([
            'name' => 'Job Title',
            'handle' => 'jobTitle',
            'instructions' => 'Shown under the name, e.g. “Head Coach”.',
        ]));

        $this->ensureField('sameAs', fn() => new Table([
            'name' => 'Profiles Elsewhere',
            'handle' => 'sameAs',
            'instructions' => 'Full URLs of this person’s profiles on other sites (LinkedIn, Instagram, …). Search engines use them to confirm who the author is.',
            'columns' => ['col1' => ['heading' => 'URL', 'handle' => 'url', 'type' => 'url', 'width' => '']],
            'defaults' => [],
        ]));

        $entries = Craft::$app->getEntries();
        $entryType = $entries->getEntryTypeByHandle(self::ENTRY_TYPE) ?? $this->createEntryType();
        $section = $entries->getSectionByHandle(self::SECTION);

        if ($section === null) {
            $section = new Section([
                'name' => 'Authors',
                'handle' => self::SECTION,
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
                    'uriFormat' => 'authors/{slug}',
                ]);
            }
            $section->setSiteSettings($siteSettings);
            $section->setEntryTypes([$entryType]);

            if (!$entries->saveSection($section)) {
                throw new \Exception("Couldn't save the 'Authors' section: " . implode(', ', $section->getErrorSummary(true)));
            }
        }

        $this->ensureField(self::AUTHORS_FIELD, fn() => new Entries([
            'name' => 'Authors',
            'handle' => self::AUTHORS_FIELD,
            'instructions' => 'Who wrote this. Leave empty to credit the organization.',
            'sources' => ['section:' . $section->uid],
            'maxRelations' => 3,
            'viewMode' => 'cards',
        ]));

        foreach (self::CREDITED as $entryTypeHandle => $anchorHandle) {
            $this->placeAfter($entryTypeHandle, $anchorHandle);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();
        $authorsField = $fields->getFieldByHandle(self::AUTHORS_FIELD);

        // A null match must never reach a delete: see the stables CLAUDE.md on the migration that wiped a production site.
        if ($authorsField instanceof Entries) {
            foreach (array_keys(self::CREDITED) as $entryTypeHandle) {
                $this->removeFrom($entryTypeHandle, self::AUTHORS_FIELD);
            }

            $fields->deleteField($authorsField);
        }

        if ($section = $entries->getSectionByHandle(self::SECTION)) {
            $entries->deleteSection($section);
        }

        if ($entryType = $entries->getEntryTypeByHandle(self::ENTRY_TYPE)) {
            $entries->deleteEntryType($entryType);
        }

        foreach (['sameAs' => Table::class, 'jobTitle' => PlainText::class] as $handle => $class) {
            $field = $fields->getFieldByHandle($handle);

            if ($field instanceof $class) {
                $fields->deleteField($field);
            }
        }

        return true;
    }

    private function ensureField(string $handle, callable $make): void
    {
        $fields = Craft::$app->getFields();

        if ($fields->getFieldByHandle($handle) !== null) {
            return;
        }

        $field = $make();

        if (!$fields->saveField($field)) {
            throw new \Exception("Couldn't save the '{$handle}' field: " . implode(', ', $field->getErrorSummary(true)));
        }
    }

    private function createEntryType(): EntryType
    {
        $fields = Craft::$app->getFields();
        $get = fn(string $handle) => $fields->getFieldByHandle($handle);

        $profile = new FieldLayoutTab(['name' => 'Profile']);
        $content = new FieldLayoutTab(['name' => 'Content']);
        $tabs = [$profile, $content];
        $seoField = $get('seo');

        if ($seoField) {
            $seo = new FieldLayoutTab(['name' => 'SEO']);
            $tabs[] = $seo;
        }

        $layout = new FieldLayout(['type' => Entry::class]);
        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs($tabs);

        $profileElements = [new EntryTitleField(['label' => 'Name'])];
        foreach ([
            ['jobTitle', []],
            ['image', ['label' => 'Photo']],
            ['excerpt', ['label' => 'Short Bio']],
            ['sameAs', []],
        ] as [$handle, $config]) {
            if ($field = $get($handle)) {
                $profileElements[] = new CustomField($field, $config);
            }
        }
        $profile->setElements($profileElements);

        if ($postBuilder = $get('postBuilder')) {
            $content->setElements([new CustomField($postBuilder, ['label' => 'Longer Bio'])]);
        }

        if ($seoField) {
            $seo->setElements([new CustomField($seoField)]);
        }

        $entryType = new EntryType([
            'name' => 'Author',
            'handle' => self::ENTRY_TYPE,
            'hasTitleField' => true,
            'showSlugField' => true,
            'showStatusField' => true,
            'icon' => 'user-pen',
        ]);
        $entryType->setFieldLayout($layout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'Author' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        return $entryType;
    }

    /**
     * Inserts `postAuthors` directly after the anchor, in the anchor's tab, keeping every existing element (and its uid).
     */
    private function placeAfter(string $entryTypeHandle, string $anchorHandle): void
    {
        $entries = Craft::$app->getEntries();
        $entryType = $entries->getEntryTypeByHandle($entryTypeHandle);
        $field = Craft::$app->getFields()->getFieldByHandle(self::AUTHORS_FIELD);

        if ($entryType === null || $field === null) {
            return;
        }

        $layout = $entryType->getFieldLayout();
        $tabs = $layout->getTabs();

        foreach ($layout->getCustomFieldElements() as $element) {
            if ($this->handleOf($element) === self::AUTHORS_FIELD) {
                return;
            }
        }

        $uidsBefore = array_map(fn(CustomField $el) => $el->uid, $layout->getCustomFieldElements());
        $hadTitleField = $entryType->hasTitleField;
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

        $elements = array_values($targetTab->getElements());
        array_splice($elements, $insertAt ?? count($elements), 0, [new CustomField($field)]);

        $layout->setTabs($tabs);
        $targetTab->setElements($elements);
        $entryType->setFieldLayout($layout);

        if (!$entries->saveEntryType($entryType)) {
            throw new \Exception("Couldn't add postAuthors to '{$entryTypeHandle}': " . implode(', ', $entryType->getErrorSummary(true)));
        }

        $saved = $entries->getEntryTypeById($entryType->id);
        $uidsAfter = array_map(fn(CustomField $el) => $el->uid, $saved->getFieldLayout()->getCustomFieldElements());

        if (array_diff($uidsBefore, $uidsAfter) !== [] || $saved->hasTitleField !== $hadTitleField) {
            throw new \Exception("Adding postAuthors to '{$entryTypeHandle}' changed its existing layout; stopped.");
        }
    }

    private function removeFrom(string $entryTypeHandle, string $fieldHandle): void
    {
        $entries = Craft::$app->getEntries();
        $entryType = $entries->getEntryTypeByHandle($entryTypeHandle);

        if ($entryType === null) {
            return;
        }

        $layout = $entryType->getFieldLayout();
        $tabs = $layout->getTabs();
        $changed = false;

        foreach ($tabs as $tab) {
            $kept = array_values(array_filter($tab->getElements(), fn(mixed $el): bool => $this->handleOf($el) !== $fieldHandle));

            if (count($kept) !== count($tab->getElements())) {
                $changed = true;
                $layout->setTabs($tabs);
                $tab->setElements($kept);
            }
        }

        if ($changed) {
            $entryType->setFieldLayout($layout);
            $entries->saveEntryType($entryType);
        }
    }

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
