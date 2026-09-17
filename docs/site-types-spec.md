# Site types — grouping themes by the content they expect, and warning before a switch

Written 2026-09-14 from Gary's idea: themes get a type, so themes can be grouped and a switch that crosses types can
warn about what the new theme won't show. Every "today" claim in §2 was read in the code on that date. **Status:
draft, unbuilt; all decisions made (§10).** Build order: [`theme-content-roadmap.md`](theme-content-roadmap.md). Companion to [`theme-designer-blocks-spec.md`](theme-designer-blocks-spec.md) (what
a theme shows) and [`business-content-spec.md`](business-content-spec.md) (the Business baseline).

## 1. Goal

1. **Every site theme declares a site type.** A type is defined by content that differs greatly, not by industry or a
   few fields.
2. **Grouping everywhere themes are listed:** Theme Picker, the Theme Designer switcher and Library, site-launcher.
3. **An honest warning before a switch:** what content the site has that the new theme won't show, with counts.
   Nothing is ever deleted by a switch.
4. **An honest check before an Adopt:** what a library theme needs (blocks, fields, layouts, sections) that the
   receiving site doesn't have, recorded when the theme is published and compared on the site adopting it (§5).

**`siteType` is a label, never a mechanism.** It groups and filters. Every warning and check is worked out from what
themes render and need, so a mislabelled theme can't cause a wrong warning.

### The types

Decided with Gary 2026-09-14:

