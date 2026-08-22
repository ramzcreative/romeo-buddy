<?php
/**
 * Security response headers
 *
 * Multi-environment config — Craft merges the '*' defaults with whichever key
 * matches the environment (dev/staging/production, from ENVIRONMENT — see
 * bootstrap.php / .env.example.*).
 *
 * ── Why this exists in PHP at all ──
 * Craft already sends frame-ancestors, X-Frame-Options and X-Content-Type-Options,
 * but vendor/craftcms/cms/src/web/Application.php:208 gates that block on
 * `if ($isCpRequest)`. Site requests get none of it, which is why the front-end
 * login form at general.php's loginPath is framable today.
 *
 * Production nginx does set some of these — but at the server, not in this
 * repo, so it's invisible to code review, differs per environment, and a new
 * client site on different hosting inherits nothing. Setting them in PHP means
 * every site that forks stables gets the same headers on every host.
 *
 * The module never overwrites a header that's already present, so anything the
 * server (or Craft) already sends still wins. This is a floor, not a mandate.
 *
 * ── What is deliberately NOT here ──
 * - Permissions-Policy has a native Craft setting (GeneralConfig::$permissionsPolicyHeader,
 *   applied to site requests at Application.php:192). Set it in config/general.php
 *   and the module leaves it alone; leave it null and the module sends the value
 *   below instead. One mechanism wins, never both.
 * - X-Powered-By is removed via general.php's `sendPoweredByHeader(false)`,
 *   which is Craft's own switch — no reason to fight it from here.
 * - X-XSS-Protection is intentionally absent. It's deprecated, no current
 *   browser implements it, and its legacy filter could itself introduce
 *   vulnerabilities. Production nginx currently sends `1; mode=block`; that's
 *   worth removing at the server rather than reproducing here.
 */

return [
    '*' => [
        /**
         * Who may frame this site.
         *
         * MUST stay 'self', never 'none'/DENY: Craft's Live Preview and the
         * entry preview panes load the front end in a same-origin iframe from
         * the CP. DENY breaks editing for every author, and it breaks it
         * silently — the pane just goes blank.
         *
         * Sent as BOTH `frame-ancestors` (CSP, current) and X-Frame-Options
         * (legacy). frame-ancestors wins wherever both are understood.
         */
        'frameAncestors' => "'self'",

        /**
         * Referrer-Policy.
         *
         * strict-origin-when-cross-origin: full URL on same-origin requests,
         * origin only when crossing to another HTTPS site, nothing at all on a
         * downgrade to HTTP. It's the modern browser default, but only for
         * browsers that have one — sending it explicitly covers the rest and
         * documents the intent.
         */
        'referrerPolicy' => 'strict-origin-when-cross-origin',

        /**
         * Permissions-Policy — features this site never uses, denied outright.
         *
         * Only applied when general.php doesn't set permissionsPolicyHeader
         * (see the note above). An empty allowlist `()` means "no origin,
         * including this one".
         *
         * If a site ever adds a store, a map that needs location, or video
         * calling, the matching entry has to come out of this list or the
         * feature silently does nothing.
         */
        'permissionsPolicy' => 'accelerometer=(), autoplay=(self), camera=(), display-capture=(), encrypted-media=(), fullscreen=(self), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=(), picture-in-picture=(self), usb=()',

        /**
         * HSTS — force HTTPS for return visits.
         *
         * Only ever sent over an already-secure connection. Sending it over
         * plain HTTP is meaningless (the spec says ignore it) and on local dev
         * it would be actively harmful.
         *
         * includeSubDomains and preload both default OFF, deliberately. They
         * are the two settings that are genuinely hard to undo: a browser
         * caches HSTS for maxAge regardless of what you send later, so
         * switching them on for a domain with any subdomain not yet serving
         * HTTPS takes that subdomain down for up to a year, with no
         * server-side fix. Turn them on per-site once every subdomain is
         * confirmed HTTPS — not as a default inherited by every fork.
         */
        'hsts' => [
            'enabled' => true,
            'maxAge' => 31536000, // 1 year
            'includeSubDomains' => false,
            'preload' => false,
        ],

        /**
         * Content-Security-Policy — phase 2, off until the origin list below
         * is finished and proven.
         *
         * Notes for when it's switched on, both learned the hard way:
         *
         * 1. Multiple CSP policies are enforced INDEPENDENTLY and a resource
         *    must satisfy every one of them. scaffold.twig already ships a
         *    <meta> CSP carrying frame-src. So this policy needs its own
         *    explicit frame-src — a `default-src 'self'` here with no frame-src
         *    falls back to 'self' for frames and vetoes the video embeds the
         *    meta tag permits.
         *
         * 2. Plyr's iframe is youtube-NOCOOKIE (videoPlayer.ts sets
         *    `youtube: { noCookie: true }`), but the API script it injects is
         *    plain www.youtube.com. Allowlisting only the nocookie host — the
         *    obvious move, since that's all the meta tag lists — loads the
         *    iframe and then fails to play.
         *
         * The four origins Plyr actually needs:
         *    frame-src    https://www.youtube-nocookie.com  https://player.vimeo.com
         *    script-src   https://www.youtube.com/iframe_api
         *                 https://player.vimeo.com/api/player.js
         *    img-src      https://i.ytimg.com
         *    connect-src  https://vimeo.com
         *
         * reportOnly ships first so a wrong policy reports instead of breaking
         * the site. Flip it only after a real browsing session produces no
         * violations.
         */
        'csp' => [
            'enabled' => false,
            'reportOnly' => true,
            'directives' => [],
        ],
    ],

    'dev' => [
        // Local dev is plain HTTP, so this would never fire anyway — off
        // explicitly so nobody has to work that out.
        'hsts' => [
            'enabled' => false,
        ],
    ],
];
