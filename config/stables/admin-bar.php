<?php
/**
 * Admin bar configuration
 *
 * Multi-environment config — Craft merges the '*' defaults with whichever
 * key matches the environment (dev/staging/production, from ENVIRONMENT —
 * see bootstrap.php / .env.example.*).
 *
 * `links` are extra shortcuts shown in the front-end admin bar alongside
 * the built-in Dashboard/Settings/Utilities/Logout (see
 * modules/stablestwigextensions/services/AdminBar.php::customLinks() and
 * themes/_base/templates/_partials/adminBar.twig). Each entry:
 *   - label: shown text
 *   - url: an absolute URL
 *   - permission (optional): a Craft user permission the current user must
 *     have for the link to show; omit to show it to any logged-in user
 */

return [
    '*' => [
        'links' => [
            // ['label' => 'Docs', 'url' => 'https://example.com/docs'],
        ],
    ],
];