| Type | Content model | Examples |
|---|---|---|
| **Business** | Pages from blocks, plus blog, events, jobs, articles, projects, topics, authors, galleries and related content (business-content-spec) | Most client sites: services, trades, nonprofits, churches, restaurants, tourism, publications, agencies and portfolios |
| **Catalog** | Products: price, variants, SKU, stock, maybe a cart | Shops, product lines, book lists |
| **Directory** | Many records with structured fields, filters and search, maybe a map | Rosters and player profiles (a team site, or one player's), member directories, real estate, locations |
| **Custom** | Anything else | — |

**Folded into Business** (Gary, 2026-09-14):
- **Publication:** articles, topics and authors become Business baseline content.
- **Portfolio:** a projects/work section, galleries and client fields are common on service sites too. The section is
  renamed per site the way Events already is.

**Sports sites are Directory**, whether they're a team site or one player's. The player data is the same records
either way, so moving between the two is easy.

A restaurant, church or nonprofit is **not** its own type. Each is Business plus one section.

## 2. What we have today

| # | Claim | Where |
|---|---|---|
| S1 | **`type` is taken.** `theme.json` `"type": "page"` marks a Theme variant; anything else is a site theme. The library row hardcodes `type: 'site'`. | `ThemeRegistry.php:34`, `:125`, `:210`; `LibraryPublisher.php:108` |
| S2 | **Every `theme.json` writer keeps unknown keys:** each reads the whole file, changes one key, and writes it back. New site themes copy the source folder, and new variants copy `theme.json` byte for byte, so a new key is inherited from the source. | `DesignerController.php:1015`, `:1102`, `:1249`, `:1257`–`:1278`, `:2115`, `:2523`, `:2573`, `:9915`; `ThemeInstaller.php:67`–`:81` |
| S3 | **The library catalog drops unknown fields:** `LibraryPublisher` writes a fixed row, and both readers (`LibraryClient::fetchIndex()`, site-launcher `lib/themeLibrary.js`) rebuild rows from a fixed list. The one published theme (`meridian`) has tags, but no categories, colors or extensions. | `LibraryPublisher.php:105`–`:122`; `LibraryClient.php:66`–`:82`; `site-launcher/lib/themeLibrary.js:101`; `theme-library/index.json` |
| S4 | **`getThemes()` returns only** `handle`, `name`, `thumbnail` and `type`. `manifest($handle)` returns the whole file. | `ThemeRegistry.php:84`–`:89`, `:154` |
| S5 | **Activating a theme has no confirm step:** a plain POST from the card (`index.twig:96`), plus the preview banner (`:44`), the front-end preview banner (`_partials/themePreview.twig:23`) and `theme-picker/themes/activate`. The `form[data-confirm]` JS only initialises when the variant panel exists. | themepicker `templates/index.twig`; `ThemesController.php:110`–`:135`; `theme-picker.js:101`, `:237`; console `ThemesController.php:290` |
| S6 | **The active theme is per site** (`theme_settings.activeTheme`), while `themes/` is shared. A landing page's Theme Override isn't changed by a sitewide switch. | `ThemeRegistry.php:227`, `:568`, `:276`–`:307` |
| S7 | **Section templates fall back quietly.** `_router.twig` tries `_sections/<handle>/_detail`, then `<handle>/<handle>`, then `_sections/default`, all with `ignore missing`. A section whose template exists only in the old theme renders through the generic page layout after a switch, not an error. The `posts` block renders nothing for a missing `_sections/<postType>`. | `_router.twig:1`–`:5`; `_blocks/posts.twig:7` |
| S9 | **Adopt is one request:** `actionDownloadTheme` fetches and validates the bundle, writes it to disk, then sets a notice. There's no preview step between fetching and writing. `LibraryClient` can already fetch a single file (`fetchFileContent()`, private; `fetchThumbnail()` public). | `DesignerController.php:1494`–`:1527`; `LibraryClient.php:95`, `:151`, `:218` |
| S10 | **Nothing records what a theme needs.** The only content-model check is `extensions.json` `newBlocks`/`newLayouts`, which covers blocks using the shared `items` field, and layout options. A theme whose templates read fields the receiving site lacks adopts silently; those reads come back null. | `ThemeInstaller.php:342`–`:462` |
| S8 | **Where type input would go:** Theme Designer General chips (`_tab-general.twig:36`–`:66`), the New theme form (`_new-theme-form.twig:45`), the publish form (`:443`), the Library facets and hardcoded "Site theme" chip (`_library.twig:60`, `:171`), the site-launcher chips and badge (`public/app.js:147`, `:216`), and the skills `ramz-site-design` (`:18`), `ramz-design-import` (`:33`) and `ramz-theme-publish` (`:21`, `:46`). | as cited |

## 3. The key

```json
{ "name": "Diamond", "siteType": "directory" }
```

- **`siteType`**: one of `business`, `catalog`, `directory`, `custom`.
  - It isn't `type`, which is taken (S1).
  - **A missing key reads as `business`**, so every existing theme is correct with no edits.
- **Constants live in `ThemeRegistry`** (`SITE_TYPES`, with labels), next to `TYPE_SITE`/`TYPE_PAGE`. These are RAMZ-wide
  names, not stables handles, so craft-modules may hold them.
  - `getThemes()` gains `siteType` (S4).
  - An unknown value reads as `custom` and is logged once.
- **Variants have no site type.** A variant restyles whichever site theme is active. Theme Designer refuses the key on a
  variant, and `checkThemeFiles`-style bundle checks strip it with a notice.
- **Copies inherit it** (S2). The New theme form shows the source's type, and it can be changed there.

## 4. What the warning says

**Why the warning isn't built from type definitions.** "Business expects blog; Directory expects players" would need
each site's section handles written into a type list, and it would still be wrong the first time a theme differs from
its type. The warning is **worked out from the two themes**, the same way theme-designer-blocks-spec works out
whether a block is offered: a theme handles something when it has a template for it.

Switching from theme A to theme B on site S, list:

1. **Sections with live entries on S whose templates are in A's folder only.** A handles `_sections/<handle>/`; neither
   B nor `_base` has it.
   - After the switch those entries render through the generic page layout (S7).
   - Example: "**Players**: 34 entries will show as plain pages".
2. **Blocks used on S whose only template is in A** (theme-designer-blocks-spec §4.1 default).
   - Those render through the cards fallback, or nothing when empty.
   - Example: "**Stat Rows**: used on 6 pages".
3. **Blocks and layouts B doesn't offer**, from B's `blockfields.json`, with usage counts. They keep rendering but
   can't be added.
4. **The type change itself**, as a headline: "Diamond is a **Directory** theme; Coastal is **Business**."

- **When nothing is listed**, the switch has no confirmation, even across types. A type change with nothing lost is
  just a label change.
- **The last line is always the same:** "Nothing is deleted. Switching back restores all of it."

**Counts**
- Live entries per section, and block usage from the field-aware counters (theme-designer-blocks-spec §4.3).
- One query per listed item. It runs only when the dialog opens, never on page load.

### Where it shows

| Place | Behaviour |
|---|---|
| **Theme Picker card** | "Activate" opens a confirmation dialog listing §4 items, when there are any. The dialog is wired independently of the variant panel (S5), keyboard-trapped, and titled with `aria-labelledby`. Server data comes from a new `theme-picker/themes/switch-report` action that returns JSON. |
| **Card grouping** | Site theme cards grouped under type headings (`h2`). The active theme's type is shown first, and "Custom" last. |
| **Preview banner Activate** (CP and front end) | Same dialog. The front-end banner links to the CP Theme Picker with the theme preselected, rather than activating straight from the front end once there's something to report. |
| **Console `theme-picker/themes/activate`** | Prints the report; exits non-zero when there are items, unless `--force`. |
| **Landing pages with a Theme Override** | Not affected by a sitewide switch (S6). The report says how many there are: "3 landing pages keep their own theme". |

The server action still activates on POST. The dialog is UX, not a gate, matching Activate today.

**As built (2026-09-16, craft-modules `SwitchReport`, `BlockUsage`):**
- **Report:** `SwitchReport::report(from, to, siteId)` returns the type headline, up to three groups ("Shown as plain
  pages", "Blocks only A has a template for", "Not offered by B"), `itemCount`, the landing page line and the footer.
  Blocks come from `BlockRules::resolve()` for each theme; a layout counts when B hides a value A offers, and its usage
  is filtered by the Button Box value. Theme-only blocks aren't listed again as unoffered.
- **Counts:** `BlockUsage` (shared with the Blocks tab) counts uses on the site and the pages from the first 200; past
  that the detail says "used N times" instead of pages. Sections count live entries on the site.
- **Landing pages:** `ThemeRegistry::landingPageOverrideCount()` counts entries whose override names a site theme. It
  isn't an item, so on its own it never asks for confirmation.
- **Dialog:** `_switch-dialog.twig`, a Garnish modal like the Edit window dialog, initialised whether or not the variant
  panel shows. Activate fetches the report; nothing listed submits straight away, and a failed fetch activates as before.
  Focus goes to the title and back to the card's Activate on close.
- **Grouping (revised 2026-09-16, Gary picked mockup C):** one grid, no per-type rows. The core theme comes first,
  then the rest by site type and name. Each card shows its type as a small caption over the name, and Active, Preview
  and Core as pills (`tp-chip--on`, `tp-chip--scheduled`, `tp-chip--core`), matching the Theme variant cards. The CP
  preview banner uses the same violet tint as the Preview pill.
- **Front-end banner:** Activate is always a link to `theme-picker?switch=<handle>` (plus `site` on multi-site), which
  opens the dialog even when nothing is listed, since the choice was made on another page. Checking first would run the
  report on every page view while previewing.
- **Console:** `theme-picker/themes/activate` prints the report when there's anything or a type change, and exits 1
  with "Run again with --force" when there are items.

## 5. What a theme needs — recorded at publish, checked on Adopt

Switching themes on one site never needs a field diff: blocks and fields are installed site-wide, so every theme there
sees the same fields (§4 covers what they render). Adopting a library theme onto **another** site is different: its
blocks, fields and sections can differ a lot from the site the theme was built on, and today nothing checks (S10).

### 5.1 `requirements.json`

Generated at publish into the bundle root, **never hand-written** (publish overwrites it). It describes the theme as it
stood on the author's site:

```json
{
  "generatedAt": "2026-09-14T18:00:00Z",
  "blocks": {
    "cards": {
      "name": "Cards",
      "template": "base",
      "fields": {
        "layoutCards": { "type": "verbb\\buttonbox\\fields\\Buttons", "options": ["grid", "list"] },
        "items": { "type": "craft\\fields\\Matrix", "entryTypes": ["item"] },
        "blockHeading": { "type": "craft\\fields\\Matrix", "entryTypes": ["blockHeading"] }
      },
      "children": {
        "item": { "heading": { "type": "craft\\fields\\PlainText" }, "text": { "type": "craft\\ckeditor\\Field" } }
      }
    },
    "playerStats": { "name": "Player Stats", "template": "theme", "fields": { "items": { "type": "craft\\fields\\Matrix", "entryTypes": ["item"] } } }
  },
  "sections": {
    "players": { "name": "Players", "type": "channel", "entryTypes": { "player": { "position": { "type": "craft\\fields\\Dropdown" } } } }
  }
}
```

**What goes in:**
- **Blocks:** only those the theme **offers** (theme-designer-blocks-spec §4.1, including the template default).
  - `template` is `theme` when the theme ships the block's template, else `base`.
- **Fields:** each block's own fields and each nested type's fields that the theme **shows**. A per-layout field counts
  as shown.
  - Hidden fields are left out, because nothing the theme's editors can fill in depends on them.
- **Layouts:** the layout options the theme offers.
- **Sections:** only those whose `_sections/<handle>/` templates live in the theme's folder (the same test as §4),
  with each entry type's fields.
- **Types:** field classes are compared as plain strings, never instantiated.

**Built from the same data as the Blocks tab** (theme-designer-blocks-spec §4.3), so the requirements, the tab and the
CP rules can't disagree. In particular they share one effective-rules resolver in craft-modules, rather than
`BlockFieldCss` and Theme Designer each working rules out separately; the blocks spec gains that line.

**The proxy, stated plainly:** "fields the theme shows" stands in for "fields its templates read". A template that
reads a field editors can't see would be missed. Parsing Twig for field reads (`itemData()` keys, dynamic access,
includes) would be far less reliable, so the check doesn't try.

**Dormant:**
- A site that doesn't register `blockfields` with `ThemeConfig` publishes `sections` only.
- An adopting site that doesn't register it checks sections only, and says blocks couldn't be checked.

### 5.2 The diff

On the receiving site, for the chosen site's content model:

| Finding | Meaning | Existing path |
|---|---|---|
| **Missing block** | No entry type with that handle, or it's in no builder field | If the bundle's `extensions.json` `newBlocks` has it: the existing Add flow. Otherwise report only. |
| **Missing field** | The block or nested type exists, but its layout lacks the field | Report; add it in the CP |
| **Different field type** | Same handle, different class, e.g. Plain Text here but CKEditor in the theme | Report; the template may render it oddly |
| **Missing layout** | The layout field lacks an option the theme offers | `newLayouts` Add flow if the bundle has it, else report |
| **Missing section** | No section with that handle, or its entry type lacks listed fields | Report |

- **What the site has and the theme doesn't need isn't a finding.** §4 covers content the theme won't render, at
  switch time.
- **Matching is by handle.** A site that renamed a handle reads as "missing". The report says so in one line rather than
  guessing at renames.

### 5.3 Where it shows

| Place | Behaviour |
|---|---|
| **Library inspector** | On opening a theme, fetch only its `requirements.json` (one file, cached 5 minutes like thumbnails) and show **Fits this site**, or **Needs 3 things** expanding to the list. A theme published before this has no file and shows "Not checked". |
| **Adopt** | Becomes two steps (S9): Download fetches and validates, then shows a confirmation with the §5.2 report when there are findings. Confirming writes the files, as today. No findings: it writes straight away, as today. |
| **General tab of an adopted theme** | A **Needs** card while findings remain, recomputed on load (one layout walk), with links to the CP for each, so the gap stays visible after Adopt. |
| **Starter Kit** (console + site-launcher) | Prints the report against the fresh stables baseline; it never blocks. site-launcher shows it on its confirmation step. |
| **Console** | `php craft theme-designer/requirements/check <handle> [--site=]` for an installed theme, exiting non-zero on findings; `.../generate <handle>` regenerates locally without publishing. |

**As built (2026-09-16, craft-modules, roadmap 2.6):**
- **Check:** `ThemeRequirements::check(?json)` gives `unchecked` (no file), `invalid` (lenient parse failed), `fits` or
  `needs`, with findings that now carry a `cpUrl` (the entry type, field, or New section / entry type page).
- **Library inspector:** `actionLibraryRequirements` fetches `themes/<handle>/requirements.json` through
  `LibraryClient::fetchRequirements()` (a 404 is "no file"), caches the file for 5 minutes and runs the check each time.
  The inspector shows Fits this site / Needs N things (Show expands the list) / Not checked, and the type chip replaces
  "Site theme". A **Site type** facet sits above Category.
- **Adopt:** Download checks the fetched bundle's file. With findings and no `confirmed`, it stores a review in the
  session and redirects back to the Library, which opens a modal listing them with **Download anyway** (posts
  `confirmed=1`, re-fetching the bundle) and Cancel. Otherwise it writes straight away, as before.
- **General tab:** a **Needs** card on a site theme whose own `requirements.json` doesn't fit (or isn't valid), each row
  linking to the CP in a new tab. A theme published from this site fits, so it shows nothing.
