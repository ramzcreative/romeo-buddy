<?php
/**
 * General Configuration
 *
 * All of your system's general configuration settings go in here. You can see a
 * list of the available settings in vendor/craftcms/cms/src/config/GeneralConfig.php.
 *
 * @see \craft\config\GeneralConfig
 */

use Craft;
use craft\config\GeneralConfig;
use craft\helpers\App;

// Always 'default' here -- this file runs as part of constructing the
// Application itself, before the Craft class is even available (confirmed
// directly: referencing Craft::$app this early throws "Class Craft not
// found"), so there's no reliable way to look up the real active theme at
// this point. modules/themepicker/Module.php's Application::EVENT_INIT
// handler re-sets @webrootTheme correctly once the app actually exists --
// see that handler's own comment. Nothing reads @webrootTheme before then.
$activeTheme = 'default';

return GeneralConfig::create()
    // Set the default week start day for date pickers (0 = Sunday, 1 = Monday, etc.)
    ->defaultWeekStartDay(1)
    // Prevent generated URLs from including "index.php"
    ->omitScriptNameInUrls()
    // Preload Single entries as Twig variables
    ->preloadSingles()
    // Prevent user enumeration attacks
    ->preventUserEnumeration()
    // Set the @webroot alias so the clear-caches command knows where to find CP resources.
    // @webrootTheme resolves to the active theme's own web/assets/themes/<handle>/ folder,
    // for per-theme overrides (logo, favicons) that fall back to nothing — every theme is
    // expected to supply its own full set, copied from another theme's folder.
    ->aliases([
        '@webroot' => dirname(__DIR__) . '/web',
        '@webrootTheme' => dirname(__DIR__) . '/web/assets/themes/' . $activeTheme,
    ])

	->partialTemplatesPath('_blocks')
	->maxUploadFileSize(524288000)

    // Sends X-Robots-Tag: none on every response when true. Driven by
    // CRAFT_DISALLOW_ROBOTS (true on staging, false on production, per
    // .env.example.*) — the foundation of "never let non-production rank."
    // Note this only sets the HTTP header; robots.txt content itself is
    // handled separately by modules/seo's RobotsController.
    ->disallowRobots((bool) App::env('CRAFT_DISALLOW_ROBOTS'))

    // Errors
    ->errorTemplatePrefix('_errors/')

	//password protected pages
    ->loginPath('portal/portal-login')
    ->setPasswordRequestPath('portal/reset-password')
    ->postLogoutRedirect('portal/portal-login')

    //disable plugins
    //->disabledPlugins(['formie','pluginName'])
;