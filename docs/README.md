# docs

These are the boilerplate's specs, copied here **unchanged** from `stables/docs/` (by `scripts/boilerplate.sh update`) so that the code comments
in `themes/_base`, `modules/stablestwigextensions`, `config/stables` and this site's migrations resolve to a
file that actually exists in this repo. They describe shared systems, not Romeo & Buddy, and they are kept
identical to stables' copies — edit them there and copy, never fork one here.

| File | What it covers |
|---|---|
| `base-layer-spec.md` | `_base` as a machine rather than a design: `main-core`/`critical-core`, the cascade layers, what a clean theme imports |
| `business-blocks-spec.md` | The block library: hero layouts, video modal, ticker, logo strip, testimonials, galleries, posts |
| `business-content-spec.md` | The content model behind those blocks: authors, related content, topics, articles/projects |
| `theme-designer-blocks-spec.md` | Per-theme block rules — what a theme offers, which fields show, generated CP CSS/JS |
| `theme-config-spec.md` | `themes/<handle>/config/*.json` — how a theme changes block fields and item fallbacks |
| `site-types-spec.md` | The `siteType` label and the theme-switch warning |
| `inline-editing-spec.md` | Front-end inline editing (admin bar tier 4) — the engine, and every permission check |
| `design-mode-spec.md` | Its sibling surface: editing *theme tokens* from the front end. Unbuilt; here because the inline-editing spec links to it |
| `boilerplate-migrations-spec.md` | How the boilerplate's content migrations reach this site, and the rules one must follow to be safe to run here |

**Site-specific docs live beside them without this rule** — anything about Romeo & Buddy itself (the Books
section, activity sheets) is this repo's own and has no stables counterpart.

Two things deliberately *not* copied:

- **`stables/docs/romeo-buddy-port-plan.md`** — the plan that brought this site up to date with the
  boilerplate in September 2026. It lives in stables (migrations here cite it as "stables
  docs/romeo-buddy-port-plan.md") and is the record of every decision behind those migrations.
- **`stables/docs/theme-build-proof/`** — the before/after verification harness (`page-checks.mjs`). It takes
  two running copies of a site and compares every computed style, and it can be pointed at this site from
  there; there's no reason for a second copy.

Specs for the shared modules (Theme variants, background roles, page themes, the design pipeline) live in
`craft-modules/docs/`, and the code comments that name them say so.
