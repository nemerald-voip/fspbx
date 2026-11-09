# Publishing FS PBX

Increase the stable `X.Y.Z` release version in `config/version.php` and push it to
`main`. The **Publish release** workflow tags that commit, creates a draft, builds
the core frontend assets, and uploads the archive and checksum. It downloads and
verifies the uploaded files before publishing the release and refreshing the
website's release list. No personal access token is required.

For written release notes, include `.github/release-notes/vX.Y.Z.md` in the same
commit. Otherwise GitHub generates the notes automatically.

A failed build or verification leaves the release as a draft. Retry the failed
workflow from the Actions tab. Alternatively, run **Publish release** manually
from `main`, supplying the draft's tag as `ref`.

Manual retries use the workflow tooling from `main` while building the original
tagged source. Release-tooling fixes can therefore be applied without retagging.

Tags always identify the original commit. If the source needs a fix, increase the
version again; the workflow will not move an existing tag. Published assets are
never overwritten. Retrying an older release cannot replace a newer latest
release.
