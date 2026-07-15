<?php

namespace modules\seo\controllers;

use craft\web\Controller;
use modules\seo\services\SitemapGenerator;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Serves sitemap.xml (index) and sitemap-<section>.xml — see routes
 * registered in modules/seo/Module.php. Public/anonymous by design (a
 * crawler is never logged into the CP); returns 404, not an empty-but-valid
 * sitemap, when the environment disallows indexing.
 */
class SitemapController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function actionIndex(): Response
    {
        $xml = (new SitemapGenerator())->generateIndex();
        if ($xml === null) {
            throw new NotFoundHttpException();
        }

        return $this->rawXmlResponse($xml);
    }

    public function actionSection(string $handle): Response
    {
        $xml = (new SitemapGenerator())->generateSection($handle);
        if ($xml === null) {
            throw new NotFoundHttpException();
        }

        return $this->rawXmlResponse($xml);
    }

    // Named distinctly from the parent's asXml() — that one serializes a
    // PHP data structure via Response::FORMAT_XML's formatter; this just
    // sends an already-built XML string through as-is (FORMAT_RAW).
    private function rawXmlResponse(string $xml): Response
    {
        $response = $this->response;
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->data = $xml;

        return $response;
    }
}
