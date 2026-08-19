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
 * - fieldDefaults: the sitewide field-handle chain for each auto-generated
 *   SEO value, tried in order until one resolves to non-empty content:
 *   - titleFields: source for the page title (before it's dropped into
 *     titleTemplate below). 'title' means the entry's native Craft title
 *     field — the only handle in any of these chains that isn't looked up
 *     via getFieldValue().
 *   - descriptionFields: source for the auto-generated meta description.
 *   - imageFields: source for the auto-generated OG/Twitter image (each
 *     handle must be an Assets field).
 *   A section can replace any of these three outright via sectionDefaults
 *   below — an empty array explicitly opts a section out of that chain
 *   (skip straight to the next tier down) rather than inheriting the
 *   sitewide one; omitting the key (or explicit null) inherits it.
 * - sectionDefaults: keyed by section handle. Each entry controls:
 *   - titleFields / descriptionFields / imageFields: see fieldDefaults
 *     above — replaces the sitewide chain for entries in this section.
 *   - defaultOgImage: an asset ID to use as this section's fallback
 *     Open Graph/Twitter image if imageFields resolves nothing, or null
 *     to fall through to the Global Set's sitewide default image.
 *   - titleTemplate: overrides the Global Set's sitewide title template
 *     for this section specifically. Supports {title}, {separator},
 *     {siteName}. Null uses the sitewide template unchanged.
 * - sitemap: per-environment cache behavior for generated sitemap XML.
 *   Caching itself is invalidated automatically on entry save/delete via
 *   Craft's own element cache tags (see SitemapGenerator) — this only
 *   controls how long a cache entry lives before that.
 *
 * Two related conventions that don't have a config key here because
 * nothing in this codebase exercises them yet — flagging so a future
 * client doesn't have to rediscover the pattern from scratch:
 *
 * - Faceted/filtered listings (a filterable product/location/service
 *   directory, category pages with ?color=/?size=-style query params,
 *   etc.): no such content type exists in this boilerplate today, so
 *   there's nothing to configure. When one shows up, that listing's
 *   controller/template should call
 *   SeoResolver::getRobotsContentForListing($allowedParams) instead of
 *   getRobotsContent() — pass it the query params that listing's own
 *   canonical view actually needs indexed; anything else forces noindex,
 *   so filtered combinations never compete with the unfiltered listing in
 *   search results.
 * - Paginated listings (the blog index's /blog/p2, /blog/p3, ...) already
 *   get correct treatment automatically: SeoResolver::getCanonicalUrl()
 *   self-references the current page instead of always pointing at page
 *   1, and getPageTitleSuffix() appends "Page N" so paginated pages don't
 *   share one duplicate <title>. Any future paginated listing that reuses
 *   renderSeoTags() gets this for free — no per-listing config needed.
 */

return [
    '*' => [
        'fieldDefaults' => [
            'titleFields' => ['heading', 'title'],
            'descriptionFields' => ['excerpt', 'intro', 'textPlain'],
            'imageFields' => ['image'],
        ],
        'sectionDefaults' => [
            'blog' => [
                'descriptionFields' => ['excerpt'],
                'defaultOgImage' => null,
                'titleTemplate' => '{title} {separator} {siteName} Blog',
            ],
            'pages' => [
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
