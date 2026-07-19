<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Widens {{%nav_nodes}}.type to add category/asset/site alongside the
 * original entry/url/passive (verbb/navigation's own concept always
 * included category/asset — see the Phase 1 plan's Context section — this
 * was just deferred out of the first pass). Adds the two columns those new
 * types need:
 *
 * - targetSiteId: which site a `type = site` node points at (a different
 *   thing entirely from the existing `siteId` column, which is which site
 *   a *row* belongs to under a non-propagated Nav — a `type = site` node
 *   has no elementId at all, just a site to link to, e.g. for a language/
 *   region switcher).
 * - icon: an optional icon key (same value shape modules/iconpicker's
 *   field already uses, e.g. "ui/arrow-left") — most nav items in a real
 *   menu end up wanting one.
 */
class m260719_090000_expandNavNodeTypes extends Migration
{
    public function safeUp(): bool
    {
        $this->alterColumn(
            '{{%nav_nodes}}',
            'type',
            $this->enum('type', ['entry', 'url', 'passive', 'category', 'asset', 'site'])->notNull()
        );

        $this->addColumn('{{%nav_nodes}}', 'targetSiteId', $this->integer()->after('elementId'));
        $this->addForeignKey(null, '{{%nav_nodes}}', ['targetSiteId'], '{{%sites}}', ['id'], 'SET NULL', null);

        $this->addColumn('{{%nav_nodes}}', 'icon', $this->string()->after('customClass'));

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropColumn('{{%nav_nodes}}', 'icon');

        $this->dropForeignKeyIfExists('{{%nav_nodes}}', ['targetSiteId']);
        $this->dropColumn('{{%nav_nodes}}', 'targetSiteId');

        $this->alterColumn(
            '{{%nav_nodes}}',
            'type',
            $this->enum('type', ['entry', 'url', 'passive'])->notNull()
        );

        return true;
    }
}
