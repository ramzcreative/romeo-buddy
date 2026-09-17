# Theme Designer Blocks — choosing a theme's blocks and fields without editing config

Written 2026-09-14 from Gary's request to give `blockfields` a GUI in Theme Designer. Every "today" claim in §2 was read
in the code on that date and carries its file and line. **Status: draft, unbuilt; all decisions made (§11).** Builds on
[`theme-config-spec.md`](theme-config-spec.md), whose §8 sketched this tab.

## 1. Goal

The first draft also had Theme Designer create fields, build page templates and switch sections. Comparing other CMSs
showed that was re-doing what the Craft CP already does well, and those were the parts where content could be lost.
The line this spec draws:

**Theme Designer does what the CP can't. The CP keeps doing what it already does, and Theme Designer links to it.**

In scope:
1. **Blocks tab, per site theme.** For each page-builder block, choose which fields show on the block (parent) and on
   everything nested in it (child), which layouts the block offers, and whether the theme offers the block at all. It
   writes `themes/<handle>/config/blockfields.json`.
2. **No required fields or minimums on blocks.** Templates guard instead, so any field can be hidden (§3).
3. **New block.** An entry type from a preset, a stub template and CSS **in the theme's own folder**, and nothing to do
   in other themes (§5).
4. **Theme thumbnail upload** (§6).

Left to the CP: §7.

**Why:** themes are about to diverge a lot. A softball player-profile site came in and nothing existing fits it, so it
needs a very custom theme. Switching to a theme like that should need no site file edits. The model matches WordPress
block themes, where content is global and the theme curates the editor, rather than Shopify's, where the theme owns
the content and switching loses it.

## 2. What we have today

