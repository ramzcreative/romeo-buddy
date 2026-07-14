<?php

namespace modules\themepicker\console\controllers;

use craft\console\Controller;
use modules\themepicker\services\ThemeRegistry;
use yii\console\ExitCode;
use yii\helpers\Console;

class ThemesController extends Controller
{
    public function actionList(): int
    {
        $registry = new ThemeRegistry();
        $themes = $registry->getThemes();
        $active = $registry->getActiveThemeHandle();

        foreach ($themes as $theme) {
            $marker = $theme['handle'] === $active ? '*' : ' ';
            $this->stdout("$marker {$theme['handle']} — {$theme['name']}\n");
        }

        return ExitCode::OK;
    }

    public function actionActivate(string $handle): int
    {
        $registry = new ThemeRegistry();
        $themes = $registry->getThemes();

        if (!isset($themes[$handle])) {
            $this->stderr("Unknown theme: $handle\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $registry->setActiveThemeHandle($handle);
        $this->stdout("Active theme set to \"$handle\"\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
