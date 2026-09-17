<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;

/**
 * Business blocks spec §3.6: background colour and background image, as a pair, on the section-level blocks — the
 * existing `backgroundRole` (ColorChip) and `backgroundImage` (Assets) fields, attached where they're missing.
 *
 * Placement follows what entryHeading, container and columns already do: the background sits in the Settings tab,
 * just before the block heading; the image goes straight after the colour. Blocks with a single tab get the pair
 * before their block heading (or at the end). `text` and `blockquote` get nothing (they render no section of their
 * own for a background to sit on); `hero` waits for its layouts (spec §4.1), since its only layout today is a
 * full-bleed image a colour couldn't show on; `banner` is colour only (its own image is the background).
 *
 * Adopt-if-present, and every layout save is checked to keep its existing element uids — content is keyed by them.
 */
class m260917_150000_blockBackgrounds extends Migration
{
    private const PAIR = ['backgroundRole', 'backgroundImage'];

    public function safeUp(): bool
    {
        foreach (['cards', 'imageText', 'slider', 'posts', 'image', 'video', 'related', 'gallery', 'stats', 'spotlight', 'accordion', 'buttons'] as $type) {
            $this->attach($type, self::PAIR);
        }

        $this->attach('banner', ['backgroundRole']);

        foreach (['entryHeading', 'form', 'container', 'columns'] as $type) {
            $this->attach($type, ['backgroundImage'], after: 'backgroundRole');
        }

        return true;
    }

    public function safeDown(): bool
    {
        foreach (['cards', 'imageText', 'slider', 'posts', 'image', 'video', 'related', 'gallery', 'stats', 'spotlight', 'accordion', 'buttons'] as $type) {
            $this->detach($type, self::PAIR);
        }

        $this->detach('banner', ['backgroundRole']);

        foreach (['entryHeading', 'form', 'container', 'columns'] as $type) {
            $this->detach($type, ['backgroundImage']);
        }

        return true;
    }

    /**
     * @param string[] $handles attached in this order, together
     */
    private function attach(string $typeHandle, array $handles, ?string $after = null): void
    {
        $entries = Craft::$app->getEntries();
        $type = $entries->getEntryTypeByHandle($typeHandle);

        if ($type === null) {
            echo "  > No '{$typeHandle}' entry type on this site — skipped.\n";

            return;
        }

        $layout = $type->getFieldLayout();
        $present = array_map(static fn(CustomField $el) => $el->getField()->handle, $layout->getCustomFieldElements());
        $fields = [];

        foreach ($handles as $handle) {
            $field = Craft::$app->getFields()->getFieldByHandle($handle);

            if ($field === null) {
                echo "  > No '{$handle}' field on this site — skipped for '{$typeHandle}'.\n";
                continue;
            }

            if (!in_array($handle, $present, true)) {
                $fields[] = $field;
            }
        }

        if ($fields === []) {
            echo "  > '{$typeHandle}' already has " . implode(' + ', $handles) . ".\n";

            return;
        }

        $tabs = $layout->getTabs();
        $tab = $this->targetTab($tabs, $after);
        $elements = array_values($tab->getElements());
        $insertAt = count($elements);

        foreach ($elements as $i => $element) {
            if (!$element instanceof CustomField) {
                continue;
            }

            $handle = $element->getField()->handle;

            if ($after !== null && $handle === $after) {
                $insertAt = $i + 1;
                break;
            }

            if ($after === null && in_array($handle, ['blockHeading', 'formHeading'], true)) {
                // Before the block heading, and before the rule that separates it when there is one.
                $insertAt = $i > 0 && !$elements[$i - 1] instanceof CustomField ? $i - 1 : $i;
                break;
            }
        }

        $uidsBefore = array_map(static fn(CustomField $el) => $el->uid, $layout->getCustomFieldElements());
        array_splice($elements, $insertAt, 0, array_map(static fn($field) => new CustomField($field), $fields));

        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs($tabs);
        $tab->setElements($elements);
        $type->setFieldLayout($layout);

        if (!$entries->saveEntryType($type)) {
            throw new \Exception("Couldn't add backgrounds to '{$typeHandle}': " . implode(', ', $type->getErrorSummary(true)));
        }

        $saved = $entries->getEntryTypeById($type->id);
        $uidsAfter = array_map(static fn(CustomField $el) => $el->uid, $saved->getFieldLayout()->getCustomFieldElements());

        if (array_diff($uidsBefore, $uidsAfter) !== []) {
            throw new \Exception("Adding backgrounds to '{$typeHandle}' changed its existing layout; stopped.");
        }

        echo "  > Added " . implode(' + ', array_map(static fn($f) => $f->handle, $fields)) . " to '{$typeHandle}' ({$tab->name}).\n";
    }

    /**
     * The tab holding `$after` when given; otherwise the Settings tab if the layout has one, else the first tab.
     *
     * @param \craft\models\FieldLayoutTab[] $tabs
     */
    private function targetTab(array $tabs, ?string $after): \craft\models\FieldLayoutTab
    {
        foreach ($tabs as $tab) {
            foreach ($tab->getElements() as $element) {
                if ($after !== null && $element instanceof CustomField && $element->getField()->handle === $after) {
                    return $tab;
                }
            }
        }

        foreach ($tabs as $tab) {
            if ($after === null && $tab->name === 'Settings') {
                return $tab;
            }
        }

        return $tabs[array_key_first($tabs)];
    }

    /**
     * @param string[] $handles
     */
    private function detach(string $typeHandle, array $handles): void
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
                static fn($el) => !($el instanceof CustomField && in_array($el->getField()->handle, $handles, true))
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
}