| # | Claim | Where |
|---|---|---|
| T1 | A theme can already replace one block's `itemFields` or `ownFields` from `themes/<h>/config/blockfields.json`. Units are replaced whole. `switchGroups` and `layoutOptions` are site-only. No theme has a `config/` folder yet. | `themepicker/services/ThemeConfig.php`; `BlockFieldCss.php:42`; `stablestwigextensions/Module.php:32`; theme-config-spec §4.2 |
| T2 | **`ownFields` supports only `perLayout`.** The validator refuses `ownFields.hidden` because `generate()` never reads it. | `BlockFieldCss.php:53`, `:61`, `:248` |
| T3 | **"Child" means only the shared `item` type.** The inline item rules hardcode `.matrixblock[data-type="item"]`. `blockHeading` (on 12 block types, and on `form` as `formHeading`), `accordionItem`, `button` and `column` can't be targeted. | `BlockFieldCss.php:188`, `:255` |
| T3a | **The slideout item rule leaks today.** In cards view the rule is `.cp-screen:has(.so-content > [data-layout-tab=…]) [data-attribute="<field>"]`, not scoped to `item`, so it also hides a same-handle field on the block's **Block Heading**. From the code, it's almost certainly hiding: Block Heading `subheading` on slider; `preheading`, `subheading` and `intro` on imageText; `preheading` and `subheading` on banner; `preheading` on spotlight. The inline rules have the same kind of reach into nested blocks inside `container`/`columns`. **Only one of these renders:** `partials/heading.twig` outputs Block Heading `heading` and `intro` only, so the lost field is Intro on Image + Text; Preheading and Subheading never render on any block. | `BlockFieldCss.php:209`–`:213`; `blockfields.php:74`–`:113`; `_blocks/partials/heading.twig` |
| T4 | **Nothing hides a whole block type.** Craft's Matrix field has no per-entry-type condition. `Matrix::EVENT_DEFINE_ENTRY_TYPES` is read by **blocks-view** input (`containerBlocks`, `columnBuilder`) and by `MatrixController`, but **not** by the cards-view create menu, which calls `getEntryTypes()` directly. | `vendor/craftcms/cms/src/fields/Matrix.php:476`, `:1112`, `:1205`, `:1228` |
| T5 | **Filtering the field's saved `entryTypes`, even only in memory, is not a safe hide.** Existing blocks still render. But nested-entry validation checks `typeId` against the field's `_entryTypes`, so a page holding that block can't be saved. **`EVENT_DEFINE_ENTRY_TYPES` isn't read by validation**, so it is safe for blocks-view fields. | `Entry.php:1080`, `:1711`; `Matrix.php:515`, `:1311`; `Entry.php:1763` |
| T6 | **Cards-view Add menus** (`pageBuilder`, `postBuilder`) are Garnish disclosure menus moved to `<body>` and built as soon as the field loads. There are **N+1** of them: a combined menu with group headings, shown when the container is narrow (slideouts), plus one per group. **Items carry no type id**, only an icon and a label. `hideItem()` → `updateVisibility()` hides an emptied list, its heading and its divider. Arrow keys skip hidden items, and menu search doesn't unhide them. **Blocks-view** create items carry `data-type`, and each block's action menu adds "Add {type} above" items that `MatrixInput.js` re-shows on every open. | `NestedElementManager.js:66`–`:177`; `DisclosureMenu.js:83`, `:638`, `:875`, `:916`; `Craft.js:3300`; `Matrix/create-button.twig`; `Matrix/block.twig:148`; `MatrixInput.js:666`, `:704` |
| T7 | The switch-type dropdown is already filtered in JS **by entry type id**, using `window.stablesSwitchGroups` and a `MutationObserver` on `<body>`. A type with no entry in the map gets the **unfiltered** list. The script and its generated data are re-injected with every slideout response, so each slideout adds another listener. | `blockSwitchGroups.js:25`, `:42`, `:74`; `BlockFieldCss.php:537`; `Module.php:53`; `CpScreenResponseFormatter.php:111` |
| T8 | **A theme can already own a block template.** On site requests, `themes/<h>/templates/` (when that folder exists) is searched before `_base`. Craft tries the bare name, `.twig`, `.html`, `/index.twig` and `/index.html`. A handle with no template anywhere renders through `resolveBlockFallback()` as cards/grid, and nothing is saved. **When the block has no items, the fallback invents one from the block's own fields** and renders it. Theme block CSS goes in `themes/<h>/src/css/blocks/`, imported from the theme's `main.pcss`/`critical.pcss`. **Every block in `pageBuilder` has a `_base` template today**, and no theme has any template. | `themes/CLAUDE.md:38`, `:57`, `:63`; themepicker `Module.php:93`; `View.php:2510`; `_blocks/_builder.twig:52`; `BlockFallback.php:51`–`:57`; `ls` |
| T9 | **Where block types are offered:** `pageBuilder` (cards view, 4 groups) on `page`, `landingPage`, `event`, `job`; `postBuilder` on `blogPost`; `containerBlocks` on `container`; `columnBuilder` on `column`. `footerForm` (type `form`) is on the **footer global set**, not a page. | `config/project/fields/`, `entryTypes/`, `globalSets/footer--*.yaml` |
| T10 | **Blocks require things today.** Required: `blockquote.textPlain`, `video.video`, `image.image`, `accordionItem.heading`. `items` has `minEntries: 1`. Items blocks already guard with `{% if items %}` (`cards.twig:12`, `slider.twig:11`, `spotlight.twig:8`, `banner.twig:11`, `imageText.twig:9`). **Four templates don't guard:** `image.twig` renders its `<article>` and a placeholder with no asset; `blockquote.twig` renders an empty `<p>`; `accordion.twig` has no empty-list guard and renders a heading per item even when it's empty; `video.twig` guards the player but still renders its `<article>` and heading. | `entryTypes/*.yaml`; `fields/items--*.yaml:21`; `_blocks/*.twig` |
| T11 | **Theme Designer already creates a block** for library extensions. `ThemeInstaller::registerNewBlockExtension()` saves an entry type holding `items`, appends it to `pageBuilder`, copies the template and CSS **into `_base`**, and adds the `main.pcss` import. It appends with **no group**, so the type lands under "General". A group is stored only on a usage clone that has `original` set, and appending to `getEntryTypes()` keeps the existing types' groups. The template and CSS are written before the entry type is saved, and aren't removed if the field save fails. | `ThemeInstaller.php:383`–`:462`, `:554`; `vendor/.../services/Entries.php:1605`; `models/EntryType.php:505`–`:510`; `Matrix.php:382` |
| T12 | **Adding a Designer tab** means: a `navItems` entry and include branch (`index.twig:16`, `:235`), an icon (`_flyout-nav.twig`), a `_tab-*.twig`, a data key in `actionIndex` (`DesignerController.php:896`–`:985`), actions (plus `SITE_THEME_ONLY_ACTIONS`, `:740`), and `dist/theme-designer.{js,css}`. The UI is hand-written `td-*` code with no Garnish; there's a toggle, modal, source badge and notice kit. **No JSON writer is atomic**; only font uploads use temp + `rename()`. Access is devMode plus `requireAdmin()`, whose default also requires `allowAdminChanges`. **Usage counts** are field-aware (`blockExtensionUsageCount()`, `layoutExtensionUsageCount()`), not `LIKE`. | `themedesigner/`; `DesignerController.php:1861`, `:1877`, `:11966`; `web/Controller.php:496` |
| T13 | **Theme Designer is a site request**, so `ThemeConfig::get()` would use the *rendered* theme. `ThemeConfig::explain($name, $handle)` returns config and per-unit sources for any theme. `themeHandle()` is private. **A bad theme file throws under devMode on every CP request.** | `ThemeConfig.php:119`, `:411`, `:443` |
| T14 | **Theme thumbnails**: `theme.json` `"thumbnail"` → `themes/<h>/thumbnail.png`, 1600×1000 PNG, about 1.9 MB. No code uploads one. The `ramz-theme-publish` skill screenshots it and checks the name `thumbnail.png`. The library proxy and `LibraryClient` accept png, jpg and webp. New variants get no thumbnail, but Theme Picker renders variant thumbnails when they exist. Theme Picker cards use `alt="{{ name }} preview"`; the Library uses `alt=""`. | `ThemeRegistry.php:87`; `SKILL.md:28`, `:33`, `:40`; `DesignerController.php:1427`, `:1273`; `LibraryClient.php:97`; themepicker `templates/index.twig:70`, `_variant-cards.twig:23` |
| T15 | New site theme = `copyDirectory()` of the source, including `templates/`, `src/` and `config/`. Remove theme deletes the whole folder. | `DesignerController.php:1249`, `:4769` |
| T16 | `stablestwigextensions` is stables-local; romeo-buddy has its own copy of `BlockFieldCss` and registers nothing with `ThemeConfig`. | theme-config-spec T11, §7.1 |
| T17 | **Layout fields:** each is on exactly one block today (`layoutButton`, `layoutCards`, `layoutImageText`, `layoutPosts`, `layoutSliders` are buttonbox; **`layoutForms` is a Dropdown**). `layoutOptionRules()` isn't scoped to a block, and targets `label.buttonbox-button`. `perLayoutFieldRules()` uses `input:checked`, so on a Dropdown a per-layout rule hides the field on **every** layout. | project config; `BlockFieldCss.php:299`, `:376` |
| T18 | **The CP never follows a page's own theme.** A landing page with a Theme Override renders in another theme, but the CP shows the stored site theme's rules (theme-config-spec §4.4 known gap). | `ThemeConfig.php:426`; `ThemeRegistry.php:408` |
| T19 | `BlockFieldCss` runs on **every** CP request, AJAX and autosave included. | `stablestwigextensions/Module.php` (`EVENT_INIT`) |
| T20 | **Nothing else depends on block required fields.** Inline editing saves with validation off; the SEO FAQ builder skips empty accordion items; content import relies on `saveElement()` and would import an empty block silently after §3. | `InlineEditController.php:74`, `:171`; `StructuredDataBuilder.php:643`; `ImportController` |

## 3. Blocks don't require anything; templates guard

Decided 2026-09-14. Hiding a required field would leave editors unable to save (T10), and the templates can do the
job.

- **Migration:**
  - Turn off `required` on the block and nested-type layout elements listed in T10, and set `items` `minEntries` to
    null.
  - Edit the existing layout elements' flags in place and save through the entry type and field services. Never
    rebuild a layout: content is keyed by layout element uid.
  - Nothing is deleted, so it's safe whichever order project config and migrations run in.
  - Section entry types (`page`, `blogPost`, `event`, `job`, `landingPage`) keep their required fields. They aren't
    blocks. (`formContact` is in no section and no Matrix field; leave it alone.)
- **An empty block renders nothing**, not an empty wrapper:
  - `image`: no asset → nothing.
  - `blockquote`: no `textPlain` → nothing.
  - `accordion`: no items → nothing, and an item with no heading is skipped (an empty `<h2>` is an ADA failure).
  - `video`: nothing unless there's a playable video.
- **The same rule everywhere after this:**
  - A new block's stub starts with its guard (§5).
  - A theme fork of those four keeps it. `themes/CLAUDE.md` § fork rules gets a line.
  - **The fallback too:** `BlockFallback::render()` renders nothing when the block has no items and nothing to build a
    card from (no heading, image, intro or text), instead of an empty card (T8).
