<?php

namespace modules\seo\services;

use Craft;
use modules\seo\models\SeoSettings;

/**
 * Reads/writes the sitewide SEO defaults from project config, replacing the
 * old "SEO Defaults" Global Set. Mirrors modules\themepicker\services\ThemeRegistry's
 * use of project config for CP-editable, project-config-synced settings.
 */
class SeoSettingsService
{
    public const CONFIG_PATH = 'seo.settings';

    public function getSettings(): SeoSettings
    {
        $config = Craft::$app->getProjectConfig()->get(self::CONFIG_PATH) ?? [];

        $settings = new SeoSettings();
        $settings->setAttributes($config, false);

        return $settings;
    }

    public function saveSettings(SeoSettings $settings): void
    {
        Craft::$app->getProjectConfig()->set(
            self::CONFIG_PATH,
            $settings->getAttributes(),
            'Update SEO settings'
        );
    }
}
