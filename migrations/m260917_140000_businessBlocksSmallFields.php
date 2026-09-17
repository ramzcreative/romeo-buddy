<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;
use verbb\buttonbox\fields\Buttons;

/**
 * Business blocks spec §3.1, §3.4 and §3.5 — the content-model half of three small changes whose templates land in the
 * same commit:
 *
 * - `hero` gets `preheading`, before `heading`. hero/standard.twig already renders it; the entry type never had it.
 * - `accordion` gets `blockHeading` and `buttons`, the only list block without a heading of its own.
 * - `layoutCards` gets a `steps` option (an ordered list, numbered by CSS — the number is never a field).
 *
 * Every field already exists; this only attaches them. Adopt-if-present throughout, so a replay after project config
 * already carries the change does nothing, and each layout save is checked to keep its existing element uids: content
 * is keyed by layout-element uid, so a rewrite that dropped one would take real content with it.
 */
class m260917_140000_businessBlocksSmallFields extends Migration
{
    public function safeUp(): bool
    {
        $this->attach('hero', 'preheading', before: 'heading');
        $this->attach('accordion', 'buttons', after: 'accordionItem');
        $this->attach('accordion', 'blockHeading', after: 'buttons');
        $this->addStepsLayout();

        return true;
    }

    public function safeDown(): bool
    {
        $this->detach('hero', 'preheading');
        $this->detach('accordion', 'buttons');
        $this->detach('accordion', 'blockHeading');

        $field = Craft::$app->getFields()->getFieldByHandle('layoutCards');

        if ($field instanceof Buttons) {
            $field->options = array_values(array_filter($field->options, static fn(array $o) => ($o['value'] ?? null) !== 'steps'));
            Craft::$app->getFields()->saveField($field);
        }

        return true;
    }

    /**
     * Put an existing field on an entry type's first tab, next to a named field (or at the end if that field isn't
     * there). Does nothing if the field is already on the layout, or if the type or field doesn't exist here.
     */
    private function attach(string $typeHandle, string $fieldHandle, ?string $before = null, ?string $after = null): void
    {
        $entries = Craft::$app->getEntries();
        $type = $entries->getEntryTypeByHandle($typeHandle);
        $field = Craft::$app->getFields()->getFieldByHandle($fieldHandle);

        if ($type === null || $field === null) {
            echo "  > No '{$typeHandle}' type or '{$fieldHandle}' field on this site — skipped.\n";

            return;
        }

        $layout = $type->getFieldLayout();

        foreach ($layout->getCustomFieldElements() as $element) {
            if ($element->getField()->handle === $fieldHandle) {
                echo "  > '{$typeHandle}' already has '{$fieldHandle}'.\n";

                return;
            }
        }

        $tabs = $layout->getTabs();
        $tab = $tabs[array_key_first($tabs)];
        $elements = array_values($tab->getElements());
        $anchor = $before ?? $after;
        $insertAt = count($elements);

        foreach ($elements as $i => $element) {
            if ($element instanceof CustomField && $element->getField()->handle === $anchor) {
                $insertAt = $before !== null ? $i : $i + 1;
                break;
            }
        }

        $uidsBefore = array_map(static fn(CustomField $el) => $el->uid, $layout->getCustomFieldElements());
        array_splice($elements, $insertAt, 0, [new CustomField($field)]);

        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs($tabs);
        $tab->setElements($elements);
        $type->setFieldLayout($layout);

        if (!$entries->saveEntryType($type)) {
            throw new \Exception("Couldn't add '{$fieldHandle}' to '{$typeHandle}': " . implode(', ', $type->getErrorSummary(true)));
        }

        $saved = $entries->getEntryTypeById($type->id);
        $uidsAfter = array_map(static fn(CustomField $el) => $el->uid, $saved->getFieldLayout()->getCustomFieldElements());

        if (array_diff($uidsBefore, $uidsAfter) !== []) {
            throw new \Exception("Adding '{$fieldHandle}' to '{$typeHandle}' changed its existing layout; stopped.");
        }

        echo "  > Added '{$fieldHandle}' to '{$typeHandle}'.\n";
    }

    private function detach(string $typeHandle, string $fieldHandle): void
    {
        $entries = Craft::$app->getEntries();
        $type = $entries->getEntryTypeByHandle($typeHandle);

        if ($type === null) {
            return;
        }

        $layout = $type->getFieldLayout();
        $tabs = $layout->getTabs();
        $changed = false;

        foreach ($tabs as $tab) {
            $elements = array_values(array_filter(
                $tab->getElements(),
                static fn($el) => !($el instanceof CustomField && $el->getField()->handle === $fieldHandle)
            ));

            if (count($elements) !== count($tab->getElements())) {
                $changed = true;
            }

            $tab->setElements($elements);
        }

        if ($changed) {
            $layout->setTabs($tabs);
            $type->setFieldLayout($layout);
            $entries->saveEntryType($type);
        }
    }

    private function addStepsLayout(): void
    {
        $fields = Craft::$app->getFields();
        $field = $fields->getFieldByHandle('layoutCards');

        if (!$field instanceof Buttons) {
            echo "  > No Button Box 'layoutCards' field on this site — skipped.\n";

            return;
        }

        foreach ($field->options as $option) {
            if (($option['value'] ?? null) === 'steps') {
                echo "  > 'layoutCards' already offers 'steps'.\n";

                return;
            }
        }

        $field->options[] = [
            'label' => 'Steps',
            'showLabel' => '1',
            'value' => 'steps',
            'imageUrl' => '/assets/cms/images/layout-cards-steps.svg',
            'imageAlign' => 'top',
            'default' => '',
        ];

        if (!$fields->saveField($field)) {
            throw new \Exception("Couldn't add the 'steps' layout to 'layoutCards': " . implode(', ', $field->getErrorSummary(true)));
        }

        echo "  > Added the 'steps' layout to 'layoutCards'.\n";
    }
}
