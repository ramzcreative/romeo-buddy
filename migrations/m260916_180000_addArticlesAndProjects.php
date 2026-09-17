<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\FieldInterface;
use craft\db\Migration;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Date;
use craft\fields\Entries;
use craft\fields\PlainText;
use craft\fields\Table;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use modules\stablestwigextensions\services\ContentModel;

/**
 * Articles and Projects, both **off** (docs/business-content-spec.md §6): the sections, entry types and fields exist,
 * but each section is hidden in the Entries sidebar, has no `postType` option, no index page and no SEO section
 * default. `php craft stablestwigextensions/content/enable <articles|projects>` turns one on.
 *
 * Adopt-if-present throughout: project config applies before content migrations, so any of it may already exist.
 */
class m260916_180000_addArticlesAndProjects extends Migration
{
    private const SECTIONS = [
        'articles' => ['name' => 'Articles', 'type' => 'article', 'typeName' => 'Article', 'icon' => 'newspaper'],
        'projects' => ['name' => 'Projects', 'type' => 'project', 'typeName' => 'Project', 'icon' => 'briefcase'],
    ];

    public function safeUp(): bool
    {
        $this->createFields();

        $contentModel = new ContentModel();

        $after = 'jobs';

        foreach (self::SECTIONS as $handle => $spec) {
            $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($spec['type']) ?? $this->createEntryType($spec);
            $section = Craft::$app->getEntries()->getSectionByHandle($handle);

            if ($section === null) {
                $section = $this->createSection($handle, $spec['name'], $entryType);
                // Off: hidden in the Entries sidebar until enabled.
                $contentModel->setSourceEnabled($section, false, $after);
            }

            $after = $handle;
        }

        return true;
    }

    public function safeDown(): bool
    {
        $entries = Craft::$app->getEntries();

        foreach (self::SECTIONS as $handle => $spec) {
            if ($section = $entries->getSectionByHandle($handle)) {
                (new ContentModel())->removeSource($section);
                $entries->deleteSection($section);
            }

            if ($entryType = $entries->getEntryTypeByHandle($spec['type'])) {
                $entries->deleteEntryType($entryType);
            }
        }

        // A null match must never reach a delete: see the stables CLAUDE.md on the migration that wiped a production site.
        $classes = [
            'keyTakeaways' => Table::class, 'references' => Table::class, 'reviewedDate' => Date::class,
            'projectClient' => PlainText::class, 'projectDate' => Date::class, 'projectLocation' => PlainText::class,
            'projectServices' => Entries::class,
        ];

        foreach ($classes as $handle => $class) {
            $field = Craft::$app->getFields()->getFieldByHandle($handle);

            if ($field instanceof $class) {
                Craft::$app->getFields()->deleteField($field);
            }
        }

        return true;
    }

    private function createFields(): void
    {
        $pages = Craft::$app->getEntries()->getSectionByHandle('pages');

        $this->ensure(new Table([
            'name' => 'Key Takeaways',
            'handle' => 'keyTakeaways',
            'instructions' => 'Up to five short points, shown above the article.',
            'addRowLabel' => 'Add a takeaway',
            'maxRows' => 5,
            'columns' => ['col1' => ['heading' => 'Takeaway', 'handle' => 'takeaway', 'type' => 'singleline', 'width' => '']],
            'defaults' => [],
        ]));

        $this->ensure(new Table([
            'name' => 'Sources',
            'handle' => 'references',
            'instructions' => 'What this article is based on. Listed after it, numbered, and given to search engines as citations.',
            'addRowLabel' => 'Add a source',
            'columns' => [
                'col1' => ['heading' => 'Title', 'handle' => 'title', 'type' => 'singleline', 'width' => ''],
                'col2' => ['heading' => 'Publisher', 'handle' => 'publisher', 'type' => 'singleline', 'width' => ''],
                'col3' => ['heading' => 'URL', 'handle' => 'url', 'type' => 'url', 'width' => ''],
                'col4' => ['heading' => 'Date', 'handle' => 'date', 'type' => 'date', 'width' => ''],
            ],
            'defaults' => [],
        ]));

        $this->ensure(new Date([
            'name' => 'Reviewed Date',
            'handle' => 'reviewedDate',
            'instructions' => 'When the facts were last checked. Shown as “Reviewed …”.',
            'showDate' => true,
            'showTime' => false,
        ]));

        $this->ensure(new PlainText(['name' => 'Client', 'handle' => 'projectClient']));
        $this->ensure(new Date(['name' => 'Project Date', 'handle' => 'projectDate', 'showDate' => true, 'showTime' => false]));
        $this->ensure(new PlainText(['name' => 'Location', 'handle' => 'projectLocation']));
        $this->ensure(new Entries([
            'name' => 'Services',
            'handle' => 'projectServices',
            'instructions' => 'The service pages this project relates to.',
            'sources' => $pages ? ['section:' . $pages->uid] : '*',
            'maxRelations' => 5,
            'viewMode' => 'cards',
        ]));
    }

