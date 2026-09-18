<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use modules\eventdates\services\Occurrences;

/**
 * A `cancelled` flag on each occurrence, so one date of a repeating event can be called off while the series
 * carries on (docs/recurring-events-spec.md §11, decision 1).
 *
 * This is the difference between "cancelled" and the `skip` list next to it: a skipped date produces no row at all
 * — the rule shouldn't have made it — while a cancelled date keeps its row, flagged. That's what lets the page show
 * "Sat 28 Nov — cancelled" instead of quietly not mentioning a date somebody has a flyer for, and it's what the
 * plugin this replaces can't express (solspace/craft-calendar discussion #437, open and unanswered: a status field
 * on the entry "affects all the dates").
 *
 * Rows are derived, so this needs no data migration — `php craft eventdates/occurrences/rebuild` rewrites them.
 */
class m260918_140000_addCancelledToOccurrences extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists(Occurrences::TABLE)) {
            echo '  > No ' . Occurrences::TABLE . " yet — the table migration creates this column with it.\n";

            return true;
        }

        if ($this->db->columnExists(Occurrences::TABLE, 'cancelled')) {
            echo "  > Already has a 'cancelled' column.\n";

            return true;
        }

        $this->addColumn(Occurrences::TABLE, 'cancelled', $this->boolean()->notNull()->defaultValue(false)->after('isExtra'));

        // Every listing query filters cancelled rows out of "what's next", so it sits with the columns that do the
        // filtering rather than being looked up row by row.
        $this->createIndex(null, Occurrences::TABLE, ['siteId', 'cancelled', 'end', 'start']);

        echo "  > Added 'cancelled'. Run `php craft eventdates/occurrences/rebuild` to populate it.\n";

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists(Occurrences::TABLE, 'cancelled')) {
            $this->dropColumn(Occurrences::TABLE, 'cancelled');
        }

        return true;
    }
}
