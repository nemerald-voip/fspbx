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
- For modern Inertia pages, pass page-specific permissions from the controller as a `permissions` prop and read `props.permissions` in Vue. Do not add new page-specific permission keys to `HandleInertiaRequests` unless a truly shared layout/component needs them globally.
- For date filters, follow the CDR pattern: pass `timezone` as a top-level Inertia prop for the DatePicker/UI, but do not include it in the API `filter` payload. Only send filters the QueryBuilder endpoint explicitly allows.
- Use VueForm for create/update modals.
- Keep forms clear and operational. Avoid marketing-style UI.

## Extensions And Voicemail

- Extension numbers and voicemail IDs are only unique inside a domain. Any lookup, relationship use, eager load, listener, observer, job, or response reload that connects `v_extensions.extension` to `v_voicemails.voicemail_id` must also constrain `domain_uuid`.
- `Extensions::voicemail()` is intentionally broad because Eloquent does not enforce the domain match there. Do not update, delete, sync copies, or read user-facing voicemail data from `$extension->voicemail` unless the relation was loaded with a domain filter or the voicemail was queried explicitly by `domain_uuid + voicemail_id`.
- Extension suspension and unsuspension must only toggle voicemail for the same tenant. DND updates should target the concrete extension row by `extension_uuid` and `domain_uuid`, not by extension number alone.

## Settings Pages

- Default Settings and Domain Settings are native Laravel/Vue pages. Keep page routes in `routes/web.php` and data/action routes in `routes/api.php`.
- Default Settings is the global catalog. Domain Settings is the working surface for overrides and custom domain rows.
- Domain Settings should open focused on overrides/custom rows by default; users can switch the source filter to see inherited defaults.
- Domain Settings status toggles should update the row in place and refresh silently. Do not blank the whole list for a single on/off click.
- Use VueForm for settings edit and copy modals, including copy-to-domain workflows. Keep VueForm labels non-floating on these forms.
- In settings edit forms, Category should be a searchable/createable select populated from existing categories, and Subcategory should be labeled as `Setting Name`.
- Do not invent an order value for existing or newly-created settings. Preserve `NULL`/blank order unless the user enters one or a default-backed override is intentionally copying the default row.
- Domain option labels should prefer `domain_description` and only fall back to `domain_name` when no human label exists.
- The native Domain Settings route requires a domain UUID (`/domains/{domain}/settings`). Generic navigation should go to `/domains`, where each domain row links to its settings.
- Preserve legacy settings semantics: non-array domain overrides match defaults by category/subcategory/type, while `array` settings remain distinct rows.

## Devices And Provisioning

- Device Key Templates are the preferred reusable key layout workflow. Show Key Template controls before legacy Device Profile controls, but keep legacy Profile support in place unless explicitly asked to remove it.
- Key Template and Device Profile assignment should be mutually exclusive in device create, update, bulk update, and extension device modals. If one is selected, clear/disable the other.
- Device tables and extension assigned-device mini tables should show a combined Profile / Key Template column so users can see which assignment is active.
- Device Key Templates are domain-scoped. Use `device_key_template_assign` for assignment UI/actions and pass permissions from the controller props rather than global Inertia middleware when working on the Devices page.
- Provisioning merge order for effective keys is: old assigned Device Profile keys, new assigned Device Key Template keys, old per-device legacy `v_device_keys`, then new per-device `device_keys`.
- Blank or `N/A` Key Template keys intentionally occupy their slot and can clear older profile keys.
- Keep vendor translation and normalization in the existing provisioning path. Do not add new broad vendor translations unless the stored data shape truly requires it.
- The modern provisioning path is `app/Http/Controllers/ProvisioningController.php`; the old provisioning URL still uses `public/app/provision/resources/classes/provision.php`.
- Native provisioning renders `provisioning_templates.content` from the database, not the resource file directly. Changes under `resources/provisioning/.../template.blade.php` require reseeding default templates with `php artisan prov:templates:seed` or manually updating custom template content.
- After reseeding provisioning templates, run `php artisan view:clear`. Changed template content forces Blade to recompile the stored string, and a stale/wrongly-owned compiled view causes a 500 on the phone's config request. Do not render provisioning Blade as root (via `tinker`/`artisan`) — it writes root-owned files in `storage/framework/views` that the `www-data` web server cannot overwrite. Verify template rendering with `curl` against `/prov/...`, not a root tinker `Blade::render`.
- Custom provisioning templates do not inherit default template changes automatically.
- When changing default provisioning templates, bump the Blade front-matter `version:` so the seeder can update the database row clearly.
- The provisioning template seeder keys default rows by `vendor + folder name + type`. When renaming a default template folder, add an update step before `prov:templates:seed` runs that renames the existing `provisioning_templates.name` row and any custom `base_template` references, otherwise the seeder will create a second default row under the new folder name.
- Provisioning preview should render through the same code path as live provisioning but must not touch `/prov`, digest auth, or device last-contact metadata. Treat generated previews as credential-bearing output and gate them with a dedicated action permission.
- The old provisioning URL delegates modern `device_keys` overlay behavior to `fspbx_apply_new_keys_override()` in `app/helpers.php`. Key Template support for that old path should stay in helpers where possible.
- `device_key_templates.enabled` is stored as the string `'true'`, not a boolean. Legacy SQL helpers should compare it as `t.enabled = 'true'`.
- If the old `provision.php` path must participate in a new behavior, remember `public/` is not tracked. Make the runtime file change for the working server, then add or update an update class to download/replace the file for deployed systems.
- When expanding a Grandstream template from an official configuration file, use active `P... = ...` assignments as the vendor baseline and retain commented P-value entries as documentation only. Apply FS PBX system, account, credential, and key values as an explicit overlay after parsing the baseline.
- Comments inside Blade/PHP setup blocks are not part of the provisioned XML. If provisioning previews should explain each Grandstream P-value, render the captured descriptions as XML comments next to the resulting elements and render prominent XML-comment banners for section headings.
- Grandstream key mode numbers are key-family specific. On GXP17xx, fixed/line VPKs 1-6 use values such as `11` for BLF and `26` for Monitored Call Park, while dynamic VPKs 7-32 use `1` for BLF and `16` for Monitored Call Park. Do not reuse one mode map for both families.
- Grandstream account selectors in P-values are zero-based even when the UI labels them as Account 1, Account 2, and so on. Normalize FS PBX line assignments accordingly.
- For normal GXP17xx BLF operation, leave the account BLF server blank so subscriptions use the configured SIP server. Monitored park values use the form `park+*5901`.
- When a vendor baseline contains settings for unsupported accounts or model variants as commented entries, do not activate them merely to make the generated template more complete. Only emit supported active settings and intentional FS PBX overrides.
- Yealink templates should use `$main_keys` for `linekey.*` output and `$expansion_keys` for `expansion_module.1.key.*` output.
- Yealink phone line keys should clear unused slots through 30; Yealink expansion module keys should clear unused slots through 60 per module.
- Yealink devices can use the Expansion Keys tab in the device edit modal.

