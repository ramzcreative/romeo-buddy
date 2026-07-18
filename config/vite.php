<?php

use Craft;
use craft\helpers\App;

// Resolve the active theme so the Vite manifest/dist paths follow it, with no
// rebuild needed to switch — same value modules/themepicker/Module.php uses
// to switch the template root. Reads through ThemeRegistry (backed by the
// theme_settings table as of craft-modules v1.2.0), not project config
// directly — themePicker.activeTheme no longer lives there.
$activeTheme = 'default';
try {
    if (Craft::$app !== null && !Craft::$app->getRequest()->getIsConsoleRequest() && Craft::$app->getIsInstalled()) {
        $activeTheme = (new \modules\themepicker\services\ThemeRegistry())->getActiveThemeHandle();
    }
} catch (\Throwable $e) {
    // DB/project config not ready yet (e.g. during install) — fall back to default.
}

return [
    'useDevServer' => App::env('CRAFT_DEV_MODE'),
    // Vite 6+ writes the manifest to a `.vite/` subfolder by default (moved
    // from the dist root in Vite 5+).
    'manifestPath' => '@webroot/dist/' . $activeTheme . '/.vite/manifest.json',
    'devServerPublic' => App::env('PRIMARY_SITE_URL') . ':' . App::env('DEV_PORT_HTTP')  . '/',
    'serverPublic' => App::env('PRIMARY_SITE_URL') . '/dist/' . $activeTheme . '/',
    'errorEntry' => 'main.js',
    'cacheKeySuffix' => '',
    // Bypasses Herd/nginx entirely — the Vite dev server (npm run dev) is a
    // plain Node process on localhost, not proxied, so ping it directly.
    'devServerInternal' => 'http://localhost:' . App::env('DEV_PORT_HTTP'),
    // Actually verify the dev server is reachable before pointing every
    // script/link tag at it. With this off (the previous setting),
    // CRAFT_DEV_MODE=true alone was enough to assume it's running — forget
    // to start `npm run dev` and every page silently breaks (nothing
    // loads from the dead dev-server port, no fallback). With this on,
    // Craft pings devServerInternal first and falls back to the built
    // web/dist/ assets if nothing answers.
    'checkDevServer' => true,
    'includeReactRefreshShim' => false,
    // main.js already does `import 'vite/modulepreload-polyfill'` itself (the
    // Vite-recommended approach) — leaving this on double-shipped the same
    // polyfill a second time, inlined fresh into every page's HTML on top of
    // the cached copy already bundled into main.js.
    'includeModulePreloadShim' => false,
    'criticalPath' => '@webroot/dist/' . $activeTheme . '/assets',
    'criticalSuffix' =>'',
];