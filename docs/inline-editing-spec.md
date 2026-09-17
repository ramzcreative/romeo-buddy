# Front-end inline content editing — spec

Status: **Phase 0 (admin bar toggle) and Phase 1 (content editing + block
reorder) built and real-browser-verified 2026-09-13.** Phase 2 (item
reorder) and Phase 3 (gear panel) not yet built. Tier 4 of the
[admin bar](../modules/stablestwigextensions/services/AdminBar.php) rework
(tiers 1-3 shipped 2026-09-12); this is that deferred tier, designed and
now partially built.

Companion docs: [`themes/CLAUDE.md`](../themes/CLAUDE.md) (theme/`_base`
relationship), [`themes/_base/templates/_blocks/CLAUDE.md`](../themes/_base/templates/_blocks/CLAUDE.md)
(page-builder mechanics this spec builds directly on top of).

## Goal

Let a logged-in user with edit permission on a page change its content
directly on the live front end — Webflow-style click-to-edit — without
opening the CP. Gated entirely behind login + per-element permission; a
logged-out visitor or a user without edit rights sees nothing different from
today.

## Component boundary — read this before building anything

**The admin bar is a toggle, not the editing engine.** Its entire
responsibility here is flipping one piece of shared state (e.g. a
`[data-inline-editing]` attribute on `<body>` — the "hide/show editable
sections" switch) and nothing else. It must not know about toolbars, saving,
block state, or the entry/item data model.

A **separate, independent system** owns everything downstream of that
attribute: detecting editable regions, rendering the block-level toolbar,
activation state, contenteditable behavior, autosave, and the save
endpoint(s) on the PHP side. This keeps `adminBar.ts` from growing into a
second, unrelated feature, and means the editing engine could be removed or
swapped without touching the admin bar at all.

Concretely, this likely means:

- `themes/_base/src/js/Components/adminBar.ts` — unchanged in spirit, just
  gains the one attribute toggle.
- A new Web Component, e.g. `themes/_base/src/js/Components/inlineEditor.ts`
  (or similar), owning all of the above. Loaded/active only when
  `currentUser` is set, same gate `adminBar.twig` already uses — costs
  logged-out visitors nothing.
- A new controller alongside `AdminBarController`, e.g.
  `modules/stablestwigextensions/controllers/InlineEditController.php`, plus
  a service (`services/InlineEdit.php`) matching the existing
  service/controller split described in `AdminBar.php`'s own docblock.

## Scope for this version

### In scope

- **Content editing**, per block, always writing to the field(s) owned
  directly by that block/item — never a linked entry (see "Data model" below
  for why that's not just a v1 restriction but the permanent behavior here).
- **Reordering** a block within its own existing list only (see "Reorder").
- **A generic settings panel** ("gear") for a block's own non-content fields
  — layout/background selects, not content.

### Explicitly deferred (not this version)

- **Adding or deleting blocks** from the front end. Both were considered and
  intentionally cut: add needs a block-type picker scoped to the field's
  allowed `entryTypes` and well-defined blank-state content; delete is a
  destructive action on a live public page that needs real confirmation UX
  and ideally confirmed soft-delete/trash behavior. Neither is required to
  ship real value (content editing + reorder), so both wait for a later
  pass — see "Planned extensions" for what v1 should do to leave room for
  this rather than needing a rework.
- **Editing a linked entry directly** (an "entry mode" toggle was discussed
  and deliberately dropped — see "Data model").
- **A separate item-level toolbar for content/settings.** Considered, then
  cut: selecting an item inside a block activates the whole containing
  block instead (see "Interaction model"). This does NOT rule out a
  minimal per-item **reorder** handle — see "Item reorder" under "Block
  toolbar" — that's a single-purpose control, not a toolbar, and mirrors
  a capability Craft's own CP already offers on nested Matrix blocks.
- **Moving a block out of its containing list** — no dragging a block from
  inside a Container or a column out to the top level, or between columns.
  Reorder is strictly within the array it already lives in.
- **An outer frame around the whole editable canvas**, separate from any
  one block's own selection outline (explored in the mockup canvas's
  `Placement` artboard, inspired by a reference screenshot). Dropped: the
  per-block outline already signals what's editable/active, and a
  persistent frame around the whole page mostly adds visual noise rather
  than new information. Can be revisited later if it turns out to matter
  in practice.

## Data model — what a write actually targets

Page-builder blocks (`pageBuilder`/`postBuilder`/`columnBuilder`, all real
Matrix fields per `_blocks/CLAUDE.md`) either hold plain block fields
directly, or — for anything using the shared `items` field (`cards`,
`slider`, `imageText`, `banner`, `spotlight`) — resolve each item's displayed
value through `itemData()` against the chain defined in
[`config/stables/items.php`](../config/stables/items.php): the item's own
field wins if set, otherwise it falls back to the item's linked
`sourceEntry`. (A theme can replace a key's chain in its own
`config/items.json` — read the chain through `ThemeConfig::get('items')`, as
`ItemResolver` does, never the site file directly. See theme-config-spec.md.)

**This system only ever writes to the item's/block's own field — never to a
linked `sourceEntry`.** An entry/custom toggle was discussed and explicitly
rejected: from this surface, edits should be fast and low-friction, which
means no mode-switching and no "which element does this actually belong to"
decision exposed to the editor. Practical effects:

- Editing a field that's currently showing a value inherited from
  `sourceEntry` (because the item has no override yet) **creates that
  override** — per `items.php`'s resolution order, the item's own field now
  wins going forward, permanently, until someone clears it. This is a
  one-way switch worth surfacing in the UI (e.g. on first edit of an
  inherited value), even though there's no mode toggle.
- Because writes never touch a linked entry, there's only ever one
  permission check to make per write: `canSave()` on the block/item element
  itself, not a second check against some other entry.
- If an item has no `sourceEntry` at all (pure custom content already),
  there's nothing special to note — it behaves exactly like any other
  content edit.

## Interaction model

Three visual states per block, all reachable through CSS from state already
present in the DOM (`:hover`, `:focus-within`, an `[data-active]`-style
attribute) — no bespoke JS hover-tracking required for the base case:

1. **Idle** — global toggle is on, but this block isn't hovered/focused/
   active. Page looks like normal production output; no outline, no
   toolbar.
2. **Hover / focus preview** — outline + toolbar fade in as a discovery
   affordance. Nothing is editable yet.
3. **Active** — outline + toolbar shown solid; every field owned by this
   block, and every field owned by each of its items, becomes directly
   editable (contenteditable or equivalent per field type — see "Field
   support tiers").

Reached either via the block's own toolbar/outline, or by interacting with
*any* item inside it — item-level interaction bubbles up and activates the
whole containing block. There is no "item active, block not active" state;
granularity of *content editing* activation is always the block. The one
per-item control that exists independently of this is the reorder handle
(see "Item reorder" below) — reordering doesn't imply or require the block
being in its editable-content state.

### Accessibility requirements for this state model

Hover-driven reveal is mouse-only by construction, so it cannot be the only
way in or the interaction breaks for a real chunk of users:

- **`:focus-within` must get full parity with `:hover`.** Tabbing into any
  field or control inside a block reveals the same outline + toolbar a
  mouse hover would. Focus should land somewhere sensible when a block goes
  active — never silently redirected away from where a keyboard user was
  already tabbing.
- **Every toolbar control needs a real accessible name** — "Move block up,"
  "Move block down," "Block settings" — never an icon-only `<button>` with
  no label. Same for the admin bar's own toggle (`aria-pressed`).
- **Reorder needs a keyboard-operable path**, not only a drag handle — move
  up/move down controls (or arrow-key support on a properly-described drag
  handle) that trigger the same field-order save a mouse drag would.
- **Save/status feedback can't be visual-only.** A "Changes Saved"-style
  toast needs a paired `aria-live="polite"` region so a screen-reader user
  gets equivalent confirmation without it interrupting their flow.
- **Respect `prefers-reduced-motion`** on the hover/active fade transitions
  — skip or shorten them rather than forcing motion on someone who's opted
  out.
- **Touch has no hover state at all.** Default behavior: a tap goes straight
  to the same reveal `:focus-within` produces — there's no meaningful
  "preview-only" state to fake on a device with no hover. This is a default
  assumption, not a confirmed requirement — worth a second look once there's
  something real to tap on a phone.

## Permission model

Gate on `entry.canSave(currentUser)` — Craft 5's real write-permission check
([`Element::canSave()`](../vendor/craftcms/cms/src/base/Element.php)) — not
`canView()`, which is what the existing "Edit Entry" link in `adminBar.twig`
uses today and is not sufficient here. For a block/item, the relevant check
is against that specific nested element (`canSaveNestedElement()` /
the field's own `canSaveElement()`), since a user could plausibly have
rights to the page itself but not to a specific nested block type — this
should be verified rather than assumed once real fixtures exist.

The global toggle can be visible to anyone who can save *something* on the
page; each block's own activation and each write still re-checks
permission independently. **The server-side save endpoint re-checks
`canSave()` itself on every request** — the client-side toggle/highlight
state is UI convenience only and must never be trusted as an authorization
decision.

## Field support tiers

- **v1**: PlainText fields (native `title`, `excerpt`) and short CKEditor
  fields (`heading`, `subheading`, `preheading`) as `contenteditable`
  regions with minimal/no formatting UI.
- **v2**: full CKEditor body fields (`text`, `intro`) — needs a real
  toolbar, not bare `contenteditable`.
- **Out of scope indefinitely from this surface**: images, asset/entry
  relations, the `items` Matrix structure itself (handled by reorder, not
  by field-level editing), layout selectors (handled by the gear panel,
  not as a "field").

## Block toolbar

Two controls, given add/delete are deferred:

- **Reorder handle** — moves the *whole block* (as one atomic unit — the
  same granularity as dragging a Matrix block's own row in the Craft CP
  today, not anything finer-grained inside it) within its own existing
  list only (top-level `pageBuilder` blocks reorder among siblings at
  that level; a block inside one column's `columnBuilder` reorders among
  that column's own blocks; same for `container`'s `containerBlocks`).
  Confirmed explicitly, in two parts: nothing can be dragged out of its
  current container (no cross-container or cross-column moves), and
  within that constraint the block itself can still be freely
  repositioned — this is not a narrower "just nudge it up or down one
  slot" control. Mechanically this is a re-save of the *owner* element's
  Matrix field value in the new block order — `saveElement()` on the
  owner, same partial-save posture as content edits. Only needs
  `canSave()` on the owner element, since block content itself isn't
  touched — only its position in the owner's field array.
- **Gear (settings)** — a panel for the block's own non-content fields.
  Checked the actual code for both field families this panel needs to
  render, and they turn out to need **two different techniques**, not one:

  - **Background** is `modules\themepicker\fields\ColorChip`
    ([source](../../craft-modules/modules/themepicker/fields/ColorChip.php)) —
    a real `<fieldset>` of plain radio inputs, each with a `:checked`-driven
    swatch span; its `ColorChipAsset` is **CSS-only, no JS at all**
    ([source](../../craft-modules/modules/themepicker/web/assets/ColorChipAsset.php)).
    Options come from `BackgroundOptions::forElement($element, $palette)`,
    resolved **per element** — already accounts for that page's own Page
    Theme or section Theme Override, not just the sitewide theme, and
    correctly handles a stored-but-now-turned-off/deleted role (still
    shown, labeled accordingly). **Decided: reuse this field's own real
    `getInputHtml()` output verbatim** via a small endpoint, restyled only
    to fit the dark chrome — safe because it's CSS-only, and it means
    zero duplicated logic (a future gradient preset in ColorChip needs no
    matching front-end change).
  - **Layout selectors** (`layoutHero`, `layoutCards`, etc.) are almost
    all `verbb\buttonbox\fields\Buttons`, plus one plain
    `craft\fields\Dropdown` (`layoutForms`) — and buttonbox's own asset
    bundle ([source](../../vendor/verbb/buttonbox/src/assetbundles/ButtonBoxAsset.php))
    explicitly depends on `craft\web\assets\cp\CpAsset` (Craft's full CP
    JS/CSS framework — Garnish, jQuery, CP-wide styles) plus verbb's own
    base CP asset. **The reuse-its-own-markup approach does NOT extend to
    this one** — pulling the CP framework onto a public front-end page is
    a real performance/bundle-size cost this repo explicitly guards
    against (see root `CLAUDE.md`'s performance mandate). Decided instead:
    a small **custom generic renderer** for these — read the field's raw
    `options` setting (label/value, `layoutButton`'s own config) and
    render a plain button-group/radio-group in this system's own chrome,
    same posture originally planned before checking buttonbox's asset
    bundle.

  Anything heavier than either of these (asset pickers, relation fields)
  stays CP-only via a link out; any other field type considered later
  gets the same asset-bundle check before deciding which technique it
  gets.

### Item reorder

Craft's own CP lets a nested Matrix block (an item inside a block's
`items` field — a card in Cards, a slide in a Slider, and so on) be
dragged into a new position the same way a top-level block can, so this
surface should offer the same capability rather than an artificial gap.
Deliberately the same shape as block reorder, not a heavier feature:

- Each item gets a minimal reorder handle (or the keyboard move-up/
  move-down equivalent — see "Accessibility requirements") when the
  block is active. This is a single-purpose control, not a revival of
  the item-level toolbar ruled out above — no settings, no separate
  content affordance rides along with it.
- Scope matches block reorder exactly: strictly within that one block's
  own `items` array — no dragging an item out to a different block's
  `items` field.
- Mechanically identical to block reorder, just one field level down: a
  re-save of the *block* element's `items` field value in the new order.
  Same `canSave()` check, same partial-save posture — nothing new to
  invent here, it's the same mechanism applied one level deeper.

**Decided: no confirm or undo step for either reorder.** Both are
low-stakes and fully reversible (drag it back), unlike delete — the
standard save-toast is the only feedback needed, no modal in the way.

## Save endpoint (sketch)

`InlineEditController::actionSave()` — POST, CSRF-protected,
`allowAnonymous = false`, mirroring `AdminBarController`'s posture. Given
`elementId`, `siteId`, `fieldHandle`, `value`:

1. Load the element via
   `Craft::$app->getElements()->getElementById($elementId, siteId: $siteId)`.
2. Re-check `canSave($user)` server-side.
3. Confirm `fieldHandle` is both present on that element's field layout
   *and* on an explicit inline-editable allow-list — never accept a write
   to an arbitrary field just because it exists on the layout.
4. `$element->setFieldValue($fieldHandle, $value)`, then a scoped save
   (`saveElement($element, false)` to skip validating unrelated fields,
   while still validating and purifying the field being written — the same
   purification path a normal CP save would apply, per the
   [content-safety note](../themes/_base/templates/_blocks/CLAUDE.md#a-content-safety-note)).
5. Return the field's *rendered* value, not an echo of the raw input, so
   the client swaps in exactly what a reload would show — avoids drift
   between an optimistic client render and Craft's own formatting/
   purification.

A parallel `actionReorder()` takes the owner element ID, field handle, and
the new ordering of block IDs within that one field's array. **Built and
verified in real-browser testing** — the mechanism is Craft's own Matrix
"delta" input format, not an array of `Entry` objects: `setFieldValue`
with `['sortOrder' => $requestedIds]` and no `entries` key, which
resequences the field's existing blocks unchanged
(`Matrix::_createEntriesFromSerializedData()` leaves `forceSave` false for
each when there's no per-entry data). Passing loaded `Entry` objects
directly throws a hard `TypeError` deep in Craft core — confirmed the
expensive way, so this note is here to stop that from being rediscovered.

### Preview-token case

**Decided: disabled entirely.** If the page is being viewed via a
share/preview token, the element on screen is a draft, not the canonical
entry — rather than build a second data-flow to target that draft, inline
editing simply doesn't activate on a preview-token request at all (the
admin bar toggle and every `inlineEditAttrs()` call should check for this
and render nothing). Drafts stay CP-only. Revisit only if editors turn out
to regularly review drafts this way before publishing.

### Concurrency

**Decided: optimistic lock.** No drafts/revision workflow is introduced by
this feature — it writes straight to the live element, the same trust
level as a CP quick-edit, so the save endpoint compares `dateUpdated` at
save time and refuses (prompting a reload) rather than overwriting a
change made elsewhere in the meantime — the same protection Craft's own CP
already gives a normal edit form. **Built and genuinely exercised**: a
real stale write was caught during browser testing (the page had been
loaded a while before the save attempt, and the entry's `dateUpdated` had
moved on) and correctly refused rather than silently overwritten.

## Template-side markup convention

Block templates opt in per field, mirroring how `canEditEntry` already
gates what renders in `adminBar.twig` — nothing new is emitted for a user
who can't edit:

```twig
<h2 {{ inlineEditAttrs(entry, 'heading') }}>{{ entry.heading }}</h2>
```

`inlineEditAttrs()` is a new Twig function returning `data-inline-edit`
attributes (element ID + field handle) when `canSave()` passes, or nothing
at all otherwise. Opt-in per block template, not automatic — a block author
can deliberately leave a field CP-only (e.g. a legal disclaimer) the same
way `blockfields.php` already lets a block hide fields it doesn't use. A
parallel `inlineEditBlock(entry)` marks a block's own root tag
(`data-inline-block`) — derives the owner/field/block-id addressing from
Craft's own `Entry::getOwner()`/`getField()` rather than anything
`_builder.twig` passes down.

**Coverage as of 2026-09-13**: wired into every top-level block template
and layout variant except three, each excluded for a real reason, not an
oversight:
- `collage.twig` and `hero/slider.twig` wrap their whole block in
  `{% cache %}` with a cache key that has **no per-viewer component**
  (e.g. `themeCacheKey("block-collage-" ~ settings.uid ~ '-' ~ preloadImage)`). Adding
  permission-gated inline-edit markup inside either would get baked into
  that shared cache — a real bug (one viewer's edit affordances leaking to
  another's cached render), not a cosmetic gap. Needs a decision before
  either can join the rest: incorporate viewer-editability into the cache
  key, move the inline-edit markup outside the cached region, or bypass
  the cache entirely while the admin bar's toggle is on.
- `posts.twig` (a live blog listing, not static block content) and
  `text.twig` (dispatches to nested CKEditor-body content, v2 territory)
  are structurally different from the rest — not a caching problem, just
  not what this surface is for.
- The shared `_blocks/buttons.twig` partial (link/button content — v3
  scope, see "Planned extensions") is untouched; its fields aren't on the
  allow-list and it has no reliable standalone root tag to anchor to.

The allow-list (`config/stables/inline-editing.php`) grew from the original
four fields to also cover `blockquote.twig`'s own `textPlain`/`citeName`/
`citeTitle` — confirmed `craft\fields\PlainText` before adding them, same
scrutiny as the original four.

## Open items

- The custom generic renderer for buttonbox-backed layout selectors needs
  to read `layoutButton`'s raw option config (label/value, and whichever
  of `displayAsGraphic`/custom icon data a block actually uses) closely
  enough to stay visually coherent with its CP counterpart — not yet
  designed in detail.
- Any field type considered for the gear panel beyond ColorChip/buttonbox/
  Dropdown needs the same asset-bundle check (CSS-only → reuse its real
  markup; CP-framework-dependent → build a custom renderer instead) before
  a decision, not an assumption either way.

## Planned extensions — not v1, but build v1 to leave room for these

Four features are explicitly coming later, not just hypothetically
possible — flagged here, deliberately, **so v1 gets built in a shape that
doesn't need patching or rework** to grow into them:

- **Add/delete blocks** (see "Explicitly deferred" above). Leave room by:
  treating the block toolbar's control set as a small ordered list rather
  than two hardcoded buttons, and giving the controller's block-mutation
  actions one shared naming/permission-check convention
  (`actionReorder()` now; `actionAdd()`/`actionDelete()` later,
  siblings — not a bolt-on) rather than a bespoke reorder-only endpoint.
- **v3 — image editing**, Webflow-style: clicking an image opens a
  slide-out panel with an upload dropzone plus a grid of existing assets,
  not an inline swap. `image` is a single-relation Assets field pinned to
  one restricted volume (already excluded from v1's field-support tiers
  for exactly this reason). Still-open question from earlier discussion:
  reuse Craft's native `Craft.AssetSelectorModal` vs. a lighter custom
  picker scoped to that one volume. Leave room by: building the panel
  mechanism the gear settings introduce (see "Block toolbar") generic
  enough to grow from a small popover into a full slide-out — same
  underlying component, not a second UI system when this arrives.
- **v3 — button editing**: `buttons` is a Matrix of `Button` entries, each
  holding one `craft\fields\Link` field — label text plus a type switcher
  across `entry`/`url`/`asset`/`category`/`email`/`tel`, each needing its
  own picker (an entry/category/asset target is a full element-selector,
  not a text input). Leave room by: keeping the inline-editable-field
  allow-list (see "Save endpoint") data-driven rather than a hardcoded
  handful of handles, so adding Link-field support is a config change,
  and reusing the same generic field-panel mechanism as Background/image
  rather than a third bespoke UI.
- **Admin bar light/dark mode**: not this feature's scope at all — a
  future *admin bar* item (a separate module, per "Component boundary"
  above) that would also affect this editor's own dark chrome. Leave room
  by: keeping this spec's dark-chrome values as a small set of named
  tokens (matching how `adminBar.pcss` already keeps "its own small,
  fixed-value design language," per that file's own docblock) rather than
  raw hex scattered through every component, so a future toggle only has
  to redefine those tokens once.

None of these four are stubbed as code — there's nothing to attach a stub
to before v1 exists. This section is the durable record of "these are
coming and why," so v1's actual implementation gets shaped around them
from the start.

## Sibling surface — Design Mode (theme tokens), deliberately separate

Editing a *theme* token from the front end — the background role's stop,
a text style's size, an element color — is **not** a phase of this spec
and must not land in the gear panel. It has its own:
[`docs/design-mode-spec.md`](design-mode-spec.md).

The short reason: this system is for anyone with `canSave()` on the
element, on any environment, and writes one entry's field. That one is
admin + `devMode` only, writes generated `.pcss` files compiled by Vite,
and every write is sitewide. Same gesture, nothing else in common —
folding them together would put a global, admin-only, local-dev-only
control inside a panel a content editor opens on production.

**It shares this feature's toggle, though** — decided 2026-09-17, no second
switch in the admin bar: it is inline editing, of a different layer. That is
the component boundary above working as written (the bar owns one piece of
shared state; two systems may read it), not an exception to it. Two things
follow for *this* spec:

- **The block toolbar's control set gains a third entry** (a style control,
  opening that surface's rail). The "Planned extensions" note below already
  calls for those controls to be an ordered list rather than two hardcoded
  buttons — this is its first real use, so build it that way.
- **A click must keep meaning one thing.** With the toggle on, clicking text
  enters `contenteditable` and nothing else; design targeting is always a
  deliberate separate gesture (that toolbar control, or that surface's own
  armed "select element" mode, which suppresses activation while armed).
  Never overload the content click with a modifier.

**The seam, where the two meet on the same section:** a block's
`background` field picks *which role* it wears — content, this spec, the
gear panel. Design Mode changes *what that role is* — theme, that spec.
The panels link across to each other; neither reimplements the other.
