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

// Resolve the active theme so @webrootTheme follows it, same guarded pattern
// config/vite.php already uses to pick the right dist bundle.
$activeTheme = 'default';
try {
    if (Craft::$app !== null && !Craft::$app->getRequest()->getIsConsoleRequest() && Craft::$app->getIsInstalled()) {
        $activeTheme = Craft::$app->getProjectConfig()->get('themePicker.activeTheme') ?: 'default';
    }
} catch (\Throwable $e) {
    // DB/project config not ready yet (e.g. during install) — fall back to default.
}

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

    // Errors
    ->errorTemplatePrefix('_errors/')

	//password protected pages
    ->loginPath('portal/portal-login')
    ->setPasswordRequestPath('portal/reset-password')
    ->postLogoutRedirect('portal/portal-login')

    //disable plugins
    //->disabledPlugins(['formie','pluginName'])
;