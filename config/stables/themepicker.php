<?php
/**
 * Theme Picker module configuration
 *
 * 'landingPages' opts this site into the per-entry theme override: a
 * landing page's own 'themeOverride' field wins over the sitewide active
 * theme for that page only. See
 * modules/themepicker/services/ThemeRegistry::resolveActiveThemeHandle()
 * (craft-modules) — omitting this key (or the whole file) keeps every
 * request on the sitewide theme, same as before this existed.
 */
return [
    '*' => [
        // One Vite build for every theme, in web/dist/site/ (vite.config.js). Absent means a build per theme.
        'buildLayout' => 'single',
        'landingPages' => [
            'sectionHandle' => 'landingPages',
            'themeOverrideFieldHandle' => 'themeOverride',
        ],
    ],
];