## Phonebooks

- A phonebook is a device directory made of optional internal extensions (an `include_extensions` toggle) plus the phonebook's own contacts. This supports extensions-only, contacts-only, or both. Keep the editor simple enough that an end user grasps that composition at a glance.
- Contacts are per-phonebook in `phonebook_contacts`, not the shared CRM `contacts`/Messages store. Each phonebook owns its list; do not reuse account-wide contacts for phonebooks or every phonebook shows the same people. Contacts save together with the phonebook via `PhonebookService` (delete + recreate), not through separate contact endpoints.
- Data model: `phonebooks` (`enabled`, `is_default`, `include_extensions`), `phonebook_contacts` (per phonebook), and `device_phonebook` (per-device assignment with `slot`).
- Directory entries are built in `app/Services/Provisioning/Phonebook/PhonebookBuilder.php` and rendered by vendor formatters (Grandstream `AddressBook`, Yealink `YealinkIPPhoneDirectory`). The final list is de-duplicated and sorted by last name, then first name, then display name, matching Grandstream's on-phone `phonebook.sortBy = LastName`.
- Phones fetch directories from `/prov/directory/{book}/{path}` (`PhonebookController`), reusing the `provision.digest` middleware. `{book}` is a phonebook UUID or `all`. The device is resolved from the MAC/serial path segment, because some phones append a fixed filename (Grandstream requests `<server>/phonebook.xml`) so the basename is not the device token — `DigestProvisionAuth` scans path segments when the basename yields no token.
- Grandstream downloads a single `phonebook.xml`, so its config points at the device-merged `all` directory (`/prov/directory/all/{mac}`), which combines every assigned phonebook. Yealink supports multiple remote phonebooks, so it gets one credentialed URL per phonebook via `remote_phonebook.data.X`.
- Grandstream `phonebook.download.server` must be host/path WITHOUT a scheme; the separate `phonebook.download.mode`/P330 selects HTTP vs HTTPS. Item-style templates strip the scheme inline; GXP17xx strips it for P331 and derives P330 from the scheme.
- Grandstream config is injected through settings in `ProvisioningController::buildTemplateVars` (`grandstream_phonebook_server`/`_username`/`_password`/`_download_interval`), so every Grandstream model picks it up without per-template edits. When no phonebook resolves, leave any manually-configured phonebook settings intact.
- Device assignment lives on the device edit modal's Phonebook tab: "Use account default" (all `is_default` phonebooks) or "Custom" (rows in `device_phonebook`, order = slot). `DeviceService` syncs `device_phonebook` from `phonebook_mode` + `phonebook_uuids`.
- Provisioning preview mirrors the phone: Grandstream shows one merged `phonebook.xml`; Yealink shows one file per phonebook.
- User-facing phonebook copy uses "Account", never "domain" (the internal multi-tenant term). Show a per-vendor hint on the device Phonebook tab so the Grandstream-merges-into-one vs Yealink-separate-directories behavior is not surprising.

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

