# The base layer — a machine every design runs on, not a design every theme fights

Spec written 2026-09-16, from Gary: *"a theme is meant to represent an entire site, not copy a few elements and
change colors"*, with six reference sites that share no styling whatsoever — a Framer wellness template, Apple's
Vision Pro page, a Webflow recruitment template, a destination-marketing site, a WordPress one-pager, a
slider-driven template. The goal he named: **a library you pick a whole site design from, the way you would on
Webflow.** Every "today" number below was measured on that date. Status: **spec only, nothing built.**

## 1. Goal

1. **A library theme can take base's structure and behaviour without taking its look** — per block, per piece of
   chrome, declared in one visible place.
2. **Nothing changes for any theme that doesn't ask.** `themes/default` and every existing theme keep importing
   what they import now, and render byte-identically.
3. **The content-model floor survives.** A block still needs a template to be offered at all (T7); opting out of
   base's *styling* never removes a block from the page builder.
4. **The choice is legible.** What a theme takes from base is a list you can read in its entry file, not an
   absence you infer from the cascade.

Not in this spec (§7): splitting base's templates, changing what base's JS does, and the `card-default` /
`card-common` grouping conventions this exists to make unnecessary.

**Built 2026-09-16.** `_base/src/css/main-core.pcss` and `critical-core.pcss` exist, the two typographic opinions
are extracted, and the build refuses a core list that has drifted from its full counterpart. §6 has the results.
Still open: whether `inlineEdit` and `sliderMaterial` belong in core (§8 D1); they are there by inference.

## 2. What we have today

### 2.1 The shape of base's CSS

| # | Claim | Where |
|---|---|---|
| T1 | **`main.pcss` contains no CSS rules at all** — 71 lines, every one an `@import` or a comment. One `base/` import (`screens`), 20 `includes/` (lines 12–33), 23 `blocks/` (36–58), and a commented-out `@view-transition` experiment at 61–71. A pure manifest. | [`_base/src/css/main.pcss`](../themes/_base/src/css/main.pcss) |
| T2 | `critical.pcss` is 91 lines and **does** carry literal rules: its imports end at line 36, then `.slider-heros__icon` sizing (49) and `html` (62), `body` (67, with the `.menu-active` overflow lock at 74), `#app` (79) and `.main-content-header` (83). The `body` rule reads `--font-body`, `--fs-base`, `--text-color`, `--body`. | `_base/src/css/critical.pcss` |
| T3 | **The `@layer` order statement is not in base.** It is line 1 of each *theme's* own entries. Base relies on its consumer to have declared it. | `themes/default/src/css/critical.pcss:1`; `themes/coastal/…` |
| T4 | A theme's entry is 4 lines: declare layers, import its own `colors-generated` at `tokens`, import base's entry by relative path, then re-import its own generated tokens *after* so they win. That relative-path import is the existing seam. | `themes/coastal/src/css/main.pcss` |
| T5 | `base/` is 5 files, 937 lines: `normalize` 345, `typography` 274, `themes` 164, `layouts` 131, `screens` 23. | `_base/src/css/base/` |
| T6 | `includes/` is 27 files, **4,405 lines**. The five largest are chrome: `events` 536, `adminBar` 522, `header` 483, `blog` 481, `jobs` 410. | `_base/src/css/includes/` |
| T7 | `blocks/` is 24 files, **2,251 lines**. The four slider stylesheets are 879 of them (39%). At the other end `image` is 7 lines, `imageTextHero` 10, `related` 13 — those blocks are already styled almost entirely by tokens, helpers and layouts. | `_base/src/css/blocks/` |

**Totals: 7,593 lines of CSS in base.** Roughly:

| | lines | share |
|---|---|---|
| Machine a theme must keep (normalize, screens, layouts, helpers, media, motion, pins, lenis, first-paint) | ~999 | 13% |
| Token vocabulary with base's own numbers (`themes.pcss`, `typography.pcss`) | 438 | 6% |
| Styling a library theme rewrites (chrome + blocks) | ~6,156 | **81%** |

### 2.2 What binds a theme to base regardless

