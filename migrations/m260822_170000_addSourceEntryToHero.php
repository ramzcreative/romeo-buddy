<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;

/**
 * Gives the hero block the same entry-source capability the shared `item` type
 * has: pick an entry, and the hero fills itself in from it.
 *
 * WHY HERO WAS LEFT OUT
 * m260819_100000 built the whole override chain but is hardcoded to
 * `private const ENTRY_TYPE = 'item'`, so only items ever gained the fields.
 * Everything else — config/stables/items.php, ItemResolver, itemData(),
 * itemEagerLoadPaths() — is already generic and needs no change.
 *
 * BOTH FIELDS, NOT JUST sourceEntry
 * `useEntry` comes too, deliberately. ItemResolver::sourceEntry() checks
 * `if ($item->getFieldLayout()?->getFieldByHandle('useEntry') && !$item->getFieldValue('useEntry'))`
 * — so on a layout WITHOUT `useEntry` the toggle check is skipped entirely and
 * a selected entry is always used. Shipping `sourceEntry` alone would work, but
 * leave an editor no way to switch the behaviour off short of clearing the
 * relation, and would make hero behave differently from every item. The
 * resolver's own comment calls the toggle "a convenience for editors"; heroes
 * deserve the same convenience.
 *
 * FIELD ORDER
 * Both go at the TOP of the Content tab, matching `item`, where the pair reads
 * as "where does this come from?" before "what's in it?". Craft appends to the
 * end by default, which would bury the source picker under the content it
 * governs.
 *
 * SCOPE
 * Only `layouts/hero/standard.twig` is updated to resolve through itemData().
 * The other three hero layouts are deliberately untouched — they are currently
 * unreachable anyway, because no `layoutHero` field exists so hero.twig's
 * `entry['layoutHero'] ?? 'standard'` always falls through to standard. That is
 * its own bug, tracked separately.
 *
 * SAFETY
 * Additive. Two fields appended to an existing tab, skipped if already present.
 * No content rows touched.
 */
class m260822_170000_addSourceEntryToHero extends Migration
{
    private const ENTRY_TYPE = 'hero';
    private const FIELDS = ['useEntry', 'sourceEntry'];

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fieldsService = Craft::$app->getFields();
        $type = $this->entryType();

        if (!$type) {
            throw new \Exception("No '" . self::ENTRY_TYPE . "' entry type.");
        }

        $layout = $type->getFieldLayout();
        $tabs = $layout->getTabs();

        if (!$tabs) {
            throw new \Exception("The '" . self::ENTRY_TYPE . "' field layout has no tabs.");
        }

        $tab = $tabs[0];
        $existing = $tab->getElements();

        // What's already there, so a re-run is a no-op rather than a duplicate.
        $have = [];

        foreach ($tabs as $t) {
            foreach ($t->getElements() as $el) {
                if ($el instanceof CustomField) {
                    $have[] = $el->getField()->handle;
                }
            }
        }

        $prepend = [];

        foreach (self::FIELDS as $handle) {
            if (in_array($handle, $have, true)) {
                echo "    > {$handle}: already on " . self::ENTRY_TYPE . ", skipped\n";
                continue;
            }

            $field = $fieldsService->getFieldByHandle($handle);

            if (!$field) {
                throw new \Exception("No '{$handle}' field to attach.");
            }

            $prepend[] = new CustomField($field);
            echo "    > {$handle}: added\n";
        }

        if (!$prepend) {
            return true;
        }

        $layout->setTabs($tabs);
        $tab->setElements(array_merge($prepend, $existing));
        $type->setFieldLayout($layout);

        if (!$entries->saveEntryType($type)) {
            throw new \Exception(
                "Couldn't update '" . self::ENTRY_TYPE . "': "
                . implode(', ', $type->getErrorSummary(true))
            );
        }

        return true;
    }

    public function safeDown(): bool
    {
        $entries = Craft::$app->getEntries();
        $type = $this->entryType();

        if (!$type) {
            return true;
        }

        $layout = $type->getFieldLayout();
        $tabs = $layout->getTabs();
        $changed = false;

        foreach ($tabs as $tab) {
            $kept = array_values(array_filter(
                $tab->getElements(),
                fn($el) => !($el instanceof CustomField && in_array($el->getField()->handle, self::FIELDS, true)),
            ));

            if (count($kept) !== count($tab->getElements())) {
                $changed = true;
                $layout->setTabs($tabs);
                $tab->setElements($kept);
            }
        }

        if ($changed) {
            $type->setFieldLayout($layout);
            $entries->saveEntryType($type);
        }

        return true;
    }

    private function entryType(): ?\craft\models\EntryType
    {
        foreach (Craft::$app->getEntries()->getAllEntryTypes() as $type) {
            if ($type->handle === self::ENTRY_TYPE) {
                return $type;
            }
        }

        return null;
    }
}
