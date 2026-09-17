#!/usr/bin/env bash
#
# Boilerplate updates: what has changed in `stables` since this site was made, and taking the parts worth taking.
#
#   scripts/boilerplate.sh status          what's new upstream, split into safe and needs-a-decision
#   scripts/boilerplate.sh update          take the safe parts only (shared front end, modules, docs, scripts)
#   scripts/boilerplate.sh update --all    merge everything, conflicts and all
#   scripts/boilerplate.sh renamed         migrations this site has under a different timestamp
#   scripts/boilerplate.sh install-hook    block a hand-run `git merge upstream/main` while updates are off
#
# Gated by BOILERPLATE_UPDATES in .env:
#   true   this site takes boilerplate updates (staging, and while it's being built)
#   false  frozen — status still works, nothing writes. Set this at launch.
#
# The boilerplate is whichever remote is called `upstream`, or `stables` on a site that was wired up by hand.
# Sites created before 2026-09-16 were cloned without history, so their remote shares no commits with stables:
# `update` still works there (it copies trees, it doesn't merge), `update --all` doesn't.
#
# A site that owns one of the shared paths lists it in `.boilerplate-keep` at the repo root, one per line:
# `update` puts this site's own version back afterwards, so its fork survives every update.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# Paths that are shared code: the same on every site unless that site forked them deliberately.
SAFE_PATHS=(themes/_base modules scripts docs .claude)

# Paths a merge must never take by itself. Each is this site's own, and taking stables' version silently changes
# what the site is: its content model, its own theme, its built assets, its module version.
GUARDED_PATHS=(config/project themes/default web/dist composer.lock composer.json package.json package-lock.json migrations)

# Paths inside SAFE_PATHS that this site owns — read from .boilerplate-keep, one per line, # comments allowed.
# A site that has forked a shared file (its own icon build, its Base's generated CSS) lists it there.
KEEP_FILE=".boilerplate-keep"

# Content migrations travel too (docs/boilerplate-migrations-spec.md). Every migration in the boilerplate's
# migrations/ is meant to run on every site, so this is opt-OUT at both ends:
#   migrations/.boilerplate-hold   in the boilerplate — migrations that must never leave it
#   .boilerplate-skip              on the site — migrations this site declines, with a reason each
# `update` COPIES them and stops there. Running one is a deploy action, against a database someone backed up.
HOLD_FILE="migrations/.boilerplate-hold"
SKIP_FILE=".boilerplate-skip"

# How install-hook recognises a hook it wrote, so it never overwrites one the site already had.
HOOK_MARKER="boilerplate-update-gate"

command="${1:-status}"
shift || true
take_all=0

for arg in "$@"; do
    case "$arg" in
        --all) take_all=1 ;;
        *) echo "Unknown option: $arg" >&2; exit 2 ;;
    esac
done

keep_paths() {
    [ -f "$KEEP_FILE" ] || return 0
    sed 's/#.*//' "$KEEP_FILE" | sed 's/[[:space:]]*$//' | grep -v '^$' || true
}

# Puts this site's own version of every .boilerplate-keep path back after a checkout took the boilerplate's.
restore_kept() {
    local path
    local file

    while IFS= read -r path; do
        [ -n "$path" ] || continue

        if git rev-parse -q --verify "HEAD:$path" >/dev/null 2>&1; then
            # Anything the checkout ADDED under this path is the boilerplate's and doesn't belong here.
            while IFS= read -r file; do
                [ -n "$file" ] && git rm -qf --ignore-unmatch -- "$file" >/dev/null
            done < <(git diff --cached --name-only --diff-filter=A HEAD -- "$path")

            git checkout HEAD -- "$path"
        else
            # This site doesn't have the path at all — take none of it.
            while IFS= read -r file; do
                [ -n "$file" ] && git rm -qf --ignore-unmatch -- "$file" >/dev/null
            done < <(git diff --cached --name-only HEAD -- "$path")
        fi

        echo "  kept this site's $path"
    done < <(keep_paths)
}

