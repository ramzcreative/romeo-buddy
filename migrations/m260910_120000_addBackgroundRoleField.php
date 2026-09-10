<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\fieldlayoutelements\CustomField;
use modules\themepicker\fields\BackgroundRole;

/**
 * Phase 1 of replacing craftpulse/craft-colour-swatches — see
 * craft-modules/docs/background-role-field-spec.md.
 *
 * Adds a `backgroundRole` field beside the existing `background` and
 * backfills it from what `background` already holds. **Nothing reads it
 * yet**: every template still uses the old field, so this changes nothing
 * an editor or a visitor can see. Phase 2 switches the templates; the old
 * field, the plugin and SwatchValueGuard are retired in a later release
 * again, once the new field has actually served the live site.
 *
 * Additive on purpose. The old field is never written, never read
 * destructively, and never removed here, so the rollback is deleting a
 * field nothing depends on. A content migration has wiped this project's
 * production once; nothing in this one is irreversible.
 */
class m260910_120000_addBackgroundRoleField extends Migration
{
    private const NEW_HANDLE = 'backgroundRole';
    private const OLD_HANDLE = 'background';

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();

        $field = $fieldsService->getFieldByHandle(self::NEW_HANDLE);

        if (!$field) {
            $field = new BackgroundRole([
                'name' => 'Background',
                'handle' => self::NEW_HANDLE,
            ]);

            if (!$fieldsService->saveField($field)) {
                throw new \Exception("Couldn't save the 'backgroundRole' field: " . implode(', ', $field->getErrorSummary(true)));
            }
        }

        // Every entry type carrying the old field gets the new one, in the
        // same tab, immediately after it — discovered from the layouts
        // rather than hardcoded, because the two sites' block sets differ
        // and will keep differing.
        $pairs = [];

        foreach ($entriesService->getAllEntryTypes() as $entryType) {
            $fieldLayout = $entryType->getFieldLayout();
            $tabs = $fieldLayout->getTabs();
            $oldUid = null;
            $newUid = null;
            $targetTab = null;
            $insertAt = null;

            foreach ($tabs as $tab) {
                foreach (array_values($tab->getElements()) as $i => $element) {
                    $handle = $this->handleOf($element);

                    if ($handle === self::OLD_HANDLE) {
                        $oldUid = $element->uid;
                        $targetTab = $tab;
                        $insertAt = $i + 1;
                    } elseif ($handle === self::NEW_HANDLE) {
                        $newUid = $element->uid;
                    }
                }
            }

            if ($oldUid === null) {
                continue;
            }

            if ($newUid === null) {
                $layoutElement = new CustomField($field);
                $elements = array_values($targetTab->getElements());
                array_splice($elements, $insertAt, 0, [$layoutElement]);

                // setElements() only after setTabs() — an unattached tab
                // throws "Field layout tab is missing its field layout."
                $fieldLayout->setTabs($tabs);
                $targetTab->setElements($elements);
                $entryType->setFieldLayout($fieldLayout);

                if (!$entriesService->saveEntryType($entryType)) {
                    throw new \Exception("Couldn't save the '{$entryType->handle}' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
                }

                $newUid = $layoutElement->uid;
            }

            $pairs[$entryType->handle] = ['old' => $oldUid, 'new' => $newUid];
        }

        $this->backfill($pairs);

        return true;
    }

    /**
     * Copies each stored swatch's CSS class across as a bare role.
     *
     * Works on the content JSON directly rather than through saveElement()
     * for two reasons: drafts and revisions have to be covered (a restored
     * revision must not come back blank, and an applied draft would
     * otherwise overwrite a backfilled canonical with nothing), and saving
     * every element would fire save events across the whole site for a
     * change that is purely additive. Content is keyed by LAYOUT ELEMENT
     * uid, not by field handle, which is why the pairs are resolved above.
     *
     * @param array<string, array{old: string, new: string}> $pairs
     */
    private function backfill(array $pairs): void
    {
        if ($pairs === []) {
            return;
        }

        $uidPairs = [];

        foreach ($pairs as $pair) {
            $uidPairs[$pair['old']] = $pair['new'];
        }

        $rows = (new Query())
            ->select(['id', 'content'])
            ->from(Table::ELEMENTS_SITES)
            ->where(['not', ['content' => null]])
            ->andWhere(['like', 'content', 'bg--'])
            ->all();

        $updated = 0;

        foreach ($rows as $row) {
            $content = $row['content'];

            if (is_string($content)) {
                $content = json_decode($content, true);
            }

            if (!is_array($content)) {
                continue;
            }

            $changed = false;

            foreach ($uidPairs as $oldUid => $newUid) {
                if (array_key_exists($newUid, $content)) {
                    continue; // already backfilled — this migration is re-runnable
                }

                $role = $this->roleFromSwatch($content[$oldUid] ?? null);

                if ($role === null) {
                    continue;
                }

                $content[$newUid] = $role;
                $changed = true;
            }

            if (!$changed) {
                continue;
            }

            // Pass the ARRAY, never a json_encode()d string — the query
            // builder encodes for a json column, and pre-encoding produces
            // a double-encoded value.
            $this->update(Table::ELEMENTS_SITES, ['content' => $content], ['id' => $row['id']]);
            $updated++;
        }

        echo "    > backfilled backgroundRole on $updated rows\n";
    }

    /**
     * `{"color":[{"background":"bg--primary"}]}` -> `primary`.
     *
     * Null for "nothing to carry over": no value, the None swatch, or a
     * shape this doesn't recognise. The class is the only part of the old
     * value worth keeping — the label and hex are a stale snapshot of the
     * sitewide theme, which is the whole reason for this change.
     */
    private function roleFromSwatch(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $class = $value['color'][0]['background'] ?? null;

        if (!is_string($class) || !str_starts_with($class, 'bg--')) {
            return null;
        }

        $role = substr($class, 4);

        return ($role !== '' && $role !== 'none' && preg_match('/^[a-z0-9-]+$/', $role) === 1) ? $role : null;
    }

    /**
     * CustomField::getField() throws for a deleted field rather than
     * returning null, so `?->handle` is not a guard.
     */
    private function handleOf(mixed $element): ?string
    {
        if (!$element instanceof CustomField) {
            return null;
        }

        try {
            return $element->getField()->handle;
        } catch (\Throwable) {
            return null;
        }
    }

    public function safeDown(): bool
    {
        $field = Craft::$app->getFields()->getFieldByHandle(self::NEW_HANDLE);

        if (!$field instanceof BackgroundRole) {
            return true;
        }

        // Layout elements out before the field, or the entry types are left
        // pointing at a UID that no longer resolves and the next up() dies
        // on it. Learned the hard way in m260909_220000.
        $entriesService = Craft::$app->getEntries();

        foreach ($entriesService->getAllEntryTypes() as $entryType) {
            $fieldLayout = $entryType->getFieldLayout();
            $tabs = $fieldLayout->getTabs();
            $changed = false;

            foreach ($tabs as $tab) {
                $kept = array_values(array_filter(
                    $tab->getElements(),
                    fn(mixed $element): bool => $this->handleOf($element) !== self::NEW_HANDLE
                ));

                if (count($kept) !== count($tab->getElements())) {
                    $changed = true;
                    $fieldLayout->setTabs($tabs);
                    $tab->setElements($kept);
                }
            }

            if ($changed) {
                $entryType->setFieldLayout($fieldLayout);
                $entriesService->saveEntryType($entryType);
            }
        }

        Craft::$app->getFields()->deleteField($field);

        return true;
    }
}
