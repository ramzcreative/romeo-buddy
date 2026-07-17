# `_base/src/` — orientation

Quick index of this folder — see [`../../CLAUDE.md`](../../CLAUDE.md) for the `_base`/theme-folder relationship and [`../templates/CLAUDE.md`](../templates/CLAUDE.md) for the Twig side this CSS/JS pairs with.

## What's here

| Path | What's in it |
|---|---|
| `css/main.pcss`, `critical.pcss` | The two real Vite entry points. `critical.pcss` is above-the-fold structural CSS (reset, fonts, header) meant to be inlined early; `main.pcss` is everything else, including every page-builder block's styling — see below. Both `@import 'base/screens.pcss'` independently (stripped at build time by postcss-custom-media, so no duplicate output). |
| `css/base/` | Foundational, sitewide styles: `normalize`, `typography`, `fonts`, `layouts`, `backgrounds`, `themes`, `screens` (the `@custom-media` breakpoint definitions everything else uses). |
| `css/includes/` | Shared component/utility styles that aren't a single page-builder block: `header`, `footer`, `buttons`, `forms`/`formie`, `modal`, `popups`, `cookieConsent`, `blog`, animation helpers, slider globals. |
| `css/blocks/` | One stylesheet per page-builder block — see its own section below. Includes `activitySheet.pcss` for this site's own Activity Sheet block (see [`_blocks/CLAUDE.md`](../templates/_blocks/CLAUDE.md)). |
| `js/main.js` | Entry point — registers `headerOnScroll`, lazy-loads `Helpers/` (scroll animations, sliders, modal) after `DOMContentLoaded`, and imports the Web Component classes in `Components/`. |
| `js/Components/` | Native Web Components (`class X extends HTMLElement`) for interactive UI — `modal.ts`, `accordion.ts`, `cookieConsent.ts`, `videoPlayer.ts`, plus the `animations/` subsystem (scroll/inview transitions used by `[data-animations]` blocks). |
| `js/Helpers/` | Non-component behavior, imported lazily from `main.js`: `scrollAni.js` (Lenis smooth scroll + Motion text animations), `sliders.js`, `modal.js`. |
| `js/SliderEffects/` | Custom Swiper effect modules. |
| `icons/all/` | Source SVGs for the icon picker (`config/iconpicker.php`'s `dev` environment points `iconsPath` at `icons/` — the "all" subfolder is this site's one icon set/tab; unlike `stables`, which names its set `ui`, the folder name *is* the set name shown in the picker, so this differs per site). See [`craft-modules/modules/iconpicker/CLAUDE.md`](../../../../craft-modules/modules/iconpicker/CLAUDE.md). |

## Design tokens — spacing, type, color

Almost any value a block's CSS needs already exists as a custom property on `:root`, defined in `css/base/themes.pcss` — check there before hardcoding a px/rem/hex value. **This file has diverged from the `stables` boilerplate** (different token names in places, extra tokens) — don't assume `stables`' version is authoritative here.

- **Spacing**: `--spacer-1` through `--spacer-9`, each a fluid `clamp()` (scales with viewport width — no separate mobile/desktop values to maintain). Named pairs `--spacing-y`, `--spacing-x`, `--spacing-slim-y` combine specific spacers for common block-padding patterns — prefer those over composing your own if the design matches.
- **Typography**: `--fs-xs` through `--fs-xxxl` (sizes, also fluid), `--fw-light` through `--fw-heavy` (weights), `--lh-xs` through `--lh-xl` (line heights), `--ls-xs` through `--ls-xl` (letter spacing), `--font-primary` (`"ABeeZee"`) / `--font-heading` (`"Baloo Bhaina 2"`) — no `--font-secondary` here, unlike `stables`.
- **Radius**: `--radius-sm` / `--radius-md` / `--radius-lg` — note the naming (`-sm`, not a bare `--radius`) and that there's no responsive breakpoint override here, unlike `stables`.
- **Borders**: a token set `stables` doesn't have — `--border-color` (defaults to `--color1`), `--border-width` / `--border-width-thin` / `--border-width-lg`.
- **Color**: each theme defines its own `$colors` map in `src/css/base/_colors.pcss`, compiled into `--color1`, `--color1-light`, `--color2-dark`, etc. by `themes.pcss`. **Color slots aren't fixed to exactly `color1`/`color2`** — this site's `default` theme defines a third, `color3` ("Calm"/tan), while `coastal` only defines two; `config/colour-swatches.php` automatically only offers the CP background swatches a theme actually has (see that file's own comment). A block offering a background-color option should read the CP's swatch selection (`entry['background']['color'][0]['background']`, e.g. `bg--color1`) rather than introduce a new hardcoded color or assume exactly two slots exist.
- **Layout widths**: `--content` / `--wide` / `--text` (max-widths — `1200px`/`1600px` here, different from `stables`' `1680px`/`1900px`).

**Component-scoped tokens, not just global ones.** Several shared classes define their own custom properties with sensible defaults, meant to be overridden per-instance rather than needing a new variant class: `.btn` (`--btn-bg`, `--btn-text`, `--btn-border-radius`, ...), `.dialog`/`.popover` (`--popup-padding`, `--popup-max-width`, ...), `.columns` (`--col-offset-lg`/`-md`/`-sm`). **Note `.btn` defaults to `--color2` here** (`--btn-bg: var(--color2)`), not `--color1` like `stables` — a real behavioral difference, not just a color swap, so don't assume a button's default look without checking. When a new block needs a tunable value another block might also want, prefer adding a scoped custom property with a default over a one-off class or an inline style.

## How page-builder block CSS is wired up

Each file in `css/blocks/` styles one page-builder block (or one layout variant of a block — see [`../templates/_blocks/CLAUDE.md`](../templates/_blocks/CLAUDE.md) for the block/layout distinction). The naming loosely follows the block or block+layout handle — `hero.pcss`, `columns.pcss`, `cards.pcss` + `cardsList.pcss` + `cardsLarge.pcss` (one per `layouts/cards/*` variant), `imageTextDefault.pcss` + `imageTextHero.pcss` + `imageTextShow.pcss` (one per `layouts/imageText/*` variant) — but it's a convention, not a strict rule: `hero.pcss` covers all four `layouts/hero/*` variants in one file via BEM modifiers, and a block whose markup only reuses shared classes from `css/includes/` (buttons, forms) may not have a dedicated `css/blocks/` file at all.

**There's no automatic discovery.** `css/main.pcss` `@import`s each `blocks/*.pcss` file by hand, one line per file. A new block's styles won't ship until you add its `@import 'blocks/<name>.pcss';` line there — nothing scans the folder for you.

Practical flow for a new block, CSS side: create `css/blocks/<handle>.pcss` (or `<handle><LayoutName>.pcss` if it's a layout variant), add its `@import` to `main.pcss`, and match its class names to whatever the corresponding `_blocks/<handle>.twig` template actually renders (BEM: `Block__Element--Modifier`, per the root `CLAUDE.md`'s coding conventions).