# The given paths, minus any git knows nothing about. `git diff`/`git commit` fail outright on a pathspec that
# matches nothing in HEAD or the index — which happens to a shared path the site tracks none of (.claude here),
# once restore_kept has taken the boilerplate's copy of it back out again.
known_paths() {
    local path

    for path in "$@"; do
        if [ -n "$(git ls-files -- "$path")" ] || git rev-parse -q --verify "HEAD:$path" >/dev/null 2>&1; then
            echo "$path"
        fi
    done
}

# A migration's identity is what follows its timestamp: m260916_160000_addTopics.php and
# m260916_120000_addTopics.php are the same migration, written on two sites at two moments. Comparing whole
# filenames would offer a site work it has already done — 14 of romeo-buddy's are that case.
migration_key() {
    printf '%s\n' "${1##*/}" | sed -E 's/^m[0-9]{6}_[0-9]{6}_//'
}

# This site's migrations, by key.
local_migration_keys() {
    ls migrations 2>/dev/null | grep -E '^m[0-9]{6}_[0-9]{6}_.*\.php$' | sed -E 's/^m[0-9]{6}_[0-9]{6}_//' || true
}

# The file this site has for a key, if it has one.
local_migration_for() {
    local file

    for file in $(ls migrations 2>/dev/null | grep -E '^m[0-9]{6}_[0-9]{6}_.*\.php$' || true); do
        if [ "$(migration_key "$file")" = "$1" ]; then
            echo "$file"
            return 0
        fi
    done
}

# Basenames of every migration the boilerplate has, minus the ones it holds back.
shippable_migrations() {
    local held
    held="$(git show "$REMOTE/main:$HOLD_FILE" 2>/dev/null | sed 's/#.*//' | tr -d '[:blank:]' | grep -v '^$' || true)"

    git ls-tree --name-only "$REMOTE/main" migrations/ \
        | sed 's|^migrations/||' \
        | grep -E '^m[0-9]{6}_[0-9]{6}_.*\.php$' \
        | while IFS= read -r name; do
            if [ -n "$held" ] && printf '%s\n' "$held" | grep -qxF "$name"; then
                continue
            fi
            echo "$name"
        done
}

# Basenames this site has declined, from .boilerplate-skip.
skipped_migrations() {
    [ -f "$SKIP_FILE" ] || return 0
    sed 's/#.*//' "$SKIP_FILE" | tr -d '[:blank:]' | grep -v '^$' || true
}

# What `update` would copy: shippable, not already here (by key — an adapted or renamed copy counts as here),
# and not declined. A skip entry matches by key too, so it holds whatever the boilerplate later renames it to.
pending_migrations() {
    local skips
    local have
    local key
    skips="$(skipped_migrations | while IFS= read -r n; do [ -n "$n" ] && migration_key "$n"; done)"
    have="$(local_migration_keys)"

    while IFS= read -r name; do
        [ -n "$name" ] || continue
        key="$(migration_key "$name")"

        if [ -n "$have" ] && printf '%s\n' "$have" | grep -qxF "$key"; then
            continue
        fi

        if [ -n "$skips" ] && printf '%s\n' "$skips" | grep -qxF "$key"; then
            continue
        fi

        echo "$name"
    done < <(shippable_migrations)
}

# Migrations this site has under a different timestamp — the same work, done here at another moment.
renamed_migrations() {
    local key
    local mine

    while IFS= read -r name; do
        [ -n "$name" ] || continue
        [ -f "migrations/$name" ] && continue
        key="$(migration_key "$name")"
        mine="$(local_migration_for "$key")"
        [ -n "$mine" ] && echo "$name -> $mine"
    done < <(shippable_migrations)
}

updates_enabled() {
    local value
    value="$(grep -m1 '^BOILERPLATE_UPDATES=' .env 2>/dev/null | cut -d= -f2- | tr -d '"' | tr -d "'" | tr '[:upper:]' '[:lower:]')"

    [ "$value" = "true" ] || [ "$value" = "1" ]
}