    private function ensure(FieldInterface $field): void
    {
        $fields = Craft::$app->getFields();

        if ($fields->getFieldByHandle($field->handle) !== null) {
            return;
        }

        if (!$fields->saveField($field)) {
            throw new \Exception("Couldn't save the '{$field->handle}' field: " . implode(', ', $field->getErrorSummary(true)));
        }
    }

    /**
     * @param array{name: string, type: string, typeName: string, icon: string} $spec
     */
    private function createEntryType(array $spec): EntryType
    {
        $get = fn(string $handle): ?FieldInterface => Craft::$app->getFields()->getFieldByHandle($handle);
        $field = fn(string $handle, array $config = []): ?CustomField => ($f = $get($handle)) ? new CustomField($f, $config) : null;
        $heading = ['label' => 'Alternative Title', 'instructions' => 'Override the main title'];

        $tabs = $spec['type'] === 'article'
            ? [
                'Content' => [new EntryTitleField(), $field('heading', $heading), $field('excerpt'), $field('image'), $field('postAuthors'), $field('keyTakeaways'), $field('postBuilder')],
                'Sources' => [$field('references'), $field('reviewedDate')],
                'Settings' => [$field('relatedEntries'), $field('topics')],
                'SEO' => [$field('seo')],
            ]
            : [
                'Overview' => [new EntryTitleField(), $field('heading', $heading), $field('excerpt'), $field('image'), $field('projectClient'), $field('projectDate'), $field('projectLocation'), $field('projectServices')],
                'Gallery' => [$field('galleryImages')],
                'Content' => [$field('pageBuilder')],
                'Settings' => [$field('relatedEntries'), $field('topics')],
                'SEO' => [$field('seo')],
            ];

        $tabs = array_filter(array_map(static fn(array $elements) => array_values(array_filter($elements)), $tabs));
        $tabModels = array_map(static fn(string $name) => new FieldLayoutTab(['name' => $name]), array_keys($tabs));

        $layout = new FieldLayout(['type' => Entry::class]);
        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs($tabModels);

        foreach (array_values($tabs) as $i => $elements) {
            $tabModels[$i]->setElements($elements);
        }

        $entryType = new EntryType([
            'name' => $spec['typeName'],
            'handle' => $spec['type'],
            'hasTitleField' => true,
            'showSlugField' => true,
            'showStatusField' => true,
            'icon' => $spec['icon'],
        ]);
        $entryType->setFieldLayout($layout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the '{$spec['typeName']}' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        return $entryType;
    }

    private function createSection(string $handle, string $name, EntryType $entryType): Section
    {
        $section = new Section([
            'name' => $name,
            'handle' => $handle,
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
                'uriFormat' => $handle . '/{slug}',
            ]);
        }
        $section->setSiteSettings($siteSettings);
        $section->setEntryTypes([$entryType]);

        if (!Craft::$app->getEntries()->saveSection($section)) {
            throw new \Exception("Couldn't save the '{$name}' section: " . implode(', ', $section->getErrorSummary(true)));
        }

        return $section;
    }
}
