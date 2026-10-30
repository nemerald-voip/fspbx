---
id: frontend-assets
title: Frontend assets and local builds
sidebar_position: 6
---

# Frontend assets and local builds

FS PBX installs compiled frontend files during application updates. Node and npm are needed when developing or deliberately rebuilding locally, but normal updates do not run them. There is no environment setting to switch between released and local assets.

## Application updates

`php artisan app:update` downloads the frontend package matching `config/version.php`, verifies its checksum and frontend source files before running update steps, and activates it after module refreshes complete. A missing or mismatched package stops the update before those steps. It never falls back to running npm.

The source checkout happens separately, before `app:update`. Asset preflight does not undo a previous `git pull`. Use source from the matching published release; unbuilt frontend changes on `main` cannot use an older release's assets just because the version number is unchanged.

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

The core package contains no optional module pages. Modules with a frontend ship their compiled assets in `Resources/dist` inside the existing licensed module archive. The normal license and entitlement checks still control downloads. Git-managed module checkouts remain under developer control and can be included by `npm run build`.

Core and module packages share a frontend API version and dependency fingerprint. Compatible installed module assets survive a skipped module update. Missing or incompatible module assets are reported; they are not loaded into an incompatible runtime. Publish compatible module packages before rolling out a change to the shared runtime. License/API failures remain optional-module failures and do not invoke a local build or abort the core update.

Module packages include their own CSS. They use the core Vue, Inertia, VueForm, and translation instances, so installing a module does not require rebuilding the core. Site-specific public settings such as the Reverb key are supplied by Laravel at page load. Private keys are never supplied to the browser.

## Preparing releases

The core repository's **Build frontend release assets** workflow builds a published release tag with `npm ci` and attaches:

- `fspbx-frontend-VERSION.tar.gz`
- `fspbx-frontend-VERSION.tar.gz.sha256`

For release preparation, create the tag and a draft GitHub release, dispatch the workflow for that tag, and verify the attachments before publishing. The published-release event also builds missing assets; wait for the attachments before directing administrators to upgrade. Existing complete attachments are retained. Fresh installations check for the package and use the matching tag's source.

Contact Center's release workflow builds its frontend before creating a release and includes it in the archive sent through Keygen. Its `FSPBX_FRONTEND_CORE_REF` GitHub repository variable may pin the build-tool checkout to a compatible core tag or commit; without it the workflow uses `main`. This is release preparation configuration, not a customer-server mode switch. Publish the core build tooling before running the updated module workflow.

For other module release pipelines, check out the matching core build tools, put the module source under `Modules/NAME`, install the core lockfile's dependencies, and build:

```bash
npm ci
npm run build -- --release --module NAME --outDir /tmp/NAME-frontend
```

Copy that output into the module archive as `Resources/dist`, alongside its source. Never publish a module version's PHP and frontend separately. Bump the module version whenever its packaged frontend changes. A module release must be rebuilt when its shared runtime fingerprint changes.

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
