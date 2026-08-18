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
 * Converts 'pageBuilder' from craft\ckeditor\Field to craft\fields\Matrix
 * in place — same field id/uid/handle, so every field layout that already
 * references it (Page, Landing Page, ...) picks up the new type
 * automatically with no layout changes needed. Ported from stables'
 * pageBuilder-ckeditor-to-matrix migration — see that repo's identical
 * m260817_160000_convertPageBuilderToMatrix.php for the full reasoning.
 * Adds 'activitySheet' to the block set, a type specific to this site.
 *
 * CKEditor's embedded entries and Matrix's blocks already share identical
 * storage (`entries.fieldId` + `elements_owners.ownerId`), so a currently
 * -embedded entry doesn't need to move. Two things still need real work
 * before the type flip, though:
 *
 * 1. Freeform 'markup' chunks (plain rich text typed between embedded
 *    entries) aren't entries at all — Matrix has no equivalent, so each
 *    one becomes a new 'Text' block entry (see the addTextEntryType
 *    migration).
 * 2. `elements_owners` rows for this field turned out NOT to reliably
 *    reflect current content on stables (content removed from the
 *    CKEditor text over time leaves its relationship row behind
 *    uncleaned) — this repo's own survey found the same pattern, smaller
 *    in scale (3 stale rows on one entry). Trusting them as-is would
 *    resurrect stale/removed content once Matrix starts rendering every
 *    owned child unconditionally. The only authoritative source for
 *    "what's actually there, in what order" is parsing each entry's
 *    CKEditor content the same way the front end already does
 *    (`craft\ckeditor\data\FieldData::getChunks()`, the same parser
 *    `_blocks/_builder.twig` relies on) — so this migration does that
 *    per entry, synthesizes Text blocks for markup runs, sets correct
 *    sequential sortOrder for exactly what's referenced, and removes any
 *    relationship row that isn't.
 *
 * Only touches canonical, non-deleted entries — drafts/revisions have
 * their own separate nested-entry copies and are out of scope; they may
 * simply become non-restorable after this migration, which is accepted.
 */
class m260817_190000_convertPageBuilderToMatrix extends Migration
{
    private const FIELD_HANDLE = 'pageBuilder';
    private const TEXT_ENTRY_TYPE_HANDLE = 'text';
    private const TEXT_FIELD_HANDLE = 'bodyText';

    private const BLOCK_TYPE_HANDLES = [
        'entryHeading',
        'imageText',
        'container',
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
        $db = Craft::$app->getDb();

        $pageBuilderField = $fieldsService->getFieldByHandle(self::FIELD_HANDLE);

        if (!$pageBuilderField) {
            throw new \Exception("Couldn't find the 'pageBuilder' field — expected it to already exist.");
        }

        if ($pageBuilderField instanceof Matrix) {
            // Already converted — safe to run this migration more than
            // once (e.g. re-running after an earlier partial failure).
            return true;
        }

        if (!$pageBuilderField instanceof CkeditorField) {
            throw new \Exception("The 'pageBuilder' field isn't a CKEditor field — expected it to be, before conversion.");
        }

        $textEntryType = $entriesService->getEntryTypeByHandle(self::TEXT_ENTRY_TYPE_HANDLE);
        if (!$textEntryType) {
            throw new \Exception("Couldn't find the 'text' entry type — expected the addTextEntryType migration to have run already.");
        }

        // A nested entry's typeId is validated against its owning field's
        // *current* entryTypes setting — 'text' isn't in pageBuilder's
        // (still-CKEditor) entryTypes list yet, so creating a Text block
        // below would fail validation. Add it now, while still a
        // CKEditor field; the type flip further down sets the field's
        // final entryTypes list again anyway.
        $currentHandles = array_map(fn($entryType) => $entryType->handle, $pageBuilderField->getEntryTypes());

        if (!in_array(self::TEXT_ENTRY_TYPE_HANDLE, $currentHandles, true)) {
            $pageBuilderField->setEntryTypes([...$pageBuilderField->getEntryTypes(), $textEntryType]);

            if (!$fieldsService->saveField($pageBuilderField)) {
                throw new \Exception("Couldn't add the 'text' entry type to 'pageBuilder': " . implode(', ', $pageBuilderField->getErrorSummary(true)));
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
                    'fieldId' => $pageBuilderField->id,
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

            // Drop any elements_owners row for this owner+field that isn't
            // part of the authoritative, just-parsed sequence — stale
            // leftovers from content removed from the CKEditor text over
            // time (see class docblock).
            $db->createCommand()->delete(
                '{{%elements_owners}}',
                [
                    'and',
                    ['ownerId' => $entry->id],
                    ['elementId' => (new \yii\db\Query())
                        ->select('id')
                        ->from('{{%entries}}')
                        ->where(['fieldId' => $pageBuilderField->id]),
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
            'id' => $pageBuilderField->id,
            'uid' => $pageBuilderField->uid,
            'name' => $pageBuilderField->name,
            'handle' => self::FIELD_HANDLE,
            'context' => $pageBuilderField->context,
            'viewMode' => Matrix::VIEW_MODE_BLOCKS,
        ]);
        $matrixField->setEntryTypes($blockEntryTypes);

        if (!$fieldsService->saveField($matrixField)) {
            throw new \Exception("Couldn't convert the 'pageBuilder' field to Matrix: " . implode(', ', $matrixField->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        // Best-effort only — reverts the field type but doesn't attempt
        // to restore original sortOrder/stale rows or reconstruct the
        // original CKEditor HTML. Synthesized Text blocks are left in
        // place rather than deleted, since by this point they may be the
        // only copy of that content.
        $fieldsService = Craft::$app->getFields();
        $pageBuilderField = $fieldsService->getFieldByHandle(self::FIELD_HANDLE);

        if (!$pageBuilderField instanceof Matrix) {
            return true;
        }

        $ckeditorField = new CkeditorField([
            'id' => $pageBuilderField->id,
            'uid' => $pageBuilderField->uid,
            'name' => $pageBuilderField->name,
            'handle' => self::FIELD_HANDLE,
            'context' => $pageBuilderField->context,
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
        $ckeditorField->setEntryTypes($pageBuilderField->getEntryTypes());

        if (!$fieldsService->saveField($ckeditorField)) {
            throw new \Exception("Couldn't revert the 'pageBuilder' field to CKEditor: " . implode(', ', $ckeditorField->getErrorSummary(true)));
        }

        return true;
    }
}
