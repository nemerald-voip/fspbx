---
id: frontend-assets
title: Frontend assets and local builds
sidebar_position: 6
---

# Frontend assets and local builds

Frontend assets are the files that display the FS PBX web interface. FS PBX provides these files ready to use, so normal updates do not need to compile them on your server. This reduces the CPU and memory needed during an update.

You only need Node and npm if you are developing features or rebuilding the interface after making local changes.

## During an application update

Follow the [How to update guide](/docs/updates). The usual update command installs the frontend files for your application version:

```bash
php artisan app:update
```

There is no separate build step or setting to change. On a cluster, complete the update on each server.

If the required frontend files cannot be installed, the application update stops and displays an error. Check the message and resolve the reported problem before retrying. The updater will not start a resource-intensive local build automatically.

## Optional modules

Modules installed through your license include their frontend files with the module update. Your license determines which modules are available.

If you installed a module through Git, pull its updates manually before running the application update. For example, for an installed Contact Center module:

```bash
cd /var/www/fspbx
git -C Modules/ContactCenter pull --ff-only
php artisan app:update
```

Use a completed module release so its frontend files are available. Repeat the pull for any other modules you manage through Git.

A module update warning does not stop the application update, but the affected module may remain unavailable. Resolve the warning, then retry the module update:

```bash
php artisan modules:refresh
```

## Rebuilding after local changes

You can still edit Vue files and build the interface on a development or production server. From `/var/www/fspbx`, run:

```bash
npm run build
```

This rebuilds the application and enabled modules. The new frontend is used only after the build succeeds. No setting needs to be switched back afterward.

If dependencies need installing first, run `npm ci`. Local builds can use significant CPU and memory, so plan production troubleshooting accordingly.

For development with live reloading, use:

```bash
npm run dev
```

Stop the development server before checking the compiled interface. Future updates use released frontend files when they match your installed code. Keep track of any local code changes you need to retain.

## Troubleshooting

Run the following commands from `/var/www/fspbx`.

### Retry frontend installation

After resolving a download or installation error, retry the application's frontend installation without repeating the full update:

```bash
php artisan frontend:install
```

For a module warning, use `php artisan modules:refresh` instead.

### Frontend files do not match the installed version

Check that the application and modules have been updated to compatible releases. If you intentionally changed frontend source files locally, run `npm run build` to include those changes.

### Restore the previous frontend

If a recent frontend change caused a problem, you can restore the previous frontend files:

```bash
php artisan frontend:rollback
```

The previous files must be compatible with the currently installed application and module versions. This restores the interface files only; it does not undo an application update or database changes.
