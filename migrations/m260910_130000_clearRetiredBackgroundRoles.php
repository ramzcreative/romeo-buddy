<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;

/**
 * Clears `backgroundRole` values naming a role no theme actually defines.
 *
 * This site's content still references `color1`, `color3` and `black` —
 * the role names retired in July 2026 when the hand-authored `$colors` map
 * was replaced by the Color System. No theme has shipped a `.bg--color3`
 * class since, so those 37 blocks have been rendering with **no background
 * at all** for months. The old colour-swatches field hid that: it stored a
 * class and a stale hex, and nothing ever checked the class still existed.
 *
 * So this is not a visual change — it makes the stored content say what
 * visitors already see. It runs only because the new BackgroundRole field
 * validates against the theme's real roles, which would otherwise nag an
 * editor about every one of these the next time they opened a page.
 *
 * Deliberately NOT in stables: this is one site's legacy data, not
 * boilerplate behaviour. stables' own backfill produced only live roles.
 *
 * Only the NEW field is touched. The old `background` field is left exactly
 * as it is, so this stays reversible until it is retired for good.
 */
class m260910_130000_clearRetiredBackgroundRoles extends Migration
{
    private const FIELD_HANDLE = 'backgroundRole';

    public function safeUp(): bool
    {
        $live = $this->liveRoles();

        if ($live === []) {
            echo "    > no backgrounds CSS found; nothing to judge against, skipping\n";
            return true;
        }

        echo '    > roles defined by a theme: ' . implode(', ', $live) . "\n";

        $uids = $this->layoutElementUids();

        if ($uids === []) {
            return true;
        }

        $rows = (new Query())
            ->select(['id', 'content'])
            ->from(Table::ELEMENTS_SITES)
            ->where(['not', ['content' => null]])
            ->all();

        $cleared = [];

        foreach ($rows as $row) {
            $content = is_string($row['content']) ? json_decode($row['content'], true) : $row['content'];

            if (!is_array($content)) {
                continue;
            }

            $changed = false;

            foreach ($uids as $uid) {
                $role = $content[$uid] ?? null;

                if (!is_string($role) || $role === '' || in_array($role, $live, true)) {
                    continue;
                }

                // Null rather than removing the key: "explicitly no
                // background" is a value, and leaving the key absent would
                // be indistinguishable from a block that predates the field.
                $content[$uid] = null;
                $cleared[$role] = ($cleared[$role] ?? 0) + 1;
                $changed = true;
            }

            if ($changed) {
                // Pass the array; the query builder encodes for the json
                // column. Pre-encoding double-encodes it.
                $this->update(Table::ELEMENTS_SITES, ['content' => $content], ['id' => $row['id']]);
            }
        }

        foreach ($cleared as $role => $count) {
            echo "    > cleared '$role' on $count blocks\n";
        }

        if ($cleared === []) {
            echo "    > nothing to clear\n";
        }

        return true;
    }

    /**
     * Every role any theme ships a `.bg--{role}` class for — the literal
     * test of whether a stored role can render at all.
     *
     * @return string[]
     */
    private function liveRoles(): array
    {
        $roles = [];
        $themesPath = Craft::getAlias('@root/themes');

        foreach ((array)glob($themesPath . '/*/src/css/generated/backgrounds-generated.pcss') as $path) {
            if (preg_match_all('/\.bg--([a-z0-9-]+)/', (string)file_get_contents($path), $m)) {
                $roles = array_merge($roles, $m[1]);
            }
        }

        return array_values(array_unique($roles));
    }

    /**
     * Content is keyed by layout-element uid, so the field's own handle
     * isn't enough to find its values.
     *
     * @return string[]
     */
    private function layoutElementUids(): array
    {
        $uids = [];

        foreach (Craft::$app->getEntries()->getAllEntryTypes() as $entryType) {
            foreach ($entryType->getFieldLayout()->getTabs() as $tab) {
                foreach ($tab->getElements() as $element) {
                    if (!$element instanceof \craft\fieldlayoutelements\CustomField) {
                        continue;
                    }

                    try {
                        $handle = $element->getField()->handle;
                    } catch (\Throwable) {
                        continue;
                    }

                    if ($handle === self::FIELD_HANDLE) {
                        $uids[] = $element->uid;
                    }
                }
            }
        }

        return array_values(array_unique($uids));
    }

    /**
     * Not reversible: the cleared values are gone. They rendered as nothing
     * before this ran, so there is nothing to restore that anyone could
     * see — and the old `background` field still holds the original class
     * if the history is ever needed.
     */
    public function safeDown(): bool
    {
        echo "    > m260910_130000 cleared content and can't be undone; the original classes are still in the old `background` field\n";

        return true;
    }
}
