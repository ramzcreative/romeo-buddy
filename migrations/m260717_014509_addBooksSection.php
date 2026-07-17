<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\enums\PropagationMethod;
use craft\elements\Entry;
use craft\fields\Dropdown;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\Table;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;

/**
 * Adds a 'Books' section (channel, one entry type per book) with the field
 * shape modules/seo/services/StructuredDataBuilder::buildBook() already
 * knows how to read — see that method's doc comment for the exact
 * field-handle contract. Reuses the sitewide 'image'/'excerpt'/'seo' fields
 * (same ones Blog Post/Page already use) rather than creating book-specific
 * duplicates, so title/description/image resolution and the SEO field
 * override all work the same way they already do for every other entry
 * type — no config/seo.php changes needed, since config/seo.php's sitewide
 * fieldDefaults (imageFields: ['image'], descriptionFields includes
 * 'excerpt') already covers a section with no sectionDefaults override.
 */
class m260717_014509_addBooksSection extends Migration
{
    private const NEW_FIELD_HANDLES = [
        'isbn', 'bookAuthor', 'illustrator', 'typicalAgeRange', 'bookFormat',
        'numberOfPages', 'bookSeriesName', 'bookSeriesPosition', 'retailerLinks',
    ];

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();

        $fieldsToCreate = [
            'isbn' => new PlainText([
                'name' => 'ISBN',
                'handle' => 'isbn',
                'instructions' => 'e.g. 978-3-16-148410-0. Required for this book to appear in structured data as a schema.org Book (see StructuredDataBuilder::buildBook()).',
            ]),
            'bookAuthor' => new PlainText([
                'name' => 'Author',
                'handle' => 'bookAuthor',
                'instructions' => 'Falls back to the entry\'s Craft author if left blank.',
            ]),
            'illustrator' => new PlainText([
                'name' => 'Illustrator',
                'handle' => 'illustrator',
            ]),
            'typicalAgeRange' => new PlainText([
                'name' => 'Age Range',
                'handle' => 'typicalAgeRange',
                'instructions' => 'e.g. "3-6". Powers age-targeted structured data — the single most valuable field for children\'s book search.',
                'placeholder' => '3-6',
            ]),
            'bookFormat' => new Dropdown([
                'name' => 'Format',
                'handle' => 'bookFormat',
                // Labels are editor-friendly; values are schema.org's own
                // BookFormatType enum names verbatim — buildBook() builds
                // the structured data value as 'https://schema.org/' . value,
                // so these must match schema.org exactly, not be relabeled.
                'options' => [
                    ['label' => 'Hardcover', 'value' => 'Hardcover'],
                    ['label' => 'Paperback', 'value' => 'Paperback'],
                    ['label' => 'EBook', 'value' => 'EBook'],
                    ['label' => 'Audiobook', 'value' => 'AudiobookFormat'],
                    ['label' => 'Graphic Novel', 'value' => 'GraphicNovel'],
                ],
            ]),
            'numberOfPages' => new Number([
                'name' => 'Number of Pages',
                'handle' => 'numberOfPages',
                'min' => 0,
                'decimals' => 0,
            ]),
            'bookSeriesName' => new PlainText([
                'name' => 'Series Name',
                'handle' => 'bookSeriesName',
                'instructions' => 'Leave blank if this book isn\'t part of a series.',
            ]),
            'bookSeriesPosition' => new Number([
                'name' => 'Series Position',
                'handle' => 'bookSeriesPosition',
                'min' => 1,
                'decimals' => 0,
                'instructions' => 'e.g. 1 for the first book in the series.',
            ]),
            'retailerLinks' => new Table([
                'name' => 'Where to Buy',
                'handle' => 'retailerLinks',
                'instructions' => 'One row per retailer (Amazon, Bookshop.org, ...) — becomes one Offer per row in structured data rather than a single price/availability pair.',
                'columns' => [
                    'col1' => ['heading' => 'Retailer', 'handle' => 'retailer', 'type' => 'singleline'],
                    'col2' => ['heading' => 'URL', 'handle' => 'url', 'type' => 'url'],
                ],
            ]),
        ];

        foreach ($fieldsToCreate as $handle => $field) {
            if (!$fieldsService->saveField($field)) {
                throw new \Exception("Couldn't save the '{$handle}' field: " . implode(', ', $field->getErrorSummary(true)));
            }
        }

        $imageField = $fieldsService->getFieldByHandle('image');
        $excerptField = $fieldsService->getFieldByHandle('excerpt');
        $seoField = $fieldsService->getFieldByHandle('seo');

        // setElements() reads the tab's own $layout back-reference, so the
        // tabs have to be attached to the FieldLayout via setTabs() first —
        // building elements before that (as an earlier version of this
        // migration did) throws "Field layout tab is missing its field
        // layout."
        $contentTab = new FieldLayoutTab(['name' => 'Content']);
        $tabs = [$contentTab];

        if ($seoField) {
            $seoTab = new FieldLayoutTab(['name' => 'SEO']);
            $tabs[] = $seoTab;
        }

        $fieldLayout = new FieldLayout(['type' => Entry::class]);
        $fieldLayout->setTabs($tabs);

        $contentElements = [new EntryTitleField(['required' => true])];

        if ($imageField) {
            $contentElements[] = new CustomField($imageField);
        }
        if ($excerptField) {
            $contentElements[] = new CustomField($excerptField, ['label' => 'Short Description']);
        }
        foreach (self::NEW_FIELD_HANDLES as $handle) {
            $contentElements[] = new CustomField($fieldsService->getFieldByHandle($handle));
        }
        $contentTab->setElements($contentElements);

        if ($seoField) {
            $seoTab->setElements([new CustomField($seoField)]);
        }

        $entryType = new EntryType([
            'name' => 'Book',
            'handle' => 'book',
            'hasTitleField' => true,
            'showSlugField' => true,
            'showStatusField' => true,
            'icon' => 'book',
        ]);
        $entryType->setFieldLayout($fieldLayout);

        if (!$entriesService->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'Book' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        $section = new Section([
            'name' => 'Books',
            'handle' => 'books',
            'type' => Section::TYPE_CHANNEL,
            'enableVersioning' => true,
            'propagationMethod' => PropagationMethod::All,
        ]);
        $section->setEntryTypes([$entryType]);

        $siteSettings = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteSettings[$site->id] = new Section_SiteSettings([
                'siteId' => $site->id,
                'enabledByDefault' => true,
                'hasUrls' => true,
                'template' => '_router.twig',
                'uriFormat' => 'books/{slug}',
            ]);
        }
        $section->setSiteSettings($siteSettings);

        if (!$entriesService->saveSection($section)) {
            throw new \Exception("Couldn't save the 'Books' section: " . implode(', ', $section->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        $entriesService = Craft::$app->getEntries();
        $fieldsService = Craft::$app->getFields();

        // Deletes the section's entries along with it — expected for a down
        // migration, not something to run against real book content.
        if ($section = $entriesService->getSectionByHandle('books')) {
            $entriesService->deleteSection($section);
        }

        if ($entryType = $entriesService->getEntryTypeByHandle('book')) {
            $entriesService->deleteEntryType($entryType);
        }

        // Only the fields this migration created — 'image'/'excerpt'/'seo'
        // are shared with Blog Post/Page and stay untouched.
        foreach (self::NEW_FIELD_HANDLES as $handle) {
            if ($field = $fieldsService->getFieldByHandle($handle)) {
                $fieldsService->deleteField($field);
            }
        }

        return true;
    }
}
