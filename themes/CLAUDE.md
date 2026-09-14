# `themes/` — how theming works here

Quick orientation for working inside this directory. See [`../CLAUDE.md`](../CLAUDE.md) for the module/dev-stack picture and [`../stables/themes/CLAUDE.md`](../../stables/themes/CLAUDE.md) for the boilerplate's own version of this doc — the underlying mechanism is identical (this site started as a copy of `stables`), but `_base` here has since diverged with this site's own real content, so treat this file as the accurate one for this repo.

## The shape

```
themes/
  _base/            shared templates + CSS/JS — the bulk of the site's front end lives here
  default/          a site theme: palette + thin override files + theme.json
  christmas/        another site theme, same shape
```

Every real theme (`default`, `christmas`) is a folder with a `theme.json` (`{"name": "...", "thumbnail": "thumbnail.png"}`) — `modules/themepicker` auto-discovers themes by scanning `themes/*/theme.json`. `_base` is not itself a theme (no `theme.json`, never directly activated) — it's the shared foundation both real themes build on.

Unlike `stables`, where `_base` is meant to stay generic for every future client site, **this site's `_base` is just this site's own shared code now** — it holds Romeo & Buddy-specific templates (the Books/blog listing pattern, the Activity Sheet block, the header/nav markup) that have no reason to exist in the generic boilerplate. Nothing here needs to stay portable to other client sites.

