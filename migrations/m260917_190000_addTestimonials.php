<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Assets;
use craft\fields\Entries;
use craft\fields\Matrix;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use verbb\buttonbox\fields\Buttons;

/**
 * Business blocks spec §5.1: a `testimonials` section (channel, no URLs) of `testimonialsCollection` entries, and a
 * Testimonials block that shows picked ones, or the latest six, as a grid, a carousel or a single quote.
 *
 * Adopt-if-present throughout: project config applies before content migrations, so any of it may already exist.
 */
class m260917_190000_addTestimonials extends Migration
{
    private const SECTION = 'testimonials';
    private const ENTRY_TYPE = 'testimonialsCollection';
    private const BLOCK = 'testimonials';

    /** Builder field => its group for the block, when it groups. */
    private const BUILDERS = ['pageBuilder' => 'Components', 'postBuilder' => 'General', 'containerBlocks' => null];

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $volume = Craft::$app->getVolumes()->getVolumeByHandle('images');
        $imagesSource = $volume ? 'volume:' . $volume->uid : null;

        $quote = $fields->getFieldByHandle('quote') ?? $this->save(new PlainText([
            'name' => 'Quote',
            'handle' => 'quote',
            'multiline' => true,
            'initialRows' => 4,
        ]));

        $authorRole = $fields->getFieldByHandle('authorRole') ?? $this->save(new PlainText([
            'name' => 'Role',
            'handle' => 'authorRole',
            'instructions' => 'Their role and company, e.g. “Owner, Tucson Dental”.',
        ]));

        $companyLogo = $fields->getFieldByHandle('companyLogo') ?? $this->save(new Assets([
            'name' => 'Company Logo',
            'handle' => 'companyLogo',
            'restrictFiles' => true,
            'allowedKinds' => ['image'],
            'sources' => $imagesSource ? [$imagesSource] : '*',
            'defaultUploadLocationSource' => $imagesSource,
            'defaultUploadLocationSubpath' => 'images',
            'maxRelations' => 1,
            'viewMode' => 'large',
            'selectionLabel' => 'Add a logo',
        ]));

        $rating = $fields->getFieldByHandle('rating') ?? $this->save(new Number([
            'name' => 'Rating',
            'handle' => 'rating',
            'instructions' => 'Out of 5. Leave empty to show no rating.',
            'min' => 1,
            'max' => 5,
            'decimals' => 0,
        ]));

        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle(self::ENTRY_TYPE)
            ?? $this->createCollectionType($quote, $authorRole, $companyLogo, $rating);
        $section = $this->section($entryType);

        $picker = $fields->getFieldByHandle('testimonialEntries') ?? $this->save(new Entries([
            'name' => 'Testimonials',
            'handle' => 'testimonialEntries',
            'instructions' => 'Pick up to 12, in the order to show them. Leave empty to show the latest six.',
            'sources' => ['section:' . $section->uid],
            'maxRelations' => 12,
            'selectionLabel' => 'Add a testimonial',
        ]));

        $layout = $fields->getFieldByHandle('layoutTestimonials') ?? $this->save(new Buttons([
            'name' => 'Layout Testimonials',
            'handle' => 'layoutTestimonials',
            'displayAsGraphic' => true,
            'options' => [
                ['label' => 'Grid', 'showLabel' => '1', 'value' => 'grid', 'imageUrl' => '/assets/cms/images/layout-testimonials-grid.svg', 'imageAlign' => 'top', 'default' => '1'],
                ['label' => 'Carousel', 'showLabel' => '1', 'value' => 'carousel', 'imageUrl' => '/assets/cms/images/layout-testimonials-carousel.svg', 'imageAlign' => 'top', 'default' => ''],
                ['label' => 'Single', 'showLabel' => '1', 'value' => 'single', 'imageUrl' => '/assets/cms/images/layout-testimonials-single.svg', 'imageAlign' => 'top', 'default' => ''],
            ],
        ]));

        $block = Craft::$app->getEntries()->getEntryTypeByHandle(self::BLOCK) ?? $this->createBlock($layout, $picker);

        foreach (self::BUILDERS as $builderHandle => $group) {
            $this->offer($builderHandle, $block, $group);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();

        if ($block = $entries->getEntryTypeByHandle(self::BLOCK)) {
            foreach (array_keys(self::BUILDERS) as $builderHandle) {
                $builder = $fields->getFieldByHandle($builderHandle);

                if ($builder instanceof Matrix) {
                    $builder->setEntryTypes(array_values(array_filter($builder->getEntryTypes(), static fn(EntryType $t): bool => $t->handle !== self::BLOCK)));
                    $fields->saveField($builder);
                }
            }

            $entries->deleteEntryType($block);
        }

        if ($section = $entries->getSectionByHandle(self::SECTION)) {
            $entries->deleteSection($section);
        }

        if ($entryType = $entries->getEntryTypeByHandle(self::ENTRY_TYPE)) {
            $entries->deleteEntryType($entryType);
        }

        // A null match must never reach a delete: see the stables CLAUDE.md on the migration that wiped a production site.
        $owned = [
            'testimonialEntries' => Entries::class,
            'layoutTestimonials' => Buttons::class,
            'quote' => PlainText::class,
            'authorRole' => PlainText::class,
            'companyLogo' => Assets::class,
            'rating' => Number::class,
        ];

        foreach ($owned as $handle => $class) {
            $field = $fields->getFieldByHandle($handle);

            if ($field instanceof $class) {
                $fields->deleteField($field);
            }
        }

        return true;
    }

