<?php

namespace modules\stablestwigextensions\controllers;

use Craft;
use craft\web\Controller;
use modules\stablestwigextensions\services\AdminBar;
use modules\themepicker\services\ThemeRegistry;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Session-only page-theme preview, set from the front-end admin bar. Never
 * touches the entry's own page-theme field — see
 * services/AdminBar.php::PREVIEW_SESSION_KEY and scaffold.twig's
 * pageThemeHandle resolution.
 *
 * Mirrors modules\themepicker's own site-theme preview posture (any
 * logged-in user may set or clear one, not just someone with
 * 'accessThemePicker' — a team should be able to spot-check a page theme
 * together) while staying session-scoped rather than DB-persisted, since
 * this is per-entry, not sitewide.
 */
class AdminBarController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function actionPreviewPageTheme(): Response
    {
        $this->requirePostRequest();

        $handle = Craft::$app->getRequest()->getRequiredBodyParam('handle');
        $theme = (new ThemeRegistry())->getThemes()[$handle] ?? null;

        if (!$theme || ($theme['type'] ?? ThemeRegistry::TYPE_SITE) !== ThemeRegistry::TYPE_PAGE) {
            throw new BadRequestHttpException("'{$handle}' isn't a Theme variant.");
        }

        Craft::$app->getSession()->set(AdminBar::PREVIEW_SESSION_KEY, $handle);

        return $this->redirectToPostedUrl();
    }

    public function actionStopPreviewPageTheme(): Response
    {
        $this->requirePostRequest();

        Craft::$app->getSession()->remove(AdminBar::PREVIEW_SESSION_KEY);

        return $this->redirectToPostedUrl();
    }

    /**
     * Turns the inline-editing show/hide toggle back on — same posture as
     * the page-theme actions above: any logged-in user may flip this, not
     * just someone with a specific permission, since it only shows/hides
     * affordances that are themselves still permission-gated per element.
     */
    public function actionEnableInlineEditing(): Response
    {
        $this->requirePostRequest();

        Craft::$app->getSession()->remove(AdminBar::INLINE_EDIT_SESSION_KEY);

        return $this->redirectToPostedUrl();
    }

    public function actionDisableInlineEditing(): Response
    {
        $this->requirePostRequest();

        Craft::$app->getSession()->set(AdminBar::INLINE_EDIT_SESSION_KEY, false);

        return $this->redirectToPostedUrl();
    }
}
