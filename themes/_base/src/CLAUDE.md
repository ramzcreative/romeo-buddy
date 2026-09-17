# `_base/src/` — orientation

Quick index of this folder — see [`../../CLAUDE.md`](../../CLAUDE.md) for the `_base`/theme-folder relationship and [`../templates/CLAUDE.md`](../templates/CLAUDE.md) for the Twig side this CSS/JS pairs with.

## What's here

| Path | What's in it |
|---|---|
| `css/main.pcss`, `critical.pcss` | The two real Vite entry points. `critical.pcss` is above-the-fold structural CSS (reset, header, typography, buttons, section layout, the hero slider's first-paint size from `blocks/sliderHeroFirstPaint.pcss`, the skip link and `.pile` from `includes/helpersFirstPaint.pcss`, and `includes/modalFirstPaint.pcss`, which keeps a modal's content collapsed until `<cta-modal>` is defined) meant to be inlined early; `main.pcss` is everything else, including every page-builder block's styling — see below. Both `@import 'base/screens.pcss'` independently (stripped at build time by postcss-custom-media, so no duplicate output). |
| `css/base/` | Foundational, sitewide styles: `normalize`, `typography`, `layouts`, `themes`, `screens` (the `@custom-media` breakpoint definitions everything else uses). |
| `css/includes/` | Shared component/utility styles that aren't a single page-builder block: `header`, `footer`, `buttons`, `forms`, `modal`, `popups`, `cookieConsent`, `blog`, `motion` (see below), slider globals. |
| `css/blocks/` | One stylesheet per page-builder block — see its own section below. |
| `js/main.js` | Entry point — registers `headerOnScroll`, lazy-loads `Helpers/` (scroll animations, sliders, modal) after `DOMContentLoaded`, and imports the Web Component classes in `Components/`. |
| `js/Components/` | Native Web Components (`class X extends HTMLElement`) for interactive UI — `modal.ts`, `accordion.ts`, `cookieConsent.ts`, `videoPlayer.ts`, `ticker.ts` (`<ticker-row>`, loaded only when one is on the page), the `effects/` a Theme variant switches on (`snowFall.ts`, imported only when its element is on the page), plus the `motion/` subsystem (scroll/inview transitions used by `[data-motion]` blocks). |
| `js/Helpers/` | Non-component behavior, imported lazily from `main.js`: `scrollAni.js` (Lenis smooth scroll + Motion text animations), `sliders.js`, `modal.js`. |
| `js/SliderEffects/` | Custom Swiper effect modules. |
| `icons/` | Source SVGs for the icon picker, one folder per set, each a tab: `ui` (interface: arrows, close, play) and `base` (content icons and social marks). `config/stables/iconpicker.php`'s `dev` environment points `iconsPath` here — see [`craft-modules/modules/iconpicker/CLAUDE.md`](../../../../craft-modules/modules/iconpicker/CLAUDE.md). Drawing rules: [Icons](#icons) below. |

## What a fork must keep

Base JS finds most of its markup by custom element tag (`cta-modal`, `accordion-group`, `swiper-container`, `gallery-lightbox`, `ticker-row`, ...) and `data-*` attribute (`data-slider-*`, `data-motion`, `data-reveal`, `data-toggle-modal`, `data-video-*` including `data-video-modal` and `data-video-manual`, `data-ticker-pause`, ...). A ticker also needs its two `.ticker__list`s' `inert`/`aria-hidden` copy and `[paused]` on the element: the loop and its pause are CSS. A theme fork that renames its BEM block keeps every tag, `data-*` attribute and slot name it copied, and these light-DOM classes, which Base JS queries by name:

