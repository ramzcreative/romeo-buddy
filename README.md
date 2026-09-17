# Romeo & Buddy

Built on the RAMZ Creative Craft starter/boilerplate.

## Authors
RAMZ CREATIVE LLC

## Getting started
Motion+ installs from its private registry. Get a token from https://motion.dev/dashboard/tokens and export it in your shell profile (npm doesn't read `.env`):
```
export MOTION_TOKEN=your-token
```
Then:
```
npm install
```

## Run vite locally
```
npm run dev 
```

## Pushing to production
- [ ] before pushing to server you must run `npm run build` locally to build out the dist files
- [ ] TODO: find solution to auto generate on deployment (currently pushing code the oldschool way)
```
npm run build
```
This is the one command for everything a site needs to go live: one Vite build of every site theme's CSS plus the shared JS into `web/dist/site/`, optimized icons (`svg-build`), and each theme's favicons and logo (`favicon-build`, `logo-build`) — see [Themes](#themes) below. Staging and production are identical here: both set `CRAFT_DEV_MODE=false`, so there's no separate "staging build" — the same output serves either, only the deployed `.env` differs.

**Pushing is not deploying.** The server pulls when someone clicks Deploy in Forge — a push on its own changes nothing on the live site. The deploy script puts the site in maintenance mode (`php craft off`), runs `composer install`, `php craft up` (project config, then content migrations) and brings it back (`php craft on`).

## Boilerplate updates on this site
This site predates the site launcher, so it has no `scripts/boilerplate.sh` and no `upstream` remote — its `stables` remote is the local clone, for reading. Taking a boilerplate update here is a deliberate port: read what changed in `stables`, copy the shared files (`themes/_base`, `modules/stablestwigextensions`, `config/stables`, `scripts`, `docs`), and write the matching content migration in this repo, because `project.yaml` can never be copied — the same field handle has a different UID in each site and content is keyed by field-layout element UID.

`stables/docs/romeo-buddy-port-plan.md` is the worked example: the September 2026 port that brought this site level with the boilerplate, in nine phases, each verified against production before the next began. Its rules are worth keeping: try every migration against a scratch database first (`scripts/scratch-db.sh`), make it survive either deploy order, and compare rendered pages before and after.

## Icons
Icons for the Icon Picker field live in `themes/_base/src/icons/<set>/*.svg` — each top-level subfolder (`ui/`, `base/`, and this site's own `all/`) becomes a named "set" shown as a tab in the CP picker. In dev, the field reads straight from that source folder (`config/stables/iconpicker.php`'s `dev` override) so new icons show up immediately. `/dev/icons` (admin-only) renders every set.

**A theme can ship its own icons** in `themes/<handle>/src/icons/<set>/`. That directory replaces Base's entirely for that theme, so copy in any of Base's sets the theme still wants. No theme here does.

Every icon except Base's `ui` set and this site's own `all` set must follow the drawing rules in `themes/_base/src/CLAUDE.md` § Icons (a 24-unit box, longest side exactly 20 and centred, `currentColor` only, lowercase names). **`svg-build` checks them and fails the build**, naming each icon and what's wrong, before writing anything. `all` is exempt because it came with this site and predates the rules (`scripts/build-icons.mjs`'s `EXEMPT`).

For staging/production, optimize and copy every library into the built output the field reads from there (`web/dist/assets/icons/`, and `web/dist/assets/theme-icons/<handle>/` per theme):
```
npm run svg-build
```
This also runs automatically as part of `npm run build`.

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
The site supports multiple themes, each living in its own folder under `/themes` (`/themes/default`, `/themes/christmas`), with its own `templates/`, `src/` (CSS + JS), and a `theme.json` manifest (`name` + `thumbnail`).

There are **two kinds**, distinguished by `theme.json`'s `type` (absent means `site`):

