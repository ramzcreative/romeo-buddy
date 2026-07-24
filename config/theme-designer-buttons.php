<?php
/**
 * Theme Designer's Buttons config for this site.
 *
 * Hand-edit this file to change which button names are PERMANENT
 * (always exist, can't be deleted from the Buttons tab) — presence of a
 * key here is what makes it permanent, same mechanism
 * `theme-designer-colors.php`'s `customUiColors` section already uses
 * for permanent custom UI colors. A permanent button's role/stop/label
 * are still fully editable from the Buttons tab, same as an unlocked
 * Color System stop — only DELETING it is blocked.
 *
 * A button's name/handle here is completely independent of its color —
 * "primary" means "the site's main CTA button," not the `primary` Color
 * System role specifically. `role`/`stop` below are just the SEED used
 * the first time this button doesn't exist yet on a given scope (Base,
 * or an independent theme) — the live value after that is whatever's
 * actually saved in that scope's own `buttons-settings.json`, edited
 * from the Buttons tab like anything else.
 *
 * Any button NOT listed here is a regular, user-added, deletable button
 * (created via the Buttons tab's "+ Add button").
 */
return [
    'buttons' => [
        'primary' => ['label' => 'Primary', 'role' => 'primary', 'stop' => 'base'],
        'secondary' => ['label' => 'Secondary', 'role' => 'secondary', 'stop' => 'base'],
    ],
];
