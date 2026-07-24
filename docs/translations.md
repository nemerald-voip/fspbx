# Translations

The app UI (Blade + Vue/Inertia) and backend messages share one translation
catalog: `resources/lang/{locale}.json`, keyed by the literal English source
string. There's no invented key naming -- the string you already see in the
code *is* the key, so wrapping existing copy is the whole job.

```php
// Backend (PHP/Blade)
__('The caller ID was successfully updated')
```

```vue
<!-- Frontend (Vue) -->
{{ $t('Caller ID') }}
```

`resources/lang/en-us.json` is the **source manifest**: every other locale is
translated against it, and Crowdin syncs from it. Its own "translation" of
each key is just the key itself.

## Adding a translation for an existing locale

Edit `resources/lang/{code}.json` directly (e.g. `resources/lang/es.json`)
and open a PR, or use [Crowdin](#crowdin) if you'd rather translate through a
web UI without touching Git. Either path lands as a PR and goes through the
same CI check (see [Validation](#validation)).

You don't have to translate every key. Partial translations are expected and
useful -- untranslated keys just render in English (or in the parent dialect,
see below) until someone fills them in.

## Adding a new locale or regional dialect

Spanish has real dialect variation (Mexico, Spain, Latin America generally),
and so will most languages contributors add. Rather than requiring a full
retranslation per dialect, each locale can declare a `fallback` parent in
[`config/locales.php`](../config/locales.php), and only needs to define the
strings that actually differ from that parent:

```php
'es-es' => [
    'name' => 'Spanish',
    'native' => 'Español',
    'fallback' => 'en-us',
],
'es-419' => [
    'name' => 'Spanish (Latin America)',
    'native' => 'Español (Latinoamérica)',
    'fallback' => 'es-es',
],
'es-mx' => [
    'name' => 'Spanish (Mexico)',
    'native' => 'Español (México)',
    'fallback' => 'es-419',
],
```

At runtime, `es-mx` resolves a key by checking `es-mx.json`, then `es-419.json`,
then `es-es.json`, then finally `en-us.json` -- see
`App\Support\Localization\LocaleRegistry::chain()` and
`App\Support\Localization\LocaleFileLoader`. `resources/lang/es-mx.json` and
`es-419.json` in this repo are seeded with only one illustrative override
each, to prove the mechanism -- they need native-speaker review, not just
more overrides, before they're comprehensive.

Locale codes here are **Crowdin's own locale IDs, lowercased** (from
`https://api.crowdin.com/api/v2/languages`), not invented codes -- e.g.
Crowdin has no bare "es"; its generic "Spanish" target language IS `es-ES`,
which is why `es-es` (not `es`) is the Spanish parent locale. Get the exact
ID from Crowdin's API/project language settings before registering a new
locale, rather than guessing -- a mismatch means Crowdin downloads
translations into a file this app never reads.

`config/locales.php` currently registers 32 locales (`en-us` plus the 31
languages enabled as Crowdin target languages on this project). Only the
Spanish family demonstrates the fallback chain above; every other locale
falls back straight to `en-us` since none of the others were requested as
regional variants of one another.

To add a brand-new locale:

1. Confirm it's added as a target language in the Crowdin project, and note
   its exact locale ID from Crowdin.
2. Add an entry to `config/locales.php` (`name`, `native`, and `fallback` if
   it's a dialect of something already registered) using that ID, lowercased,
   as the key.
3. Add the same mapping (Crowdin's ID -> the lowercased key) to
   `languages_mapping` in `crowdin.yml`.
4. Open a PR. `resources/lang/{code}.json` doesn't need to exist yet --
   `LocaleRegistry`/`LocaleFileLoader` treat a missing file as 0% translated
   and fall back cleanly; the download-translations workflow creates it once
   Crowdin actually has content for that language.

A locale only appears in a domain's language picker once its own keys cover
`config('locales.minimum_completion')` (currently 60%) of `en-us.json` --
see `LocaleRegistry::available()`. Below that, it still exists, still renders
(via fallback), and can still be worked on across multiple PRs; it's just not
offered to tenants yet.

## Developer workflow: adding new UI copy

After writing new user-facing strings, run:

```
php artisan lang:sync
```

This scans `app/`, `resources/views/` (excluding `resources/views/emails/`,
which the [email template system](../app/Services/EmailTemplateService.php)
translates separately), `routes/`, and `resources/js/` for
`__()`/`trans()`/`@lang()` (PHP/Blade) and `$t()`/`$tChoice()`/`trans()`
(Vue/JS) calls, and adds any new literal strings to `resources/lang/en-us.json`.
Commit the resulting diff. It won't remove keys it can't find anymore unless
you pass `--prune` -- a string might still be used somewhere the scan doesn't
cover (e.g. `Modules/`, not yet included).

## Validation

`.github/workflows/validate-translations.yml` runs
`.github/scripts/validate-translations.php` on any PR touching
`resources/lang/**`. It fails a PR for:

- Invalid JSON.
- A key that doesn't exist in `en-us.json` (almost always a typo, or a
  leftover key after the English source string changed).
- A translation whose `:placeholder` tokens don't match the source string's
  (a dropped or invented variable -- a functional bug, not a translation
  quality issue).

It does **not** fail on incomplete translation coverage. You can run it
locally before opening a PR:

```
php .github/scripts/validate-translations.php
```

## Crowdin

`crowdin.yml` and `.github/workflows/crowdin.yml` are wired up so
`resources/lang/en-us.json` syncs to Crowdin and translations come back as a
PR, letting contributors translate through Crowdin's web UI instead of
hand-editing JSON. **This needs one-time setup on Crowdin and GitHub that
only a repo admin can do:**

1. Create a **File-based** project at [crowdin.com](https://crowdin.com)
   (free for open-source projects) and add `resources/lang/en-us.json` as its
   source file, or just let the first `push` to `main` upload it
   automatically once secrets are configured.
2. Add each target language you want translators to see in Crowdin's UI. This
   project currently has all 31 non-English languages registered in
   `config/locales.php` enabled as targets (Spanish plus the Latin
   America/Mexico regional variants, and 28 other languages). Adding more
   later means: enable it as a Crowdin target language, look up its exact
   locale ID at `https://api.crowdin.com/api/v2/languages`, then add both a
   `config/locales.php` entry and a `languages_mapping` line in `crowdin.yml`
   using that ID -- see [Adding a brand-new locale](#adding-a-new-locale-or-regional-dialect).
3. In the repo's **Settings > Secrets and variables > Actions**, add:
   - `CROWDIN_PROJECT_ID` -- the numeric ID from the project's API settings.
   - `CROWDIN_PERSONAL_TOKEN` -- a personal token from
     [crowdin.com/settings#api-key](https://crowdin.com/settings#api-key)
     scoped to source files/translations (read+write) and translation status
     (read).
   - `GH_TOKEN` -- a classic GitHub personal access token with `repo` scope,
     used to open the translations PR (Crowdin's own recommendation; the
     default `GITHUB_TOKEN` is intentionally not used here).

Until those secrets exist, both Crowdin jobs skip quietly instead of failing
CI -- see the `if:` conditions in `.github/workflows/crowdin.yml`.

## What's not covered yet

- **Optional modules** (`Modules/Billing`, `Modules/ContactCenter`,
  `Modules/StirShaken`) aren't scanned by `lang:sync` and don't have their own
  translation files yet. `nwidart/laravel-modules` supports a namespaced
  `Resources/lang/{locale}` per module; adding that is future work, not
  something every install needs (per `AGENTS.md`, main-repo features must
  work without optional modules).