| Class | Queried by | What it drives |
|---|---|---|
| `.swiper-btn-prev`, `.swiper-btn-next` | `Helpers/sliders.js` | slider arrows |
| `.swiper-pag` | `Helpers/sliders.js` | slider pagination |
| `.split-text` (inside `[data-text]`) | `Helpers/scrollAni.js` | the split-text scroll animation (no shipped template uses it yet) |
| `.swiper-material-wrapper` | `SliderEffects/effect-material.esm.js` | the material slider effect (no shipped template uses it yet) |
| `.modal` | `Helpers/scrollAni.js` | Lenis leaves scrolling inside it native (its `prevent` option) |
| `.gallery__link`, `dialog.gallery-lightbox` and its `.gallery-lightbox__*` parts (`image`, `caption`, `current`, `total`, `btn--prev`/`--next`/`--close`) | `Components/galleryLightbox.ts` | the gallery lightbox |

`cta-modal`'s `.cta-modal__*` classes aren't on the list: the component writes them into its own shadow root. Add a row here when Base JS starts querying a new class.

## Icons

Every icon in every set — Base's and a theme's own — is drawn to the same rules, so icons from different sets sit
together without one looking bigger, heavier or differently spaced. Decided with Gary 2026-09-17; see
`docs/business-blocks-spec.md` §3.2.

1. **`viewBox="0 0 24 24"`**, no `width`/`height` (the element that holds it sizes it).
2. **Live area: longest side exactly 20, centred** — a 2-unit margin on the icon's longest side, measured on what's
   *drawn*, half the stroke included. Never edge-to-edge (0 margin) and never an icon's own extra padding: CSS
   controls spacing, and it can only do that if every icon's padding is the same.
3. **Colour is only ever `currentColor`.** Every `fill` and `stroke` is `currentColor` or `none`; no hex, `rgb()`,
   named colour or colour in `style`. The page styles the colour.
4. **Stroke icons render a 2-unit stroke** with round caps and joins. A drawing scaled into the live area with a
   `transform` gets `stroke-width = 2 / scale` on the same group, or its line weight drifts from the rest.
5. **Brand marks** (social logos) keep the brand's own shape — rule 4 doesn't apply — but still fit rule 2 and fill
   with `currentColor`.
6. **Names are lowercase kebab-case**, sets too (`base/arrow-right`): production is Linux and case-sensitive, so a
   mixed-case name can work on a Mac and break live.

**Using them:** call `renderIcon(key)` directly where it's output. It's registered `is_safe`, which only covers
direct output — a value stored with `{% set %}` first is escaped and prints the SVG source as text. Icons in block
items are decorative (`aria-hidden="true"`; the heading carries the meaning) and sized by `.block-icon` in
`includes/helpersFirstPaint.pcss`, never a `main.pcss` rule (see the FOUC note below).

**A theme's own icons:** `themes/<handle>/src/icons/<set>/`, and that directory **replaces Base's entirely** — only
the theme's folders show as tabs, so a theme that wants some of Base's icons copies those folders in. A child theme
without its own directory uses its nearest ancestor's. Configured by `themeIconsPath` in
`config/stables/iconpicker.php`; built to `web/dist/assets/theme-icons/<handle>/`.

