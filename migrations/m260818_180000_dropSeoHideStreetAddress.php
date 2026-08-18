<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Drops {{%seo_settings}}.hideStreetAddress, added by
 * m260818_150000_addSeoServiceAreaSettings and made redundant in
 * craft-modules v1.22.0.
 *
 * The column backed a "service-area business" lightswitch that duplicated
 * what leaving Address Line 1 blank already did — see that release's own
 * notes. modules\seo\models\SeoSettings no longer declares the property.
 *
 * ORDERING MATTERS: run this only once the site is on craft-modules
 * >= v1.22.0. On an earlier version SeoSettings still declares the
 * property, and SeoSettingsService::saveSettings() upserts
 * $settings->getAttributes() wholesale — so with the column gone, every SEO
 * settings save would fail on an unknown column. A normal deploy is safe
 * because `composer install` runs before `migrate/up`; the hazard is
 * rolling the module back after this has run, which needs safeDown().
 */
class m260818_180000_dropSeoHideStreetAddress extends Migration
{
    private const TABLE = '{{%seo_settings}}';
    private const COLUMN = 'hideStreetAddress';

    public function safeUp(): bool
    {
        if ($this->db->getTableSchema(self::TABLE, true)->getColumn(self::COLUMN) !== null) {
            $this->dropColumn(self::TABLE, self::COLUMN);
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->getTableSchema(self::TABLE, true)->getColumn(self::COLUMN) === null) {
            // Restored with its original definition, so rolling back to a
            // module version that still writes this attribute works.
            $this->addColumn(self::TABLE, self::COLUMN, $this->boolean()->notNull()->defaultValue(false));
        }

        return true;
    }
}
