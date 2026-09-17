# `themes/` — how theming works here

Quick orientation for working inside this directory. See [`../CLAUDE.md`](../CLAUDE.md) for the module/dev-stack picture and [`../README.md`](../README.md) for full build commands — this file is specifically about the `_base` / theme-folder relationship and where a given change belongs. The mechanism is the boilerplate's, unchanged: [`../../stables/themes/CLAUDE.md`](../../stables/themes/CLAUDE.md) is the same document on that side, and the two are kept in step.

**The one thing to know about this site:** `_base` here is the boilerplate's, effectively identical to stables' (only its generated CSS, its icons — this site adds an `all` set — `src/logo.svg` and `src/favicon.png` differ). **Romeo & Buddy's look lives in `themes/default`.** A change made in `_base` to fix something about this site is a divergence someone has to unpick later; the September 2026 port (`stables/docs/romeo-buddy-port-plan.md`) exists because that happened once already.

## The shape

```
themes/
  _base/            the boilerplate's shared machine — templates + CSS/JS, kept equal to stables'
  default/          THIS SITE: its palette, its own templates and block CSS. The active theme
  christmas/        a Theme variant ("type": "page"): theme.json + one colors file, nothing else
```

Every real theme is a **folder with a `theme.json`** — `modules/themepicker` auto-discovers themes by scanning `themes/*/theme.json`, so a new theme needs zero code changes to show up. `_base` is not itself a theme (no `theme.json`, never directly activated) — it's the shared foundation every theme builds on top of.

**Two kinds, set by `theme.json`'s `type`** (absent means `site`):

| | Site theme (`default`) | Theme variant (`christmas`; page theme in code) |
|---|---|---|
| Contains | `templates/`, `src/` CSS+JS, `generated/` | `theme.json` + `src/css/generated/colors-generated.pcss` |
| Built by Vite | yes — its CSS entries in the one build | **never** |
| Can be the site's theme | yes | no — picker and console both refuse |
| Chosen where | CP Themes / `themes/activate` | a page's own **Theme Variant** field, or sitewide (now or scheduled) on CP Themes |

**`siteType`** (site themes only): `business` (the default when missing), `catalog`, `directory` or `custom`. It groups themes and heads the switch warning; it's a label, never logic, and a new variant drops it. See [`../docs/site-types-spec.md`](../docs/site-types-spec.md).

