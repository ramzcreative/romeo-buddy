<?php

namespace modules\stablestwigextensions\services;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\Html;

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
        if ($block === null || $this->isTokenizedRequest()) {
            return '';
        }

        $user = Craft::$app->getUser()->getIdentity();

        if (!$user instanceof User) {
            return '';
        }

        $owner = $block->getOwner();
        $field = $block->getField();

        if ($owner === null || $field === null || !$owner->canSave($user)) {
            return '';
        }

        $value = sprintf('%d:%d:%s:%d', $owner->id, $owner->siteId, $field->handle, $block->id);

        return 'data-inline-block="' . Html::encode($value) . '"';
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