- **Site theme** (`"type": "site"`) — everything described below. Templates, CSS and JS of its own, built by Vite, and eligible to be the site's active theme. `default` is this site's, and holds Romeo & Buddy's entire look.
- **Theme variant** (`"type": "page"`, a "page theme" in code) — colours and marks only: a `theme.json` and a `src/css/generated/colors-generated.pcss`, and nothing else. It is never built by Vite and can never be the site's theme. An editor picks one on a page's **Theme Variant** field, or switches one on sitewide (now or scheduled) under **Themes**, and pages render in the active site theme's bundle with the variant's colours layered over it. `christmas` is one. See [Theme variants](#theme-variants) below.

Shared CSS/JS lives in `/themes/_base/src/` — this is where the bulk of the styling and all the shared JS (Swiper, Lenis, animations, components) actually live. A theme's own `src/` only needs to contain what's *different* about it:
- `src/css/generated/` — this theme's own machine-generated output from `craft-modules/modules/themedesigner`: `colors-generated.pcss` (its Color System roles, the CSS custom properties every other stylesheet reads), `buttons-generated.pcss`/`buttons-settings.json`, and `backgrounds-generated.pcss` (this theme's own `.bg--{role}` classes only)
- `src/css/main.pcss` / `critical.pcss` — the theme's entries. They import its own `generated/colors-generated.pcss` into `layer(tokens)` first, then `_base`'s **`main-core.pcss` / `critical-core.pcss`** — Base's machine (tokens, reset, utilities, the shared vocabulary), which carries no block CSS — and then name the block and include files the theme wants, Base's by path and its own beside them. `critical.pcss` also imports this theme's other `generated/` files, buttons and backgrounds included, so they're styled on the first frame, and its `fonts-generated.pcss`: `@font-face` for only the families the theme uses, from the one font pool on the Theme Designer's Fonts tab (files in `web/fonts/`, named with a content hash). A theme built before the core split (or one that wants Base's whole look) imports `_base/src/css/main.pcss` / `critical.pcss` instead — see `docs/base-layer-spec.md`
- `src/js/critical.js` / `maincss.js` — tiny files that just import the theme's own compiled CSS entry via the `@src` alias (it resolves to the importing theme's own `src/`, nothing to change)

Optionally, a theme also has:
- `templates/` forks and `src/css/blocks/` — this theme's own version of a block: the template copied to the same path, its CSS imported from `critical.pcss` if the block can sit above the fold. A theme on the core entries keeps the plain class name and imports its file in `layer(components)` in place of Base's; one that loads Base's full block CSS gives the fork its own BEM name in `layer(overrides)`
- `src/js/theme.js` — loaded as a module after `_base/src/js/main.js`, only on that theme
- `"js": "replace"` in `theme.json` with its own `src/js/main.js` — loaded instead of Base's, for a theme that replaces Base's JS entirely

