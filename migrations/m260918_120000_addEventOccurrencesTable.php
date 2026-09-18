<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use modules\eventdates\services\Occurrences;

/**
 * The table every date an event happens on is written to — craft-modules' `eventdates` module fills it, the events
 * listing will query it (docs/recurring-events-spec.md §5.1). Step 1: nothing reads it yet and nothing repeats yet,
 * so it holds one row per event and the site is unchanged.
 *
 * It lives here rather than in the module because a module's own migration track is never part of `php craft up`
 * (stables docs/boilerplate-migrations-spec.md §2), so a table created there would never exist on a real site.
 *
 * Deliberately NOT a project-config change: rows are content, not configuration.
 */
class m260918_120000_addEventOccurrencesTable extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->tableExists(Occurrences::TABLE)) {
            echo '  > ' . Occurrences::TABLE . " already exists.\n";

            return true;
        }

        $this->createTable(Occurrences::TABLE, [
            'id' => $this->primaryKey(),
            'elementId' => $this->integer()->notNull(),
            // Per site: whether an event is enabled, and what it is called, are per site.
            'siteId' => $this->integer()->notNull(),
            // UTC, like every other date Craft stores. The rule is expanded in the system time zone and converted
            // here, which is what keeps a 7pm event at 7pm across a daylight-saving change.
            'start' => $this->dateTime()->notNull(),
            // Effective end: the event's own end, or the end of its day when it has none, so "is it over?" is one
            // comparison rather than two fields and a null check.
            'end' => $this->dateTime()->notNull(),
            'allDay' => $this->boolean()->notNull()->defaultValue(false),
            // Came from an added date rather than the rule (RDATE) — step 3 sets it.
            'isExtra' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Upcoming / past / range: every one of them filters on end and start, per site.
        $this->createIndex(null, Occurrences::TABLE, ['siteId', 'end', 'start']);
        // One event's own dates, in order — the detail page's list, and the delete half of a rebuild.
        $this->createIndex(null, Occurrences::TABLE, ['elementId', 'siteId', 'start']);

        // Craft soft-deletes elements, so these only fire on hard delete or garbage collection. Every read goes
        // through an element query, which already excludes trashed and disabled entries, so a stale row is never
        // shown — the cascades are housekeeping, not the safety net.
        $this->addForeignKey(null, Occurrences::TABLE, ['elementId'], '{{%elements}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Occurrences::TABLE, ['siteId'], '{{%sites}}', ['id'], 'CASCADE', null);

        echo '  > Created ' . Occurrences::TABLE . ". Run `php craft eventdates/occurrences/rebuild` to fill it.\n";

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(Occurrences::TABLE);

        return true;
    }
}
