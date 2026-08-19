<?php
namespace modules\stablestwigextensions;

use modules\stablestwigextensions\services\BlockFieldCss;
use modules\stablestwigextensions\twigextensions\ModuleTwigExtensions;

use Craft;

class Module extends \yii\base\Module
{
    public function init()
    {
        // Define a custom alias named after the namespace
        Craft::setAlias('@stablestwigextensions', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'stablestwigextensions\\console\\controllers';
        } else {
            $this->controllerNamespace = 'stablestwigextensions\\controllers';
        }

        parent::init();

        // Per-block field visibility in the page builder. Generated rather
        // than a static file because it has to embed per-database entry type
        // ids — see services/BlockFieldCss.php.
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $service = new BlockFieldCss();

            $css = $service->generate();
            if ($css !== '') {
                Craft::$app->getView()->registerCss($css);
            }

            // The type dropdown can't be restricted in CSS: Garnish appends an
            // open disclosure menu to document.body, so it isn't a descendant
            // of the form being edited and nothing scopes it to the block's
            // type. See resources/js/blockSwitchGroups.js.
            $groups = $service->switchGroupMap();
            if ($groups) {
                $view = Craft::$app->getView();
                $view->registerJs(
                    'window.stablesSwitchGroups = ' . \craft\helpers\Json::encode($groups) . ';',
                    \yii\web\View::POS_HEAD
                );
                $view->registerJs(
                    @file_get_contents(__DIR__ . '/resources/js/blockSwitchGroups.js') ?: '',
                    \yii\web\View::POS_END
                );
            }
        }

        // Custom initialization code goes here...
        if (Craft::$app->getRequest()->getIsSiteRequest()) {
            // Instantiate + register the extension:
            $extension = new ModuleTwigExtensions();
            Craft::$app->getView()->registerTwigExtension($extension);
        }
    }
}