A variant is an overlay, not a second bundle: the page loads the active site theme's CSS and JS, and `scaffold.twig` inlines the variant's colour tokens as an unlayered `<style>` that beats `layer(tokens)`. Anything it doesn't define falls through to the live site theme, so switching the site theme re-tints every variant automatically. The overlay, logos, favicons and effects (`<snow-fall>`) all follow `brandThemeHandle()`, so they can't disagree. Full mechanics in [`../README.md` § Theme variants](../README.md#theme-variants).

**Where a change goes:**
1. It's a value (colour, type, spacing, buttons) → the Theme Designer.
2. It's how this site looks for one block → fork that block into `default` ([The theme layer](#the-theme-layer) below). **This is the usual answer here.**
3. Every RAMZ site would plausibly want it → `stables`' `_base`, ideally as a new layout variant, then copy the file here.
4. It needs behaviour Base doesn't have → the theme's `theme.js`, or stables' `_base` if every site would want it.
5. A forked block needs different CP fields, or a different item fallback chain → `themes/<handle>/config/blockfields.json` or `items.json`, holding only the blocks or keys that theme changes (`themes/default/config/blockfields.json` is this site's, with a `.md` beside it saying why each rule is there). `php craft theme-picker/themes/config blockfields --theme=<handle>` shows the combined rules and where each came from; `php craft stablestwigextensions/blocks/offered` shows the result per block. Full rules in [`../docs/theme-config-spec.md`](../docs/theme-config-spec.md).

When a fork proves good *and is generic*, promote it into stables' `_base` as a layout variant — not into this repo's `_base`.

**A theme's own new block** (Theme Designer → Blocks → New block) is different from a fork: its CSS goes in `layer(components)`, not `overrides`, because there's no Base rule to beat, and staying in `components` lets `overrides` and `utilities` still win over it. Only themes with its template offer it; see [`../docs/theme-designer-blocks-spec.md`](../docs/theme-designer-blocks-spec.md) §5.

## Templates: automatic fallback

`modules/themepicker` registers `_base/templates` as a site template root matching every template name, *after* setting the active theme's own `templates/` as Craft's primary path. Craft checks the primary path first and only falls through to `_base` if the template isn't found there.

Practical effect: a theme's `templates/` folder only needs to contain files that actually differ from `_base`. `default/templates/` holds this site's real markup — the header, footer and banner with the brand's own chrome, the hero's standard layout, `entryHeading` with its waves and stamp, the cards grid and its icon cards, `imageText`, the sliders, the Books section (`_sections/books/*`), the Activity Sheet block and the site's own image transforms. `christmas/templates/` doesn't exist: a variant has none. Everything neither theme overrides resolves from `_base/templates/`.

**`_base/templates/` is a fallback, not a starting point.** It means a theme renders something from day one, and it means a theme never has to copy a file it genuinely doesn't change — not that a good theme is mostly empty. Hand-authoring this site's own templates is normal work, not a sign something went wrong.

This fallback is **site-request only**. CP template roots (the icon-picker field input, the theme-picker page itself) are registered separately and don't participate.

See [`_base/templates/CLAUDE.md`](_base/templates/CLAUDE.md) for what's inside `_base/templates/` itself — routing, layouts, and (in [`_blocks/CLAUDE.md`](_base/templates/_blocks/CLAUDE.md)) how the page builder works.

## CSS/JS: thin entry files, no per-file fallback

There's no "check the theme folder first, fall back to `_base`" for CSS/JS the way there is for templates. Each theme's `src/` has a few small entry files, plus whatever the theme adds on top ([The theme layer](#the-theme-layer)). A theme's `src/` only needs:

| File | Job |
|---|---|
| `src/css/generated/` | This theme's own machine-generated output from `craft-modules/modules/themedesigner` — `colors-generated.pcss` (Color System roles, 6 stops each), `buttons-generated.pcss`/`buttons-settings.json`, `scale-`, `surfaces-`, `lists-`, `font-stack-`, `fonts-` and `backgrounds-generated.pcss` (only this theme's own `.bg--{role}` classes) |
| `src/css/main.pcss`, `critical.pcss` | This theme's entries. `default`'s import its own colours into `layer(tokens)`, then Base's **`main-core.pcss` / `critical-core.pcss`** — the machine, not Base's look — then name every block and include file one by one, Base's by path and this site's own beside them |
| `src/js/critical.js`, `maincss.js` | Import this theme's own compiled CSS entry via the `@src` alias (already theme-scoped, nothing to change) |
| `src/css/blocks/` (optional) | This theme's own block CSS — here: hero, cards, cardsBooks, columns, form, imageTextDefault, sliderDefault, sliderCarousel, video, activitySheet |
| `src/js/theme.js` (optional) | This theme's own JS, loaded after Base's `main.js`. This site has none |
| `src/logo.svg`, `src/favicon.png` (optional) | Only if the theme is enough of a redesign to warrant its own — otherwise `logo-build`/`favicon-build` fall back to `_base/src/` at build time |

`_base/src/css/main-core.pcss` carries the machine: tokens, reset, utilities, the shared vocabulary (`.btn`, `.container`, `.section`, `.prose`, `.pile`, typography, forms, modal, pagination) — and **no block names at all**. Base's block CSS is a list of files a theme opts into, which is why `default` names them. See [`_base/src/CLAUDE.md`](_base/src/CLAUDE.md) and [`../docs/base-layer-spec.md`](../docs/base-layer-spec.md).

## The theme layer

What a theme adds on top of Base, without owning Base's import list.

**Forking a block.** Copy the template to the same path under `themes/default/templates/`, then write the markup this site wants. Because `default` is a **clean theme** — its entries import `main-core`/`critical-core`, so Base's block CSS is only loaded for the blocks it names — **a fork keeps the plain class name**: `.cards` is `.cards`, `.hero` is `.hero`. Its CSS goes in `themes/default/src/css/blocks/<name>.pcss`, imported in `layer(components)` in place of Base's file:

```css
@import 'blocks/hero.pcss' layer(components);                    /* this site's hero */
@import '../../../_base/src/css/blocks/banner.pcss' layer(components);  /* Base's, unchanged */
```

That is what avoids the `card-default` / `card-common` / `card-listing` sprawl a long-lived site otherwise grows — every theme's card fighting a card it inherited. A card can just be a card.

- **In `main.pcss`**, or **in `critical.pcss` if the block can sit above the fold** — `main.css` loads without blocking, so a hero styled only there flashes unstyled. Test: block `main.css` in DevTools and the fork still renders.
- **Don't redefine the shared vocabulary.** `.btn` is driven by the Theme Designer's Buttons tab through `generated/buttons-generated.pcss`, and the container widths come from tokens (`--content`, `--wide`, `--text`) — change those rather than rewriting the class.
- **`overrides` sits above `components`** in both layer statements, so a rule there beats a `components` rule of equal specificity whatever the import order. Only `utilities` is above it. A clean theme rarely needs that reach — `default` currently uses none, because nothing of Base's is in its way. Where this site does have to undo a Base rule it does it in the layer that owns it (`includes/siteProse.pcss` in `utilities` hands `.prose`'s flow spacing back with `revert-layer`).
- **Keep the hooks Base JS relies on.** A fork of a block with behaviour keeps Base's custom element tags, `data-*` attributes and slot names, and the few light-DOM classes Base JS queries. The list is in [`_base/src/CLAUDE.md`](_base/src/CLAUDE.md#what-a-fork-must-keep). Rename one and the behaviour stops silently.
- **Keep the empty guard.** Blocks require no fields (so any field can be hidden per theme), so every block template renders nothing when its content is empty — `{% if image %}`, `{% if items %}`, no `<h2>` without a heading. A fork keeps that guard. See [`../docs/theme-designer-blocks-spec.md`](../docs/theme-designer-blocks-spec.md) §3.
- **Copy a `{% cache %}` tag as it is.** Every key goes through `themeCacheKey()` (craft-modules' themepicker), which adds the rendering theme, so a fork and Base never serve each other's cached markup. A key written without it is a bug.

**`src/js/theme.js` (optional).** If the file exists, the build adds it as an entry and `scaffold.twig` loads it as a module after `main.js`; a theme without one gets no extra script tag. In dev it's detected on disk, so a new `theme.js` loads with no build. Don't import Base's `Components/` or `Helpers/`: `main.js` already runs them. Anything `theme.js` shares with `main.js` still runs once, but the build moves it into a shared chunk, which changes Base's `main.js` for every theme. Check the build output when you add an import.

**`"js": "replace"` in `theme.json`.** The one way a theme's own `src/js/main.js` becomes an entry: `scaffold.twig` loads it **instead of** Base's `main.js` (then `theme.js`, if any). Without the flag a `src/js/main.js` is ignored. A bad value, or the flag with no `main.js`, fails the build naming the file.

## One build, one dev server

`vite.config.js` scans `themes/*/theme.json` and adds each **site** theme's `src/js/critical.js` and `maincss.js` as entries, its `theme.js` if it has one, and its `main.js` only under `"js": "replace"`, plus `_base`'s `main.js` once. Everything lands in `web/dist/site/` with one manifest. `@src` resolves to the **importing file's own theme** `src/`, so every theme's wrappers work unchanged. A new theme needs no `package.json` change.

- `npm run dev` serves every theme. Switching the active theme in the CP is live locally — reload, no restart.
- Production serves `web/dist/site/`. Run `npm run build` after any theme change and before every deploy. A theme that isn't in the build can't be activated there, and pages whose stored theme isn't built fall back to `default` rather than rendering unstyled.

## Adding a new theme (short version)

**Ask first whether it needs to be a site theme at all.** If the only thing that differs is colour — a season, a campaign — a **Theme variant** is the cheaper answer, and it is what `christmas` is: no bundle, no build for its colours, applied per page or sitewide (now or scheduled) from CP Themes. Make one in the Theme Designer (*New theme* → Theme variant). A site theme is for a genuine redesign, or for anything that needs its own templates, type, spacing or components.

Full step-by-step for both is in [`../README.md` § Themes](../README.md#themes) — not duplicated here.

## Conventions once you're editing `_base` (or any theme) CSS/JS

BEM naming, the PostCSS plugin stack, TypeScript's no-type-checking caveat, and the Web Components pattern used for interactive UI are all documented once in [`../CLAUDE.md` § Core Coding Conventions](../CLAUDE.md#core-coding-conventions) — that applies everywhere under `themes/`, not just `_base`.
