<?php
/**
 * Front-end inline content editing configuration (admin bar tier 4) — see
 * docs/inline-editing-spec.md.
 *
 * Multi-environment config — Craft merges the '*' defaults with whichever
 * key matches the environment (dev/staging/production, from ENVIRONMENT —
 * see bootstrap.php / .env.example.*).
 *
 * `fields` is the allow-list of field HANDLES eligible for inline editing —
 * checked both when deciding whether to emit `data-inline-edit` markup
 * (modules/stablestwigextensions/services/InlineEdit.php) and, for real,
 * server-side on every save (InlineEditController::actionSave()), which
 * never trusts the client just because the markup exists in the page.
 * Matches "Field support tiers" in the spec doc: v1 is plain/short-text
 * CUSTOM fields only — nothing CKEditor-body-length (`text`, `intro`) yet,
 * nothing structural (images, relations, layout selectors — those go
 * through the gear panel instead, not this list), and deliberately not
 * Craft's native `title` either: that's a plain Element attribute, not a
 * field-layout field (`getFieldByHandle()` won't find it), and saves
 * differently (`$element->title = ...`, not `setFieldValue()`) — worth
 * adding later as its own small case, not folded in here to keep v1's
 * save path to exactly one mechanism.
 */

return [
    '*' => [
        'fields' => [
            'heading',
            'subheading',
            'preheading',
            'excerpt',
            // Confirmed craft\fields\PlainText — blockquote.twig's own fields.
            'textPlain',
            'citeName',
            'citeTitle',
        ],
    ],
];