- **Content import:** its verify step reports blocks that would render nothing (T20).
- **What editors lose:** the "can't be blank" error on save. An empty block publishes silently as nothing.
  - A later option is an "empty block" marker in the admin bar for logged-in editors. Not in this spec.

## 4. The Blocks tab

### 4.1 What the config can say

Five changes to what `BlockFieldCss` reads and validates. Each one works in site config (`blockfields.php`) and in a
theme file, so the two shapes stay the same.

```json
{
  "blocks": {
    "slider": { "available": false },
    "cards": {
      "ownFields":     { "hidden": ["sliderNav"] },
      "itemFields":    { "hidden": ["itemIcon", "text"] },
      "childFields":   { "blockHeading": { "hidden": ["subheading"] } },
      "layoutOptions": { "layoutCards": ["large"] }
    }
  }
}
```

| Change | What it does | Decided |
|---|---|---|
| **`builderFields`** (site only) | The Matrix fields that offer blocks, e.g. `['pageBuilder', 'postBuilder', 'containerBlocks', 'columnBuilder']` (§4.2) | — |
| **`ownFields.hidden`** | Hides one of the block's own fields outright (T2) | — |
| **`childFields.<typeHandle>`** (`hidden`, `perLayout`) | The same rules for any type nested in the block. `itemFields` stays, and means `childFields.item`; a block may not use both. | Q3: every nested type |
| **`available`** | Whether this theme offers the block (§4.4). **When absent, the block is offered if the theme or `_base` has a template for it.** | Q2: yes |
| **`layoutOptions` becomes theme-changeable** | Its shape doesn't change; it was site-only until now (T1) | Q2: yes |

**Registered theme units:**
- `blocks.*.ownFields` (validator now allows `hidden`)
- `blocks.*.itemFields`
- `blocks.*.childFields`
- `blocks.*.available` (boolean)
- `blocks.*.layoutOptions`

`switchGroups` and `builderFields` stay site-only. **This reverses theme-config-spec §5's "what a client is offered is
site-only"** for blocks and layouts; that spec's §5 and §4.2 table get a line pointing here.

**Why `available` defaults from templates.** A theme can't offer a block it can't render. The default makes that hold
without anyone writing a rule:
- A block whose only template is in the softball theme isn't offered anywhere else, including themes created later.
- A copy of the softball theme (T15) brings the template, so the copy offers it.
- Every block has a `_base` template today (T8), so nothing changes on any current site.
- The check tries the five names Craft tries (T8) in the theme's `templates/_blocks/` and then `_base`'s.

**Every rule targets exactly its own level.** This fixes T3a, and it's required before `ownFields.hidden` and
`childFields` exist, or they would leak the same way.
- **Child rules** are scoped to `.matrixblock[data-type="<child>"]` in the slideout form as well as inline.
- **Own-field rules** exclude anything inside a nested block:
  `[data-attribute="f"]:not(.matrixblock [data-attribute="f"])` in the slideout, and
  `.matrixblock[data-type="<block>"] [data-attribute="f"]:not(.matrixblock[data-type="<block>"] .matrixblock [data-attribute="f"])`
  inline. Complex `:not()` works in every browser the CP supports.
- **Fields T3a has been hiding by accident reappear**, and that's intended: Block Heading **Intro** on Image + Text,
  and Block Heading **Pre Heading** and **Subheading** on Slider, Image + Text, Banner and Spotlight. Those two stay on
  `blockHeading` (decided 2026-09-15). This is a boilerplate, and themes will use them, whatever `_base`'s heading
  partial renders today.

**Checked when the CSS is generated, not just in the tab.** A hand-edited or stale theme file (a field renamed in the CP)
can name something that isn't on the block. Every rule is checked against the real layout:
- the field is on the block, or on that child type;
- the child type is nested in the block;
- the layout field is on the block and is a **buttonbox** field (T17);
- the layout values exist (as `knownOptionValues()` does today).

A rule that fails is **dropped and logged**, like an unknown block today, rather than thrown, so renaming a field in
the CP never locks the CP under devMode. The Blocks tab shows it as a **Stale rule** with a remove button. This matters
most for `layoutOptions`, whose selector isn't scoped to a block: `blocks.cards.layoutOptions.layoutSliders` would
otherwise hide slider's options everywhere.

**`assertNoOverlaps()`** extends to `ownFields` and `childFields`. It also refuses `itemFields` and `childFields.item`
on the same block.

### 4.2 Blocks, builder fields and children

- **Builder fields** come from the site-only `builderFields` key. Each must be a Matrix field, and a missing one is
  logged and skipped.
- **Blocks** are the types those fields allow.
- **Children** are any other types nested under a block (`item`, `button`, `accordionItem`, `blockHeading`, `column`).
- **Global sets are out**, because `footerForm` isn't a builder field: the footer's form stays whatever a theme offers.

**One resolver for effective rules.** A block's effective state (offered, layouts, shown fields with per-layout
conditions) is worked out by one craft-modules service. `BlockFieldCss` turns it into CSS, the Blocks tab renders it,
and site-types-spec §5 writes it into `requirements.json`, so the three can't disagree. The stables-specific pieces
(`builderFields`, unit registration) stay site config.

**Why a list, not worked out from the content model.** Deriving it ("Matrix fields on section entry types") matches
today's config, but it breaks the day someone adds a `buttons` Matrix field to a page type, which would turn `button`
into a block. One reviewed line of site config is sturdier. Theme Designer reads it through
`ThemeConfig::explain()`, so craft-modules still names no stables handle.

### 4.3 The screen — "C · Editor preview"

