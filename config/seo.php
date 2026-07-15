<?php
/**
 * SEO module configuration
 *
 * Multi-environment config — Craft merges the '*' defaults with whichever
 * key matches the environment (dev/staging/production, from ENVIRONMENT —
 * see bootstrap.php / .env.example.*).
 *
 * This file holds developer-owned, structural SEO strategy — content that
 * rarely changes and isn't day-to-day editorial work. Day-to-day editable
 * content (org name, logo, social links, default OG image, etc.) lives in
 * the "SEO Defaults" Global Set instead; per-entry overrides live on the
 * `seo` field. See modules/seo/services/SeoResolver.php for how these
 * three levels cascade.
 *
 * - sectionDefaults: keyed by section handle. Each entry controls:
 *   - descriptionFallbackField: which field handle on that section's
 *     entries to pull plain-text from for an auto-generated meta
 *     description when neither the entry's own `seo.description` nor this
 *     section default resolves anything. Null means "no consistent
 *     field across this section's entry types — fall through to the
 *     Global Set's sitewide default description."
 *   - defaultOgImage: an asset ID to use as this section's fallback
 *     Open Graph/Twitter image, or null to fall through to the Global
 *     Set's sitewide default image.
 *   - titleTemplate: overrides the Global Set's sitewide title template
 *     for this section specifically. Supports {title}, {separator},
 *     {siteName}. Null uses the sitewide template unchanged.
 * - sitemap: per-environment cache behavior for generated sitemap XML.
 *   Caching itself is invalidated automatically on entry save/delete via
 *   Craft's own element cache tags (see SitemapGenerator) — this only
 *   controls how long a cache entry lives before that.
 */

return [
    '*' => [
        'sectionDefaults' => [
            'blog' => [
                'descriptionFallbackField' => 'excerpt',
                'defaultOgImage' => null,
                'titleTemplate' => '{title} {separator} {siteName} Blog',
            ],
            'pages' => [
                'descriptionFallbackField' => null,
                'defaultOgImage' => null,
                'titleTemplate' => null,
            ],
        ],
        'sitemap' => [
            'cacheDuration' => 3600, // 1 hour
        ],
    ],
    'dev' => [
        'sitemap' => [
            'cacheDuration' => 0, // always regenerate locally
        ],
    ],
];
