# Theme config — a theme changes a site's block rules without editing site config

Spec written 2026-09-13, from a discussion about what happens when a site gets a redesign by switching to a
library theme. Every "today" claim in §2 was read in the code on that date and carries its file and line;
anything not yet exercised says so. Status: **built 2026-09-14, uncommitted; verified headlessly and in the CP
slideout (§7.1).** Line numbers in §2 are from before the build; §7.1 names where things landed.

## 1. Goal

1. **A theme can change registered module config** — v1: `blockfields` and `items` — and the change applies
   while that theme is the one in use. Activating, adopting or removing a theme edits no site file.
2. **A theme that changes nothing inherits the site's config exactly.** No file, no difference.
3. **The code that reads config doesn't know which theme is in use or how it was chosen.**
4. **Closed by default.** A config file is theme-changeable only if the module that owns it registers it,
   and only in the parts it registers.

Not in this spec (§8): theme parents / a theme stack, a site overruling a theme, the CP following each
entry's own theme, a CP screen for editing theme config.

## 2. What we have today

| # | Claim | Where |
|---|---|---|
| T1 | Every module config read goes through `modules\support\Config::get()` — 19 calls in 15 files across both repos. It isn't cached: Craft's `getConfigFromFile()` `include`s the file on every call and resolves `*`/environment keys. It returns `array\|callable\|BaseConfig`. | [`support/Config.php:32`](../../craft-modules/modules/support/Config.php), [`services/Config.php:268`](../vendor/craftcms/cms/src/services/Config.php) |
| T2 | `BlockFieldCss` reads `blockfields` twice: `generate()` and `switchGroupMap()`. It runs inside `stablestwigextensions`' `Module::init()` on every CP request, AJAX included. `stablestwigextensions` is **first** in the bootstrap list, before `theme-picker`. | [`BlockFieldCss.php:37`](../modules/stablestwigextensions/services/BlockFieldCss.php), `:456`; [`Module.php:30`](../modules/stablestwigextensions/Module.php); [`app.php:66`](../config/app.php) |
| T3 | `BlockFieldCss` reads only `blocks.*.itemFields.hidden`, `blocks.*.itemFields.perLayout`, `blocks.*.ownFields.perLayout`, `blocks.*.layoutOptions` and `switchGroups`. An `ownFields.hidden` would be silently ignored. `assertNoOverlaps()` throws `InvalidConfigException`, which takes down every CP request. | `BlockFieldCss.php:71`, `:104`, `:183`, `:189`; `:68`–`:83` |
| T4 | `ItemResolver` reads `items` in its constructor, and a new resolver is built for every `itemData()` / eager-load call. It reads `$map['item']` with no guard, so a key without `item` is a PHP warning (an exception under `devMode`). `sectionKeys` overrides a key's chain per section. | [`ItemResolver.php:34`](../modules/stablestwigextensions/services/ItemResolver.php), `:59`, `:98`, `:157`; [`ModuleTwigExtensions.php:358`](../modules/stablestwigextensions/twigextensions/ModuleTwigExtensions.php), `:363` |
| T5 | On a site request, themepicker resolves the rendering theme in an `Application::EVENT_INIT` handler: Theme Override, preview, then the unbuilt fallback. The handle is handed to the Twig extension and the `activeThemeHandle` global and **stored nowhere else**. CP requests return early from that handler. | [`themepicker/Module.php:57`](../../craft-modules/modules/themepicker/Module.php), `:68`, `:83`, `:113`, `:142`; [`ThemeRegistry.php:252`](../../craft-modules/modules/themepicker/services/ThemeRegistry.php) |
| T6 | The CP-side lookup is `getActiveThemeHandle($siteId)`: the stored choice, blind to builds (theme-build-spec P9e). `Cp::requestedSite()` reads the `site` query param and **memoizes its first answer** for the request. | `ThemeRegistry.php:203`; [`Cp.php:4045`](../vendor/craftcms/cms/src/helpers/Cp.php) |
| T7 | Yii runs event handlers in the order they were attached. `stablestwigextensions` bootstraps first (T2), so any `EVENT_INIT` handler it registers runs **before** themepicker's resolution (T5). | Yii `Component::trigger()` |
| T8 | CSS registered during a CP request reaches slideout responses too: the element editor returns `getHeadHtml()` with its HTML. | [`ElementsController.php:671`](../vendor/craftcms/cms/src/controllers/ElementsController.php), `:2540` |
| T9 | A theme folder travels whole. Publish collects every file except dotfiles. Fetch refuses only empty and `..` paths. Adopt and Starter Kit write every file after `fetchAndValidateBundle()`. New site theme = `copyDirectory()` of the source. A new page theme copies exactly two paths. Remove deletes the folder. | [`LibraryPublisher.php:285`](../../craft-modules/modules/themedesigner/services/LibraryPublisher.php); [`LibraryClient.php:111`](../../craft-modules/modules/themedesigner/services/LibraryClient.php); [`ThemeInstaller.php:44`](../../craft-modules/modules/themedesigner/services/ThemeInstaller.php), `:286`; [`DesignerController.php:1212`](../../craft-modules/modules/themedesigner/controllers/DesignerController.php), `:1972`, `:4586` |
| T10 | Bundles already carry Twig templates, and Craft's Twig isn't sandboxed. A bundle is trusted like our own code: the library is a private repo behind `THEME_LIBRARY_TOKEN`, and Adopt is dev-gated. | theme-library-spec §7 |
| T11 | No theme in `stables`, `launch-pad` or `romeo-buddy` has a `config/` folder. romeo-buddy has its own copy of `BlockFieldCss` with the same two `Config::get('blockfields')` reads. | `ls`, grep |
| T12 | These read or describe `blockfields.php`/`items.php` by path: the `ramz-page-scaffold` skill; craft-modules `docs/content-import-spec.md:85`, `docs/page-scaffold-spec.md:98`, `docs/page-patterns.md:4,16`; stables `docs/inline-editing-spec.md:97,109,354`, `_blocks/CLAUDE.md`, `resources/css/README.md`, comments in `cards.twig:9` and `layouts/cards/grid.twig:18`, and `BlockFallback.php:73`. Migrations mention them historically and don't change. | grep, both repos, worktrees excluded |

