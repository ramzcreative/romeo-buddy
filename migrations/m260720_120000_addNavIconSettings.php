<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Adds {{%nav_navs}}.hasIcons and .iconSet — per-Nav control over whether
 * nodes show an icon field at all, and whether that picker is restricted to
 * one icon set. hasIcons defaults true so existing Navs keep behaving
 * exactly as before this existed. Neither column ever clears a node's
 * already-saved icon value — see modules/nav/models/Nav.php's doc comments
 * on both properties for why (hidden-but-preserved, not destroyed).
 */
class m260720_120000_addNavIconSettings extends Migration
{
    public function safeUp(): bool
    {
        $this->addColumn('{{%nav_navs}}', 'hasIcons', $this->boolean()->notNull()->defaultValue(true)->after('propagateNodes'));
        $this->addColumn('{{%nav_navs}}', 'iconSet', $this->string()->after('hasIcons'));

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropColumn('{{%nav_navs}}', 'iconSet');
        $this->dropColumn('{{%nav_navs}}', 'hasIcons');

        return true;
    }
}
