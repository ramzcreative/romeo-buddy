#!/usr/bin/env bash
# Regenerates composer.lock so ramzcreative/craft-modules resolves via its
# vcs (GitHub) source rather than the local path repo.
#
# composer.json declares both a `path` repository (../craft-modules,
# symlinked — for live local editing) and a `vcs` one (GitHub) for the same
# package. Composer prefers the path source whenever both can resolve,
# which is exactly what you want while actively developing the shared
# modules, but it means composer.lock ends up recording a source that only
# exists on machines with that exact sibling directory — not deployable.
#
# Run this once you're done editing and are ready to commit: it removes
# the path repository just long enough to force resolution through GitHub,
# then puts it back. composer.json is unchanged when this finishes — only
# composer.lock and vendor/ramzcreative/craft-modules (now a real download,
# not a symlink) change. Review that diff and commit composer.lock.
#
# Run `composer update ramzcreative/craft-modules` again any time you want
# to switch back to live-symlinked local development.

set -euo pipefail
cd "$(dirname "$0")/.."

REMOVED=0
restore_path_repo() {
    if [ "$REMOVED" = "1" ]; then
        echo "==> Restoring the path repository in composer.json..."
        php -r '
            $f = "composer.json";
            $j = json_decode(file_get_contents($f), true);
            foreach ($j["repositories"] as $r) {
                if (($r["type"] ?? null) === "path" && ($r["url"] ?? null) === "../craft-modules") {
                    exit(0);
                }
            }
            $vcsIndex = null;
            foreach ($j["repositories"] as $i => $r) {
                if (($r["type"] ?? null) === "vcs" && str_contains($r["url"] ?? "", "craft-modules")) {
                    $vcsIndex = $i;
                    break;
                }
            }
            $pathEntry = ["type" => "path", "url" => "../craft-modules", "options" => ["symlink" => true]];
            if ($vcsIndex !== null) {
                array_splice($j["repositories"], $vcsIndex, 0, [$pathEntry]);
            } else {
                $j["repositories"][] = $pathEntry;
            }
            file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        '
    fi
}
trap restore_path_repo EXIT

echo "==> Temporarily removing the path repository..."
php -r '
    $f = "composer.json";
    $j = json_decode(file_get_contents($f), true);
    $before = count($j["repositories"]);
    $j["repositories"] = array_values(array_filter(
        $j["repositories"],
        fn($r) => !(($r["type"] ?? null) === "path" && ($r["url"] ?? null) === "../craft-modules")
    ));
    if (count($j["repositories"]) === $before) {
        fwrite(STDERR, "No ../craft-modules path repository found in composer.json -- nothing to do.\n");
        exit(1);
    }
    file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
'
REMOVED=1

echo "==> Resolving ramzcreative/craft-modules via its GitHub (vcs) source..."
# Deliberately no --with-all-dependencies: this site's pinned craftcms/cms
# version stays a hard ceiling. If a craft-modules release raises its own
# Craft requirement (see that repo's composer.json), Composer will refuse
# to offer it here rather than dragging this site's Craft version along to
# make room for it -- exactly what stops a stale site from pulling in a
# module update it isn't ready for.
composer update ramzcreative/craft-modules

echo ""
echo "Done. composer.lock now points at the GitHub-hosted source and"
echo "vendor/ramzcreative/craft-modules is a real downloaded copy (not a"
echo "symlink). composer.json is back to normal -- review the composer.lock"
echo "diff and commit it."
