<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fields\PlainText;
use craft\fieldlayoutelements\CustomField;

/**
 * Adds a 'genre' field to the 'book' entry type (see
 * m260717_014509_addBooksSection) — schema.org's Book.genre property accepts
 * either a single value or an array, so
 * modules/seo/services/StructuredDataBuilder::buildBook() splits this the
 * same comma-separated way as SeoSettings' awards/knowsAbout (see
 * SeoResolver::splitCommaList()) rather than standing up a dedicated tag
 * group for what's usually one or two values per book.
 */
class m260718_164932_addBookGenreField extends Migration
{
    private const FIELD_HANDLE = 'genre';

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();

        $entryType = $entriesService->getEntryTypeByHandle('book');
        if (!$entryType) {
            throw new \Exception("Couldn't find the 'book' entry type — run m260717_014509_addBooksSection first.");
        }

        $genreField = new PlainText([
            'name' => 'Genre',
            'handle' => self::FIELD_HANDLE,
            'instructions' => 'Comma-separated if the book spans more than one, e.g. "Adventure, Fantasy". Feeds the schema.org Book genre property (accepts one value or many).',
            'placeholder' => 'Adventure, Fantasy',
        ]);

        if (!$fieldsService->saveField($genreField)) {
            throw new \Exception("Couldn't save the 'genre' field: " . implode(', ', $genreField->getErrorSummary(true)));
        }

        $fieldLayout = $entryType->getFieldLayout();
        $contentTab = null;
        foreach ($fieldLayout->getTabs() as $tab) {
            if ($tab->name === 'Content') {
                $contentTab = $tab;
                break;
            }
        }
        if (!$contentTab) {
            throw new \Exception("Couldn't find the 'Book' entry type's Content tab.");
        }

        $elements = $contentTab->getElements();
        $elements[] = new CustomField($genreField);
        $contentTab->setElements($elements);

        if (!$entriesService->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'Book' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        // Removing the field from the entry type's field layout is handled
        // by Craft itself when the field is deleted (saveField()/
        // deleteField() prune references from every field layout).
        $fieldsService = Craft::$app->getFields();

        if ($field = $fieldsService->getFieldByHandle(self::FIELD_HANDLE)) {
            $fieldsService->deleteField($field);
        }

        return true;
    }
}
