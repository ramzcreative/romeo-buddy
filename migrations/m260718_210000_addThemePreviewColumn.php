<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Adds the 'previewTheme' column {{%theme_settings}} needs for
 * modules/themepicker/services/ThemeRegistry::getPreviewThemeHandle()/
 * setPreviewThemeHandle() (craft-modules) — a logged-in user can preview a
 * theme sitewide without actually activating it. Nullable: null/empty
 * means nothing's being previewed, same convention 'activeTheme' already
 * established for this table.
 */
class m260718_210000_addThemePreviewColumn extends Migration
{
    public function safeUp(): bool
    {
        $this->addColumn('{{%theme_settings}}', 'previewTheme', $this->string()->after('activeTheme'));

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropColumn('{{%theme_settings}}', 'previewTheme');

        return true;
    }
}
