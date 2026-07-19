<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Adds {{%nav_nodes}}.customClass — an optional per-node CSS class, so a
 * node can be styled differently than its siblings once the front-end nav
 * template (Phase 5 — not built yet) renders it. Nullable: null/empty means
 * no extra class, same convention every other optional string column on
 * this table already uses (title, url).
 */
class m260719_060000_addNavNodeCustomClass extends Migration
{
    public function safeUp(): bool
    {
        $this->addColumn('{{%nav_nodes}}', 'customClass', $this->string()->after('enabled'));

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropColumn('{{%nav_nodes}}', 'customClass');

        return true;
    }
}
