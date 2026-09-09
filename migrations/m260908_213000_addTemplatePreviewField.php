<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\conditions\entries\EntryCondition;
use craft\elements\conditions\entries\SectionConditionRule;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Assets;

/**
 * A thumbnail for page templates, shown on the picker card in place of the
 * block summary (craft-modules' page-templates module looks for a
 * `templatePreview` field and ignores its absence).
 *
 * Templates share the page entry types, so the field goes on those layouts —
 * but with an element condition limiting it to the Page Templates section,
 * so an ordinary page never sees it. Volume and restrictions mirror the
 * existing `image` field.
 */
class m260908_213000_addTemplatePreviewField extends Migration
{
    private const FIELD_HANDLE = 'templatePreview';
    private const SECTION_HANDLE = 'pageTemplates';
    private const ENTRY_TYPE_HANDLES = ['page', 'landingPage'];

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();

        $section = $entriesService->getSectionByHandle(self::SECTION_HANDLE);
        if (!$section) {
            throw new \Exception("Couldn't find the 'pageTemplates' section — run m260908_200000_addPageTemplatesSection first.");
        }

        $field = $fieldsService->getFieldByHandle(self::FIELD_HANDLE);

        if (!$field) {
            $image = $fieldsService->getFieldByHandle('image');
            $volumeSource = $image instanceof Assets ? $image->restrictedLocationSource : null;

            $field = new Assets([
                'name' => 'Template Thumbnail',
                'handle' => self::FIELD_HANDLE,
                'instructions' => 'Shown on the "Start from a template" card. Landscape works best.',
                'allowedKinds' => ['image'],
                'restrictFiles' => true,
                'maxRelations' => 1,
                'viewMode' => 'large',
                'selectionLabel' => 'Add Thumbnail',
                'restrictLocation' => (bool)$volumeSource,
                'restrictedLocationSource' => $volumeSource,
                'restrictedDefaultUploadSubpath' => $volumeSource ? 'templates' : null,
                'allowSubfolders' => true,
                'showSiteMenu' => true,
            ]);

            if (!$fieldsService->saveField($field)) {
                throw new \Exception("Couldn't save the 'templatePreview' field: " . implode(', ', $field->getErrorSummary(true)));
            }
        }

        foreach (self::ENTRY_TYPE_HANDLES as $handle) {
            $entryType = $entriesService->getEntryTypeByHandle($handle);
            if (!$entryType) {
                continue;
            }

            $fieldLayout = $entryType->getFieldLayout();
            $tabs = $fieldLayout->getTabs();

            $present = false;
            foreach ($tabs as $tab) {
                foreach ($tab->getElements() as $element) {
                    if ($element instanceof CustomField && $element->getField()?->handle === self::FIELD_HANDLE) {
                        $present = true;
                    }
                }
            }
            if ($present || !$tabs) {
                continue;
            }

            $layoutElement = new CustomField($field);
            $layoutElement->setElementCondition([
                'class' => EntryCondition::class,
                'conditionRules' => [
                    [
                        'class' => SectionConditionRule::class,
                        'values' => [$section->uid],
                    ],
                ],
            ]);

            // Top of the first tab: on a template it's the one field that is
            // about the template rather than the page it will seed.
            $firstTab = reset($tabs);
            $elements = array_values($firstTab->getElements());
            $insertAt = 0;
            foreach ($elements as $i => $element) {
                if ($element instanceof \craft\fieldlayoutelements\entries\EntryTitleField) {
                    $insertAt = $i + 1;
                    break;
                }
            }
            array_splice($elements, $insertAt, 0, [$layoutElement]);

            $firstTab->setElements($elements);
            $fieldLayout->setTabs($tabs);
            $entryType->setFieldLayout($fieldLayout);

            if (!$entriesService->saveEntryType($entryType)) {
                throw new \Exception("Couldn't save the '$handle' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($field = Craft::$app->getFields()->getFieldByHandle(self::FIELD_HANDLE)) {
            Craft::$app->getFields()->deleteField($field);
        }

        return true;
    }
}
