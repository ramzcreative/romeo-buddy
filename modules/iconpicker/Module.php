<?php

namespace modules\iconpicker;

use Craft;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\services\Fields;
use craft\web\Application;
use craft\web\View;
use modules\iconpicker\fields\IconPicker;
use modules\iconpicker\twig\IconPickerTwigExtension;
use yii\base\Event;

class Module extends \yii\base\Module
{
    public function init(): void
    {
        Craft::setAlias('@iconpicker', __DIR__);

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
                $event->types[] = IconPicker::class;
            }
        );

        // This module's own CP templates (the field's input template).
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots['iconpicker'] = __DIR__ . '/templates';
            }
        );

        // renderIcon() Twig function, front-end templates only — the CP
        // field's own template reads icons straight from IconRegistry.
        Event::on(
            Application::class,
            Application::EVENT_INIT,
            function () {
                $app = Craft::$app;
                $request = $app->getRequest();

                if ($request->getIsConsoleRequest() || $request->getIsCpRequest()) {
                    return;
                }

                $app->getView()->registerTwigExtension(new IconPickerTwigExtension());
            }
        );
    }
}
