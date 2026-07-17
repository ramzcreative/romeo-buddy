# `themes/` — how theming works here

Quick orientation for working inside this directory. See [`../CLAUDE.md`](../CLAUDE.md) for the module/dev-stack picture and [`../stables/themes/CLAUDE.md`](../../stables/themes/CLAUDE.md) for the boilerplate's own version of this doc — the underlying mechanism is identical (this site started as a copy of `stables`), but `_base` here has since diverged with this site's own real content, so treat this file as the accurate one for this repo.

## The shape

```
themes/
  _base/            shared templates + CSS/JS — the bulk of the site's front end lives here
  default/          a real theme: palette + thin override files + theme.json
  coastal/          another real theme, same shape
```

Every real theme (`default`, `coastal`) is a folder with a `theme.json` (`{"name": "...", "thumbnail": "thumbnail.png"}`) — `modules/themepicker` auto-discovers themes by scanning `themes/*/theme.json`. `_base` is not itself a theme (no `theme.json`, never directly activated) — it's the shared foundation both real themes build on.

Unlike `stables`, where `_base` is meant to stay generic for every future client site, **this site's `_base` is just this site's own shared code now** — it holds Romeo & Buddy-specific templates (the Books/blog listing pattern, the Activity Sheet block, the header/nav markup) that have no reason to exist in the generic boilerplate. Nothing here needs to stay portable to other client sites.

**Rule of thumb for where to make a change:**
- Affects `default` and `coastal` the same way → edit it in `_base`.
- Only one theme should look/behave differently → override it in that theme's own folder (see the two override mechanisms below).

## Templates: automatic fallback

`modules/themepicker` registers `_base/templates` as a site template root matching every template name, *after* setting the active theme's own `templates/` as Craft's primary path. Craft checks the primary path first and only falls through to `_base` if the template isn't found there.

Both `default/templates/` and `coastal/templates/` are currently empty (`.gitkeep` only) — every template on this site resolves from `_base/templates/` for both themes, including the Books/blog listing templates (`_sections/books/*`, `_sections/blog/*`) and every page-builder block. To override one template for one theme only, add just that file at the same relative path inside that theme's own `templates/` folder.

This fallback is **site-request only**. CP template roots don't participate.

See [`_base/templates/CLAUDE.md`](_base/templates/CLAUDE.md) for what's inside `_base/templates/` — routing, layouts, and (in [`_blocks/CLAUDE.md`](_base/templates/_blocks/CLAUDE.md)) how the page builder works. Both are this site's own versions, already adapted for the Books section and the Activity Sheet block.

## CSS/JS: no automatic fallback — thin delegating files instead

Same mechanism as `stables`: each theme's `src/` contains a handful of tiny files that explicitly import `_base`'s equivalents, rather than any automatic resolution.

| File | Job |
|---|---|
| `src/css/base/_colors.pcss` | This theme's own `$colors` map |
| `src/css/main.pcss`, `critical.pcss` | Two-line files: import this theme's own `_colors.pcss` first, then `_base`'s equivalent |
| `src/js/main.js` | One line, importing `_base/src/js/main.js` |
| `src/js/critical.js`, `maincss.js` | Import this theme's own compiled CSS entry via the `@src` alias |
| `src/logo.svg`, `src/favicon.png` (optional) | Only needed if a theme is enough of a redesign to warrant its own — otherwise falls back to `_base/src/logo.svg` / `favicon.png` at build time |

`_base/src/css/main.pcss` is where the real work happens, importing every `base/*.pcss`, `includes/*.pcss`, and `blocks/*.pcss` partial directly. No per-file "theme wins if present" resolution for anything beyond color — to change something else for one theme only, fork that specific `@import` line in the theme's own `main.pcss`/`critical.pcss`.

## The `@src` alias and the per-theme dev server

`vite.config.js` resolves `@src` to `CUSTOM_SRC_PATH` (`themes/${CUSTOM_THEME}/src/`), a **build-time env var**, not something read live from the CP's active-theme setting:

- Production/staging: switching the active theme in the CP takes effect immediately, serving the pre-built `web/dist/<theme>/` bundle. Run `npm run build:themes` after any theme change.
- **Local dev**: the Vite dev server is pinned to one theme's `src/` for its whole process lifetime (`npm run dev:default` vs. `npm run dev:coastal`). Switching the active theme in the CP without restarting the matching `dev:<handle>` script is the most common cause of "wrong colors locally."

## Adding a new theme (short version)

For a palette-only variant: `cp -r themes/coastal themes/<handle>`, then edit the new `src/css/base/_colors.pcss`. Full step-by-step is in [`../../stables/README.md` § Themes](../../stables/README.md#themes) — the workflow itself wasn't changed by this site's divergence, only `_base`'s actual content was.

## Conventions once you're editing `_base` (or any theme) CSS/JS

BEM naming, the PostCSS plugin stack, TypeScript's no-type-checking caveat, and the Web Components pattern used for interactive UI are all documented once in [`../CLAUDE.md` § Core Coding Conventions](../CLAUDE.md#core-coding-conventions) — that applies everywhere under `themes/`, not just `_base`.
