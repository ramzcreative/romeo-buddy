# romeo-buddy — CLAUDE.md

## Project Overview
The Romeo & Buddy picture-book brand site — built from RAMZ Creative's `stables` Craft CMS boilerplate, now diverged into its own client site. See [`../stables/CLAUDE.md`](../stables/CLAUDE.md) for the boilerplate conventions this site inherited.

## Tech Stack
- **Craft CMS** 5.10.11 · **PHP** 8.2 · **MySQL**
- **Vite** (build/dev server) · **PostCSS** · **TypeScript** (syntax only in the build — Vite strips types; `npm run typecheck` checks them separately, see below)
- Key Craft plugins: `craftcms/ckeditor` ^5.6.1, `craftpulse/craft-colour-swatches` 5.1.0, `mmikkel/retcon` 3.2.3, `nystudio107/craft-vite` 5.0.1, `ryssbowh/craft-prefetch` ^3.0.0, `spicyweb/craft-embedded-assets` 5.4.8, `vaersaagod/dospaces` 3.2.1, `verbb/buttonbox` 5.0.2
- `ramzcreative/craft-modules` ^1.0 — shared modules, see [Custom modules](#custom-modules-craft-modules) below
- `dompdf/dompdf` ^3.1 — **site-specific**, not in `stables`. Powers the Activity Sheet PDF downloads (`modules/activitysheets`). Renders HTML/CSS to PDF; its inline `<svg>` support is unreliable (confirmed via testing) — anything drawn for a PDF here uses HTML tables with CSS borders instead, never raw SVG.
- No pinned Node version (no `.nvmrc`, no `engines` field)

## Directory Structure
| Path | What goes here |
|---|---|
| `config/` | Craft + plugin config, one file per concern (`seo.php`, `iconpicker.php`, `colour-swatches.php`, `general.php`, ...) |
| `modules/stablestwigextensions/` | Inherited from `stables` — boilerplate-wide Twig filters |
| `modules/activitysheets/` | **Site-specific.** Generates the downloadable word-search/maze PDFs for the Activity Sheet page-builder block — word search and maze are both built entirely from logic (`services/WordSearchGenerator`, `services/MazeGenerator`), no artwork involved. Coloring pages aren't implemented — they'd need real Romeo & Buddy line-art from the illustrator, which doesn't exist yet. |
| `themes/_base/` | Shared templates + CSS/JS for this site's own themes. **Diverged from `stables`' `_base`** — started as a copy, has since picked up this site's own content (Books/blog listing templates, the Activity Sheet block, styling tweaks) independently. Not kept in sync with `stables`. |
| `themes/<handle>/` | A real theme (`default`, `coastal`) — thin override files only; mechanics in [`themes/CLAUDE.md`](themes/CLAUDE.md) |
| `scripts/` | Build-time Node scripts (favicons, logos) + `lock-shared-modules.sh` |
| `migrations/` | Content migrations — the only sanctioned way this site's fields/sections/entry types get added or changed. Real ones exist here: the Books section, starter header nav nodes (working around a bug in the nav plugin), the Activity Sheet block. |
| `web/` | Public webroot — `index.php`, generated `dist/`, static `assets/`/`fonts/` |
| `storage/` | Craft runtime storage (logs, backups, cache) — never hand-edited, never committed content |

**Where new code goes:**
- Front-end (templates/CSS/JS) → `themes/_base`, unless it's genuinely one theme's own look — see `themes/CLAUDE.md`.
- Something specific to Romeo & Buddy's own content (another activity type, another book-related feature) → this site's own `modules/`, same pattern as `activitysheets`.
- A capability generic enough that *other* RAMZ client sites would also want it → the separate `craft-modules` repo, not here — see `stables/CLAUDE.md` for that decision rule in more depth.

## Key Commands
- `npm install` — install JS deps
- `npm run dev:default` / `npm run dev:coastal` — Vite dev server, pinned to one theme's `src/` for the process lifetime
- `npm run build` (= `build:themes`) — builds every theme + optimized icons + favicons + logos; run before any deploy
- `composer lock-shared-modules` — regenerate `composer.lock` pointing at `craft-modules`' latest git tag (not the local symlink), for a deployable build
- `php craft migrate/up` / `php craft migrate/down` — apply/revert content migrations

**No automated test suite or linter exists in this repo** — no PHPUnit, no ESLint/Stylelint. Verification is otherwise manual/visual (curl the live route, check the CP, download and inspect a generated PDF, etc.) — see the migration commit history for the pattern (create a throwaway test entry via a migration, hit the real endpoint, verify, roll it back).

`npm run typecheck` (`tsc --noEmit`, config in `tsconfig.json`) is the one automated check, ported from stables 2026-08-25. It is **advisory and deliberately not wired into `npm run build`** — Vite strips types with esbuild and never type-checks, so a type error cannot break a build or a deploy. It covers only `themes/_base/src/js/**/*.ts`.

**It is clean — keep it that way.** It started at 27 errors and was taken to zero the same day, so any error is a regression you introduced. Two things worth knowing: a `.ts` under `Components/` with no top-level `import`/`export` is treated by TS as a *global script*, which silently breaks `declare global` and collides top-level function names with other such files — add `export {};` (see `contactForm.ts`/`cookieConsent.ts`/`videoPlayer.ts`). And **careful editing `tsconfig.json`** — Vite's esbuild auto-reads it, so `target` alone would flip `useDefineForClassFields` on and change class-field emit in the *shipped* JS; it is pinned false, and the built bundles were verified byte-identical before and after adding the file.

## Core Coding Conventions
- **CSS**: BEM (`Block__Element--Modifier`), one file per page-builder block under `themes/_base/src/css/blocks/`, imported once in `main.pcss`. Shared utility classes live in `includes/helpers.pcss` — promote something there only once it's genuinely reused across unrelated blocks.
- **JS**: vanilla, no framework. Reusable interactive UI is a native Web Component (`class X extends HTMLElement`), not a component-library element. Configure a component via CSS custom properties with sensible defaults (`var(--thing-x, default)`), not constructor options — lets one usage (e.g. the full-height nav drawer's close button) override just what it needs without touching every other instance of the component.
- **TypeScript**: nothing in the *build* validates annotations — never reason as if a type error would break a build. `npm run typecheck` does read them, so an annotation is worth getting right rather than approximating (`String` is the wrapper object; you almost always want `string`). `Options.ease` is Motion's own `Easing`, so the CSS `cubic-bezier(...)` string this repo shipped for months is now a compile error.
- **Content model changes** (fields/sections/entry types): a migration using Craft's own service layer (`Craft::$app->getFields()->saveField()`, `getEntries()->saveEntryType()`, etc.), never by hand-editing `config/project/project.yaml`.
- **New pageBuilder block types**: `pageBuilder`/`postBuilder` are CKEditor fields with nested entries, not Matrix — add a new block type via `craft\ckeditor\Field::setEntryTypes()`, and render it by iterating the field directly (`{% for chunk in blocks %}` checking `chunk.type == 'markup'` vs `chunk.entry`), not `.all()` — see `_blocks/_builder.twig` for the real, working pattern.
- **PDF generation**: HTML/CSS via Dompdf, never inline `<svg>` — see Tech Stack above.

## Things to Avoid
- **Don't hand-edit `config/project/project.yaml`.** UUID-referential, easy to corrupt. Use a migration + Craft's field/entry/section services instead.
- **Don't call `FieldLayoutTab::setElements()` before the tab is attached to its `FieldLayout` via `setTabs()`.** Throws "Field layout tab is missing its field layout." Build the tabs, call `setTabs()`, *then* `setElements()`.
- **Don't use inline `<svg>` in anything rendered through Dompdf.** Confirmed unreliable — a minimal 2-element test SVG produced the same empty output as a 200-element one. Use HTML tables/CSS borders instead.
- **Don't assume `craft-modules` edits here are already live in `stables` or vice versa** — `_base` diverged a while ago; nothing auto-syncs between the two repos' theme code, only the shared `craft-modules` package does.
- **Don't forget to restart the matching `npm run dev:<theme>`** after switching the active theme in the CP during local dev.

## Custom modules (`craft-modules`)
Three shared Craft modules — `seo`, `themepicker`, `iconpicker` — live in the separate [`craft-modules`](../craft-modules) repo, required via `ramzcreative/craft-modules: ^1.0` (path-repo symlink for instant local dev, a real git tag on staging/production). See [`../craft-modules/CLAUDE.md`](../craft-modules/CLAUDE.md) for what's in it. The SEO module is doing real work here beyond the `stables` default: this site's `books` section (with an `isbn` field) activates the shared module's dormant `Book` structured-data support — see `modules/seo/services/StructuredDataBuilder.php`'s `buildBook()`. Its dormant `Review`/`AggregateRating` support is *not* active here yet — that needs a `testimonial` pageBuilder block type, which doesn't exist on this site.

**Non-production environments are intentionally non-indexable.** `CRAFT_DISALLOW_ROBOTS` is `true` in both `.env.example.dev` and `.env.example.staging` (only `false` in `.env.example.production`) — `config/general.php` reads it into Craft's `disallowRobots` setting. The `seo` module's `SeoResolver::isIndexable()` is the single place that flag is checked, and every SEO-facing route/tag gates on it: `robots.txt` still renders but with `Disallow: /`, while `sitemap.xml`, `llms.txt`, and `blog/feed.xml` 404 outright rather than serve empty-but-valid content. So on `dev`/`staging`, those routes *looking* broken (404s, a blocking robots.txt) is the intended behavior, not a bug — don't "fix" it by touching this flag. Only `PRIMARY_SITE_URL` pointing at the real production environment with `CRAFT_DISALLOW_ROBOTS=false` should ever actually be crawlable.
