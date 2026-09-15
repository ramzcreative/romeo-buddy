<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Category;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Entries;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;

/**
 * Topics, phase 1 of replacing blog categories (docs/business-content-spec.md §6.3). Additive only: nothing about
 * categories is removed here; phase 2 does that in a later deploy.
 *
 * Creates the `topics` structure, the `topic` entry type and the `topics` field (after `blogCategories` on blogPost,
 * in place), copies every blog category to a topic with the same slug, sets each post's topics from its categories
 * (drafts included, so applying a draft doesn't clear them), asserts the result, and adds the
 * `blog/category/<slug>` → `/topics/<slug>` 301 for when the category routes are gone.
 */
class m260916_120000_addTopics extends Migration
{
    private const SECTION = 'topics';
    private const ENTRY_TYPE = 'topic';
    private const FIELD = 'topics';
    private const CATEGORY_FIELD = 'blogCategories';
    private const CATEGORY_GROUP = 'blogCategory';
    private const REDIRECT_SOURCE = 'blog/category/<slug:[\w-]+>';

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();

        $entryType = $entries->getEntryTypeByHandle(self::ENTRY_TYPE) ?? $this->createEntryType();
        $section = $entries->getSectionByHandle(self::SECTION) ?? $this->createSection($entryType);

        $field = $fields->getFieldByHandle(self::FIELD);

        if ($field === null) {
            $field = new Entries([
                'name' => 'Topics',
                'handle' => self::FIELD,
                'instructions' => 'What this is about. Topic pages list everything tagged with them.',
                'sources' => ['section:' . $section->uid],
                'viewMode' => 'cards',
            ]);

            if (!$fields->saveField($field)) {
                throw new \Exception("Couldn't save the 'topics' field: " . implode(', ', $field->getErrorSummary(true)));
            }
        }

        $this->placeAfter('blogPost', self::CATEGORY_FIELD, $field);

        $topicIdsByCategoryId = $this->copyCategories($section, $entryType);
        $this->tagPosts($topicIdsByCategoryId);
        $this->addRedirect();

