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
     *               them it doesn't use. Worked out from what this site's
     *               layout templates actually render — `buttons` stays
     *               visible on imageText because hero/show draw it even
     *               though default doesn't, and visibility is per block
     *               type, not per layout.
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
     *               Empty here too — nothing on this site withholds a layout
     *               yet.
     */
    'blocks' => [
        'cards' => [
            'itemFields' => [
                'hidden' => ['subheading', 'text'],
            ],
        ],

        'slider' => [
            'itemFields' => [
                'hidden' => ['text', 'iconPicker', 'comingSoon'],
            ],
            'ownFields' => [
                'perLayout' => [
                    'layoutSliders' => [
                        // Only the hero layout draws a nav; the others have
                        // their own arrows and pagination and nothing to
                        // choose between.
                        'sliderNav' => ['hero'],
                    ],
                ],
            ],
            'layoutOptions' => [
                // 'layoutSliders' => ['carousels'],
            ],
        ],

        'imageText' => [
            'itemFields' => [
                'hidden' => ['subheading', 'iconPicker', 'comingSoon'],
            ],
        ],

        'banner' => [
            'itemFields' => [
                'hidden' => ['preheading', 'subheading', 'iconPicker', 'comingSoon', 'text'],
            ],
        ],

        'spotlight' => [
            'itemFields' => [
                'hidden' => ['preheading', 'iconPicker', 'comingSoon', 'text'],
            ],
        ],
    ],
];