# The remote that is the boilerplate: `upstream` on a launcher-made site, `stables` on one wired up by hand.
REMOTE=''

require_upstream() {
    local candidate

    for candidate in upstream stables; do
        if git remote get-url "$candidate" >/dev/null 2>&1; then
            REMOTE="$candidate"
            return 0
        fi
    done

    # Run inside the boilerplate itself rather than a site made from it.
    case "$(git remote get-url origin 2>/dev/null)" in
        */stables|*/stables.git)
            echo "This is stables itself — there's nothing upstream of it." >&2
            exit 1
            ;;
    esac

    echo "This site has no \`upstream\` (or \`stables\`) remote, so it can't see the boilerplate." >&2
    echo "Add one with \`git remote add upstream <url-or-path-to-stables>\`; a site cloned before 2026-09-16" >&2
    echo "shares no history with it, which is fine for \`update\` and rules out \`update --all\`." >&2
    exit 1
}

# True when this site and the boilerplate share history at all. Sites cloned before 2026-09-16 don't, so
# "N commits behind" is meaningless there and a merge would be a rewrite rather than an update.
shares_history() {
    git merge-base HEAD "$REMOTE/main" >/dev/null 2>&1
}

require_enabled() {
    if ! updates_enabled; then
        echo "Boilerplate updates are off for this site (BOILERPLATE_UPDATES in .env)." >&2
        echo "That is the normal state for a live site. Set it to true to take updates again." >&2
        exit 1
    fi
}

