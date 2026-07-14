<?php
namespace modules\stablestwigextensions;

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

        // Custom initialization code goes here...
        if (Craft::$app->getRequest()->getIsSiteRequest()) {
            // Instantiate + register the extension:
            $extension = new ModuleTwigExtensions();
            Craft::$app->getView()->registerTwigExtension($extension);
        }
    }
}