        return true;
    }

    public function safeDown(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();

        if ($this->db->tableExists('{{%redirects_rules}}')) {
            $this->delete('{{%redirects_rules}}', ['source' => self::REDIRECT_SOURCE]);
        }

        // A null match must never reach a delete: see the stables CLAUDE.md on the migration that wiped a production site.
        $field = $fields->getFieldByHandle(self::FIELD);

        if ($field instanceof Entries) {
            $this->removeFrom('blogPost', self::FIELD);
            $fields->deleteField($field);
        }

        if ($section = $entries->getSectionByHandle(self::SECTION)) {
            $entries->deleteSection($section);
        }

        if ($entryType = $entries->getEntryTypeByHandle(self::ENTRY_TYPE)) {
            $entries->deleteEntryType($entryType);
        }

        return true;
    }

    private function createEntryType(): EntryType
    {
        $fields = Craft::$app->getFields();
        $settings = new FieldLayoutTab(['name' => 'Settings']);
        $content = new FieldLayoutTab(['name' => 'Content']);
        $tabs = [$settings, $content];
        $seoField = $fields->getFieldByHandle('seo');

        if ($seoField) {
            $seo = new FieldLayoutTab(['name' => 'SEO']);
            $tabs[] = $seo;
        }

        $layout = new FieldLayout(['type' => Entry::class]);
        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs($tabs);

        $settingsElements = [new EntryTitleField()];
        foreach (['excerpt', 'image'] as $handle) {
            if ($f = $fields->getFieldByHandle($handle)) {
                $settingsElements[] = new CustomField($f);
            }
        }
        $settings->setElements($settingsElements);

        if ($pageBuilder = $fields->getFieldByHandle('pageBuilder')) {
            $content->setElements([new CustomField($pageBuilder)]);
        }

        if ($seoField) {
            $seo->setElements([new CustomField($seoField)]);
        }

        $entryType = new EntryType([
            'name' => 'Topic',
            'handle' => self::ENTRY_TYPE,
            'hasTitleField' => true,
            'showSlugField' => true,
            'showStatusField' => true,
            'icon' => 'tag',
        ]);
        $entryType->setFieldLayout($layout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'Topic' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        return $entryType;
    }

    private function createSection(EntryType $entryType): Section
    {
        $section = new Section([
            'name' => 'Topics',
            'handle' => self::SECTION,
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
                'uriFormat' => 'topics/{slug}',
            ]);
        }
        $section->setSiteSettings($siteSettings);
        $section->setEntryTypes([$entryType]);

        if (!Craft::$app->getEntries()->saveSection($section)) {
            throw new \Exception("Couldn't save the 'Topics' section: " . implode(', ', $section->getErrorSummary(true)));
        }

        return $section;
    }

    /**
     * One topic per category, same title, slug, excerpt, image, status and nesting. A topic that already has the slug
     * is adopted, so a re-run creates nothing.
     *
     * @return array<int, int> category id => topic id
     */
    private function copyCategories(Section $section, EntryType $entryType): array
    {
        $map = [];

        $categories = Category::find()->group(self::CATEGORY_GROUP)->status(null)->orderBy('lft ASC')->all();

        foreach ($categories as $category) {
            $topic = Entry::find()->section(self::SECTION)->slug($category->slug)->status(null)->one();

            if ($topic === null) {
                $topic = new Entry([
                    'sectionId' => $section->id,
                    'typeId' => $entryType->id,
                    'title' => $category->title,
                    'slug' => $category->slug,
                    'enabled' => $category->enabled,
                ]);

                $values = [];
                foreach (['excerpt', 'image'] as $handle) {
                    if ($category->getFieldLayout()?->getFieldByHandle($handle) && $topic->getFieldLayout()?->getFieldByHandle($handle)) {
                        $value = $category->getFieldValue($handle);
                        $values[$handle] = $handle === 'image' ? $value->ids() : $value;
                    }
                }
                $topic->setFieldValues($values);

                $parentId = $category->getParent()?->id;
                if ($parentId !== null && isset($map[$parentId])) {
                    $topic->setParentId($map[$parentId]);
                }

                if (!Craft::$app->getElements()->saveElement($topic)) {
                    throw new \Exception("Couldn't copy the '{$category->slug}' category to a topic: " . implode(', ', $topic->getErrorSummary(true)));
                }
            }

            $map[$category->id] = $topic->id;
        }

        return $map;
    }

    /**
     * Sets each post's topics from its categories, in their order, then checks every post's topic slugs match its
     * category slugs.
     *
     * @param array<int, int> $topicIdsByCategoryId
     */
    private function tagPosts(array $topicIdsByCategoryId): void
    {
        $posts = Entry::find()
            ->section('blog')
            ->status(null)
            ->drafts(null)
            ->provisionalDrafts(null)
            ->revisions(false)
            ->all();

        foreach ($posts as $post) {
            if (!$post->getFieldLayout()?->getFieldByHandle(self::CATEGORY_FIELD) || !$post->getFieldLayout()?->getFieldByHandle(self::FIELD)) {
                continue;
            }

            $categoryIds = $post->getFieldValue(self::CATEGORY_FIELD)->status(null)->ids();

            if ($categoryIds === []) {
                continue;
            }

            $wanted = array_values(array_filter(array_map(static fn(int $id): ?int => $topicIdsByCategoryId[$id] ?? null, $categoryIds)));
            $current = $post->getFieldValue(self::FIELD)->status(null)->ids();

            if ($current === $wanted) {
                continue;
            }

            $post->setFieldValue(self::FIELD, $wanted);

            if (!Craft::$app->getElements()->saveElement($post, false)) {
                throw new \Exception("Couldn't set topics on post {$post->id}: " . implode(', ', $post->getErrorSummary(true)));
            }
        }

        foreach ($posts as $post) {
            // After phase 2 (or on a fresh install replaying migrations) there are no categories left to compare.
            if (!$post->getFieldLayout()?->getFieldByHandle(self::FIELD) || !$post->getFieldLayout()?->getFieldByHandle(self::CATEGORY_FIELD)) {
                continue;
            }

            $fresh = Entry::find()->id($post->id)->status(null)->drafts(null)->provisionalDrafts(null)->one();
            $categorySlugs = array_map(static fn($el) => $el->slug, $fresh->getFieldValue(self::CATEGORY_FIELD)->status(null)->all());
            $topicSlugs = array_map(static fn($el) => $el->slug, $fresh->getFieldValue(self::FIELD)->status(null)->all());
            sort($categorySlugs);
            sort($topicSlugs);

            if ($categorySlugs !== $topicSlugs) {
                throw new \Exception("Post {$post->id}'s topics (" . implode(', ', $topicSlugs) . ") don't match its categories (" . implode(', ', $categorySlugs) . "); stopped.");
            }
        }
    }

    private function addRedirect(): void
    {
        if (!class_exists(\modules\redirects\services\RedirectService::class) || !$this->db->tableExists('{{%redirects_rules}}')) {
            echo "    > No redirects module: add blog/category/<slug:[\\w-]+> → /topics/<slug> (301) by hand.\n";

            return;
        }

        (new \modules\redirects\services\RedirectService())->saveRule(self::REDIRECT_SOURCE, '/topics/<slug>', 301);
    }

    /**
     * Inserts the field directly after the anchor, in the anchor's tab, keeping every existing element and its uid.
     */
    private function placeAfter(string $entryTypeHandle, string $anchorHandle, Entries $field): void
    {
        $entries = Craft::$app->getEntries();
        $entryType = $entries->getEntryTypeByHandle($entryTypeHandle);

        if ($entryType === null) {
            return;
        }

        $layout = $entryType->getFieldLayout();

        foreach ($layout->getCustomFieldElements() as $element) {
            if ($this->handleOf($element) === self::FIELD) {
                return;
            }
        }

        $tabs = $layout->getTabs();
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

        $uidsBefore = array_map(fn(CustomField $el) => $el->uid, $layout->getCustomFieldElements());
        $hadTitleField = $entryType->hasTitleField;

        $elements = array_values($targetTab->getElements());
        array_splice($elements, $insertAt ?? count($elements), 0, [new CustomField($field)]);

        $layout->setTabs($tabs);
        $targetTab->setElements($elements);
        $entryType->setFieldLayout($layout);

        if (!$entries->saveEntryType($entryType)) {
            throw new \Exception("Couldn't add topics to '{$entryTypeHandle}': " . implode(', ', $entryType->getErrorSummary(true)));
        }

        $saved = $entries->getEntryTypeById($entryType->id);
        $uidsAfter = array_map(fn(CustomField $el) => $el->uid, $saved->getFieldLayout()->getCustomFieldElements());

        if (array_diff($uidsBefore, $uidsAfter) !== [] || $saved->hasTitleField !== $hadTitleField) {
            throw new \Exception("Adding topics to '{$entryTypeHandle}' changed its existing layout; stopped.");
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
