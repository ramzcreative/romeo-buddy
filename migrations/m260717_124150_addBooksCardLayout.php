<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fields\Lightswitch;
use craft\fieldlayoutelements\CustomField;

/**
 * Adds a 'Books' option to the Cards block's layout picker (see
 * themes/_base/templates/_blocks/layouts/cards/books.twig) and a
 * 'Coming Soon' toggle on the shared `item` entry type (used by both the
 * Cards and Slider blocks — see config/project/fields/items--*.yaml) so an
 * editor can flag a not-yet-available format (e.g. a hardcover pre-order)
 * without a separate item/button just for that state. Slider items get the
 * field too since it's the same entry type, but nothing reads it there —
 * harmless, same tradeoff the shared `heading`/`image`/etc. fields already
 * make.
 */
class m260717_124150_addBooksCardLayout extends Migration
{
    private const NEW_FIELD_HANDLE = 'comingSoon';

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();

        $comingSoonField = new Lightswitch([
            'name' => 'Coming Soon',
            'handle' => self::NEW_FIELD_HANDLE,
            'instructions' => 'Styles this item as not-yet-available (muted, dashed border) on the Books card layout — its button (e.g. "Notify Me") still renders, just outlined instead of solid.',
        ]);

        if (!$fieldsService->saveField($comingSoonField)) {
            throw new \Exception("Couldn't save the 'comingSoon' field: " . implode(', ', $comingSoonField->getErrorSummary(true)));
        }

        $itemEntryType = $entriesService->getEntryTypeByHandle('item');
        if (!$itemEntryType) {
            throw new \Exception("Couldn't find the 'item' entry type.");
        }

        $fieldLayout = $itemEntryType->getFieldLayout();
        $tabs = $fieldLayout->getTabs();
        $contentTab = $tabs[0];
        $contentTab->setElements([...$contentTab->getElements(), new CustomField($comingSoonField)]);
        $fieldLayout->setTabs($tabs);
        $itemEntryType->setFieldLayout($fieldLayout);

        if (!$entriesService->saveEntryType($itemEntryType)) {
            throw new \Exception("Couldn't add 'comingSoon' to the 'item' entry type: " . implode(', ', $itemEntryType->getErrorSummary(true)));
        }

        $layoutCardsField = $fieldsService->getFieldByHandle('layoutCards');
        if (!$layoutCardsField) {
            throw new \Exception("Couldn't find the 'layoutCards' field.");
        }

        // No CP preview thumbnail yet (imageUrl left blank) — Button Box
        // falls back to label-only for this option until one's designed;
        // see the other options' imageUrl for the /assets/cms/images/ path
        // convention to follow when one exists.
        $layoutCardsField->options = [
            ...$layoutCardsField->options,
            [
                'label' => 'Books',
                'showLabel' => '1',
                'value' => 'books',
                'imageUrl' => '',
                'imageAlign' => 'top',
                'default' => '',
            ],
        ];

        if (!$fieldsService->saveField($layoutCardsField)) {
            throw new \Exception("Couldn't add the 'Books' option to 'layoutCards': " . implode(', ', $layoutCardsField->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();

        $layoutCardsField = $fieldsService->getFieldByHandle('layoutCards');
        if ($layoutCardsField) {
            $layoutCardsField->options = array_values(array_filter(
                $layoutCardsField->options,
                fn(array $option) => $option['value'] !== 'books',
            ));
            $fieldsService->saveField($layoutCardsField);
        }

        $itemEntryType = $entriesService->getEntryTypeByHandle('item');
        if ($itemEntryType) {
            $fieldLayout = $itemEntryType->getFieldLayout();
            $tabs = $fieldLayout->getTabs();
            $contentTab = $tabs[0];
            $contentTab->setElements(array_values(array_filter(
                $contentTab->getElements(),
                fn($element) => !($element instanceof CustomField) || $element->getField()?->handle !== self::NEW_FIELD_HANDLE,
            )));
            $fieldLayout->setTabs($tabs);
            $itemEntryType->setFieldLayout($fieldLayout);
            $entriesService->saveEntryType($itemEntryType);
        }

        if ($field = $fieldsService->getFieldByHandle(self::NEW_FIELD_HANDLE)) {
            $fieldsService->deleteField($field);
        }

        return true;
    }
}
