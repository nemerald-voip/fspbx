---
id: translations
title: Translations
slug: /additional-information/translations/
sidebar_position: 5
---

# Translations

FS PBX's interface is translated by its community, directly through GitHub.
Interface text and email content have separate translation workflows. This page
explains how to contribute translations. To change the language
used by your system, see [Language Settings and Sounds](/docs/getting-started/language-settings/)
in the Getting Started Guide.

## Translating interface text

There's no separate translation website. Interface translations live in the
[FS PBX GitHub repository](https://github.com/nemerald-voip/fspbx), in
`resources/lang/{locale}.json`. Each file is a flat list of
`"English text": "Translated text"` pairs. To contribute:

1. Open the file for your language, e.g. `resources/lang/es-es.json` for
   Spanish. Every string the app knows how to translate is already listed
   there, one `"English text": "..."` pair per line -- new strings are
   added to every language file automatically the moment they're added in
   English, so you never have to go hunting for what's new or add a key
   yourself.
2. An empty value (`""`) means nobody has translated that string yet --
   it currently falls back to showing the English text. Search the file for
   `": ""` to jump straight to what's left, and fill in your translation:
   ```json
   {
       "Save": "Guardar",
       "Extensions": "Extensiones"
   }
   ```
3. If a source string contains a `:placeholder` (e.g. `":count items"`),
   keep the same placeholder token in your translation, just move it to
   wherever it reads naturally in your language -- CI checks that the
   token survives translation, not its position.
4. Open a pull request with your changes. A CI check validates the JSON and
   the placeholder tokens automatically; someone will review and merge it.
   You don't need to finish a whole language in one PR -- leaving the rest
   blank is completely normal, and each string you fill in is used as soon
   as it's merged.

If your language doesn't have a file yet, don't create one by hand -- open
an issue or PR asking for it to be added to `config/locales.php`, which
generates the file for you (fully populated, ready to fill in) the next
time `lang:sync` runs.

**A note on regional variants:** some languages are listed more than once --
for example, Spanish, plus Spanish (Mexico) and Spanish (Latin America). Each
one is a fully independent file with no connection to the others -- so if
you're translating Spanish (Mexico), you're translating every string in
`resources/lang/es-mx.json` yourself, not just the handful that differ from
`es-es.json`. That's a deliberate choice: having one variant borrow from
another was more confusing than helpful.

## Translating shipped email templates

Email subjects and bodies are maintained separately from the interface JSON
catalogs. Each language needs its own complete template. FS PBX selects an
existing language version when sending email, fills in its variables, and falls
back to English when that version is unavailable. It does not translate the
message at send time.

### Source format

The shipped sources use **Blade files (`.blade.php`)**, not Markdown (`.md`).
Each email purpose has an HTML file and a required plain-text companion under
`resources/views/emails/{locale}/`. For example, the English password-reset
template consists of:

```text
resources/views/emails/en-us/authentication/reset-password.blade.php
resources/views/emails/en-us/authentication/reset-password-text.blade.php
```

The HTML file begins with an `email-template` metadata comment. It owns the
pair's version, language, category, subcategory, subject, description, and HTML
layout. The plain-text companion carries only `format: text` and `layout: none`.

Translate the subject, visible HTML text, and plain-text text together. Keep
variable names, links, and Blade conditions intact. Do not add translation
helpers around the email text or put whole email bodies into JSON catalogs.
A subject such as `{{ $email_subject }}` inserts the subject supplied by the
sending workflow; write a translated subject with the appropriate variables
instead of leaving an English generated subject. Keep this variable when the
subject is intentionally supplied by a user, as in AI Agent follow-up emails.

### Adding another shipped language

The shipped folders are `en-us`, `ru`, `fr`, `es-419`, and `pt-br`. Folder names
must match a locale code in `config/locales.php` and the HTML file's `language`
metadata. Use `en-us` for English, not `en`.

1. Copy the English HTML and text pair into the corresponding category in the
   target locale folder. For example, a German password-reset translation uses
   `resources/views/emails/de/authentication/reset-password.blade.php` and
   `reset-password-text.blade.php`. Preserve the English files.
2. Set `language: de` and `version: 1.0.0` in the new HTML file. Keep the same
   category and subcategory. Translate its subject, description, HTML body,
   and the companion's plain-text body. The text companion must contain only
   `format: text` and `layout: none` metadata.
3. For templates with `layout: standard`, copy `en-us/email_layout.blade.php`
   to the new locale folder if it does not exist. Translate the visible footer
   and set the HTML `lang` attribute. Extend `emails.de.email_layout` from
   the German HTML template. Templates with `layout: none` remain self-contained.
4. Check both formats with representative data. Preserve variables, links,
   attachments, and all conditional branches. Template text is written directly
   in Blade; do not add calls to `__()` or other runtime translation helpers.
5. On a development installation, run `php artisan email:templates:seed --dry-run`
   to validate sources and see the import summary. Add `-v` to see every template
   and language. Then run `php artisan email:templates:seed` to import the files
   and use **Email Templates → Preview** to review them without sending email.

Each email purpose can be contributed independently. If a purpose is missing
in the requested language, the sender falls back to `en-us`. Regional variants
are matched exactly; `es-419` does not borrow templates from `es-es`.

### Updating an existing shipped template

When changing a shipped subject, HTML body, plain-text body, or layout, increase
the `version` in the HTML file's metadata, for example from `1.0.0` to `1.0.1`.
Bump it once for the pair; do not add version metadata to the text companion.

Include both formats in the pull request and check that they convey the same
message. Review variables and links carefully, especially verification codes
and password-reset URLs. Use the editor's **Preview** to inspect sample output
without sending email.

The `email:templates:seed` command discovers all locale folders and installs or
updates shipped defaults in the database. Normal application updates run this
command automatically. Its normal output is a summary; `-v` enables per-template
details, and failures are always reported. It requires a newer version when
template content changes and leaves custom templates unchanged. Existing
database templates are used for sending, so changing a source file alone does
not update an installed default. When changing a shared locale layout, bump the
versions of that locale's affected HTML templates as well.

## For administrators: switching the language

The administrator instructions are now in [Language Settings and Sounds](/docs/getting-started/language-settings/).
That guide covers system and account interface languages, menu assignments,
separate email templates per language, installing call audio packs, voicemail
configuration, and troubleshooting.
