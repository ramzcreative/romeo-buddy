# `_blocks/` — the page builder

Quick orientation for how a "page builder" field's value turns into rendered HTML. See [`../CLAUDE.md`](../CLAUDE.md) for the rest of `templates/` and [`../../../CLAUDE.md`](../../../CLAUDE.md) for the `_base`/theme-folder picture.

## It's not a Matrix field

`pageBuilder` (and its siblings `postBuilder`, `columnBuilder` — see below) is a **`craft\ckeditor\Field`**, not a classic Matrix/block field. Editors get a normal CKEditor rich-text toolbar, plus a "createEntry" button that inserts a fully structured block (Hero, Cards, Accordion, ...) inline, as a nested Entry element, anywhere in the flow of text.

Because of that, iterating a builder field's value yields a sequence of **chunks**, not a flat list of blocks:
- `chunk.type == 'markup'` — a run of plain rich-text HTML the editor typed directly (already HTML-Purified at save time, same as any other CKEditor field — see `purifyHtml: true` on these fields' config).
- anything else — a nested block, reachable as `chunk.entry` (a real `craft\elements\Entry`, one of whichever entry types that field's `entryTypes` setting allows).

## `_builder.twig` — the dispatcher

`_builder.twig` doesn't read any specific field itself — the including template sets a `blocks` variable first, then includes it:

```twig
{% set blocks = entry.pageBuilder %}
{% include "_blocks/_builder" %}
```

For each chunk it loops over, it either:
- includes **`_blocks/markup.twig`** (markup chunks), or
- resolves `handle = chunk.entry.type.handle` and includes **`_blocks/#{handle}.twig`** (entry chunks) — passing `entry: chunk.entry`, plus `index`, `last`, `hasHandle` (whether this block's type has already appeared earlier on the page), `parent`, `previous`, and `preloadImage` (true only for the very first block, so its image isn't lazy-loaded).

**A block template's filename must exactly match its entry type's handle.** That's the entire registration mechanism — there's no separate list in PHP/Twig mapping handles to templates to keep in sync. Add a new entry type to a builder field's `entryTypes` setting, drop a matching `_blocks/<handle>.twig` in this folder, and `_builder.twig` picks it up automatically.

Current block handles → templates (from `pageBuilder`'s `entryTypes`, see `config/project/fields/pageBuilder--*.yaml`): `entryHeading`, `imageText`, `contentBlock`, `columns`, `hero`, `banner`, `spotlight`, `slider`, `cards`, `posts`, `form` (+ `formContact`), `image`, `video`, `accordion`, `blockquote`, `buttons` — plus **`activitySheet`**, a romeo-buddy-specific addition (its "Download PDF" button links to a custom `activity-sheets/generate` route, not shared with the `stables` boilerplate).

## Three builder fields, one dispatcher

| Field | Used on | Included from | `entryTypes` scope |
|---|---|---|---|
| `pageBuilder` | Any entry with the field (typically the `pages` section) | `_layouts/index.twig`, via `_sections/default.twig` | The full block set — everything above |
| `postBuilder` | Blog entries/categories (post body content) | `_sections/blog/_detail.twig`, `_sections/blog/_category.twig` | A narrower set — `cards`, `spotlight`, `hero`, `image`, `video`, `accordion`, `blockquote`, `buttons` only; no full-page layout blocks (Entry Heading, Image+Text, Content, Columns, Banner, Slider, Posts, Form) |
| `columnBuilder` | Each individual column inside a **Columns** block | `_blocks/columns.twig`, once per column | Narrower still (Form, Blockquote, Buttons, Image) |

All three funnel through the same `_blocks/_builder.twig` — only the field being read and the `parent` string passed down differ.

The **books** section (`_sections/books/*`) reuses `pageBuilder` the same way blog does with `postBuilder` — it's rendered through the same generic `_builder.twig`, nothing books-specific in the dispatch mechanism itself.

## Nesting: blocks that contain more blocks

Two block templates recursively re-include `_blocks/_builder`:
- **`columns.twig`** — each `column` sub-item has its own `columnBuilder` field; the column loop sets `blocks = column['columnBuilder']` and includes `_blocks/_builder` with `parent: 'column'`.
- **`contentBlock.twig`** — the "Content" block has its own nested `pageBuilder` field (`entry['pageBuilder']`), included with `parent: 'builder'`.

`parent` is how a nested template (e.g. `markup.twig`) tells it's rendering inside a column vs. at the top level, for layout/class purposes — it's not a security or data boundary.

## The "layouts" sub-pattern

Several blocks are themselves thin dispatchers: they read a `layout<BlockName>` selector field on the block entry, default to a base variant, and include a layout-specific template under `_blocks/layouts/<blockName>/`:

```twig
{# hero.twig #}
{% set layout = entry['layoutHero'] ?? 'standard' %}
{% include "_blocks/layouts/hero/#{layout}" with { entry: entry, ... } %}
```

Current layout groups: `layouts/hero/` (`standard`, `parallax`, `slider`, `video`), `layouts/imageText/` (`default`, `hero`, `show`), `layouts/cards/` (`grid`, `large`, `list`), `layouts/sliders/` (`sliders`, `carousels`, `hero`, `slants`). Not every block has this indirection — `accordion.twig`, `text.twig`, `columns.twig`, etc. render directly with no layout variants.

## The first-block-becomes-header special case

`_layouts/index.twig` peeks at the *first* block before handing anything to `_builder.twig`. **In this site, only a `slider` block gets pulled into the header this way** (`heroArray = ['slider']` — note this differs from the `stables` boilerplate, where a plain `hero` block qualifies too; don't assume the two stay in sync). If that first slider's own sub-layout (`layoutSliders`) is `hero`, an extra flag makes it float behind the nav bar rather than push content down (`heroOverlayArray`). A page whose first block is a plain `hero` entry type is *not* promoted here — it renders inline through the normal `_builder.twig` loop like any other block.

## From a design to a block

The first real decision, before touching anything, is **which of these four this design actually is**:

1. **A CSS-only variation** of a block/layout that already exists (different spacing, color, an existing background swatch) — no new template, no new fields. Edit the existing `_blocks/<handle>.twig` (or its `css/blocks/<handle>.pcss`) directly, or fork it into a theme override if it's genuinely theme-specific — see [`../../../CLAUDE.md`](../../../CLAUDE.md).
2. **A new arrangement of the same kind of content** an existing block already handles — e.g. another way to lay out "image + heading + intro" alongside `imageText`'s existing `default`/`hero`/`show` variants. This is a new **layout variant**, not a new block: add the option to the block's `layout<BlockName>` select field, then a new `_blocks/layouts/<blockName>/<newLayout>.twig` (see "The layouts sub-pattern" above).
3. **A genuinely new kind of content** — nothing existing renders this shape of information. This is a real new **block type**: a new entry type, a new `_blocks/<handle>.twig`, its own fields. See the steps below.
4. **A piece reused *inside* other blocks**, not standalone on the page (a card, a heading treatment) — not a top-level block at all. Belongs under `_blocks/partials/` (see `partials/heading.twig`), included from whichever block(s) need it, not registered in any builder field's `entryTypes`.

If you're not sure which it is, look at how many existing entry types already have most of the fields the design needs (see below) — a design that reuses 80% of an existing block's fields with one new arrangement is almost always case 2, not case 3.

### Reuse fields before creating new ones

Craft fields are defined once and attached to as many entry types' field layouts as want them — `heading`, `image`, `intro`, `subheading`, `preheading`, `buttons`, `background`, `imageItems`, `reverse`, `alignment`, `offset`, `width` are all already shared across most of the existing block entry types (e.g. the `heading` field is on 10 different entry types, `image` on another 10, including the newer `book` entry type — check `grep -l "fieldUid: <uid>" config/project/entryTypes/*.yaml` for any field in `config/project/fields/` to see who else already uses it). A design's "headline + intro + image" section almost never needs its own new heading/intro/image fields — attach the existing ones to the new entry type's field layout instead. Only add a new field when the design genuinely needs data nothing else captures (an ISBN, a price, a rating).

Repeatable "item" content (cards, `imageItems`, anything that's a heading/intro/image/link tuple repeated N times) can lean on the `getItemData` Twig filter (`modules/stablestwigextensions`) rather than writing bespoke per-block getter logic — it already knows the entry-vs-override-field fallback chain (see `ModuleTwigExtensions::getItemData()`).

### Building a genuinely new block (case 3)

1. Create the entry type (+ its own field layout, reusing shared fields per above) in the CP, or a migration — see the root `CLAUDE.md`'s "Things to Avoid" re: not hand-editing `project.yaml`.
2. Add it to whichever builder field(s) should offer it (`pageBuilder`/`postBuilder`/`columnBuilder`'s `entryTypes` setting) — this is *where in the site* the block becomes insertable, so match it to the design's context (a full-page-only block probably shouldn't be offered inside a blog post's `postBuilder`).
3. Create `_blocks/<handle>.twig` matching the entry type's handle exactly.
4. Create `src/css/blocks/<handle>.pcss` and add its `@import` to `src/css/main.pcss` — see [`../../src/CLAUDE.md`](../../src/CLAUDE.md); nothing renders styled without that manual import line.
5. Only reach for JS if the design needs real interactivity beyond CSS (a carousel, a modal, a toggle) — add a Web Component under `src/js/Components/` (see existing ones: `modal.ts`, `accordion.ts`, `videoPlayer.ts`) and import it from `src/js/main.js`. A block that's just laid-out content — most of them — needs no JS at all.

`_testBlockBoilerplate.twig` in this folder looks like a leftover reference from an earlier block pattern (predates the CKEditor nested-entries approach — references a `widgetBlock`/`settings.children` shape none of the current blocks use). Don't copy it; start from an existing real block instead (`accordion.twig` for something simple, `hero.twig` for something with layout variants, or `activitySheet.twig` for the most recently added one).

## A content-safety note

Markup chunks and CKEditor-backed fields on a block entry (`heading`, `text`, `intro`, ...) are already HTML-Purified at save time — safe to render as-is. But plain fields on the same entry (the native `title`, a `PlainText` field like `excerpt`) are **not** purified, and a block template rendering one of those must go through `format.basic()` / `format.simple()` / `format.plain()` (see `_utilities/format.twig`) — never bare `{{ value|raw }}`. Those macros use Craft's `purify` filter (real HTMLPurifier), not `strip_tags()`, specifically so an allowed tag can't smuggle in an `onclick`/`javascript:` attribute.
