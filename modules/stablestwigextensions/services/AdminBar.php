<?php

namespace modules\stablestwigextensions\services;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use modules\themepicker\fields\ThemeOverride;
use modules\themepicker\services\ThemeRegistry;

/**
 * Data + actions for the front-end admin bar (see
 * themes/_base/templates/_partials/adminBar.twig, and
 * controllers/AdminBarController.php for the two actions it posts to).
 * Kept out of the Twig extension itself so that class stays a thin
 * registry, matching ItemResolver/BlockFieldCss's own split.
 */
class AdminBar
{
    /**
     * Session key for a non-persistent page-theme preview override. Never
     * written to the entry's own field — clearing the session (or logging
     * out) reverts to whatever that field says. See scaffold.twig's
     * pageThemeHandle resolution.
     *
     * Deliberately separate from modules\themepicker's own site-theme
     * preview (ThemeRegistry::getPreviewThemeHandle()), which is
     * DB-persisted and sitewide — a page theme is per-entry, so a session
     * value (this browser tab's login, not the whole site) is the right
     * scope.
     */
    public const PREVIEW_SESSION_KEY = 'adminBar.pageThemePreview';

    /**
     * Session key for the inline-editing show/hide toggle. Defaults ON —
     * the INVERSE of the page-theme preview above, which defaults off —
     * so absence of the key means "enabled" and only an explicit `false`
     * turns it off. This is the admin bar's own concern: it owns nothing
     * beyond this one flag (see inlineEditingEnabled()) — the separate
     * inline-editing engine (not this module) is what actually reads it
     * to decide whether to render anything.
     */
    public const INLINE_EDIT_SESSION_KEY = 'adminBar.inlineEditing';

    /**
     * The ThemeOverride field on this entry's own layout that offers page
     * themes, if any. Discovery is by field TYPE, mirroring
     * PageThemeResolver::ownHandleFor() in craft-modules — a site's field
     * for this can be named anything (`pageTheme` on the generic `page`
     * entry type, `themeOverride` on `landingPage`, and so on), so this
     * never assumes a handle.
     */
    private function pageThemeField(Entry $entry): ?ThemeOverride
    {
        $layout = $entry->getFieldLayout();

        if ($layout === null) {
            return null;
        }

        foreach ($layout->getCustomFields() as $field) {
            if ($field instanceof ThemeOverride && $field->offersPageThemes()) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Page themes available to preview on this entry, or [] if it has no
     * page-theme-capable field at all.
     *
     * @return array<int, array{handle: string, name: string}>
     */
    public function pageThemeOptions(?Entry $entry): array
    {
        if (!$entry || !$this->pageThemeField($entry)) {
            return [];
        }

        $options = [];

        foreach ((new ThemeRegistry())->getThemes() as $handle => $theme) {
            if (($theme['type'] ?? ThemeRegistry::TYPE_SITE) === ThemeRegistry::TYPE_PAGE) {
                $options[] = ['handle' => $handle, 'name' => $theme['name']];
            }
        }

        return $options;
    }

    /**
     * The session-only preview override, as {handle, name} — or null if
     * none is set, or the one that was set no longer names a real page
     * theme (its folder was removed after the preview was set).
     *
     * @return array{handle: string, name: string}|null
     */
    public function previewedPageTheme(): ?array
    {
        $handle = Craft::$app->getSession()->get(self::PREVIEW_SESSION_KEY);

        if (!is_string($handle) || $handle === '') {
            return null;
        }

        $theme = (new ThemeRegistry())->getThemes()[$handle] ?? null;

        if (!$theme || ($theme['type'] ?? ThemeRegistry::TYPE_SITE) !== ThemeRegistry::TYPE_PAGE) {
            return null;
        }

        return ['handle' => $handle, 'name' => $theme['name']];
    }

    /**
     * Whether the inline-editing show/hide toggle is currently on.
     * Session-only, same posture as the page-theme preview above — never
     * persisted anywhere else. Absence of the session key means on; only
     * an explicit `false` (set by actionDisableInlineEditing()) turns it
     * off, which is why this is a plain boolean read, not a nullable one.
     */
    public function inlineEditingEnabled(): bool
    {
        $value = Craft::$app->getSession()->get(self::INLINE_EDIT_SESSION_KEY);

        return $value === null || (bool)$value;
    }

    /**
     * The same entry as it's propagated to every OTHER site, each with
     * that site's own front-end URL — for the admin bar's site switcher.
     * Skips a site the entry isn't propagated to, one the user can't view,
     * or one where it has no URL (a section with no template).
     *
     * @return array<int, array{id: int, name: string, url: string}>
     */
    public function siteAlternates(Entry $entry, User $user): array
    {
        $currentSiteId = Craft::$app->getSites()->getCurrentSite()->id;
        $alternates = [];

        // getLocalized() resolves the same element for every OTHER site
        // it's propagated to — the same call SeoResolver::getAlternateUrls()
        // uses for hreflang, and returns [] outright on a single-site
        // install, so this degrades to nothing extra to loop over.
        foreach ($entry->getLocalized()->status(null)->all() as $altEntry) {
            if ($altEntry->siteId === $currentSiteId) {
                continue;
            }

            if ($altEntry->canView($user) && $altEntry->url) {
                $alternates[] = [
                    'id' => $altEntry->siteId,
                    'name' => $altEntry->getSite()->name,
                    'url' => $altEntry->url,
                ];
            }
        }

        return $alternates;
    }

    /**
     * Custom shortcuts from config/stables/admin-bar.php, filtered to ones
     * the current user is allowed to see.
     *
     * @return array<int, array{label: string, url: string}>
     */
    public function customLinks(?User $user): array
    {
        $config = \modules\support\Config::get('admin-bar');
        $links = $config['links'] ?? [];

        $allowed = [];

        foreach ($links as $link) {
            $permission = $link['permission'] ?? null;

            if ($permission && (!$user || !$user->can($permission))) {
                continue;
            }

            $allowed[] = ['label' => $link['label'], 'url' => $link['url']];
        }

        return $allowed;
    }

    /**
     * Total queue jobs (pending + delayed + reserved), or null if the
     * count couldn't be read. This is read-only front-end chrome behind a
     * permission check (see the Twig partial) — never worth a 500 over.
     */
    public function queueTotal(): ?int
    {
        try {
            return Craft::$app->getQueue()->getTotalJobs();
        } catch (\Throwable) {
            return null;
        }
    }
}