## 3. Problem

A site gets a redesign by switching to a library theme. The new theme's Cards template renders `text`, but
the site's `blockfields.php` hides `text` on Cards, so editors can't fill it in. Today the only fix is
editing `blockfields.php` by hand, and switching back means editing it again. The same goes for `items.php`
when a forked template wants the item-then-entry fallback for a key the site doesn't set up.

A library theme's own `newBlocks` item has the same gap: the block gets the shared `items` field, and with
no `blockfields` entry it shows every item field (T3). This spec lets the theme ship rules for that block
while it's in use. Rules that stay with the block after a theme switch are §8.

## 4. Design

### 4.1 Where a theme's changes live

```
config/stables/blockfields.php              the site's rules — unchanged, still the defaults
themes/coastal/config/blockfields.json      only what Coastal changes
themes/default/                             no config/ folder → the site's rules, exactly
```

A theme file has the same shape as the site file after environment keys are resolved. Only the parts that
§4.2 registers:

```json
{
  "blocks": {
    "cards": { "itemFields": { "hidden": ["itemIcon"] } }
  }
}
```

```json
{
  "keys": {
    "intro": { "item": "intro", "entry": ["summary", "excerpt"] }
  }
}
```

**JSON, not PHP.** This isn't a defence against hostile bundles; templates are already code (T10). The
reasons: a theme file is data only (no `include`, closures or environment keys), so it can be validated
before Adopt writes it and before publish uploads it; Theme Designer can write it later; and `config/` stays
the only folder PHP config is included from.

**Not `extensions.json → blockRenders`**, which the discussion first suggested. That list describes forks
for display. A `config/` folder mirrors the site's own file name for name, so it's obvious what each file
changes, and it covers `items` and later registrations the same way.

