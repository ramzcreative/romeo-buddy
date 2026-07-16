<?php

namespace modules\seo\services;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\StringHelper;
use modules\seo\models\SeoData;
use modules\seo\models\SeoSettings;

/**
 * Resolves the final SEO values for an element through a 3-tier cascade:
 *   1. entry-level  — the `seoV2` field's own SeoData (explicit override)
 *   2. section-type  — config/seo.php's `sectionDefaults`, keyed by section
 *      handle (developer-owned content strategy, not CP-editable)
 *   3. global default — the sitewide SEO settings (project-config-backed,
 *      see modules/seo/services/SeoSettingsService.php)
 *
 * Robots/indexability is intentionally a separate, single-source-of-truth
 * check (getRobotsContent()) that always resolves toward more restrictive:
 * the environment's disallowRobots flag overrides everything else outright.
 *
 * FIELD_HANDLE was `seoV2` during the side-by-side SEOmatic rollout
 * (Phases 2-5) — renamed to `seo` as part of Phase 6's cutover, once the
 * old SEOmatic field (which owned that handle) was deleted.
 */
class SeoResolver
{
    public const FIELD_HANDLE = 'seo';

    private ?SeoSettings $settings = null;

    public function getRobotsContent(?ElementInterface $entry = null): string
    {
        // Environment always wins outright — never let a per-entry setting
        // make a disallowed environment more indexable than "nothing".
        if (Craft::$app->getConfig()->getGeneral()->disallowRobots) {
            return 'noindex, nofollow';
        }

        $seo = $this->getSeoData($entry);
        $index = ($seo && $seo->noindex) ? 'noindex' : 'index';
        $follow = ($seo && $seo->nofollow) ? 'nofollow' : 'follow';

        return "{$index}, {$follow}";
    }

    public function isIndexable(?ElementInterface $entry = null): bool
    {
        return !str_contains($this->getRobotsContent($entry), 'noindex');
    }

    public function getTitle(?ElementInterface $entry = null): string
    {
        $seo = $this->getSeoData($entry);
        if ($seo && $seo->title) {
            return $seo->title;
        }

        $siteName = Craft::$app->getSites()->getCurrentSite()->getName() ?? '';
        $separator = $this->getSettings()->titleSeparator ?: '|';
        $entryTitle = $entry?->title ?: $siteName;

        $template = $this->getSectionDefault($entry)['titleTemplate']
            ?? $this->getSettings()->defaultTitleTemplate
            ?? '{title} {separator} {siteName}';

        return trim(strtr($template, [
            '{title}' => $entryTitle,
            '{separator}' => $separator,
            '{siteName}' => $siteName,
        ]));
    }

    public function getDescription(?ElementInterface $entry = null): ?string
    {
        $seo = $this->getSeoData($entry);
        if ($seo && $seo->description) {
            return $seo->description;
        }

        $fallbackField = $this->getSectionDefault($entry)['descriptionFallbackField'] ?? null;
        if ($fallbackField && $entry instanceof Entry) {
            $value = $entry->getFieldValue($fallbackField) ?? null;
            $plain = is_string($value) ? trim(strip_tags($value)) : '';
            if ($plain !== '') {
                return StringHelper::safeTruncate($plain, 160);
            }
        }

        return $this->getSettings()->defaultDescription ?: null;
    }

    public function getImage(?ElementInterface $entry = null): ?Asset
    {
        $seo = $this->getSeoData($entry);
        if ($seo?->getImage()) {
            return $seo->getImage();
        }

        $sectionImageId = $this->getSectionDefault($entry)['defaultOgImage'] ?? null;
        if ($sectionImageId) {
            $asset = Craft::$app->getAssets()->getAssetById($sectionImageId);
            if ($asset) {
                return $asset;
            }
        }

        return $this->getSettings()->getDefaultOgImage();
    }

    public function getCanonicalUrl(?ElementInterface $entry = null): ?string
    {
        return $entry?->getUrl() ?: null;
    }

    public function getOrgName(): ?string
    {
        return $this->getSettings()->orgName ?: null;
    }

    public function getLogo(): ?Asset
    {
        return $this->getSettings()->getLogo();
    }

    public function getSocialLinks(): array
    {
        $settings = $this->getSettings();

        return array_values(array_filter([
            $settings->twitterUrl,
            $settings->facebookUrl,
            $settings->instagramUrl,
            $settings->linkedinUrl,
            $settings->youtubeUrl,
            $settings->tiktokUrl,
            $settings->pinterestUrl,
            $settings->threadsUrl,
        ]));
    }

    public function getTwitterHandle(): ?string
    {
        return $this->getSettings()->twitterHandle ?: null;
    }

    /**
     * Social links keyed by platform, for templates that need to pair each
     * URL with a platform-specific icon (see _partials/social.twig).
     */
    public function getSocialLinksByPlatform(): array
    {
        $settings = $this->getSettings();

        return [
            'twitter' => $settings->twitterUrl ?: null,
            'facebook' => $settings->facebookUrl ?: null,
            'linkedin' => $settings->linkedinUrl ?: null,
            'instagram' => $settings->instagramUrl ?: null,
            'youtube' => $settings->youtubeUrl ?: null,
            'tiktok' => $settings->tiktokUrl ?: null,
            'pinterest' => $settings->pinterestUrl ?: null,
            'threads' => $settings->threadsUrl ?: null,
        ];
    }

    public function getSeoData(?ElementInterface $entry): ?SeoData
    {
        if (!$entry || !$entry->getFieldLayout()?->getFieldByHandle(self::FIELD_HANDLE)) {
            return null;
        }

        return $entry->getFieldValue(self::FIELD_HANDLE);
    }

    private function getSectionDefault(?ElementInterface $entry): array
    {
        if (!$entry instanceof Entry) {
            return [];
        }

        $sectionHandle = $entry->getSection()?->handle;
        if (!$sectionHandle) {
            return [];
        }

        $config = Craft::$app->getConfig()->getConfigFromFile('seo');

        return $config['sectionDefaults'][$sectionHandle] ?? [];
    }

    private function getSettings(): SeoSettings
    {
        if (!$this->settings) {
            $this->settings = (new SeoSettingsService())->getSettings();
        }

        return $this->settings;
    }
}