**Chosen by Gary 2026-09-14** from three mockup directions (canvas: https://claude.ai/artifact/DF2KCnjgmAFhbmv6VKDgt4).
The block's editor view is drawn as editors see it in the slideout, and each field is controlled where it sits. Same
page head and card kit as the other tabs (T12); a top-level **Blocks** item after Components.

**Three columns:** block tiles (left, 340px) · editor preview (centre) · sidebar (right, 260px).

**Block tiles (left)**
- A two-column grid of every block (§4.2), grouped the way `pageBuilder` groups them, with a filter above.
- Each tile is a `button` (selected: `aria-pressed`) holding:
  - the name;
  - an **Offered** switch (its own control, not nested in the tile button);
  - a status line: **Site rules**, **N changes**, **Not offered · N in use**, **Stale rule**, or **This theme's block**.
- A block that isn't offered by default says why ("No template in this theme"). Turning it on anyway is allowed, and it
  renders through the cards fallback.

**Editor preview (centre)** — a schematic slideout built from the block's real field layout:
- **Header:** the block name, a source badge (**This theme** / **Site rules**), and a **Preview layout** radio group of
  the block's layout options.
  - A layout this theme doesn't offer is struck through, but can still be previewed.
  - A Dropdown layout field (`layoutForms`, T17) previews without layout choices: "Change it to Button Box in the CP to
    choose layouts per theme".
- **Tabs** exactly as the layout has them (Content / Settings). A tab with hidden fields shows a count ("2 hidden").
- **Fields** in layout order, with the layout's labels, instructions and element widths (25/50/75/100%). Each renders
  a placeholder by field class:
  - Plain Text: a line;
  - CKEditor: a taller box;
  - Assets: a tile row;
  - Lightswitch: a switch;
  - Button Box and Dropdown: its real options;
  - Entries: a chip;
  - Matrix: a nested card (below);
  - an unknown class: a plain box with the class's short name.
- **Nested Matrix fields** (Items, Block Heading, Buttons) render one sample card ("Item 1") with the child type's own
  fields and controls, so the child level is edited in place.
- **Each field's control** sits at its top-right: a three-option radio group, **Shown / Hidden / Only on layouts**,
  in a `fieldset` whose legend is the field label.
  - "Only on layouts" opens a popover with one checkbox per layout; focus returns to the control when it closes.
  - It isn't offered on the layout field itself.
- **Every field stays in place,** so its position still makes sense:
  - **Hidden:** striped, with **Hidden · this theme** or **Hidden · site rule**, so the source is visible.
  - **Only on some layouts:** an **Only on Grid, List · this theme** (or **· site rule**) pill. When the previewed
    layout excludes the field, it's dimmed with "Not on Large".
  - **Shown only because this theme says so:** a **Shown · this theme** pill. Every pill names its source.
  - **Conditional:** "Shows when Pull From an Entry is on".
  - **Changed in this theme:** a violet outline.
- **Placeholders, never sample content.** Inputs are inert and `aria-hidden`. The labels and the controls are the only
  reachable elements, so the preview is a set of labelled radio groups to assistive tech, not a fake form.
- **Why schematic, not Craft's real field HTML:** `getInputHtml()` needs an element context and loads each field's
  CP JS and CSS (CKEditor and so on) into Theme Designer, which is a site URL without CP assets. Rendering from field
  classes and layout data keeps the preview in step with Craft without copying Craft's DOM, and without running field
  scripts.
- **Colours:** the preview uses a light, CP-like palette so it reads as "the editor's view". Its text, pills and
  control borders meet 4.5:1 (text) and 3:1 (controls).

**Sidebar (right)**
- **Offered** switch. When it's off:
  - a notice above the preview says "Editors can't add Cards while Coastal is active". The preview is outlined, not
    faded, because its rules stay editable and have to keep their contrast;
  - the usage count shows ("4 blocks on 3 pages keep rendering"), with page links from the field-aware counts
    (`blockExtensionUsageCount()`, T12; grouping by page is new);
  - **those blocks keep rendering** (§4.4).
- **Layouts offered:** one checkbox per option, with the default marked.
- **Changes in Coastal:** one line per unit that differs from site rules, each with a revert button.
- **Stale rules** (§4.1): one line each, with **Remove**.
- **Read-only, "Set for the whole site":** the block's switch group.
- **Actions:** **Save**, **Use site rules** (deletes the block from the theme file, and the file once it's empty), and
  **Edit fields in the CP** (`EntryType::getCpEditUrl()`). A field added in the CP appears in the preview on the next
  load.
- Save results go to a `role="status"` region.

**New block** is a button in the page head that opens the §5 dialog.

**Refused on the server** (the same checks as §4.1, plus):
- "Only on layouts…" with no layout checked.
- Turning off every option of a layout field.
- Turning off the layout field's **default** option. New blocks would start on a hidden value; change the default in
  the CP first.

**Warned, not refused:** a field shown only on a layout this theme doesn't offer. Existing blocks may use that layout.

**Base, variants and locked themes are read-only** (the preview still renders; every control is disabled):
- **Base** shows `config/stables/blockfields.php`, with "Edit this file to change rules for every theme". Theme
  Designer doesn't write PHP config.
- **Variants** get the existing `_page-theme-readonly` card.
- **Locked themes** get the locked notice.

**Only where the site supports it:** the tab appears only when the site has registered `blockfields` with
`ThemeConfig`. Otherwise it stays dormant, e.g. on romeo-buddy until it's ported (T16).

**As built (2026-09-16, craft-modules `BlocksTab` + `_tab-blocks.twig` + `wireBlocks` in theme-designer.js):**
- **Data** comes from `BlockRules::resolve()` for the theme and a new `BlockRules::resolveSite()` (the same resolution
  over site config only), so every pill can say "this theme" or "site rule" and a save can drop units equal to the
  site's. `ThemeConfig::explain()` lists site units too; a unit is the theme's when its source is the theme handle.
- **The Offered switch saves on its own** (tile and sidebar). Field and layout changes wait for **Save**; a tile
  shows "Unsaved", and leaving the page asks first.
- **"Only on layouts"** shows the layout checkboxes inline under the radio group instead of in a popover: same
  controls, nothing to trap or return focus from.
- **The default layout's checkbox is disabled** with a note, as well as refused on the server.
- **Stale rule → Remove** saves the block's current effective rules, which the resolver has already cleaned.
- **A Theme variant** shows the live site theme's rules, read-only, inside the existing variant card's disabled
  fieldset.
- **New block** (§5) isn't in the head yet; that's 2.2.
- **Saves re-render from disk in the same request:** `BlockRules::forget()` and `ThemeConfig::forget()` clear the
  per-request memo after a write.
- **Checked:** the tab for a theme, Base, a Theme variant; hide a nested field, Save, the theme file, the pill and
  outline, focus kept on the control; Offered off and on (the block entry is dropped when it matches the site);
  Use site rules (the file is deleted when empty); server refusals (variant, Base, unknown block, bad JSON, hidden
  default layout, no CSRF); a save on the active theme reaching the CP's generated rules. **Not checked:** VoiceOver,
  a locked theme in the browser (the server check is the same `isThemeLocked()` every tab uses).

### 4.4 What "not offered" does

The CP applies the stored theme for the site being edited, as it does today. Landing pages with a Theme Override still
get the site theme's choices in the CP (T18). That's a known gap; following each entry's theme is theme-config-spec
§8.

1. **Blocks-view builder fields** (`containerBlocks`, `columnBuilder`): a `Matrix::EVENT_DEFINE_ENTRY_TYPES` handler,
   CP requests only, removes blocks this theme doesn't offer. This covers the create menu and each block's "Add
   {type} above" items, which JS can't keep hidden (T6). Validation doesn't read the event, so pages that already hold
   the block still save (T5). Verify both (V7).
2. **Cards-view builder fields** (`pageBuilder`, `postBuilder`): the event doesn't reach these menus (T4), so a new
   `blockAvailability.js` does it:
   - It gets the field's **own** create buttons: `.menubtn[aria-controls]` that are direct create controls of that
     field, not a nested field's or a block's action button. For each it takes `data('disclosureMenu')`.
   - It works through **all N+1 menus**: the combined narrow-width menu and one per group (T6).
   - It calls `hideItem()` on rows whose label matches. The label is the field-level name override if there is one,
     otherwise the type name, translated as `NestedElementManager` does. **Labels, because these items carry no id.**
     If two types in one field share a label, the generator skips both and logs a warning.
   - It hides a group button left with nothing, moves the plus icon to the first visible button, and re-runs the
     narrow-width check.
   - It's **idempotent**: it marks what it has handled, and a second copy of the script from a slideout response (T7)
     does nothing.
3. **Switch-type dropdown:** `switchGroupMap()` keeps **every** group member as a key and removes unoffered ids only
   from the lists. A slider shown with slider off still gets a filtered list, not the unfiltered one T7 falls back
   to.
4. **Layouts:** the existing `layoutOptionRules()`, now fed from the theme's units and checked per block (§4.1).
5. **Existing content keeps rendering** (Q1). Hiding means "can't add new ones" and never changes what's live, so
   switching themes can't remove content. The same applies to a hidden layout.
6. **Not an access control.** A crafted request can still create a cards-view block the theme hides. It's editor UX,
   and the front end renders it anyway.
7. **Never done by filtering the field's saved `entryTypes`:** that stops pages that hold the block from saving (T5).

**Skills.** `theme-picker/themes/config` prints config, not a template-derived default. So stables adds a console
command, `php craft stablestwigextensions/blocks/offered [--theme=] [--site=] [--json]`, printing each
block's effective offered state, layouts and hidden fields, with sources:
- `ramz-page-scaffold` switches its field check to it and skips unoffered blocks.
- `ramz-content-import` gains the same check; today it reads nothing (T20).
- **As built (2026-09-16):** both skills run `blocks/offered --json` before drafting and skip unoffered blocks,
  hidden layouts and fields hidden for the chosen layout; `ramz-page-scaffold` §5's icon note points at the same
  report.

### 4.5 Reading and saving

- **Read:** `ThemeConfig::explain('blockfields', $handle)` (T13). Never `get()`.
- **Save (`actionSaveBlockRules`):**
  1. Build the block's units from the post and run the §4.1 and §4.3 checks against Craft.
  2. Merge into the theme file, leaving every other block as it is. A unit equal to the site's value is dropped
     rather than stored as a copy, so it keeps following site rules.
  3. Run `ThemeConfig::checkThemeFiles(['config/blockfields.json' => $json], false, true)` over the result.
  4. Write atomically: a **dot-named** temp file in the same folder (`.blockfields.json.tmp`), then `rename()`. A
     half-written file would throw on every CP request (T13). The dot matters because publish skips dotfiles, and
     strict checks refuse any non-JSON file in `config/`. This is the first atomic JSON writer in
     `DesignerController`; moving the other writers onto it is not part of this change.
- **Gating:** `requirePostRequest` + CSRF; the action goes in `SITE_THEME_ONLY_ACTIONS`; `isThemeLocked()` is checked.
- **One craft-modules change:** `ThemeConfig` makes the stored-theme lookup public (`currentThemeHandle()`), so
  `BlockFieldCss` checks templates for the same theme `get()` used.

### 4.6 Budget, security, ADA

**Budget**
`BlockFieldCss` runs on every CP request, autosaves included (T19), so step 2 **caches** the generated CSS and the two
JS maps. The key covers everything they depend on:
- the stored theme;
- project config `dateModified`, which moves with any field or entry type change;
- `filemtime` of `blockfields.php`, the theme's `config/blockfields.json`, and both `templates/_blocks/` folders
  (adding or removing a template changes the folder's mtime).

With the cache warm: one theme lookup and about 5 `stat()` calls per request. With it cold: the layout walk plus up to
10 `is_file()` per block (5 names × 2 roots). No database query is added in either case.

**Security**
- Admin plus devMode, as for all of Theme Designer.
- Handles, field handles and layout values are checked against Craft before any write. Nothing from the post reaches
  a path.
- Selectors are escaped as today.

**ADA** (Designer UI and CP)
- Each field's three-way choice is a `fieldset`/`legend` radio group. Layouts and Offered are real checkboxes or
  switch buttons (`aria-pressed`).
- Save results go to `role="status"`, errors to `role="alert"`.
- Hidden CP fields are `display:none`, which also takes them out of the tab order.
- Arrow keys already skip hidden menu items (T6). Check that focus lands sensibly when a whole group button is gone
  (V6).

## 5. New block

On a site theme's Blocks tab, a **New block** dialog.

**Inputs**
- **Name.** A handle is derived from it: editable, `^[a-z][a-zA-Z0-9]*$`. It's refused when:
  - it's already an entry type handle;
  - it's reserved by Craft;
  - it already resolves to a template in `_base` or any theme's `_blocks/` (`collage`, `markup`, `formContact`,
    `layouts` and `partials` exist today).
- **Group:** Content / Components / Media / Layout.
- **Where it goes:**
  - **This theme** (default): only this theme and copies of it offer it (§4.1).
  - **Base:** every theme offers it.
- **Builder fields:** any of `builderFields`, with `pageBuilder` and `containerBlocks` checked by default.
- **Fields preset:**
  - **Items:** `items` on a Content tab, `blockHeading` on Settings, like `cards`.
  - **Heading only:** `blockHeading`.
  - **Empty:** add fields in the CP afterwards.

**Content model**
- Save the entry type, then append it to each chosen builder field. **Groups follow each field's own scheme**:
  - a field with several groups (`pageBuilder`) gets the chosen group;
  - a field whose types share one group (`postBuilder`, "General") gets that group;
  - a field with no groups (`containerBlocks`, `columnBuilder`) gets none.

  Setting a group that doesn't fit would split that field's menu. A group is set through a usage clone of the saved
  type with `original` and `group` (T11). Fix T11's "General" landing in the same place.
- Craft writes this to project config YAML, which is committed and applied by `php craft up`. It only adds, so the
  migration-order rule (project config before content migrations) doesn't bite. `allowAdminChanges` is already
  required by the whole Designer (T12).
- **All or nothing:** files are written first, so a bad path refuses before Craft is touched. If saving the entry type
  or any field fails, the new files, the CSS import and the entry type are all removed. (T11 leaves files behind.)

**Files**

| | This theme | Base |
|---|---|---|
| Template | `themes/<h>/templates/_blocks/<handle>.twig` | `themes/_base/templates/_blocks/<handle>.twig` |
| CSS | `themes/<h>/src/css/blocks/<handle>.pcss` | `themes/_base/src/css/blocks/<handle>.pcss` |
| Import | added to `themes/<h>/src/css/main.pcss` with `ThemeInstaller::insertAfterLastImport()`, `layer(components)` | `appendBlockCssImport()` (T11) |

- **Template stub**, from `_testBlockBoilerplate.twig`:
  - the preset's guard (`{% if items %}` or a heading check), `inlineEditBlock(entry)` and the heading partial;
  - root class = the handle in kebab case (`player-profile`), as in `image-text-default`;
  - a loop over items for the Items preset.
  - The name only goes into a Twig comment, with `#}` stripped.
- **CSS stub:** the BEM root and element placeholders.
- **Why `layer(components)` in a theme:** `themes/CLAUDE.md` puts theme CSS in `layer(overrides)` because a fork has
  to beat Base. A new block has nothing to beat, and staying in `components` lets `overrides` and `utilities` still
  win over it. Both theme `main.pcss` files already declare `components` in their layer statement. `themes/CLAUDE.md`
  gets a line.
- **Rules:** the Items preset writes `itemFields.hidden` copied from `cards` into this theme's file (or site rules
  stay in charge for Base).

**Afterwards, the success panel lists:**
- "Pending build" until `npm run build`, or the running dev server, picks up the CSS;
- "Editors see it when this theme is active" (for a theme block);
- what isn't automated: layout field and layout templates, icons, JS, a critical-CSS entry if it can be the first
  block, `_blocks/CLAUDE.md`, `page-patterns.md`, and the skills.

**Theme lifecycle**
- **New theme copied from this one:** brings the files, so it offers the block (T15).
- **Remove theme:**
  - The confirmation lists "This theme's own blocks: Player Profile (used in 8 blocks)".
  - The entry type stays, and those blocks render through the cards fallback in every other theme, or render nothing
    when empty (§3).
  - Deleting the entry type is a CP action, and it soft-deletes the content.
- **Publish:** a theme with its own blocks publishes its template and CSS files, but **not** the entry type. Publish
  warns "Player Profile won't be created on a site that adopts this theme". Carrying theme blocks through the library
  is later: `newBlocks` today installs into `_base`, which is the opposite of a theme block.

**Not in v1:** deleting or renaming a block from Theme Designer.

**As built (2026-09-16, craft-modules `NewBlock`, `actionCreateBlock`, the dialog in `_tab-blocks.twig`):**
- **Validation** runs before anything is written: handle format, an existing entry type, Craft's own handle rules
  (reserved words), a template or folder of that name in `_base` or any theme (`collage`, `layouts`), group, preset,
  and builder fields from `builderFields`.
- **Order and undo:** template and CSS, the `main.pcss` import, the entry type, each builder field, then (Items preset,
  theme target) Cards' item `hidden` copied into the theme file. Every step records its undo; a failure runs them in
  reverse and rethrows. Checked by forcing the last step to fail (an invalid theme config file): no files, import,
  entry type or field membership left.
- **Groups** follow each field: several groups → the chosen one (pageBuilder), one shared group → that group
  (postBuilder "General"), none → none (containerBlocks, columnBuilder). T11's library path still appends with no
  group; not changed here.
- **Stubs:** Items renders a list of items through `itemData()` and nothing without items; Heading only renders
  when there's a Block Heading; Empty renders nothing until someone adds fields. Checked rendering under coastal
  after `npm run build`.
- **The dialog** is a native `<dialog>`: handle derived from the name until edited, errors in `role="alert"` with
  focus, a success panel listing the files, "Pending build", who sees it and what isn't automated; **View the
  block** selects it in the tab.
- **Theme lifecycle:** the General tab's Remove confirmation lists this theme's own blocks and how many are in use;
  Publish shows a notice that they won't be created on an adopting site (not seen in the browser: library publishing
  is off on stables).