| # | Claim | Where |
|---|---|---|
| T8 | **A block with no template anywhere isn't offered.** `hasTemplate()` is `templateSource() !== null`, and availability gates the page builder. Base's 25 block templates are the content-model floor. | [`BlockRules.php`](../../craft-modules/modules/themepicker/services/BlockRules.php) |
| T9 | **`helpers.pcss`'s class names are hardcoded in block templates** — `container max-w-slim`, `text-center`, `object-fit`, `visually-hidden` and ~30 more. Renaming or dropping that file breaks every template that isn't also rewritten. | `_base/src/css/includes/helpers.pcss` (171 lines); block templates |
| T10 | Base's JS finds markup by custom element tag, `data-*` attribute, and a **named list of light-DOM classes** — `.swiper-btn-prev`/`-next`, `.swiper-pag`, `.split-text`, `.swiper-material-wrapper`, `.modal`, `.gallery__link` and the `.gallery-lightbox__*` parts. That table already exists as the "what a fork must keep" clause. | [`_base/src/CLAUDE.md`](../themes/_base/src/CLAUDE.md) |
| T11 | `src/js/` is 5,558 lines and **~93% is design-agnostic behaviour**. Only `effects/snowFall.ts` (214) and arguably `typewriter.ts` (164) are a look. | `_base/src/js/` |
| T12 | **JS already has the opt-out this spec wants for CSS.** `"js": "replace"` in `theme.json` makes `scaffold.twig` load the theme's own `main.js` instead of base's. | [`themes/CLAUDE.md:85`](../themes/CLAUDE.md) |
| T13 | `adminBar.pcss` (522) and `inlineEdit.pcss` (140) are deliberately written in their own fixed-value design language rather than the theme scale — they are CP tooling that is *meant* to be theme-immune. | `_base/src/css/includes/adminBar.pcss` |
| T14 | Base's `generated/buttons-generated.pcss` is **1 line, an empty placeholder** — every theme supplies its own. Base has no `colors-generated`, `backgrounds-generated` or `fonts-generated` at all. | `_base/src/css/generated/` |
| T15 | `themes/_base` is a **SAFE** path in the boilerplate gate and `themes/default` is **GUARDED** — base is the shared update channel, the core theme is the site's own. | [`scripts/boilerplate.sh`](../scripts/boilerplate.sh) |
| T16 | A published bundle is `themes/<handle>/` and nothing else, so anything a theme puts in `_base` doesn't travel with it. | `LibraryPublisher::collectThemeFiles()` |

## 3. Problem

**You can fork a template. You cannot un-import a stylesheet.** That asymmetry is the entire problem.

A theme that wants its own cards today has three options and all three cost something:

| | keeps the semantic name | clean slate | keeps receiving base's fixes |
|---|---|---|---|
| Keep `.cards`, override | ✅ | ❌ — all 43 of base's properties across 8 elements leak; the ones you don't redeclare survive and surface later, on a page you weren't looking at | ✅ |
| Rename `.cards-acme` | ❌ | ✅ | ❌ |
| Full override (own import list) | ✅ | ✅ | ❌ **for everything**, not just cards |

Multiply by 23 blocks and 20 pieces of chrome and the middle column is the only honest one: a library theme
inherits ~6,156 lines of styling it did not ask for, and pays for it in `unset` archaeology.

**The known end state of this road is a naming convention.** Gary maintains agency builds that grew
`card-default` → `card-common` → `card-listing` for exactly this reason: when a base layer applies styles you
can't decline, teams name their way out of the inheritance. That is the artificiality the BEM-rename rule already
forces on one block at a time, generalised across a whole codebase.

**And the reference sites make the scale plain.** Two of the six — a sage-on-sage wellness template with a
floating minimal nav, and a destination site with a full-bleed photo hero and a two-tier utility nav with search
and print — share no styling at all. Base's `header.pcss` is not a head start for either; it is 483 lines in the
way of both. The same is true of the other 19 chrome files.

**What this is not.** Base's *templates* are not the problem: forking one is already free, and the dispatchers
(`cards.twig` is 24 lines of eager-loading, layout selection and an empty guard, with no markup) are pure
structure a theme keeps. Base's *JS* is not the problem either: 93% of it is behaviour any design wants, and
`"js": "replace"` already exists for the rest. **Only the CSS lacks an opt-out, because import is additive.**

## 4. Design

### 4.1 The principle

**Base is the machine. The core theme is the look.**

`themes/_base` provides what every design needs regardless of how it looks: the content model's templates, the
behaviour, normalize, breakpoints, the layout and section machinery, the utility classes templates hardcode, and
the token *vocabulary*. `themes/default` — the core theme, already GUARDED and already the "this site works out of
the box" theme (T15) — keeps base's full styling. A library theme starts from the machine and brings its own look.

This also makes base safer as the update channel: updating behaviour can't disturb anyone's design.

### 4.2 The mechanism: two more entry files in base

No build change, no PHP, no new concept. Base gains two manifests alongside the two it has:

