#!/usr/bin/env bash
#
# Create/drop a throwaway copy of the dev database, and run a command with
# Craft actually pointed at it — for safely testing a write-heavy Craft
# action (e.g. content-import's bulk-create-page-content) repeatedly
# without touching real dev content. A bad run gets dropped wholesale
# instead of needing manual per-entry cleanup. See
# craft-modules/docs/content-import-spec.md §8, which flagged this as a
# real, previously-unsolved gap.
#
# Usage:
#   scripts/scratch-db.sh create              # dumps the real DB, (re)creates <db>_scratch from it
#   scripts/scratch-db.sh drop                 # drops <db>_scratch
#   scripts/scratch-db.sh run -- <command...>  # temporarily points .env's CRAFT_DB_DATABASE at
#                                               # <db>_scratch, runs <command>, restores .env after
#                                               # -- even if <command> fails or is interrupted
#
# `run` exists because of a real gotcha, verified directly rather than
# assumed: overriding CRAFT_DB_DATABASE via putenv() before requiring
# bootstrap.php, or even a real `export`'d shell environment variable
# BEFORE invoking php, does NOT work -- Craft's own .env loading takes
# precedence over an already-set process environment variable and
# overwrites it back to whatever the file says. The only override that
# actually redirects Craft's DB connection is the literal .env file's own
# contents at the moment bootstrap.php runs, which is exactly what `run`
# does, temporarily and safely.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
ENV_BACKUP="$ROOT_DIR/.env.scratch-backup"

if [ ! -f "$ENV_FILE" ]; then
    echo "No .env file found at $ENV_FILE" >&2
    exit 1
fi

# Minimal .env reader for just the CRAFT_DB_* keys this needs — not a full
# parser, since this project's own .env files never quote these values in
# a way that would need more than stripping a leading/trailing double quote.
read_env() {
    grep "^$1=" "$ENV_FILE" | head -1 | cut -d '=' -f2- | sed 's/^"//;s/"$//'
}

DB_SERVER=$(read_env CRAFT_DB_SERVER)
DB_PORT=$(read_env CRAFT_DB_PORT)
DB_DATABASE=$(read_env CRAFT_DB_DATABASE)
DB_USER=$(read_env CRAFT_DB_USER)
DB_PASSWORD=$(read_env CRAFT_DB_PASSWORD)

if [ -z "$DB_DATABASE" ]; then
    echo "Couldn't read CRAFT_DB_DATABASE from $ENV_FILE" >&2
    exit 1
fi

SCRATCH_DB="${DB_DATABASE}_scratch"
MYSQL_ARGS=(-h "$DB_SERVER" -P "$DB_PORT" -u "$DB_USER")

# MYSQL_PWD rather than -p"$DB_PASSWORD" -- avoids both the client's own
# "insecure" warning and leaking the password into `ps` output.
if [ -n "$DB_PASSWORD" ]; then
    export MYSQL_PWD="$DB_PASSWORD"
fi

case "${1:-}" in
    create)
        echo "==> Dumping $DB_DATABASE..."
        DUMP_FILE="$(mktemp -t scratch-db-dump).sql"
        mysqldump "${MYSQL_ARGS[@]}" "$DB_DATABASE" > "$DUMP_FILE"

        echo "==> Creating $SCRATCH_DB (dropping first if it already exists)..."
        mysql "${MYSQL_ARGS[@]}" -e "DROP DATABASE IF EXISTS \`$SCRATCH_DB\`; CREATE DATABASE \`$SCRATCH_DB\`;"

        echo "==> Importing dump into $SCRATCH_DB..."
        mysql "${MYSQL_ARGS[@]}" "$SCRATCH_DB" < "$DUMP_FILE"

        rm -f "$DUMP_FILE"
        echo "==> Done. Scratch database: $SCRATCH_DB"
        ;;
    drop)
        echo "==> Dropping $SCRATCH_DB..."
        mysql "${MYSQL_ARGS[@]}" -e "DROP DATABASE IF EXISTS \`$SCRATCH_DB\`;"
        echo "==> Done."
        ;;
    run)
        shift
        if [ "${1:-}" = "--" ]; then
            shift
        fi
        if [ "$#" -eq 0 ]; then
            echo "Usage: $0 run -- <command...>" >&2
            exit 1
        fi

        if [ -f "$ENV_BACKUP" ]; then
            echo "A previous .env.scratch-backup already exists — a prior 'run' may not have restored cleanly. Refusing to proceed until that's resolved by hand." >&2
            exit 1
        fi

        cp "$ENV_FILE" "$ENV_BACKUP"

        # Runs on ANY exit from this point on — normal completion, the
        # command failing, or the script being interrupted — so .env is
        # never left pointed at the scratch database.
        restore_env() {
            mv "$ENV_BACKUP" "$ENV_FILE"
            echo "==> .env restored."
        }
        trap restore_env EXIT

        sed -i '' "s/^CRAFT_DB_DATABASE=.*/CRAFT_DB_DATABASE=\"$SCRATCH_DB\"/" "$ENV_FILE"
        echo "==> .env temporarily pointed at $SCRATCH_DB. Running: $*"
        "$@"
        ;;
    *)
        echo "Usage: $0 {create|drop|run -- <command...>}" >&2
        exit 1
        ;;
esac
