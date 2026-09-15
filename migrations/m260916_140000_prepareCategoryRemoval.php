<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\elements\Category;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Categories;

/**
 * Topics, phase 2a (ported from stables, docs/business-content-spec.md §6.3): gets blog categories ready to delete,
 * deleting nothing. This site's blog is empty, so in practice it only takes the field off the layout — but it is the
 * deploy that makes phase 2b safe if any category content appears before then.
 *
 * Phase 2 can't be one deploy. Project config applies before content migrations, so a committed deletion of the group
 * and field would run before any migration could check or copy anything. This deploy closes the gap instead:
 * - takes `blogCategories` off blogPost, so no post gains a category after this;
 * - copies what changed since phase 1 (a new category, a post with categories but no topics), reading the relations
 *   table directly so it works whether or not project config already removed the field from the layout;
 * - repoints nav nodes that link to a category;
 * - asserts nothing would be lost.
 * Phase 2b (a later deploy) deletes the field and group.
 */
class m260916_140000_prepareCategoryRemoval extends Migration
{
    private const GROUP = 'blogCategory';
    private const CATEGORY_FIELD = 'blogCategories';
    private const TOPICS_FIELD = 'topics';
    private const TOPICS_SECTION = 'topics';

    public function safeUp(): bool
    {
        $group = Craft::$app->getCategories()->getGroupByHandle(self::GROUP);
        $categoryField = Craft::$app->getFields()->getFieldByHandle(self::CATEGORY_FIELD);

        if ($group === null && $categoryField === null) {
            return true;
        }

        $topicsSection = Craft::$app->getEntries()->getSectionByHandle(self::TOPICS_SECTION);
        $blogPost = Craft::$app->getEntries()->getEntryTypeByHandle('blogPost');

        if ($topicsSection === null || !$blogPost?->getFieldLayout()->getFieldByHandle(self::TOPICS_FIELD)) {
            throw new \Exception('Topics phase 1 (m260916_120000_addTopics) must be applied first.');
        }

        $this->removeFromLayout();

        $topicIdsByCategoryId = $group ? $this->catchUpTopics($topicsSection->id) : [];
        $categoryIdsByPostId = $categoryField instanceof Categories ? $this->categoryRelations($categoryField) : [];

        $this->catchUpPosts($categoryIdsByPostId, $topicIdsByCategoryId);
        $this->assertNothingLost($categoryIdsByPostId, $topicIdsByCategoryId);
        $this->repointNavNodes($topicIdsByCategoryId);

        return true;
    }

    public function safeDown(): bool
    {
        $field = Craft::$app->getFields()->getFieldByHandle(self::CATEGORY_FIELD);
        $blogPost = Craft::$app->getEntries()->getEntryTypeByHandle('blogPost');

        if ($field instanceof Categories && $blogPost !== null && !$blogPost->getFieldLayout()->getFieldByHandle(self::CATEGORY_FIELD)) {
            $layout = $blogPost->getFieldLayout();
            $tabs = $layout->getTabs();
            $tab = $tabs[array_key_first($tabs)];
            $layout->setTabs($tabs);
            $tab->setElements([...array_values($tab->getElements()), new CustomField($field)]);
            $blogPost->setFieldLayout($layout);
            Craft::$app->getEntries()->saveEntryType($blogPost);
        }

        return true;
    }

    private function removeFromLayout(): void
    {
        $blogPost = Craft::$app->getEntries()->getEntryTypeByHandle('blogPost');
        $layout = $blogPost->getFieldLayout();

        if (!$layout->getFieldByHandle(self::CATEGORY_FIELD)) {
            return;
        }

        $keep = array_values(array_filter(
            array_map(static fn(CustomField $el) => $el->getField()->handle === self::CATEGORY_FIELD ? null : $el->uid, $layout->getCustomFieldElements())
        ));

        $tabs = $layout->getTabs();

        foreach ($tabs as $tab) {
            $kept = array_values(array_filter(
                $tab->getElements(),
                static fn(mixed $el): bool => !$el instanceof CustomField || $el->getField()->handle !== self::CATEGORY_FIELD
            ));

            if (count($kept) !== count($tab->getElements())) {
                $layout->setTabs($tabs);
                $tab->setElements($kept);
            }
        }

        $blogPost->setFieldLayout($layout);

        if (!Craft::$app->getEntries()->saveEntryType($blogPost)) {
            throw new \Exception("Couldn't take blogCategories off blogPost: " . implode(', ', $blogPost->getErrorSummary(true)));
        }

        $saved = Craft::$app->getEntries()->getEntryTypeById($blogPost->id);
        $after = array_map(static fn(CustomField $el) => $el->uid, $saved->getFieldLayout()->getCustomFieldElements());

        if (array_diff($keep, $after) !== [] || $saved->getFieldLayout()->getFieldByHandle(self::CATEGORY_FIELD)) {
            throw new \Exception("Removing blogCategories from blogPost changed its other fields; stopped.");
        }
    }

