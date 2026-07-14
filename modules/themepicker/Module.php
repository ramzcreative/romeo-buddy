<?php

namespace modules\themepicker;

use Craft;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\web\Application;
use craft\web\twig\variables\Cp;
use craft\web\UrlManager;
use craft\web\View;
use modules\themepicker\services\ThemeRegistry;
use yii\base\Event;

class Module extends \yii\base\Module
{
    public function init(): void
    {
        Craft::setAlias('@themepicker', __DIR__);
        // Yii's console help/command-enumeration resolves controllerNamespace
        // to a path via the "@modules" alias (namespace root -> path root);
        // nothing else in this codebase registers it, so any module whose
        // controllerNamespace includes the "modules\" prefix needs this.
        Craft::setAlias('@modules', dirname(__DIR__));

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'modules\\themepicker\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\themepicker\\controllers';
        }

        parent::init();

        $this->attachEventHandlers();
    }

    private function attachEventHandlers(): void
    {
        // Switch the live site's template root to the active theme. Runs after
        // plugins load but before any rendering, so the change requires no rebuild.
        Event::on(
            Application::class,
            Application::EVENT_INIT,
            function () {
                $app = Craft::$app;
                $request = $app->getRequest();

                if ($request->getIsConsoleRequest() || !$app->getIsInstalled()) {
                    return;
                }

                // Only the live site should follow the active theme — the control
                // panel must keep using Craft's own CP templates.
                if ($request->getIsCpRequest()) {
                    return;
                }

                $handle = (new ThemeRegistry())->getActiveThemeHandle();
                $themePath = Craft::getAlias('@root/themes/' . $handle . '/templates');

                if (is_dir($themePath)) {
                    Craft::setAlias('@templates', $themePath);
                    $app->getView()->setTemplatesPath($themePath);
                }

                // Theme templates reference this instead of the static
                // CUSTOM_THEME env var, so Vite asset lookups (which are keyed
                // by the source path, e.g. "themes/coastal/src/js/main.js")
                // follow the live-switched theme too.
                $app->getView()->registerTwigExtension(new class($handle) extends \Twig\Extension\AbstractExtension implements \Twig\Extension\GlobalsInterface {
                    public function __construct(private string $handle) {}
                    public function getGlobals(): array
                    {
                        return ['activeThemeHandle' => $this->handle];
                    }
                });
            }
        );

        // This module's own CP-only templates.
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots['theme-picker'] = __DIR__ . '/templates';
            }
        );

        // Fallback for any template not present in the active theme's own (now
        // empty, unless overridden) templates/ folder: resolve it from the
        // shared _base theme instead. Empty-string key matches every template
        // name. Site requests only — CP has its own separate root/event above.
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots[''] = Craft::getAlias('@root/themes/_base/templates');
            }
        );

        // CP routes for the picker page + its save action.
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['theme-picker'] = 'theme-picker/themes/index';
                $event->rules['theme-picker/select'] = 'theme-picker/themes/select';
            }
        );

        // CP sidebar nav item.
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function (RegisterCpNavItemsEvent $event) {
                $event->navItems[] = [
                    'label' => 'Themes',
                    'url' => 'theme-picker',
                    'icon' => Craft::getAlias('@appicons/template.svg'),
                ];
            }
        );
    }
}
