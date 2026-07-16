<?php

// Resolve the active theme, same guarded pattern config/general.php and
// config/vite.php use — the swatch hex previews below need to match
// whichever theme is actually live, not just 'default'.
$activeTheme = 'default';
try {
    if (Craft::$app !== null && !Craft::$app->getRequest()->getIsConsoleRequest() && Craft::$app->getIsInstalled()) {
        $activeTheme = Craft::$app->getProjectConfig()->get('themePicker.activeTheme') ?: 'default';
    }
} catch (\Throwable $e) {
    // DB/project config not ready yet (e.g. during install) — fall back to default.
}

/**
 * Per-theme swatch definitions: label => [hex, CSS class], for whichever
 * base colors (color1, color2, ...) that theme's own _colors.pcss actually
 * defines. These are CP-preview-only, shown as the little swatch icons
 * when an editor picks a background — the actual rendered color always
 * comes from the theme's own themes/<handle>/src/css/base/_colors.pcss,
 * via the CSS class this field stores (e.g. bg--color1). A theme that
 * doesn't define one of these colors just doesn't offer that swatch,
 * rather than offering one that would render as nothing — coastal here
 * has no color3, so it only offers Primary/Secondary, not Calm. Keep in
 * sync with each theme's _colors.pcss by hand; there's no build-time link
 * between the two.
 */
$themeSwatches = [
    'default' => [
        'Primary' => ['hex' => '#00aeef', 'class' => 'bg--color1'],
        'Secondary' => ['hex' => '#ff6b4a', 'class' => 'bg--color2'],
        'Calm' => ['hex' => '#f7ddb2', 'class' => 'bg--color3'],
    ],
    'coastal' => [
        'Primary' => ['hex' => '#0e8f8a', 'class' => 'bg--color1'],
        'Secondary' => ['hex' => '#143c4d', 'class' => 'bg--color2'],
    ],
];

$swatches = $themeSwatches[$activeTheme] ?? $themeSwatches['default'];

$palette = [
    [
        'label' => 'None',
        'default' => true,
        'color' => [
            [
                'color' => 'transparent',
                'background' => 'bg--none',
            ],
        ],
    ],
];
foreach ($swatches as $label => $swatch) {
    $palette[] = [
        'label' => $label,
        'default' => false,
        'color' => [
            [
                'color' => $swatch['hex'],
                'background' => $swatch['class'],
            ],
        ],
    ];
}

return [
    'palettes' => [
        'Background' => $palette,
    ],
    // Single source of truth for the active theme's brand color — read by
    // scaffold.twig's <meta name="theme-color"> and by ManifestController
    // for site.webmanifest's theme_color, so both stay in sync with the CP
    // swatches above without duplicating the hex value a third time.
    'themeColor' => $swatches['Primary']['hex'] ?? '#ffffff',
];
