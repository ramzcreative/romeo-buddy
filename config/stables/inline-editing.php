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
 * nothing structural (images, relations — those aren't offered by either
 * list yet), and deliberately not Craft's native `title` either: that's a
 * plain Element attribute, not a field-layout field (`getFieldByHandle()`
 * won't find it), and saves differently (`$element->title = ...`, not
 * `setFieldValue()`) — worth adding later as its own small case, not
 * folded in here to keep v1's save path to exactly one mechanism.
 *
 * `gearFields` is the SEPARATE allow-list for the gear panel's own fields
 * (Phase 3 — a block's non-content settings, not editable inline in the
 * page flow) — `backgroundRole` (the Background/ColorChip field) plus
 * every `layout*` field that's a real block-layout-variant switch. Kept
 * apart from `fields` because these are structural, not content, and
 * because being on THIS list is only half the gate: whether one is
 * actually offered on a given block also depends on the active theme's
 * own block rules (config/stables/blockfields.php + the theme's
 * config/blockfields.json), resolved by BlockFieldVisibility — being
 * allow-listed here just means "eligible in principle," never "always
 * shown." `layoutButton` (button alignment) and `layoutColumns` (column
 * count) are deliberately excluded — they're not block-layout-variant
 * switches the way `layoutHero`/`layoutCards`/etc. are, so they don't
 * belong in a "which layout is this block rendered as" panel.
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
        'gearFields' => [
            'backgroundRole',
            'layoutHero',
            'layoutCards',
            'layoutForms',
            'layoutGallery',
            'layoutImageText',
            'layoutLogos',
            'layoutPosts',
            'layoutSliders',
            'layoutTestimonials',
            'layoutVideo',
        ],
    ],
];
