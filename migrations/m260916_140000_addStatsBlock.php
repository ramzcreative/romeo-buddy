<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\HorizontalRule;
use craft\fields\Matrix;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

/**
 * A Stats block ("500+ clients · $2.5M raised"): three stat fields on the shared `item`, and a `stats` block that
 * holds items like Cards, so the two switch without losing content. See docs/theme-designer-blocks-spec.md §5.1.
 *
 * The stat fields go into the existing item layout in place (content is keyed by layout element uid). The block is
 * offered wherever Cards is. Adopt-if-present throughout.
 */
class m260916_140000_addStatsBlock extends Migration
{
    private const BLOCK = 'stats';
    private const ITEM = 'item';
    private const STAT_FIELDS = ['statPrefix', 'statNumber', 'statSuffix'];

    /** Builder field => its group for the block, when it groups. */
    private const GROUPS = ['pageBuilder' => 'Components', 'postBuilder' => 'General'];

    public function safeUp(): bool
    {
        $prefix = $this->ensure('statPrefix', fn() => new PlainText([
            'name' => 'Stat Prefix',
            'handle' => 'statPrefix',
            'instructions' => 'Before the number, e.g. “$”.',
            'charLimit' => 10,
        ]));
        $number = $this->ensure('statNumber', fn() => new Number([
            'name' => 'Stat Number',
            'handle' => 'statNumber',
            'instructions' => 'The number only, e.g. 2.5 for “$2.5M”.',
            'decimals' => 2,
        ]));
        $suffix = $this->ensure('statSuffix', fn() => new PlainText([
            'name' => 'Stat Suffix',
            'handle' => 'statSuffix',
            'instructions' => 'After the number, e.g. “+”, “%” or “M”.',
            'charLimit' => 10,
        ]));

        $this->addToItem([$prefix, $number, $suffix]);

        $block = Craft::$app->getEntries()->getEntryTypeByHandle(self::BLOCK) ?? $this->createBlock();

        foreach ($this->buildersOffering('cards') as $builder) {
            $this->offer($builder, $block, self::GROUPS[$builder->handle] ?? null);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();
        $block = $entries->getEntryTypeByHandle(self::BLOCK);

        if ($block !== null) {
            foreach ($this->buildersOffering(self::BLOCK) as $builder) {
                $builder->setEntryTypes(array_values(array_filter($builder->getEntryTypes(), static fn(EntryType $t): bool => $t->handle !== self::BLOCK)));
                $fields->saveField($builder);
            }

            $entries->deleteEntryType($block);
        }

        $item = $entries->getEntryTypeByHandle(self::ITEM);

        if ($item !== null) {
            $layout = $item->getFieldLayout();
            $tabs = $layout->getTabs();

            foreach ($tabs as $tab) {
                $kept = array_values(array_filter($tab->getElements(), fn(mixed $el): bool => !in_array($this->handleOf($el), self::STAT_FIELDS, true)));

                if (count($kept) !== count($tab->getElements())) {
                    $layout->setTabs($tabs);
                    $tab->setElements($kept);
                }
            }

            $item->setFieldLayout($layout);
            $entries->saveEntryType($item);
        }

        // A null match must never reach a delete: see the stables CLAUDE.md on the migration that wiped a production site.
        foreach (['statPrefix' => PlainText::class, 'statNumber' => Number::class, 'statSuffix' => PlainText::class] as $handle => $class) {
            $field = $fields->getFieldByHandle($handle);

            if ($field instanceof $class) {
                $fields->deleteField($field);
            }
        }

        return true;
    }

    private function ensure(string $handle, callable $make): \craft\base\FieldInterface
    {
        $fields = Craft::$app->getFields();

        if ($field = $fields->getFieldByHandle($handle)) {
            return $field;
        }

        $field = $make();

        if (!$fields->saveField($field)) {
            throw new \Exception("Couldn't save the '{$handle}' field: " . implode(', ', $field->getErrorSummary(true)));
        }

        return $field;
    }

    /**
     * Prefix, number and suffix on one row, right after the item's first divider (before Pre Heading), keeping every
     * existing element and its uid.
     *
     * @param \craft\base\FieldInterface[] $statFields
     */
    private function addToItem(array $statFields): void
    {
        $entries = Craft::$app->getEntries();
        $item = $entries->getEntryTypeByHandle(self::ITEM);

        if ($item === null) {
            throw new \Exception("Couldn't find the shared 'item' entry type.");
        }

        $layout = $item->getFieldLayout();

        foreach ($layout->getCustomFieldElements() as $element) {
            if (in_array($this->handleOf($element), self::STAT_FIELDS, true)) {
                return;
            }
        }

        $tabs = $layout->getTabs();
        $tab = $tabs[array_key_first($tabs)];
        $elements = array_values($tab->getElements());
        $insertAt = count($elements);

        foreach ($elements as $i => $element) {
            if ($element instanceof HorizontalRule) {
                $insertAt = $i + 1;
                break;
            }
        }

        $widths = [25, 50, 25];
        $new = array_map(static fn(\craft\base\FieldInterface $field, int $width) => new CustomField($field, ['width' => $width]), $statFields, $widths);

        $uidsBefore = array_map(fn(CustomField $el) => $el->uid, $layout->getCustomFieldElements());
        $hadTitleField = $item->hasTitleField;

        array_splice($elements, $insertAt, 0, $new);
        $layout->setTabs($tabs);
        $tab->setElements($elements);
        $item->setFieldLayout($layout);

        if (!$entries->saveEntryType($item)) {
            throw new \Exception("Couldn't add the stat fields to 'item': " . implode(', ', $item->getErrorSummary(true)));
        }

        $saved = $entries->getEntryTypeById($item->id);
        $uidsAfter = array_map(fn(CustomField $el) => $el->uid, $saved->getFieldLayout()->getCustomFieldElements());

        if (array_diff($uidsBefore, $uidsAfter) !== [] || $saved->hasTitleField !== $hadTitleField) {
            throw new \Exception("Adding the stat fields to 'item' changed its existing layout; stopped.");
        }
    }

    private function createBlock(): EntryType
    {
        $fields = Craft::$app->getFields();
        $items = $fields->getFieldByHandle('items') ?? throw new \Exception("Couldn't find the 'items' field.");

        $content = new FieldLayoutTab(['name' => 'Content']);
        $settings = new FieldLayoutTab(['name' => 'Settings']);
        $layout = new FieldLayout(['type' => Entry::class]);

        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs([$content, $settings]);
        $content->setElements([new CustomField($items, ['label' => 'Stats'])]);

        if ($blockHeading = $fields->getFieldByHandle('blockHeading')) {
            $settings->setElements([new CustomField($blockHeading)]);
        }

        $entryType = new EntryType([
            'name' => 'Stats',
            'handle' => self::BLOCK,
            'hasTitleField' => false,
            'showSlugField' => false,
            'showStatusField' => true,
            'icon' => 'chart-simple',
        ]);
        $entryType->setFieldLayout($layout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'Stats' block: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        return $entryType;
    }

    /** @return Matrix[] */
    private function buildersOffering(string $blockHandle): array
    {
        $builders = [];

        foreach (['pageBuilder', 'postBuilder', 'containerBlocks', 'columnBuilder'] as $handle) {
            $field = Craft::$app->getFields()->getFieldByHandle($handle);

            if ($field instanceof Matrix && array_filter($field->getEntryTypes(), static fn(EntryType $t): bool => $t->handle === $blockHandle)) {
                $builders[] = $field;
            }
        }

        return $builders;
    }

    private function offer(Matrix $builder, EntryType $block, ?string $group): void
    {
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

        if (!Craft::$app->getFields()->saveField($builder)) {
            throw new \Exception("Couldn't offer the Stats block in '{$builder->handle}': " . implode(', ', $builder->getErrorSummary(true)));
        }
    }

    private function handleOf(mixed $element): ?string
    {
        if (!$element instanceof CustomField) {
            return null;
        }

        try {
            return $element->getField()->handle;
        } catch (\Throwable) {
            return null;
        }
    }
}
