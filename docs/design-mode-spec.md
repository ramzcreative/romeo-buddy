# Design Mode — Theme Designer on the front end

Status: **spec, not built.** Scoped and fully decided 2026-09-17 — no open
design questions remain; what is left is listed under "Still to verify at
build time". A sibling to [inline editing](inline-editing-spec.md),
deliberately **not** a phase of it (though it shares its toggle) — see
"Why this is its own piece".

Goal: change a theme token *from the page you are looking at*, in the
context it actually renders in, instead of leaving the site, finding the
field in the CP Theme Designer, saving, coming back, and scrolling to the
block to see whether it worked.

## Why this is its own piece

Inline editing and this are the same gesture (click the thing, change the
thing) over two completely different systems, and every property that
matters is different:

| | Inline editing | Design Mode |
|---|---|---|
| What it writes | one entry's field value | a theme's generated `.pcss`/`.json` |
| Who may use it | anyone with `canSave()` on that element | admin **and** `devMode` only |
| Blast radius | that one block | every page using that token |
| How it reaches the page | Craft renders the saved value | Vite compiles the file (see "Reality of the write") |
| Markup it needs | `data-inline-edit` on every field, in every block template | **none** — it reads the DOM |
| Save path | new `InlineEditController` | `DesignerController`'s existing actions |

Folding this into the gear panel would put an admin-only, devMode-only,
sitewide-blast-radius control inside a panel a content editor opens on a
live production site.

