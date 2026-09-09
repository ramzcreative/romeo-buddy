<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

/**
 * Adds the 'Product' option to the slider layout picker.
 *
 * Copy on the left, a product cut-out on the right, and pagination that fades
 * one slide into the next — the fourth slider layout, alongside Slider,
 * Carousel and Hero. It reuses the shared item fields (preheading, heading,
 * intro, buttons, image), so there is nothing to add to the item type.
 *
 * Appended rather than inserted: the picker reads in option order, and an
 * existing site's editors know where the first three sit.
 */
class m260908_190000_addProductSliderLayout extends Migration
{
    private const FIELD_HANDLE = 'layoutSliders';
    private const VALUE = 'product';

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $field = $fieldsService->getFieldByHandle(self::FIELD_HANDLE);

        if (!$field) {
            throw new \Exception("Couldn't find the '" . self::FIELD_HANDLE . "' field — expected it to already exist.");
        }

        foreach ($field->options as $option) {
            if (($option['value'] ?? null) === self::VALUE) {
                // Already there — project config applies before content
                // migrations, so on a deploy this is the normal path.
                return true;
            }
        }

        $field->options = [
            ...$field->options,
            [
                'label' => 'Product',
                'showLabel' => '1',
                'value' => self::VALUE,
                'imageUrl' => '/assets/cms/images/layout-slider-product.svg',
                'imageAlign' => 'top',
                'default' => '',
            ],
        ];

        if (!$fieldsService->saveField($field)) {
            throw new \Exception("Couldn't save the '" . self::FIELD_HANDLE . "' field: " . implode(', ', $field->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $field = $fieldsService->getFieldByHandle(self::FIELD_HANDLE);

        if ($field) {
            $field->options = array_values(array_filter(
                $field->options,
                fn($option) => ($option['value'] ?? null) !== self::VALUE,
            ));
            $fieldsService->saveField($field);
        }

        return true;
    }
}
