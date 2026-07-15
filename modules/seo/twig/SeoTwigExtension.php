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
        ];
    }

    public function renderSeoTags(?ElementInterface $entry = null): Markup
    {
        $title = $this->resolver->getTitle($entry);
        $description = $this->resolver->getDescription($entry);
        $canonical = $this->resolver->getCanonicalUrl($entry);
        $robots = $this->resolver->getRobotsContent($entry);
        $image = $this->resolver->getImage($entry);
        $siteName = Craft::$app->getSites()->getCurrentSite()->getName();
        $twitterHandle = $this->resolver->getTwitterHandle();
        $imageUrl = $image?->getUrl();

        $tags = [];
        $tags[] = '<title>' . Html::encode($title) . '</title>';
        if ($description) {
            $tags[] = Html::tag('meta', '', ['name' => 'description', 'content' => $description]);
        }
        $tags[] = Html::tag('meta', '', ['name' => 'robots', 'content' => $robots]);
        if ($canonical) {
            $tags[] = Html::tag('link', '', ['rel' => 'canonical', 'href' => $canonical]);
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
        if ($imageUrl) {
            $tags[] = Html::tag('meta', '', ['property' => 'og:image', 'content' => $imageUrl]);
        }

        // Twitter Card
        $tags[] = Html::tag('meta', '', ['name' => 'twitter:card', 'content' => $imageUrl ? 'summary_large_image' : 'summary']);
        $tags[] = Html::tag('meta', '', ['name' => 'twitter:title', 'content' => $title]);
        if ($description) {
            $tags[] = Html::tag('meta', '', ['name' => 'twitter:description', 'content' => $description]);
        }
        if ($imageUrl) {
            $tags[] = Html::tag('meta', '', ['name' => 'twitter:image', 'content' => $imageUrl]);
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