**Checking a set:** `/dev/icons` (admin-only) renders every set at 24/40/64px, on light, on `--secondary` and tinted
by CSS, and labels anything that fails to resolve. **`npm run svg-build` enforces rules 1, 2, 3 and 6** on every set
except Base's `ui` (which predates them and is left as it is): it measures each icon's drawn bounds from the SVG
itself (`scripts/lib/icon-rules.mjs`, matched to Chrome's `getBBox()` on all 37 `base` icons) and fails the build,
naming the icon and the problem, before anything is written.

## Motion — two paths, one set of tokens

Both read the same tokens in `css/base/themes.pcss` (`--ease-out`, `--transition-reveal`, `--reveal-shift`, `--reveal-stagger`), so a CSS reveal and a Motion one look identical. `--ease-out` is deliberately the same curve as `DEFAULTS.ease` in the JS.

**`[data-reveal]` — the default.** Reach for this first. All CSS, in `css/includes/motion.pcss`; `js/Components/motion/reveal.ts` only adds `.is-revealed` when the element scrolls in, once, then stops watching.

- `data-reveal` on an element reveals it. An optional value sets direction: `up` (default), `down`, `left`, `right`, `fade`, `scale`.
- `data-reveal-group` reveals its **direct children**, cascading `--reveal-stagger` apart. Indices come from `nth-child`, capped at 8 steps, so nothing writes inline styles per child and dynamically-added children still work. A `data-reveal` value on the group aims all its children at once.

It lives in the **critical** bundle, not `main.pcss`: `scaffold.twig` loads main CSS with `media="print" onload`, so a reveal defined there would land after first paint. The hidden state is gated on `html.js` (set by an inline script in the head) and on `prefers-reduced-motion: no-preference` — a blocked script or a reduced-motion preference leaves content visible rather than stuck at `opacity: 0`. Don't put `data-reveal` on above-the-fold content; it stays hidden until `main.js` runs.

**`[data-motion]` — the Motion path.** `js/Components/motion/` — for what CSS can't do: scroll-*linked* progress (`scroll`, `inout`, `clip`, `parallax`), `typewriter`, and per-breakpoint options. `inview` is kept but no longer used by any block: it's the escape hatch for `targetId` (reveal element A when element B scrolls in), the one thing `[data-reveal]` can't express. Note `staggerDelay` is inert on the scroll-linked transitions — `BaseScroll` animates a single element, so only the inview family staggers.

## Design tokens — spacing, type, color

Almost any value a block's CSS needs already exists as a custom property on `:root`, defined in `css/base/themes.pcss` — check there before hardcoding a px/rem/hex value.

- **Spacing**: `--spacer-1` through `--spacer-10`, each a fluid `clamp()` (scales with viewport width — no separate mobile/desktop values to maintain). Custom **pairs** (`--spacer-4-6`, `--spacer-7-9`, `--spacer-8-10`, ...) span from one step's min to another's max, giving a steeper ramp than any single step — created in the Theme Designer, so check what a site actually has before reaching for one. Section rhythm reads through `--section-space` (see `base/layouts.pcss`), never a raw spacer.
- **Typography**: `--fs-xs` through `--fs-4xl` (9 fluid steps — 3 of the 12 Font Stack text styles share a step rather than each getting its own, see below), `--fw-light` through `--fw-heavy` (weights), `--lh-impact`/`-tight`/`-normal`/`-loose` (line heights), `--ls-tight`/`-normal`/`-loose`/`-wide` (letter spacing) — all generated into `generated/scale-generated.pcss`, managed via the Theme Designer CP's Type Scale tab (weight/line-height/letter-spacing are Base-only, same as sizing/spacing), not hand-authored. `--font-display` / `--font-body` / `--font-accent` (family names) and the real per-selector CSS for each of the 13 text styles (h1-h6, subheading, preheading, paragraph + 3 size variants, links) are generated into `generated/font-stack-generated.pcss`, managed via the Theme Designer CP's Font Stack tab (per-theme — follows Base unless that theme switches it off on General), not hand-authored. The font *files* are one shared pool (`generated/fonts-settings.json`, files in `web/fonts/`), managed via the Fonts tab; each site theme's own `generated/fonts-generated.pcss` carries `@font-face` for only the families its font stack and buttons use, imported from its `critical.pcss` and regenerated by the Theme Designer.
- **Color**: no hand-authored `$colors` map anymore (removed 2026-07-22 —
  see `project_stables_color1_color2_to_design_system_migration` memory).
  Each theme's own `src/css/generated/colors-generated.pcss` defines the
  Color System roles (`--primary`/`--secondary`/`--gray`/`--surface`/etc.,
  6 stops each: base/`-light`/`-dark`/`-medium`/`-accent`/
  `-complement`, computed via `oklch()`/`hsl()` — see
  `craft-modules/modules/themedesigner`) plus plain `--body`/
  `--body-medium` lines. A block offering a background-color option
  should read the CP's swatch selection (`entry['background']['color'][0]['background']`,
  e.g. `bg--primary`) rather than introduce a new hardcoded color —
  `config/colour-swatches.php`'s options are these same Color System
  roles (kept in sync via the theme designer's Backgrounds tab, not
  hand-maintained).
