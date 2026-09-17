<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\fieldlayoutelements\CustomField;

require_once __DIR__ . '/m260917_200000_addGalleriesSection.php';

/**
 * Galleries, phase 2 (business blocks spec §5.2): takes `galleryImages` off the `gallery` block and `project`, leaving
 * `galleryEntry`. Its own deploy, after phase 1 (m260917_200000) is live.
 *
 * Deletes no field and no content: `galleryImages` stays on gallery entries, and the old values stay in the relations
 * table. First it runs phase 1's copy again, from that table, for anything given images between the deploys, so it
 * gives the same result whether or not project config already took the field off the layouts.
 */
class m260917_210000_removeGalleryImagesFromBlocks extends Migration
{
    private const PREPARED = 'm260917_200000_addGalleriesSection';

    public function safeUp(): bool
    {
        $prepared = (new Query())->from('{{%migrations}}')->where(['track' => 'content', 'name' => self::PREPARED])->exists();

        if (!$prepared) {
            throw new \Exception('Galleries phase 1 (' . self::PREPARED . ') must be applied first; nothing was changed.');
        }

        (new m260917_200000_addGalleriesSection())->catchUp();

        foreach (['gallery', 'project'] as $typeHandle) {
            $this->removeFromLayout($typeHandle);
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "    > m260917_210000_removeGalleryImagesFromBlocks can't be reverted; restore a backup.\n";

        return false;
    }

    private function removeFromLayout(string $typeHandle): void
    {
        $entries = Craft::$app->getEntries();
        $type = $entries->getEntryTypeByHandle($typeHandle);

        if ($type === null || !$type->getFieldLayout()->getFieldByHandle('galleryImages')) {
            return;
        }

        $layout = $type->getFieldLayout();
        $tabs = $layout->getTabs();
        $kept = array_values(array_filter(
            array_map(static fn(CustomField $el) => $el->uid, $layout->getCustomFieldElements()),
            static fn($uid) => $layout->getElementByUid($uid)?->getField()->handle !== 'galleryImages'
        ));

        foreach ($tabs as $tab) {
            $tab->setElements(array_values(array_filter(
                $tab->getElements(),
                static fn($el) => !($el instanceof CustomField && $el->getField()->handle === 'galleryImages')
            )));
        }

        $layout->setTabs($tabs);
        $type->setFieldLayout($layout);

        if (!$entries->saveEntryType($type)) {
            throw new \Exception("Couldn't take galleryImages off '{$typeHandle}': " . implode(', ', $type->getErrorSummary(true)));
        }

        $after = array_map(static fn(CustomField $el) => $el->uid, $entries->getEntryTypeById($type->id)->getFieldLayout()->getCustomFieldElements());

        if (array_diff($kept, $after) !== []) {
            throw new \Exception("Taking galleryImages off '{$typeHandle}' changed the rest of its layout; stopped.");
        }

        echo "    > Took galleryImages off '{$typeHandle}'.\n";
    }
}