**Page themes never have one.** They change colours only, are never the resolved theme (T5), and their
scaffold copies two paths (T9).

### 4.2 Registration — closed by default

A new `modules\themepicker\services\ThemeConfig` holds a registry:

```php
ThemeConfig::register('blockfields', [
    'blocks.*.itemFields' => $itemFieldsValidator,
    'blocks.*.ownFields' => $ownFieldsValidator,
]);
```

- A **unit** is a path pattern; `*` matches one key. A theme value at a unit's path **replaces the site's
  value there whole**, or adds it if the site has none. Replacing whole is what lets a theme show a field:
  merging lists (Craft's `ArrayHelper::merge`) could only ever hide more.
- A **validator** returns `null` or an error string. It checks what the consumer actually reads, nothing more.
- The owning module registers in its own `init()`. `stablestwigextensions` registers:

| File | Unit | Validator |
|---|---|---|
| `blockfields` | `blocks.*.itemFields` | object; `hidden` is a list of strings; `perLayout` maps a layout handle to a map of field → list of strings; no field in both `hidden` and `perLayout` (the rule `assertNoOverlaps()` enforces, T3); no other keys |
| `blockfields` | `blocks.*.ownFields` | object; only `perLayout`, same shape. `hidden` is refused because nothing reads it (T3). |
| `items` | `keys.*` | object; `item` is a string and required (read with no guard, T4); `entry` is a list of strings |

**Site-only, by not being registered:** `switchGroups`, `layoutOptions` (the content model, and what this
client is offered), `sourceField`, `sectionKeys` (the site's field names), and every other config file.
Rule for future registrations: never register anything that affects security headers, redirects, indexing,
legal pages, or which theme is active.

**Why in themepicker, not `support\Config::get()`** (this corrects the discussion). `support` sits below
themepicker: `ThemeRegistry` itself reads `Config::get('themepicker')` (`ThemeRegistry.php:354`), so
`Config` asking `ThemeRegistry` would point the dependency upward. And craft-modules mustn't hardcode file
names that belong to stables' local module; the owner declares them. The cost: consumers call
`ThemeConfig::get()` instead of `Config::get()` for the files they register.

### 4.3 Reading

`ThemeConfig::get(string $name)` returns the same type as `Config::get()`:

1. `$site = Config::get($name)`. If `$name` isn't registered, or `$site` isn't an array, return `$site`.
2. Work out the theme (§4.4). None → return `$site`.
3. The handle must match `^[a-z0-9\-]+$` (as `ThemeInstaller::readThemeExtensionsJson()` does); `$name` only ever comes from
   the registry, never from request input. No `themes/<handle>/config/<name>.json` → return `$site`.
4. Decode it. `Json::decode()` throws yii's `InvalidArgumentException`, not a `RuntimeException`, so catch
   that one. A non-object counts as invalid.
5. Walk the theme object. At a unit path: validate, then replace (valid) or keep the site value (invalid). A
   key on the way to a unit (`blocks`, `blocks.cards`) is only walked. Any other key is **unknown**.
6. Memoize by name + handle for the request, with a sources map (path → `site` or the handle) for §4.6.
   Console requests skip the memo, because a `queue/listen` worker outlives a theme change.

**When a theme file is wrong** (malformed, invalid unit, unknown key):
- **`devMode`**: throw `InvalidConfigException` naming the theme, file, path and reason. That's the same
  loudness as a mistake in site config (T3), and a theme author expects every key to do something, so
  ignoring one silently would be worse.
- **Production**: never throw. Keep the site value, and log once per theme + file + modification time, the
  way `logUnbuiltOnce()` logs once per build (`ThemeRegistry.php:330`).

Adopt and publish validate first (§4.7), so a library theme never reaches disk broken. Local hand edits are
what `devMode` catches.

### 4.4 Which theme

| Request | Theme | Why |
|---|---|---|
| Site | The handle themepicker resolved at `EVENT_INIT`: Theme Override, preview, unbuilt fallback (T5) | Config has to match the templates actually rendering. A mismatch is a `data.x` that throws under `strict_variables` in dev and is silently null in production. |
| CP | `getActiveThemeHandle(Cp::requestedSite()?->id)`: the stored choice for the site being edited (T6) | Blind to builds, like every CP lookup (P9e), so a missing build never changes what editors see |
| Console / queue | `getActiveThemeHandle()` for the current site; the §4.6 command takes `--site` / `--theme` | There's no request to resolve |

- **themepicker records the resolved handle.** `ThemeRegistry` gains a request-scoped
  `setRenderedThemeHandle()` / `renderedThemeHandle(): ?string`, set right after the resolution at
  `Module.php:83`. It's the same value as `activeThemeHandle`.
- **A site-request read before that is a bug.** Because of bootstrap order (T7), a `stablestwigextensions`
  `EVENT_INIT` handler would do exactly that: `devMode` throws, production uses site config and logs. Nothing
  reads these files that early today.
- **Known gap.** A landing page whose Theme Override names another theme: visitors get the override's rules,
  editors see the sitewide theme's. Using each entry's own theme (`bundleThemeForElement()`) is §8.
- **Known limitation.** A theme preview doesn't change the CP; editors always see the stored theme's rules.

### 4.5 Consumers

- **`BlockFieldCss`**: both reads (T2) become `ThemeConfig::get('blockfields')`. Generation moves from
  `Module::init()` to an `Application::EVENT_INIT` handler, still CP requests only:
  - `stablestwigextensions` bootstraps before `theme-picker` (T2).
  - `Cp::requestedSite()` keeps its first answer for the whole request (T6). Calling it during bootstrap is
    untested, and `EVENT_INIT` removes the question.
  - This handler needs only the stored theme, so running before themepicker's handler (T7) is fine.
  - `assertNoOverlaps()` stays for site config. Theme units arrive already validated.
  - The generated CSS header names what it came from: `config/stables/blockfields.php +
    themes/coastal/config/blockfields.json`.
- **`ItemResolver`**: `:34` becomes `ThemeConfig::get('items')`. The per-request memo absorbs the
  per-item construction (T4). `eagerLoadPaths()` reads the same config, so eager loading follows the theme.
- **Slideouts (T8).** On a multi-site install with a different theme per site, the slideout request has to
  land on the entry's site. Verify (V7). If it doesn't: skip registering on AJAX requests, since the host page
  already carries the CSS.

### 4.6 Seeing where a rule came from

`php craft theme-picker/themes/config <name> [--theme=<handle>] [--site=<handle>]` prints:
- the effective config as JSON;
- each unit path and its source (`site` or a theme handle);
- anything ignored, with the reason.

It exits non-zero when the theme file is invalid, so skills and the publish check can rely on it.

Everything in T12 that needs the **effective** rules switches to this command: the `ramz-page-scaffold`
skill, and craft-modules' `content-import-spec.md`, `page-scaffold-spec.md` and `page-patterns.md`.
`inline-editing-spec.md` (tier 4, unbuilt) gets a note that it must read `items` through `ThemeConfig`.
`_blocks/CLAUDE.md`, `resources/css/README.md` and the headers of `blockfields.php`/`items.php` mention theme
files. The comments in `cards.twig:9` and `layouts/cards/grid.twig:18` stay as they are: they point at
`items.php`, which is still true. `themes/CLAUDE.md` § Where a change goes gains a line (§5).

### 4.7 Bundles

- **Adopt and Starter Kit.** `fetchAndValidateBundle()` (T9) checks every `config/` entry:
  - **Refused, naming the file:** a file that isn't `.json`, a nested path, malformed JSON, a unit that fails
    validation, or any `config/` in a page-theme bundle.
  - **Stripped from the file, with a notice:** unknown keys in a registered file. Otherwise `devMode`, which
    Adopt always runs under, would throw on the next CP request. Adopt already rewrites `theme.json` and CSS
    imports this way.
  - **Kept, with a notice:** an unregistered file, e.g. "not applied on this site: `config/iconpicker.json`".
    Nothing reads it, so it's harmless, and it takes effect if the site registers that file later. Same
    pattern as the missing-fonts notice.
- **Publish.** The same checks run before `LibraryPublisher` uploads, so an invalid file never reaches the
  library. Publish is **strict**: unknown keys and files this site doesn't register are errors too, not
  notices, because the theme's author couldn't have seen them apply.
- **New theme.** `copyDirectory()` brings `config/` along (T9), and that's intended (§9, decision 4): the
  new theme's copied forks keep the fields they need. New page themes are unchanged and get none.
- **Remove.** The file goes with the folder, and there's nothing in site config to clean up.
- **romeo-buddy on the same craft-modules release:** `ThemeConfig` exists but nothing registers, and rb's own
  `BlockFieldCss` keeps calling `Config::get()` (T11). No change until rb is ported deliberately.

### 4.8 Budget

- **Per request, per registered file:** at most one `is_file()`, plus one read and decode when the theme has
  that file. No database query is added.
- **CP:** `BlockFieldCss` already runs uncached on every CP request (T2). This adds the stored-theme lookup,
  one cached read that theme-build-spec already budgets.
- **Site:** `ItemResolver` hits the memo after the first item.

### 4.9 ADA

Hiding a field changes editor UI only; the front end is untouched. Hidden field wrappers use `display: none`
(existing), which also takes them out of the tab order and the accessibility tree.

## 5. Where a change goes

- **A rule for every theme** → `config/stables/blockfields.php` / `items.php`, as today.
- **One theme's templates need different fields** → `themes/<handle>/config/<name>.json`, only for the
  blocks or keys that theme changes.
- **Content model or what a client is offered** (`switchGroups`, `layoutOptions`, `sectionKeys`) → site
  config only.

## 6. Build order

| Step | What | Repo |
|---|---|---|
| 1 | `ThemeConfig` (registry, `get()`, sources); the rendered-handle holder in `ThemeRegistry` + `Module.php:83`; the console command; Adopt / Starter Kit / publish checks. Minor release. | `craft-modules` |
| 2 | Registration in `stablestwigextensions`; `BlockFieldCss` onto `EVENT_INIT` + `ThemeConfig`; `ItemResolver`; CSS header; the T12 doc and skill updates; `themes/CLAUDE.md`. Lock to the release. | `stables` |
| 3 | §7 checks, then Gary's click-through on stables | — |
| 4 | launch-pad, then romeo-buddy, as explicit ports | later |

**Dependency:** none on the theme build (no build code). **Conflict:** theme-build Phase 5 ended by deleting
the receiving-site gates in `ThemeInstaller`, the same class step 1 changes, so step 1 started after Phase 5's
craft-modules release (v1.91.0, 2026-09-14).

## 7. Verification

| # | Check |
|---|---|
| V1 | **No theme config anywhere.** The CP's generated `<style>` on an entry edit page is byte-identical before and after. `page-checks.mjs` shows home and one interior page identical. |
| V2 | **The redesign case.** Coastal gets `config/blockfields.json` replacing `cards.itemFields` with `{"hidden": ["itemIcon"]}`. With Coastal active, a Cards item shows Text in the slideout; activate `default` and Text is hidden; back to Coastal and it shows. Click-verified in the CP. |
| V3 | **Inherit.** With `default` active, the command lists every unit as `site`, and the effective JSON equals `Config::get('blockfields')`. |
| V4 | **items.** Coastal's `items.json` changes `keys.intro`'s chain. A Coastal fork reading `data.intro` gets the new source, `default` gets the old, and `eagerLoadPaths()` follows. |
| V5 | **Rendering theme.** A landing page overriding to Coastal while `default` is sitewide renders with Coastal's `items`. With `devMode` off and the override unbuilt, it falls back and config follows the fallback. |
| V6 | **CP stays stored.** With `devMode` off and the stored theme unbuilt, CP rules are still the stored theme's. |
| V7 | **Multi-site.** Two sites with different themes: the entry edit page **and its Cards slideout** for site B use B's theme (T8). |
| V8 | **Broken theme file.** Malformed JSON, an `itemFields` overlap, `ownFields.hidden`, `switchGroups` in a theme file, an `items` key with no `item`. Under `devMode`: an exception naming theme, file, path and reason. With `devMode` off: CP and site load on site rules, and one log line per file change, not per request. |
| V9 | **Too early.** A site-request read before themepicker's `EVENT_INIT` throws under `devMode` with that message. |
| V10 | **Adopt.** Valid config applies. Malformed is refused, naming the file. An unknown key is stripped with a notice. An unregistered file is kept with a notice. A page theme with `config/` is refused. Starter Kit behaves the same. New theme copies `config/`; Remove removes it. |
| V11 | **Publish** refuses an invalid file, naming it. |
| V12 | **The command** prints sources, exits non-zero on an invalid file, and refuses `--theme=../x`. |
| V13 | **Budget.** A temporary counter shows at most one `is_file()` per registered file per request, and the query log shows no added queries. |

### 7.1 What the build was checked against (2026-09-14)

Where it landed: `craft-modules` `themepicker/services/ThemeConfig.php` (new),
`ThemeRegistry::setRenderedThemeHandle()`/`renderedThemeHandle()`, set in `themepicker/Module.php`'s `EVENT_INIT`,
`theme-picker/themes/config`, and `ThemeConfig::checkThemeFiles()` called from `ThemeInstaller::fetchAndValidateBundle()`
and `LibraryPublisher::publishTheme()`. In stables: `BlockFieldCss::themeUnits()`, `ItemResolver::themeUnits()`,
registration and the `EVENT_INIT` move in `stablestwigextensions/Module.php`.

| # | Result | How |
|---|---|---|
| V1 | **Pass.** With no theme file anywhere, the CP's generated CSS, the switch-group map and the effective `items` config are byte-identical to before the build, and home, `/test`, `/blog` and `/test-landing-page` are identical apart from the CSRF token. | Baseline captured before any edit; CLI probe plus `curl` |
| V2 | **Pass, in the CP.** Home (entry 195), its Grid Cards block's slideout: with no theme file, Text and Icon are hidden; with `themes/default/config/blockfields.json` (`hidden: ["itemIcon"]`) and a reload, the page's CSS header names the file and Text shows between Intro and Buttons while Icon stays hidden; with the file removed and a reload, Text is hidden again. Nothing was saved. | Browser pane, signed in; computed `display` of the item's field wrappers, plus a screenshot |
| V3 | **Pass.** `default` with no file resolves exactly to `Config::get()`, and every unit's source reads `config/stables/blockfields.php`. | Probe; `themes/config` |
| V4 | **Pass on the render path.** A `default` `items.json` blanking the heading chain removes the hero slide headings on home; the coastal landing page is unaffected; removing the file restores home byte for byte. The landing page has no item blocks, so a coastal `items.json` changes nothing visible there. | `curl` diffs |
| V5 | **Partly.** The site branch reads the rendered handle (probe: `coastal` gives coastal's chain, `default` gives the site's, memo per theme). A Theme Override page with item blocks and the unbuilt fallback weren't exercised. | Probe with a web request swapped in |
| V6 | **Not run** (needs `devMode` off and an unbuilt stored theme). The CP branch was checked to use `getActiveThemeHandle(Cp::requestedSite()?->id)`. | Probe |
| V7 | **Not run.** stables has one site. | — |
| V8 | **Pass.** Malformed JSON, not an object, an overlap, `ownFields.hidden`, `switchGroups`, `layoutOptions`, `hidden` not a list, an unknown key beside a valid unit, and an `items` key without `item` are each named with their path. Under `devMode` a real request returns 500 naming theme, file, key and reason. With `devMode` off the site's rules apply and the problem is logged once across two resolutions. | Probe; `curl` |
| V9 | **Pass.** A site-branch read before the handle is set throws "items was read before the theme for this request was worked out". | Probe |
| V10 | **Checks pass; the real download wasn't run.** `checkThemeFiles()` lenient strips `switchGroups` with a notice, keeps an unregistered `iconpicker.json` with a notice, refuses an invalid unit, a nested path, a `.txt` and malformed JSON by name, and refuses any config on a variant. Adopt from the library, Starter Kit, New theme copying `config/` and Remove weren't run. | Probe |
| V11 | **Checks pass; nothing published.** Strict mode turns the stripped key and the unregistered file into errors. `publishTheme()` wasn't called: it pushes to GitHub. | Probe |
| V12 | **Pass.** Sources listed per unit; a file with problems exits 1 and lists them; a valid file and the active theme exit 0; `--theme=../coastal`, a variant (`sunset`) and an unregistered name (`security`) are refused. | `php craft` |
| V13 | **By reading the code, not instrumented.** One `is_file()` per registered file per theme per request, memoised; no query added. | — |

**Also found:**
- **romeo-buddy is dormant on this code.** It links the same craft-modules checkout: its home returns 200, and `themes/config blockfields` reports that nothing is registered.
- **From the CLI, Craft's web `Request::getIsConsoleRequest()` returns true.** A script that boots the web app never takes the site or CP branch until a request subclass says otherwise. Noted in `themepicker/CLAUDE.md`.
- **Template caches aren't keyed on config**, the same as with site config today. No `{% cache %}` block in `_base` resolves items (`hero/slider.twig`, `collage.twig`, `_listing.twig` and the cached partials were checked), so no stale markup is possible now. A future cached block that calls `itemData()` would keep old output until caches clear.
- **PHPStan isn't installed locally.** craft-modules' CI runs it on push.

## 8. Later — not in this spec

- **Theme parents.** When `theme.json` gains `parent` (missing = `_base`, `false` = none), `ThemeConfig`'s
  levels become site config, then the stack from the bottom up, and `themes/_base/config/` becomes possible.
  Consumers don't change.
- **Each entry's own theme in the CP** via `bundleThemeForElement()`, for Theme Override landing pages (§4.4).
- **A site overruling a theme**: skipped 2026-09-13. Cheap to add later because merging lives in
  `ThemeConfig::get()` and units are declared: a site `locked` list, and the merge skips those units.
- **Rules that follow a library block**, not the theme that brought it (§3).
- **More registrations**: `iconpicker` (icon set per theme, though stored icon names can go missing) and
  `page-templates`.
- **A Theme Designer Blocks section** (Gary's idea, 2026-09-13, based on `blockfields` for now): go through
  each block and choose which fields are shown, writing `themes/<handle>/config/blockfields.json`. This spec
  keeps it possible:
  - **Saves** write whole units, and a block the theme hasn't touched is simply absent.
  - **"Use site rules"** deletes the block's unit. It's also how a new theme drops a rule it copied from its
    source (§9, decision 4).
  - **Each block shows its source** (site or this theme), from the same data as §4.6.
  - **Every save runs the same validators** as Adopt.
  - **Site-only parts** (`switchGroups`, `layoutOptions`) are shown read-only, never edited.
  - **`perLayout` needs a field × layout grid**, not just on/off.

  Theme Designer is devMode-only, which fits these being local files.
- **Splitting default block templates out of `_base`**, once a theme needs `parent: false`.

## 9. Decisions and open questions

Decided by Gary, 2026-09-13:

1. **Unknown keys on Adopt: strip them, with a notice** (§4.7).
2. **A broken local theme file under `devMode`: throw** (§4.3).
3. **v1 registers only `blockfields` and `items`.**
4. **A new theme copies `config/` from its source**, and the Theme Designer Blocks section (§8) gets a
   per-block "Use site rules" reset.
   - **Why copy:** `copyDirectory()` also copies the source's forked templates (T9). A fork that renders
     `text` still needs `text` shown, and dropping the config would hide it again.
   - **The trade-off:** New theme resets `followsBase` and `locked` instead of copying them
     (`DesignerController.php:1229`, `:1238`), and field choices are the same kind of setting once Designer
     edits them. The reset button covers that case in one click, and a copy never breaks a fork.

No open questions remain.