```
_base/src/css/critical.pcss        everything — unchanged
_base/src/css/main.pcss            everything — unchanged
_base/src/css/critical-core.pcss   the machine only
_base/src/css/main-core.pcss       the machine only
```

Because `main.pcss` is a pure manifest with no rules of its own (T1), `main-core.pcss` is a subset of its import
list and nothing more. `critical.pcss` carries literal rules (T2), so `critical-core.pcss` keeps the ones that are
mechanism — the `html`/`body`/`#app` block that reads the tokens, the `.menu-active` overflow lock — and drops
`.slider-heros__icon`, which is block styling that only lives there for first paint.

A library theme's entry then reads:

```css
@layer tokens, reset, vendor, base, theme, components, overrides, utilities;
@import 'generated/colors-generated.pcss' layer(tokens);
@import '../../../_base/src/css/critical-core.pcss';
@import '../../../_base/src/css/includes/header.pcss' layer(components);  /* took base's */
@import 'blocks/header.pcss' layer(components);                          /* mine, from scratch */
```

Three properties worth naming:

- **The class names stay.** Base's template still emits `.card__item`; you style `.card__item`; base's 43
  properties simply do not exist. No rename, no `card-common`, no `unset`.
- **Your CSS goes in `layer(components)`, not `overrides`** — there is no base rule to beat. `overrides` stays
  available for the case where you *did* take base's file and want to adjust it, which is the existing behaviour
  and still works.
- **The trade is written down.** A block you opt out of stops receiving base's fixes for it, and that fact is a
  line in a file you can read, rather than something you infer.

### 4.3 What goes in core

**Decided by Gary, 2026-09-16.** His rule wasn't "is this styling?" but **"do I ever restyle it?"** — a file
that handles itself, is driven by the Theme Designer, or is a small fixed part of any design goes in core, whatever
it looks like.

What falls out of that is clean: **everything excluded is either a section that *is* the design, or a block.**

| | files | lines | |
|---|---|---|---|
| **Core** — the machine | 22 | **2,854** | 38% |
| **Opt-out** — chrome and blocks | 34 | **4,739** | 62% |

**In core:**

| Group | Files |
|---|---|
| **All of `base/`** (937) | `normalize` 345, `typography` 274, `themes` 164, `layouts` 131, `screens` 23 |
| **CP tooling** (662) | `adminBar` 522, `inlineEdit` 140 — theme-immune by design (T13) |
| **Theme-Designer-driven** (222) | `buttons` — the Buttons tab drives `.btn` through `buttons-generated` (T14), so the CSS takes care of itself |
| **Templates depend on it** (171) | `helpers` — block templates hardcode these class names (T9) |
| **Functional, rarely designed** (316) | `forms` 154, `popups` 108, `cookieConsent` 54 |
| **Component styling APIs** (180) | `media` 114, `modal` 71 (custom-property surface of `<cta-modal>`, which styles itself in shadow DOM) |
| **Mechanisms** (255) | `motion` 73, `pins` 64, `helpersFirstPaint` 35, `lenis` 24, `modalFirstPaint` 19, `sliderGlobal` 69 — sliderGlobal and sliderMaterial carry classes base's JS queries by name (T10) |
| **Small and shared** (77) | `pagination` 37, `sliderMaterial` 40 |

**Opt-out** — 10 chrome files (2,488) and all 24 block files (2,251):

`events` 536 · `header` 483 · `blog` 481 · `jobs` 410 · `footer` 165 · `articles` 106 · `scrollerNav` 97 ·
`authors` 87 · `topics` 62 · `entryCard` 61 — plus every `blocks/*.pcss`, including
`blocks/sliderHeroFirstPaint.pcss` and the `.slider-heros__icon` literal rule in `critical.pcss` (T2), which is
block styling that only lives there for first paint.

**Two files Gary didn't name, inferred and flagged** (§8 D1): `inlineEdit` (140), put in core because it is CP
tooling that reuses `adminBar`'s fixed values and is in the same category; and `sliderMaterial` (40), put in core
because it carries a JS-queried class exactly as `sliderGlobal` does. Say so if either is wrong — they're 180
lines between them.

**`typography.pcss` is in core** (§8 D2), following the "all of `base/`" rule. Its 274 lines are entirely
token-driven — `--h1-fs`, `--h1-lh`, `--h1-ls` — so a theme controls its headings from the Theme Designer without
touching CSS. The two hardcoded opinions in it still need extracting: `h2 { text-transform: uppercase }` (line 34)
and `text-wrap: balance` (line 10).

