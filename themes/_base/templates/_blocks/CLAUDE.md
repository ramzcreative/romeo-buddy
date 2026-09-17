# `_blocks/` — the page builder

Quick orientation for how a "page builder" field's value turns into rendered HTML. See [`../CLAUDE.md`](../CLAUDE.md) for the rest of `templates/` and [`../../../CLAUDE.md`](../../../CLAUDE.md) for the `_base`/theme-folder picture.

## All three builder fields are now Matrix

`pageBuilder`, `postBuilder`, and `columnBuilder` were converted from `craft\ckeditor\Field` to real `craft\fields\Matrix` fields (blocks mode) on 2026-08-17/18 — see the `pageBuilder-ckeditor-to-matrix` migration project memory for the full history. `postBuilder` and `columnBuilder`'s conversions are smaller versions of `pageBuilder`'s: neither's entry types include Container/`columns`/`form`/`entryHeading` (`postBuilder`: `cards`/`spotlight`/`hero`/`image`/`video`/`accordion`/`blockquote`/`buttons`; `columnBuilder`, narrower still: `form`/`blockquote`/`buttons`/`image`), so none of the background-suppression-when-nested-in-Container work applied to either — just add `text` to the block set and convert the field type, same technique both times. Editors previously got a normal CKEditor rich-text toolbar plus a "createEntry" button that inserted a fully structured block (Hero, Cards, Accordion, ...) inline, as a nested Entry element, anywhere in the flow of text — now it's Craft's native Matrix block UI for all three.