### 5.1 First Base block: Stats

The "500+ clients · $2.5M raised · 98% satisfaction" row turns up on most sites, so it goes in `_base` for every theme.
It doesn't need Theme Designer: it's ordinary developer work, done by migration, and it can be built before the Blocks
tab exists.

**Content model**
- **New item fields** on the shared `item`:
  - `statNumber` (Number, up to 2 decimals)
  - `statPrefix` (plain text, e.g. `$`)
  - `statSuffix` (plain text, e.g. `+`, `%`, `k`)
- **Existing item fields reused:** `heading` is the label ("Clients served") and `intro` the optional detail. `itemIcon`
  is optional.
- **Block `stats`:** Components group, Items preset (`items` on Content, `blockHeading` on Settings).
- **Added to the `switchGroups` group,** so a Stats block can switch to Cards and back without losing content.
- **`blockfields.php`:**
  - `stats` hides `image`, `buttons`, `text`, `preheading`, `subheading` and `useEntry` on its items;
  - every other items block adds `statNumber`, `statPrefix` and `statSuffix` to its `hidden` list.
- **Created by a migration** that adds the fields and inserts them into the existing `item` layout **in place**
  (content is keyed by layout element uid), then creates the entry type and appends it to the builder fields with its
  group (§5).

**Markup** (`_blocks/stats.twig`)
- The block renders only when it has items (§3), and an item without `statNumber` is skipped.
- Structure:
  - `<section>`, then the heading partial;
  - a `ul role="list"`, with one `li` per stat;
  - each stat is `<p class="stats__value"><stat-count>…</stat-count></p>` followed by `<p class="stats__label">`.
