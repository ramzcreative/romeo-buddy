<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;
use craft\fields\PlainText;
use craft\models\FieldLayoutTab;

/**
 * A `bookmark` field, first on the Settings tab of every page-builder block, so an editor can give a section an
 * anchor and link to it as `#read-this-section`.
 *
 * The id itself is never the raw value: `modules/stablestwigextensions/services/Bookmarks.php` slugifies it,
 * drops a leading `#`, prefixes a leading digit and makes a repeat unique on the page. That is the whole
 * reason this is a field plus a service rather than a field alone — "Read This Section!" is what gets typed.
 *
 * Blocks whose layout has no Settings tab get one, so the field is in the same place on every block.
 * Adopt-if-present throughout: project config applies before content migrations, and romeo-buddy already has a
 * `bookmark` field on its `imageText` block, which this adopts rather than duplicates.
 */
class m260918_100000_addBookmarkToBlocks extends Migration
{
    private const HANDLE = 'bookmark';
    private const TAB = 'Settings';
    private const BUILDERS = ['pageBuilder', 'postBuilder', 'containerBlocks', 'columnBuilder'];

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();
        $field = $fields->getFieldByHandle(self::HANDLE);

        if ($field === null) {
            $field = new PlainText();
            $field->name = 'Bookmark';
            $field->handle = self::HANDLE;
            $field->instructions = 'Optional. Lets this section be linked to directly, e.g. “pricing” becomes #pricing. Spaces and punctuation are cleaned up for you.';
            $field->placeholder = 'pricing';
            $field->multiline = false;
            $field->charLimit = 60;

            if (!$fields->saveField($field)) {
                throw new \Exception("Couldn't save the bookmark field: " . implode(', ', $field->getErrorSummary(true)));
            }

            echo "  > Created the '" . self::HANDLE . "' field.\n";
        } else {
            echo "  > Adopted this site's existing '" . self::HANDLE . "' field.\n";
        }

        $types = [];

        foreach (self::BUILDERS as $handle) {
            $builder = $fields->getFieldByHandle($handle);

            if (!$builder || !method_exists($builder, 'getEntryTypes')) {
                continue;
            }

            foreach ($builder->getEntryTypes() as $type) {
                $types[$type->handle] = $type->handle;
            }
        }

        if (!$types) {
            echo "  > No page-builder fields on this site — nothing to add it to.\n";

            return true;
        }

        $added = 0;
        $already = 0;

        foreach ($types as $handle) {
            $type = $entries->getEntryTypeByHandle($handle);

            if ($type === null) {
                continue;
            }

            $layout = $type->getFieldLayout();

            if ($layout->getFieldByHandle(self::HANDLE)) {
                $already++;
                continue;
            }

            $tabs = $layout->getTabs();
            $target = null;

            foreach ($tabs as $tab) {
                if ($tab->name === self::TAB) {
                    $target = $tab;
                    break;
                }
            }

            // A block with no Settings tab gets one, so Bookmark is in the same place on every block.
            if ($target === null) {
                $target = new FieldLayoutTab(['name' => self::TAB, 'elements' => []]);
                $tabs[] = $target;
            }

            $layout->setTabs($tabs);
            // First: it's the field that names the section, so it reads before what the section looks like.
            $target->setElements(array_merge([new CustomField($field)], $target->getElements()));
            $type->setFieldLayout($layout);

            if (!$entries->saveEntryType($type)) {
                throw new \Exception("Couldn't add " . self::HANDLE . " to '{$handle}': " . implode(', ', $type->getErrorSummary(true)));
            }

            $saved = $entries->getEntryTypeById($type->id)->getFieldLayout();

            if (!$saved->getFieldByHandle(self::HANDLE)) {
                throw new \Exception("Saved '{$handle}' without " . self::HANDLE . " on its layout; stopped.");
            }

            $added++;
        }

        echo "  > Bookmark on {$added} block type(s); {$already} already had it.\n";

        return true;
    }

    public function safeDown(): bool
    {
        echo "  > m260918_100000_addBookmarkToBlocks can be reverted by removing the field in the CP.\n";

        return false;
    }
}
