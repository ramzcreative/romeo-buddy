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
 * Business blocks spec §4.3 and §4.4: a Logo Strip block (`logos`) in a row, grid or ticker, and `ticker` as a third
 * gallery layout. A logo's name is its asset's alt text, which the stablestwigextensions module requires on save.
 *
 * Adopt-if-present throughout: project config applies before content migrations, so any of it may already exist.
 */
class m260917_170000_addLogosBlock extends Migration
{
    private const IMAGES_FIELD = 'logos';
    private const LAYOUT_FIELD = 'layoutLogos';
    private const BLOCK = 'logos';

    /** Builder field => its group for the block, when it groups. */
    private const BUILDERS = ['pageBuilder' => 'Media', 'postBuilder' => 'General', 'containerBlocks' => null];

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $volume = Craft::$app->getVolumes()->getVolumeByHandle('images');
        $source = $volume ? 'volume:' . $volume->uid : null;

        $images = $fields->getFieldByHandle(self::IMAGES_FIELD) ?? $this->save(new Assets([
            'name' => 'Logos',
            'handle' => self::IMAGES_FIELD,
            'instructions' => 'Drag to reorder. Each logo’s alt text is the company name, set once on the asset itself; a logo without one won’t save.',
            'restrictFiles' => true,
            'allowedKinds' => ['image'],
            'sources' => $source ? [$source] : '*',
            'defaultUploadLocationSource' => $source,
            'defaultUploadLocationSubpath' => 'images',
            'viewMode' => 'large',
            'selectionLabel' => 'Add logos',
        ]));

        $layout = $fields->getFieldByHandle(self::LAYOUT_FIELD) ?? $this->save(new Buttons([
            'name' => 'Layout Logos',
            'handle' => self::LAYOUT_FIELD,
            'displayAsGraphic' => true,
            'options' => [
                ['label' => 'Row', 'showLabel' => '1', 'value' => 'row', 'imageUrl' => '/assets/cms/images/layout-logos-row.svg', 'imageAlign' => 'top', 'default' => '1'],
                ['label' => 'Grid', 'showLabel' => '1', 'value' => 'grid', 'imageUrl' => '/assets/cms/images/layout-logos-grid.svg', 'imageAlign' => 'top', 'default' => ''],
                ['label' => 'Ticker', 'showLabel' => '1', 'value' => 'ticker', 'imageUrl' => '/assets/cms/images/layout-logos-ticker.svg', 'imageAlign' => 'top', 'default' => ''],
            ],
        ]));

        $block = Craft::$app->getEntries()->getEntryTypeByHandle(self::BLOCK) ?? $this->createBlock($layout, $images);

        foreach (self::BUILDERS as $builderHandle => $group) {
            $this->offer($builderHandle, $block, $group);
        }

        $this->addGalleryTicker();

        return true;
    }

    private function addGalleryTicker(): void
    {
        $fields = Craft::$app->getFields();
        $field = $fields->getFieldByHandle('layoutGallery');

        if (!$field instanceof Buttons) {
            return;
        }

        if (in_array('ticker', array_column($field->options, 'value'), true)) {
            return;
        }

        $field->options = [...$field->options, ['label' => 'Ticker', 'showLabel' => '1', 'value' => 'ticker', 'imageUrl' => '/assets/cms/images/layout-gallery-ticker.svg', 'imageAlign' => 'top', 'default' => '']];
        $this->save($field);
    }

    private function removeGalleryTicker(): void
    {
        $fields = Craft::$app->getFields();
        $field = $fields->getFieldByHandle('layoutGallery');

        if ($field instanceof Buttons) {
            $field->options = array_values(array_filter($field->options, static fn(array $option): bool => $option['value'] !== 'ticker'));
            $this->save($field);
        }
    }

    public function safeDown(): bool
    {
        $this->removeGalleryTicker();

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
        $content->setElements([new CustomField($layoutField, ['label' => 'Layout']), new CustomField($images)]);

        // The background pair before the block heading, as on every section-level block (spec §3.6).
        $settings->setElements(array_map(
            static fn($field) => new CustomField($field),
            array_values(array_filter(array_map(
                static fn(string $handle) => Craft::$app->getFields()->getFieldByHandle($handle),
                ['backgroundRole', 'backgroundImage', 'blockHeading']
            )))
        ));

        $entryType = new EntryType([
            'name' => 'Logo Strip',
            'handle' => self::BLOCK,
            'hasTitleField' => false,
            'showSlugField' => false,
            'showStatusField' => true,
            'icon' => 'building',
        ]);
        $entryType->setFieldLayout($layout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'Logo Strip' block: " . implode(', ', $entryType->getErrorSummary(true)));
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
            throw new \Exception("Couldn't offer the Logo Strip block in '{$builderHandle}': " . implode(', ', $builder->getErrorSummary(true)));
        }
    }
}
