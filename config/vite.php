<?php

use craft\helpers\App;

// One build serves every theme (vite.config.js), so nothing here depends on the active theme.
return [
    'useDevServer' => App::env('CRAFT_DEV_MODE'),
    // Vite 6+ writes the manifest to a `.vite/` subfolder by default (moved
    // from the dist root in Vite 5+).
    'manifestPath' => '@webroot/dist/site/.vite/manifest.json',
    'devServerPublic' => App::env('PRIMARY_SITE_URL') . ':' . App::env('DEV_PORT_HTTP')  . '/',
    'serverPublic' => App::env('PRIMARY_SITE_URL') . '/dist/site/',
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
    'criticalPath' => '@webroot/dist/site/assets',
    'criticalSuffix' =>'',
];
