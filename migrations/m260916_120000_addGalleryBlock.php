<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Assets;
use craft\fields\Matrix;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use verbb\buttonbox\fields\Buttons;

/**
 * A Gallery block: images from the Images volume in a grid or masonry layout, opening in a lightbox. See
 * docs/business-content-spec.md §5b.
 *
 * Adopt-if-present throughout: project config applies before content migrations, so any of it may already exist.
 */
class m260916_120000_addGalleryBlock extends Migration
{
    private const IMAGES_FIELD = 'galleryImages';
    private const LAYOUT_FIELD = 'layoutGallery';
    private const BLOCK = 'gallery';

    /** Builder field => its group for the block, when it groups. */
    private const BUILDERS = ['pageBuilder' => 'Media', 'postBuilder' => 'General', 'containerBlocks' => null, 'columnBuilder' => null];

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $volume = Craft::$app->getVolumes()->getVolumeByHandle('images');
        $source = $volume ? 'volume:' . $volume->uid : null;

        $images = $fields->getFieldByHandle(self::IMAGES_FIELD) ?? $this->save(new Assets([
            'name' => 'Gallery Images',
            'handle' => self::IMAGES_FIELD,
            'instructions' => 'Drag to reorder. Alt text is set once on each image, in the asset itself.',
            'restrictFiles' => true,
            'allowedKinds' => ['image'],
            'sources' => $source ? [$source] : '*',
            'defaultUploadLocationSource' => $source,
            'defaultUploadLocationSubpath' => 'images',
            'viewMode' => 'large',
            'selectionLabel' => 'Add images',
        ]));

        $layout = $fields->getFieldByHandle(self::LAYOUT_FIELD) ?? $this->save(new Buttons([
            'name' => 'Layout Gallery',
            'handle' => self::LAYOUT_FIELD,
            'displayAsGraphic' => true,
            'options' => [
                ['label' => 'Grid', 'showLabel' => '1', 'value' => 'grid', 'imageUrl' => '/assets/cms/images/layout-gallery-grid.svg', 'imageAlign' => 'top', 'default' => '1'],
                ['label' => 'Masonry', 'showLabel' => '1', 'value' => 'masonry', 'imageUrl' => '/assets/cms/images/layout-gallery-masonry.svg', 'imageAlign' => 'top', 'default' => ''],
            ],
        ]));

        $block = Craft::$app->getEntries()->getEntryTypeByHandle(self::BLOCK) ?? $this->createBlock($layout, $images);

        foreach (self::BUILDERS as $builderHandle => $group) {
            $this->offer($builderHandle, $block, $group);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();
        $block = $entries->getEntryTypeByHandle(self::BLOCK);

        if ($block !== null) {
            foreach (array_keys(self::BUILDERS) as $builderHandle) {
                $builder = $fields->getFieldByHandle($builderHandle);

                if ($builder instanceof Matrix) {
                    $builder->setEntryTypes(array_values(array_filter($builder->getEntryTypes(), static fn(EntryType $t): bool => $t->handle !== self::BLOCK)));
                    $fields->saveField($builder);
                }
            }

            $entries->deleteEntryType($block);
        }

        // A null match must never reach a delete: see the stables CLAUDE.md on the migration that wiped a production site.
        foreach ([self::IMAGES_FIELD => Assets::class, self::LAYOUT_FIELD => Buttons::class] as $handle => $class) {
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

    private function createBlock(\craft\base\FieldInterface $layoutField, \craft\base\FieldInterface $images): EntryType
    {
        $content = new FieldLayoutTab(['name' => 'Content']);
        $settings = new FieldLayoutTab(['name' => 'Settings']);
        $layout = new FieldLayout(['type' => Entry::class]);

        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs([$content, $settings]);
        $content->setElements([new CustomField($layoutField, ['label' => 'Layout']), new CustomField($images, ['label' => 'Images'])]);

        if ($blockHeading = Craft::$app->getFields()->getFieldByHandle('blockHeading')) {
            $settings->setElements([new CustomField($blockHeading)]);
        }

        $entryType = new EntryType([
            'name' => 'Gallery',
            'handle' => self::BLOCK,
            'hasTitleField' => false,
            'showSlugField' => false,
            'showStatusField' => true,
            'icon' => 'images',
        ]);
        $entryType->setFieldLayout($layout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'Gallery' block: " . implode(', ', $entryType->getErrorSummary(true)));
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
            throw new \Exception("Couldn't offer the Gallery block in '{$builderHandle}': " . implode(', ', $builder->getErrorSummary(true)));
        }
    }
}
