<?php

namespace modules\stablestwigextensions\services;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\fields\Dropdown;
use craft\helpers\Html;
use craft\helpers\Json;
use modules\themepicker\fields\ColorChip;
use modules\themepicker\services\BlockRules;

/**
 * Data + gating for front-end inline content editing (admin bar tier 4) —
 * see docs/inline-editing-spec.md. Kept out of the Twig extension itself,
 * matching AdminBar's own split (see that class's own docblock).
 *
 * This service only decides whether/what to render. The admin bar's own
 * show/hide toggle (AdminBar::inlineEditingEnabled()) is deliberately NOT
 * consulted here — that toggle only hides the resulting markup via CSS/JS,
 * it doesn't change what's permitted, so the markup itself doesn't need to
 * know about it. Keeping these two concerns apart is the whole point of
 * the admin bar / inline-editing split documented in the spec.
 */
class InlineEdit
{
    /**
     * Field handles eligible for inline editing, from
     * config/stables/inline-editing.php. Checked here so markup for an
     * ineligible field is never emitted, and checked again — for real —
     * in InlineEditController::actionSave(), which never trusts the
     * client just because this attribute exists in the page source.
     */
    private function editableFieldHandles(): array
    {
        return \modules\support\Config::get('inline-editing')['fields'] ?? [];
    }

    public function isFieldEditable(string $fieldHandle): bool
    {
        return in_array($fieldHandle, $this->editableFieldHandles(), true);
    }

    /**
     * Same allow-list posture as isFieldEditable(), for the gear panel's
     * own fields (background color, layout variant) — a separate list
     * (config/stables/inline-editing.php's `gearFields`) because these
     * are structural fields the content allow-list explicitly excludes.
     */
    private function gearFieldHandles(): array
    {
        return \modules\support\Config::get('inline-editing')['gearFields'] ?? [];
    }

    public function isGearFieldEditable(string $fieldHandle): bool
    {
        return in_array($fieldHandle, $this->gearFieldHandles(), true);
    }

    /**
     * `data-inline-edit="elementId:siteId:fieldHandle"` for one field on
     * one element, or an empty string when it can't be inline-edited —
     * missing element, field not on this element's own layout, field not
     * on the allow-list, current user can't save it, or this is a
     * preview/share-token request (disabled entirely, per the spec — a
     * previewed draft is never what a save here should target).
     */
    public function attrsFor(?Entry $element, string $fieldHandle): string
    {
        if ($element === null || !$this->isFieldEditable($fieldHandle) || $this->isTokenizedRequest()) {
            return '';
        }

        $user = Craft::$app->getUser()->getIdentity();

        if (!$user instanceof User) {
            return '';
        }

        if ($element->getFieldLayout()?->getFieldByHandle($fieldHandle) === null) {
            return '';
        }

        if (!$element->canSave($user)) {
            return '';
        }

        // dateUpdated rides along so the client can send it back on save —
        // the optimistic-lock check in InlineEditController::actionSave().
        $value = sprintf(
            '%d:%d:%s:%d',
            $element->id,
            $element->siteId,
            $fieldHandle,
            $element->dateUpdated?->getTimestamp() ?? 0
        );

        return 'data-inline-edit="' . Html::encode($value) . '"';
    }

    /**
     * `data-inline-block="ownerId:ownerSiteId:fieldHandle:blockId"` on a
     * block's own root tag — the boundary the front-end toolbar/outline
     * attaches to, and the addressing reorder needs (which owner, which
     * Matrix field, which block). Separate from attrsFor() above: that
     * one's per FIELD, this one's per BLOCK, and a block with no editable
     * fields at all can still be reorderable.
     *
     * Derived from Craft's own nested-element APIs
     * (`Entry::getOwner()`/`getField()`) rather than anything passed down
     * by `_builder.twig` — that dispatcher doesn't hand a block template
     * its owner or field handle today, so this works it out independently
     * instead of requiring a template-wiring change everywhere blocks are
     * rendered.
     */
    public function blockAttrs(?Entry $block): string
    {
        $attrs = $this->nestedElementAttrs($block, 'data-inline-block');

        // nestedElementAttrs() already ran every gate (token/user/canSave)
        // — an empty result here means gear fields shouldn't be computed
        // either, not just that the block-boundary attribute is skipped.
        if ($attrs === '' || $block === null) {
            return $attrs;
        }

        $gear = $this->gearAttrFor($block);

        return $gear === '' ? $attrs : $attrs . ' ' . $gear;
    }

    /**
     * `data-inline-gear='{"background":{...},"layout":{...}}'` on a
     * block's own root tag, alongside data-inline-block — which of this
     * block's own non-content fields (Background color, Layout variant)
     * the gear panel should offer, and their current values. Omits a
     * field entirely, not just hides it client-side, when: it isn't on
     * this block's own field layout, isn't on the gearFields allow-list
     * (config/stables/inline-editing.php), or the active theme's block
     * rules currently hide it (BlockFieldVisibility — the SAME check
     * InlineEditController::actionSave() re-runs server-side, so this is
     * a UX nicety, never the real enforcement).
     */
    private function gearAttrFor(Entry $block): string
    {
        $fields = $this->gearFields($block);

        if ($fields === []) {
            return '';
        }

        return 'data-inline-gear="' . Html::encode(Json::encode($fields)) . '"';
    }

