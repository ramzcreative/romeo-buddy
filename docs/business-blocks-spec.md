# Business blocks — the common core for Business sites

Status: **decided with Gary 2026-09-16, being built.** Done and committed locally (not pushed): §2.1, §6.1, §6.2, §3.1,
§3.3, §3.4, §3.5, all of §3.2, §3.6 (except `text` / `blockquote`, which render no section for a background to sit
on), §4.1–§4.4, all of §5, and the §8 doc pass. Not done: the craft-modules release and the romeo-buddy port (each on Gary's say-so).

Source: a survey of 19 business sites and templates (32 pages, 247 sections), each section compared against the
blocks stables ships. Report: https://claude.ai/artifact/78fjVxGxkUfVxBw2jCP8Jy. Counts below are "sites out of 19",
one per site however many of its pages were read.

The goal is a solid Business base, not every scenario. Patterns that belong to Events, Directory, Catalog or
Hospitality sites are listed in the report and kept out of here.

## 1. The rule that shapes most of this

Gary has decided the base starter kit offers **every** field ([[business-starter-set-overhaul]]). So most of the gaps
below aren't missing fields: the field exists and no template renders it. **A field that no template renders is a
dead field.** Every "show X" item here is template work first and a config change second, never the other way round.

## 2. Summary

| # | Item | Kind | Sites | Content model change |
|---|---|---|---|---|
| 2.1 | Fields config: site shows all, Base's rules move to `_base` | config | — | none |
| 3.1 | Eyebrow on every heading, and on the hero | render + field | 15 | `preheading` on `hero` |
| 3.2 | Icons on cards and image + text; a house icon set; theme icon sets | render + build | 17 | none |
| 3.3 | Heading highlight: `strong` in a heading is the highlight | CSS rule | 5 | none |
| 3.4 | Heading and buttons on the accordion | fields | 9 | `blockHeading`, `buttons` on `accordion` |
| 3.5 | Numbered steps layout on cards | layout | 5 | `steps` option on `layoutCards` |
| 3.6 | Background colour and image, as a pair, on every section-level block | fields | 14 | `backgroundRole`, `backgroundImage` |
| 4.1 | Hero layouts: standard, product, video; parallax toggle | layouts + fields | 19 | `layoutHero`, `heroParallax`, `video` on `hero` |
| 4.2 | Video: modal layout; reduced motion | layout | 6 | `layoutVideo` on `video` |
| 4.3 | Logo strip block, with row / grid / ticker | new block | 9 | block `logos` |
| 4.4 | Ticker layout, on logos and gallery | layout + component | 4 | `ticker` option |
| 5.1 | Testimonials section + block | section + block | 9 | section `testimonials`, block `testimonials` |
| 5.2 | Galleries section; gallery block and projects pick one | section + field swap | — | section `galleries`, `galleryEntry` |
| 5.3 | Posts: every publishing section; grid / list / slider; limit | block rework | 7 | `postType` options, `layoutPosts` values |
| 6.1 | Slider hero autoplay has no pause button | ADA fix | — | none |
| 6.2 | Container `overflow-hidden` stops pinning | bug fix | — | none |

**Parked, not in this spec:** contact details from the SEO module's settings (Gary wants to understand it first), and
container motion options (circle back). 6.2 is a plain bug, so it's in anyway.

## 2.1 Fields config: the site file shows every field

**Today.** `config/stables/blockfields.php` hides fields per block. Those `hidden` lists aren't site policy; they're facts
about Base's templates ("Base's `cards.twig` doesn't render `video`").

**Change.** Move the `blocks` rules into a new `themes/_base/config/blockfields.json`, and leave the site file's `blocks`
empty apart from the commented examples. `builderFields` and `switchGroups` stay in the site file (they're site-only).

**Why `_base`.** `ThemeConfig` already reads `_base` as a level (site, `_base`, ancestors, theme; later wins). A theme
that renders Base's `cards.twig` gets Base's rules for it; a theme that writes its own `cards.twig` sets its own
`cards` keys.

**The trade-off, stated plainly.** Because `_base` sits above the site file, a client site can no longer change a unit
Base sets by editing its own `blockfields.php`: the edit is silently overridden. Site-specific changes to those blocks
go in the site's theme JSON instead, which every client site has. Update the file's docblock and examples to say so.
A unit Base doesn't set (for example `layoutOptions`) still works from the site file.

**Verify.** `blocks/offered --json` before and after is identical for `default` and `coastal` (same hidden fields, same
sources apart from the level name), and nothing is reported stale.

## 3. Small items

### 3.1 Eyebrow everywhere a heading renders

**Today.** `preheading` renders in `entryHeading`, the block heading partial, all three card layouts, several slider
layouts and `imageText · hero`. `hero/standard.twig` already reads `preheading`, but the `hero` entry type has no such
field, so it never has a value. `banner` hides it; `imageText` shows it only on `hero`.

**Change.**
- Add the existing `preheading` field to `hero` (before `heading`). The template already renders it.
- Render it in `imageText` `default` and `show`, and in `banner`.
- With 2.1 done, the hidden rules for it simply go.

### 3.2 Icons

**Today.** `itemIcon` renders in `stats` and `sliders/hero` only. Cards and image + text hide it. The only icon set is
`ui` (arrow-left, arrow-right, close, play). `IconRegistry` reads one directory (`@webroot/dist/assets/icons`), and each
top-level subfolder is a set, shown as a tab in the picker. `svg-build` fills it from `themes/_base/src/icons`.

**Change.**
1. **Render** `itemIcon` in `cards` (grid, list, large) and `imageText` (default, show), decoratively
   (`aria-hidden="true"`): the heading carries the meaning.
2. **A small house set, `base`**, in `themes/_base/src/icons/base/`, drawn by us to one set of rules: one viewBox, one
   stroke width, `currentColor`, no fixed size. **The list (decided, 37 icons):** phone, email, location, clock,
   calendar, check, star, quote, arrow-right, play, download, user, users, shield, heart, leaf, home, briefcase,
   chart, lightbulb, chat, globe, award, gear, chevron-down, plus, minus, menu, search, and the social set: facebook,
   instagram, linkedin, pinterest, threads, tiktok, x, youtube. **`ui` is left exactly as it is** (arrow-left,
   arrow-right, close, play); anything new goes in `base`, even where it overlaps.

   **Social icons are brand marks, not house drawings.** They keep each brand's own shape (the one-stroke-width rule
   doesn't apply to them) and use `currentColor` like the rest. Names match the keys `_partials/social.twig` uses, so
   the two can't disagree. The X icon is `x`: X is renamed everywhere people see it (2026-09-16), while the stored
   setting keeps its `twitterUrl` / `twitterHandle` column names and the `twitter:` meta tags keep theirs, because
   X's own card reader still reads `twitter:`. `social.twig` keeps
   rendering `web/assets/social/*.svg` for now; switching it to `renderIcon('base/…')` is a separate choice.

   **Colour is always `currentColor` (Gary: we style the colours).** No icon in `base`, or in a theme's own icons
   directory, may carry a literal colour: every `fill` and `stroke` is `currentColor` or `none`. **Enforced, not
   remembered:** `svg-build` checks each SVG before optimising it and fails, naming the file and the offending
   attribute, on any hex, `rgb()`, named colour or colour inside a `style` attribute. It's a check rather than an
   automatic rewrite, because rewriting silently flattens a two-colour icon into one shape.

   **Live area, the same for every icon (Gary, 2026-09-17: keep padding, but make it consistent).** Longest drawn side
   exactly 20 units in the 24 box, centred, half the stroke included; stroke icons scaled into it keep a rendered
   2-unit line (`stroke-width = 2 / scale` on the scaled group). All 37 `base` icons were normalised to it and measured
   with `getBBox()` in headless Chrome. `svg-build` now enforces it beside the colour check without a browser:
   `scripts/lib/icon-rules.mjs` computes drawn bounds from the SVG (paths, shapes, group transforms) and agreed with
   Chrome's `getBBox()` to 0.00 units on all 37 icons as they were before normalising. Full rules:
   `themes/_base/src/CLAUDE.md` § Icons; the design skills (`/ramz-site-mockup`, `/ramz-theme-implement`) point at them. Today the social SVGs
   already pass (`currentColor`, plus `none` on empty background paths). `ui` is out of scope: `ui/play.svg`
   hardcodes `#707070` and `#fff`, and `ui` stays as it is.

   Evidence, counted by name from the survey's raw HTML across 18 sites: arrow/chevron 12, close 7, check 6,
   location 5, phone 5, plus/minus 5, menu 4, play 4, star 4, clock/email/search 2. Concept icons (lightbulb, award,
   gear, leaf…) can't be counted, since designs embed them unnamed; they're kept as a generic starter. Social icons
   appeared on 10 of 18 sites. Gary reviews the drawings before they go in.
3. **Theme icon sets — BUILT 2026-09-17 (craft-modules `themeIconsPath`, stables `scripts/build-icons.mjs`).** Gary has tested this working: an icons directory placed at
   the theme level won over Base's, every folder in it a tab, the same "a file in the theme wins" rule templates
   follow. **Verified 2026-09-16 that it does not work now:** with `default` active in dev, a set added at
   `themes/default/src/icons/zztest/` was ignored, and `IconRegistry` still read only `themes/_base/src/icons`. In
   committed history the config, registry and `svg-build` have only ever pointed at `_base`, so what made it work
   isn't in git; restore the behaviour rather than hunt for it.

   **The rule (Gary): a theme's icons directory replaces Base's entirely.** If `themes/<handle>/src/icons/` exists,
   it is the whole icon library for that theme: only its own folders show as tabs, and none of Base's icons are
   offered or rendered. A theme without the directory uses Base's. `IconRegistry` picks the directory from the active
   (or previewed) theme, nearest in its chain first, in dev from source and in production from that theme's build
   output (`svg-build` builds each theme's directory). craft-modules work (`iconpicker`) plus stables' build script.

   Safe for Base's own markup: no Base template hardcodes an icon. `renderIcon()` is only called with an
   editor-chosen value (`_utilities/nav.twig`, `stats.twig`, `sliders/hero.twig`).

   **Consequences to accept, and to say in the docs:**
   - Switching to a theme with its own directory blanks any saved icon (`set/name`) that theme doesn't have.
     `renderIcon()` already returns an empty string for a missing icon, so nothing errors; the icon just isn't there.
     A theme that wants to keep Base's icons copies the folders it needs.
   - An Icon Picker field limited to certain sets (`iconSets`) shows nothing under a theme that lacks those set names.

**Set name:** `base`, lowercase (Gary), so saved keys are `base/<name>`. Keep every set and icon name lowercase:
production (Linux) is case-sensitive while macOS isn't, so a mixed-case name can work locally and break live.

**`ui` stays its own set (Gary).** `base` and `ui` are two separate folders, so two tabs: `ui` is interface chrome (arrows, close, play), `base` is content icons.

### 3.3 Heading highlight

**Rule.** Bold text inside a heading is the highlight. Themes decide what it looks like: colour, a second typeface,
italic, a background, whatever the design calls for.

**Today.** Headings are CKEditor fields rendered through `format.basic()`, which already allows `strong`. So existing
bold renders as `<strong>` today and nothing new is needed in the editor.

**Change.**
- Base CSS: one rule for `strong` inside real and faux headings (`h1`–`h6`, `.faux-h1`…) that reads tokens:
  `color: var(--heading-highlight-color, inherit)`, `font-family: var(--heading-highlight-font, inherit)`,
  `font-weight: var(--heading-highlight-weight, inherit)`, `font-style`, `background`. Defaults change nothing, so no
  existing page moves until a theme sets a token.
- A theme sets the tokens, or styles `:is(h1,…) strong` itself for anything tokens can't express.

**ADA.** Screen readers generally don't announce `strong`, so restyling it doesn't mislead anyone. A highlight
colour still has to pass contrast against the heading's background; the Theme Designer's contrast readout should
include it once the token exists.

### 3.4 Accordion heading and buttons

**Change.** Add `blockHeading` and `buttons` to `accordion`. Render the heading through the shared partial above the
items, and buttons below them. All 11 surveyed FAQ sections had a heading; 6 had a button.

### 3.5 Numbered steps

**Change.** Add `steps` to `layoutCards`. The template renders the items as an `<ol>`, and the number is a CSS counter,
never a field. New layout icon to the layout icon rules.

### 3.6 Background colour and image

**Rule (Gary).** Background colour and background image are **one pair**. They sit together on the block, next to each
other in the same tab, and every block that has one has both. **Colour is missing from most blocks today, and that's
the main gap.**

**Today.** `backgroundRole` (the theme's colours and gradients) is on only four blocks: `entryHeading`, `form`,
`container` and `columns`. It renders as a `bg--{role}` class and is suppressed inside a container (`form.twig`,
`columns.twig`). A `backgroundImage` field (Assets, images, max 1) already exists, but it's on no entry type and no
template reads it.

**Change.** Add `backgroundRole` and `backgroundImage` together to every section-level block:

| Block | Colour | Image |
|---|---|---|
| `entryHeading`, `form`, `container`, `columns` | has it | add |
| `text`, `blockquote`, `buttons`, `imageText`, `spotlight`, `slider`, `cards`, `accordion`, `posts`, `image`, `video`, `related`, `gallery`, `stats` | add | add |
| `logos`, `testimonials` (new, §4.3, §5.1) | add | add |
| `hero`, `banner` | add | no: their own `image` already is the background |

- **One partial for every block**, applying both fields the same way, instead of per-block copies of the logic.
- **Nesting (Gary): inside a `container`, a block shows its own background colour but not its background image.**
  The container carries the one image behind the whole group, and nested blocks can still set colour bands over
  it. This **reverses today's colour rule**: `form.twig`, `columns.twig` and `entryHeading.twig` currently force
  `bg--none` inside a container, and that suppression is removed for colour and kept (as the only rule) for image.
  Existing content: no site has a container today (stables and romeo-buddy, checked 2026-09-16; romeo-buddy's local
  database mirrors production), so nothing changes on a live page.
- **Nesting (Gary): inside a `columns` column, a block shows neither its colour nor its image.** The `columns`
  block's own background colour sets the tone for the whole row. This is what Gary expects today, but the code doesn't
  quite do it: `form` is the only block in `columnBuilder` with a colour, and `form.twig` only suppresses it when its
  owner is a *container*; inside a column the owner is the column, so a form in a column currently **does** show its
  own colour. The shared background partial suppresses both fields when the owner is a `column` entry. The
  `columns` block itself follows the container rule when it sits in a container (its colour shows, its image doesn't).
- **The CP matches the render.** Fields that do nothing in a context shouldn't be offered there: hide the pair on
  blocks inside a column, and the image on blocks inside a container, through owner-aware block rules. If a rule
  can't reach that deep (container → block, columns → column → block), say so in the build and leave the fields
  visible rather than hide them anywhere they do work.
- **Image rendering:** a real `<img>` through the house transforms, positioned behind the content, not a CSS
  `background-image`, so it keeps srcset, lazy loading and `alt=""`. The theme's overlay slots sit between the image and
  the text, so text stays readable over any photo.
- **Colour and image together:** the colour paints first and the image covers it, so the colour shows while the image
  loads and wherever the image doesn't reach.

## 4. Medium items

### 4.1 Hero layouts

**Today.** `hero.twig` reads `entry['layoutHero'] ?? 'standard'`, and **no `layoutHero` field exists**, so every hero
renders `standard`. `layouts/hero/` holds `standard`, `parallax`, `video`, and `slider`, which is dead code (Splide, a
`settings.children` shape and a filter that no longer exist).

**Change.**
- **`layoutHero`** (Button Box): `standard` (full background image and text; the default), `product` (two columns,
  content in one and the image in the other), `video` (background video and text).
- **Remove** `layouts/hero/parallax.twig` and `layouts/hero/slider.twig`.
- **`heroParallax`** (Lightswitch), shown on `standard` only through `perLayout`. Moves the background image with scroll
  through the existing motion primitives (`data-motion` parallax). Off under reduced motion.
- **`video`** on `hero`, shown on `video` only. Same resolution as `imageText · hero` (embedded assets, privacy URL,
  image as poster), same player and pause button. Autoplay is an option, off by default (4.2).
- `preheading` from 3.1.

**Migration.** Adding `layoutHero` with `standard` as its default leaves every existing hero exactly as it renders now.
romeo-buddy has production heroes, so verify on rb's content, not just stables'.

**As built** (m260917_160000). An existing hero's `layoutHero` is empty, not missing, so `hero.twig` and
`_layouts/index.twig` read empty as `standard`; without that every existing hero failed to render (caught comparing the
homepage before and after). Existing heroes render the same markup apart from a new `id`. Only `standard` and `video`
float behind the navbar; `product` sits on its own colour. `backgroundRole` is on `product` only.
Parallax uses the `scroll` transition, not `parallax`: `parallax` pre-scales and travels from −15% to +15%, which opens
a gap at the top of a hero that starts at the top of the page. The image instead drifts 0 → 30% of its height while
the hero scrolls away, with offset `['start start', 'end start']`. (Built first with `100% start`, to dodge Motion
handing named pairs to the browser's native view timeline; BaseScroll now always uses Motion's JS tracking.) A video hero with autoplay off loads nothing until its
play button is pressed.

### 4.2 Video

**Today.** Click-to-play is the default: a thumbnail facade, nothing fetched until the click. `autoplay` switches to a
muted loop that mounts at 25% in view, pauses when it leaves, and respects a manual pause. **It never checks
`prefers-reduced-motion`.**

**Change.**
- **`layoutVideo`** (Button Box): `inline` (today's behaviour, the default) and `modal` (poster and play button opening
  the existing dialog partial; the player mounts inside the dialog on open and is destroyed on close).
- **Default stays as it is.** Autoplay remains an option, off by default (Gary).
- **Reduced motion:** with `prefers-reduced-motion: reduce`, an autoplay video doesn't start on its own. It shows the
  poster and the play control, and plays only when clicked.

**As built.** The modal uses a native `<dialog>` like the gallery lightbox; `_partials/dialog.twig` is marked unfinished
and nothing uses it. `autoplay` is shown on `inline` only. Verified: nothing loads before the click, closing by button or
backdrop destroys the player (also when closed before Plyr finished loading), focus returns to the thumbnail.
Reduced motion verified in headless Chrome with `--force-prefers-reduced-motion`.

### 4.3 Logo strip block

**New block `logos`** ("Logo Strip"), in `pageBuilder`, `containerBlocks` and `postBuilder`.

| Field | |
|---|---|
| `blockHeading` | shared |
| `logos` (new, Assets, images, multiple, reorderable) | the asset's own alt text is the company name; empty alt is refused on save |
| `layoutLogos` (Button Box) | `row` (default), `grid`, `ticker` |

Logos render at one height set by a token, greyscale optional through a theme token, never upscaled past their source.
Per-logo links were rare (2 of 11 sections), so none in v1.

**As built** (m260917_170000). Offered in `pageBuilder` (Media), `postBuilder` and `containerBlocks`, with the background
pair in Settings. The alt rule is an `EVENT_AFTER_VALIDATE` check in the stablestwigextensions module, on live saves
only, so drafts and autosaves still go through. Raster logos use a 192px-high `fit` transform with `upscale: false`
and an inline `--logo-max` of the source height; SVGs use the file. Tokens: `--logos-height` (48px), `--logos-gap`,
`--logos-filter` (`none`; a theme sets `grayscale(1)`).

### 4.4 Ticker layout

A layout, not a block (Gary). Available on `logos` (`layoutLogos: ticker`) and `gallery` (`layoutGallery: ticker`).

**Component.** One Web Component (`<ticker-row>`), CSS-driven (a transform animation on a duplicated track; the
duplicate is `aria-hidden` and its links `tabindex="-1"`), configured through custom properties (`--ticker-speed`,
`--ticker-gap`, `--ticker-direction`) with defaults.

**ADA, required, not optional:**
- A visible **pause button** (WCAG 2.2.2: anything moving for more than five seconds must be stoppable).
- It also pauses on hover and on keyboard focus inside it.
- Under `prefers-reduced-motion: reduce` it doesn't move at all and becomes a scrollable row.

**Performance.** Compositor-only transform, no JS per frame; the component only wires the pause button.

**As built.** `_partials/ticker.twig`, `Components/ticker.ts`, `blocks/ticker.pcss`. Full width (Gary): the row runs
edge to edge and the pause button lines up with the container. `tickerDirection` (Button Box `left`, the default, or
`right`; Gary) sits after the layout picker on `logos` and `gallery`, shown on `ticker` only (m260917_180000). It moves only once `<ticker-row>` is defined, so
without JS it's a still, scrollable row. `--ticker-speed` is the loop's duration (default 40s); each copy is at least
the row's width, so a short list still loops without a gap. The gallery lightbox skips the inert copy's links. The
row is a focusable, labelled region, so a keyboard user can scroll it when it doesn't move. Verified in the browser
(pause button, hover and focus pause, the copy inert) and, for reduced motion, a headless Chrome screenshot with
`--force-prefers-reduced-motion`.

## 5. Content-model items

### 5.1 Testimonials

**Section `testimonials`** (Channel, no URLs). Entry type **`testimonialsCollection`** (Gary, 2026-09-17: named to
match the galleries section's `…Collection` entry type), labelled "Testimonial":

| Field | Required | |
|---|---|---|
| Title | yes | the person's name (the title is the name, no separate name field) |
| `quote` (new, plain text, multi-line) | yes | plain text, so there's nothing to purify |
| `authorRole` (new, plain text) | no | "Owner, Tucson Dental" |
| `image` (existing) | no | photo |
| `companyLogo` (new, Assets, images, max 1) | no | optional (2 of 12 sections) |
| `rating` (new, Number, 1–5, whole numbers) | no | optional (2 of 12). Rendered as text plus stars, e.g. "Rated 5 out of 5" for screen readers |

**Block `testimonials`:**

| Field | |
|---|---|
| `blockHeading` | shared |
| `testimonialEntries` (new, Entries, `testimonials` section, reorderable, max 12) | empty = the most recent 6 |
| `layoutTestimonials` (Button Box) | `grid` (default), `carousel`, `single` |

The carousel reuses the existing slider machinery and its pause rule (6.1).

**Not doing:** Review structured data. Google doesn't show review stars for a business's reviews of itself, so it
would be markup with no effect.

**As built** (m260917_190000). Offered in `pageBuilder` (Components), `postBuilder` and `containerBlocks`, with the
background pair in Settings. The title field is labelled "Name"; `quote` is required. `_partials/testimonial.twig`
renders one (a `<figure>` with a `<blockquote>`, stars from the `base` icon set filled by CSS, the rating as
visually-hidden text); `single` shows the first picked, or the latest. The carousel is a plain Swiper row in the
container, moved only by its arrows, swipe or keyboard. **It doesn't autoplay**, so there's nothing for 6.1's pause
rule to do; if autoplay is ever added, the slider hero's toggle is the pattern. Cards take `--text-color`, so they
read on any background role.

### 5.2 Galleries

**Today.** The `gallery` block carries its own `galleryImages`, so the same gallery has to be rebuilt on every page.
`project` entries have `galleryImages` too, rendered through the same `_partials/gallery.twig`. Content: stables has
6 gallery blocks (4 with images, 28 images, test content); romeo-buddy and launch-pad have none. No site has project
entries.

**Change.**
- **Section `galleries`** (Channel, **no URLs**). Entry type **`galleriesCollection`**, labelled **"Gallery"** (the block
  keeps the `gallery` handle; entry type handles are global, so the section's type can't share it). Fields: Title and
  `galleryImages`.
- **New field `galleryEntry`** (Entries, `galleries` section, max 1). Replaces `galleryImages` on the `gallery` block
  **and** on `project`. The block keeps `blockHeading` and `layoutGallery` (`grid`, `masonry`, `ticker` from 4.4), so
  one gallery can be a grid on one page and masonry on another.
- `_partials/gallery.twig` and the lightbox are unchanged: they still receive images, now from the picked entry.
  Eager-load `galleryEntry.galleryImages`.

**Migration** (stables' own migration, adopt-if-present):
1. Create the section, entry type and `galleryEntry`, and add it to the block and to `project`.
2. For each gallery block and project with images: create a gallery entry titled from the block heading (or
   "Gallery N"), carrying the same images in order, and point `galleryEntry` at it.
3. Remove `galleryImages` from the block and `project` layouts **only after** every value has been copied and
   re-read. The field itself stays (the gallery entry type uses it).

**Deploy safety.** Project config applies before content migrations ([[craft-migration-deploy-safety]]). A site with
real gallery content must get steps 1–2 in one deploy and step 3 in a later one, or the synced layouts drop the field
before the migration can copy it. romeo-buddy has no galleries today, so this only matters for sites that build some
before taking the change.

**As built** (both run on stables 2026-09-17, after a backup). Two migrations, following the topics phase 2a pattern:
- **Phase 1, `m260917_200000_addGalleriesSection`:** the section, entry type and `galleryEntry` (added straight after
  `galleryImages` on the block and `project`), then the copy. Adds only. Identical image lists share one gallery entry,
  titled from the first block's heading (a project's title, or "Gallery N"). Drafts are copied; revisions aren't.
- **Phase 2, `m260917_210000_removeGalleryImagesFromBlocks`:** runs the copy again, then takes `galleryImages` off the
  block and `project`. No field or content is deleted.
- Both read images from the **relations table**, so the result is the same whichever runs first. Until phase 2, the
  templates fall back to a block's or project's own images.

Tested on a scratch copy of stables (6 gallery blocks including a draft): both migrations in order; `php craft up` with
phase 2's project config applied first (the worst deploy order); and phase 1, a new gallery block added with its own
images, then phase 2. Every block kept its images in the same order each time, and the four identical lists shared one
gallery entry.

### 5.3 Posts

**Today.** `postType` is a Dropdown (`blog`, `events`, `jobs`) and the block includes `_sections/<postType>` (the
section's own index). `layoutPosts` (`list`, `slider`) and `showFilters` exist on the block, but **no template reads
`layoutPosts`**: the slider option does nothing.

**Change.**
- **Sections:** offer every publishing section: `blog`, `events`, `jobs`, `projects`, `articles`.
- **Layouts** (`layoutPosts`, renamed values): `grid` (default: the section's whole listing, today's behaviour), `list`
  (the list style cards use), `slider` (the most recent N in a slider).
- **Limit** (`postLimit`, Number, default 15) applies to `list` and `slider`; `grid` pages through the whole section as
  it does now. Filters (topics) are out of scope for now.
- **Hiding a section per theme.** Recommendation: offer only sections that **exist on the site and have a card
  template** (`_sections/<handle>/_card.twig` in the theme or Base), the same "offered if a template exists" rule
  blocks already use. That needs no config and can't drift. If building the Dropdown options dynamically turns out
  heavy, fall back to Gary's alternative: show every existing publishing section and disable sections in the CMS.

**Migration.** Existing `layoutPosts` values `list` map to `grid` (today every posts block renders the full listing
whatever the value says); `slider` maps to `slider`.

**As built** (m260917_220000, run on stables 2026-09-17 after a backup). Sections are offered live, not by config: an
`EVENT_DEFINE_OPTIONS` handler in the stablestwigextensions module keeps a section in the picker only if it exists and
the active theme (or its ancestors, or Base) has `_sections/<handle>/index.twig`; a block's saved value always stays.
The `list` → `grid` remap is unconditional, so it's right even when project config has already brought the new
options. List and slider render `_partials/entryCard` (latest first; events upcoming, soonest first; jobs still open),
cached per block per day. `showFilters` shows on grid only, `postLimit` (1–50, empty = 15) on list and slider. The
slider doesn't autoplay. Tested on a scratch copy in both deploy orders: the blog, events and jobs listing pages linked
the same posts before and after, then list (limit 3), slider (limit 4) and list (limit 2) rendered the right counts.

## 6. Fixes found along the way

### 6.1 Slider hero autoplay has no pause button

`layouts/sliders/hero.twig` auto-advances every 6 seconds (`autoplay-delay="6000"`, not disabled by interaction), and no
pause control exists in the template or `Helpers/sliders.js`. WCAG 2.2.2 requires one for anything that moves on its
own for more than five seconds. Add a pause/play button (the video block's toggle pattern), pause on hover and on focus
within, and don't autoplay under reduced motion. The testimonials carousel inherits it.

### 6.2 Pinning inside a container

`container.twig` adds `overflow-hidden`. `overflow: hidden` makes the container a scroll container, so
`position: sticky` inside it (`.pin-section__sticky`) sticks to the container instead of the page, and never pins.
Use `overflow: clip` instead: it clips the same way without creating a scroll container. **Verify in a browser** with
an `imageText · hero` inside a container before and after.

## 7. Build order

Smallest and safest first, so each lands and is verified on its own:

1. **2.1** config move (no visible change; `blocks/offered` identical before and after).
2. **6.2**, **6.1** (fixes).
3. **3.1** eyebrow, **3.3** highlight, **3.4** accordion, **3.5** steps.
4. **3.2** icons: render first, then the house set, then theme sets (craft-modules release).
5. **3.6** backgrounds.
6. **4.1** hero, **4.2** video.
7. **4.4** ticker component, then **4.3** logo strip.
8. **5.1** testimonials.
9. **5.2** galleries (content migration).
10. **5.3** posts (content migration).

Each content-model step is a migration through Craft's services, never hand-edited YAML, verified to leave existing
layout-element uids intact.

## 8. Everything else a change touches

- **Skills and docs:** `craft-modules/docs/page-patterns.md`, `/ramz-site-mockup` §0 (block vocabulary),
  `/ramz-page-scaffold` §2 (new blocks, the testimonials and galleries sections need entries before a block can pick
  them), `/ramz-content-import` (payload shapes for picked entries), `themes/_base/templates/_blocks/CLAUDE.md` (block
  list, layouts), `docs/theme-designer-blocks-spec.md` (new blocks in the Blocks tab), the business content spec (§5b
  gallery is superseded by 5.2).
- **Layout icons** for every new layout value, to the layout icon rules.
- **`requirements.json`** and starter sets: new blocks and fields appear in what a theme records it shows.
- **romeo-buddy:** port per [[shipping-approval-workflow]]; verify its heroes and posts blocks after 4.1 and 5.3.

**Doc pass, as done (2026-09-17):** `craft-modules/docs/page-patterns.md` (block table, shared capabilities, patterns
1, 4 and 6); `/ramz-site-mockup` §0 vocabulary; `/ramz-page-scaffold` §3 (blocks that pick entries, and what has to
exist in the CP first) and its block count; `/ramz-content-import` (post builder set, `galleryEntry` /
`testimonialEntries` relations, what the import can't create); `/ramz-theme-implement` (partials, block count);
`_blocks/CLAUDE.md` (block list, builder sets, layouts); `_base/src/CLAUDE.md` (fork hooks: `ticker-row`,
`gallery-lightbox`, the video and ticker data attributes); business-content-spec §5b marked superseded. Nothing to change
for `requirements.json` or starter sets: requirements.json is recorded from the theme at publish, and starter sets only
add a site type's model on top of the Business baseline, which these blocks are part of. Layout icons were drawn with
each step.

## 9. Open questions

None. The `base` icon list is decided; Gary reviews the drawings when they're made (3.2).