    /**
     * A topic for every category, matched by slug, created the way phase 1 did when missing.
     *
     * @return array<int, int> category id => topic id
     */
    private function catchUpTopics(int $sectionId): array
    {
        $map = [];
        $topicType = Craft::$app->getEntries()->getEntryTypeByHandle('topic');

        foreach (Category::find()->group(self::GROUP)->status(null)->orderBy('lft ASC')->all() as $category) {
            $topic = Entry::find()->section(self::TOPICS_SECTION)->slug($category->slug)->status(null)->one();

            if ($topic === null) {
                if ($topicType === null) {
                    throw new \Exception("No topic for category '{$category->slug}' and no topic entry type to create one.");
                }

                $topic = new Entry([
                    'sectionId' => $sectionId,
                    'typeId' => $topicType->id,
                    'title' => $category->title,
                    'slug' => $category->slug,
                    'enabled' => $category->enabled,
                ]);

                foreach (['excerpt', 'image'] as $handle) {
                    if ($category->getFieldLayout()?->getFieldByHandle($handle) && $topic->getFieldLayout()?->getFieldByHandle($handle)) {
                        $value = $category->getFieldValue($handle);
                        $topic->setFieldValue($handle, $handle === 'image' ? $value->ids() : $value);
                    }
                }

                $parentId = $category->getParent()?->id;
                if ($parentId !== null && isset($map[$parentId])) {
                    $topic->setParentId($map[$parentId]);
                }

                if (!Craft::$app->getElements()->saveElement($topic)) {
                    throw new \Exception("Couldn't create a topic for category '{$category->slug}': " . implode(', ', $topic->getErrorSummary(true)));
                }

                echo "    > Created topic '{$category->slug}' (category added after phase 1).\n";
            }

            $map[$category->id] = $topic->id;
        }

        return $map;
    }

    /**
     * Straight from the relations table, so it doesn't depend on the field still being on the layout.
     *
     * @return array<int, int[]> source element id => category ids, in order
     */
    private function categoryRelations(Categories $field): array
    {
        // A falsy fieldId would match every relation: see the stables CLAUDE.md on the migration that wiped a site.
        if (!is_int($field->id) || $field->id <= 0) {
            throw new \Exception('The blogCategories field has no id; stopped.');
        }

        $rows = (new Query())
            ->select(['sourceId', 'targetId'])
            ->from('{{%relations}}')
            ->where(['fieldId' => $field->id])
            ->orderBy(['sourceId' => SORT_ASC, 'sortOrder' => SORT_ASC])
            ->all();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['sourceId']][] = (int)$row['targetId'];
        }

        return $map;
    }

    /**
     * A post (or draft) with categories but no topics gets them. A post that has topics keeps exactly those: an editor
     * may have removed one on purpose since phase 1. Revisions are history and aren't touched.
     *
     * @param array<int, int[]> $categoryIdsByPostId
     * @param array<int, int> $topicIdsByCategoryId
     */
    private function catchUpPosts(array $categoryIdsByPostId, array $topicIdsByCategoryId): void
    {
        foreach ($categoryIdsByPostId as $postId => $categoryIds) {
            $post = $this->post($postId);

            if ($post === null || $post->getFieldValue(self::TOPICS_FIELD)->status(null)->ids() !== []) {
                continue;
            }

            $post->setFieldValue(self::TOPICS_FIELD, array_values(array_filter(array_map(static fn(int $id) => $topicIdsByCategoryId[$id] ?? null, $categoryIds))));

            if (!Craft::$app->getElements()->saveElement($post, false)) {
                throw new \Exception("Couldn't set topics on post {$post->id}: " . implode(', ', $post->getErrorSummary(true)));
            }

            echo "    > Tagged post {$post->id} from its categories (no topics yet).\n";
        }
    }

    /**
     * @param array<int, int[]> $categoryIdsByPostId
     * @param array<int, int> $topicIdsByCategoryId
     */
    private function assertNothingLost(array $categoryIdsByPostId, array $topicIdsByCategoryId): void
    {
        foreach (Category::find()->group(self::GROUP)->status(null)->ids() as $categoryId) {
            if (!isset($topicIdsByCategoryId[$categoryId])) {
                throw new \Exception("Category {$categoryId} has no topic; stopped.");
            }
        }

        foreach (array_keys($categoryIdsByPostId) as $postId) {
            $post = $this->post($postId);

            if ($post !== null && $post->getFieldValue(self::TOPICS_FIELD)->status(null)->ids() === []) {
                throw new \Exception("Post {$postId} has categories but no topics; stopped.");
            }
        }
    }

    /**
     * @param array<int, int> $topicIdsByCategoryId
     */
    private function repointNavNodes(array $topicIdsByCategoryId): void
    {
        if ($topicIdsByCategoryId === [] || !$this->db->tableExists('{{%nav_nodes}}')) {
            return;
        }

        $nodes = (new Query())
            ->select(['id', 'elementId'])
            ->from('{{%nav_nodes}}')
            ->where(['type' => 'category', 'elementId' => array_keys($topicIdsByCategoryId)])
            ->all();

        foreach ($nodes as $node) {
            $this->update('{{%nav_nodes}}', ['type' => 'entry', 'elementId' => $topicIdsByCategoryId[(int)$node['elementId']]], ['id' => (int)$node['id']]);
        }

        if ($nodes !== []) {
            if (class_exists(\modules\nav\services\NodeRegistry::class)) {
                \modules\nav\services\NodeRegistry::invalidateCaches();
            }

            echo '    > Repointed ' . count($nodes) . " nav node(s) from categories to topics.\n";
        }
    }

    /** A blog post or draft with the topics field, or null (revisions, other sections, deleted). */
    private function post(int $id): ?Entry
    {
        $post = Entry::find()->id($id)->status(null)->drafts(null)->provisionalDrafts(null)->revisions(false)->site('*')->unique()->one();

        if ($post === null || $post->getSection()?->handle !== 'blog' || !$post->getFieldLayout()?->getFieldByHandle(self::TOPICS_FIELD)) {
            return null;
        }

        return $post;
    }
}
