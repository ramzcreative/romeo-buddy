<?php

namespace modules\themepicker\services;

use Craft;
use craft\helpers\Json;

class ThemeRegistry
{
    public function getThemes(): array
    {
        $themesPath = Craft::getAlias('@root/themes');
        $themes = [];

        if (!is_dir($themesPath)) {
            return $themes;
        }

        $manifests = glob($themesPath . '/*/theme.json');

        foreach ($manifests as $manifestPath) {
            $handle = basename(dirname($manifestPath));
            $data = Json::decode(file_get_contents($manifestPath));

            $themes[$handle] = [
                'handle' => $handle,
                'name' => $data['name'] ?? $handle,
                'thumbnail' => $data['thumbnail'] ?? null,
            ];
        }

        ksort($themes);

        return $themes;
    }

    public function getActiveThemeHandle(): string
    {
        $themes = $this->getThemes();
        $handle = Craft::$app->getProjectConfig()->get('themePicker.activeTheme');

        if ($handle && isset($themes[$handle])) {
            return $handle;
        }

        if (isset($themes['default'])) {
            return 'default';
        }

        return array_key_first($themes) ?? 'default';
    }

    public function setActiveThemeHandle(string $handle): void
    {
        Craft::$app->getProjectConfig()->set(
            'themePicker.activeTheme',
            $handle,
            "Set active theme to \"$handle\""
        );
    }
}
