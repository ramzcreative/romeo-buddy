<?php
/**
 * Theme Designer's Color System config for this site.
 *
 * Hand-edit this file to change role order, labels, which roles are
 * required (always on, no toggle — the CSS relies on these existing no
 * matter what) vs optional (get a toggle in Color System), each role's
 * seed hex for when it doesn't exist yet, the alert-color key list, Page
 * leaf defaults, and permanent/config-assigned custom UI colors — instead
 * of asking for a code change every time.
 *
 * `roles`' array order IS the display order everywhere (Color System,
 * Backgrounds, the UI tab's "+ Add color" role picker). A role with no
 * entry here at all (e.g. one hand-created via Backgrounds' "Create
 * custom background") is always treated as optional/toggleable — only an
 * explicit `required: true` here makes a role always-on.
 *
 * `default` only matters the moment a role doesn't exist yet and needs to
 * be created (toggled on for the first time, or a brand-new theme) — it's
 * a starting point, not a live value; every real theme's own actual
 * colors live in that theme's own `_colors-generated.pcss` as always,
 * edited from Color System like any other role once it exists.
 *
 * `locked`, per role, is variant => hex — any variant listed here is
 * automatically locked (as if the lock icon had been clicked) the moment
 * this role is created/seeded from this config; every other variant
 * computes fresh from `default` via the normal color formula and stays
 * unlocked/regeneratable. Empty by default on every role below (nothing
 * currently needs a locked seed) — this only matters for a role being
 * newly created, never touches an already-existing role's saved stops.
 *
 * Seeded 2026-07-22 from this site's actual live values at the time
 * (`default` theme) — Gray promoted from optional to required (CSS
 * depends on it out of the gate), Neutral demoted from always-on to
 * optional, matching Gary's own correction.
 *
 * `themes`, at the bottom of this file, lets a SPECIFIC theme override
 * any of the above just for itself — see that section's own comment.
 */
return [
    'roles' => [
        'primary' => [
            'label' => 'Primary',
            'required' => true,
            'default' => '#00aeef',
            // Example (not live — primary already exists on every real
            // theme, so this wouldn't do anything today): locking Dark to
            // an exact brand hex instead of letting it compute from
            // `default` would look like:
            //     'locked' => ['dark' => '#0d3550'],
            // Only listed variants lock; light/medium/accent/complement
            // would still compute fresh from `default` as normal. This
            // only ever takes effect the moment primary is CREATED (a
            // brand-new theme) — it can't retroactively relock an
            // already-existing role's already-saved stops.
            'locked' => [],
        ],
        'secondary' => [
            'label' => 'Secondary',
            'required' => true,
            'default' => '#ff6b4a',
            'locked' => [],
        ],
        'dark' => [
            'label' => 'Dark',
            'required' => true,
            'default' => '#14213d',
            'locked' => [],
        ],
        'gray' => [
            'label' => 'Gray',
            'required' => true,
            'default' => '#4a5578',
            'locked' => [],
        ],
        'neutral' => [
            'label' => 'Neutral',
            'required' => false,
            'default' => '#4a5578',
            'locked' => [],
        ],
        'tertiary' => [
            'label' => 'Tertiary',
            'required' => false,
            'default' => '#f7ddb2',
            'locked' => [],
        ],
    ],

    // Unmodified from the tool's old hardcoded ALERT_DEFAULTS — neither
    // theme on this site has ever saved a custom alert color.
    'alerts' => [
        'success' => '#00ae2a',
        'warning' => '#f59e0b',
        'error' => '#f56565',
        'info' => '#3b82f6',
    ],

    'page' => [
        'leaves' => [
            'body' => ['label' => 'Body', 'default' => '#fffaf0'],
            'body-medium' => ['label' => 'Body medium', 'default' => '#f3e7e1'],
        ],
    ],

    // No theme on this site has a custom UI color today.
    'customUiColors' => [
        'vivid' => [
            'label' => 'Vivid',
            'required' => true,
            'role' => 'primary',
            'stop' => 'base',
        ],
        'active' => [
            'label' => 'Active',
            'required' => true,
            'role' => 'secondary',
            'stop' => 'base',
        ],
        'muted' => [
            'label' => 'Muted',
            'required' => true,
            'role' => 'gray',
            'stop' => 'base',
        ],
        'muted-dark' => [
            'role' => 'gray',
            'stop' => 'dark',
        ],
    ],

    // Per-theme overrides, keyed by theme handle — everything above is
    // this SITE's default for every theme; a theme listed here only
    // needs to name what it's actually changing, not repeat the whole
    // config. Every field it doesn't mention (on a role it does mention,
    // or a role/section it doesn't touch at all) still comes from the
    // site defaults above (label/required stay inherited below — only
    // `default`/`locked` are set, since this is a value snapshot, not a
    // schema change).
    'themes' => [
        // `default` theme, filled in 2026-07-22 as a full snapshot of its
        // real live state at the time — every role's base (`default`) and
        // all 5 other variants (`locked`) captured exactly as they sat in
        // `_colors-generated.pcss` that day. First real use of the
        // per-theme override mechanism, added to test it: if this role
        // ever gets regenerated fresh from config, every stop below comes
        // back byte-for-byte instead of recomputing from just the base —
        // this theme's whole current look becomes the durable "back to
        // normal" state, not just its base hex.
        'default' => [
            'roles' => [
                'primary' => [
                    'default' => '#00aeef',
                    'locked' => [
                        'light' => '#84f6ff',
                        'dark' => '#0069a1',
                        'medium' => '#71a9c9',
                        'accent' => '#00affa',
                        'complement' => '#ef4100',
                    ],
                ],
                'secondary' => [
                    'default' => '#ff6b4a',
                    'locked' => [
                        'light' => '#ffba9a',
                        'dark' => '#ab2803',
                        'medium' => '#d48b79',
                        'accent' => '#ff5e36',
                        'complement' => '#4adeff',
                    ],
                ],
                'dark' => [
                    'default' => '#14213d',
                    'locked' => [
                        'light' => '#4d5b78',
                        'dark' => '#000005',
                        'medium' => '#1b2230',
                        'accent' => '#122041',
                        'complement' => '#3d3014',
                    ],
                ],
                'gray' => [
                    'default' => '#4a5578',
                    'locked' => [
                        'light' => '#b5beff',
                        'dark' => '#2c3246',
                        'medium' => '#505668',
                        'accent' => '#48557d',
                        'complement' => '#786d4a',
                    ],
                ],
                'neutral' => [
                    'default' => '#4a5578',
                    'locked' => [
                        'light' => '#8a96b8',
                        'dark' => '#141c38',
                        'medium' => '#505668',
                        'accent' => '#48557d',
                        'complement' => '#786d4a',
                    ],
                ],
                'tertiary' => [
                    'default' => '#f7ddb2',
                    'locked' => [
                        'light' => '#fffcd5',
                        'dark' => '#ae9772',
                        'medium' => '#ecdfca',
                        'accent' => '#fadcaa',
                        'complement' => '#b2ccf7',
                    ],
                ],
            ],
        ],
    ],
];
