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
 * Multi-domain
 * ---------------------------------------------------------------------------
 * Works, with nothing to configure here. Each domain is a Craft site, and
 * Craft resolves the site from the request host before the redirect code
 * runs — so a rule's `siteId` already scopes it to a domain. Set it in the CP
 * (the Site column appears only on a multi-site install); leave it on "All
 * sites" and a relative destination resolves against whichever domain the
 * request came in on.
 *
 * Full-URL matching and a host column were both scoped for this and both
 * turned out to be unnecessary.
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
        // Deliberately not set, so this site inherits
        // RedirectService::DEFAULT_IGNORE_PATTERNS from craft-modules and
        // picks up every widening of that list on the next module update.
        // Setting the key REPLACES the defaults rather than extending them,
        // which is how a site quietly stops inheriting — so only set it to
        // log something the defaults hide, or to an empty array to log
        // everything while debugging:
        //
        //   'ignore404Patterns' => [],
    ],
];