- **The final value is in the HTML:** the prefix, the number formatted server-side for the site's locale, and the
  suffix. Search engines, screen readers and visitors without JS all get "500+", never a count in progress.

**Count-up** (`<stat-count>`, a Web Component, `Components/statCount.ts`, loaded by dynamic import only on pages that
have one)
- **When it animates:** only when the element starts **below the fold** and `prefers-reduced-motion` isn't set. It
  then resets to the start value just before entering view, and counts up once (IntersectionObserver, then
  disconnect).
  - An element already in view on load shows its final value without animating: no flash from final to 0, and no
    layout shift.
- **Accessibility:** during the count the visible digits are `aria-hidden`, and a visually-hidden copy holds the final
  text, so assistive tech never hears intermediate numbers.
- **Formatting:** `Intl.NumberFormat` with the page's `lang` and the same decimals as the server, so the last frame
  matches the HTML exactly.
- **No layout shift:** `font-variant-numeric: tabular-nums` and a `min-inline-size` in `ch` from the final string's
  length.
- **Settings:** CSS custom properties with house defaults, `--stat-count-duration: 1000ms` and
  `--stat-count-ease: var(--ease-out)` (the existing Base easing token). No data attributes.
- **Budget:** under 1 KB compressed, and no Motion dependency (a `requestAnimationFrame` tween).

