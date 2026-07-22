<?php

// Resolve the active theme, same guarded pattern config/general.php and
// config/vite.php use — the swatch hex previews below need to match
// whichever theme is actually live, not just 'default'. Reads through
// ThemeRegistry (backed by the theme_settings table as of craft-modules
// v1.2.0), not project config directly — themePicker.activeTheme no longer
// lives there.
$activeTheme = 'default';
try {
    if (Craft::$app !== null && !Craft::$app->getRequest()->getIsConsoleRequest() && Craft::$app->getIsInstalled()) {
        $activeTheme = (new \modules\themepicker\services\ThemeRegistry())->getActiveThemeHandle();
    }
} catch (\Throwable $e) {
    // DB/project config not ready yet (e.g. during install) — fall back to default.
}

/**
 * Per-theme swatch definitions, keyed by color role — for whichever roles
 * (primary, secondary, ...) that theme's own _colors-generated.pcss
 * actually defines. These are CP-preview-only, shown as the little swatch
 * icons when an editor picks a background — the actual rendered color
 * always comes from themes/_base/src/css/generated/backgrounds-generated.pcss's
 * bg--primary/bg--secondary/etc. classes (built on the theme's own colors
 * role tokens), via the CSS class this field stores (e.g. bg--primary).
 * Machine-owned — see colour-swatches-generated.php's own header. Managed
 * entirely from modules/themedesigner's Backgrounds tab, which keeps it in
 * sync with each theme's colors automatically; don't hand-edit either file.
 */
$themeSwatches = require __DIR__ . '/colour-swatches-generated.php';

$swatches = $themeSwatches[$activeTheme] ?? $themeSwatches['default'] ?? [];

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
foreach ($swatches as $role => $swatch) {
    if (!($swatch['enabled'] ?? true)) {
        continue;
    }

    $palette[] = [
        'label' => $swatch['label'],
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
    'themeColor' => $swatches['primary']['hex'] ?? '#ffffff',
];
