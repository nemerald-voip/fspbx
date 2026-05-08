# FS PBX Agent Guide

This repo is a Laravel, Vue/Inertia, VueForm, and FreeSWITCH application. Before changing behavior, read the nearby code and follow the existing page, service, route, and update patterns.

## Working Style

- Prefer small, focused changes that match the current app structure.
- Do not revert unrelated local changes. This repo is often worked on with a dirty tree.
- Use `rg`/`rg --files` for searches.
- Use `apply_patch` for manual edits.
- Keep user-facing copy short and practical.
- For release notes, write for users first. Avoid implementation details unless they explain visible behavior.

## Laravel And Vue Pages

- Rebuild legacy `/public/app/.../*.php` admin pages as native Laravel/Vue pages when asked.
- Follow existing first-class pages, especially Devices and Conference-style pages, before inventing new patterns.
- Put the Inertia page route in `routes/web.php`.
- Put axios/data/action routes in `routes/api.php`.
- Use Spatie QueryBuilder for searchable/sortable/paginated data tables when that is the local pattern.
- Use VueForm for create/update modals.
- Keep forms clear and operational. Avoid marketing-style UI.

## Logs Page

- `/logs` is the shared logs surface. `LogsController` provides the Inertia page props and routes; individual log components usually fetch data through existing API controllers.
- Email and fax logs are domain-scoped. Default to the current `session('domain_uuid')`, and use `session('domains')` for multi-domain selectors or "all domains" views.
- Do not let a requested domain UUID bypass access. Validate requested domains against the accessible session domain list, and treat "all" as all accessible domains only.
- When a QueryBuilder endpoint receives `filter[domain_uuid]` but applies domain scoping manually, still add `domain_uuid` as an allowed no-op filter so Spatie does not reject the request.
- Keep destructive or retry actions bounded to accessible domains too, not only list queries.

## Permissions

- Keep action permissions separate from record-scope permissions.
- For record scope, prefer the pattern:
  - `*_view_all_records`
  - `*_view_self_records`
- Do not repurpose legacy permissions unless there is a concrete existing use that proves the meaning.
- Add durable permissions to `database/seeders/DatabaseSeeder.php`; it runs as part of updates.

## Updates

- If an update version has shipped, do not keep editing it for new behavior. Create the next update.
- An update should be best-effort when touching host services. App updates should not fail just because a FreeSWITCH module cannot be compiled.
- When updates change generated dialplan XML or dialplan details, clear only the affected dialplan cache contexts.
- Keep update console output truthful. Do not claim a module, file, or cache was refreshed unless it actually was.

## FreeSWITCH Files

- New Lua scripts belong in `resources/lua`.
- Future-install FreeSWITCH config belongs under `public/app/switch/resources/conf/...`.
- Existing-install FreeSWITCH config usually needs to be written or patched under `/etc/freeswitch/...` from an update class.
- Dialplan templates live under `public/app/dialplans/resources/switch/conf/dialplan`.
- For Lua called by dialplan XML, use paths that match the FreeSWITCH runtime script directory, such as `lua/call_block.lua`.

## Call Block Runtime

- The database is the source of truth.
- Redis is a runtime cache for FreeSWITCH call matching, not the source of truth.
- Keep dialplan XML cache separate from call-block rule cache.
- Call-block rule cache keys are versioned per domain:
  - `call_block:version:{domain_uuid}`
  - `call_block:rules:v{version}:{domain_uuid}:{direction}:{scope}`
- Laravel should bump the per-domain version after create/update/toggle/delete.
- Lua should fail open if both Redis and the database are unavailable.
- Use `hiredis_raw default ...` for `mod_hiredis` commands from FreeSWITCH.

## Lua Expectations

- Keep Lua comments useful and close to non-obvious logic.
- Use a single `DEBUG_MODE` switch when adding debug logs.
- Debug logs should be plain English and explain the important runtime decision.
- Preserve call routing safety: avoid blocking calls from unsupported or malformed rules unless behavior is explicit.
- Use atomic SQL for counters where concurrent calls can update the same row.

## Verification

- Run `php -l` on changed PHP files when practical.
- Run `npm run build` after frontend changes when possible.
- In the sandbox, Vite may fail with permission errors while writing temporary config files. If that happens, rerun the same build outside the sandbox when approved.
- If a runtime issue depends on FreeSWITCH, verify with real `fs_cli` output or logs when available.

## included FusionPBX

- FusionPBX with all legacy files is incuded in /public directory. This directory is not tracked. All updates must be made via an update file. 
