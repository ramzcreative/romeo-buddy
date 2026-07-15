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

## Themes
The site supports multiple themes, each living in its own folder under `/themes` (e.g. `/themes/default`, `/themes/coastal`), with its own `templates/`, `src/` (CSS + JS), and a `theme.json` manifest (`name` + `thumbnail`).

Shared CSS/JS lives in `/themes/_base/src/` — this is where the bulk of the styling and all the shared JS (Swiper, Lenis, animations, components) actually live. A theme's own `src/` only needs to contain what's *different* about it:
- `src/css/base/_colors.pcss` — its own palette (the `$colors` map every other stylesheet reads via CSS custom properties)
- `src/css/main.pcss` / `critical.pcss` — two-line files that import the theme's own `_colors.pcss` first, then delegate to `_base/src/css/main.pcss` / `critical.pcss`
- `src/js/main.js` — a one-line file that imports `_base/src/js/main.js` (replace with real theme-specific JS if a theme ever needs to diverge)
- `src/js/critical.js` / `maincss.js` — tiny files that just import the theme's own compiled CSS entry via the `@src` alias (already theme-scoped, nothing to change)

**Colors are the only built-in override point right now.** `_base/src/css/main.pcss` imports its other partials (`blocks/*.pcss`, `includes/*.pcss`, etc.) directly from `_base` — there's no automatic "check the theme folder first, fall back to `_base`" resolution for those yet. To override something beyond colors today, edit the shared file in `_base` directly (affects all themes) or fork the specific `@import` line in that theme's own `main.pcss`/`critical.pcss` to point at a local copy instead. A more general per-file override mechanism (theme file wins if present, else fall back to `_base`) is a possible future improvement, not yet built.

**Per-theme static assets** (logo, favicons) live in `web/assets/themes/<handle>/` — e.g. `web/assets/themes/default/logo.svg`, `web/assets/themes/default/favicon.ico`. Unlike `src/`, there's no `_base` fallback here: every theme is expected to have its own complete set (copy another theme's folder as a starting point, same as `templates/`). Referenced from:
- Twig, via the `@webrootTheme` alias (set in `config/general.php`, follows the active theme automatically) — e.g. `svg("@webrootTheme/logo.svg")`, used as the header/footer logo fallback when no CMS logo asset is set
- `scaffold.twig`'s favicon `<link>` tags, via `/assets/themes/{{ activeThemeHandle }}/favicon.ico` etc.
- CSS `url()`, via a `$theme` postcss variable (`postcss.config.js` is a factory taking the theme handle, called from `vite.config.js` with the same `THEME` const used for the JS/CSS entry points) — e.g. `url('/assets/themes/$theme/logo.svg')` in `media.pcss`

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
3. Customize `themes/<handle>/templates/` as needed (full copy of an existing theme's `templates/` folder, then edit) — templates aren't part of the `_base` system yet, so they're still a full copy per theme
4. Add `build:<handle>` and `dev:<handle>` scripts to `package.json`, mirroring the `default`/`coastal` ones (swap in the new handle), and add the build script to the `build:themes` chain
5. Run `npm run build:<handle>` once so production/staging has a dist bundle to serve
6. Activate it (`php craft theme-picker/themes/activate <handle>`), screenshot the homepage, and save it as `themes/<handle>/thumbnail.png`