- **Layout widths / radii / shadows**: `--content` / `--wide` / `--text` (max-widths), `--radius` / `--radius-md` / `--radius-lg` (border-radius, responsive per breakpoint), `--shadow`.

**Component-scoped tokens, not just global ones.** Several shared classes define their own custom properties with sensible defaults, meant to be overridden per-instance rather than needing a new variant class: `.btn` (`--btn-bg`, `--btn-text`, `--btn-border-radius`, ...), `.dialog`/`.popover` (`--popup-padding`, `--popup-max-width`, ...), `.columns` (`--col-offset-lg`/`-md`/`-sm`). This is the same "configure via CSS custom properties" convention the root `CLAUDE.md`'s coding conventions call out for Web Components — it applies to plain CSS classes too. When a new block needs a tunable value another block might also want, prefer adding a scoped custom property with a default over a one-off class or an inline style.

## How page-builder block CSS is wired up

Each file in `css/blocks/` styles one page-builder block (or one layout variant of a block — see [`../templates/_blocks/CLAUDE.md`](../templates/_blocks/CLAUDE.md) for the block/layout distinction). The naming loosely follows the block or block+layout handle — `hero.pcss`, `columns.pcss`, `cards.pcss` + `cardsList.pcss` + `cardsLarge.pcss` (one per `layouts/cards/*` variant), `imageTextDefault.pcss` + `imageTextHero.pcss` + `imageTextShow.pcss` (one per `layouts/imageText/*` variant) — but it's a convention, not a strict rule: `hero.pcss` covers all three `layouts/hero/*` variants in one file via BEM modifiers, and a block whose markup only reuses shared classes from `css/includes/` (buttons, forms) may not have a dedicated `css/blocks/` file at all.

**There's no automatic discovery.** `css/main.pcss` `@import`s each `blocks/*.pcss` file by hand, one line per file. A new block's styles won't ship until you add its `@import 'blocks/<name>.pcss';` line there — nothing scans the folder for you.

Practical flow for a new block, CSS side: create `css/blocks/<handle>.pcss` (or `<handle><LayoutName>.pcss` if it's a layout variant), add its `@import` to `main.pcss`, and match its class names to whatever the corresponding `_blocks/<handle>.twig` template actually renders (BEM: `Block__Element--Modifier`, per the root `CLAUDE.md`'s coding conventions).

**Watch for FOUC on above-the-fold icons, dividers, and decorative shapes.** `scaffold.twig` loads `main.pcss` with `media="print" onload` — non-render-blocking, same as the typography note in `critical.pcss` describes. Between first paint and that swap, an SVG's only sizing is normalize's bare `:where(svg){width:100%}`; if the rule that actually constrains it (a wrapper's width, or the svg's own height) lives in `main.pcss`, it renders at the width of whichever ancestor already has a size — often close to full viewport width — until main lands. This bit a client site that added a hero-adjacent divider graphic and per-card icons in its own `main.pcss`-loaded block CSS: both flashed oversized on every load. Fix is the same pattern as typography — move just that element's sizing/positioning rule (not the whole file) into `critical.pcss`, with a one-line pointer comment left at the old spot. For a whole above-the-fold block, give its first-paint size and position their own file imported from `critical.pcss` in `layer(components)`, as `blocks/sliderHeroFirstPaint.pcss` does for the hero slider — values that also live in the block's main file have to change in both. Only worth doing for something that actually renders above the fold or immediately under the hero; a footer icon flashing large for one frame isn't worth critical-bundle bloat.
