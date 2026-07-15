# Romeo & Buddy

Built on the RAMZ Creative Craft starter/boilerplate.

## Authors
RAMZ CREATIVE LLC

## Getting started
```
npm install
```

## Run vite locally
```
npm run dev 
```

## Pushing CSS and JS
- [ ] before pushing to server you must run 'npm run build' locally to build out the dist files
- [ ] TODO: find solution to auto generate on deployment (currently pushing code the oldschool way)
```
npm run build
```

## Icons
Icons for the Icon Picker field live in `themes/_base/src/icons/<set>/*.svg` — each top-level subfolder (e.g. `ui/`) becomes a named "set" shown as a tab in the CP picker. In dev, the field reads straight from that source folder (`config/iconpicker.php`'s `dev` override) so new icons show up immediately.

For staging/production, optimize and copy the set into the built output the field reads from there (`web/dist/assets/icons/`):
```
npm run svg-build
```
This also runs automatically as part of `npm run build:themes`.

**Rendering an icon on the front end** — use the `renderIcon()` Twig function (registered by `modules/iconpicker`), passing the field's `"set/icon-name"` value:
```twig
{{ renderIcon(entry.icon) }}
```
It outputs the sanitized, inlined `<svg>` markup directly (already marked safe, no `|raw` needed), and returns an empty string if the field is empty or the icon no longer exists — safe to call unconditionally. Style the icon by targeting the `svg` on a wrapper, e.g.:
```twig
<span class="icon">{{ renderIcon(entry.icon) }}</span>
```
```css
.icon svg { width: 1em; height: 1em; fill: currentColor; }
```
`renderIcon()` is only available in front-end/site templates, not CP templates.

## Themes
The site supports multiple themes, each living in its own folder under `/themes` (e.g. `/themes/default`, `/themes/coastal`), with its own `templates/`, `src/` (CSS + JS), and a `theme.json` manifest (`name` + `thumbnail`).

Shared CSS/JS lives in `/themes/_base/src/` — this is where the bulk of the styling and all the shared JS (Swiper, Lenis, animations, components) actually live. A theme's own `src/` only needs to contain what's *different* about it:
- `src/css/base/_colors.pcss` — its own palette (the `$colors` map every other stylesheet reads via CSS custom properties)
- `src/css/main.pcss` / `critical.pcss` — two-line files that import the theme's own `_colors.pcss` first, then delegate to `_base/src/css/main.pcss` / `critical.pcss`
- `src/js/main.js` — a one-line file that imports `_base/src/js/main.js` (replace with real theme-specific JS if a theme ever needs to diverge)
- `src/js/critical.js` / `maincss.js` — tiny files that just import the theme's own compiled CSS entry via the `@src` alias (already theme-scoped, nothing to change)

**Colors are the only built-in override point right now.** `_base/src/css/main.pcss` imports its other partials (`blocks/*.pcss`, `includes/*.pcss`, etc.) directly from `_base` — there's no automatic "check the theme folder first, fall back to `_base`" resolution for those yet. To override something beyond colors today, edit the shared file in `_base` directly (affects all themes) or fork the specific `@import` line in that theme's own `main.pcss`/`critical.pcss` to point at a local copy instead. A more general per-file override mechanism (theme file wins if present, else fall back to `_base`) is a possible future improvement, not yet built.

**Templates *do* have that automatic fallback, unlike CSS/JS.** `modules/themepicker` sets the active theme's `themes/<handle>/templates/` as Craft's primary template path (`View::setTemplatesPath()`), then separately registers `themes/_base/templates` as a site template root under the empty-string prefix, which matches every template name. Craft always checks the primary path first and only falls through to a registered root if the template isn't found there (`craft\web\View::resolveTemplate()`) — so a theme only needs to contain the templates that actually differ from `_base`; anything it doesn't override resolves from `_base/templates` automatically. This only applies to site/front-end requests — CP template roots (the icon picker field's input template, the theme picker page) are registered separately and don't participate in this fallback.

**Per-theme static assets** (logo, favicons) live in `web/assets/themes/<handle>/` — e.g. `web/assets/themes/default/logo.svg`, `web/assets/themes/default/favicon.ico`. Every theme ends up with a complete set of these on disk; there's no *runtime* `_base` fallback the way there is for templates. Referenced from:
- Twig, via the `@webrootTheme` alias (set in `config/general.php`, follows the active theme automatically) — e.g. `svg("@webrootTheme/logo.svg")`, used as the header/footer logo fallback when no CMS logo asset is set
- `scaffold.twig`'s favicon `<link>` tags, via `/assets/themes/{{ activeThemeHandle }}/favicon.ico` etc.
- CSS `url()`, via a `$theme` postcss variable (`postcss.config.js` is a factory taking the theme handle, called from `vite.config.js` with the same `THEME` const used for the JS/CSS entry points) — e.g. `url('/assets/themes/$theme/logo.svg')` in `media.pcss`

**Both logo and favicons are generated**, not hand-copied — same build-time fallback pattern, different scripts since a logo is used as-is (a straight file copy) while favicons need resizing/packing into a `.ico`:

- **Logo**: `npm run logo-build` (`scripts/build-logos.mjs`) copies `web/assets/themes/<handle>/logo.svg` from a source SVG, resolved per theme in this order: `themes/<handle>/src/logo.svg` (a theme-specific logo) → `themes/_base/src/logo.svg` (the shared default).
- **Favicons**: `npm run favicon-build` (`scripts/build-favicons.mjs`) renders `favicon.ico` + `favicon-16x16.png` + `favicon-32x32.png` from a source PNG, resolved the same way: `themes/<handle>/src/favicon.png` → `themes/_base/src/favicon.png`.

Both run as part of `npm run build:themes`, and both auto-discover themes the same way (`scripts/lib/discover-themes.mjs` — any `themes/*/theme.json`, same rule `modules/themepicker` uses).

A palette-only theme variant has no reason to ship its own `logo.svg`/`favicon.png` (recoloring a handful of pixels isn't worth a distinct logo or icon), so it just inherits the shared default. A theme that's a genuine redesign drops its own file in under `themes/<handle>/src/` and the build picks it up automatically — no script changes needed. The *build-time source* has this fallback; the *output* is still fully materialized per theme on disk in `web/assets/themes/<handle>/`, so nothing changes for what Twig/CSS reference above.

Assets that *aren't* theme-specific (social icons, generic UI graphics) stay where they've always been, at `web/assets/graphics/`, `web/assets/social/`, etc., referenced via the existing `@webroot/assets/...` alias — unaffected by any of the above.

The active theme is picked in the control panel under **Themes** (`/theme-picker`), or via console:
```
php craft theme-picker/themes/list
php craft theme-picker/themes/activate <handle>
```

- **Production/staging** (`CRAFT_DEV_MODE=false`): switching themes takes effect immediately, no rebuild or restart needed — it serves the pre-built `web/dist/<theme>/` bundle for whichever theme is active. Run `npm run build:themes` (or `npm run build:default` / `npm run build:coastal`) once to (re)generate those bundles after any theme changes.
- **Local dev** (`CRAFT_DEV_MODE=true`, Vite dev server): the dev server is pinned to one theme's source folder for its whole process lifetime. If you switch the active theme in the CP while developing locally, restart the dev server with the matching script so its assets/HMR match:
```
npm run dev:default
npm run dev:coastal
```
Forgetting to restart is the most common cause of "wrong theme colors" locally — it only ever affects local dev mode, not deployed environments.

### Adding a new theme
No PHP/module changes needed — the picker scans `/themes/*/theme.json` automatically.

For a **palette-only variant** (the common case): copy an existing theme's *files*, not its content — `cp -r themes/coastal themes/<handle>` gives you the right minimal skeleton (its own `_colors.pcss` + thin `main.pcss`/`critical.pcss`/`main.js`/`critical.js`/`maincss.js` + `templates/`), then just edit `src/css/base/_colors.pcss` with the new palette.

For anything more involved:
1. Set up `themes/<handle>/src/` with its own `_colors.pcss`, and thin `main.pcss` / `critical.pcss` / `main.js` files that import `_base`'s equivalents (copy the pattern from `themes/coastal/src/`)
2. Edit `themes/<handle>/theme.json` and set `"name"` (shown in the CP picker)
3. Add `themes/<handle>/templates/` files only for what actually needs to differ from `_base` — templates fall back to `themes/_base/templates` automatically for anything not overridden (see above), so this doesn't need to be a full copy
4. Add `build:<handle>` and `dev:<handle>` scripts to `package.json`, mirroring the `default`/`coastal` ones (swap in the new handle), and add the build script to the `build:themes` chain
5. Run `npm run build:<handle>` once so production/staging has a dist bundle to serve
6. Activate it (`php craft theme-picker/themes/activate <handle>`), screenshot the homepage, and save it as `themes/<handle>/thumbnail.png`
7. Logo and favicon need nothing by default — `logo-build` and `favicon-build` (both part of `build:themes`) auto-discover the new theme and generate its logo/favicon from the shared `themes/_base/src/logo.svg` / `favicon.png`. Only add a `themes/<handle>/src/logo.svg` or `favicon.png` if this theme is enough of a redesign to warrant its own.
