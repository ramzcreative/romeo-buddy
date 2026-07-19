<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Adds the tables for the in-house nav module (craft-modules/modules/nav),
 * replacing `justinholtweb/craft-free-nav` (see that plugin's own
 * m260717_053522_addStarterHeaderNavNodes workaround migration for one of
 * the bugs this is meant to get away from).
 *
 * Fully DB-native rather than project-config-backed, same reasoning as
 * {{%seo_settings}}/{{%theme_settings}} (see
 * m260718_180112_addSeoAndThemeSettingsTables) — `allowAdminChanges=false`
 * in production must not block creating/editing navs.
 *
 * Nodes use a plain adjacency list (parentId + sortOrder), not a
 * nested-set/lft-rgt scheme like Craft's own {{%structureelements}} — that
 * table also hard-FKs elementId to a real {{%elements}} row, which Nodes
 * deliberately aren't (a Node can be a bare URL or passive heading with no
 * element at all).
 */
class m260718_220000_addNavTables extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%nav_navs}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'maxLevels' => $this->integer(),
            'maxNodes' => $this->integer(),
            'propagateNodes' => $this->boolean()->notNull()->defaultValue(true),
            // Reserved for Phase 2 (per-client custom fields on nodes) — unused this phase.
            'fieldConfig' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        $this->createIndex(null, '{{%nav_navs}}', ['handle'], true);

        $this->createTable('{{%nav_nodes}}', [
            'id' => $this->primaryKey(),
            'navId' => $this->integer()->notNull(),
            'parentId' => $this->integer(),
            // Null means shared across sites when the owning Nav's propagateNodes is true.
            'siteId' => $this->integer(),
            'sortOrder' => $this->integer()->notNull()->defaultValue(0),
            'type' => $this->enum('type', ['entry', 'url', 'passive'])->notNull(),
            'elementId' => $this->integer(),
            'url' => $this->string(),
            'title' => $this->string(),
            'newWindow' => $this->boolean()->notNull()->defaultValue(false),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            // Reserved for Phase 2 (per-client custom fields on nodes) — unused this phase.
            'customData' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        $this->createIndex(null, '{{%nav_nodes}}', ['navId', 'parentId', 'sortOrder'], false);
        $this->createIndex(null, '{{%nav_nodes}}', ['elementId'], false);

        $this->addForeignKey(null, '{{%nav_nodes}}', ['navId'], '{{%nav_navs}}', ['id'], 'CASCADE', null);
        // CASCADE (not SET NULL) so deleting a node deletes its whole subtree
        // instead of silently promoting its children to top-level.
        $this->addForeignKey(null, '{{%nav_nodes}}', ['parentId'], '{{%nav_nodes}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%nav_nodes}}', ['siteId'], '{{%sites}}', ['id'], 'SET NULL', null);
        $this->addForeignKey(null, '{{%nav_nodes}}', ['elementId'], '{{%elements}}', ['id'], 'SET NULL', null);

        // Single-row settings table, id=1 — same shape as {{%theme_settings}}.
        $this->createTable('{{%nav_settings}}', [
            'id' => $this->primaryKey(),
            // Both unused until Phase 4 (NavCache) — the column is added now
            // to avoid a second migration for it later.
            'cacheEnabled' => $this->boolean()->notNull()->defaultValue(true),
            'cacheDuration' => $this->integer()->notNull()->defaultValue(3600),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->db->createCommand()
            ->insert('{{%nav_settings}}', ['id' => 1])
            ->execute();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%nav_nodes}}');
        $this->dropTableIfExists('{{%nav_navs}}');
        $this->dropTableIfExists('{{%nav_settings}}');

        return true;
    }
}