This section's CKEditor-chunk description below is now historical/for reference — kept because `_builder.twig` still has to support the shape for any future CKEditor builder field, and because understanding it explains *why* the dispatcher looks the way it does. Iterating a **CKEditor-based** builder field's value yields a sequence of **chunks**, not a flat list of blocks:
- `chunk.type == 'markup'` — a run of plain rich-text HTML the editor typed directly (already HTML-Purified at save time, same as any other CKEditor field — see `purifyHtml: true` on these fields' config).
- anything else — a nested block, reachable as `chunk.entry` (a real `craft\elements\Entry`, one of whichever entry types that field's `entryTypes` setting allows).

A **Matrix** field's value (`pageBuilder`, `postBuilder`, `columnBuilder`, `containerBlocks`), by contrast, is a flat `craft\elements\db\EntryQuery` of entries directly — no chunk wrapper, no `markup` concept (freeform prose is an explicit `text` block instead, see below).

## `_builder.twig` — the dispatcher

`_builder.twig` doesn't read any specific field itself — the including template sets a `blocks` variable first, then includes it:

```twig
{% set blocks = entry.pageBuilder %}
{% include "_blocks/_builder" %}
```

It's written to accept **either shape** — a CKEditor chunk iterable or a raw Matrix `EntryQuery` — via two normalizations at the top:
1. `{% set blocks = blocks is instance of('craft\\elements\\db\\EntryQuery') ? blocks.all() : blocks %}` — a Matrix field's value is a *lazy* query; Twig can't provide `loop.last`/`loop.length` for it the way it can for an array or CKEditor's already-`Countable` `FieldData`, so it's materialized first.
2. Per item: `{% set chunkEntry = chunk is instance of('craft\\elements\\Entry') ? chunk : chunk.entry %}` — a CKEditor chunk exposes `.entry`; a Matrix block *is* the entry, so it's used directly.

This is what let `postBuilder`/`columnBuilder` (CKEditor, at the time) and `pageBuilder` (Matrix) share one dispatcher with no "which field type is this" flag anywhere — and now that all three are Matrix, the CKEditor-chunk branch just stays dormant/unused rather than needing to be removed, ready if a future builder field is ever added as CKEditor again.

**Why `is instance of(...)`, not `??` or `is defined`, for either check above**: it's tempting to write `chunk.entry ?? chunk` and move on, but Craft's Twig attribute resolution throws a hard `Twig\Error\RuntimeError` when the underlying PHP object doesn't have the property/method *and* has a magic `__call()` that throws on an unknown method (true of `craft\elements\Entry`, `craft\elements\GlobalSet`, and most Craft/Yii base classes) — this is a genuine invoked-method exception, not Twig's own "attribute doesn't exist" case, so neither `??` nor `is defined` catches it (confirmed the hard way — see `form.twig`'s identical `owner`-might-be-a-GlobalSet guard below). `is instance of(...)` sidesteps the problem entirely by never attempting the risky attribute access on the wrong shape. Also note `className()` (Craft's `get_class()` wrapper) is **not** a safe alternative here even though it looks similar — it throws a `TypeError` if given a plain array rather than an object, which `blocks` sometimes already is by the time `_builder.twig` sees it (e.g. after `_layouts/index.twig`'s `blocks|slice(1)`). `is instance of(...)` handles any input type — object, array, or null — without special-casing.

For each item it loops over, it either:
- includes **`_blocks/markup.twig`** (CKEditor `markup` chunks only — never matches a Matrix block, since a real Entry's `.type` is an `EntryType` object, not the string `'markup'`), or
- resolves `handle = chunkEntry.type.handle` and includes **`_blocks/#{handle}.twig`** — passing `entry: chunkEntry`, plus `index`, `last`, `hasHandle` (whether this block's type has already appeared earlier on the page), `parent`, `previous`, and `preloadImage` (true only for the very first block, so its image isn't lazy-loaded).

**A block template's filename must exactly match its entry type's handle.** That's the entire registration mechanism — there's no separate list in PHP/Twig mapping handles to templates to keep in sync. Add a new entry type to a builder field's `entryTypes` setting, drop a matching `_blocks/<handle>.twig` in this folder, and `_builder.twig` picks it up automatically.

Current block handles → templates (from `pageBuilder`'s `entryTypes`, see `config/project/fields/pageBuilder--*.yaml`): `entryHeading`, `text`, `blockquote`, `buttons`, `hero`, `imageText`, `banner`, `spotlight`, `slider`, `cards`, `accordion`, `posts`, `form` (+ `formContact`), `image`, `video`, `container`, `columns`, `related`, `gallery`, `stats`, `logos`, `testimonials`.

## Three builder fields, one dispatcher

| Field | Type | Used on | Included from | `entryTypes` scope |
|---|---|---|---|---|
| `pageBuilder` | Matrix | Any entry with the field (typically the `pages` section) | `_layouts/index.twig`, via `_sections/default.twig` | The full block set — everything above |
| `postBuilder` | Matrix | Post body content (blog, articles) | the section's `_detail.twig` | A narrower set — `cards`, `spotlight`, `hero`, `image`, `video`, `accordion`, `blockquote`, `buttons`, `text`, `related`, `gallery`, `stats`, `logos`, `testimonials`; no full-page layout blocks (Entry Heading, Image+Text, Container, Columns, Banner, Slider, Posts, Form) |
| `columnBuilder` | Matrix | Each individual column inside a **Columns** block | `_blocks/columns.twig`, once per column | Narrower still — `form`, `blockquote`, `buttons`, `image`, `text`, `related`, `gallery` |

All three funnel through the same `_blocks/_builder.twig` — only the field being read and the `parent` string passed down differ.

## Nesting: blocks that contain more blocks

Two block templates recursively re-include `_blocks/_builder`:
- **`columns.twig`** — each `column` sub-item has its own `columnBuilder` field (Matrix, converted 2026-08-18); the column loop sets `blocks = column['columnBuilder']` and includes `_blocks/_builder` with `parent: 'column'`.
- **`container.twig`** — the "Container" block (formerly "Content"/`contentBlock`, repurposed in the CKEditor→Matrix migration) groups arbitrary blocks under one wrapping `<div>` for a shared background/graphic. Its own `containerBlocks` field is a real Matrix field (not a recursive CKEditor field like the old `contentBlock` was), offering the same block set `pageBuilder` does minus Container itself (no self-nesting). Included with `parent: 'container'`.

`parent` is how a nested template (e.g. `markup.twig`) tells it's rendering inside a column vs. at the top level, for layout/class purposes — it's not a security or data boundary.

### Block backgrounds, and how nesting changes them

Section-level blocks carry a background **colour** (`backgroundRole`, the theme's colour roles) and a background **image** (`backgroundImage`) as a pair, applied through one partial, `_blocks/partials/background.twig`: `background.classes(entry)` on the block's own section element (`bg--{role}`, `section--has-bg`, `has-bg-image`) and `background.image(entry, preloadImage)` as its first child. That's the pattern `entryHeading`, `form`, `container` and `columns` always used, now shared. `banner` has colour only (its own image is the background); `text` and `blockquote` render no section of their own, so they have neither — a band behind them is what a Container is for.

Nesting, decided with Gary 2026-09-16 (`docs/business-blocks-spec.md` §3.6):
- **Inside a Container**, a block keeps its **colour** but not its **image** — the container carries the image for the group. (Until 2026-09-17 blocks dropped their colour here too.)
- **Inside a Columns column**, a block shows **neither**: the columns block's colour sets the tone for the row.

The partial reads the owner's type with `is instance of('craft\\elements\\Entry')`, because `form` is also rendered standalone with the footer GlobalSet as its owner. In the CP, `themes/_base/config/blockfields.json`'s `nested` rules hide the image on blocks inside a container and both fields on blocks inside a column — applied by a small CP script, since a block inside a column is two levels down in a slideout where the generated CSS can't tell which block owns a field (see `blockfields.json.md`). The same `owner.type` check is still how `image.twig`/`video.twig`/`accordion.twig` switch to a slim section inside a Container.

## The "layouts" sub-pattern

Several blocks are themselves thin dispatchers: they read a `layout<BlockName>` selector field on the block entry, default to a base variant, and include a layout-specific template under `_blocks/layouts/<blockName>/`:

```twig
{# hero.twig #}
{% set layout = (entry['layoutHero'] ?? '')|trim ?: 'standard' %}
{% include "_blocks/layouts/hero/#{layout}" with { entry: entry, ... } %}
```

The `|trim ?:` matters once the field exists: a block saved before its layout field was added has an empty value, not a missing one, and `??` alone would include `layouts/hero/`.

Current layout groups: `layouts/hero/` (`standard`, `product`, `video`; their copy is `partials/heroCopy.twig`), `layouts/imageText/` (`default`, `hero`, `show`), `layouts/cards/` (`grid`, `large`, `list`, `steps`), `layouts/sliders/` (`sliders`, `carousels`, `hero`, `product`). `gallery` (`grid`, `masonry`, `ticker`) `logos` (`row`, `grid`, `ticker`) and `testimonials` (`grid`, `carousel`, `single`) switch inside one template instead; both tickers are `_partials/ticker.twig`. Not every block has this indirection — `accordion.twig`, `text.twig`, `columns.twig`, etc. render directly with no layout variants.

## The first-block-becomes-header special case

`_layouts/index.twig` peeks at the *first* block before handing anything to `_builder.twig`. If it's a `hero` or `slider` block, that block is pulled out of the normal flow and rendered into the page's `<header>` instead (`heroArray`) — and if it's specifically a plain `hero` (or a slider whose own sub-layout is `hero`), an extra flag makes it float behind the nav bar rather than push content down (`heroOverlayArray`). This is why a page's first hero block won't show up again in the regular block loop — it's already been rendered separately, and `blocks|slice(1)` drops it before `_builder.twig` ever sees it.

## From a design to a block

The first real decision, before touching anything, is **which of these four this design actually is**:

1. **A CSS-only variation** of a block/layout that already exists (different spacing, color, an existing background swatch) — no new template, no new fields. Edit the existing `_blocks/<handle>.twig` (or its `css/blocks/<handle>.pcss`) directly, or fork it into a theme override if it's genuinely theme-specific — see [`../../../CLAUDE.md`](../../../CLAUDE.md).
2. **A new arrangement of the same kind of content** an existing block already handles — e.g. another way to lay out "image + heading + intro" alongside `imageText`'s existing `default`/`hero`/`show` variants. This is a new **layout variant**, not a new block: add the option to the block's `layout<BlockName>` select field, then a new `_blocks/layouts/<blockName>/<newLayout>.twig` (see "The layouts sub-pattern" above).
3. **A genuinely new kind of content** — nothing existing renders this shape of information. This is a real new **block type**: a new entry type, a new `_blocks/<handle>.twig`, its own fields. See the steps below.
4. **A piece reused *inside* other blocks**, not standalone on the page (a card, a heading treatment) — not a top-level block at all. Belongs under `_blocks/partials/` (see `partials/heading.twig`), included from whichever block(s) need it, not registered in any builder field's `entryTypes`.

If you're not sure which it is, look at how many existing entry types already have most of the fields the design needs (see below) — a design that reuses 80% of an existing block's fields with one new arrangement is almost always case 2, not case 3.

### Reuse fields before creating new ones

Craft fields are defined once and attached to as many entry types' field layouts as want them — `heading`, `image`, `intro`, `subheading`, `preheading`, `buttons`, `background`, `items`, `reverse`, `alignment`, `offset`, `width` are all already shared across most of the existing block entry types (e.g. the `heading` field alone is on 10 different entry types, `image` on 9 — check `grep -l "fieldUid: <uid>" config/project/entryTypes/*.yaml` for any field in `config/project/fields/` to see who else already uses it). A design's "headline + intro + image" section almost never needs its own new heading/intro/image fields — attach the existing ones to the new entry type's field layout instead. Only add a new field when the design genuinely needs data nothing else captures (an ISBN, a price, a rating).

Repeatable "item" content is the shared `items` Matrix field holding the shared `item` entry type — `cards`, `slider`, `imageText`, `banner` and `spotlight` all use it, which is what lets a block be switched between those types without retyping anything. Render each one through the `itemData()` Twig function (`modules/stablestwigextensions`) rather than reading its fields directly: it resolves the entry-vs-item override chain defined in `config/stables/items.php`. Prime the query with `.with(itemEagerLoadPaths())` so a page of items isn't one query each.

Because the item type carries the union of every block's fields, a block that doesn't use one of them hides it in the CP via `config/stables/blockfields.php` — Craft has no owner-aware field condition, so it's done as generated CSS. That same config lists which block types may be switched between. A theme whose fork needs different fields replaces that block's rules in `themes/<handle>/config/blockfields.json` (and an item key's chain in `items.json`), so a theme switch changes what editors see with no site file edit.

The same file's `layoutFields` handles the other direction: one of the block's *own* fields that only applies to some of its layouts (`sliderNav` shows on the `hero` layout only). Craft's field conditions are evaluated server-side against the saved entry, so they can't follow the editor's clicks — the layout selector is a radio group, so the generated CSS reads `:checked` and the field appears the moment the layout is picked.

### Building a genuinely new block (case 3)

1. Create the entry type (+ its own field layout, reusing shared fields per above) in the CP, or a migration — see the root `CLAUDE.md`'s "Things to Avoid" re: not hand-editing `project.yaml`.
2. Add it to whichever builder field(s) should offer it (`pageBuilder`/`postBuilder`/`columnBuilder`'s `entryTypes` setting) — this is *where in the site* the block becomes insertable, so match it to the design's context (a full-page-only block probably shouldn't be offered inside a blog post's `postBuilder`).
3. Create `_blocks/<handle>.twig` matching the entry type's handle exactly.
4. Create `src/css/blocks/<handle>.pcss` and add its `@import` to `src/css/main.pcss` — see [`../../src/CLAUDE.md`](../../src/CLAUDE.md); nothing renders styled without that manual import line.
5. Only reach for JS if the design needs real interactivity beyond CSS (a carousel, a modal, a toggle) — add a Web Component under `src/js/Components/` (see existing ones: `modal.ts`, `accordion.ts`, `videoPlayer.ts`) and import it from `src/js/main.js`. A block that's just laid-out content — most of them — needs no JS at all.

`_testBlockBoilerplate.twig` in this folder is the starting point for a new block — copy it to `_blocks/<handle>.twig` and edit. It carries the current shape (the `entry` a block actually receives, the shared `items` field, eager-load priming) and documents what `itemData()` resolves versus what you read off the element directly. It was rewritten on 2026-08-19; before that it described a `widgetBlock`/`settings.children` shape no current block uses, so ignore any older advice not to copy it. For a real-world reference alongside it, see `accordion.twig` for something simple and `hero.twig` for something with layout variants.

## A content-safety note

Markup chunks and CKEditor-backed fields on a block entry (`heading`, `text`, `intro`, ...) are already HTML-Purified at save time — safe to render as-is. But plain fields on the same entry (the native `title`, a `PlainText` field like `excerpt`) are **not** purified, and a block template rendering one of those must go through `format.basic()` / `format.simple()` / `format.plain()` (see `_utilities/format.twig`) — never bare `{{ value|raw }}`. Those macros use Craft's `purify` filter (real HTMLPurifier), not `strip_tags()`, specifically so an allowed tag can't smuggle in an `onclick`/`javascript:` attribute.
