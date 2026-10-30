#!/usr/bin/env bash
set -euo pipefail

# Publish compiled assets through Git so SSH deploy keys work without an API token.
# Run from the module checkout; only this immutable asset ref is pushed.
source_commit=$(git rev-parse --verify "${1:?source commit required}^{commit}")
if [[ "$source_commit" != "$(git rev-parse HEAD)" ]]; then
    echo "Check out the requested module commit before publishing its frontend." >&2
    exit 1
fi
package=$(realpath "${2:?frontend package required}")
core=$(realpath "${3:?core checkout required}")
remote=${4:-origin}
runtime=$(php -r '
require $argv[1]."/app/Services/FrontendAssets.php";
$assets = new App\Services\FrontendAssets($argv[1]);
$manifest = json_decode(file_get_contents("module.json"), true, 512, JSON_THROW_ON_ERROR);
$meta = $assets->validate($argv[2], "module", $manifest["name"], $manifest["version"]);
if ($meta["origin"] !== "release") throw new RuntimeException("Expected release assets");
$assets->verifyModuleSource(getcwd(), $meta);
echo $meta["runtime"];
' "$core" "$package")
ref="refs/tags/frontend/$source_commit/$runtime"
temporary=$(mktemp -d)
trap 'rm -rf "$temporary"' EXIT

existing=$(git ls-remote --refs "$remote" "$ref")
if [[ -n "$existing" ]]; then
    git fetch --no-tags "$remote" "$ref"
    git show 'FETCH_HEAD:frontend.json' > "$temporary/frontend.json"
    if ! cmp -s "$package/frontend.json" "$temporary/frontend.json"; then
        echo "Refusing to replace an existing frontend package for this source and runtime." >&2
        exit 1
    fi
    echo "Matching frontend package is already published."
    exit 0
fi

# A separate index and parentless commit keep generated files out of source branches.
export GIT_INDEX_FILE="$temporary/index"
git read-tree --empty
git -c core.autocrlf=false --work-tree="$package" add --all --force -- .
tree=$(git write-tree)
commit=$(git -c user.name='FS PBX frontend build' -c user.email='frontend@fspbx.invalid' \
    commit-tree "$tree" -m "Frontend for $source_commit ($runtime)")
git push "$remote" "$commit:$ref"
echo "Published frontend package for $source_commit."