Every theme loads `_base/src/js/main.js` unless it declares `"js": "replace"`. How forks work, what they must keep, and where a change belongs: [`themes/CLAUDE.md` § The theme layer](themes/CLAUDE.md#the-theme-layer).

**Templates *do* have that automatic fallback, unlike CSS/JS.** `modules/themepicker` sets the active theme's `themes/<handle>/templates/` as Craft's primary template path (`View::setTemplatesPath()`), then separately registers `themes/_base/templates` as a site template root under the empty-string prefix, which matches every template name. Craft always checks the primary path first and only falls through to a registered root if the template isn't found there (`craft\web\View::resolveTemplate()`) — so a theme only needs to contain the templates that actually differ from `_base`; anything it doesn't override resolves from `_base/templates` automatically. This only applies to site/front-end requests — CP template roots (the icon picker field's input template, the theme picker page) are registered separately and don't participate in this fallback.

**Per-theme static assets** (logo, favicons) live in `web/assets/themes/<handle>/` — e.g. `web/assets/themes/default/logo.svg`, `web/assets/themes/default/favicon.ico`. Every theme ends up with a complete set of these on disk; there's no *runtime* `_base` fallback the way there is for templates. Referenced from:
- Twig, via the `@webrootTheme` alias (set in `config/general.php`, follows the active theme automatically) — e.g. `svg("@webrootTheme/logo.svg")`, used as the header/footer logo fallback when no CMS logo asset is set
- `scaffold.twig`'s favicon `<link>` tags, via `/assets/themes/{{ activeThemeHandle }}/favicon.ico` etc.
- CSS, via the `--logo-mask-url` custom property: `scaffold.twig` sets it inline to `/assets/themes/<brandThemeHandle>/logo.svg`, and `media.pcss` reads `var(--logo-mask-url)`, so the compiled CSS names no theme

**Both logo and favicons are generated**, not hand-copied — same build-time fallback pattern, different scripts since a logo is used as-is (a straight file copy) while favicons need resizing/packing into a `.ico`:

- **Logo**: `npm run logo-build` (`scripts/build-logos.mjs`) copies `web/assets/themes/<handle>/logo.svg` from a source SVG, resolved per theme in this order: `themes/<handle>/src/logo.svg` (a theme-specific logo) → `themes/_base/src/logo.svg` (the shared default).
- **Favicons**: `npm run favicon-build` (`scripts/build-favicons.mjs`) renders `favicon.ico` + `favicon-16x16.png` + `favicon-32x32.png` from a source PNG, resolved the same way: `themes/<handle>/src/favicon.png` → `themes/_base/src/favicon.png`.

Both run as part of `npm run build`, and both auto-discover themes the same way (`scripts/lib/discover-themes.mjs` — any `themes/*/theme.json`, same rule `modules/themepicker` uses).

A palette-only theme variant has no reason to ship its own `logo.svg`/`favicon.png` (recoloring a handful of pixels isn't worth a distinct logo or icon), so it just inherits the shared default. A theme that's a genuine redesign drops its own file in under `themes/<handle>/src/` and the build picks it up automatically — no script changes needed. The *build-time source* has this fallback; the *output* is still fully materialized per theme on disk in `web/assets/themes/<handle>/`, so nothing changes for what Twig/CSS reference above.

Assets that *aren't* theme-specific (social icons, generic UI graphics) stay where they've always been, at `web/assets/graphics/`, `web/assets/social/`, etc., referenced via the existing `@webroot/assets/...` alias — unaffected by any of the above.

The active theme is picked in the control panel under **Themes** (`/theme-picker`), or via console:
```
php craft theme-picker/themes/list
php craft theme-picker/themes/activate <handle> [--force]
```

Switching first lists what changes (sections and blocks only the current theme has templates for, blocks and layouts the new theme doesn't offer). In the CP that's a confirmation dialog; the console stops with exit 1 until you add `--force`. Nothing is deleted either way. See [`docs/site-types-spec.md`](docs/site-types-spec.md) §4.

- **Production/staging** (`CRAFT_DEV_MODE=false`): switching themes takes effect immediately, no rebuild or restart needed — every site theme's CSS is already in the one build in `web/dist/site/`. Run `npm run build` after any theme change. A theme that isn't in the build can't be activated, and if the stored theme's CSS goes missing (a deploy without its build), pages fall back to `default`, then the first built site theme, and the fallback is logged.
- **Local dev** (`CRAFT_DEV_MODE=true`): one `npm run dev` serves every theme, so switching the active theme in the CP shows on the next reload.

### Theme variants
A Theme variant (`"type": "page"`; code still says page theme) recolours pages, or the whole site, on top of whichever site theme is active. It has no bundle of its own — the page still loads the site theme's CSS and JS — so it costs no extra request and needs no build for its colours. `christmas` is this site's, for the season.

**Which one a page wears.** The admin bar preview first, then a sitewide variant (unless the page's **Keep this page's look during sitewide variants** toggle is on, or the sitewide one doesn't override page variants and the page has its own), then the page's own, then none. The sitewide slot and its schedule live in `theme_settings` and `theme_variant_schedule`; switch and schedule them under **Themes**, or with `php craft theme-picker/themes/variant|schedule-variant|variants|unschedule-variant`. Full rules: craft-modules `docs/theme-variants-spec.md`.

**How it renders.** `modules/themepicker`'s `PageThemeResolver` reads the page's variant off the entry *being rendered* (not from the request URI, which is what makes Live Preview re-tint a draft before it's saved), parses that theme's `colors-generated.pcss`, and `scaffold.twig` inlines the result as an **unlayered** `<style data-page-theme>`. A theme's colour tokens are imported into `layer(tokens)`, the lowest layer, so an unlayered `:root` wins over both the inlined critical CSS and the async main stylesheet regardless of load order, with no `!important` anywhere.

Only lines matching `--token: <6-digit hex | var(--token)>` are carried over, rebuilt into fresh CSS — comments and anything else in the file are dropped, never passed through.

The overlay, favicons, the `theme-color` meta, the header/footer logos and effects all follow one answer, `brandThemeHandle()` (`scaffold.twig` / `index.twig`); `@webrootTheme` and every Vite path stay pointed at the **bundle** theme, which is the site theme.

**Effects.** A variant can switch on `"effects": ["snowfall"]` (Theme Designer → the variant's General tab). `scaffold.twig` writes `<snow-fall>` only when the variant on the page lists it, and `main.js` imports `Components/effects/snowFall.ts` only when that element exists. It's off under reduced motion, pauses with the tab hidden, and has a Pause button. Flakes are white by default (`--snowfall-color`, `--snowfall-opacity`), so they show over dark and coloured sections.

**Creating one:** Theme Designer → *New theme* → **Theme variant**, or the *Create new theme from X* modal on any site theme's General tab. It scaffolds exactly two files. The Vite build skips it — it has no bundle to build.

**Deploying one:** its colours go live as soon as `themes/<handle>/src/css/generated/colors-generated.pcss` is committed and deployed. Only its logo/favicon need `npm run build` (`logo-build`/`favicon-build` discover it like any other theme and fall back to `_base`'s marks when it ships none of its own).

**What it can't do:** be the site's active theme (the picker and `themes/activate` both refuse it), be a source for a new theme, go in the Theme Library, or change type, spacing, fonts, components, the site-wide colour palette, or the Base relationship. Those tabs still appear in the Designer for a variant, showing the site theme's values read-only.

### Removing a theme
Delete it from the Theme Designer's **Remove** button. `default` can't be removed — the site needs it as a fallback, and here it *is* the site.

**Remove deletes:** `themes/<handle>/`, `web/assets/themes/<handle>/`, and that theme's entries in `config/stables/themes/generated/color-chips.php` and `.../colors.php`. A site theme's compiled CSS stays in `web/dist/site/` until the next `npm run build`, which drops it.

**You still have to do by hand — site themes only:**
1. `config/stables/themes/colors.php` — delete its `themes.<handle>` override block, if it has one.
2. Any prose mentioning it (this README, `themes/CLAUDE.md`).
3. Run `npm run build`.

**A Theme variant needs none of the above** — no compiled CSS, no thumbnail, so Remove is the whole job.

**Nothing dangles dangerously if you miss a step.** A stored theme handle that no longer resolves degrades rather than breaks: the sitewide active theme falls back to `default` (`ThemeRegistry::getActiveThemeHandle()` checks the handle still names a real *site* theme), and a page whose Theme Variant was deleted simply renders in the site theme's colours, with the stale value flagged the next time an editor opens that entry. A sitewide slot or window naming it is skipped and logged. Removing a variant that's in use warns you first: how many pages, and whether it's on sitewide or scheduled.

### Adding a new theme
No PHP/module changes needed — the picker scans `/themes/*/theme.json` automatically. This section is about **site** themes; for a recolour, make a [Theme variant](#theme-variants) instead — it is the cheaper answer and needs no build.

Steps:
1. Set up `themes/<handle>/src/` with its own `generated/colors-generated.pcss`, `main.pcss` / `critical.pcss` entries that import `_base`'s `main-core.pcss` / `critical-core.pcss` and then name the block files the theme wants, and the `critical.js` / `maincss.js` wrappers (copy the pattern from `themes/default/src/`)
2. Edit `themes/<handle>/theme.json` and set `"name"` (shown in the CP picker)
3. Add `themes/<handle>/templates/` files only for what actually needs to differ from `_base` — templates fall back to `themes/_base/templates` automatically for anything not overridden (see above), so this doesn't need to be a full copy
4. Run `npm run build` — the build finds the new theme through its `theme.json`, so there's no `package.json` change. `npm run dev` already serves it locally.
5. Activate it (`php craft theme-picker/themes/activate <handle>`), screenshot the homepage, and save it as `themes/<handle>/thumbnail.png`
6. Logo and favicon need nothing by default — `logo-build` and `favicon-build` (both part of `npm run build`) auto-discover the new theme and generate its logo/favicon from the shared `themes/_base/src/logo.svg` / `favicon.png`. Only add a `themes/<handle>/src/logo.svg` or `favicon.png` if this theme is enough of a redesign to warrant its own.
