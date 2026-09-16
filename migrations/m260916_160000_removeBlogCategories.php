<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\elements\Category;
use craft\elements\Entry;
use craft\fields\Categories;

/**
 * Topics, phase 2b (ported from stables, docs/business-content-spec.md §6.3): deletes the `blogCategories` field and the `blogCategory`
 * group. Its own deploy, after phase 2a (m260916_140000_prepareCategoryRemoval) is verified in production.
 *
 * On a deploy, project config deletes both before this runs; phase 2a already copied everything and took the field off
 * the layout, so nothing depends on this migration for data. What it still does: repoint any nav node that links to a
 * category (including one trashed by that deletion) at the topic with the same slug, and delete the field and group
 * where project config didn't (a site without project config sync), after a backup.
 *
 * Category URLs keep resolving through phase 1's redirect rule. Can't be reverted: restore a backup.
 */
class m260916_160000_removeBlogCategories extends Migration
{
    private const GROUP = 'blogCategory';
    private const CATEGORY_FIELD = 'blogCategories';
    private const PREPARED = 'm260916_140000_prepareCategoryRemoval';

    /**
     * The backup runs here, before Craft opens the migration's transaction: mysqldump inside that transaction waits on
     * locks it holds, and the deploy hangs.
     */
    public function up(bool $throwExceptions = false): bool
    {
        if (Craft::$app->getCategories()->getGroupByHandle(self::GROUP) || Craft::$app->getFields()->getFieldByHandle(self::CATEGORY_FIELD)) {
            try {
                echo '    > Backup before removing categories: ' . Craft::$app->getDb()->backup() . "\n";
            } catch (\Throwable $e) {
                echo "    > Couldn't back up the database, so nothing was removed: {$e->getMessage()}\n";

                if ($throwExceptions) {
                    throw $e;
                }

                return false;
            }
        }

        return parent::up($throwExceptions);
    }

    public function safeUp(): bool
    {
        $prepared = (new Query())->from('{{%migrations}}')->where(['track' => 'content', 'name' => self::PREPARED])->exists();

        if (!$prepared) {
            throw new \Exception('Phase 2a (' . self::PREPARED . ') must be applied and verified first; nothing was removed.');
        }

        $this->repointNavNodes();

        $field = Craft::$app->getFields()->getFieldByHandle(self::CATEGORY_FIELD);
        if ($field instanceof Categories && $field->handle === self::CATEGORY_FIELD) {
            Craft::$app->getFields()->deleteField($field);
        }

        $group = Craft::$app->getCategories()->getGroupByHandle(self::GROUP);
        if ($group !== null && $group->handle === self::GROUP) {
            Craft::$app->getCategories()->deleteGroup($group);
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "    > m260917_100000_removeBlogCategories deleted content; restore a backup.\n";

        return false;
    }

    private function repointNavNodes(): void
    {
        if (!$this->db->tableExists('{{%nav_nodes}}')) {
            return;
        }

        // Only blog categories: another group's category can share a slug with a topic. The group row outlives a
        // project-config deletion (soft-deleted), so its id is still there to match.
        $groupIds = (new Query())->select(['id'])->from('{{%categorygroups}}')->where(['handle' => self::GROUP])->column();

        if ($groupIds === []) {
            return;
        }

        $nodes = (new Query())->select(['id', 'elementId'])->from('{{%nav_nodes}}')->where(['type' => 'category'])->andWhere(['not', ['elementId' => null]])->all();
        $repointed = 0;

        foreach ($nodes as $node) {
            $id = (int)$node['elementId'];
            $category = Category::find()->id($id)->groupId($groupIds)->status(null)->one()
                ?? Category::find()->id($id)->groupId($groupIds)->status(null)->trashed()->one();

            if ($category === null) {
                continue;
            }

            $topic = Entry::find()->section('topics')->slug($category->slug)->status(null)->one();

            if ($topic === null) {
                echo "    > Nav node {$node['id']} links to category '{$category->slug}', which has no topic; left as is.\n";
                continue;
            }

            $this->update('{{%nav_nodes}}', ['type' => 'entry', 'elementId' => $topic->id], ['id' => (int)$node['id']]);
            $repointed++;
        }

        if ($repointed > 0) {
            if (class_exists(\modules\nav\services\NodeRegistry::class)) {
                \modules\nav\services\NodeRegistry::invalidateCaches();
            }

            echo "    > Repointed {$repointed} nav node(s) from categories to topics.\n";
        }
    }
}
