# Shipping content migrations to client sites

Status: **DRAFT — nothing built.** Written 2026-09-17, straight after the romeo-buddy port, while what it cost is
still measurable. The mechanism question in §2 is **verified against Craft 5.11.1's own source**, not recalled.

## 1. The problem this exists to solve

`scripts/boilerplate.sh` made shared code a one-command update: `themes/_base`, `modules`, `scripts` and `docs`
copy over, each site's own forks are named in `.boilerplate-keep` and survive. That half scales.

The content model doesn't. A boilerplate change that adds a field, a block or a section needs a **migration on every
site**, and today that migration is written once per site by hand. The romeo-buddy port is the measurement: nine
phases, ~30 migrations, a day of work, for one site that was about a month behind. At one production site that is
affordable. At ten it is the whole week, every release.

The thing worth noticing is that the migration itself is portable. It's ordinary PHP going through Craft's services
by **handle** — `getFields()->saveField()`, `getEntries()->saveEntryType()` — and never touches a UID. What isn't
portable is `project.yaml` (the same handle has a different UID on every site, and content is keyed by field-layout
element UID: 38 shared handles already differ between stables and rb). So the file can travel; only the compiled
config can't. There is no mechanism that carries it — that's the gap.

## 2. What Craft actually supports

Checked in `vendor/craftcms/cms/src` at 5.11.1:

| Track | Who runs it | In `php craft up`? |
|---|---|---|
| `craft` | Craft's own | yes |
| `plugin:<handle>` | each installed plugin | yes |
| `content` | the site's `migrations/` | yes, last |
| anything else | only `migrate/up --track=<name>` | **no** |

- **`php craft up`** (`console/controllers/UpController.php`) runs exactly three things in this order:
  `migrate/all --noContent` (Craft + plugins), `project-config/apply`, then `migrate/up --track=content`.
- **`migrate/all`** (`MigrateController::actionAll()`) collects Craft's migrator, every **plugin's** migrator, and
  the content migrator. Modules are not in that list, at all.
- **A custom track is possible** — `MigrateController::getMigrator()` fires `EVENT_REGISTER_MIGRATOR` for any
  unrecognised track name, so a module can serve `module:ramz` — but nothing reaches it except an explicit
  `php craft migrate/up --track=module:ramz`. It is never part of `up`.
- **The content track is the site's own folder**: `config/app.php` configures `contentMigrator` with
  `migrationPath: '@contentMigrations'` and namespace `craft\contentmigrations`, and `bootstrap.php` sets that
  alias to `<root>/migrations` (overridable with `CRAFT_CONTENT_MIGRATIONS_PATH`).
- **Applied migrations are recorded per track**: `MigrationManager` writes `track` + `name`, and reads back
  `where(['track' => $this->track])`. The same file under two tracks is two independent records.

So the tempting idea — *put the migrations in `craft-modules` and a version bump carries them* — **does not work as
written.** `craft-modules` is a set of modules, not a plugin, and module tracks are invisible to `craft up`. It
would work only by either turning the package into a real Craft plugin (handle, install/uninstall, project-config
entry, edition/licence machinery — a large change to something deliberately built as modules), or by adding
`php craft migrate/up --track=module:ramz` to every site's deploy script.

## 3. The decision: ship the files into the site's own content track

`boilerplate.sh update` copies the boilerplate's shippable migrations into the site's `migrations/`. The site's
normal `php craft up` — the one its deploy already runs — picks them up as content migrations, in timestamp order,
after project config. No new Craft machinery, no deploy-script change, nothing to register.

Why this over the module track, given the module track is one copy in `vendor` rather than a copy per site:

- **A site has to be able to decline one, and that isn't an edge case.** Four of stables' migrations must never run
  on romeo-buddy: it has `footerForm` already, it added `itemEntrySource` directly instead of the
  `addItemVariant`/`removeItemVariant` pair, and it retired `imageItems` rather than consolidating them. With files
  in the site's own folder, declining is *not copying a file*. With a vendored track it needs a skip list that the
  migrator consults, which is more machinery to get the same answer.
- **The record stays legible.** `ls migrations/` on a site is the truth about what that site has run. That is how
  the rb port was audited, and it is what a person looks at first when something is wrong at 9pm.
