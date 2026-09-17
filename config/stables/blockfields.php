<?php
/**
 * Page-builder block rules, applied in the CP as generated CSS.
 *
 * Craft has no owner-aware field condition, so a nested item can't know which
 * block contains it, and nothing in Craft restricts which types a block may be
 * switched to. Both are expressed here and turned into CSS by BlockFieldCss.
 *
 * This file shows every field: it holds only what is genuinely site-wide (builderFields, switchGroups). Which
 * fields a block hides belongs to the templates that render it, so the rules live beside them:
 *
 *   themes/_base/config/blockfields.json      what Base's own block templates don't render (blockfields.json.md beside it)
 *
 * Besides per-block rules, `nested.<parent>.hidden` hides fields on any block directly inside a parent type (a
 * Container, a Columns block's column), wherever that parent sits — applied by resources/js/blockNestedFields.js,
 * since generated CSS stays one level deep.
 *   themes/<handle>/config/blockfields.json   what a theme's own templates change
 *
 * Levels apply lowest first — this file, then _base, then a theme's ancestors, then the theme — and each unit a
 * level sets (e.g. `cards.itemFields`) replaces the one below it whole. So a block rule written HERE is overridden
 * wherever _base or the theme sets the same unit; put site-specific block rules in the site's theme JSON instead.
 * Resolved by craft-modules' BlockRules. See docs/theme-designer-blocks-spec.md §4.1 and
 * docs/business-blocks-spec.md §2.1.
 */

return [
    /**
     * The Matrix fields that offer blocks. Their entry types are the blocks; anything nested in a block is a child.
     */
    'builderFields' => ['pageBuilder', 'postBuilder', 'containerBlocks', 'columnBuilder'],

    /**
     * Which blocks may be switched between, in the entry type dropdown.
     *
     */
    'switchGroups' => [
        // All five keep their content in the shared `items` field, so a
        // switch between them carries it. (`stats` joins when the block is ported.)
        ['cards', 'slider', 'imageText', 'banner', 'spotlight'],
    ],

    /**
     * Field visibility, per block. Two independent tiers per field group:
     *
     *   hidden      structural — this block type never uses the field, full
     *               stop. Doesn't matter what layout is selected, doesn't
     *               matter if a new layout value is added later.
     *
     *   perLayout   conditional — the field only applies to some of the
     *               block's layout values, keyed by the layout field's own
     *               handle, live via `:checked` (verbb/buttonbox renders the
     *               layout picker as a radio group, so this follows the
     *               editor's clicks with no save needed). A layout value not
     *               listed hides the field; an unknown value (typo, renamed
     *               layout) is dropped at generation time rather than
     *               emitted, so a mistake here leaves the field visible
     *               instead of hiding it forever.
     *
     * A field may not appear in both tiers for the same block — that's a
     * contradiction (BlockFieldCss throws rather than silently picking one).
     *
     *   itemFields  fields on the shared `item` entry type nested in the
     *               block. One shared item type means it carries the union
     *               of what every block needs, so each block says which of
     *               them it doesn't use.
     *
     *   ownFields   fields on the block itself, sitting alongside its layout
     *               selector.
     *
     *   layoutOptions   values a layout picker offers that this site chooses
     *               not to. Keyed by the layout field's own handle => the
     *               option values to hide from the picker entirely — an
     *               editor can't select what isn't there. Unlike the tiers
     *               above, this isn't an owner-aware rule (no block/item
     *               boundary to cross), so it's the same rule regardless of
     *               block type or view mode.
     *
     *               Empty everywhere here: stables ships every layout for
     *               every client. A site that wants to withhold one — e.g. a
     *               client with no use for the carousel layout — sets it in
     *               that site's own forked config, not in this shared file.
     */
    /**
     * Block rules for this site only — normally empty. See the docblock at the top for where rules live and why a rule
     * here loses to _base's or a theme's for the same unit. The tiers:
     *
     *   hidden      structural — this block never uses the field, whatever layout is selected.
     *   perLayout   conditional — the field only applies to some layout values, keyed by the layout field's handle,
     *               live via `:checked`. A layout value not listed hides it; an unknown value is dropped, not emitted.
     *   itemFields  fields on the shared `item` entry type nested in the block.
     *   ownFields   fields on the block itself.
     *   childFields any other nested type (`blockHeading`, …); `itemFields` means `childFields.item`.
     *   layoutOptions   Button Box layout values this site withholds from the picker.
     *   available   false to stop offering the block (existing ones still render).
     *
     * A field may not be in both `hidden` and `perLayout` for the same block, and a block may not set both
     * `itemFields` and `childFields.item` — BlockFieldCss throws rather than guessing.
     */
    'blocks' => [
        /*
         * Examples — copy one out of this comment and edit it. Keys are block (entry type) handles; field
         * values are field handles. `php craft stablestwigextensions/blocks/offered` shows the result and
         * names any rule that points at something that doesn't exist; `php craft theme-picker/themes/config
         * blockfields --theme=<handle>` shows which level each unit came from.
         *
         * They're written in PHP, but most belong in a theme's JSON: a unit _base already sets (cards.itemFields,
         * slider.ownFields, …) is overridden by _base if you set it here. Here works only for units _base leaves
         * alone.
         *
         * Stop offering a block at all (editors can't add it; existing ones still render):
         *
         *     'gallery' => ['available' => false],
         *
         * Hide one of a block's own fields:
         *
         *     'posts' => [
         *         'ownFields' => ['hidden' => ['showFilters']],
         *     ],
         *
         * Show an item field only on some layouts (a layout not listed hides it):
         *
         *     'cards' => [
         *         'itemFields' => [
         *             'perLayout' => ['layoutCards' => ['itemIcon' => ['grid', 'list']]],
         *         ],
         *     ],
         *
         * Hide a field on a nested type other than `item` — here, the block heading's subheading:
         *
         *     'gallery' => [
         *         'childFields' => ['blockHeading' => ['hidden' => ['subheading']]],
         *     ],
         *
         * Remove a layout from the picker. Button Box layout fields only (layoutCards, layoutSliders,
         * layoutImageText, …); a Dropdown such as layoutForms is reported as a stale rule instead:
         *
         *     'cards' => [
         *         'layoutOptions' => ['layoutCards' => ['large']],
         *     ],
         *
         * Rules combine per block. Don't list a field in both `hidden` and `perLayout`, or set both
         * `itemFields` and `childFields.item` — either one throws rather than guessing.
         *
         * The same keys in themes/<handle>/config/blockfields.json. Each unit a theme sets replaces the one below
         * it whole — a theme's `cards.itemFields` replaces _base's list, it doesn't add to it — and every unit it
         * doesn't set keeps the lower level's value. As JSON:
         *
         *     { "blocks": { "cards": { "itemFields": { "hidden": ["text"] }, "layoutOptions": { "layoutCards": ["large"] } } } }
         */
    ],
];
