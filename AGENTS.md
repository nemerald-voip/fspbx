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

## Basic Queues

- Basic Queues are a main-repo feature and must work without optional modules under `Modules/`. Do not route Basic Queue status/detail pages into module controllers.
- Basic Queue pages use FS PBX call center tables and FreeSWITCH `mod_callcenter`. The database is the source of truth, but queue, tier, and agent changes also need the matching event socket commands so FreeSWITCH runtime state changes immediately.
- For Basic Queue Music on Hold selects, mirror the Phone Number update modal pattern: use grouped `getMusicOnHoldCollection(session('domain_uuid'))` options with VueForm `:groups="true"`, searchable non-native selects, `:strict="false"`, and `allow-absent`.
- Keep Music on Hold values compatible with FreeSWITCH. Domain streams should use `local_stream://{domain_name}/{music_on_hold_name}`; global streams can remain `local_stream://{name}`. Do not rebuild full recording paths as relative filenames when saving existing values.
- Queue extension generation must avoid colliding with Ring Groups and other extension-like features. Keep Basic Queue ranges separate from Ring Group ranges.
- Basic Queue agent forms should put Contact first. Selecting a contact should autofill agent name, agent ID, and agent password from the selected extension/contact. Do not reintroduce the deprecated User field.
- Keep Basic Queue agent status choices simple unless the product scope changes: `Logged Out`, `Available`, and `On Break`.
- For queue tier changes, keep the FreeSWITCH command order close to the legacy call center behavior. Removing an agent from a queue should delete the live tier before reloading the queue. Deleting an assigned agent should delete the live tier, reload the queue, then delete the live agent. Avoid extra `reloadxml` calls for pure tier removals.

## Optional Modules

- Not every server has optional modules under `Modules/`. Main-repo features should not assume an optional module exists unless the code has an explicit availability check and a safe fallback.
- Module-specific implementation notes belong in an `AGENTS.md` inside that module instead of the root guide.

## Music On Hold

- The native Music on Hold page replaces the old `/app/music_on_hold/music_on_hold.php` surface. Keep create/update/upload forms in VueForm.
- Treat a visible stream as a family of FreeSWITCH `mod_local_stream` rows, normally one row for each rate: `8000`, `16000`, `32000`, and `48000`. Do not expose a rate dropdown for normal stream creation; create, edit, upload, and delete the family together.
- Uploaded audio should be converted with `ffmpeg` to mono 16-bit PCM WAV for all supported rates to avoid FreeSWITCH transcoding surprises.
- Stream paths are generated, not user editable. Use `$${sounds_dir}/music/{domain-or-global}/{stream-name}/{rate}` for stored rows. The UI can display the family root without the trailing rate.
- Global Music on Hold streams use `global` in the generated filesystem path and `NULL` `domain_uuid` in the database. If a VueForm select needs a non-empty value for Global, use a UI-only sentinel and normalize it back to `NULL` before validation.
- Treat global Music on Hold streams as view-only from the tenant page. Normal add/edit/delete/upload permissions must only modify streams assigned to `session('domain_uuid')`; do not let action permissions alone modify `NULL` `domain_uuid` rows.
- Users without `music_on_hold_domain` should not see the domain selector. Creating a stream should assign their session domain. Editing an existing stream must preserve its stored `domain_uuid`.
- When Music on Hold rows or files change, clear the FS PBX local stream XML cache with `FusionCache::clear('configuration:local_stream.conf')` and reload `mod_local_stream` when requested. The generated cache file lives at `/var/cache/fusionpbx/configuration.local_stream.conf`.
- The FS PBX Lua XML generator for `local_stream.conf` may need patches in two places: `public/app/switch/resources/scripts/app/xml_handler/resources/scripts/configuration/local_stream.conf.lua` for future installs and `/usr/share/freeswitch/scripts/app/xml_handler/resources/scripts/configuration/local_stream.conf.lua` for existing servers.
- The Lua XML generator should build directory names as `{domain_name}/{music_on_hold_name}` without appending the rate, while the row path still points to the rate-specific directory. It should default blank or null rates to `48000` and honor row-level `channels` and `interval` values.
- Deleting a stream should delete the whole generated stream folder under `/usr/share/freeswitch/sounds/music/{domain-or-global}/{stream-name}`, not just individual DB rows. Keep deletion guarded so it cannot remove the music root or a domain root.
- Avoid icons that look like playback unless the action actually starts playback. For file selection rows, prefer an audio/file icon; reserve play icons for buttons that open or start the player.

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

## Included Legacy Files

- Legacy admin files are included in the `/public` directory. This directory is not tracked. All updates must be made via an update file.