- **Adapting is possible.** Two of stables' migrations were unsafe on rb *as written* and were rewritten additively
  in rb's copies. A vendored file can't be adapted per site; a copied one can, and the diff says so.
- **It composes with what exists.** `.boilerplate-keep` already means "this site owns its version of that file".

The cost, stated plainly: the same migration file exists on every site, and once applied it can't be fixed in place
(the `migrations` table records it by name) — a mistake ships as a follow-up migration. That is already true today.

## 4. What update does

Two files, both readable:

- **`migrations/.boilerplate-ship`** in stables — the basenames of the migrations that are meant to travel, newest
  last. Not every migration in stables belongs on a client site: some are stables' own demo content, some were
  superseded. The list is written by hand as part of writing the migration, and reviewed like any other change.
- **`.boilerplate-skip`** on the site — basenames this site declines, each with a `#` comment saying why. romeo-buddy
  seeds it with the four above. `update` never copies a listed migration, and never removes one already applied.

`update` then, for each shippable migration the site lacks and hasn't skipped, copies the file and **prints it**.
It does not run anything: applying a migration is a deploy action, taken deliberately, against a database someone
has backed up. `status` lists what is waiting the same way it already lists what stables has that the site doesn't.

A site made by the launcher starts as a clone, so it has every migration already; this only matters from its first
update onward.

## 5. What a shippable migration must do

These are the rules that make a file safe to run on a site nobody was looking at while writing it. Each one is here
because it went wrong once.

1. **Adopt if present, create if not.** Project config applies *before* content migrations in `craft up`, so a field
   the migration creates may already exist by the time it runs. Fetch by handle first; create only on null.
2. **Add, never replace.** `postsLayouts` overwrote a Dropdown's options and would have dropped rb's `books` layout;
   the ported version appends instead. Anything that sets a list — options, entry types on a field, tabs on a layout
   — reads what's there and adds to it.
3. **Handles only, never UIDs**, and never a hand-edited `project.yaml`. Craft's services regenerate the config
   correctly; nothing else does.
4. **Survive either deploy order.** A deploy may apply project config first (`craft up`) or run migrations first
   (`migrate/up`). A destructive step reads the `relations` table for real usage rather than trusting that an
   earlier step already moved the content.
5. **Idempotent, and say so.** Running it twice finds it done and echoes that. Every step echoes what it did — that
   output is the audit trail on a site with no test suite.
6. **Destructive work is its own release.** Additive migration ships and deploys first; the removal ships after,
   once the site is known good. Two deploys, deliberately.
7. **Back up in an overridden `up()`, not in `safeUp()`.** `mysqldump` inside the migration's transaction waits on
   locks that transaction holds, and the deploy hangs.
8. **Never assume a field is on a layout, or that a block is offered.** Per-theme block rules mean any field can be
   hidden and any block can be absent. Check, then act.

A migration that can't follow these isn't shippable: it goes on the list of things each site does for itself, with
a note, the way rb's four are.

## 6. The loop, per site, per release

1. `scripts/boilerplate.sh status` — what's waiting, code and migrations.
2. `scripts/boilerplate.sh update` — shared code in, new migrations copied in, nothing run.
3. Read the copied migrations. This is the judgement step, and it is meant to stay a human one.
4. `scripts/scratch-db.sh create && php craft up` against the scratch copy, both orders where it matters.
5. `npm run build`, commit, deploy, smoke check.

Steps 1–2 are minutes. Step 3 is the only part that scales with how much the boilerplate changed, which is the
argument for updating every release rather than once a year: the port cost is superlinear in drift.

## 7. Not in this spec

- **Verification as one command.** Today the before/after harness (`docs/theme-build-proof`) is assembled by hand
  per port. It should be `boilerplate.sh verify`, spinning the "before" worktree itself. Separate piece.
- **Feature opt-in.** Whether a site *wants* a block is already answered by theme block rules, not by withholding
  the fields; the default stays "every site gets the fields, the theme decides what shows".
- **Turning `craft-modules` into a plugin.** Written down in §2 so the option isn't re-litigated from memory. If the
  package ever needs install/uninstall semantics for its own reasons, migrations riding in `craft up` come free with
  it, and this spec's §3 decision should be revisited then.
