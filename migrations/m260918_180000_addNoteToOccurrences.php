<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use modules\eventdates\services\Occurrences;

/**
 * A `note` on each occurrence — "Starts an hour later", "Side entrance this week" — for the narrow
 * per-occurrence editing decided in docs/recurring-events-spec.md §11, decision 2.
 *
 * Narrow on purpose. The other half of that decision is a per-date time, which needs no column because a row
 * already carries real start and end datetimes. What is deliberately NOT here is a per-date venue, title or
 * description: those are the part of Solspace's per-occurrence editing that generates its hardest bugs, and they
 * would make every consumer resolve a content cascade to render one card.
 *
 * Rows are derived, so this needs no data migration — `php craft eventdates/occurrences/rebuild` rewrites them.
 */
class m260918_180000_addNoteToOccurrences extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists(Occurrences::TABLE)) {
            echo '  > No ' . Occurrences::TABLE . " yet — the table migration creates this column with it.\n";

            return true;
        }

        if ($this->db->columnExists(Occurrences::TABLE, 'note')) {
            echo "  > Already has a 'note' column.\n";

            return true;
        }

        // Not indexed: nothing queries by it. It is read with the row it belongs to and rendered beside the date.
        $this->addColumn(Occurrences::TABLE, 'note', $this->string(140)->notNull()->defaultValue('')->after('cancelled'));

        echo "  > Added 'note'. Run `php craft eventdates/occurrences/rebuild` to populate it.\n";

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists(Occurrences::TABLE, 'note')) {
            $this->dropColumn(Occurrences::TABLE, 'note');
        }

        return true;
    }
}