**Where a change goes:**
1. It's a value (colour, type, spacing, buttons) → the Theme Designer.
2. It's one theme's look for one block → fork that block into the theme ([The theme layer](#the-theme-layer) below).
3. Every site would plausibly want it → `_base`, ideally as a new layout variant.
4. It needs behaviour Base doesn't have → the theme's `theme.js`, or `_base` if every site would want it.
5. It's a different design system → a full override: the theme's own import list, its own `main.js` behind `"js": "replace"`, its own templates. It stops receiving Base fixes for whatever it replaced.

When a fork proves good, promote it into `_base` as a layout variant.

## Templates: automatic fallback

`modules/themepicker` registers `_base/templates` as a site template root matching every template name, *after* setting the active theme's own `templates/` as Craft's primary path. Craft checks the primary path first and only falls through to `_base` if the template isn't found there.

Both `default/templates/` and `christmas/templates/` are currently empty (`.gitkeep` only) — every template on this site resolves from `_base/templates/` for both themes, including the Books/blog listing templates (`_sections/books/*`, `_sections/blog/*`) and every page-builder block. To override one template for one theme only, add just that file at the same relative path inside that theme's own `templates/` folder.

This fallback is **site-request only**. CP template roots don't participate.

See [`_base/templates/CLAUDE.md`](_base/templates/CLAUDE.md) for what's inside `_base/templates/` — routing, layouts, and (in [`_blocks/CLAUDE.md`](_base/templates/_blocks/CLAUDE.md)) how the page builder works. Both are this site's own versions, already adapted for the Books section and the Activity Sheet block.

## CSS/JS: thin entry files, no per-file fallback

There's no "check the theme folder first, fall back to `_base`" for CSS/JS the way there is for templates. Each theme's `src/` has a few small entry files that import `_base`'s, plus whatever the theme adds on top ([The theme layer](#the-theme-layer)). A theme's `src/` only needs:

| File | Job |
|---|---|
| `src/css/generated/` | This theme's own machine-generated output from `craft-modules/modules/themedesigner` — `colors-generated.pcss` (Color System roles, `--primary`/`--secondary`/etc., 6 stops each, plus `--body`/`--body-medium`; no hand-authored `$colors` map anymore, removed 2026-07-22, see `project_stables_color1_color2_to_design_system_migration` memory), `buttons-generated.pcss`/`buttons-settings.json`, and `backgrounds-generated.pcss` (per-theme since the generated/-folder migration — only this theme's own `.bg--{role}` classes, not every role any theme has ever used) — the folder every theme is expected to actually differ on. |
| `src/css/main.pcss`, `critical.pcss` | Import this theme's own `generated/colors-generated.pcss` first, then `@import '../../../_base/src/css/main.pcss'` (or `critical.pcss`); `critical.pcss` also imports this theme's own other generated files — scale, surfaces, lists, font stack, buttons, backgrounds and fonts — after `_base`'s import, so this theme's own values win the cascade. `fonts-generated.pcss` holds `@font-face` for only the families this theme's font stack and buttons use, from the shared pool on the Fonts tab; the Theme Designer rewrites it and adds the import when missing (`php craft theme-designer/fonts/sync` does the same for every theme). Buttons and backgrounds are in `critical.pcss` so they style the first frame (a new `@import` goes after the last existing one, never after a rule) |
| `src/js/critical.js`, `maincss.js` | Import this theme's own compiled CSS entry via the `@src` alias (already theme-scoped — see below, nothing to change here) |
| `src/css/blocks/` (optional) | This theme's forked block CSS, each file imported in `layer(overrides)` |
| `src/js/theme.js` (optional) | This theme's own JS, loaded after Base's `main.js` |
| `src/logo.svg`, `src/favicon.png` (optional) | Only needed if this theme is enough of a redesign to warrant its own — otherwise `logo-build`/`favicon-build` fall back to `_base/src/logo.svg` / `favicon.png` automatically at build time |

`_base/src/css/main.pcss` is where the real work happens — it `@import`s every `base/*.pcss`, `includes/*.pcss`, and `blocks/*.pcss` partial directly from `_base`, and a theme's entry imports it with one line, so the theme keeps receiving every new Base block. See [`_base/src/CLAUDE.md`](_base/src/CLAUDE.md) for what's in each of `_base/src/`'s folders, including the design-token system and how a page-builder block's CSS in `css/blocks/` is wired up — this site's own version, already noting where it's diverged from `stables`' tokens.

## The theme layer

What a theme adds on top of Base, without owning Base's import list.

**Forking a block.** Copy the template to the same path under `themes/<handle>/templates/` and give its markup **its own BEM block name** (`.cards-christmas`, not `.cards`). Base's block CSS then never matches it, so there's nothing to override or unset; shared classes (`.btn`, `.container`, `.section`) still apply. The exception is a fork that only adds to the block (motion attributes, a keyframe): it keeps Base's classes, so Base's CSS keeps styling it and the theme's file only adds. Style it in `themes/<handle>/src/css/blocks/<name>.pcss`, imported with one line after the entry's last `@import`:

```css
@import 'blocks/cardsChristmas.pcss' layer(overrides);
```

- **In `main.pcss`**, or **in `critical.pcss` if the block can sit above the fold** — `main.css` loads without blocking, so a hero styled only there flashes unstyled. Test: block `main.css` in DevTools and the fork still renders.
- **`overrides` sits above `components`** in both layer statements, so a theme rule beats a Base rule of equal specificity whatever the import order. Only `utilities` is above it.
- **Keep the hooks Base JS relies on.** A fork of a block with behaviour keeps Base's custom element tags, `data-*` attributes and slot names, and the few light-DOM classes Base JS queries, alongside its own class (`split-text split-text--christmas`). The list is in [`_base/src/CLAUDE.md`](_base/src/CLAUDE.md#what-a-fork-must-keep). Rename one and the behaviour stops silently.
- **Copy a `{% cache %}` tag as it is.** Every key goes through `themeCacheKey()` (craft-modules' themepicker), which adds the rendering theme, so a fork and Base never serve each other's cached markup. A key written without it is a bug.

**`src/js/theme.js` (optional).** If the file exists, the build adds it as an entry and `scaffold.twig` loads it as a module after `main.js`; a theme without one gets no extra script tag. In dev it's detected on disk, so a new `theme.js` loads with no build. Use it to register this theme's own elements and behaviour. Don't import Base's `Components/` or `Helpers/`: `main.js` already runs them. Anything `theme.js` shares with `main.js` — a Base module, or a package like `motion` — still runs once, but the build moves it into a shared chunk, which changes Base's `main.js` for every theme. Check the build output when you add an import.

**`"js": "replace"` in `theme.json`.** The one way a theme's own `src/js/main.js` becomes an entry: `scaffold.twig` loads it **instead of** Base's `main.js` (then `theme.js`, if any). Without the flag a `src/js/main.js` is ignored — it's a leftover per-theme wrapper, not a replacement. A bad value, or the flag with no `main.js`, fails the build naming the file. The CSS side of a full override is the theme's entry files importing whatever they want in place of `_base`'s.

## One build, one dev server

`vite.config.js` scans `themes/*/theme.json` and adds each **site** theme's `src/js/critical.js` and `maincss.js` as entries, its `theme.js` if it has one, and its `main.js` only under `"js": "replace"`, plus `_base`'s `main.js` once. Everything lands in `web/dist/site/` with one manifest. `@src` resolves to the **importing file's own theme** `src/`, so every theme's wrappers work unchanged. A new theme needs no `package.json` change.

- `npm run dev` serves every theme. Switching the active theme in the CP is live locally — reload, no restart.
- Production/staging serve `web/dist/site/`. Run `npm run build` after any theme change and before every deploy. A theme that isn't in the build can't be activated there, and pages whose stored theme isn't built fall back to `default` (then the first built site theme) rather than rendering unstyled.
- `config/stables/themepicker.php` sets `buildLayout => 'single'`, which is how `craft-modules` knows to read `web/dist/site/`. Keep it and `vite.config.js` in step.

## Adding a new theme (short version)

For a palette-only variant: `cp -r themes/christmas themes/<handle>`, then edit the new theme's colors through the theme designer tool (`/theme-designer`, local dev only) rather than hand-editing `colors-generated.pcss` directly. The build finds it through its `theme.json`, so there's no `package.json` change. Full step-by-step is in [`../README.md` § Themes](../README.md#themes).

## Conventions once you're editing `_base` (or any theme) CSS/JS

BEM naming, the PostCSS plugin stack, TypeScript's no-type-checking caveat, and the Web Components pattern used for interactive UI are all documented once in [`../CLAUDE.md` § Core Coding Conventions](../CLAUDE.md#core-coding-conventions) — that applies everywhere under `themes/`, not just `_base`.
