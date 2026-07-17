<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

/**
 * Follow-up to m260717_124150_addBooksCardLayout — that migration left the
 * 'Books' layoutCards option with a blank imageUrl (no thumbnail existed
 * yet). Points it at the new CP preview icon, same style/size as the other
 * options (see web/assets/cms/images/{grid,list,grid-2}.png).
 */
class m260717_125324_addBooksLayoutThumbnail extends Migration
{
    private const IMAGE_URL = '/assets/cms/images/cards-books.png';

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $layoutCardsField = $fieldsService->getFieldByHandle('layoutCards');
        if (!$layoutCardsField) {
            throw new \Exception("Couldn't find the 'layoutCards' field.");
        }

        $layoutCardsField->options = array_map(
            fn(array $option) => $option['value'] === 'books'
                ? [...$option, 'imageUrl' => self::IMAGE_URL]
                : $option,
            $layoutCardsField->options,
        );

        if (!$fieldsService->saveField($layoutCardsField)) {
            throw new \Exception("Couldn't update the 'Books' option's imageUrl: " . implode(', ', $layoutCardsField->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $layoutCardsField = $fieldsService->getFieldByHandle('layoutCards');
        if ($layoutCardsField) {
            $layoutCardsField->options = array_map(
                fn(array $option) => $option['value'] === 'books'
                    ? [...$option, 'imageUrl' => '']
                    : $option,
                $layoutCardsField->options,
            );
            $fieldsService->saveField($layoutCardsField);
        }

        return true;
    }
}
