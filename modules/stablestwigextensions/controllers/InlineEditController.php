<?php

namespace modules\stablestwigextensions\controllers;

use Craft;
use craft\elements\Entry;
use craft\elements\db\EntryQuery;
use craft\web\Controller;
use modules\stablestwigextensions\services\InlineEdit;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Front-end inline content editing (admin bar tier 4) — see
 * docs/inline-editing-spec.md. Every check here is the REAL enforcement:
 * `inlineEditAttrs()`'s markup gating (services/InlineEdit.php) is a UX
 * nicety only — this never trusts that a request came from that markup,
 * and re-checks the field allow-list and canSave() independently.
 *
 * Both actions work on any craft\elements\Entry, whether that's a
 * top-level page entry or a nested Matrix block/item entry (Craft 5 Matrix
 * blocks are real Entry elements) — no special-casing needed for depth.
 */
class InlineEditController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    /**
     * Saves ONE field on one element — a scoped, partial save (skips full
     * element validation, same idiom craft-modules' own
     * DesignerController/BlockFallback use for a similar single-field
     * update), not a normal CP entry save.
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $elementId = (int)$request->getRequiredBodyParam('elementId');
        $siteId = (int)$request->getRequiredBodyParam('siteId');
        $fieldHandle = (string)$request->getRequiredBodyParam('fieldHandle');
        $value = (string)$request->getBodyParam('value', '');
        $expectedDateUpdated = $request->getBodyParam('dateUpdated');

        if (!(new InlineEdit())->isFieldEditable($fieldHandle)) {
            throw new BadRequestHttpException("'{$fieldHandle}' isn't an inline-editable field.");
        }

        $element = $this->loadElement($elementId, $siteId);
        $user = $this->requireEditableBy($element);

        if ($element->getFieldLayout()?->getFieldByHandle($fieldHandle) === null) {
            throw new BadRequestHttpException("'{$fieldHandle}' isn't a field on this element.");
        }

        // Optimistic lock — someone else (a CP editor, most likely) may
        // have saved this element since the page was loaded.
        if ($expectedDateUpdated !== null && !$this->isStillCurrent($element, $expectedDateUpdated)) {
            return $this->asJson([
                'success' => false,
                'error' => 'stale',
                'message' => 'This was changed elsewhere since the page loaded. Reload and try again.',
            ]);
        }

        $element->setFieldValue($fieldHandle, $value);

        // Purification (for a CKEditor-backed field like heading/
        // subheading/preheading) needs no extra code here — it's
        // intrinsic to HtmlField::serializeValue(), called by every
        // saveElement(), regardless of what triggered it.
        if (!Craft::$app->getElements()->saveElement($element, false)) {
            return $this->asJson([
                'success' => false,
                'error' => 'invalid',
                'message' => $element->getFirstError($fieldHandle) ?? 'Could not be saved.',
            ]);
        }

        return $this->asJson([
            'success' => true,
            // The field's rendered value, not an echo of the raw input —
            // avoids drift between an optimistic client render and
            // Craft's own formatting/purification.
            'value' => (string)$element->getFieldValue($fieldHandle),
            'dateUpdated' => $element->dateUpdated?->getTimestamp(),
        ]);
    }

    /**
     * Reorders an existing Matrix field's blocks — moves the WHOLE block
     * (same granularity as dragging a Matrix block's row in the Craft CP),
     * strictly within the field it already lives in. No precedent for
     * this exact mechanic exists elsewhere in this codebase; the
     * exact-permutation check below is what keeps it from also becoming
     * an add/delete path by accident.
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $ownerId = (int)$request->getRequiredBodyParam('ownerId');
        $siteId = (int)$request->getRequiredBodyParam('siteId');
        $fieldHandle = (string)$request->getRequiredBodyParam('fieldHandle');
        // The client sends this as a JSON-encoded string (a plain FormData
        // field, not order[]=...&order[]=... form-array syntax), so it
        // arrives here as a string, not a native PHP array — confirmed by
        // real-browser testing, which is exactly the case this decode
        // step exists to handle.
        $order = json_decode((string)$request->getRequiredBodyParam('order'), true);

        if (!is_array($order) || $order === []) {
            throw new BadRequestHttpException('order must be a non-empty JSON array of block IDs.');
        }

        $owner = $this->loadElement($ownerId, $siteId);
        $this->requireEditableBy($owner);

        $fieldValue = $owner->getFieldValue($fieldHandle);

        if (!$fieldValue instanceof EntryQuery) {
            throw new BadRequestHttpException("'{$fieldHandle}' isn't a Matrix field on this element.");
        }

        /** @var Entry[] $current */
        $current = $fieldValue->status(null)->all();
        $byId = [];
        foreach ($current as $block) {
            $byId[$block->id] = $block;
        }

        $requestedIds = array_map('intval', $order);

        // The page only knows the blocks it rendered as editable: a disabled
        // block, or one without inline-edit markup (text, collage), isn't in
        // the list. So the requested blocks must be distinct blocks of this
        // field, and they're put back into the positions they already hold,
        // in the requested order, while every other block stays where it is.
        // The result is always a permutation of the current blocks: reorder
        // only, never add or delete.
        $currentIds = array_keys($byId);

        if (count(array_unique($requestedIds)) !== count($requestedIds) || array_diff($requestedIds, $currentIds) !== []) {
            throw new BadRequestHttpException('order must only contain blocks of this field, each once.');
        }

        $requested = array_flip($requestedIds);
        $next = 0;
        $finalIds = [];

        foreach ($currentIds as $id) {
            $finalIds[] = isset($requested[$id]) ? $requestedIds[$next++] : $id;
        }

        $requestedIds = $finalIds;

        // NOT an array of Entry objects — confirmed by real-browser testing
        // that Matrix::_createEntriesFromSerializedData() doesn't accept
        // that shape (a hard TypeError deep in Craft core). This is Craft's
        // own documented "delta" input format instead: sortOrder alone,
        // with no 'entries' key, resequences the field's EXISTING entries
        // ($forceSave stays false for each, per that method) without
        // touching their content — exactly the reorder-only operation this
        // action is meant to perform.
        $owner->setFieldValue($fieldHandle, ['sortOrder' => $requestedIds]);

        if (!Craft::$app->getElements()->saveElement($owner, false)) {
            return $this->asJson([
                'success' => false,
                'error' => 'invalid',
                'message' => 'Could not be saved.',
            ]);
        }

        return $this->asJson(['success' => true]);
    }

    private function loadElement(int $id, int $siteId): Entry
    {
        $element = Entry::find()->id($id)->siteId($siteId)->status(null)->one();

        if ($element === null) {
            throw new BadRequestHttpException('Element not found.');
        }

        return $element;
    }

    private function requireEditableBy(Entry $element): \craft\elements\User
    {
        $user = Craft::$app->getUser()->getIdentity();

        if ($user === null || !$element->canSave($user)) {
            throw new ForbiddenHttpException("You don't have permission to edit this.");
        }

        return $user;
    }

    private function isStillCurrent(Entry $element, mixed $expectedDateUpdated): bool
    {
        $actual = $element->dateUpdated?->getTimestamp();

        return $actual === null || (string)$actual === (string)$expectedDateUpdated;
    }
}