**CSS:** `blocks/stats.pcss` in `layer(components)`. The grid is 2 across on small screens and up to 4 on wide ones;
the number uses the type scale's display step.

**Verification:** V20 below.

**As built (2026-09-16):**
- **Item layout:** Stat Prefix, Stat Number and Stat Suffix sit after the item's first divider, at 25/50/25 widths.
- **Stats hides `sourceEntry` as well as `useEntry`:** a stat has nothing to pull from an entry.
- **Offered wherever Cards is** (pageBuilder under Components, postBuilder, containerBlocks), so the switch group
  always has somewhere to switch to. Not in columnBuilder.
- **Value size is `--stats-value-size`,** `--fs-xl` on phones and `--fs-2xl` from md, not the display step: at 4xl
  "12,500" didn't fit four across, and at 2xl it overflowed a phone column.
- **Server formatting** passes the site language to `|number`, so it matches `<html lang>` and `Intl` even for a
  logged-in user with another formatting locale. `value` and `decimals` are attributes on `<stat-count>` (content, not
  settings).
- **The count** uses an empty Web Animations animation to carry `--stat-count-ease`, reading its eased progress each
  frame, so there's no JS easing code. 0.80 KB gzipped.
- **Checked:** HTML has the final values; above the fold and under reduced motion nothing changes; below the fold it
  resets just before view, counts eased, and ends on the HTML value; Stats → Cards → Stats keeps every item value;
  item content hashes identically before and after the migration. **Not checked:** VoiceOver, throttled CLS with
  `page-checks.mjs`.

## 6. Theme thumbnail upload

On the General tab, for site themes and variants (Theme Picker shows both, T14). Base has no `theme.json`.

- **Accepts** PNG, JPEG or WebP, up to 5 MB.
  - It checks magic bytes, as `actionUploadFavicon` does (`DesignerController.php:2856`). That action has no size cap,
    so the cap is new.
  - `loadImage()` also checks memory and MIME type.
- **Processing:** `getImages()->loadImage($tmp)->scaleAndCrop(1600, 1000)->saveAs(…)`, to `thumbnail.webp` when
  `Images::getSupportsWebP()`, else `thumbnail.jpg`. The format comes from the extension.
  - `theme.json` `thumbnail` is set only after the file is written; then the old file is deleted.
- **Alongside:**
  - **Remove** clears the key.
  - The preview shows at the card's own size.
- **Where it shows:** Theme Picker cards and publish already follow the `theme.json` key. The library already accepts
  WebP and JPEG (T14).
- **`ramz-theme-publish` skill:**
  - The check becomes "the file `theme.json` names exists".
  - The screenshot step keeps writing `thumbnail.png` when there's no thumbnail yet.
- **ADA:**
  - A labelled file input, and errors in `role="alert"`.
  - **Theme Picker cards change to `alt=""`** (`index.twig:70`, `_variant-cards.twig:23`). The image repeats the theme
    name printed beside it, and "Coastal preview" read before "Coastal" is noise. This matches the Library.
- **Later:** "Capture from the site", using the screenshot the skill already takes.

**As built (2026-09-16, craft-modules `ThemeThumbnail`, `actionUploadThumbnail`/`actionRemoveThumbnail`):**
- **Size changed to 1200×750, WebP quality 80** (Gary asked about storage). Cards are 220–350px wide, so 1200 is
  still sharper than 2×; coastal's 1.9 MB stock screenshot comes out at 111 KB (1600×1000 was 198 KB).
- **Publish guard:** `LibraryPublisher` re-encodes a thumbnail that isn't WebP/JPEG, is larger than 1200×750, or is
  over 300 KB, in the bundle copy only, and points the bundle's `theme.json` at it. A theme's own file isn't touched.
- **The tile** sits under Logo & favicon on the General tab for site themes and variants, in the same gallery kit:
  a 16:10 preview, Custom / Missing, Upload or Replace, Remove.
- **Checks:** 5 MB cap, magic bytes (PNG, JPEG, WebP), a GIF, a fake or truncated PNG and Base are all refused;
  the image read error no longer shows a temp filename. The upload is written as a dotfile and renamed; theme.json
  is updated after, then the old file is deleted. The General tab's flash error now has `role="alert"`.
- **Theme Picker cards** use `alt=""` (site cards and variant cards).
- **`ramz-theme-publish`** checks the file `theme.json` names, points at the upload, and notes that a scaffolded
  theme may carry its source's screenshot.

## 7. Left to the CP

| Idea from the first draft | Why not in Theme Designer |
|---|---|
| Create fields and attach them to a block or child | The CP field layout designer already does it, and Blocks picks new fields up on its own (§4.3). Doing it from code risks the layout-element-uid content loss, and a field on the shared item appears on every block in every theme, which needed its own design. |
| Build page templates with blank blocks | The CP entry editor already adds blank blocks, and they save once §3 lands. Nested entries written from code have lost content before. |
| Template preview image | Already a CP field on the template entry (`templatePreview`). |
| Section on/off | The CP's Customize sources toggle is the same setting, and it **only** hides the Entries sidebar source. A real "off" (routes, sitemap, feed, `posts` block) would be its own spec. |
| Page templates per theme | Not handled today. If the softball theme needs a "Player profile" template that other themes don't show, that's a small separate spec, e.g. a theme filter on the template picker. |

## 8. Where things live

| Piece | Repo |
|---|---|
| Blocks tab, New block, thumbnail upload, atomic writer, `ThemeConfig::currentThemeHandle()`, `BlockFallback` empty case, Theme Picker `alt=""` | `craft-modules` (`themedesigner`, `themepicker`) |
| §3 migration + template guards; `BlockFieldCss` changes (scoping, units, generation checks, cache), `blockAvailability.js`, the blocks-view event handler, `stablestwigextensions/blocks/offered` command, stub source files | `stables` |
| Doc lines: `themes/CLAUDE.md` (fork guard, `components` for theme blocks), theme-config-spec §4.2/§5, `_blocks/CLAUDE.md`, `ramz-page-scaffold`, `ramz-content-import`, `ramz-theme-publish` | `stables` |

## 9. Build order

