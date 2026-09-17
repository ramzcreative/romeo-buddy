# romeo-buddy — CLAUDE.md

## Project Overview
The Romeo & Buddy picture-book brand site — built from RAMZ Creative's `stables` Craft CMS boilerplate, and **brought back in step with it in September 2026** (the plan, decision by decision, is `stables/docs/romeo-buddy-port-plan.md`). What that port settled, and the thing to understand before changing anything here: `themes/_base` is the boilerplate's, effectively byte-identical to stables', and **everything Romeo & Buddy looks like lives in `themes/default`**. See [`../stables/CLAUDE.md`](../stables/CLAUDE.md) for the conventions this site inherits, and don't fix one of this site's quirks by editing `_base`.

This is **the only live production site** in the group, and its GitHub repo is **public** — no secrets, tokens or client data in a commit, ever.

## Tech Stack
- **Craft CMS** 5.11.1 · **PHP** 8.2 · **MySQL**
- **Vite** (build/dev server) · **PostCSS** · **TypeScript** (syntax only in the build — Vite strips types; `npm run typecheck` checks them separately, see below)
- Key Craft plugins: `craftcms/ckeditor` ^5.7.0, `craftcms/webhooks` ^3.3, `mmikkel/retcon` 3.2.3, `nystudio107/craft-vite` 5.0.2, `ryssbowh/craft-prefetch` ^3.0.0, `spicyweb/craft-embedded-assets` 5.4.9, `vaersaagod/dospaces` 3.2.1, `verbb/buttonbox` 5.0.2
- `ramzcreative/craft-modules` ^1.0 — shared modules, see [Custom modules](#custom-modules-craft-modules) below
- `dompdf/dompdf` ^3.1 — **site-specific**, not in `stables`. Powers the Activity Sheet PDF downloads (`modules/activitysheets`). Renders HTML/CSS to PDF; its inline `<svg>` support is unreliable (confirmed via testing) — anything drawn for a PDF here uses HTML tables with CSS borders instead, never raw SVG.
- **Node** 24 (`.nvmrc`); `package.json` `engines` requires >=22

## Performance, ADA and Security
- every element built, whether it be a module or custom js or a new plugin, etc, needs to be highly performant, be secure and following ADA best practices.
- we focus on building secure, performing, ADA compliant (an honest focus for ADA, trying our best to catch what we can) sites
- this one is live and public, so a regression here is a regression a real audience meets

## Directory Structure
| Path | What goes here |
|---|---|
| `config/` | Craft + plugin config. The shared modules' config is one file per concern under `config/stables/` (`seo.php`, `iconpicker.php`, `blockfields.php`, `items.php`, `related.php`, ...) — same layout as stables, so a boilerplate change copies straight across |
| `modules/stablestwigextensions/` | Inherited from `stables`, kept identical — Twig filters, inline editing, the block-field CSS/JS generators, related content, the `blocks`/`content` console commands |
| `modules/activitysheets/` | **Site-specific.** Generates the downloadable word-search/maze PDFs for the Activity Sheet page-builder block — word search and maze are both built entirely from logic (`services/WordSearchGenerator`, `services/MazeGenerator`), no artwork involved. Coloring pages aren't implemented — they'd need real Romeo & Buddy line-art from the illustrator, which doesn't exist yet |
| `themes/_base/` | The boilerplate's shared machine — templates + CSS/JS, **identical to `stables/themes/_base` except its generated CSS, its icons (this site adds an `all` set), `src/logo.svg` and `src/favicon.png`**. Keep it that way: a change that belongs to every site is made in `stables` and copied here |
| `themes/default/` | **This site's look.** Its palette and generated CSS, its own `templates/` forks (hero, entry heading with the waves and stamp, cards, image/text, sliders, header/footer/banner branding, the Books section, the Activity Sheet block), its `src/css/blocks/`, its typography and spacing files, and its `config/blockfields.json` |
| `themes/christmas/` | A **Theme variant** (`"type": "page"`) — colours only: a `theme.json` and one `colors-generated.pcss`. Never built by Vite, never the site's active theme |
| `scripts/` | Build-time Node scripts (favicons, logos, icons) + `lock-shared-modules.sh` + `scratch-db.sh` |
| `migrations/` | Content migrations — the only sanctioned way this site's fields/sections/entry types get added or changed. The site's own (the Books section, starter header nav nodes, the Activity Sheet block, the `iconPicker` → `itemIcon` rename, retiring `imageItems`) sit beside the ported boilerplate ones |
| `docs/` | The boilerplate's specs, copied unchanged so code comments resolve — see [`docs/README.md`](docs/README.md) |
| `web/` | Public webroot — `index.php`, generated `dist/`, static `assets/`/`fonts/` |
| `storage/` | Craft runtime storage (logs, backups, cache) — never hand-edited, never committed content |

**Where new code goes:**
- Something that is how *Romeo & Buddy* looks → `themes/default` (a template fork at the same relative path, its CSS a `blocks/*.pcss` named in that theme's entry). This is the default answer for front-end work on this site.
- Something every RAMZ site should have → `stables`' `themes/_base` first, then copy the same file here. Never here first.
- Site-specific PHP (another activity type, another book feature) → this site's own `modules/`, same pattern as `activitysheets`.
- A capability another client site would plausibly also want → the separate `craft-modules` repo.

## Key Commands
- `npm install` — install JS deps. Needs `MOTION_TOKEN` exported in your shell (not `.env` — npm doesn't read it) whenever it has to download Motion+; `.npmrc` references the variable, the token itself is never committed
- `npm run dev` — one Vite dev server for every theme; switching the active theme in the CP needs no restart
- `npm run build` — one Vite build of every site theme into `web/dist/site/`, plus optimized icons, favicons and logos; run before any deploy
- `npm run svg-build` / `favicon-build` / `logo-build` — individual pieces of `build`, rarely run alone
- `npm run format:check` / `format` — Prettier over hand-written JS/TS/CSS/JSON; machine-written files (generated CSS, `theme.json`, theme config, Twig, PHP, Markdown) are in `.prettierignore`
- `npm run lint:css` — Stylelint (`stylelint-config-recommended`) over theme CSS
- `composer lock-shared-modules` — regenerate `composer.lock` pointing at `craft-modules`' latest git tag (not the local symlink), for a deployable build
- `php craft migrate/up` / `php craft migrate/down` — apply/revert content migrations
- `php craft stablestwigextensions/blocks/offered [--theme=<handle>]` — every block with whether this theme offers it, its hidden layouts, and the fields hidden on it and on its items; exits 1 when a rule names something that no longer exists
- `php craft theme-picker/themes/list|activate|variant|schedule-variant` — the active site theme and the sitewide Theme variant
- `scripts/scratch-db.sh create|run|drop` — a throwaway copy of this site's database to try a migration against. Only the literal `.env` swap it performs redirects Craft's DB; a shell variable doesn't
- **`php craft off` / `php craft on`** — maintenance mode, wired into the Forge deploy script

**Deploys are manual.** Pushing to `main` deploys nothing: Gary clicks Deploy in Forge. So a commit here is not live, and "pushed" and "deployed" are two separate facts — never report one as the other.

**No automated test suite exists in this repo** — no PHPUnit, no ESLint. Prettier and Stylelint (added 2026-09-16) are advisory, not wired into `build`, and the repo has not had a bulk format pass — `format:check` failing on files you didn't touch is expected. Verification is otherwise manual/visual (curl the live route, check the CP, download and inspect a generated PDF, etc.) — see the migration commit history for the pattern (create a throwaway test entry via a migration, hit the real endpoint, verify, roll it back).

For a CSS or template change that should look the same, `stables/docs/theme-build-proof/page-checks.mjs` compares two running copies of a site in headless Chrome — every computed style, the settled layout, throttled CLS, first paint without `main.css` — and its `smoke` command checks one site (this site's production included) for failed requests, console errors and a working menu. It lives in stables and is pointed at this site; there's no copy here. It's a before/after check you run by hand, not a suite.

`npm run typecheck` (`tsc --noEmit`, config in `tsconfig.json`) is the one automated check, ported from stables 2026-08-25. It is **advisory and deliberately not wired into `npm run build`** — Vite strips types with esbuild and never type-checks, so a type error cannot break a build or a deploy. It covers only `themes/_base/src/js/**/*.ts`.

**It is clean — keep it that way.** It started at 27 errors and was taken to zero the same day, so any error is a regression you introduced. Two things worth knowing: a `.ts` under `Components/` with no top-level `import`/`export` is treated by TS as a *global script*, which silently breaks `declare global` and collides top-level function names with other such files — add `export {};` (see `contactForm.ts`/`cookieConsent.ts`/`videoPlayer.ts`). And **careful editing `tsconfig.json`** — Vite's esbuild auto-reads it, so `target` alone would flip `useDefineForClassFields` on and change class-field emit in the *shipped* JS; it is pinned false, and the built bundles were verified byte-identical before and after adding the file.

## Core Coding Conventions
- **CSS**: BEM (`Block__Element--Modifier`), one file per page-builder block. `default` is a **clean theme** — its entry imports Base's `main-core.pcss`/`critical-core.pcss` (the machine: tokens, utilities, `.btn`, containers, typography, no block names), then names every block file one by one: Base's by path, and **this site's own from `themes/default/src/css/blocks/`, under the plain class name in `layer(components)`**, in Base's own order. Nothing of Base's is in their way, so there is nothing to suffix around and `layer(overrides)` stays free for a genuine override. See [`docs/base-layer-spec.md`](docs/base-layer-spec.md).
- **JS**: vanilla, no framework. Reusable interactive UI is a native Web Component (`class X extends HTMLElement`), not a component-library element. Configure a component via CSS custom properties with sensible defaults (`var(--thing-x, default)`), not constructor options — lets one usage (e.g. the full-height nav drawer's close button) override just what it needs without touching every other instance of the component.
- **TypeScript**: nothing in the *build* validates annotations — never reason as if a type error would break a build. `npm run typecheck` does read them, so an annotation is worth getting right rather than approximating (`String` is the wrapper object; you almost always want `string`). `Options.ease` is Motion's own `Easing`, so the CSS `cubic-bezier(...)` string this repo shipped for months is now a compile error.
- **Content model changes** (fields/sections/entry types): a migration using Craft's own service layer (`Craft::$app->getFields()->saveField()`, `getEntries()->saveEntryType()`, etc.), never by hand-editing `config/project/project.yaml`.
- **New page-builder block types**: `pageBuilder`, `postBuilder`, `columnBuilder` and `containerBlocks` are all **Matrix fields** (they were CKEditor fields until the 2026 migration — `_builder.twig` still normalizes both shapes) — add a block type by saving an entry type and appending it to the field's `entryTypes`, in a migration. Render it by adding `_blocks/<handle>.twig`; `_builder.twig` dispatches on the entry type's handle.
- **Which fields a block shows** is data, not markup: `config/stables/blockfields.php` for the site, `themes/default/config/blockfields.json` for this theme's changes on top. Check with `blocks/offered` before assuming a field is there — see [`docs/theme-designer-blocks-spec.md`](docs/theme-designer-blocks-spec.md) and [`docs/theme-config-spec.md`](docs/theme-config-spec.md).
- **Every block renders nothing when empty.** No field on a block is required and any field can be hidden per theme, so a template that assumes a field exists breaks the moment someone turns it off.
- **PDF generation**: HTML/CSS via Dompdf, never inline `<svg>` — see Tech Stack above.

## Things to Avoid
- **Don't hand-edit `config/project/project.yaml`.** UUID-referential, easy to corrupt. Use a migration + Craft's field/entry/section services instead.
- **Don't call `FieldLayoutTab::setElements()` before the tab is attached to its `FieldLayout` via `setTabs()`.** Throws "Field layout tab is missing its field layout." Build the tabs, call `setTabs()`, *then* `setElements()`.
- **Don't edit `themes/_base` for something that is only true of this site.** That is what `themes/default` is for, and every `_base` edit here is a divergence someone has to reconcile later. `_base` is the boilerplate's; the site's look is the theme's.
- **Write a migration that survives either deploy order.** `php craft up` applies project config *before* content migrations, so a migration may find its own fields already there (adopt them) or not (create them), and a destructive step must read the `relations` table rather than trust an order. Take backups in an overridden `up()`, never inside `safeUp()`'s transaction — `mysqldump` waits on the locks that transaction holds and the deploy hangs.
- **Don't use inline `<svg>` in anything rendered through Dompdf.** Confirmed unreliable — a minimal 2-element test SVG produced the same empty output as a 200-element one. Use HTML tables/CSS borders instead.
- **Don't write a `{% cache %}` key without `themeCacheKey()`.** `{% cache globally using key themeCacheKey('footer-nav') %}` — a key without the theme lets one theme's cached markup reach another's pages.
- **Don't copy `project.yaml` (or any of it) between this site and stables.** The same field handle has a different UID in each, and content is keyed by field-layout element UID — 38 shared handles already differ. Migrations through Craft's services are the only way a change travels.

## Custom modules (`craft-modules`)
Shared Craft modules — `seo`, `themepicker`, `iconpicker`, `themedesigner` and the rest — live in the separate [`craft-modules`](../craft-modules) repo, required via `ramzcreative/craft-modules: ^1.0` (path-repo symlink for instant local dev, a real git tag on staging/production). See [`../craft-modules/CLAUDE.md`](../craft-modules/CLAUDE.md) for what's in it.

The SEO module is doing real work here beyond the `stables` default: this site's `books` section (with an `isbn` field) activates the shared module's dormant `Book` structured-data support — see `modules/seo/services/StructuredDataBuilder.php`'s `buildBook()`. Its `Review`/`AggregateRating` support is active too, as of craft-modules 1.117.0: `buildReviews()` reads the `testimonials` block this site has (`testimonialsCollection` entries — title, `quote`, `rating`), mirroring whichever testimonials the block actually renders. Place the block on a page and that page's `LocalBusiness` gets `review` nodes; the synthesized `aggregateRating` stays behind the SEO settings' `enableAggregateRating` opt-in (off by default, deliberately — see the module's CLAUDE.md). That holds on a **book** page too: as of 1.118.0 reviews attach to the LocalBusiness and never to the `Book`, because these testimonials are about the business rather than about the book — books carry the page builder (m260917_260000) precisely so they can show them.

**Non-production environments are intentionally non-indexable.** `CRAFT_DISALLOW_ROBOTS` is `true` in both `.env.example.dev` and `.env.example.staging` (only `false` in `.env.example.production`) — `config/general.php` reads it into Craft's `disallowRobots` setting. The `seo` module's `SeoResolver::isIndexable()` is the single place that flag is checked, and every SEO-facing route/tag gates on it: `robots.txt` still renders but with `Disallow: /`, while `sitemap.xml`, `llms.txt`, and `blog/feed.xml` 404 outright rather than serve empty-but-valid content. So on `dev`/`staging`, those routes *looking* broken (404s, a blocking robots.txt) is the intended behavior, not a bug — don't "fix" it by touching this flag. Only `PRIMARY_SITE_URL` pointing at the real production environment with `CRAFT_DISALLOW_ROBOTS=false` should ever actually be crawlable.

## Taking the next boilerplate update
`scripts/boilerplate.sh status` / `update` — the shared code (`themes/_base`, `modules`, `scripts`, `docs`) comes over with one command, reading the `stables` remote. This site shares no history with the boilerplate, which `update` doesn't care about (it copies trees, it doesn't merge) but which rules out `update --all`. Everything this site owns inside those paths is listed in `.boilerplate-keep` and put back afterwards; `config/project`, `migrations`, `themes/default`, `web/dist` and the lock files are never touched at all. Gated by `BOILERPLATE_UPDATES` in `.env` — true on a dev copy, false everywhere else.

**Content model changes are still a deliberate port**, because `project.yaml` can't be copied between sites: write the migration here. Four of stables' migrations must never be run on this site — the README's "Boilerplate updates on this site" says which and why. `stables/docs/romeo-buddy-port-plan.md` is the worked example — nine phases, each one verified pixel-for-pixel against production before the next started.