- **Verified with fixtures only** for "needs": no library theme has a requirements file yet (Meridian predates them), so
  the review redirect hasn't run against a real bundle with findings.

**Never blocking.** A theme with findings still adopts. Its templates guard missing data (theme-designer-blocks-spec
§3), so the effect is missing pieces, not errors. The report makes that a choice rather than a surprise.

### 5.4 Type starter sets

Decided 2026-09-14: a site type can carry a **starter set**, the blocks, fields and sections a new site of that type
starts with. Only under four conditions, each of which heads off a real problem:

1. **Applied when a site is created, or deliberately with consent, never by a theme switch or New theme.** Content model
   created by a theme switch is Shopify's trap: content tied to a theme is stranded when you switch away.
   - The place is site-launcher's Starter Kit step, on a fresh database with nothing to lose.
   - The console command refuses a site that already has live entries in any section the set would change, unless
     `--consent` is passed. That consent path on existing sites is §5.5.
2. **Additive on top of Business, never a replacement.** A sports site still wants pages, blog, authors and galleries.
   - The Business set is the stables baseline, the content model business-content-spec builds, so it's empty as a
     file.
   - Directory adds to it, and Custom adds nothing. No set removes anything, so switching type later never deletes.
3. **Same format as `requirements.json`, plus creation.** One schema (§5.1), one validator, one create path.
   - **Self-checking:** after the Starter Kit applies a Directory set and a Directory theme,
     `theme-designer/requirements/check <theme>` must report nothing missing. Otherwise the set and the theme have
     drifted, and the Starter Kit says so.
