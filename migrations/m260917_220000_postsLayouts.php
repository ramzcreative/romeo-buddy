<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Dropdown;
use craft\fields\Number;
use verbb\buttonbox\fields\Buttons;

/**
 * Business blocks spec §5.3, the posts block:
 * - `postType` gains the boilerplate's publishing sections BESIDE the ones this site already offers (its own `books`
 *   stays, and the live Books listing keeps working). The CP only offers the ones that exist and have a listing
 *   template for the theme (stablestwigextensions Module, EVENT_DEFINE_OPTIONS).
 * - `layoutPosts` becomes grid (the section's whole listing, what every posts block renders today), list and slider.
 *   A saved `list` is the old option that rendered the full listing, so it becomes `grid`; `slider` stays.
 * - `postLimit` (default 15) caps list and slider.
 *
 * Values are remapped by saving each posts block (drafts too, not revisions). Adopt-if-present; layout saves keep
 * their existing element uids.
 */
class m260917_220000_postsLayouts extends Migration
{
    private const SECTIONS = ['blog' => 'Blog', 'events' => 'Events', 'jobs' => 'Jobs', 'projects' => 'Projects', 'articles' => 'Articles'];

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();

        $postType = $fields->getFieldByHandle('postType');

        if ($postType instanceof Dropdown) {
            // ADDED to what this site already offers, never replacing it: `books` is this site's own section and the
            // live Books listing block is set to it (stables docs/romeo-buddy-port-plan.md, D7).
            $existing = array_column($postType->options, 'value');
            $added = [];

            foreach (self::SECTIONS as $value => $label) {
                if (!in_array($value, $existing, true)) {
                    $postType->options[] = ['label' => $label, 'value' => $value, 'default' => false];
                    $added[] = $value;
                }
            }

            if ($added !== []) {
                $this->save($postType);
                echo '    > postType also offers ' . implode(', ', $added) . ", beside " . implode(', ', $existing) . ".\n";
            }
        }

        // Every `list` saved so far is the old option, which rendered the whole listing. Read before anything is
        // saved, and remapped whether or not project config already brought the new options: a migration runs once.
        $toGrid = $this->postsBlockIdsWithLayout('list');

        $layout = $fields->getFieldByHandle('layoutPosts');

        if ($layout instanceof Buttons && !in_array('grid', array_column($layout->options, 'value'), true)) {
            $layout->options = [
                ['label' => 'Grid', 'showLabel' => '1', 'value' => 'grid', 'imageUrl' => '/assets/cms/images/layout-posts-grid.svg', 'imageAlign' => 'top', 'default' => '1'],
                ['label' => 'List', 'showLabel' => '1', 'value' => 'list', 'imageUrl' => '/assets/cms/images/layout-posts-list.svg', 'imageAlign' => 'top', 'default' => ''],
                ['label' => 'Slider', 'showLabel' => '1', 'value' => 'slider', 'imageUrl' => '/assets/cms/images/layout-posts-slider.svg', 'imageAlign' => 'top', 'default' => ''],
            ];
            $layout->displayAsGraphic = true;
            $this->save($layout);
        }

        foreach ($toGrid as $id) {
            $block = Entry::find()->id($id)->status(null)->drafts(null)->provisionalDrafts(null)->site('*')->unique()->one();

            if ($block === null) {
                continue;
            }

            $block->setFieldValue('layoutPosts', 'grid');

            if (!Craft::$app->getElements()->saveElement($block, false, false, false)) {
                throw new \Exception("Couldn't set posts block {$id} to grid: " . implode(', ', $block->getErrorSummary(true)));
            }
        }

        echo '    > ' . count($toGrid) . " posts block(s) moved from list to grid.\n";

        $limit = $fields->getFieldByHandle('postLimit') ?? $this->save(new Number([
            'name' => 'Limit',
            'handle' => 'postLimit',
            'instructions' => 'How many to show, newest first (upcoming first for events).',
            'min' => 1,
            'max' => 50,
            'decimals' => 0,
            'defaultValue' => 15,
        ]));

        $this->attachAfter('posts', $limit, 'layoutPosts');

        return true;
    }

    public function safeDown(): bool
    {
        echo "    > m260917_220000_postsLayouts changed saved values; restore a backup to undo it.\n";

        return false;
    }

    /**
     * @return int[]
     */
    private function postsBlockIdsWithLayout(string $value): array
    {
        if (Craft::$app->getEntries()->getEntryTypeByHandle('posts') === null) {
            return [];
        }

        $ids = [];

        foreach (Entry::find()->type('posts')->status(null)->drafts(null)->provisionalDrafts(null)->site('*')->unique()->all() as $block) {
            if ((string)$block->getFieldValue('layoutPosts') === $value) {
                $ids[] = $block->id;
            }
        }

        return $ids;
    }

    private function save(\craft\base\FieldInterface $field): \craft\base\FieldInterface
    {
        if (!Craft::$app->getFields()->saveField($field)) {
            throw new \Exception("Couldn't save the '{$field->handle}' field: " . implode(', ', $field->getErrorSummary(true)));
        }

        return $field;
    }

    private function attachAfter(string $typeHandle, \craft\base\FieldInterface $field, string $after): void
    {
        $entries = Craft::$app->getEntries();
        $type = $entries->getEntryTypeByHandle($typeHandle);

        if ($type === null || $type->getFieldLayout()->getFieldByHandle($field->handle)) {
            return;
        }

        $layout = $type->getFieldLayout();
        $tabs = $layout->getTabs();
        $target = $tabs[array_key_first($tabs)];
        $insertAt = count($target->getElements());

        foreach ($tabs as $tab) {
            foreach (array_values($tab->getElements()) as $i => $element) {
                if ($element instanceof CustomField && $element->getField()->handle === $after) {
                    $target = $tab;
                    $insertAt = $i + 1;
                    break 2;
                }
            }
        }

        $elements = array_values($target->getElements());
        $uidsBefore = array_map(static fn(CustomField $el) => $el->uid, $layout->getCustomFieldElements());
        array_splice($elements, $insertAt, 0, [new CustomField($field)]);

        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs($tabs);
        $target->setElements($elements);
        $type->setFieldLayout($layout);

        if (!$entries->saveEntryType($type)) {
            throw new \Exception("Couldn't add {$field->handle} to '{$typeHandle}': " . implode(', ', $type->getErrorSummary(true)));
        }

        $uidsAfter = array_map(static fn(CustomField $el) => $el->uid, $entries->getEntryTypeById($type->id)->getFieldLayout()->getCustomFieldElements());

        if (array_diff($uidsBefore, $uidsAfter) !== []) {
            throw new \Exception("Adding {$field->handle} to '{$typeHandle}' changed its existing layout; stopped.");
        }

        echo "    > Added {$field->handle} to '{$typeHandle}'.\n";
    }
}
