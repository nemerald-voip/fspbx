---
id: frontend-assets
title: Frontend assets and local builds
sidebar_position: 6
---

# Frontend assets and local builds

FS PBX installs compiled frontend files during application updates. Node and npm are needed when developing or deliberately rebuilding locally, but normal updates do not run them. There is no environment setting to switch between released and local assets.

## Application updates

`php artisan app:update` downloads the frontend package matching `config/version.php`, verifies its checksum and frontend source files before running update steps, and activates it after module refreshes complete. A missing or mismatched package stops the update before those steps. It never falls back to running npm.

Pull the core application and any Git-managed module source manually before `app:update`. Asset preflight does not undo a previous `git pull`. Use source from the matching published release; unbuilt frontend changes on `main` cannot use an older release's assets just because the version number is unchanged. Enabled Git-managed modules receive matching frontend assets and run their update hooks as described below.

For an unpublished development checkout, an already completed local build can be reused if its frontend source files still match exactly. Run `npm run build` yourself first. A compatible published package takes precedence over a local build, and the updater reports when it replaces local assets.

To retry asset installation without running the whole application update:

```bash
php artisan frontend:install
```

To install a release package transferred from another machine:

```bash
php artisan frontend:install --archive=/path/to/fspbx-frontend-VERSION.tar.gz
```

## Development and production troubleshooting

Continue using the usual commands from `/var/www/fspbx`:

```bash
npm ci           # when dependencies need installing
npm run dev      # development server and hot reload
npm run build    # compile and activate local core and enabled module assets
```

A local build stages all its output and activates it only after every enabled module build succeeds. A failure leaves the active frontend intact. Node is still resource intensive: on a busy production server, prefer building the matching source on a separate machine, or use appropriate host resource limits for the manual build.

You can edit Vue files and rebuild while Laravel remains in production mode. No mode flag needs resetting afterward. The next update uses released assets when they match the checked-out source. Local source patches still need ordinary Git/patch management: the updater will not discard them to make a release package fit.

While `npm run dev` is running, Laravel continues to use its hot file and development server. Stop it before checking compiled assets. The updater reports an existing hot file and does not terminate a development server.

Restore the previous compiled frontend, without compiling again:

```bash
php artisan frontend:rollback
```

Rollback requires the same application and module versions. It changes frontend files only; it does not revert PHP, Vue source, migrations, or module lifecycle hooks.

## Licensed modules

The core package contains no optional module pages. Modules with a frontend ship their compiled assets in `Resources/dist` inside the existing licensed module archive. The normal license and entitlement checks still control downloads.

Core and module packages share a frontend API version and dependency fingerprint. Compatible installed module assets survive a skipped module update. Missing or incompatible module assets are reported; they are not loaded into an incompatible runtime. Publish compatible module packages before rolling out a change to the shared runtime. License/API failures remain optional-module failures and do not invoke a local build or abort the core update.

Module packages include their own CSS. They use the core Vue, Inertia, VueForm, and translation instances, so installing a module does not require rebuilding the core. Site-specific public settings such as the Reverb key are supplied by Laravel at page load. Private keys are never supplied to the browser.

## Modules installed through Git

Pull module source yourself, then run `app:update` for a full application update or `modules:refresh` to refresh modules only. For example, from `/var/www/fspbx`:

```bash
git -C Modules/ContactCenter pull --ff-only
php artisan modules:refresh
```

Wait for the module's GitHub Actions build to finish, then repeat the manual pull for each Git-managed module you want to update. The pull brings down both source and the compiled files in `Resources/dist`. The refresh validates and activates those local files against the installed module version, frontend source, and prepared or active core runtime. It never fetches, pulls, or merges module code or assets. A compatible existing package, including a local build, can be reused when its version and frontend sources still match exactly. Modules without frontend pages run their update hooks without an asset installation.

Only your manual pull requires repository access. The module refresh needs no Git credentials, editor connection, or license download for a Git checkout. Detached release tags and linked worktrees work too. Each node installs its own assets.

Older checkouts may not include `Resources/dist`. A matching active local bundle can still be reused; otherwise, pull a module version built by the updated workflow and retry `php artisan modules:refresh`. The updater no longer downloads the old `frontend/...` tags. Existing published tags are not moved or deleted.

Local edits and untracked files are preserved. Backend-only edits do not prevent a matching frontend from being installed. Changed frontend files or module metadata require a matching local build; a published package that does not match the checkout is rejected. An in-progress Git operation, missing bundle, or incompatible package produces a module warning and leaves existing assets intact. The core update continues, so check module warnings before considering the deployment complete. Successfully checked assets are followed by module update hooks; retry a failed hook with `php artisan modules:refresh`. The updater does not stash, reset, merge local commits, or start npm.