**Separate system, shared switch.** This is its own module, but it does
**not** get its own toggle — it rides the existing Inline Editing switch,
because it is inline editing, of a different layer (see "Architecture" and
"One toggle, two gestures"). That is the
[component-boundary rule](inline-editing-spec.md#component-boundary--read-this-before-building-anything)
working as written: the admin bar owns one piece of shared state and knows
nothing downstream of it, and two independent systems are free to read it.

**The seam between the two, stated once:** a block's `background` field
picks *which role* that block wears — that is content, it belongs to the
inline-editing gear panel. Design Mode changes *what that role is*. Both
are reachable from the same section; they are two different questions and
two different writes. Each panel should link across to the other rather
than duplicate it.

## What makes the front end better than the CP for this

Not just convenience — context that the CP screen cannot have:

- The **background role** the element sits on is already in the DOM
  (`.bg--primary`, from `backgrounds-generated.pcss`). In the CP, "heading
  color on a primary background"
  ([`actionSaveBackgroundElementOverride()`](../../craft-modules/modules/themedesigner/controllers/DesignerController.php))
  is three abstract dropdowns. Here it is the thing you clicked.
- The **theme** and any **Theme variant** in play are already resolved for
  the request (`activeThemeHandle`, `brandThemeHandle` in
  [`scaffold.twig`](../themes/_base/templates/_layouts/scaffold.twig)).
- The **viewport** is real, so a token that only applies above `--screen-lg`
  can be shown as applying, or not, *right now*.
- You see every other consumer of the token on the same screen, which is
  the actual reason the round trip is expensive today.

## Permission and environment gate

Identical to the CP tool's, deliberately not a looser copy —
[`DesignerController::beforeAction()`](../../craft-modules/modules/themedesigner/controllers/DesignerController.php):

1. `devMode` off → the surface does not exist (404 from the endpoints, no
   assets, no rail, no third toolbar control — the Inline Editing toggle
   still works, it just carries content editing alone). Not 403: the tool
   does not reveal itself outside local dev.
2. `requireAdmin()` — these writes are files on disk, so
   `requireAdminChanges` semantics apply the same way they do in the CP.
3. Theme lock (`isThemeLocked()`) → read-only, with the tool's own message
   ("unlock it on the General tab"), not a silent failure.

Clients never see this. Consistent with the existing rule that client-facing
CP copy never names Theme Designer — this surface should not appear, or be
described, anywhere a client can reach.

## Reality of the write — read this before designing any panel

Theme Designer writes generated files that **Vite compiles**. The page in
front of you is not reading those files. Three different truths, all real,
and the panel has to be honest about which one applies:

| Situation | When the change is really on the page |
|---|---|
| Dev server running (`craft.vite.devServerRunning()`) | Immediately — `scaffold.twig` loads critical/main CSS as dev-server modules precisely so Vite's CSS HMR tracks them. |
| No dev server (page is served from `web/dist`) | Only after `npm run build`. |
| A **Theme variant**'s colors | Next page load, no build at all — `PageThemeResolver::cssFor()` parses the variant's `colors-generated.pcss` at render time and inlines it (`pageThemeCss()` in `scaffold.twig`, outside the dev guard). |

So the panel does two things on every change, not one:

1. **Apply optimistically, always** — `documentElement.style.setProperty()`
   with the new value, so the page updates in the same frame regardless of
   which row above you are in. This is a preview, not the save.
2. **Save durably** — POST to the real Theme Designer action.

And it reports the third fact plainly: `devServerRunning` is already known
server-side, so pass it into the surface and show a persistent, quiet
"dev server is off — saved to the theme, but this page won't show it again
until `npm run build`" state. Guessing, or staying silent and letting a
reload appear to lose the change, is the failure mode to design against.

**Do not** invent a runtime token-override store for site themes to dodge
this. It would be a second cascade alongside the files, with its own
caching and its own drift, to save a build step the dev server already
handles.

## How an element resolves to a token — no template wiring

Unlike inline editing, this surface needs **zero** new markup in block
templates. Everything it needs is already in the rendered DOM and the
generated CSS:

- Background role — nearest ancestor matching `.bg--*`.
- Named text style — the element's own tag/class, exactly as
  [`typography.pcss`](../themes/_base/src/css/base/typography.pcss)
  selects it: `h1:not([class*='faux-'])` … `h6`, `.faux-h1`…`.faux-h6`,
  `.faux-subheading`, `.faux-preheading`, `p:not([class*='faux-']):not(.ignore)`,
  `.faux-p-lg`/`-sm`/`-xs`, links. The `faux-` convention means an element's
  *declared* style is readable from its class list; the panel resolves the
  same way the stylesheet does, so it can never disagree with it.

Two consequences worth having on purpose:

- No `{% cache %}` hazard. Inline editing had to leave `collage.twig` and
  `hero/slider.twig` unwired because permission-gated markup inside a
  cached region bakes one viewer's affordances into everyone's cache. This
  surface adds no markup, so every block is covered, including those two.
- Nothing to keep in sync as blocks are added.

### The override probe — the "cards set their own sizing" case

`<p class="card__heading faux-p-lg">` ([`layouts/cards/grid.twig:59`](../themes/_base/templates/_blocks/layouts/cards/grid.twig))
declares the **Paragraph Large** style, and then
[`cards.pcss`](../themes/_base/src/css/blocks/cards.pcss) overrides its
weight always and its size above `--screen-lg`. Writing `--paragraph-lg-fs`
from that card would do nothing visible at desktop width. Silently offering
the control is the wrong answer; so is guessing from a hardcoded list of
"components that override things".

**Per-property reachability probe.** For each property the panel is about
to offer, set the token to a sentinel on `documentElement`, read
`getComputedStyle(el)` for that one property, restore. Changed → the token
reaches this element, offer the control. Unchanged → something else owns
it; show the value read-only with "set by this block's own CSS, not the
type scale", and don't pretend.

Per **property**, not per element: on that card heading, line-height and
letter-spacing still come from the style and are genuinely editable, while
size and weight are not. It is also viewport-honest for free — the same
probe run at 1440px and at 500px gives different answers about
`.card__heading`'s size, which is the truth.

**Name the owner, don't just flag it** — decided 2026-09-17. "Something else
owns this" is honest; "`.card__heading` owns this" is useful, and it is the
difference between the rail telling you that you are stuck and telling you
where to go. Walk `document.styleSheets` for the winning rule and show its
selector, with the CP Theme Designer's own posture: the fact, plainly, no
offer to edit that rule from here (hand-authored block CSS is not this
surface's business — see "Explicitly not here").

Two guards, because this is the one control here that could get noisy:
a long cascade should surface **only the winning selector**, not the whole
chain; and if the walk cannot confidently identify one (a `@media` block, a
shorthand that set the longhand, a cross-origin sheet that throws on
`cssRules`), it falls back to the unnamed flag rather than guessing a
selector. The probe's yes/no is the load-bearing part and never depends on
the naming succeeding.

## Which theme a change targets, and what that theme may change

Two questions, in this order: **who is the target**, then **what does that
target actually provide**. The second one is the load-bearing rule.

### Target resolution

1. **A Theme variant is in play on this page** → the variant
   (`brandThemeHandle`) is the target.
2. **No variant** → the site theme (`activeThemeHandle`).
3. **Admin-bar variant preview active** (session-only, only you see it) →
   the previewed variant is the target, and the rail says so loudly. This is
   the easiest way to change a theme you did not mean to.
4. **Theme locked** (`isThemeLocked()`) → read-only, with the tool's own
   message, not a silent failure.
5. **A stop marked `/* locked */`** → shown locked, never silently
   overwritten. Locks have been lost to bulk writes before; this surface
   must not be a new way to lose them.

**Every panel names its target in its header** before any control:
*Editing: **Coastal** › primary*.

### Capability scoping — only offer what the target provides

A Theme variant carries **colors, gradients and effects**, and nothing
else. Its overlay is built by parsing `:root` token lines and gradient
lines out of its own `colors-generated.pcss`
([`PageThemeResolver::buildCss()`](../../craft-modules/modules/themepicker/services/PageThemeResolver.php))
— a font stack or a radius written to a variant is a file nothing reads.

So on a page wearing a variant, the rail offers Background, Element colors
and Gradients, and **does not render** a Type, Surfaces, Lists or Buttons
panel at all. Not disabled, not dimmed — absent, with one line naming where
that setting lives ("Typography belongs to **Coastal**, the site theme")
and a link to the CP tab.

**This is not a new rule to invent — it already exists and is already
enforced.** `DesignerController::SITE_THEME_ONLY_ACTIONS` plus the refusal
in `runAction()` ("That setting belongs to the site theme. A Theme variant
only carries colors and marks.") is the authority. The rail must **read that
capability set from the server**, not keep a parallel hardcoded list that
drifts from it the first time an action moves. Note the deliberate omission
in that list: **gradients are not site-theme-only** — a variant owns
gradients as much as a site theme does, with its own reasoning in the
const's comments. A hand-copied list would have got that wrong.

**Where this deliberately differs from the CP tool:** the CP renders the
tab you navigated to and wraps it in a real `disabled` fieldset with an
explanation, because you asked for that tab. Nothing here is asked for — the
rail is assembled from what you clicked — so an inert section is pure noise.
Absent, with a one-line pointer, is the right form for this surface. Same
truth, different affordance.

**Ownership shows through the same mechanism, not a special case:** the rail
is a map of the theme system as it actually is. A variant that can only
recolor teaches that by what it offers.

### `followsBase` — verify before building

Button shape, font stack and the type/spacing scale come from Base while a
theme follows it (`themeFollowsBase()`), and stopping persists Base's values
into the theme. Those actions are *not* variant-blocked, so a follows-Base
site theme can reach them in the CP today — **what the CP actually does with
that save has to be read from the code and mirrored, not guessed at here.**
The failure to design against is the one already seen on clean-scaffolded
themes: a value written to a theme's own file that nothing imports, saving
successfully and changing nothing. Whatever the CP's answer is, the rail
gives the same one, and names Base as the target when Base is the target.

## One toggle, two gestures

Sharing the toggle creates exactly one problem worth solving carefully: with
the switch on, a click on a heading already means "edit this text". It
cannot also mean "restyle every heading on the site".

So targeting for design is a **deliberate, separate gesture**, never a
reinterpretation of the content click:

- **The block toolbar gains a third control** (a paint/style icon) beside
  reorder and gear, opening the rail targeted at that block. The spec
  already calls for that toolbar's controls to be "a small ordered list
  rather than two hardcoded buttons" so add/delete can arrive later — this
  is the first use of that extension point, not a new pattern.
- **The rail carries a "select element" mode** for finer targets (a heading
  inside a card, a button, a list). While it is armed, clicks target for
  design and do *not* enter `contenteditable`; it disarms on Esc and after
  each pick. This is also the keyboard path — it has to be drivable by Tab
  and Enter, not pointer-only.
- **No modifier-click** (Alt-click and friends). Undiscoverable, and it has
  no keyboard equivalent, which makes it a non-starter.

A content editor without admin rights in devMode sees none of this: same
toggle, same content editing, no third toolbar control, no rail. The
capability difference is who you are, not which switch you flipped.

## The rail

**Decided: a right-hand rail, not an overlay** — the page stays visible and
keeps rendering beside it, same as the reference tools. The whole point is
watching the page change; a panel covering the block defeats it.

Three consequences to build for, not discover later:

- **The page reflows, it is not covered.** The rail takes real width and the
  document gets the remaining space, so nothing the rail sits on top of is
  hidden behind it.
- **Fixed elements have to be pushed too.** `body` margin does not move a
  `position: fixed` sticky header or the admin bar itself. The design-mode
  stylesheet sets a `--design-rail-width` custom property and shifts
  `_base`'s own fixed chrome by it. They are our elements, so this is a
  short, targeted rule set — not a general solution to somebody else's
  fixed positioning.
- **The effective viewport is now narrower than the window, and that
  changes what you are looking at.** A 360px rail on a 1440px screen renders
  the page at 1080px, which can drop it below a breakpoint —
  `.card__heading`'s `--screen-lg` size rule being the obvious example. The
  reference in the screenshot shows its canvas width in the toolbar for
  exactly this reason. **The rail shows the live canvas width**, and the
  reachability probe reports against that width, because that is the width
  the page is actually being rendered at.

## Panels

Assembled from what you targeted, filtered by what the target theme provides
(see "Capability scoping"). Not a port of the CP tab — the CP tool stays the
place you *author* a system; this is the place you *adjust* one.

**One rail, a breadcrumb of scopes** — decided 2026-09-17. Targeting a
heading inside a card reaches three things at once: the card's surface, the
background role it sits on, and the text style. The rail shows all of them
as a breadcrumb and opens the innermost, rather than making you guess which
one a given click will produce. It teaches the token hierarchy instead of
hiding it, and it matches how the reference tool heads its own panel
("Selector / Inheriting 2 Selectors").

Revisit if it turns out to be noisier than the alternative in practice —
one panel per target is a smaller thing to fall back to than to grow into,
so nothing here should assume a single scope.

### 1. Background (click any `.bg--*` section)

The one this surface exists for.

- **Which stop this role's background uses** — `--bg-{role}-source` →
  `actionSaveBackgroundSource`. **Global by construction:** that token is a
  single `:root` declaration, so every `.bg--primary` section on the site
  moves with it. The panel must say that in words, not just imply it — this
  is the control the usage count matters most for — and the live preview
  makes it obvious the moment other sections on the page shift too, which is
  a feature.
- **The role's own six stops** (hex per stop) → `actionSaveColors`, with the
  computed on-color pairing shown, and locked stops respected.
- **Background image / gradient for the role** → `actionSetBackgroundImage`.
- **Element overrides on this background** → `actionSaveBackgroundElementOverride`:
  heading, subheading, preheading, form and link colors *on this role*. The
  highest-value control on the whole surface, because the CP version of it
  is the hardest to hold in your head.
- Link out: "change which background **this block** uses" → the
  inline-editing gear panel (content, not theme).

### 2. Type (click text that resolves to a named style)

- Font role, weight, size step, line-height, letter-spacing for that style →
  `actionSaveFontStack`, each constrained to the theme's own scale
  (`--fs-*`, `--fw-*`, `--lh-*`, `--ls-*`), never a free-text CSS value.
- Every control gated by the reachability probe above.
- The `followsBase` question above applies here more than anywhere.

### 3. Color of an element (click a link, a heading, a border, a form)

- The Elements keys from
  [`config/stables/themes/colors.php`](../config/stables/themes/colors.php)
  — `heading-color`, `link-color`, `border-color`, `form-*`, … →
  `actionSaveElement`, with the per-background variant of the same key
  alongside it (same action pair as panel 1).

### 4. Surfaces (click a card, a panel, anything with a radius or shadow)

- `--radius-sm/md/lg`, `--shadow*`, form input radius → `actionSaveSurfaces`.
  Low risk, very visible, good v1 material.

### 5. Buttons (click a button)

- The clicked variant's colors and the shared shape fields →
  `actionSaveButtonBaseConfigField` / `actionSaveButtons`. Scope carefully:
  the button config is large, and only the shape/color fields belong here.

### 6. Lists (click a `ul`/`ol` in body text)

- Marker size/color/indent, gaps → `actionSaveLists`. Nice to have, last.

### 7. Scale (from the panel nav, not click-driven) — **v2**

Type scale and spacing scale (`actionSaveTypography` / `actionSaveSpacing`)
move *everything*. They are not a tweak made from one block's vantage point,
and the live preview of a ratio change across a whole page is exactly the
thing the CP tool's ladder view already does better. Deferred on purpose,
not forgotten.

### Explicitly not here, ever

Font upload/fetch, palette and gradient authoring, color groups, logos and
favicons, blocks/extensions, theme creation, Adopt/Starter Kit, Library
publish. Authoring a system stays in the CP tool. This surface adjusts a
token that already exists — that boundary is what keeps it small.

## Usage count before a global write

**Decided 2026-09-17: yes.** Every control in the rail is a sitewide write
made from one block's vantage point, and the vantage point is the whole
problem — you are looking at one red section while changing every red
section on the site. A count is the cheapest possible correction for that:

> `--bg-primary-source` · **14 sections on 6 pages** use this background

Rules that keep it a guard rather than a speed bump:

- **Shown before the write, not as a confirm dialog.** It sits in the panel
  next to the control, always visible, so it informs the change instead of
  interrupting it. A modal on every token tweak would defeat the point of
  the surface.
- **Read from the CP's existing answers** — `BlockUsage` / `SwitchReport`
  already answer "what else uses this", and a second counter written here
  would disagree with the CP's within a release.
- **Counted, then cached per rail session.** It is a query, not a token
  read; it must not run on every drag of a color slider.
- **Degrades to nothing.** If a token has no usage answer available (a
  scale token, a surface radius — things no block-level report tracks),
  the rail shows no count rather than a wrong or alarming zero.

The one place it should be loudest is Background and Element colors, where
the blast radius is invisible from where you are standing. It is genuinely
optional on Surfaces and Lists, where the page in front of you already
shows most consumers.

## Architecture

Mirrors the existing split rather than inventing one:

- **PHP — `craft-modules`**, in `modules/themedesigner`: a second web
  controller (e.g. `controllers/DesignModeController.php`) behind the same
  `beforeAction()` gate, offering **read** endpoints only — "for role
  `primary` on theme `coastal`, what are the stops, the current source, the
  lock state?" — built from the same tab-data builders the CP screens use.
  **No new write path**: every save POSTs to the existing
  `DesignerController` actions, which already accept JSON
  (`getAcceptsJson()`), validate the handle, check the lock, and return
  `{success, …}`. Duplicating a writer here is how the two drift.
- **Assets — `craft-modules`**, alongside `web/assets/DesignerAsset.php`:
  hand-written `dist/design-mode.js` / `.css`, no build step, registered on
  *site* requests only when devMode + admin + the toggle is on. Two reasons:
  the panel exists partly to work around build latency, so its own code must
  not need a build; and nothing admin-only belongs in the site's main bundle
  under this repo's performance mandate.
- **Toggle — none.** **Decided: no second toggle.** This rides the existing
  Inline Editing switch in the admin-bar popover — it is inline editing, of
  a different layer. Nothing is added to
  [`adminBar.twig`](../themes/_base/templates/_partials/adminBar.twig) or
  `services/AdminBar.php`, and there is no `[data-design-mode]` attribute.

  This keeps the component boundary intact rather than bending it: the rule
  was always "the admin bar owns **one** piece of shared state and knows
  nothing downstream of it". Two independent systems subscribing to
  `[data-inline-editing]` is that rule working, not an exception to it. The
  admin bar gains nothing; the design system reads the same attribute the
  content system does, and adds its own devMode + admin gate on top — which
  it needs anyway, because the server enforces it regardless of any
  attribute.

## Safety, ADA, performance

- **Undo per edit.** Every write keeps the previous value in the panel with
  a one-click revert (a second POST of the old value). These are global,
  file-level writes; "drag it back" reasoning from reorder does not apply.
- **Optimistic lock.** The generated files are also editable in the CP at
  the same time. Compare the file's own current value before writing;
  refuse and re-read rather than clobbering a CP save made seconds ago.
- **Keyboard path, not a mouse-only surface.** Targeting needs an
  equivalent: the "select element" mode driven by Tab and Enter, and the
  rail listing what is on this page, so nothing is reachable only by
  pointing.
- **Non-modal, so not dialog semantics.** The rail is deliberately *not* a
  modal — the page beside it stays live and interactive, which is the whole
  point — so it gets **no focus trap**. Trapping focus in it would be the
  easy mistake, copied from a dialog it only superficially resembles. It is
  a complementary region with a name, `Esc` closes it and returns focus to
  whatever opened it, and Tab moves freely between rail and page.
- `aria-live` for save/error status, accessible names on every control, and
  `prefers-reduced-motion` on the rail's own transition — and on the page
  reflow it causes.
- **Zero cost when off** — no assets, no markup, no endpoint surface for
  anyone who is not an admin in devMode.
- **Contrast.** Color writes should surface the same WCAG pairing
  information `ColorScheme` already computes, so a fast tweak on the page
  cannot quietly produce an unreadable pairing.

## Build order

**Confirmed 2026-09-17.** Panels 1–4 — Background, Type, Element color,
Surfaces — are v1. They are the ones that pay for the surface: the first two
are the round trip this exists to kill, and all four are pure token writes
with no new field-rendering problem in them.

Buttons, Lists and Scale follow once the shape is proven. Same posture that
worked for inline editing, which was wired into `cards/grid.twig` alone
before being repeated across every block: prove the convention on a real
surface first, then repeat it mechanically.

Within v1, Background first and alone — it exercises every hard part of the
architecture (capability scoping, the target-resolution rules, the
optimistic apply, the three write-visibility truths, the usage count) on a
single panel. If that panel is right, the rest are variations on it.

## Still to verify at build time

Not design decisions — things that must be read from the code or tested
against reality rather than assumed:

1. **What the CP does when a `followsBase` theme saves a font stack.** See
   that section above. No gate was found; the behaviour has to be read and
   mirrored, not guessed.
2. **The optimistic lock against a real race**, not just in principle — the
   same generated files are editable in the CP at the same moment.
3. **The CSSOM walk's noise level** on a real page, with the fallback above
   as the answer if it turns out to be high.
