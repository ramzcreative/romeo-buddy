<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use modules\eventdates\services\Occurrences;

/**
 * An index that matches how the listing actually reads this table.
 *
 * `occurring()` groups by `elementId` and only then cares about the window, but the index it had put `end`
 * before `elementId` — so MySQL filtered the range and then scanned everything to group it. Measured on a
 * seeded 300 events / 36,878 rows: `EXPLAIN` reported `type=index, rows=36729`, a full pass of the index.
 *
 * Putting `elementId` ahead of the dates makes it a covering index (`type=ref … Using index`) and cuts the
 * listing from 40ms to 16ms, its pagination count from 20ms to 8ms, and a date range from 37ms to 13ms. The
 * cost is about 2ms on saving a repeating event, and a fifth of a millisecond on the reads that were already
 * fast — both measured, neither noticeable.
 *
 * The older index stays: it serves a read that filters dates without grouping, which this one can't.
 */
class m260918_200000_addOccurrenceGroupIndex extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists(Occurrences::TABLE)) {
            echo '  > No ' . Occurrences::TABLE . " yet — the table migration creates this index with it.\n";

            return true;
        }

        $this->createIndex(null, Occurrences::TABLE, ['siteId', 'cancelled', 'elementId', 'end', 'start']);

        echo "  > Added the grouping index.\n";

        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }
}
