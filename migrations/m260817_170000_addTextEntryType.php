<?php

namespace craft\contentmigrations;

use Craft;
use craft\ckeditor\Field as CkeditorField;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

/**
 * Adds a 'Text' entry type — the page-builder's new explicit block for
 * freeform prose, replacing the free-flowing 'markup' chunks the CKEditor
 * -based 'pageBuilder' field used to allow between embedded entries.
 * Ported from stables' pageBuilder-ckeditor-to-matrix migration — see that
 * repo's identical m260817_140000_addTextEntryType.php for the full
 * reasoning (Matrix has no equivalent of a chunk with no entry type of
 * its own, so editors now add a dedicated Text block wherever they want
 * a paragraph).
 *
 * Its body field, 'bodyText', is a CKEditor field cloned from
 * 'pageBuilder''s current settings (same toolbar/preset/purify config) but
 * scoped to only embed Buttons entries — not the full block set, since
 * this field doesn't need pageBuilder's own recursive block-embedding
 * capability, just the ability to drop a button/CTA inline in a
 * paragraph.
 */
class m260817_170000_addTextEntryType extends Migration
{
    private const FIELD_HANDLE = 'bodyText';
    private const ENTRY_TYPE_HANDLE = 'text';

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();

        $bodyTextField = $fieldsService->getFieldByHandle(self::FIELD_HANDLE);

        if (!$bodyTextField) {
            $buttonsEntryType = $entriesService->getEntryTypeByHandle('buttons');

            if (!$buttonsEntryType) {
                throw new \Exception("Couldn't find the 'buttons' entry type — expected it to already exist.");
            }

            $bodyTextField = new CkeditorField([
                'name' => 'Body Text',
                'handle' => self::FIELD_HANDLE,
                'toolbar' => [
                    'bookmark',
                    'sourceEditing',
                    '|',
                    'createEntry',
                    'style',
                    'heading',
                    '|',
                    'alignment',
                    'fontColor',
                    'bulletedList',
                    'numberedList',
                    'bold',
                    'italic',
                    'underline',
                    'strikethrough',
                    'superscript',
                    'subscript',
                    'horizontalLine',
                    'findAndReplace',
                    'link',
                    'removeFormat',
                ],
                'headingLevels' => [2, 3, 4, 5, 6],
                'imageMode' => CkeditorField::IMAGE_MODE_IMG,
                'jsFile' => 'Default.js',
                'purifyHtml' => true,
                'sourceEditingGroups' => ['__ADMINS__'],
                'showWordCount' => false,
                'wordLimit' => null,
                'parseEmbeds' => false,
                'availableTransforms' => '',
                'availableVolumes' => '*',
            ]);
            $bodyTextField->setEntryTypes([$buttonsEntryType]);

            if (!$fieldsService->saveField($bodyTextField)) {
                throw new \Exception("Couldn't save the 'bodyText' field: " . implode(', ', $bodyTextField->getErrorSummary(true)));
            }
        }

        $entryType = $entriesService->getEntryTypeByHandle(self::ENTRY_TYPE_HANDLE);

        if (!$entryType) {
            // setElements() reads the tab's own $layout back-reference, so
            // the tab has to be attached to the FieldLayout via setTabs()
            // first.
            $contentTab = new FieldLayoutTab(['name' => 'Content']);

            $fieldLayout = new FieldLayout(['type' => \craft\elements\Entry::class]);
            $fieldLayout->setTabs([$contentTab]);

            $contentTab->setElements([new CustomField($bodyTextField)]);

            $entryType = new EntryType([
                'name' => 'Text',
                'handle' => self::ENTRY_TYPE_HANDLE,
                'hasTitleField' => false,
                'showSlugField' => false,
                'showStatusField' => true,
                'icon' => 'text',
                'color' => 'fuchsia',
            ]);
            $entryType->setFieldLayout($fieldLayout);

            if (!$entriesService->saveEntryType($entryType)) {
                throw new \Exception("Couldn't save the 'Text' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        $entriesService = Craft::$app->getEntries();
        $fieldsService = Craft::$app->getFields();

        if ($entryType = $entriesService->getEntryTypeByHandle(self::ENTRY_TYPE_HANDLE)) {
            $entriesService->deleteEntryType($entryType);
        }

        if ($field = $fieldsService->getFieldByHandle(self::FIELD_HANDLE)) {
            $fieldsService->deleteField($field);
        }

        return true;
    }
}