## FreeSWITCH Modules Page

- Module config writes should target `modules.conf.xml` under the FreeSWITCH conf dir from default settings category `switch`, subcategory `conf`, name `dir`; do not depend on `session('switch.conf.dir')`.
- Module disk sync should scan the module dir from default settings category `switch`, subcategory `mod`, name `dir`.
- When module autoload settings change, rewrite `/autoload_configs/modules.conf.xml` and run `reloadxml` through `FreeswitchEslService`.
- Start and stop actions should use `FreeswitchEslService` commands (`load mod_name`, `unload mod_name`) and then refresh runtime status from `show modules as json`; allow for a short delay before the runtime list reflects the command.
- Treat FreeSWITCH `-ERR` responses as real action failures and surface the ESL text to the user. Some modules return useful errors such as `Module is not unloadable.`
- `Notification.vue` expects a message-bag shape where each key maps to an array and renders the first value for each key, for example `{ error: ["FreeSWITCH returned an error."], error_1: ["mod_x: Module is not unloadable."] }`.
- Runtime start/stop buttons should reflect current module status: show Start only for stopped modules, Stop only for running modules, and hide both when status is unknown.

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

## Scheduled Announcements

- Scheduled Announcements are broader than school bells. Keep naming and UI copy generic enough for bells, tones, and periodic announcements.
- The schedule is the main object. It owns one recording, one selected-extension list, optional start/end dates, events, and exclusions. Events are simple day/time rows; their presence means they run. Do not add event names or per-event enabled flags unless the product scope changes.
- Exclusions are simple date/comment rows on the schedule. A matching exclusion skips that schedule for that local date. Do not model alternate schedules or exception types unless explicitly requested.
- Use VueForm for all create/update modal controls and mirror existing Ring Group recording controls for recording select/play/edit/delete/upload behavior.
- Keep Scheduled Announcements controllers thin. Put validation and normalization in FormRequest classes, and keep schedule persistence/execution behavior in services.
- Do not use FreeSWITCH `sched_api` or pre-schedule FreeSWITCH jobs. Laravel finds due events and executes at fire time through `FreeswitchEslService`.
- Real-time behavior is "on time or missed, never late." Respect the configured fire window; events found after the window should be logged as missed rather than played late.
- Redundant-server execution is guarded by authoritative DNS active-node checks before claim and before ESL execution. If active status, DNS, local node IPs, or FreeSWITCH health are uncertain, fail closed and log a skipped/missed run rather than risking duplicates.
- Busy extension behavior is schedule-scoped. `skip` should check active FreeSWITCH channels and leave busy extensions alone; `force` may originate with auto-answer even if a phone is already on a call. `page.lua` is a useful reference for busy-extension detection, but scheduled playback should stay in the Laravel/ESL path.
- Playback should include a short silence lead-in before the recording to avoid clipped audio on auto-answer endpoints.

## Fax Jobs And Retention

- New outbound fax state lives in `outbound_faxes`; do not use legacy `v_fax_queue` for current outbound fax alerts or status decisions.
- The old fax queue controller/page can remain legacy unless explicitly asked to rebuild or remove it. Do not add compatibility fields to `OutboundFax` just to support that page.
- `CheckFaxServiceStatus` should evaluate pending and failed outbound fax alerts from `outbound_faxes`.
- `DeleteOldFaxes` should clean old inbound/sent `v_fax_files`, associated `v_fax_logs`, old terminal `outbound_faxes`, logs/files associated with those outbound rows, and leftover legacy `v_fax_queue` records.
- Fax retention file cleanup scans the `fax` storage disk. Outside temp directories it only removes old `.tif` and `.pdf` files; temp directories have their own shorter orphan cleanup window.
- Physical fax file deletion is based on filesystem modified time, while database retention uses `fax_date` or `created_at`. Be careful when changing either side so old DB rows and physical files do not drift unexpectedly.

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
- Do not manually add permissions in update classes unless the normal update flow is bypassing the seeder for that release.

## Updates

- If an update version has shipped, do not keep editing it for new behavior. Create the next update.
- An update should be best-effort when touching host services. App updates should not fail just because a FreeSWITCH module cannot be compiled.
- When updates change generated dialplan XML or dialplan details, clear only the affected dialplan cache contexts.
- Keep update console output truthful. Do not claim a module, file, or cache was refreshed unless it actually was.
- When replacing untracked legacy files under `public/app/...`, follow the existing update pattern: download from the canonical GitHub raw URL, ensure the destination directory exists, reject empty downloads, and register the update step in `UpdateApp.php`.

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