4. **Written from real sites, never invented.** A guessed Directory or Catalog model would be baked into every site
   that starts from it, which is the change-it-on-N-production-sites problem.
   - **Now:** the mechanism, plus Business, which is the baseline being built anyway. `starter-sets/business.json` is
    in the library as of 2026-09-16 — empty by design, so every reader gets a definite "nothing to add" rather than a
    missing file.
   - **Directory:** extracted from the softball build once it's real (players, stats, schedule).
   - **Catalog:** written when the first catalog client arrives.

**Where sets live:** `theme-library/starter-sets/<siteType>.json`, next to the themes they pair with, because the
Starter Kit reads the library anyway. A set carries **no templates**: templates belong to a theme, e.g. a Directory
theme ships `_sections/players/`, and the theme's `requirements.json` is what the set has to satisfy.

**The format as built** (2026-09-15): requirements.json's shape, where each field may also carry `name`, `instructions`,
`tab`, `settings` (a per-type allowlist), `optionLabels`, `sources` (section handles, Entries) and `volume` (Assets) or `like`
(the CKEditor field whose settings to copy, default `text`); a block may carry `group` and `builderFields` (default
`pageBuilder`); a section `uriFormat`, `template` and `maxLevels`. `generatedAt` and a block's `template` are optional in a set.

