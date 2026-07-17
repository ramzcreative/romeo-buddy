# `_base/templates/` — orientation

Quick index of this folder — see [`../../CLAUDE.md`](../../CLAUDE.md) for the `_base`/theme-folder relationship (this is the shared template root every theme falls back to), and [`../src/CLAUDE.md`](../src/CLAUDE.md) for the CSS/JS this pairs with (in particular, how a page-builder block's markup here relates to its stylesheet in `src/css/blocks/`).

## Routing: how a URL lands on a template

`_router.twig` is the shared entry point most section templates resolve through — for an entry, it tries `_sections/<section>/_detail.twig`, then `_sections/<section>/<section>.twig`, then `_sections/default.twig`, in that order (`ignore missing`, first match wins). `_sections/default.twig` is the generic case: any entry whose section has no dedicated template just renders through `_layouts/index.twig`, which is entirely driven by that entry's `pageBuilder` field.

## What's here

| Path | What's in it |
|---|---|
| `_layouts/` | `scaffold.twig` (the real `<html>` doc — head tags, header/footer includes) and `index.twig` (the page-builder-driven layout most entries render through — first-block-becomes-header logic lives here). |
| `_blocks/` | The page builder itself — every block template a `pageBuilder`/`postBuilder`/`columnBuilder` field can render. **See [`_blocks/CLAUDE.md`](_blocks/CLAUDE.md)** for how blocks are dispatched, nested, and added. |
| `_sections/` | Per-section overrides — `blog/` and `books/` (the site's two custom content types beyond the generic `pageBuilder` flow: card partials, category/detail templates, nav for each). |
| `_partials/` | Site-wide chrome included from `scaffold.twig` — header, footer, cookie consent, social links, the CP "Edit Entry" link. |
| `_utilities/` | Twig macros imported (`{% import ... as x %}`), not included — `format.twig` (safe inline-HTML rendering, see its own content-safety note and [`_blocks/CLAUDE.md`](_blocks/CLAUDE.md)'s), `transforms.twig` (responsive image markup), `nav.twig`. |
| `_login/` | The custom front-end auth templates for password-protected pages (`loginPath`/`setPasswordRequestPath` in `config/general.php`) — thin wrappers around Craft's own `users/login` / `users/send-password-reset-email` actions, no reinvented auth logic. |
| `_errors/` | `404.twig`, `error.twig`, `offline.twig` — `errorTemplatePrefix('_errors/')` in `config/general.php` is what points Craft at this folder. |
| `dev/` | Local scratch/example templates, not part of the real site. |
| `index.twig`, `search.twig` | Top-level routes with no section behind them (homepage, `/search/`). |

## Content-safety convention

Any template outputting a value that isn't already known-safe (a native `title`, a `PlainText` field, anything user-adjacent) should go through `_utilities/format.twig`'s macros, not a bare `|raw`. See [`_blocks/CLAUDE.md`](_blocks/CLAUDE.md#a-content-safety-note) for the specifics and why `strip_tags()` alone isn't enough.
