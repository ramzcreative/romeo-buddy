<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;

/**
 * Retirement step of craft-modules' docs/background-role-field-spec.md:
 * removes the old colour-swatches `background` field, now that every
 * template reads `backgroundRole` instead.
 *
 * This is the one irreversible step in the whole replacement, and it comes
 * last on purpose — the new field has been serving the live site since
 * phase 2, so anything wrong with it had a chance to surface while the old
 * data was still sitting there.
 *
 * Runs alongside removing craftpulse/craft-colour-swatches from
 * composer.json and deleting config/colour-swatches.php. The Designer's own
 * generated data is NOT removed: it is renamed to
 * config/stables/themes/generated/color-chips.php and is now themepicker's
 * BackgroundOptions source, so deleting it would leave the new field with
 * no options at all. (The spec originally said to delete it — that was
 * wrong, and is corrected there.)
 */
class m260910_150000_removeLegacyBackgroundField extends Migration
{
    private const OLD_HANDLE = 'background';

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $field = $fieldsService->getFieldByHandle(self::OLD_HANDLE);

        // getFieldByHandle() returning null must never become a delete that
        // treats "no match" as "match everything" — see the boilerplate's
        // CLAUDE.md on the migration that wiped a production site that way.
        if ($field === null) {
            echo "    > no `background` field; nothing to remove\n";

            return true;
        }

        // Layout elements out first, then the field. The reverse order
        // leaves every entry type pointing at a uid that no longer
        // resolves, and CustomField::getField() THROWS on one rather than
        // returning null — which is what broke a re-run of m260909_220000.
        $entriesService = Craft::$app->getEntries();
        $touched = 0;

        foreach ($entriesService->getAllEntryTypes() as $entryType) {
            $fieldLayout = $entryType->getFieldLayout();
            $tabs = $fieldLayout->getTabs();
            $changed = false;

            foreach ($tabs as $tab) {
                $kept = array_values(array_filter(
                    $tab->getElements(),
                    fn(mixed $element): bool => $this->handleOf($element) !== self::OLD_HANDLE
                ));

                if (count($kept) !== count($tab->getElements())) {
                    $changed = true;
                    $fieldLayout->setTabs($tabs);
                    $tab->setElements($kept);
                }
            }

            if ($changed) {
                $entryType->setFieldLayout($fieldLayout);

                if (!$entriesService->saveEntryType($entryType)) {
                    throw new \Exception("Couldn't save the '{$entryType->handle}' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
                }

                $touched++;
            }
        }

        $fieldsService->deleteField($field);

        echo "    > removed the legacy `background` field from $touched entry types\n";

        return true;
    }

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
        echo "    > m260910_150000 removed a field and its content; restore from a backup if it's needed\n";

        return true;
    }
}
