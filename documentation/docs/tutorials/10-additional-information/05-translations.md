---
id: translations
title: Translations
slug: /additional-information/translations/
sidebar_position: 5
---

# Translations

FS PBX's interface is translated by its community, directly through GitHub.
This page explains how to contribute translations. To change the language
used by your system, see [Language Settings and Sounds](/docs/getting-started/language-settings/)
in the Getting Started Guide.

## Helping with translation

There's no separate translation website -- everything lives in the
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

## For administrators: switching the language

The administrator instructions are now in [Language Settings and Sounds](/docs/getting-started/language-settings/).
That guide covers system and account interface languages, installing call audio
packs, voicemail configuration, and troubleshooting.