    /**
     * Which of this block's own gear-eligible fields are currently
     * visible, and their (candidate or current) values — the one place
     * both this class's own markup (gearAttrFor()) and
     * InlineEditController::actionGearPanel() build that same
     * descriptor, so the panel's first render and its live re-check after
     * a not-yet-saved layout change go through identical logic.
     *
     * @param array<string, string> $candidateLayoutValues see
     *        BlockFieldVisibility::isVisible()'s own param of the same
     *        name — a not-yet-saved layout value to check against instead
     *        of the block's current one.
     * @return array{background?: array{handle: string, value: string}, layout?: array{handle: string, value: string, options: array<int, array{value: string, label: string}>}}
     */
    public function gearFields(Entry $block, array $candidateLayoutValues = []): array
    {
        $layout = $block->getFieldLayout();

        if ($layout === null) {
            return [];
        }

        $visibility = new BlockFieldVisibility();
        $blockType = $block->getType();
        $fields = [];

        foreach ($layout->getCustomFields() as $field) {
            $handle = $field->handle;

            if (!$this->isGearFieldEditable($handle) || !$visibility->isVisible($blockType, $block, $handle, $candidateLayoutValues)) {
                continue;
            }

            if ($field instanceof ColorChip) {
                $fields['background'] = [
                    'handle' => $handle,
                    'value' => (string)($block->getFieldValue($handle) ?? ''),
                ];
                continue;
            }

            if (!BlockRules::isButtonBox($field) && !$field instanceof Dropdown) {
                continue;
            }

            // A theme-withheld option (BlockFieldVisibility's own
            // layoutOptions, not the field's own settings) never reaches
            // the front-end picker either — same "match what the theme
            // allows" principle applied to option values, not just
            // whether the field shows at all.
            $hiddenOptions = $visibility->hiddenLayoutOptionValues($blockType, $handle);
            $options = [];

            foreach ((array)($field->options ?? []) as $option) {
                $value = (string)($option['value'] ?? '');

                if ($value === '' || in_array($value, $hiddenOptions, true)) {
                    continue;
                }

                $imageUrl = (string)($option['imageUrl'] ?? '');
                $options[] = array_filter([
                    'value' => $value,
                    'label' => (string)($option['label'] ?? $value),
                    // Already a root-relative URL (verbb/buttonbox's own
                    // option config, e.g. /assets/cms/images/layout-hero-
                    // standard.svg) — no URL generation needed, and absent
                    // entirely on layoutButton/layoutColumns, which have
                    // none (see config/stables/inline-editing.php's own
                    // note on why those two aren't in gearFields anyway).
                    'imageUrl' => $imageUrl,
                ], fn($v) => $v !== '');
            }

            if ($options !== []) {
                $fields['layout'] = [
                    'handle' => $handle,
                    'value' => (string)($block->getFieldValue($handle) ?? ''),
                    'options' => $options,
                ];
            }
        }

        return $fields;
    }

    /**
     * `data-inline-item="ownerId:ownerSiteId:fieldHandle:itemId"` on one
     * item within a block's shared `items` field — reorder-only (items
     * don't get their own outline/toolbar/content-activation the way a
     * block does), but the addressing is identical: an item is a nested
     * Matrix entry exactly like a block is, just one level deeper. Same
     * derivation as blockAttrs(), only the attribute name differs — kept
     * as a separate method/name because the JS/CSS treat the two very
     * differently, not because the underlying data shape does.
     */
    public function itemAttrs(?Entry $item): string
    {
        return $this->nestedElementAttrs($item, 'data-inline-item');
    }

    private function nestedElementAttrs(?Entry $nested, string $attrName): string
    {
        if ($nested === null || $this->isTokenizedRequest()) {
            return '';
        }

        $user = Craft::$app->getUser()->getIdentity();

        if (!$user instanceof User) {
            return '';
        }

        $owner = $nested->getOwner();
        $field = $nested->getField();

        if ($owner === null || $field === null || !$owner->canSave($user)) {
            return '';
        }

        $value = sprintf('%d:%d:%s:%d', $owner->id, $owner->siteId, $field->handle, $nested->id);

        return $attrName . '="' . Html::encode($value) . '"';
    }

    /**
     * Whether this request carries Craft's own share/preview token param
     * (`generalConfig.tokenParam`, "token" by default) — the signal for
     * "the element on screen is a draft or an otherwise-unpublished entry
     * being viewed via a share link", not the live canonical page.
     */
    private function isTokenizedRequest(): bool
    {
        $tokenParam = Craft::$app->getConfig()->getGeneral()->tokenParam;

        return Craft::$app->getRequest()->getQueryParam($tokenParam) !== null;
    }
}
