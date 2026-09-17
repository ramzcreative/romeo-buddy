<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayoutTab;

/**
 * Adds the shared `blockHeading` field to the banner, cards and slider entry
 * types, on a Settings tab — matching how columns and imageText already carry
 * it.
 *
 * WHY THIS IS A NO-OP UNTIL NOW
 * Every one of these blocks ALREADY includes `_blocks/partials/heading`:
 * banner.twig directly, and all three cards layouts plus all four slider
 * layouts. The partial does `entry['blockHeading'].all() ?? false`, so with no
 * such field on the entry type the `??` swallows it and nothing renders — the
 * include has been dead markup rather than a missing feature.
 *
 * BANNER IS THE ONE THAT REALLY GAINS SOMETHING
 * m260819_150000 consolidated banner's own heading/intro into the shared
 * `items` field, deliberately ("those describe the block, not the item"). That
 * left banner with no block-level heading capability at all. This gives it back
 * — at the block level this time, where it belongs.
 *
 * TEMPLATE CHANGES SHIP WITH THIS
 * The field alone is not enough for cards or slider, because their layouts are
 * included with `only` (slider always did; cards gains it here for
 * consistency), so `entry` is not in scope inside them and the partial has
 * nothing to read. `entry` is now passed explicitly by both.
 *
 * SAFETY
 * Additive. It appends one field to a tab it creates, and skips an entry type
 * that already has the field. Nothing is removed, reordered or re-pointed, and
 * no content rows are touched.
 *
 * Note the setTabs()/setElements() ordering below: setElements() on a tab that
 * is not yet attached to its layout throws "Field layout tab is missing its
 * field layout". Build the tabs, call setTabs(), THEN setElements().
 */
class m260822_090000_addBlockHeadingToBannerCardsSlider extends Migration
{
    private const ENTRY_TYPES = ['banner', 'cards', 'slider'];
    private const FIELD = 'blockHeading';
    private const TAB = 'Settings';

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $field = Craft::$app->getFields()->getFieldByHandle(self::FIELD);

        if (!$field) {
            throw new \Exception("No '" . self::FIELD . "' field — nothing to attach.");
        }

        foreach (self::ENTRY_TYPES as $handle) {
            $type = $this->entryTypeByHandle($handle);

            if (!$type) {
                echo "    > {$handle}: entry type not found, skipped\n";
                continue;
            }

            $layout = $type->getFieldLayout();
            $tabs = $layout->getTabs();

            // Already attached? Leave the CP's own arrangement alone — re-adding
            // would give the editor two Block Heading fields.
            foreach ($tabs as $tab) {
                foreach ($tab->getElements() as $element) {
                    if ($element instanceof CustomField && $element->getField()->handle === self::FIELD) {
                        echo "    > {$handle}: already has " . self::FIELD . ", skipped\n";
                        continue 3;
                    }
                }
            }

            // Reuse a Settings tab if the type already has one, so this doesn't
            // end up with two tabs of the same name.
            $target = null;

            foreach ($tabs as $tab) {
                if ($tab->name === self::TAB) {
                    $target = $tab;
                    break;
                }
            }

            $created = false;

            if (!$target) {
                $target = new FieldLayoutTab(['name' => self::TAB]);
                $tabs[] = $target;
                $created = true;
            }

            $layout->setTabs($tabs);
            $target->setElements(array_merge($target->getElements(), [new CustomField($field)]));
            $type->setFieldLayout($layout);

            if (!$entries->saveEntryType($type)) {
                throw new \Exception(
                    "Couldn't add " . self::FIELD . " to '{$handle}': "
                    . implode(', ', $type->getErrorSummary(true))
                );
            }

            echo "    > {$handle}: " . self::FIELD
                . ($created ? " added on a new " . self::TAB . " tab\n" : " added to the existing " . self::TAB . " tab\n");
        }

        return true;
    }

    public function safeDown(): bool
    {
        $entries = Craft::$app->getEntries();

        foreach (self::ENTRY_TYPES as $handle) {
            $type = $this->entryTypeByHandle($handle);

            if (!$type) {
                continue;
            }

            $layout = $type->getFieldLayout();
            $tabs = $layout->getTabs();
            $changed = false;

            foreach ($tabs as $tab) {
                $kept = array_values(array_filter(
                    $tab->getElements(),
                    fn($el) => !($el instanceof CustomField && $el->getField()->handle === self::FIELD),
                ));

                if (count($kept) !== count($tab->getElements())) {
                    $changed = true;
                    $layout->setTabs($tabs);
                    $tab->setElements($kept);
                }
            }

            // A Settings tab this migration created and then emptied is dropped
            // rather than left behind as an empty tab in the CP.
            if ($changed) {
                $tabs = array_values(array_filter(
                    $tabs,
                    fn($tab) => $tab->name !== self::TAB || $tab->getElements(),
                ));

                $layout->setTabs($tabs);
                $type->setFieldLayout($layout);
                $entries->saveEntryType($type);
            }
        }

        return true;
    }

    private function entryTypeByHandle(string $handle): ?\craft\models\EntryType
    {
        foreach (Craft::$app->getEntries()->getAllEntryTypes() as $type) {
            if ($type->handle === $handle) {
                return $type;
            }
        }

        return null;
    }
}
