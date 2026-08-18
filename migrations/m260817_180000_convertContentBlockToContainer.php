<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Matrix;

/**
 * Repurposes the 'Content' entry type (handle 'contentBlock') into
 * 'Container' (handle 'container'). Ported from stables' pageBuilder-
 * ckeditor-to-matrix migration — see that repo's identical
 * m260817_150000_convertContentBlockToContainer.php for the full
 * reasoning: 'Content' was never really a plain-text block, its actual
 * job was letting editors group multiple blocks together inside one
 * wrapping <div> for a shared background/graphic, via a recursive nested
 * 'pageBuilder' field. This swaps that recursive CKEditor field for a
 * real nested Matrix field ('containerBlocks') offering the same block
 * set 'pageBuilder' does — minus Container itself (no self-nesting) —
 * plus the new 'Text' block. Includes 'activitySheet', a block type
 * specific to this site that stables' pageBuilder doesn't have.
 *
 * The entry type's 'Settings' tab (Container Background, Block Heading)
 * is left untouched — background suppression when nested is handled at
 * the template level via `entry.owner.type`, not a field-layout change.
 *
 * Does not touch any existing entries' content — any Container-owned
 * (formerly Content-owned) nested pageBuilder content that already
 * exists needs the same content migration this field-layout swap is
 * deliberately deferring for pageBuilder itself.
 */
class m260817_180000_convertContentBlockToContainer extends Migration
{
    private const OLD_ENTRY_TYPE_HANDLE = 'contentBlock';
    private const NEW_ENTRY_TYPE_HANDLE = 'container';
    private const FIELD_HANDLE = 'containerBlocks';

    private const BLOCK_TYPE_HANDLES = [
        'entryHeading',
        'imageText',
        'columns',
        'hero',
        'banner',
        'spotlight',
        'slider',
        'cards',
        'posts',
        'form',
        'image',
        'video',
        'accordion',
        'blockquote',
        'buttons',
        'activitySheet',
        'text',
    ];

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();

        $containerBlocksField = $fieldsService->getFieldByHandle(self::FIELD_HANDLE);

        if (!$containerBlocksField) {
            $blockEntryTypes = [];
            foreach (self::BLOCK_TYPE_HANDLES as $handle) {
                $blockEntryType = $entriesService->getEntryTypeByHandle($handle);

                if (!$blockEntryType) {
                    throw new \Exception("Couldn't find the '$handle' entry type — expected it to already exist.");
                }

                $blockEntryTypes[] = $blockEntryType;
            }

            $containerBlocksField = new Matrix([
                'name' => 'Container Blocks',
                'handle' => self::FIELD_HANDLE,
                'viewMode' => Matrix::VIEW_MODE_BLOCKS,
            ]);
            $containerBlocksField->setEntryTypes($blockEntryTypes);

            if (!$fieldsService->saveField($containerBlocksField)) {
                throw new \Exception("Couldn't save the 'containerBlocks' field: " . implode(', ', $containerBlocksField->getErrorSummary(true)));
            }
        }

        $entryType = $entriesService->getEntryTypeByHandle(self::OLD_ENTRY_TYPE_HANDLE)
            ?? $entriesService->getEntryTypeByHandle(self::NEW_ENTRY_TYPE_HANDLE);

        if (!$entryType) {
            throw new \Exception("Couldn't find the '" . self::OLD_ENTRY_TYPE_HANDLE . "' entry type — expected it to already exist.");
        }

        $entryType->name = 'Container';
        $entryType->handle = self::NEW_ENTRY_TYPE_HANDLE;
        $entryType->icon = 'layer-group';

        $fieldLayout = $entryType->getFieldLayout();
        $tabs = $fieldLayout->getTabs();
        $contentTab = null;

        foreach ($tabs as $tab) {
            if ($tab->name === 'Content') {
                $contentTab = $tab;
                break;
            }
        }

        if (!$contentTab) {
            throw new \Exception("Couldn't find the '" . self::OLD_ENTRY_TYPE_HANDLE . "' entry type's 'Content' tab.");
        }

        $contentTab->setElements([new CustomField($containerBlocksField)]);
        $fieldLayout->setTabs($tabs);
        $entryType->setFieldLayout($fieldLayout);

        if (!$entriesService->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'Container' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();

        $entryType = $entriesService->getEntryTypeByHandle(self::NEW_ENTRY_TYPE_HANDLE);

        if ($entryType) {
            $pageBuilderField = $fieldsService->getFieldByHandle('pageBuilder');

            if ($pageBuilderField) {
                $fieldLayout = $entryType->getFieldLayout();
                $tabs = $fieldLayout->getTabs();

                foreach ($tabs as $tab) {
                    if ($tab->name === 'Content') {
                        $tab->setElements([new CustomField($pageBuilderField)]);
                    }
                }

                $fieldLayout->setTabs($tabs);
                $entryType->setFieldLayout($fieldLayout);
            }

            $entryType->name = 'Content';
            $entryType->handle = self::OLD_ENTRY_TYPE_HANDLE;
            $entryType->icon = 'text';
            $entriesService->saveEntryType($entryType);
        }

        if ($field = $fieldsService->getFieldByHandle(self::FIELD_HANDLE)) {
            $fieldsService->deleteField($field);
        }

        return true;
    }
}
