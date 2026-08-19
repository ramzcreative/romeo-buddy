<?php
/**
 * Page-builder block rules, applied in the CP as generated CSS.
 *
 * Craft has no owner-aware field condition, so a nested item can't know which
 * block contains it, and nothing in Craft restricts which types a block may be
 * switched to. Both are expressed here and turned into CSS by BlockFieldCss.
 *
 * Ported from stables, but the field handles are this site's own — the item
 * type here carries `iconPicker` and `comingSoon` where the boilerplate has
 * `itemIcon`, and has no entry-source fields. Which is the point of keeping
 * this in config rather than in the service.
 */

return [
    /**
     * Which blocks may be switched between, in the entry type dropdown.
     *
     * Switching a block's type in the CP destroys the fields the new type
     * doesn't have: the form posts only the current type's fields, so the save
     * rewrites content from that post and everything else is gone. It's
     * recoverable by discarding changes, but that isn't obvious enough to rely
     * on. (Switching programmatically preserves everything — the loss is
     * specific to the CP form, which is why it's easy to miss.)
     *
     * So only blocks that keep their content in the same fields are offered as
     * alternatives to each other; the rest are hidden from the dropdown rather
     * than left as a trap. A block in no group here is left alone, with
     * Craft's default behaviour.
     */
    'switchGroups' => [
        // All five keep their content in the shared `items` field, so a
        // switch between them carries it. What differs is the layout selector
        // and how many items the template draws — banner and spotlight loop
        // like the rest and just happen to be designed around one.
        ['cards', 'slider', 'imageText', 'banner', 'spotlight'],
    ],

    /**
     * Item fields each block hides.
     *
     * One shared item type means it carries the union of what every block
     * needs, so each block has to say which of them it doesn't use. Worked out
     * from what this site's layout templates actually render — `buttons` stays
     * visible on imageText because hero/show draw it even though default
     * doesn't, and visibility is per block type, not per layout.
     */
    'hiddenFields' => [
        'cards' => ['subheading', 'text'],
        'slider' => ['text', 'iconPicker', 'comingSoon'],
        'imageText' => ['subheading', 'iconPicker', 'comingSoon'],
        'banner' => ['preheading', 'subheading', 'iconPicker', 'comingSoon', 'text'],
        'spotlight' => ['preheading', 'iconPicker', 'comingSoon', 'text'],
    ],
];