case "$command" in
    status)
        require_upstream
        git fetch -q "$REMOTE"

        if shares_history; then
            count="$(git rev-list --count "HEAD..$REMOTE/main")"

            if [ "$count" = "0" ]; then
                echo "Level with stables."
                exit 0
            fi

            echo "$count commit(s) behind stables. Updates are $(updates_enabled && echo ON || echo OFF) for this site."
        else
            if git diff --quiet --diff-filter=AM "HEAD..$REMOTE/main" -- "${SAFE_PATHS[@]}" "${GUARDED_PATHS[@]}"; then
                echo "Nothing differs from stables in the paths this tracks."
                exit 0
            fi

            echo "This site shares no history with stables (cloned before 2026-09-16), so what follows is a file"
            echo "comparison, not a commit count. Updates are $(updates_enabled && echo ON || echo OFF) for this site."
        fi

        # The keep paths are this site's, so they don't belong in the list of what an update would take.
        exclusions=()
        while IFS= read -r kept; do
            [ -n "$kept" ] && exclusions+=(":(exclude)$kept")
        done < <(keep_paths)

        echo
        # --diff-filter=AM is what `update` would actually copy: files stables added or changed. A file that
        # exists only here is left alone (a pathspec checkout never deletes), so it isn't part of the answer.
        echo "Shared code (what \`update\` takes):"
        git diff --stat --diff-filter=AM "HEAD..$REMOTE/main" -- "${SAFE_PATHS[@]}" "${exclusions[@]+"${exclusions[@]}"}" | tail -20 | sed 's/^/  /'

        if [ -f "$KEEP_FILE" ]; then
            echo
            echo "This site's own, inside those paths ($KEEP_FILE — \`update\` leaves them alone):"
            keep_paths | sed 's/^/  /'
        fi

        echo
        echo "This site's own (never taken automatically):"
        git diff --stat "HEAD..$REMOTE/main" -- "${GUARDED_PATHS[@]}" | tail -12 | sed 's/^/  /'
        echo
        pending="$(pending_migrations)"

        if [ -n "$pending" ]; then
            echo "Migrations waiting (\`update\` copies them; nothing runs until you run php craft up):"
            printf '%s\n' "$pending" | sed 's/^/  /'
        else
            echo "No migrations waiting."
        fi

        renamed="$(renamed_migrations)"

        if [ -n "$renamed" ]; then
            echo
            echo "$(printf '%s\n' "$renamed" | wc -l | tr -d ' ') already here under a different timestamp (same work, written on this site at another"
            echo "moment) — not offered again. \`scripts/boilerplate.sh renamed\` lists them."
        fi

        if [ -f "$SKIP_FILE" ] && [ -n "$(skipped_migrations)" ]; then
            echo
            echo "Declined by this site ($SKIP_FILE):"
            sed 's/#.*//' "$SKIP_FILE" | tr -d '[:blank:]' | grep -v '^$' | sed 's/^/  /'
        fi
        ;;

    update)
        require_upstream
        require_enabled
        git fetch -q "$REMOTE"

        if shares_history && [ "$(git rev-list --count "HEAD..$REMOTE/main")" = "0" ]; then
            echo "Level with stables; nothing to take."
            exit 0
        fi

        # Only the shared paths have to be clean: an update writes nothing outside them, and a local dev copy
        # normally carries a modified composer.lock (the path repo for craft-modules) that never gets committed.
        if [ -n "$(git status --porcelain -- "${SAFE_PATHS[@]}")" ]; then
            echo "There are uncommitted changes in the shared paths — commit or stash them first, so an update" >&2
            echo "is its own commit and nothing of yours is swept into it." >&2
            git status --short -- "${SAFE_PATHS[@]}" | sed 's/^/  /' >&2
            exit 1
        fi

        if [ "$take_all" -eq 1 ]; then
            if ! shares_history; then
                echo "This site shares no history with stables, so there is nothing to merge — a merge here would" >&2
                echo "replay the whole boilerplate over it. Take the shared code with a plain \`update\`, and port" >&2
                echo "config and migrations deliberately (stables docs/romeo-buddy-port-plan.md is the worked example)." >&2
                exit 1
            fi

            echo "==> Merging everything from stables"
            git merge --no-edit "$REMOTE/main"
            echo "Check \`git diff HEAD~1 -- config/project migrations\` before running php craft up."
            exit 0
        fi

        pending="$(pending_migrations)"
        commit_paths=("${SAFE_PATHS[@]}")

        # Copying a migration writes into migrations/, so that folder has to be clean too.
        if [ -n "$pending" ] && [ -n "$(git status --porcelain -- migrations)" ]; then
            echo "There are uncommitted changes in migrations/ — commit or stash them first." >&2
            git status --short -- migrations | sed 's/^/  /' >&2
            exit 1
        fi

        echo "==> Taking shared code only"
        git checkout "$REMOTE/main" -- "${SAFE_PATHS[@]}"
        restore_kept

        if [ -n "$pending" ]; then
            echo "==> Copying migrations (not running them)"

            while IFS= read -r name; do
                [ -n "$name" ] || continue
                git checkout "$REMOTE/main" -- "migrations/$name"
                commit_paths+=("migrations/$name")
                echo "  $name"
            done < <(printf '%s\n' "$pending")
        fi

        # Only the paths git actually knows, or diff/commit fail on the ones this site tracks none of.
        # (Built with a read loop rather than readarray: macOS ships bash 3.2, which hasn't got it.)
        known=()
        while IFS= read -r path; do
            [ -n "$path" ] && known+=("$path")
        done < <(known_paths "${commit_paths[@]}")
        commit_paths=("${known[@]+"${known[@]}"}")

        if [ ${#commit_paths[@]} -eq 0 ] || git diff --cached --quiet -- "${commit_paths[@]}"; then
            echo "Nothing changed in the shared paths, and no migrations were waiting."
            exit 0
        fi

        # Limited to the shared paths plus the migrations just copied, so anything else the working copy
        # happens to carry stays out of it.
        git commit -q -m "Take boilerplate updates from stables

$(git diff --cached --stat -- "${commit_paths[@]}" | tail -1)

scripts/boilerplate.sh update. Project config, this site's own theme and its built assets were
left alone; see \`scripts/boilerplate.sh status\` for what's still upstream." -- "${commit_paths[@]}"

        echo "Committed. Run npm run build if front-end code changed."

        if [ -n "$pending" ]; then
            echo
            echo "$(printf '%s\n' "$pending" | wc -l | tr -d ' ') migration(s) were COPIED, not run. Read them, try them"
            echo "against a scratch database (scripts/scratch-db.sh), and they apply on the next \`php craft up\`."
            echo "One this site shouldn't run belongs in $SKIP_FILE, with the reason — then delete the file again."
        fi
        ;;

    renamed)
        require_upstream
        git fetch -q "$REMOTE"
        renamed="$(renamed_migrations)"

        if [ -z "$renamed" ]; then
            echo "Nothing: every migration this site shares with the boilerplate has the same filename."
            exit 0
        fi

        echo "The boilerplate's name, then this site's, for migrations that are the same work:"
        printf '%s\n' "$renamed" | sed 's/^/  /'
        ;;

    install-hook)
        require_upstream
        mkdir -p .git/hooks

        # Two hooks, because git splits the merge between them: a clean merge runs pre-merge-commit, while one that
        # stopped for conflicts is finished by `git commit` and runs pre-commit instead.
        for hook in pre-merge-commit pre-commit; do
            path=".git/hooks/$hook"

            if [ -e "$path" ] && ! grep -q "$HOOK_MARKER" "$path"; then
                echo "Left $path alone — it's this site's own hook. Add the check by hand if you want it there." >&2
                continue
            fi

            cat > "$path" <<HOOK
#!/usr/bin/env bash
# $HOOK_MARKER — refuses a merge from ${REMOTE} (the boilerplate) while BOILERPLATE_UPDATES is off.
# Installed by scripts/boilerplate.sh install-hook. Bypass for one merge with --no-verify.
set -uo pipefail

root="\$(git rev-parse --show-toplevel)"
value="\$(grep -m1 '^BOILERPLATE_UPDATES=' "\$root/.env" 2>/dev/null | cut -d= -f2- | tr -d '"' | tr -d "'" | tr '[:upper:]' '[:lower:]')"

if [ "\$value" = "true" ] || [ "\$value" = "1" ]; then
    exit 0
fi

if ! git remote get-url ${REMOTE} >/dev/null 2>&1; then
    exit 0
fi

# What's being merged in. MERGE_HEAD exists only when the merge stopped for conflicts; on the clean path git has
# already resolved the merge and the only thing naming the other side is GIT_REFLOG_ACTION ("merge ${REMOTE}/main").
incoming="\$(git rev-parse -q --verify MERGE_HEAD)" || incoming=''

if [ -z "\$incoming" ]; then
    case "\${GIT_REFLOG_ACTION:-}" in
        pull*) ref='FETCH_HEAD' ;;
        'merge '*) ref="\${GIT_REFLOG_ACTION#merge }" ;;
        *) ref='' ;;
    esac

    if [ -n "\$ref" ]; then
        incoming="\$(git rev-parse -q --verify "\${ref}^{commit}")" || incoming=''
    fi
fi

if [ -z "\$incoming" ]; then
    exit 0
fi

# Only refuse what actually came from the boilerplate — a merge between this site's own branches is its own business.
if git merge-base --is-ancestor "\$incoming" ${REMOTE}/main 2>/dev/null; then
    echo 'Boilerplate updates are off for this site (BOILERPLATE_UPDATES in .env); merge refused.' >&2
    echo 'Set it to true if this site should take stables changes again, or use scripts/boilerplate.sh status to see' >&2
    echo "what is waiting. A one-off merge anyway: git merge --no-verify ${REMOTE}/main." >&2
    exit 1
fi
HOOK
            chmod +x "$path"
        done

        # A fast-forward creates no merge commit, so neither hook would run on the common case. Forcing merges into
        # main to make a commit is what gives the hook something to refuse.
        git config branch.main.mergeoptions --no-ff
        echo "Installed the boilerplate check into .git/hooks, and set merges into main to --no-ff so it always runs."
        echo "A merge from upstream is now refused while updates are off."
        ;;

    *)
        echo "Usage: scripts/boilerplate.sh status | update [--all] | renamed | install-hook" >&2
        exit 2
        ;;
esac
