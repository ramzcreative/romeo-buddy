<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Lightswitch;
use verbb\buttonbox\fields\Buttons;

/**
 * Business blocks spec §4.1 and §4.2.
 *
 * - `layoutHero` (standard, product, video) goes first on the hero. `standard` is the default, and a hero saved
 *   before this has no value, which hero.twig and _layouts/index.twig both read as `standard`, so nothing changes.
 * - `heroParallax`, `video` and `autoplay` follow the hero's image; `backgroundRole` follows the layout picker.
 *   themes/_base/config/blockfields.json shows each only on the layout that uses it.
 * - `layoutVideo` (inline, modal) goes first on the video block; `inline` is today's behaviour.
 *
 * Adopt-if-present, and every layout save is checked to keep its existing element uids — content is keyed by them.
 */
class m260917_160000_heroLayoutsVideoModal extends Migration
{
    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();

        $layoutHero = $fields->getFieldByHandle('layoutHero') ?? $this->save(new Buttons([
            'name' => 'Layout Hero',
            'handle' => 'layoutHero',
            'displayAsGraphic' => true,
            'options' => [
                ['label' => 'Standard', 'showLabel' => '1', 'value' => 'standard', 'imageUrl' => '/assets/cms/images/layout-hero-standard.svg', 'imageAlign' => 'top', 'default' => '1'],
                ['label' => 'Product', 'showLabel' => '1', 'value' => 'product', 'imageUrl' => '/assets/cms/images/layout-hero-product.svg', 'imageAlign' => 'top', 'default' => ''],
                ['label' => 'Video', 'showLabel' => '1', 'value' => 'video', 'imageUrl' => '/assets/cms/images/layout-hero-video.svg', 'imageAlign' => 'top', 'default' => ''],
            ],
        ]));

        $heroParallax = $fields->getFieldByHandle('heroParallax') ?? $this->save(new Lightswitch([
            'name' => 'Parallax',
            'handle' => 'heroParallax',
            'instructions' => 'The image moves slower than the page as it scrolls. Off for visitors who reduce motion.',
            'default' => false,
        ]));

        $layoutVideo = $fields->getFieldByHandle('layoutVideo') ?? $this->save(new Buttons([
            'name' => 'Layout Video',
            'handle' => 'layoutVideo',
            'displayAsGraphic' => true,
            'options' => [
                ['label' => 'Inline', 'showLabel' => '1', 'value' => 'inline', 'imageUrl' => '/assets/cms/images/layout-video-inline.svg', 'imageAlign' => 'top', 'default' => '1'],
                ['label' => 'Modal', 'showLabel' => '1', 'value' => 'modal', 'imageUrl' => '/assets/cms/images/layout-video-modal.svg', 'imageAlign' => 'top', 'default' => ''],
            ],
        ]));

        // This site's hero carries a second image beside the first; keep that pair together.
        $heroLayout = Craft::$app->getEntries()->getEntryTypeByHandle('hero')?->getFieldLayout();
        $afterImage = $heroLayout?->getFieldByHandle('image2') ? 'image2' : 'image';

        $this->place('hero', [[$layoutHero, 'Layout']], atStart: true);
        $this->place('hero', array_filter([
            [$fields->getFieldByHandle('backgroundRole'), null],
        ], static fn($pair) => $pair[0] !== null), after: 'layoutHero');
        $this->place('hero', array_filter([
            [$heroParallax, null],
            [$fields->getFieldByHandle('video'), null],
            [$fields->getFieldByHandle('autoplay'), null],
        ], static fn($pair) => $pair[0] !== null), after: $afterImage);

        $this->place('video', [[$layoutVideo, 'Layout']], atStart: true);

        return true;
    }

    public function safeDown(): bool
    {
        $this->detach('hero', ['layoutHero', 'backgroundRole', 'heroParallax', 'video', 'autoplay']);
        $this->detach('video', ['layoutVideo']);

        $fields = Craft::$app->getFields();

        // A null match must never reach a delete: see the stables CLAUDE.md on the migration that wiped a production site.
        foreach (['layoutHero' => Buttons::class, 'heroParallax' => Lightswitch::class, 'layoutVideo' => Buttons::class] as $handle => $class) {
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

    /**
     * Inserts fields the layout doesn't have yet, in order, into the first tab: at its start, or straight after `$after`.
     *
     * @param array<array{0: \craft\base\FieldInterface, 1: ?string}> $pairs field and its label
     */
    private function place(string $typeHandle, array $pairs, bool $atStart = false, ?string $after = null): void
    {
        $entries = Craft::$app->getEntries();
        $type = $entries->getEntryTypeByHandle($typeHandle);

        if ($type === null) {
            echo "  > No '{$typeHandle}' entry type on this site — skipped.\n";

            return;
        }

        $layout = $type->getFieldLayout();
        $present = array_map(static fn(CustomField $el) => $el->getField()->handle, $layout->getCustomFieldElements());
        $pairs = array_values(array_filter($pairs, static fn($pair) => !in_array($pair[0]->handle, $present, true)));

        if ($pairs === []) {
            return;
        }

        $tabs = $layout->getTabs();
        $tab = $tabs[array_key_first($tabs)];
        $elements = array_values($tab->getElements());
        $insertAt = $atStart ? 0 : count($elements);

        if ($after !== null) {
            foreach ($elements as $i => $element) {
                if ($element instanceof CustomField && $element->getField()->handle === $after) {
                    $insertAt = $i + 1;
                    break;
                }
            }
        }

        $new = array_map(static fn($pair) => new CustomField($pair[0], $pair[1] !== null ? ['label' => $pair[1]] : []), $pairs);
        $uidsBefore = array_map(static fn(CustomField $el) => $el->uid, $layout->getCustomFieldElements());
        array_splice($elements, $insertAt, 0, $new);

        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs($tabs);
        $tab->setElements($elements);
        $type->setFieldLayout($layout);

        if (!$entries->saveEntryType($type)) {
            throw new \Exception("Couldn't update '{$typeHandle}': " . implode(', ', $type->getErrorSummary(true)));
        }

        $saved = $entries->getEntryTypeById($type->id);
        $uidsAfter = array_map(static fn(CustomField $el) => $el->uid, $saved->getFieldLayout()->getCustomFieldElements());

        if (array_diff($uidsBefore, $uidsAfter) !== []) {
            throw new \Exception("Updating '{$typeHandle}' changed its existing layout; stopped.");
        }

        echo "  > Added " . implode(', ', array_map(static fn($pair) => $pair[0]->handle, $pairs)) . " to '{$typeHandle}'.\n";
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

        foreach ($tabs as $tab) {
            $tab->setElements(array_values(array_filter(
                $tab->getElements(),
                static fn($el) => !($el instanceof CustomField && in_array($el->getField()->handle, $handles, true))
            )));
        }

        $layout->setTabs($tabs);
        $type->setFieldLayout($layout);
        $entries->saveEntryType($type);
    }
}
