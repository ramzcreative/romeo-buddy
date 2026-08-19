<?php
/**
 * Item resolution — how a page-builder item fills itself in from a selected
 * entry, and which of this site's fields hold what.
 *
 * The mapping lives here, per site, and not in the resolver. That's the whole
 * point: the old getItemData() hardcoded `image` then `heroImage` inside the
 * shared module, so a site whose field is named differently had to edit code
 * every other site also runs. This site is the example — its icon field is
 * `iconPicker`, not the boilerplate's `itemIcon`.
 *
 * Resolution for each key, in order:
 *   1. the item's own field (`item`) — always wins when it holds something
 *   2. the selected entry's fields (`entry`), tried in order
 *   3. nothing
 *
 * An empty `entry` list makes a key item-only: an icon has nowhere to come
 * from on an entry, so it only ever renders if it's set on the item itself.
 */

return [
    '*' => [
        // The item field holding the entry to pull from.
        'sourceField' => 'sourceEntry',

        'keys' => [
            'preheading' => ['item' => 'preheading', 'entry' => ['preheading']],
            // `book` has no heading field, so it falls through to the native
            // title; `page` has one and uses it.
            'heading' => ['item' => 'heading', 'entry' => ['heading', 'title']],
            'subheading' => ['item' => 'subheading', 'entry' => ['subheading']],
            'intro' => ['item' => 'intro', 'entry' => ['excerpt', 'intro']],
            'image' => ['item' => 'image', 'entry' => ['image']],
            'icon' => ['item' => 'iconPicker', 'entry' => []],
            'text' => ['item' => 'text', 'entry' => []],
        ],

        // Per-section overrides, for sections whose shape differs from the
        // sitewide chain. Keyed by section handle, then by key. Nothing needs
        // one yet — books and pages both carry `image` and `excerpt`.
        'sectionKeys' => [
            // 'books' => ['image' => ['coverImage']],
        ],
    ],
];