For development or a production patch, edit and run `npm run build` as before. Local builds activate files in application storage and do not overwrite the tracked `Resources/dist`. Wait for the module workflow before pulling onto production: the initial source push precedes its generated asset commit. A missing package cannot undo your manual pull; wait for the successful workflow, pull again and retry the refresh, or deliberately build the checkout locally.

## Preparing releases

The core repository's **Build frontend release assets** workflow builds a published release tag with `npm ci` and attaches:

- `fspbx-frontend-VERSION.tar.gz`
- `fspbx-frontend-VERSION.tar.gz.sha256`

For release preparation, create the tag and a draft GitHub release, dispatch the workflow for that tag, and verify the attachments before publishing. The published-release event also builds missing assets; wait for the attachments before directing administrators to upgrade. Existing complete attachments are retained. Fresh installations check for the package and use the matching tag's source.

Contact Center's release workflow builds its frontend and appends a commit containing `Resources/dist` to `main`. New version tags point to that bundled commit, and the Keygen archive is created from the same commit. It always checks out build tools from `nemerald-voip/fspbx` on `main`; no repository variable is needed. Publish the core build tooling before running the updated module workflow. For the first release using this workflow, bump the module version so existing published tags remain unchanged.

The shared `.github/scripts/bundle-module-frontend.sh` validates assets against committed module source, replaces the generated directory in a separate Git index, and pushes the bundled commit without changing the runner's checkout or index. A new release tag is pushed atomically with the branch. The workflow's existing `contents: write` permission authorizes the push; repository branch rules must permit that workflow to update `main`. It uses the normal `GITHUB_TOKEN`, whose pushes do not start another workflow; generated-only changes are also excluded and the commit carries `[skip ci]`.

If source changes while a build runs, the publication fails without overwriting it; let the latest commit's workflow finish. Identical retries reuse the existing bundle. A published release tag must keep the exact source and assets it already contains; changed release content requires a new module version. After a workflow finishes, pull its generated commit into your development checkout before making the next source push.

The module workflow prints the checked-out core commit and verifies its build tools before installing dependencies. If a run reports `vite build --release` followed by `Unknown option --release`, its core checkout predates the new builder. Push the updated build tools to the core repository's `main` branch, then rerun the workflow. The preflight also checks that the bundle script is present.

Billing's **Build frontend assets** workflow builds and commits `Resources/dist` on pushes to its `main` branch, and can also be run manually. It uses the same core build tools and bundle script as Contact Center. This workflow updates the Git checkout distribution only; it does not upload a licensed archive to Keygen.

For other module release pipelines, check out the matching core build tools, put the module source under `Modules/NAME`, install the core lockfile's dependencies, and build:

```bash
npm ci
npm run build -- --release --module NAME --outDir /tmp/NAME-frontend
```

Copy that output into the module archive as `Resources/dist`, alongside its source. Never publish a module version's PHP and frontend separately. Bump the module version whenever its packaged frontend changes. A module release must be rebuilt when its shared runtime fingerprint changes.

To bundle assets for another Git-managed module, run the shared script in CI from its source repository after building and validating its assets:

```bash
bash /path/to/fspbx/.github/scripts/bundle-module-frontend.sh HEAD /tmp/NAME-frontend /path/to/fspbx
```

The script prints the bundled commit ID for release tags and archives. Optional arguments are the destination branch (default `main`), remote (default `origin`), and new release tag. This is a publishing step run in CI, never by `app:update`. Existing licensed installations need a newly versioned module archive to receive newly packaged assets; republishing an already installed version does not trigger a download.

To prepare core assets without activating them:

```bash
npm run build -- --release --outDir /tmp/fspbx-frontend
```

The output directory must not already exist. The frontend API contract lives in `build/frontend-contract.json`; increment its API version for incompatible shared runtime changes. Changes to the core dependency lockfile also change the runtime fingerprint. Test core and module packages together before publishing; the browser must support native import maps.

## Storage and verification

Compiled packages are immutable directories under `storage/app/public/frontend`. An atomic activation record at `storage/app/frontend/active.json` selects the current core and module packages; `previous.json` supports rollback. Existing browser tabs can still fetch older chunks because activation does not delete previous packages. These records are node-local deployment state and should not be replicated between servers with different installed source or modules.

Retain packages referenced by both activation records and by recently opened browser sessions. No automatic package deletion is performed. On redundant installations, install and verify assets on each server.

Useful checks for release preparation:

```bash
vendor/bin/phpunit --do-not-cache-result tests/Unit/FrontendAssetsTest.php
node --experimental-vm-modules .github/scripts/test-frontend-bundles.mjs /tmp/fspbx-frontend /tmp/NAME-frontend
```

The JavaScript check links every compiled chunk against the shared runtime and rejects unresolved dependencies or exports. It does not replace browser testing of the affected pages.
