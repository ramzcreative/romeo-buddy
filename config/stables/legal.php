<?php
/**
 * Legal pages the theme links to.
 *
 * The cookie-consent banner used to hardcode `section('pages')` and
 * `slug('privacy-policy')`, which is a content-model assumption a boilerplate
 * has no business making — a site whose privacy page lives in another section
 * or under another slug lost the link silently, with no error to notice.
 *
 * Either key set to null drops the link rather than guessing.
 */

return [
    'privacyPolicy' => [
        'section' => 'pages',
        'slug' => 'privacy-policy',
    ],
];