**Both core files as a diff from what exists** (Gary asked whether `critical.pcss` was already mostly core — it
is):

| | imports today | in core | dropped |
|---|---|---|---|
| `critical-core.pcss` | 17 | **15** | `includes/header.pcss` (line 15), `blocks/sliderHeroFirstPaint.pcss` (34), and the `.slider-heros__icon` literal rule (49) |
| `main-core.pcss` | 44 | **12** — `base/screens` + 11 includes | 9 chrome + all 23 blocks |

So `critical-core` is very nearly `critical.pcss`, and `main-core` is very nearly empty. That is the split doing
what it should: the critical path is almost entirely machine — normalize, breakpoints, tokens, layout, motion,
first-paint guards — and `main.pcss` is almost entirely look.

**`generated/` needs no rule.** Base's folder is 214 lines across 5 files, and `critical.pcss` already imports
four of them (`scale`, `surfaces`, `lists`, `buttons-generated`), so they arrive in core automatically; a theme
re-imports its own after and wins (T4). The fifth, `font-stack-generated.pcss` (100 lines), is **not imported by
base's entries at all** — each theme imports its own, and base's copy exists only as the values the Theme
Designer reads for themes following Base.

**Why the core files are parallel lists, not something `main.pcss` imports.** The elegant shape was tried first
and measured. Making `critical.pcss` into "core plus the three excluded things" moves `includes/header.pcss` after
`buttons.pcss` and `modalFirstPaint.pcss` **within `layer(components)`**, and inside a layer, source order decides
ties. The compiled `components` layer came out the same length but reordered — same rules, different precedence —
which isn't provably harmless, so the full files keep their exact order and the core files repeat the list.

Parallel lists drift, so the build refuses a core file that imports something its full counterpart doesn't
(`assertCoreIsSubsetOfFull()` in `vite.config.js`). That catches the dangerous direction — core pointing at
something removed from full — but not a new machine import added to full and forgotten in core, which stays a
matter of reading the note at the top of each core file.

### 4.4 Which side a new theme starts on

**A new library theme defaults to core.** Six reference designs sharing no styling is the answer to "is base's
look a useful starting point for a library theme?" — it is not.

**Built as one question, "How does this theme begin?", with three answers** — the draft's "a new theme with no
source" was wrong, because the form has no sourceless case: it always posts a `source`, falling back to `default`.

| | what it creates | source |
|---|---|---|
| **Build on a theme** *(default)* | a child: `theme.json` naming its parent, entries importing the parent's **full** CSS | needed |
| **Copy a theme** | a fork — the source's templates, CSS and JS, independent from then on | needed |
| **Start clean** | the same five files, entries importing `_base`'s **core** CSS. No parent, `followsBase: true` | **not used** |

`Build on a theme` is the default (Gary): a new theme is almost always this site's core theme with things changed,
and inheriting means the parent's fixes keep arriving. Copy is the deliberate fork. Start clean is the library
case — a design that shares nothing with what's here.

The source picker is **absent** rather than dimmed for Start clean; a greyed control still reads as a question
waiting for an answer. It stays for a Theme variant, which does need one (it takes its colours from a site theme).

**`themes/default`** — unchanged, full `main.pcss`. A fresh site still renders completely.

So the default flips only where a theme is genuinely new, and every existing theme is untouched.

### 4.5 What doesn't change

- **Templates.** Forking is already the opt-out and it already works. A theme takes base's template, or copies and
  edits it; there is nothing to un-import. §7 keeps the question of splitting base's templates open, but nothing
  here needs it.
- **JS.** `"js": "replace"` (T12) already covers the one case, and 93% of base's JS is behaviour any design wants.
- **The content model.** Blocks are entry types; base's 25 templates stay exactly where they are, so every block
  is still offered on every site (T8). This spec never removes a block from the page builder.
- **`themes/default`**, every existing theme, and every published bundle.

### 4.6 The two checks that need to know

- **`assertCascadeLayersMatch()`** greps a bundle's entries for `@import … layer(…)`. A core-based theme still
  imports its own `colors-generated` at `layer(tokens)`, so it passes — but this should be *verified*, not
  assumed, because the same check already produced one wrong answer during the inheritance work.
- **`INHERIT_IMPORT`** in `scripts/lib/theme-chain.mjs` and `ThemeEntries::PATTERN` match
  `…/src/css/(main|critical).pcss`. They must accept `main-core`/`critical-core` too, or the build will refuse
  every core-based theme as having a stale parent link.

## 5. Migration

