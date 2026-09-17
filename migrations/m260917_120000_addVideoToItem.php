<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;

/**
 * Puts the existing `video` field on the shared `item` entry type, so an item can carry a video as well as an
 * image.
 *
 * Why: the pinned-scene layout (`imageText · hero`) is built around full-bleed background media, and in that
 * idiom the media is video — a still can only ever approximate it. The field is not new; the `video` block has
 * used it since the block library was built. This only widens where it can be set.
 *
 * `item` is shared by cards, imageText, banner, spotlight and stats, so adding it here reaches all of them at
 * once. That is deliberate but it is NOT a licence for every block to show it: visibility is gated in
 * `config/stables/blockfields.php`, the same way `itemIcon` is restricted to a slider's hero layout. Today only
 * `imageText`'s hero layout offers it; a block starts offering it by being un-hidden there AND having a template
 * that renders it — never one without the other, or an editor gets a field that does nothing.
 *
 * `image` stays exactly as it is and keeps its own meaning: on a block that renders video it doubles as the
 * poster frame, which is also what the `video` block does with it.
 *
 * Adopt-if-present, and it verifies the item layout's existing element uids survive the save — content is keyed
 * by layout-element uid, so a layout rewrite that dropped one would take real content with it.
 */
class m260917_120000_addVideoToItem extends Migration
{
    private const ITEM = 'item';
    private const FIELD = 'video';

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();

        $video = $fields->getFieldByHandle(self::FIELD);

        if ($video === null) {
            echo "  > No '" . self::FIELD . "' field on this site — nothing to add.\n";

            return true;
        }

        $item = $entries->getEntryTypeByHandle(self::ITEM);

        if ($item === null) {
            echo "  > No '" . self::ITEM . "' entry type on this site — nothing to add.\n";

            return true;
        }

        $layout = $item->getFieldLayout();

        foreach ($layout->getCustomFieldElements() as $element) {
            if ($element->getField()->handle === self::FIELD) {
                echo "  > '" . self::ITEM . "' already has '" . self::FIELD . "'.\n";

                return true;
            }
        }

        $tabs = $layout->getTabs();
        $tab = $tabs[array_key_first($tabs)];
        $elements = array_values($tab->getElements());

        // Directly after `image`, because the two are a pair wherever both are offered: the video plays and the
        // image is its poster. Falls back to the end if this layout has no image.
        $insertAt = count($elements);

        foreach ($elements as $i => $element) {
            if ($element instanceof CustomField && $element->getField()->handle === 'image') {
                $insertAt = $i + 1;
                break;
            }
        }

        $uidsBefore = array_map(fn(CustomField $el) => $el->uid, $layout->getCustomFieldElements());
        $hadTitleField = $item->hasTitleField;

        array_splice($elements, $insertAt, 0, [new CustomField($video, [
            'instructions' => 'Optional. Where a block supports it, this plays as the background and the image above becomes its poster frame.',
        ])]);

        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs($tabs);
        $tab->setElements($elements);
        $item->setFieldLayout($layout);

        if (!$entries->saveEntryType($item)) {
            throw new \Exception("Couldn't add '" . self::FIELD . "' to '" . self::ITEM . "': " . implode(', ', $item->getErrorSummary(true)));
        }

        $saved = $entries->getEntryTypeById($item->id);
        $uidsAfter = array_map(fn(CustomField $el) => $el->uid, $saved->getFieldLayout()->getCustomFieldElements());

        if (array_diff($uidsBefore, $uidsAfter) !== [] || $saved->hasTitleField !== $hadTitleField) {
            throw new \Exception("Adding '" . self::FIELD . "' to '" . self::ITEM . "' changed its existing layout; stopped.");
        }

        return true;
    }

    public function safeDown(): bool
    {
        $entries = Craft::$app->getEntries();
        $item = $entries->getEntryTypeByHandle(self::ITEM);

        if ($item === null) {
            return true;
        }

        $layout = $item->getFieldLayout();
        $tabs = $layout->getTabs();
        $changed = false;

        foreach ($tabs as $tab) {
            $elements = array_values(array_filter(
                $tab->getElements(),
                static fn($el) => !($el instanceof CustomField && $el->getField()->handle === self::FIELD)
            ));

            if (count($elements) !== count($tab->getElements())) {
                $changed = true;
            }

            $tab->setElements($elements);
        }

        if (!$changed) {
            return true;
        }

        $layout->setTabs($tabs);
        $item->setFieldLayout($layout);
        $entries->saveEntryType($item);

        // The field itself is left alone: the `video` block has used it since long before this migration, so
        // deleting it here would take that block's content with it.
        return true;
    }
}
