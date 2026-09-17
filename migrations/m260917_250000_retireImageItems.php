<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Matrix;

require_once __DIR__ . '/m260917_240000_checkImageItemsCopied.php';

/**
 * Retires this site's old `imageItems` field and its `imageItem` entry type. Every one of them was copied into the
 * shared `items` field by this site's own phase 1 (`moveBlockContentIntoItems`), so this deletes rather than converts —
 * the boilerplate's consolidation (m260819_120000) would re-type them and leave each block holding its item twice.
 *
 * The check migration (m260917_240000) runs again first, against the content as it is now, and throws if any imageItem
 * has no matching item. Deleting the Matrix field takes its nested entries with it, which is what removes the four
 * entries and the six in revisions.
 *
 * Can't be reverted: restore the backup taken in up() below.
 */
class m260917_250000_retireImageItems extends Migration
{
    /**
     * The backup runs here, before Craft opens the migration's transaction: mysqldump inside that transaction waits on
     * locks it holds, and the deploy hangs (see stables' CLAUDE.md).
     */
    public function up(bool $throwExceptions = false): bool
    {
        if (Craft::$app->getFields()->getFieldByHandle('imageItems') !== null) {
            try {
                echo '    > Backup before retiring imageItems: ' . Craft::$app->getDb()->backup() . "\n";
            } catch (\Throwable $e) {
                echo "    > Couldn't back up the database, so nothing was removed: {$e->getMessage()}\n";

                if ($throwExceptions) {
                    throw $e;
                }

                return false;
            }
        }

        return parent::up($throwExceptions);
    }

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();
        $field = $fields->getFieldByHandle('imageItems');

        if ($field === null) {
            echo "  > No 'imageItems' field — already retired.\n";

            return true;
        }

        if (!$field instanceof Matrix) {
            throw new \Exception("'imageItems' isn't a Matrix field; stopped rather than deleting it.");
        }

        // The same check as m260917_240000, against the content as it stands now.
        (new m260917_240000_checkImageItemsCopied())->safeUp();

        $before = Entry::find()->type('imageItem')->status(null)->drafts(null)->provisionalDrafts(null)->site('*')->unique()->count();

        foreach (['imageText'] as $typeHandle) {
            $this->removeFromLayout($typeHandle, 'imageItems');
        }

        if (!$fields->deleteField($field)) {
            throw new \Exception("Couldn't delete the 'imageItems' field.");
        }

        $type = $entries->getEntryTypeByHandle('imageItem');

        if ($type !== null && $type->handle === 'imageItem') {
            $entries->deleteEntryType($type);
        }

        $left = Entry::find()->type('imageItem')->status(null)->drafts(null)->provisionalDrafts(null)->site('*')->unique()->count();

        echo "  > Retired imageItems: {$before} nested entr" . ($before === 1 ? 'y' : 'ies') . " removed with the field, {$left} left.\n";

        return true;
    }

    public function safeDown(): bool
    {
        echo "    > m260917_250000_retireImageItems deleted content; restore a backup.\n";

        return false;
    }

    private function removeFromLayout(string $typeHandle, string $fieldHandle): void
    {
        $entries = Craft::$app->getEntries();
        $type = $entries->getEntryTypeByHandle($typeHandle);

        if ($type === null || !$type->getFieldLayout()->getFieldByHandle($fieldHandle)) {
            return;
        }

        $layout = $type->getFieldLayout();
        $tabs = $layout->getTabs();
        $kept = [];

        foreach ($layout->getCustomFieldElements() as $element) {
            if ($element->getField()->handle !== $fieldHandle) {
                $kept[] = $element->uid;
            }
        }

        foreach ($tabs as $tab) {
            $tab->setElements(array_values(array_filter(
                $tab->getElements(),
                static fn($el) => !($el instanceof CustomField && $el->getField()->handle === $fieldHandle)
            )));
        }

        $layout->setTabs($tabs);
        $type->setFieldLayout($layout);

        if (!$entries->saveEntryType($type)) {
            throw new \Exception("Couldn't take {$fieldHandle} off '{$typeHandle}': " . implode(', ', $type->getErrorSummary(true)));
        }

        $after = array_map(static fn(CustomField $el) => $el->uid, $entries->getEntryTypeById($type->id)->getFieldLayout()->getCustomFieldElements());

        if (array_diff($kept, $after) !== []) {
            throw new \Exception("Taking {$fieldHandle} off '{$typeHandle}' changed the rest of its layout; stopped.");
        }

        echo "  > Took {$fieldHandle} off '{$typeHandle}'.\n";
    }
}
