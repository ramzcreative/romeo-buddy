<?php
/**
 * Redirects module configuration
 *
 * Multi-environment config — Craft merges the '*' defaults with whichever
 * key matches the environment (dev/staging/production, from ENVIRONMENT).
 *
 * Redirect RULES are not configured here: they live in the database and are
 * edited under SEO-adjacent CP screens (Redirects / Not Found), so a
 * non-developer can fix a broken link without a deploy. This file holds the
 * few things that are build decisions rather than content.
 *
 * Note Craft's own `config/redirects.php` is a separate, built-in feature
 * that this module deliberately does not replace — it reuses Craft's
 * matching engine (craft\web\RedirectRule) but reads rules from the database
 * first. A site can use both; the database wins.
 *
 * ---------------------------------------------------------------------------
 * FUTURE: multi-domain support
 * ---------------------------------------------------------------------------
 * The tables already carry a `siteId` on both rules and logged 404s, and
 * RedirectService::match() prefers a site-specific rule over a catch-all —
 * so per-site redirects work today for a multi-SITE Craft install.
 *
 * What is NOT built yet is multi-DOMAIN matching, and it needs three things:
 *
 *   1. Matching on the full URL (scheme + host + path), not just the path.
 *      Craft's RedirectRule matches getFullPath(), so `example.com/about` and
 *      `example.de/about` are indistinguishable to a rule today. Retour
 *      offers full-URL matching for exactly this reason.
 *   2. A host column, or a convention for encoding the host in `source`, so a
 *      rule can say "only when the request came in on this domain".
 *   3. A site column in the CP table. It exists in the schema but is not yet
 *      exposed, because with one site there is nothing to choose.
 *
 * Worth doing at the same time as hreflang in the seo module, since both are
 * blocked on the same thing — this package having a real answer for a site
 * that serves more than one domain or language. Neither is urgent until a
 * client needs it, and both should be built together rather than half each.
 */

return [
    '*' => [
        // Create a 301 automatically when an entry's URI changes, from the
        // old URL to the new one.
        //
        // On by default because the costs are lopsided: an unwanted rule is
        // one row an editor can delete, while a missed rename is a dead URL
        // that nobody notices until traffic drops — and on a client site the
        // person renaming the page is rarely the person watching analytics.
        //
        // Drafts and revisions are excluded, so Craft's autosave doesn't mint
        // a rule every time someone pauses while typing a title.
        'autoRedirectOnUriChange' => true,

        // 404s not worth logging. Glob patterns, matched case-insensitively
        // against the path with no leading slash.
        //
        // A public site is probed constantly for wp-login.php, .env and
        // friends. Every one is a 404, and logging them buries the handful of
        // real broken links under scanner traffic while burning the row cap
        // that keeps the screen readable. None will ever become a redirect.
        //
        // Omit this key entirely to use RedirectService::DEFAULT_IGNORE_PATTERNS
        // (the list below). Set it to an empty array to log everything.
        'ignore404Patterns' => [
            'favicon.ico',
            'apple-touch-icon*',
            'robots.txt',
            'sitemap*.xml',
            'wp-*',
            '*.php',
            '*.env',
            '.well-known/*',
            'cgi-bin/*',
            'vendor/*',
        ],
    ],
];
