# CP stylesheets

Per-block field visibility in the page builder is **generated**, not a file here
— see `../../services/BlockFieldCss.php` and `config/stables/blockfields.php`.

It has to be generated because the slideout only identifies its block by numeric
entry type id, and those are per-database. A hand-written `[data-value="28"]`
would mean a different block on every other site.

Two selectors are emitted per rule, because where the parent's type can be found
depends on the Matrix field's view mode:

- **blocks view** — the parent renders inline, so a descendant selector works.
- **cards view** — the parent opens in a slideout and its own `.matrixblock` is
  absent from that DOM entirely. The type lives in the sidebar's entry type
  select, a sibling of the fields, so `:has()` is what reaches it.

`pageBuilder` is currently in cards view, so the second form is the one doing
the work today.

## Why the type dropdown is JavaScript, not CSS

`config/stables/blockfields.php`'s `switchGroups` restricts which entry types a block
can be switched to. That can't be done in CSS: Garnish appends an open
disclosure menu to `document.body`, so the menu isn't a descendant of the form
being edited and no selector can scope it to the current block's type.

`../js/blockSwitchGroups.js` filters it on open instead. It's the only
JavaScript in this mechanism, and it exists for that one reason.
