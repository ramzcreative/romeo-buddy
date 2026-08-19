<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\elements\Entry;

/**
 * Removes the duplicate `text` entry type and its two orphaned fields.
 *
 * The CKEditor→Matrix conversion on 2026-08-17/18 landed twice, eleven
 * minutes apart, and left two complete sets of the same things — both with
 * the same handles, neither deleted. Craft doesn't enforce handle uniqueness
 * at the database level, so both simply sat there, and the CP has shown two
 * "Text" entry types ever since.
 *
 *   03:29:27 (dead)   entry type `text` 4d62dced  ·  bodyText 05907072  ·  containerBlocks 879c91f6
 *   03:40:57 (live)   entry type `text` e5434020  ·  bodyText d367f8e9  ·  containerBlocks 6176e0d0
 *
 * The dead set holds nothing. Its entry type has no live entries — only seven
 * elements trashed on 2026-08-18 by the second run — its `bodyText` is on no
 * layout but the dead type's own, and its `containerBlocks` is referenced by
 * no field layout anywhere. Everything real runs on the live set.
 *
 * Identified by uid rather than id, because ids are per-database and this has
 * to mean the same thing on production as it does locally.
 *
 * The rows are removed with direct DELETEs rather than through the entry type
 * and field services, because those services work THROUGH project config —
 * they remove a config path and let the resulting event delete the row. The
 * dead set was never registered in project config (it is in neither the YAML
 * nor the projectconfig table, only in the database), so both services return
 * cleanly having done nothing at all. That is also how the duplicate came to
 * exist and why it survived: project config has never known about it, so
 * nothing that reconciles against project config will ever clean it up.
 *
 * Nothing here is assumed. Each deletion is preceded by a check that the thing
 * is genuinely unused, and anything unexpected aborts rather than continuing —
 * this migration is cleanup, so there is no case where deleting more than
 * planned is the right outcome.
 */
class m260819_180000_removeDuplicateTextEntryType extends Migration
{
    private const DEAD_ENTRY_TYPE = '4d62dced-3ab9-4007-8f27-d2464e226726';
    private const DEAD_BODY_TEXT = '05907072-c9f3-4079-8988-40d3edbb86e2';
    private const DEAD_CONTAINER_BLOCKS = '879c91f6-5af3-484f-ab9d-5a456bb3d72c';

    private const LIVE_ENTRY_TYPE = 'e5434020-b731-4f0b-b995-c77436af8ad3';

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();

        // The live set must exist before anything is removed. If it doesn't,
        // this database isn't the one this migration was written for and the
        // "duplicate" might be the only copy.
        if (!$entries->getEntryTypeByUid(self::LIVE_ENTRY_TYPE)) {
            throw new \Exception(
                'The surviving `text` entry type (' . self::LIVE_ENTRY_TYPE . ') is missing. ' .
                'Refusing to delete the other one.'
            );
        }

        $deadTypeId = (new Query())
            ->select(['id'])
            ->from('{{%entrytypes}}')
            ->where(['uid' => self::DEAD_ENTRY_TYPE])
            ->scalar();

        $deadLayoutId = null;

        if ($deadTypeId) {
            $deadTypeId = (int)$deadTypeId;

            $deadLayoutId = (new Query())
                ->select(['fieldLayoutId'])
                ->from('{{%entrytypes}}')
                ->where(['id' => $deadTypeId])
                ->scalar();
            $deadLayoutId = $deadLayoutId ? (int)$deadLayoutId : null;

            // No live entries. Trashed ones are expected — they're what the
            // second conversion run left behind — and go with the type.
            $live = (int)Entry::find()->typeId($deadTypeId)->status(null)->count();
            if ($live > 0) {
                throw new \Exception(
                    "The duplicate `text` entry type has $live live entries. It was supposed to " .
                    'have none, so something is using it — aborting rather than deleting content.'
                );
            }

            $this->purgeTrashedEntries($deadTypeId);

            // craft_entries.typeId cascades, but craft_elements does not, so
            // the entries are removed as elements above rather than left as
            // rows pointing at nothing.
            $this->delete('{{%entrytypes}}', ['id' => $deadTypeId]);
            echo "    > deleted the duplicate `text` entry type (#$deadTypeId)\n";

            if ($deadLayoutId) {
                $this->delete('{{%fieldlayouts}}', ['id' => $deadLayoutId]);
                echo "    > deleted its field layout (#$deadLayoutId)\n";
            }
        }

        // Its two fields, each checked against every field layout still in use.
        foreach ([
            'bodyText' => self::DEAD_BODY_TEXT,
            'containerBlocks' => self::DEAD_CONTAINER_BLOCKS,
        ] as $handle => $uid) {
            $field = $fields->getFieldByUid($uid);
            if (!$field) {
                continue;
            }

            $layouts = array_diff($this->layoutsUsing($uid), array_filter([$deadLayoutId]));
            if ($layouts) {
                throw new \Exception(
                    "The duplicate `$handle` field is still on field layout(s) " .
                    implode(', ', $layouts) . ' — aborting.'
                );
            }

            // A Matrix field takes its nested entries with it, so make sure
            // there aren't any. The id is passed straight through: a falsy
            // fieldId doesn't narrow this query, it removes the filtering
            // altogether and matches every entry on the site.
            $nested = (int)Entry::find()->fieldId($field->id)->status(null)->count();
            if ($nested > 0) {
                throw new \Exception("The duplicate `$handle` field owns $nested entries — aborting.");
            }

            $this->delete('{{%fields}}', ['id' => $field->id]);
            echo "    > deleted the duplicate `$handle` field (#{$field->id})\n";
        }

        return true;
    }

    /**
     * Hard-deletes the trashed entries of one entry type.
     *
     * Every id is collected and re-checked first, and the delete is issued
     * against that explicit list. Nothing here is expressed as a query that
     * could widen: an earlier migration in this repo passed a falsy fieldId to
     * Entry::find(), which does not narrow the query but removes the filtering
     * entirely, and hard-deleted every entry on the site.
     */
    private function purgeTrashedEntries(int $typeId): void
    {
        $elements = Craft::$app->getElements();

        $trashed = Entry::find()
            ->typeId($typeId)
            ->status(null)
            ->trashed(true)
            ->all();

        foreach ($trashed as $entry) {
            if ($entry->typeId !== $typeId || !$entry->trashed) {
                throw new \Exception("Entry #{$entry->id} isn't a trashed type-$typeId entry — aborting.");
            }

            $elements->deleteElement($entry, true);
        }

        if ($trashed) {
            echo '    > purged ' . count($trashed) . " trashed entries of the duplicate type\n";
        }
    }

    /**
     * Field layout ids whose config references a field uid.
     *
     * Read from the layout config rather than inferred, because deleting a
     * field does NOT remove its layout element — a layout still pointing at a
     * deleted field throws FieldNotFoundException on the next read.
     *
     * @return int[]
     */
    private function layoutsUsing(string $fieldUid): array
    {
        $out = [];

        foreach ((new Query())
            ->select(['id', 'config'])
            ->from('{{%fieldlayouts}}')
            ->where(['dateDeleted' => null])
            ->all() as $row) {
            if (str_contains((string)$row['config'], $fieldUid)) {
                $out[] = (int)$row['id'];
            }
        }

        return $out;
    }

    public function safeDown(): bool
    {
        // Not reversible, and not worth being: it removes a duplicate that
        // never held anything.
        return false;
    }
}