    private function save(\craft\base\FieldInterface $field): \craft\base\FieldInterface
    {
        if (!Craft::$app->getFields()->saveField($field)) {
            throw new \Exception("Couldn't save the '{$field->handle}' field: " . implode(', ', $field->getErrorSummary(true)));
        }

        return $field;
    }

    private function createCollectionType(...$fields): EntryType
    {
        [$quote, $authorRole, $companyLogo, $rating] = $fields;
        $content = new FieldLayoutTab(['name' => 'Content']);
        $layout = new FieldLayout(['type' => Entry::class]);
        $image = Craft::$app->getFields()->getFieldByHandle('image');

        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs([$content]);
        $content->setElements(array_values(array_filter([
            new EntryTitleField(['label' => 'Name', 'required' => true]),
            new CustomField($quote, ['required' => true]),
            new CustomField($authorRole),
            $image ? new CustomField($image, ['label' => 'Photo']) : null,
            new CustomField($companyLogo),
            new CustomField($rating),
        ])));

        $entryType = new EntryType([
            'name' => 'Testimonial',
            'handle' => self::ENTRY_TYPE,
            'hasTitleField' => true,
            'titleTranslationMethod' => 'none',
            'showSlugField' => false,
            'showStatusField' => true,
            'icon' => 'quote-left',
        ]);
        $entryType->setFieldLayout($layout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'Testimonial' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        return $entryType;
    }

    private function section(EntryType $entryType): Section
    {
        $entries = Craft::$app->getEntries();
        $section = $entries->getSectionByHandle(self::SECTION);

        if ($section) {
            return $section;
        }

        $section = new Section([
            'name' => 'Testimonials',
            'handle' => self::SECTION,
            'type' => Section::TYPE_CHANNEL,
            'enableVersioning' => true,
            'propagationMethod' => PropagationMethod::All,
        ]);

        $siteSettings = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteSettings[$site->id] = new Section_SiteSettings([
                'siteId' => $site->id,
                'enabledByDefault' => true,
                'hasUrls' => false,
                'uriFormat' => null,
                'template' => null,
            ]);
        }

        $section->setSiteSettings($siteSettings);
        $section->setEntryTypes([$entryType]);

        if (!$entries->saveSection($section)) {
            throw new \Exception("Couldn't save the 'Testimonials' section: " . implode(', ', $section->getErrorSummary(true)));
        }

        return $section;
    }

    private function createBlock(\craft\base\FieldInterface $layoutField, \craft\base\FieldInterface $picker): EntryType
    {
        $content = new FieldLayoutTab(['name' => 'Content']);
        $settings = new FieldLayoutTab(['name' => 'Settings']);
        $layout = new FieldLayout(['type' => Entry::class]);

        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs([$content, $settings]);
        $content->setElements([new CustomField($layoutField, ['label' => 'Layout']), new CustomField($picker)]);

        // The background pair before the block heading, as on every section-level block (spec §3.6).
        $settings->setElements(array_map(
            static fn($field) => new CustomField($field),
            array_values(array_filter(array_map(
                static fn(string $handle) => Craft::$app->getFields()->getFieldByHandle($handle),
                ['backgroundRole', 'backgroundImage', 'blockHeading']
            )))
        ));

        $entryType = new EntryType([
            'name' => 'Testimonials',
            'handle' => self::BLOCK,
            'hasTitleField' => false,
            'showSlugField' => false,
            'showStatusField' => true,
            'icon' => 'quote-left',
        ]);
        $entryType->setFieldLayout($layout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'Testimonials' block: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        return $entryType;
    }

    private function offer(string $builderHandle, EntryType $block, ?string $group): void
    {
        $fields = Craft::$app->getFields();
        $builder = $fields->getFieldByHandle($builderHandle);

        if (!$builder instanceof Matrix) {
            return;
        }

        $existing = $builder->getEntryTypes();

        foreach ($existing as $type) {
            if ($type->handle === self::BLOCK) {
                return;
            }
        }

        $groups = array_values(array_unique(array_filter(array_map(static fn(EntryType $t): ?string => $t->group, $existing))));
        $usage = clone $block;
        $usage->original = $block;
        $usage->group = $groups === [] ? null : ($group !== null && in_array($group, $groups, true) ? $group : $groups[0]);

        $builder->setEntryTypes([...$existing, $usage]);

        if (!$fields->saveField($builder)) {
            throw new \Exception("Couldn't offer the Testimonials block in '{$builderHandle}': " . implode(', ', $builder->getErrorSummary(true)));
        }
    }
}
