<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;

/**
 * Puts the page builder on books, so a book page can carry the same blocks any other page can — testimonials
 * above all, which is what feeds that page's LocalBusiness review markup (craft-modules' seo module,
 * buildReviews()).
 *
 * The layout had NO builder field at all: `_sections/books/_detail.twig` reads `entry.postBuilder`, which has
 * quietly resolved to nothing for as long as it has been there. This adds `pageBuilder` — the full block set,
 * not the post one — and the template is changed to render it in the same commit.
 *
 * Adopt-if-present: project config applies before content migrations, so the field may already be on the
 * layout by the time this runs.
 */
class m260917_260000_addPageBuilderToBooks extends Migration
{
    private const TYPE = 'book';
    private const FIELD = 'pageBuilder';
    private const TAB = 'Content';

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $type = $entries->getEntryTypeByHandle(self::TYPE);

        if ($type === null) {
            echo "  > No '" . self::TYPE . "' entry type on this site — nothing to do.\n";

            return true;
        }

        $field = Craft::$app->getFields()->getFieldByHandle(self::FIELD);

        if ($field === null) {
            throw new \Exception("No '" . self::FIELD . "' field on this site; stopped rather than inventing one.");
        }

        $layout = $type->getFieldLayout();

        if ($layout->getFieldByHandle(self::FIELD)) {
            echo "  > '" . self::TYPE . "' already has " . self::FIELD . ".\n";

            return true;
        }

        $tabs = $layout->getTabs();
        $target = null;

        foreach ($tabs as $tab) {
            if ($tab->name === self::TAB) {
                $target = $tab;
                break;
            }
        }

        // The tab it belongs in, or the first one — a layout always has at least one.
        $target ??= $tabs[0] ?? null;

        if ($target === null) {
            throw new \Exception("'" . self::TYPE . "' has no field layout tabs to add " . self::FIELD . " to.");
        }

        $before = count($layout->getCustomFieldElements());

        $elements = $target->getElements();
        $elements[] = new CustomField($field);
        $layout->setTabs($tabs);
        $target->setElements($elements);
        $type->setFieldLayout($layout);

        if (!$entries->saveEntryType($type)) {
            throw new \Exception("Couldn't put " . self::FIELD . " on '" . self::TYPE . "': " . implode(', ', $type->getErrorSummary(true)));
        }

        $after = $entries->getEntryTypeById($type->id)->getFieldLayout();

        if (!$after->getFieldByHandle(self::FIELD)) {
            throw new \Exception('Saved without ' . self::FIELD . ' on the layout; stopped.');
        }

        echo '  > Added ' . self::FIELD . " to '" . self::TYPE . "' on the '{$target->name}' tab ("
            . $before . ' fields before, ' . count($after->getCustomFieldElements()) . " after).\n";

        return true;
    }

    public function safeDown(): bool
    {
        echo "  > m260917_260000_addPageBuilderToBooks can be reverted by removing the field in the CP.\n";

        return false;
    }
}
