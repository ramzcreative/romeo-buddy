<?php

namespace modules\themepicker\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use modules\themepicker\services\ThemeRegistry;
use modules\themepicker\web\assets\ThemePickerAsset;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ThemesController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requireAdmin();

        $registry = new ThemeRegistry();
        $themes = $registry->getThemes();
        $activeHandle = $registry->getActiveThemeHandle();

        Craft::$app->getView()->registerAssetBundle(ThemePickerAsset::class);

        // Publish each thumbnail individually rather than the whole themes/
        // directory — publishing the full tree meant any edit anywhere in
        // themes/ (templates, CSS, JS — none of which the CP needs here)
        // invalidated this bundle's cache-busting hash and forced a full
        // re-publish of every theme's source files just to show thumbnails.
        $assetManager = Craft::$app->getAssetManager();
        foreach ($themes as &$theme) {
            $theme['thumbnailUrl'] = null;
            if ($theme['thumbnail']) {
                $thumbnailPath = Craft::getAlias('@root/themes/' . $theme['handle'] . '/' . $theme['thumbnail']);
                if (is_file($thumbnailPath)) {
                    [, $theme['thumbnailUrl']] = $assetManager->publish($thumbnailPath);
                }
            }
        }
        unset($theme);

        return $this->renderTemplate('theme-picker/index', [
            'themes' => $themes,
            'activeHandle' => $activeHandle,
        ]);
    }

    public function actionSelect(): Response
    {
        $this->requireAdmin();
        $this->requirePostRequest();

        $handle = Craft::$app->getRequest()->getRequiredBodyParam('handle');
        $registry = new ThemeRegistry();
        $themes = $registry->getThemes();

        if (!isset($themes[$handle])) {
            throw new NotFoundHttpException('Unknown theme: ' . $handle);
        }

        $registry->setActiveThemeHandle($handle);

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Theme updated.'));

        return $this->redirectToPostedUrl(null, UrlHelper::cpUrl('theme-picker'));
    }
}