**None.** Nothing is moved, renamed or deleted; two files are added. Every existing entry keeps importing
`main.pcss`/`critical.pcss` and every existing theme builds byte-identically — which is the acceptance test (§6
V1).

Two pieces of dead code found in passing, worth removing separately rather than carrying into the new manifests:
`main.pcss:61–71` (a commented-out `@view-transition` experiment) and the 0-byte
`templates/_blocks/layouts/hero/video.twig`.

## 6. Verification

| # | Check | Passing looks like |
|---|---|---|
| V1 | Nothing opted out | **Pass** — byte-identical for every existing theme, except the critical bundle growing **159 bytes** where extracting the two typographic opinions split one rule into two and duplicated its selector list. Proven behaviour-preserving rather than assumed: 314 selectors both sides, same set, **zero whose applied declarations differ** |
| V2 | A core-based theme with no CSS of its own | **Pass** — `.container`, the typography scale, the motion system, helpers and the admin bar all present; base's header, footer and hero-slider block all absent. Bundles: critical 22,643 vs 49,417 bytes, main 22,638 vs 90,418 |
| V3 | A core-based theme that takes back `header.pcss` | Base's header renders exactly as it does in `default` |
| V4 | A core-based theme styling `.card__item` from scratch | **Pass** — `.card__item` is the only card rule in the bundle, and base's `card__image-wrapper`/`card__info-int` appear zero times. Semantic class name, clean slate |
| V5 | Page builder | Every block still offered (T8), on a core-based theme with no block CSS at all |
| V6 | Publish and adopt a core-based theme | Passes `assertCascadeLayersMatch()` (§4.6), and the adopted copy renders as it did on the publishing site |
| V7 | Build checks | A core-based theme passes `relink` and the chain check (§4.6) |
| V8 | First paint | Block `main.css` in DevTools on a core-based theme: layout, typography and containers still correct |

## 7. Later — not in this spec

- **Splitting base's templates.** The Aspen reference — a two-tier utility nav with search and a print icon —
  can't be base's `header.twig` with different CSS; it is different markup. Forking covers it today. Whether base
  should ship *thinner* templates (dispatcher + hooks, no BEM chrome) is a real question and a bigger one.
- **Extracting base's own look into `themes/default`.** The logical end of this: base keeps no styling at all and
  the core theme owns every line of it. Much cleaner, much more disruptive, and only worth doing once §4 has been
  used in anger.
- **`themes.pcss`'s numbers.** The token names are the contract; `1680px`, the 60/70/90px header, the -300px
  column offset are base's look living in a mechanism file.
- **The four slider stylesheets** — 879 lines, 39% of all block CSS. Whether sliders should be a theme concern at
  all is worth asking separately.
- **`snowFall.ts` and `typewriter.ts`** — the only design-specific JS in base (T11).

## 8. Decisions and open questions

- **D1 — the split is decided** (§4.3, Gary 2026-09-16). His rule: **"do I ever restyle it?"**, not "is it
  styling?". `forms` went *into* core against my proposal — it's functional, and he rarely touches it. Everything
  excluded turns out to be either a section that *is* the design or a block, which is a cleaner line than the one
  I drew. **Two files still open**: `inlineEdit` (140) and `sliderMaterial` (40) weren't named and are in core by
  inference — CP tooling and a JS-queried class respectively.
- **D2 — `typography.pcss` is in core** (Gary), following "anything within `css/base`". Token-driven, so the
  Theme Designer controls it; the two hardcoded opinions in it (`h2` uppercase, `text-wrap: balance`) come out.
- **D3 — two core entries**, `main-core.pcss` and `critical-core.pcss` (Gary), mirroring the existing pair and
  keeping each Vite entry independent (T1's `@custom-media` note).
- **D4 — opting out is per-file, in the entry** (Gary): *"it keeps the css with the css — when styling themes
  you're going to be in css, and you might as well have everything in one place that relates to styling."* The
  theme's entry file is the declaration; `@import` is already the native way to say "I take this". The rejected
  alternative was a `theme.json` key mirroring `js`, with the entry generated from it.

  Worth recording *why* this differs from `parent`, which does live in `theme.json`: a parent means things beyond
  CSS — templates resolve through the chain, module config folds along it, requirements union across it — so it
  needed a home all of those could read, and it cost a `relink` command plus a build check to keep the two in
  step. A CSS opt-out means nothing outside CSS, so the CSS file can hold it: one source of truth, nothing to
  generate, nothing to fall out of sync. If the CP ever wants to show what a theme takes from base, it reads the
  entry rather than replacing it.
