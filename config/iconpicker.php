<?php
/**
 * Icon Picker field configuration
 *
 * Multi-environment config — Craft merges the '*' defaults with whichever
 * key matches CRAFT_ENVIRONMENT (dev/staging/production).
 *
 * - iconsPath: source directory Craft scans for icons. Subfolders one level
 *   deep become named "sets" shown as tabs in the picker.
 * - enableCache: whether the icon list + sanitized SVG content get cached
 *   via Craft's app-level cache component (Craft::$app->getCache() — file
 *   by default, or Redis if the app's cache component is configured for it).
 * - cacheDuration: seconds. Ignored when enableCache is false.
 */

return [
    '*' => [
        // built/optimized output — see the `icons:build` npm script
        'iconsPath' => '@webroot/dist/assets/icons',
        'enableCache' => true,
        'cacheDuration' => 604800, // 1 week
    ],
    'dev' => [
        // raw source — edit an SVG and see it immediately, no cache/build step
        'iconsPath' => '@root/themes/_base/src/icons',
        'enableCache' => false,
        'cacheDuration' => null,
    ],
];
