# `_blocks/` — the page builder

Quick orientation for how a "page builder" field's value turns into rendered HTML. See [`../CLAUDE.md`](../CLAUDE.md) for the rest of `templates/` and [`../../CLAUDE.md`](../../CLAUDE.md) for the `_base`/theme-folder picture.

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

## Adding a new block type

1. Create the entry type (+ its own field layout) in the CP, or a migration — see the root `CLAUDE.md`'s "Things to Avoid" re: not hand-editing `project.yaml`.
2. Add it to whichever builder field(s) should offer it (`pageBuilder`/`postBuilder`/`columnBuilder`'s `entryTypes` setting).
3. Create `_blocks/<handle>.twig` matching the entry type's handle exactly.

`_testBlockBoilerplate.twig` in this folder looks like a leftover reference from an earlier block pattern (predates the CKEditor nested-entries approach — references a `widgetBlock`/`settings.children` shape none of the current blocks use). Don't copy it; start from an existing real block instead (`accordion.twig` for something simple, `hero.twig` for something with layout variants, or `activitySheet.twig` for the most recently added one).

## A content-safety note

Markup chunks and CKEditor-backed fields on a block entry (`heading`, `text`, `intro`, ...) are already HTML-Purified at save time — safe to render as-is. But plain fields on the same entry (the native `title`, a `PlainText` field like `excerpt`) are **not** purified, and a block template rendering one of those must go through `format.basic()` / `format.simple()` / `format.plain()` (see `_utilities/format.twig`) — never bare `{{ value|raw }}`. Those macros use Craft's `purify` filter (real HTMLPurifier), not `strip_tags()`, specifically so an allowed tag can't smuggle in an `onclick`/`javascript:` attribute.
