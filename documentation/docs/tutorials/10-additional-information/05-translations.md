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

## For administrators: switching the language

Language is set per account, not per user -- everyone signed into the same
account sees the same language. There are two levels:

### System-wide default (all accounts)

**System Settings → General → Language** sets the default language for the
whole system. Every account that hasn't chosen its own language inherits it.
This is the setting to change if you want to switch the language of the
system as a whole. (Changing it needs the default-settings edit permission.)

### Per-account (one account)

**Account Settings → General → Language** overrides the system default for
that one account. Leave it empty to inherit the system-wide default; pick a
language to override it just for this account. Time Zone works the same way,
right next to it.

### Things worth knowing

- A language only appears in these dropdowns once it's translated enough to
  be usable. Partially-translated languages still exist and are being worked
  on, but aren't offered until they clear that bar -- so you won't
  accidentally switch into a half-finished interface.
- A language change takes effect the next time affected users log in, or
  after a "Reload Settings" action -- the same as other settings, not
  mid-session.
- Anything not yet translated for the chosen language falls back to English
  (or to a closely related language, for regional variants) until the
  community finishes it.
