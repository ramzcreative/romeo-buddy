<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use modules\themepicker\services\ThemeVariantSchedule;

/**
 * The sitewide Theme variant slot and its schedule, for craft-modules'
 * themepicker (ThemeVariantSchedule, see docs/theme-variants-spec.md §4.2).
 *
 * theme_settings gains the "on now, until switched off" choice. Windows live
 * in their own table: UTC datetimes, half-open (startsAt <= now < endsAt),
 * never overlapping per site. Guarded so a re-run is a no-op.
 */
class m260914_120000_addThemeVariantSchedule extends Migration
{
    private const SETTINGS = '{{%theme_settings}}';
    private const SCHEDULE = '{{%theme_variant_schedule}}';

    public function safeUp(): bool
    {
        if (!$this->db->columnExists(self::SETTINGS, 'activeVariant')) {
            $this->addColumn(self::SETTINGS, 'activeVariant', $this->string()->after('previewTheme'));
        }

        if (!$this->db->columnExists(self::SETTINGS, 'activeVariantOverridesPages')) {
            $this->addColumn(self::SETTINGS, 'activeVariantOverridesPages', $this->boolean()->notNull()->defaultValue(true)->after('activeVariant'));
        }

        if (!$this->db->tableExists(self::SCHEDULE)) {
            $this->createTable(self::SCHEDULE, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->notNull(),
                'variant' => $this->string()->notNull(),
                'startsAt' => $this->dateTime()->notNull(),
                'endsAt' => $this->dateTime()->notNull(),
                'overridePages' => $this->boolean()->notNull()->defaultValue(true),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, self::SCHEDULE, ['siteId', 'startsAt']);
            $this->addForeignKey(null, self::SCHEDULE, ['siteId'], '{{%sites}}', ['id'], 'CASCADE', null);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(self::SCHEDULE);

        foreach (['activeVariantOverridesPages', 'activeVariant'] as $column) {
            if ($this->db->columnExists(self::SETTINGS, $column)) {
                $this->dropColumn(self::SETTINGS, $column);
            }
        }

        // The cached "available" and state would otherwise outlive the tables.
        (new ThemeVariantSchedule())->forget();

        return true;
    }
}
