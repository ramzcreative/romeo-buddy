<?php

namespace craft\contentmigrations;

use Craft;
use craft\ckeditor\Field as CkeditorField;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fields\Dropdown;
use craft\fields\PlainText;
use craft\fields\Table;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

/**
 * Adds an 'Activity Sheet' block type to the pageBuilder CKEditor field (see
 * modules/activitysheets) so an editor can drop a downloadable word-search
 * or maze PDF onto any page. Word search/maze are both generated entirely
 * from these fields at download time — no artwork involved. Coloring pages
 * are intentionally not included here — they need real character line-art
 * from the illustrator, which doesn't exist yet.
 */
class m260717_060753_addActivitySheetBlock extends Migration
{
    private const NEW_FIELD_HANDLES = ['activitySheetType', 'activitySheetIntro', 'activitySheetWords', 'activitySheetDifficulty'];

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();

        $fieldsToCreate = [
            'activitySheetType' => new Dropdown([
                'name' => 'Activity Type',
                'handle' => 'activitySheetType',
                'options' => [
                    ['label' => 'Word Search', 'value' => 'wordSearch'],
                    ['label' => 'Maze', 'value' => 'maze'],
                ],
            ]),
            'activitySheetIntro' => new PlainText([
                'name' => 'Intro Text',
                'handle' => 'activitySheetIntro',
                'multiline' => true,
                'instructions' => 'Optional — shown above the download button, e.g. "Help Romeo find Buddy in the forest!"',
            ]),
            'activitySheetWords' => new Table([
                'name' => 'Word Search Words',
                'handle' => 'activitySheetWords',
                'instructions' => 'Only used when Activity Type is Word Search. One word per row — keep them short for younger readers.',
                'columns' => [
                    'col1' => ['heading' => 'Word', 'handle' => 'word', 'type' => 'singleline'],
                ],
            ]),
            'activitySheetDifficulty' => new Dropdown([
                'name' => 'Maze Difficulty',
                'handle' => 'activitySheetDifficulty',
                'instructions' => 'Only used when Activity Type is Maze — controls the maze size.',
                'options' => [
                    ['label' => 'Easy', 'value' => 'easy'],
                    ['label' => 'Medium', 'value' => 'medium'],
                    ['label' => 'Hard', 'value' => 'hard'],
                ],
            ]),
        ];

        foreach ($fieldsToCreate as $handle => $field) {
            if (!$fieldsService->saveField($field)) {
                throw new \Exception("Couldn't save the '{$handle}' field: " . implode(', ', $field->getErrorSummary(true)));
            }
        }

        // See migrations/m260717_014509_addBooksSection.php for why tabs
        // must be attached via setTabs() before setElements() is called.
        $contentTab = new FieldLayoutTab(['name' => 'Content']);
        $fieldLayout = new FieldLayout(['type' => Entry::class]);
        $fieldLayout->setTabs([$contentTab]);

        $contentTab->setElements([
            new EntryTitleField(['required' => true, 'label' => 'Sheet Title']),
            new CustomField($fieldsService->getFieldByHandle('activitySheetType')),
            new CustomField($fieldsService->getFieldByHandle('activitySheetIntro')),
            new CustomField($fieldsService->getFieldByHandle('activitySheetWords')),
            new CustomField($fieldsService->getFieldByHandle('activitySheetDifficulty')),
        ]);

        $entryType = new EntryType([
            'name' => 'Activity Sheet',
            'handle' => 'activitySheet',
            'hasTitleField' => true,
            'showSlugField' => false,
            'showStatusField' => false,
            'icon' => 'gamepad',
        ]);
        $entryType->setFieldLayout($fieldLayout);

        if (!$entriesService->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'Activity Sheet' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        $pageBuilderField = $fieldsService->getFieldByHandle('pageBuilder');
        if (!$pageBuilderField instanceof CkeditorField) {
            throw new \Exception("Couldn't find the 'pageBuilder' CKEditor field.");
        }

        $pageBuilderField->setEntryTypes([...$pageBuilderField->getEntryTypes(), $entryType]);

        if (!$fieldsService->saveField($pageBuilderField)) {
            throw new \Exception("Couldn't add 'Activity Sheet' to the pageBuilder field: " . implode(', ', $pageBuilderField->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();

        $pageBuilderField = $fieldsService->getFieldByHandle('pageBuilder');
        if ($pageBuilderField instanceof CkeditorField) {
            $remaining = array_filter(
                $pageBuilderField->getEntryTypes(),
                fn($entryType) => $entryType->handle !== 'activitySheet',
            );
            $pageBuilderField->setEntryTypes(array_values($remaining));
            $fieldsService->saveField($pageBuilderField);
        }

        if ($entryType = $entriesService->getEntryTypeByHandle('activitySheet')) {
            $entriesService->deleteEntryType($entryType);
        }

        foreach (self::NEW_FIELD_HANDLES as $handle) {
            if ($field = $fieldsService->getFieldByHandle($handle)) {
                $fieldsService->deleteField($field);
            }
        }

        return true;
    }
}
