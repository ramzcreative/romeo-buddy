<?php

namespace modules\seo\controllers;

use Craft;
use craft\web\Controller;
use modules\seo\services\SeoResolver;
use yii\web\Response;

/**
 * Serves robots.txt dynamically — reads the same disallowRobots flag that
 * drives the X-Robots-Tag header (config/general.php) and sitemap gating
 * (SitemapGenerator), so there's exactly one on/off switch, not several
 * that could drift out of sync.
 */
class RobotsController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function actionIndex(): Response
    {
        $resolver = new SeoResolver();
        $siteUrl = rtrim(Craft::$app->getSites()->getCurrentSite()->getBaseUrl() ?? '', '/');

        if (!$resolver->isIndexable(null)) {
            $body = "User-agent: *\nDisallow: /\n";
        } else {
            $body = "User-agent: *\nDisallow:\n\nSitemap: {$siteUrl}/sitemap.xml\n";
        }

        $response = $this->response;
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->data = $body;

        return $response;
    }
}