The order across all three specs is set in [`theme-content-roadmap.md`](theme-content-roadmap.md): stored formats and content model first, screens second. The table below is this spec's detail.

| Step | What | Repo |
|---|---|---|
| 1 | §3: required/minimum migration + guards in `image`, `blockquote`, `accordion`, `video`; `BlockFallback` empty case | stables + craft-modules |
| 2a | **Confirmed 2026-09-15** in the CP: Home's Banner slideout hides its Block Heading Pre Heading and Subheading through the unscoped item rule. Nothing is removed from `blockHeading`; the scoping fix in 2b brings them back. | — |
| 2b | §4.1, §4.2, §4.4 and §4.6: scoping fix, new units, generation checks, `builderFields`, cache, `blockAvailability.js`, blocks-view event, switch map, `offered` command; `ThemeConfig::currentThemeHandle()` | stables + craft-modules |
| 3 | Blocks tab (§4.3, §4.5) | craft-modules |
| 4 | Gary clicks through Blocks on stables | — |
| 5 | New block (§5) | craft-modules + stables stubs |
| 6 | Thumbnail upload + Theme Picker `alt` (§6), independent, can go any time | craft-modules |
| 6b | Stats block (§5.1), independent of the tab; after step 1 | stables |
| 7 | Release → lock stables; romeo-buddy only on Gary's yes (its own `BlockFieldCss` has the T3a leak too) | — |

## 10. Verification

| # | Check |
|---|---|
| V1 | **No theme file:** after step 2b the CP's generated CSS differs from before **only** by the scoping fix (every changed selector listed), and the switch map is identical; the new add-menu data is empty; home and an interior page byte-identical (`page-checks.mjs`) |
| V2 | **§3:** an empty image, blockquote, accordion, video and cards block each save and render no markup; a theme-only block with no items renders nothing in another theme; filled blocks render byte-identically before and after; `php craft up` on a restored DB applies the migration and no content keys change |
| V3 | **Scoping (T3a):** in the Image + Text slideout, Block Heading Intro shows and item Intro stays hidden; Block Heading shows Pre Heading and Subheading on Banner, while its Item still hides them; inside a `container` slideout, a container `ownFields.hidden` rule doesn't hide the same field on a nested block |
| V4 | **Fields:** hide `cards` item `text` in a theme → the Home Cards slideout loses Text; Use site rules brings it back; the file is deleted once empty |
| V5 | **`ownFields.hidden`** hides a slider's own field in its slideout (cards view) **and** inline inside a container (blocks view) |
| V6 | **Not offered, cards view:** slider off → gone from `pageBuilder`'s Components menu **and** the combined narrow menu in a slideout; arrow keys skip it; with a whole group emptied, its button is gone and the plus icon is on the first visible button; opening three slideouts leaves one handler; a page already holding a slider still saves and renders |
| V7 | **Not offered, blocks view:** slider off → gone from `containerBlocks`' create buttons **and** from every block's "Add … above" action, after reopening that menu; a container already holding a slider still saves |
| V8 | **Switch dropdown:** with slider off, an existing slider's type switch lists cards/imageText/banner/spotlight, not the unfiltered list; a cards block doesn't offer slider |
| V9 | **Layouts:** `large` off for cards → gone from the picker; an existing Large block still renders; turning off the default or every option is refused; `layoutForms` shows read-only |
| V10 | **Default availability:** a block with a template only in theme A is offered with A active, not with B, and offered in a copy of A; `stablestwigextensions/blocks/offered` reports the same |
| V11 | **Stale rules:** a theme file naming a renamed field, a layout field from another block, or a missing child type → the rule is dropped and logged, the CP loads under devMode, and the tab shows Stale rule |
| V12 | **Refusals:** a post with an unknown block, field or layout value, or a variant/locked handle → refused, nothing written |
| V13 | **Atomic write:** a save interrupted after the temp file is written leaves the theme file either old or new; the leftover dotfile doesn't break publish's strict check |
| V14 | **Cache:** editing `blockfields.php`, the theme file, a field in the CP, or adding a template each change the CSS on the next CP request; an autosave with nothing changed does no layout walk (temporary counter) |
| V15 | **New block (theme):** entry type in `pageBuilder`'s chosen group and group-less in `containerBlocks`; files in the theme; stub renders nothing empty and items when filled after `npm run build`; not offered with another theme active; YAML diff additive only; a forced field-save failure leaves no files, import or entry type behind |
| V16 | **New block (Base):** files and import in `_base`; offered in every theme; a handle matching `collage` is refused |
| V17 | **Remove theme** with its own block lists it; afterwards those blocks render through the fallback and nothing is deleted |
| V18 | **Thumbnail:** a renamed non-image is refused; a JPEG becomes a 1600×1000 WebP; Theme Picker shows it with `alt=""`; Remove clears it |
| V19 | Dormant: romeo-buddy on the same craft-modules checkout shows no Blocks tab and its CP is unchanged; `npm run typecheck` clean |
| V20 | **Stats:** page source has "500+" before JS runs; below the fold it counts once and ends matching the HTML; above the fold and with reduced motion it doesn't animate; VoiceOver reads only "500+ Clients served"; throttled CLS 0 (`page-checks.mjs`); switching Stats → Cards → Stats keeps every value; existing item content unchanged after the migration |
| V21 | **Editor preview:** Cards previews its Content and Settings tabs with the layout's own labels and widths; the Item card shows child fields with controls; switching the preview layout to Large dims Pre Heading ("Not on Large"); a field of an unknown class renders a plain box; with VoiceOver, only field labels and the radio groups are reachable, never the placeholder inputs; Base shows the preview with every control disabled |

## 11. Decisions

Decided by Gary, 2026-09-14:

1. **No required fields or minimums on blocks**; templates guard (§3).
2. **Q1:** a block or layout a theme doesn't offer **keeps rendering** where it's already used.
3. **Q2:** a theme decides **which blocks** it offers **and which layouts**; both are theme units.
4. **Q3:** child rules cover **every nested type**, not just `item`.
5. **Block templates for a custom theme live in that theme** (§5), and scope stops where the CP already does the job
   (§7).

Decided by Gary, 2026-09-14 (my recommendations accepted):

6. **Accidental hides (T3a):** every field the leak was hiding comes back. Block Heading keeps Pre Heading and
   Subheading (revised 2026-09-15: removing them was judged by what `_base` renders today, which says nothing about a
   boilerplate's themes).
7. **`available` defaults from templates** (§4.1).
8. **A theme's own blocks use `layer(components)`** (§5).
9. **`builderFields` is an explicit site list** (§4.2).

No open questions remain.
