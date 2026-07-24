---
id: translations
title: Translations
slug: /additional-information/translations/
sidebar_position: 5
---

# Translations

[![Crowdin](https://badges.crowdin.net/fs-pbx/localized.svg)](https://crowdin.com/project/fs-pbx)

FS PBX's interface is translated by its community. This page is for two
audiences: people who'd like to help translate, and administrators who want
to set the language a tenant sees.

## Helping with translation

Translation happens on [Crowdin](https://crowdin.com/project/fs-pbx) -- a
free web-based tool, no coding or Git required. To get started:

1. Open the [FS PBX Crowdin project](https://crowdin.com/project/fs-pbx) and
   join it (sign up if you don't already have a Crowdin account).
2. Pick a language and start translating strings. Crowdin shows you the
   original English text next to a box for your translation.
3. You don't need to translate everything in one sitting, and you don't need
   to finish a whole language before your work counts -- every string you
   translate is used as soon as it's approved.

**A note on regional variants:** some languages are listed more than once --
for example, Spanish, plus Spanish (Mexico) and Spanish (Latin America).
Those regional variants automatically inherit the generic version's
translations, so you only need to translate a word or phrase for the
variant if it's actually said differently there. You don't need to
retranslate everything from scratch for a regional variant -- just the
handful of things that are genuinely different.

If you'd rather not use Crowdin, you're also welcome to open a pull request
directly against the [FS PBX GitHub repository](https://github.com/nemerald-voip/fspbx).

## For administrators: setting a tenant's language

Language is set per account (domain), not per user -- everyone signed into
the same account sees the same language. An administrator with access to
Domain Settings can set it there, under the domain-level "language" setting.

A few things worth knowing:

- A language only becomes available to choose once it's translated enough to
  be usable. Partially-translated languages exist and are being worked on,
  but aren't offered until they clear that bar -- so you won't accidentally
  switch a tenant into a half-finished interface.
- A language change takes effect the next time affected users log in, or
  after using the account's "Reload Settings" action -- the same as other
  account-level settings.
- Anything not yet translated for a given language simply falls back and
  displays in English (or in a closely related language, for regional
  variants) until the community finishes it.
