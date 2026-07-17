<?php

namespace modules\activitysheets;

use Craft;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use craft\web\View;
use yii\base\Event;

/**
 * Powers the 'Activity Sheet' pageBuilder block (see
 * migrations/m260717_060753_addActivitySheetBlock.php) — generates a
 * downloadable word-search or maze PDF on request. Word search/maze are
 * both built entirely from logic (services/WordSearchGenerator,
 * services/MazeGenerator) — no character artwork involved. Coloring pages
 * aren't part of this: they need real Romeo & Buddy line-art from the
 * illustrator, which doesn't exist yet.
 */
class Module extends \yii\base\Module
{
    public function init(): void
    {
        Craft::setAlias('@activitysheets', __DIR__);

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'modules\\activitysheets\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\activitysheets\\controllers';
        }

        parent::init();

        $this->attachEventHandlers();
    }

    private function attachEventHandlers(): void
    {
        // This module's own templates (the PDF layouts) — kept separate
        // from the theme's templates/ dir since they're not
        // theme/design-system content, just print layouts fed to Dompdf.
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots['activitysheets'] = __DIR__ . '/templates';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['activity-sheets/generate'] = 'activitysheets/generate/index';
            }
        );
    }
}