**How a set is applied** (`php craft theme-designer/starter-kit/set <siteType> [--file=] [--dry-run] [--consent] [--theme=]`,
run by site-launcher after the theme; without `--file` it reads the library's `starter-sets/<siteType>.json`):
- **Plan first:** every operation is listed, and refusals (a handle that exists with another type, a field type outside
  the allowlist, a handle Craft's own validation rejects, such as a reserved word) stop the set before anything is written.
- **Order, by dependency:** non-Matrix fields → nested entry types → Matrix fields → block and section entry types (new
  ones built whole, existing ones gain fields in place) → sections → Entries field sources → builder-field membership.
  Each step is skipped when it's already present, so re-running changes nothing.
- **New entry types** are built whole. **Existing baseline types** (e.g. `item` gaining `statNumber`) get fields
  inserted into their existing layout **in place**, never rebuilt (content is keyed by layout element uid).
- **Field classes are an allowlist:** Plain Text, CKEditor (using the site's existing CKEditor config), Assets, Number,
  Date, Lightswitch, Dropdown / Button Box (with options), Entries (sources by section handle), Matrix (entry types from
  the set) and Link. Anything else is refused by name.
- **All through Craft's services.** It writes committed project config and is additive only, so it's safe under the
  migration-order rule. Every step asserts its outcome, and the whole apply stops at the first failure, reporting what
  was already created.
- **Handles are checked** against the baseline first: a set that reuses a baseline handle with a different field class
  is refused before anything is written.

**site-launcher:** picking a theme whose `siteType` has a set shows "Starts with: Players, Teams (Directory)" on the
confirmation step. The skills `ramz-site-design` and `ramz-site-build` pass the type through.

**site-launcher as built (2026-09-16, roadmap 2.7):** type chips above the categories (Business selected; only types the
library has, hidden when that's just Business), the type as each card's badge, Default under Business. Review shows the
type and "Starts with: …" from `GET /api/starter-sets/:siteType`, which summarises section and block names. `createSite`
reads the kit's type from the library row, and when that type has a set runs `starter-kit/set <siteType>
--theme=default` after `apply` and before the build; a failure or drift is a warning on the result screen. No set is in
the library yet, so the apply path hasn't run for real; the Review line was checked with a stubbed response.

### 5.5 Later

**Creating what's missing on an existing site, with consent.** The same create path as §5.4, offered on Adopt as an
Add row per missing item, like today's extensions. It's the path to a Directory theme adopted onto a live Business
site. It needs its own spec: on a site with real content, every creation has to be additive, previewed, and verified on
a restored production snapshot with `php craft up` before release.

**Written 2026-09-16:** [`content-model-consent-spec.md`](content-model-consent-spec.md) — a draft for review, nothing
built. Four decisions are open at its §8.

## 6. Theme Designer, library, site-launcher

- **Theme Designer General tab:**
  - A **Site type** chip in the hero, with a select in Settings for site themes.
  - Saving goes through `actionSaveSettings` (whole-file rewrite, S2).
  - Locked themes are read-only.
- **New theme form:** a Site type select for site themes, defaulting to the source's.
- **Theme switcher rail:** site themes grouped by type, with variants staying in their own group.
- **Publish:**
  - `siteType` is required. A theme without the key publishes as `business`, after confirming the form's pre-filled
    value.
  - `LibraryPublisher` writes `siteType` into the `index.json` row.
- **Readers keep it (S3):**
  - `LibraryClient::fetchIndex()` and site-launcher `themeLibrary.js` add `siteType`.
  - A row without it reads as `business`, so meridian needs no republish.
- **Library page:**
  - A **Site type** facet above Category.
  - The inspector's hardcoded "Site theme" chip becomes the type.
- **site-launcher Starter Kit picker:**
  - A type chip row above categories, with Business selected by default.
  - The card badge shows the type.
- **Adopt and Starter Kit:** the key travels in `theme.json`; the Adopt flow gains the §5 check.
- **Publish:** also writes `requirements.json` (§5.1), replacing any copy already in the theme folder.

**As built (2026-09-16, craft-modules, Theme Designer part only; the Library page and site-launcher are roadmap 2.6/2.7):**
- **General tab:** a site type chip in the hero, and a **Site type** segmented choice in Settings that saves on change
  through `actionSaveSettings`, which now writes only the keys a form posts (`name` from rename, `siteType` from the
  choice). A locked theme dims it, the server refuses it and the choice reverts.
- **New theme:** a Site type select for site themes, starting as the source's and following Start from until changed
  by hand; hidden and not posted for a Theme variant.
- **Switcher:** Base, then "Business themes", "Directory themes"… in `SITE_TYPES` order with `default` first in its
  group, then "Theme variants".
- **Publish:** a required Site type select, pre-filled from `theme.json`, with a note when the key is missing. The chosen
  value is written to the theme's `theme.json` first (refused on a locked theme whose type differs), then into the
  bundle's `theme.json` and the `index.json` row. `requirements.json` is regenerated into the theme folder before the
  bundle is collected, so the library copy always matches this site.

## 7. Skills

- **`ramz-site-design`:** collect the site type with the shared inputs (`:18`), defaulting to Business.
- **`ramz-design-import`:** pass `siteType` when creating the theme (`:33`).
- **`ramz-theme-publish`:** check `siteType` is set, and propose it at the G7 gate with tags and categories. Show the
  generated `requirements.json` summary at the same gate.
- **`craft-modules/docs/site-design-spec.md:282` and `:293`:** add the field.

**As built (2026-09-16, roadmap 2.8):** `ramz-site-design` gathers the site type (proposed from the source, Business
when unclear) and `ramz-site-build` passes it on; `ramz-design-import` posts `siteType` with `new-theme`;
`ramz-theme-publish` checks `theme.json` for it, previews `requirements/generate --print` and puts both at G7, and
posts `siteType` with the publish. `site-design-spec.md` §6 and §8 carry the field and `requirements.json`.

## 8. Budget, security, ADA

- **Budget:**
  - The key adds nothing to site requests.
  - Theme Picker's page load reads manifests it already reads.
  - The switch report runs only on demand.
  - Requirements are generated once per publish. The diff is one walk over builder-field layouts and sections (about
    20 entry types in stables), run on Adopt, on the inspector (one small file fetch, cached 5 minutes) and on an
    adopted theme's General tab. Never on site requests.
- **Security:**
  - Values are an enum, checked on every write.
  - The report action is gated like `actionSelect` (permission `accessThemePicker`, CSRF on POST) and returns counts
    only, no entry titles.
  - `requirements.json` is untrusted bundle data: schema-checked (handles `^[a-zA-Z][a-zA-Z0-9]*$`, class names as
    plain strings, size-capped), only ever compared, never executed or used to create anything in v1.
- **ADA:**
  - The dialog is a real modal: focus moves in and returns on close, Escape closes it, and it has a title and a
    description.
  - The list is a `ul`; counts are text, not colour.
  - Type group headings are real headings.

## 9. Verification

| # | Check |
|---|---|
| T1 | Every existing theme reads as `business`; Theme Picker, Designer and Library render as before apart from grouping |
| T2 | Every `theme.json` writer (the §2 S2 list) keeps `siteType`: rename, lock, effects, background fallback, colour groups, New theme, Adopt |
| T3 | Switch report, theme-only section: a section with a template only in theme A and 3 entries → listed when switching to B, with the right count; after switching, an entry renders through `_sections/default` (not a 500); switching back renders it as before |
| T4 | Switch report, theme-only block and not-offered block → listed with page counts |
| T5 | Same-type switch with nothing to report → no dialog; cross-type with nothing to report → no dialog |
| T6 | Console activate exits non-zero with items, zero with `--force` |
| T7 | Multi-site: report counts only the chosen site's entries |
| T8 | Publish writes `siteType`; `fetchIndex()` and site-launcher keep it; meridian reads as `business` |
| T9 | Dialog keyboard: Tab stays inside, Escape closes, focus returns to Activate; VoiceOver reads the title and list |
| T10 | **Generate:** publishing Coastal with a Blocks-tab change writes `requirements.json`: offered blocks only, hidden fields absent, layout options as offered, no sections (Coastal has no theme-only section); `generate` locally gives the same file |
| T11 | **Diff:** on a scratch site missing `text` on Item and with `intro` as a different field type, Adopt shows "Missing field: Text on Cards → Item" and "Different field type: Intro"; confirming writes the bundle; cancelling writes nothing |
| T12 | **Theme block:** a theme with `playerStats` (in its `newBlocks`) reports "Missing block" with the Add flow offered; without `newBlocks` it's report only |
| T13 | **Theme section:** a theme with `_sections/players/` reports "Missing section: Players" on a site without it |
| T14 | **Library inspector:** Fits this site / Needs N / Not checked (meridian, published before this) |
| T15 | **Untrusted file:** a bundle `requirements.json` with a bad handle, a 5 MB file, or non-JSON is refused by the strict check and reported by the lenient one; nothing is instantiated from class names |
| T16 | **Starter Kit** prints the report and still applies; the console `check` exits non-zero on findings |
| T17 | **Starter set apply:** on a fresh stables, a test Directory set (one section, one entry type, a field added to `item`) applies; running it again changes nothing; the `item` field is inserted without touching existing content keys; `requirements/check` against a matching test theme reports nothing |
| T18 | **Starter set refusals:** a set with a disallowed field class, or a baseline handle reused with a different class, is refused before any write; on a site with live entries in a section the set changes, apply refuses without `--consent` |
| T19 | **Partial failure:** a forced failure midway stops the apply, reports what was created, and a re-run completes the rest |

## 10. Decisions and questions

Decided by Gary, 2026-09-14:
1. **Types:** Business (the default; Publication and Portfolio folded in), Catalog, Directory, Custom.
2. **A missing key is `business`.**
3. **Sports sites (team or single player) are Directory.**
4. **`siteType` stays a label.** Warnings come from what themes render (§4) and need (§5): add `requirements.json`
   and the Adopt diff.
5. **The site's own theme preview banner warns before activating.** Its Activate button links to the CP Theme
   Picker with the theme preselected, where the §4 dialog shows, instead of activating straight away from the site.
6. **Site types carry starter sets** under the four §5.4 conditions: the mechanism and Business now, Directory from
   the softball build, Catalog with its first client.

No open questions remain.
