<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Adds {{%nav_nodes}}.urlSuffix — an optional fragment/query string (e.g.
 * "#section-2" or "?foo=bar") appended to whatever URL a node resolves to
 * at render time, regardless of type. Kept as its own column rather than
 * folded into the existing `url` column (which is only ever populated for
 * `type = url`) since an entry/category/asset/site node has no `url` value
 * of its own to append to — the suffix gets concatenated onto whatever
 * that type resolves to live (see models/Node.php).
 */
class m260719_110000_addNavNodeUrlSuffix extends Migration
{
    public function safeUp(): bool
    {
        $this->addColumn('{{%nav_nodes}}', 'urlSuffix', $this->string()->after('url'));

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropColumn('{{%nav_nodes}}', 'urlSuffix');

        return true;
    }
}
