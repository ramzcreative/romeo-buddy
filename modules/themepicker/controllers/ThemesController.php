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

        $themesPath = Craft::getAlias('@root/themes');
        [, $publishedUrl] = Craft::$app->getAssetManager()->publish($themesPath);
        $publishedUrl = rtrim($publishedUrl, '/');

        foreach ($themes as &$theme) {
            $theme['thumbnailUrl'] = $theme['thumbnail']
                ? $publishedUrl . '/' . $theme['handle'] . '/' . $theme['thumbnail']
                : null;
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
