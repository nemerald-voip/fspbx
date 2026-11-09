#!/usr/bin/env bash
set -euo pipefail

# CI only: append a commit containing Resources/dist to the module source branch.
# The caller's checkout and index are preserved. Stdout is the bundled commit ID.
source_commit=$(git rev-parse --verify "${1:?source commit required}^{commit}")
if [[ "$source_commit" != "$(git rev-parse HEAD)" ]]; then
    echo "Check out the requested module commit before bundling its frontend." >&2
    exit 1
fi
package=$(realpath "${2:?frontend package required}")
core=$(realpath "${3:?core checkout required}")
branch=${4:-main}
remote=${5:-origin}
release_tag=${6:-}
git check-ref-format "refs/heads/$branch"
if [[ "$remote" == -* ]]; then
    echo "Invalid module remote." >&2
    exit 1
fi
if [[ -n "$release_tag" ]]; then git check-ref-format "refs/tags/$release_tag"; fi
temporary=$(mktemp -d)
trap 'rm -rf "$temporary"' EXIT

# Validate against committed source, never against uncommitted developer edits.
mkdir "$temporary/source"
git archive "$source_commit" module.json Resources/assets | tar -x -C "$temporary/source"
php -r '
require $argv[1]."/app/Services/FrontendAssets.php";
$assets = new App\Services\FrontendAssets($argv[1]);
$manifest = json_decode(file_get_contents($argv[3]."/module.json"), true, 512, JSON_THROW_ON_ERROR);
$meta = $assets->validate($argv[2], "module", $manifest["name"], $manifest["version"]);
if ($meta["origin"] !== "release") throw new RuntimeException("Expected release assets");
$assets->verifyModuleSource($argv[3], $meta);
' "$core" "$package" "$temporary/source"

git fetch --no-tags --no-recurse-submodules "$remote" "refs/heads/$branch"
remote_commit=$(git rev-parse 'FETCH_HEAD^{commit}')

# Replace the complete generated tree so obsolete chunks are not carried forward.
export GIT_INDEX_FILE="$temporary/index"
git read-tree --empty
git -c core.autocrlf=false --work-tree="$package" add --all --force -- .
package_tree=$(git write-tree)
git read-tree "$source_commit"
git ls-files -z -- Resources/dist | git update-index --force-remove -z --stdin
git read-tree --prefix=Resources/dist/ "$package_tree"
tree=$(git write-tree)

# A rerun must retain the exact source and assets of an already published tag.
existing_tag=''
if [[ -n "$release_tag" ]]; then
    existing_tag=$(git ls-remote --refs "$remote" "refs/tags/$release_tag")
fi
if [[ -n "$existing_tag" ]]; then
    git fetch --no-tags --no-recurse-submodules "$remote" "refs/tags/$release_tag"
    tagged_commit=$(git rev-parse 'FETCH_HEAD^{commit}')
    if [[ "$(git rev-parse "$tagged_commit^{tree}")" != "$tree" ]] \
        || ! git merge-base --is-ancestor "$source_commit" "$tagged_commit"; then
        echo "Refusing to replace published release $release_tag. Increase the module version for changed source or assets." >&2
        exit 1
    fi
    echo "$tagged_commit"
    exit 0
fi
if ! git merge-base --is-ancestor "$source_commit" "$remote_commit" \
    || ! git diff --quiet "$source_commit" "$remote_commit" -- . ':(exclude)Resources/dist'; then
    echo "Module source changed while building. Run the workflow for the latest branch commit; no source was overwritten." >&2
    exit 1
fi
commit=$remote_commit
if [[ "$tree" != "$(git rev-parse "$remote_commit^{tree}")" ]]; then
    commit=$(git -c user.name='github-actions[bot]' \
        -c user.email='41898282+github-actions[bot]@users.noreply.github.com' \
        commit-tree "$tree" -p "$remote_commit" -m "Build module frontend for $source_commit [skip ci]")
fi
# No force push. Publish source and a new release tag together or change neither.
refs=("$commit:refs/heads/$branch")
if [[ -n "$release_tag" ]]; then refs+=("$commit:refs/tags/$release_tag"); fi
git -c push.followTags=false push --atomic "$remote" "${refs[@]}"
echo "$commit"
