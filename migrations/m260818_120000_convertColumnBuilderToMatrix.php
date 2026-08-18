<?php

namespace craft\contentmigrations;

use Craft;
use craft\ckeditor\data\Entry as CkeEntryChunk;
use craft\ckeditor\data\FieldData;
use craft\ckeditor\data\Markup as CkeMarkupChunk;
use craft\ckeditor\Field as CkeditorField;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fields\Matrix;
use craft\helpers\ElementHelper;
use craft\helpers\StringHelper;

/**
 * Converts 'columnBuilder' from craft\ckeditor\Field to craft\fields\Matrix
 * in place. Ported from stables' identical
 * m260818_120000_convertColumnBuilderToMatrix.php — see that migration's
 * docblock for the full reasoning. columnBuilder's entry types here match
 * stables' exactly (Form, Blockquote, Buttons, Image), so no block-set
 * adaptation was needed beyond adding 'text'.
 *
 * Only touches canonical, non-deleted entries — drafts/revisions are out
 * of scope, matching the pageBuilder/postBuilder migrations' convention.
 */
class m260818_120000_convertColumnBuilderToMatrix extends Migration
{
    private const FIELD_HANDLE = 'columnBuilder';
    private const TEXT_ENTRY_TYPE_HANDLE = 'text';
    private const TEXT_FIELD_HANDLE = 'bodyText';

    private const BLOCK_TYPE_HANDLES = [
        'form',
        'blockquote',
        'buttons',
        'image',
        'text',
    ];

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();
        $db = Craft::$app->getDb();

        $columnBuilderField = $fieldsService->getFieldByHandle(self::FIELD_HANDLE);

        if (!$columnBuilderField) {
            throw new \Exception("Couldn't find the 'columnBuilder' field — expected it to already exist.");
        }

        if ($columnBuilderField instanceof Matrix) {
            // Already converted — safe to run this migration more than once.
            return true;
        }

        if (!$columnBuilderField instanceof CkeditorField) {
            throw new \Exception("The 'columnBuilder' field isn't a CKEditor field — expected it to be, before conversion.");
        }

        $textEntryType = $entriesService->getEntryTypeByHandle(self::TEXT_ENTRY_TYPE_HANDLE);
        if (!$textEntryType) {
            throw new \Exception("Couldn't find the 'text' entry type — expected the addTextEntryType migration to have run already.");
        }

        $currentHandles = array_map(fn($entryType) => $entryType->handle, $columnBuilderField->getEntryTypes());

        if (!in_array(self::TEXT_ENTRY_TYPE_HANDLE, $currentHandles, true)) {
            $columnBuilderField->setEntryTypes([...$columnBuilderField->getEntryTypes(), $textEntryType]);

            if (!$fieldsService->saveField($columnBuilderField)) {
                throw new \Exception("Couldn't add the 'text' entry type to 'columnBuilder': " . implode(', ', $columnBuilderField->getErrorSummary(true)));
            }
        }

        // --- content pass: synthesize Text blocks, fix sortOrder, drop stale rows ---

        $entries = Entry::find()->status(null)->all();

        foreach ($entries as $entry) {
            if (!$entry->getFieldLayout()?->getFieldByHandle(self::FIELD_HANDLE)) {
                continue;
            }

            /** @var FieldData|null $value */
            $value = $entry->getFieldValue(self::FIELD_HANDLE);
            if (!$value instanceof FieldData) {
                continue;
            }

            $chunks = $value->getChunks(false);
            if ($chunks->isEmpty()) {
                continue;
            }

            $finalChildIds = [];

            foreach ($chunks as $chunk) {
                if ($chunk instanceof CkeEntryChunk) {
                    $finalChildIds[] = (int)$chunk->entryId;
                    continue;
                }

                if (!$chunk instanceof CkeMarkupChunk) {
                    continue;
                }

                $rawHtml = (string)$chunk->rawHtml;
                if (trim(strip_tags($rawHtml)) === '') {
                    continue;
                }

                $textEntry = Craft::createObject([
                    'class' => Entry::class,
                    'siteId' => $entry->siteId,
                    'uid' => StringHelper::UUID(),
                    'typeId' => $textEntryType->id,
                    'fieldId' => $columnBuilderField->id,
                    'primaryOwner' => $entry,
                    'owner' => $entry,
                    'slug' => ElementHelper::tempSlug(),
                ]);
                $textEntry->setFieldValue(self::TEXT_FIELD_HANDLE, $rawHtml);

                if (!Craft::$app->getElements()->saveElement($textEntry)) {
                    throw new \Exception("Couldn't create a 'Text' block for entry #{$entry->id}: " . implode(', ', $textEntry->getErrorSummary(true)));
                }

                $finalChildIds[] = $textEntry->id;
            }

            if (empty($finalChildIds)) {
                continue;
            }

            $db->createCommand()->delete(
                '{{%elements_owners}}',
                [
                    'and',
                    ['ownerId' => $entry->id],
                    ['elementId' => (new \yii\db\Query())
                        ->select('id')
                        ->from('{{%entries}}')
                        ->where(['fieldId' => $columnBuilderField->id]),
                    ],
                    ['not', ['elementId' => $finalChildIds]],
                ],
            )->execute();

            foreach (array_values($finalChildIds) as $index => $childId) {
                $db->createCommand()->update(
                    '{{%elements_owners}}',
                    ['sortOrder' => $index + 1],
                    ['ownerId' => $entry->id, 'elementId' => $childId],
                )->execute();
            }
        }

        // --- field type conversion ---

        $blockEntryTypes = [];
        foreach (self::BLOCK_TYPE_HANDLES as $handle) {
            $blockEntryType = $entriesService->getEntryTypeByHandle($handle);

            if (!$blockEntryType) {
                throw new \Exception("Couldn't find the '$handle' entry type — expected it to already exist.");
            }

            $blockEntryTypes[] = $blockEntryType;
        }

        $matrixField = new Matrix([
            'id' => $columnBuilderField->id,
            'uid' => $columnBuilderField->uid,
            'name' => $columnBuilderField->name,
            'handle' => self::FIELD_HANDLE,
            'context' => $columnBuilderField->context,
            'viewMode' => Matrix::VIEW_MODE_BLOCKS,
        ]);
        $matrixField->setEntryTypes($blockEntryTypes);

        if (!$fieldsService->saveField($matrixField)) {
            throw new \Exception("Couldn't convert the 'columnBuilder' field to Matrix: " . implode(', ', $matrixField->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $columnBuilderField = $fieldsService->getFieldByHandle(self::FIELD_HANDLE);

        if (!$columnBuilderField instanceof Matrix) {
            return true;
        }

        $ckeditorField = new CkeditorField([
            'id' => $columnBuilderField->id,
            'uid' => $columnBuilderField->uid,
            'name' => $columnBuilderField->name,
            'handle' => self::FIELD_HANDLE,
            'context' => $columnBuilderField->context,
            'toolbar' => [
                'bookmark', 'sourceEditing', '|', 'createEntry', 'style', 'heading', '|',
                'alignment', 'fontColor', 'bulletedList', 'numberedList', 'bold', 'italic',
                'underline', 'strikethrough', 'superscript', 'subscript', 'horizontalLine',
                'findAndReplace', 'link', 'removeFormat',
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
        $ckeditorField->setEntryTypes($columnBuilderField->getEntryTypes());

        if (!$fieldsService->saveField($ckeditorField)) {
            throw new \Exception("Couldn't revert the 'columnBuilder' field to CKEditor: " . implode(', ', $ckeditorField->getErrorSummary(true)));
        }

        return true;
    }
}
