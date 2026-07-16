<?php

namespace modules\seo\twig;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\Html;
use craft\helpers\Json;
use modules\seo\services\SeoResolver;
use modules\seo\services\StructuredDataBuilder;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Front-end SEO rendering — renderSeoTags()/renderStructuredData(), called
 * once each from scaffold.twig's <head>. Registered for site requests only
 * (see modules/seo/Module.php's Application::EVENT_INIT handler).
 */
class SeoTwigExtension extends AbstractExtension
{
    private SeoResolver $resolver;
    private StructuredDataBuilder $structuredData;

    public function __construct()
    {
        $this->resolver = new SeoResolver();
        $this->structuredData = new StructuredDataBuilder($this->resolver);
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('renderSeoTags', [$this, 'renderSeoTags'], ['is_safe' => ['html']]),
            new TwigFunction('renderStructuredData', [$this, 'renderStructuredData'], ['is_safe' => ['html']]),
            new TwigFunction('seoSocialLinks', [$this->resolver, 'getSocialLinksByPlatform']),
        ];
    }

    public function renderSeoTags(?ElementInterface $entry = null): Markup
    {
        $title = $this->resolver->getTitle($entry);
        $description = $this->resolver->getDescription($entry);
        $canonical = $this->resolver->getCanonicalUrl($entry);
        $robots = $this->resolver->getRobotsContent($entry);
        $image = $this->resolver->getImage($entry);
        $imageAlt = $this->resolver->getImageAlt($entry);
        $site = Craft::$app->getSites()->getCurrentSite();
        $siteName = $site->getName();
        $twitterHandle = $this->resolver->getTwitterHandle();
        $imageUrl = $image?->getUrl(SeoResolver::OG_IMAGE_TRANSFORM);

        $tags = [];
        $tags[] = '<title>' . Html::encode($title) . '</title>';
        if ($description) {
            $tags[] = Html::tag('meta', '', ['name' => 'description', 'content' => $description]);
        }
        $tags[] = Html::tag('meta', '', ['name' => 'robots', 'content' => $robots]);
        if ($canonical) {
            $tags[] = Html::tag('link', '', ['rel' => 'canonical', 'href' => $canonical]);
        }

        // Search engine ownership verification — sitewide, sourced from the
        // SEO settings page (see modules/seo/models/SeoSettings.php), not
        // per-entry.
        if ($googleVerification = $this->resolver->getGoogleSiteVerification()) {
            $tags[] = Html::tag('meta', '', ['name' => 'google-site-verification', 'content' => $googleVerification]);
        }
        if ($bingVerification = $this->resolver->getBingSiteVerification()) {
            $tags[] = Html::tag('meta', '', ['name' => 'msvalidate.01', 'content' => $bingVerification]);
        }

        // Open Graph
        $tags[] = Html::tag('meta', '', ['property' => 'og:title', 'content' => $title]);
        if ($description) {
            $tags[] = Html::tag('meta', '', ['property' => 'og:description', 'content' => $description]);
        }
        if ($canonical) {
            $tags[] = Html::tag('meta', '', ['property' => 'og:url', 'content' => $canonical]);
        }
        $isArticle = $entry instanceof \craft\elements\Entry && $entry->getSection()?->handle === 'blog';
        $tags[] = Html::tag('meta', '', ['property' => 'og:type', 'content' => $isArticle ? 'article' : 'website']);
        if ($siteName) {
            $tags[] = Html::tag('meta', '', ['property' => 'og:site_name', 'content' => $siteName]);
        }
        // OG's locale format is underscore-delimited (en_US), unlike the
        // hyphenated BCP 47 tag (en-US) Craft/the <html lang> attribute use.
        $tags[] = Html::tag('meta', '', ['property' => 'og:locale', 'content' => str_replace('-', '_', $site->getLanguage())]);
        if ($isArticle) {
            /** @var \craft\elements\Entry $entry */
            if ($entry->postDate) {
                $tags[] = Html::tag('meta', '', ['property' => 'article:published_time', 'content' => $entry->postDate->format(DATE_ATOM)]);
            }
            if ($entry->dateUpdated) {
                $tags[] = Html::tag('meta', '', ['property' => 'article:modified_time', 'content' => $entry->dateUpdated->format(DATE_ATOM)]);
            }
        }
        if ($imageUrl) {
            $tags[] = Html::tag('meta', '', ['property' => 'og:image', 'content' => $imageUrl]);
            $tags[] = Html::tag('meta', '', ['property' => 'og:image:width', 'content' => (string) SeoResolver::OG_IMAGE_TRANSFORM['width']]);
            $tags[] = Html::tag('meta', '', ['property' => 'og:image:height', 'content' => (string) SeoResolver::OG_IMAGE_TRANSFORM['height']]);
            if ($imageAlt) {
                $tags[] = Html::tag('meta', '', ['property' => 'og:image:alt', 'content' => $imageAlt]);
            }
        }

        // Twitter Card
        $tags[] = Html::tag('meta', '', ['name' => 'twitter:card', 'content' => $imageUrl ? 'summary_large_image' : 'summary']);
        $tags[] = Html::tag('meta', '', ['name' => 'twitter:title', 'content' => $title]);
        if ($description) {
            $tags[] = Html::tag('meta', '', ['name' => 'twitter:description', 'content' => $description]);
        }
        if ($imageUrl) {
            $tags[] = Html::tag('meta', '', ['name' => 'twitter:image', 'content' => $imageUrl]);
            if ($imageAlt) {
                $tags[] = Html::tag('meta', '', ['name' => 'twitter:image:alt', 'content' => $imageAlt]);
            }
        }
        if ($twitterHandle) {
            $tags[] = Html::tag('meta', '', ['name' => 'twitter:site', 'content' => $twitterHandle]);
        }

        return new Markup(implode("\n", $tags), 'utf-8');
    }

    public function renderStructuredData(?ElementInterface $entry = null): Markup
    {
        $graph = $this->structuredData->build($entry);
        if (!$graph) {
            return new Markup('', 'utf-8');
        }

        $json = Json::encode([
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ], JSON_UNESCAPED_SLASHES);

        return new Markup('<script type="application/ld+json">' . $json . '</script>', 'utf-8');
    }
}
