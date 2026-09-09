<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;

/**
 * Makes the SEO and theme settings per-site instead of install-wide.
 *
 * Both tables shipped as a single row keyed `id = 1`, so every site on a
 * multi-site install shared one organisation identity and one active theme.
 * Fine for one business across several languages — a single Organization node
 * is exactly right there — but wrong for two brands sharing an install, which
 * would both publish the same name, address and phone, and render identically.
 *
 * Both tables are done in ONE migration deliberately: the change is the same
 * shape twice, and doing them together is one migration per consuming site
 * rather than two.
 *
 * SEEDING, NOT INHERITANCE. Every existing row is assigned to the primary
 * site, and a site added later gets its own row copied from the primary one
 * (see SeoSettingsService/ThemeRegistry). Runtime fallback was considered and
 * rejected: it produces "why did editing the main site change the DJ site's
 * phone number". Copy-on-create suits one-business-many-languages and two
 * unrelated brands equally, with no surprise either way.
 *
 * Safe to run on a single-site install — it becomes "the one row now names
 * the one site", and nothing observable changes.
 */
class m260909_100000_addSiteIdToSeoAndThemeSettings extends Migration
{
    public function safeUp(): bool
    {
        $primarySiteId = Craft::$app->getSites()->getPrimarySite()->id;

        foreach (['{{%seo_settings}}', '{{%theme_settings}}'] as $table) {
            if (!$this->db->tableExists($table)) {
                continue;
            }

            if (!$this->db->columnExists($table, 'siteId')) {
                $this->addColumn($table, 'siteId', $this->integer()->after('id'));
            }

            // Existing rows belong to the primary site. Done before the index
            // so a NULL siteId can't collide with itself under it.
            $this->update($table, ['siteId' => $primarySiteId], ['siteId' => null]);

            // One row per site. Unique rather than a plain index because the
            // whole point is that a second row for the same site is a bug —
            // and this is the constraint the upserts in both services rely on
            // to update rather than insert.
            if (!$this->indexExists($table, 'siteId')) {
                $this->createIndex(null, $table, ['siteId'], true);
            }

            // Cascade, like Craft's own per-site tables. Without it, deleting
            // a site in the CP leaves its settings row behind forever —
            // invisible, unreachable, and still occupying the unique index.
            if (!$this->foreignKeyExists($table)) {
                $this->addForeignKey(null, $table, ['siteId'], '{{%sites}}', ['id'], 'CASCADE', null);
            }
        }

        // A site added AFTER this runs is seeded by the services. Sites that
        // already exist are seeded here, or they'd have no row at all until
        // someone saved settings for them.
        $this->seedMissingRows($primarySiteId);

        return true;
    }

    public function safeDown(): bool
    {
        // Collapse back to one row per table, keeping the primary site's.
        $primarySiteId = Craft::$app->getSites()->getPrimarySite()->id;

        foreach (['{{%seo_settings}}', '{{%theme_settings}}'] as $table) {
            if (!$this->db->tableExists($table) || !$this->db->columnExists($table, 'siteId')) {
                continue;
            }

            // The FK has to go before the column it constrains.
            foreach ($this->foreignKeyNames($table) as $name) {
                $this->dropForeignKey($name, $table);
            }

            $this->delete($table, ['not', ['siteId' => $primarySiteId]]);
            $this->dropColumn($table, 'siteId');
        }

        return true;
    }

    /**
     * Give every existing site its own row, copied from the primary site's.
     *
     * Copies rather than leaving a site row-less so the CP has something to
     * render and the services never have to invent defaults at read time.
     */
    private function seedMissingRows(int $primarySiteId): void
    {
        $sites = Craft::$app->getSites()->getAllSites();

        if (count($sites) < 2) {
            return;
        }

        foreach (['{{%seo_settings}}', '{{%theme_settings}}'] as $table) {
            if (!$this->db->tableExists($table)) {
                continue;
            }

            $template = (new Query())->from($table)->where(['siteId' => $primarySiteId])->one();

            if (!$template) {
                continue;
            }

            // id is auto-increment and uid must be unique per row; everything
            // else is copied verbatim.
            unset($template['id'], $template['uid']);

            foreach ($sites as $site) {
                if ($site->id === $primarySiteId) {
                    continue;
                }

                $exists = (new Query())->from($table)->where(['siteId' => $site->id])->exists();

                if ($exists) {
                    continue;
                }

                $this->insert($table, array_merge($template, [
                    'siteId' => $site->id,
                    'uid' => \craft\helpers\StringHelper::UUID(),
                ]));
            }
        }
    }

    /**
     * @return string[] names of this table's foreign keys on siteId
     */
    private function foreignKeyNames(string $table): array
    {
        $rawTable = Craft::$app->getDb()->getSchema()->getRawTableName($table);
        $names = [];

        foreach (Craft::$app->getDb()->getSchema()->getTableForeignKeys($rawTable) as $fk) {
            if ($fk->columnNames === ['siteId']) {
                $names[] = $fk->name;
            }
        }

        return $names;
    }

    private function foreignKeyExists(string $table): bool
    {
        return $this->foreignKeyNames($table) !== [];
    }

    /**
     * Craft's Migration has no indexExists(), and re-creating an index that
     * is already there throws rather than no-ops.
     */
    private function indexExists(string $table, string $column): bool
    {
        $rawTable = Craft::$app->getDb()->getSchema()->getRawTableName($table);

        foreach (Craft::$app->getDb()->getSchema()->getTableIndexes($rawTable) as $index) {
            if ($index->columnNames === [$column]) {
                return true;
            }
        }

        return false;
    }
}
