<?php

namespace modules\seo\services;

use Craft;
use craft\elements\Entry;
use yii\caching\TagDependency;

/**
 * Hand-rolled sitemap XML — no native Craft sitemap service exists to reuse
 * (confirmed absent from vendor/craftcms/cms/src/services). Caching reuses
 * Craft's own element-save cache invalidation for free: Craft core already
 * calls invalidateCachesForElement() on every entry save/delete, tagging
 * the cache with `element::craft\elements\Entry::*` — no custom event
 * listener needed here.
 *
 * Gated on SeoResolver::isIndexable(null) (the environment-level check) —
 * returns null (controller 404s) rather than an empty-but-valid sitemap
 * when disallowRobots is on, so nothing on a disallowed environment ever
 * points crawlers anywhere.
 */
class SitemapGenerator
{
    private SeoResolver $resolver;

    public function __construct(?SeoResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new SeoResolver();
    }

    /** @return string[] Handles of sections that actually have URLs on the current site. */
    public function getSectionHandles(): array
    {
        $currentSiteId = Craft::$app->getSites()->getCurrentSite()->id;
        $handles = [];

        foreach (Craft::$app->getEntries()->getAllSections() as $section) {
            $siteSettings = $section->getSiteSettings()[$currentSiteId] ?? null;
            if ($siteSettings && $siteSettings->hasUrls) {
                $handles[] = $section->handle;
            }
        }

        return $handles;
    }

    public function generateIndex(): ?string
    {
        if (!$this->resolver->isIndexable(null)) {
            return null;
        }

        $handles = $this->getSectionHandles();
        if (!$handles) {
            return null;
        }

        return $this->withCache('index.' . $this->siteCacheSuffix(), function () use ($handles) {
            $siteUrl = $this->siteUrl();
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            foreach ($handles as $handle) {
                $xml .= '  <sitemap><loc>' . htmlspecialchars("{$siteUrl}/sitemap-{$handle}.xml", ENT_XML1) . "</loc></sitemap>\n";
            }
            $xml .= '</sitemapindex>';
            return $xml;
        });
    }

    public function generateSection(string $sectionHandle): ?string
    {
        if (!$this->resolver->isIndexable(null)) {
            return null;
        }

        $currentSiteId = Craft::$app->getSites()->getCurrentSite()->id;
        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);
        $siteSettings = $section?->getSiteSettings()[$currentSiteId] ?? null;
        if (!$siteSettings || !$siteSettings->hasUrls) {
            return null;
        }

        return $this->withCache('section.' . $sectionHandle . '.' . $this->siteCacheSuffix(), function () use ($sectionHandle) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            foreach (Entry::find()->section($sectionHandle)->all() as $entry) {
                if (!$this->resolver->isIndexable($entry)) {
                    continue;
                }

                $url = $entry->getUrl();
                if (!$url) {
                    continue;
                }

                $lastmodDate = $entry->dateUpdated ?? $entry->postDate;
                $xml .= "  <url>\n";
                $xml .= '    <loc>' . htmlspecialchars($url, ENT_XML1) . "</loc>\n";
                if ($lastmodDate) {
                    $xml .= '    <lastmod>' . $lastmodDate->format('Y-m-d') . "</lastmod>\n";
                }
                $xml .= "  </url>\n";
            }

            $xml .= '</urlset>';
            return $xml;
        });
    }

    private function withCache(string $key, callable $generate): string
    {
        $duration = $this->cacheDuration();

        // 0/falsy means "don't cache" here (dev), not Yii's usual "cache
        // forever" — skip the cache component entirely in that case.
        if (!$duration) {
            return $generate();
        }

        return Craft::$app->getCache()->getOrSet(
            'seo.sitemap.' . $key,
            $generate,
            $duration,
            new TagDependency(['tags' => ['element::craft\\elements\\Entry::*']])
        );
    }

    private function cacheDuration(): int
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('seo');
        return (int)($config['sitemap']['cacheDuration'] ?? 3600);
    }

    private function siteUrl(): string
    {
        return rtrim(Craft::$app->getSites()->getCurrentSite()->getBaseUrl() ?? '', '/');
    }

    private function siteCacheSuffix(): string
    {
        return (string)Craft::$app->getSites()->getCurrentSite()->id;
    }
}
