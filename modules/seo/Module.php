<?php

namespace modules\seo;

use Craft;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Fields;
use craft\web\Application;
use craft\web\UrlManager;
use craft\web\View;
use modules\seo\fields\Seo;
use modules\seo\twig\SeoTwigExtension;
use yii\base\Event;

class Module extends \yii\base\Module
{
    public function init(): void
    {
        Craft::setAlias('@seo', __DIR__);

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'modules\\seo\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\seo\\controllers';
        }

        parent::init();

        $this->attachEventHandlers();
    }

    private function attachEventHandlers(): void
    {
        // Register the field type.
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Seo::class;
            }
        );

        // This module's own CP templates (the field's input template).
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots['seo'] = __DIR__ . '/templates';
            }
        );

        // Site routes for sitemap.xml / sitemap-<section>.xml / robots.txt —
        // these are real controller actions (dynamic per-environment output,
        // e.g. staging returns 404/disallow), not static files.
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['sitemap.xml'] = 'seo/sitemap/index';
                $event->rules['sitemap-<handle:[\w\-]+>.xml'] = 'seo/sitemap/section';
                $event->rules['robots.txt'] = 'seo/robots/index';
            }
        );

        // renderSeoTags()/renderStructuredData() Twig functions, front-end
        // templates only — the CP field's own template needs none of this.
        Event::on(
            Application::class,
            Application::EVENT_INIT,
            function () {
                $app = Craft::$app;
                $request = $app->getRequest();

                if ($request->getIsConsoleRequest() || $request->getIsCpRequest()) {
                    return;
                }

                $app->getView()->registerTwigExtension(new SeoTwigExtension());
            }
        );
    }
}
