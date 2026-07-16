<?php

namespace modules\seo\controllers;

use Craft;
use craft\web\Controller;
use modules\seo\models\SeoSettings;
use modules\seo\services\SeoSettingsService;
use yii\web\Response;

class SettingsController extends Controller
{
    public function actionIndex(?SeoSettings $settings = null): Response
    {
        $this->requireAdmin();

        $settings ??= (new SeoSettingsService())->getSettings();

        return $this->renderTemplate('seo/settings/index', [
            'settings' => $settings,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requireAdmin();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $service = new SeoSettingsService();
        $settings = $service->getSettings();

        $settings->orgName = $request->getBodyParam('orgName') ?: null;
        $settings->twitterHandle = $request->getBodyParam('twitterHandle') ?: null;
        $settings->twitterUrl = $request->getBodyParam('twitterUrl') ?: null;
        $settings->facebookUrl = $request->getBodyParam('facebookUrl') ?: null;
        $settings->instagramUrl = $request->getBodyParam('instagramUrl') ?: null;
        $settings->linkedinUrl = $request->getBodyParam('linkedinUrl') ?: null;
        $settings->youtubeUrl = $request->getBodyParam('youtubeUrl') ?: null;
        $settings->tiktokUrl = $request->getBodyParam('tiktokUrl') ?: null;
        $settings->pinterestUrl = $request->getBodyParam('pinterestUrl') ?: null;
        $settings->threadsUrl = $request->getBodyParam('threadsUrl') ?: null;
        $settings->titleSeparator = $request->getBodyParam('titleSeparator') ?: null;
        $settings->defaultTitleTemplate = $request->getBodyParam('defaultTitleTemplate') ?: null;
        $settings->defaultDescription = $request->getBodyParam('defaultDescription') ?: null;
        $settings->logoUid = $this->resolveAssetUid($request->getBodyParam('logoId'));
        $settings->defaultOgImageUid = $this->resolveAssetUid($request->getBodyParam('defaultOgImageId'));

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('app', "Couldn't save SEO settings."));
            Craft::$app->getUrlManager()->setRouteParams(['settings' => $settings]);

            return null;
        }

        $service->saveSettings($settings);

        Craft::$app->getSession()->setNotice(Craft::t('app', 'SEO settings saved.'));

        return $this->redirectToPostedUrl();
    }

    private function resolveAssetUid(mixed $assetIdParam): ?string
    {
        $id = is_array($assetIdParam) ? ($assetIdParam[0] ?? null) : $assetIdParam;

        if (!$id) {
            return null;
        }

        return Craft::$app->getAssets()->getAssetById((int)$id)?->uid;
    }
}
