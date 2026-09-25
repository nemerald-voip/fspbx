---
id: language-settings
title: Language Settings and Sounds
slug: /getting-started/language-settings/
sidebar_position: 9
---

# Language Settings and Sounds

FS PBX has separate language settings for the web interface and the audio callers hear. This guide explains how to select an interface language, install call audio, and configure the system to use it.

| What you want to change | Where to configure it |
| --- | --- |
| Menus, buttons, and messages in the web interface | **System Settings** or **Account Settings → General → Language**. |
| System voice prompts, such as voicemail menus, spoken numbers, and dates | Install a FreeSWITCH sound pack, check speech-module and phrase support, and update **Advanced → Variables**. |
| Your business greeting, IVR welcome message, or personal voicemail greeting | Record, upload, or generate a greeting with AI in the desired language, then select it in the relevant feature. |
| Additional FS PBX feature prompts | See [FS PBX's additional sounds](#fs-pbxs-additional-sounds) below; some features still use fixed English recordings. |

Changing the web interface language does not install or select a sound pack. Installing sounds does not translate the web interface or existing recordings.

## Change the web interface language

The interface language is set per account. Everyone signed into the same account uses that account's language; it is not an individual user's preference.

### Set the system default

1. Open **System Settings → General**.
2. Under **Regional**, select **Language**.
3. Click **Save**.
4. Sign out and sign back in to load the new setting.

Accounts without their own language selection inherit this default. Accounts with an override keep their selected language. Editing the system default requires the `default_setting_edit` permission.

### Set one account's language

1. Switch to the account you want to configure.
2. Open **Account Settings → General**.
3. Under **Regional**, select **Language**.
4. Click **Save**, then sign out and sign back in.

Clear the account's **Language** selection and save to return to the system default. After any account language change, all users signed into that account need to sign out and sign back in to load the new setting.

### Missing languages or untranslated text

The language picker normally offers translations that meet the project's minimum completion level, currently 60%. A language already configured can remain visible even if its coverage drops below that level after new text is added.

Untranslated interface text falls back to English. Regional variants have independent translations, so the completeness of one Spanish variant, for example, does not determine another's. Available choices can change as translations are added in updates.

To contribute missing translations, see [Translations](/docs/additional-information/translations/).

## Audio support for the interface languages

The target language list is the same 32 locales registered for the frontend. Audio needs three separate pieces: recordings, phrase XML that orders the prompts, and a FreeSWITCH `mod_say_*` module for spoken numbers and dates. An interface translation does not supply these pieces.

The table below describes the shipped configuration and FreeSWITCH build list. **Included** means configuration is present, not that the language has passed call testing or that its recordings are installed. Non-English sound packs must be installed separately. Only the English speech module is enabled by default on a new installation; enable additional modules for the languages you use.

| Language (interface code) | Speech module in the build list | Phrase XML shipped |
| --- | --- | --- |
| English (`en-us`) | `mod_say_en` | Included (`en`) |
| Spanish (`es-es`) | `mod_say_es` | Included (`es`) |
| Spanish, Latin America (`es-419`) | `mod_say_es` | Shared (`es`) |
| Spanish, Mexico (`es-mx`) | `mod_say_es` | Shared (`es`) |
| Afrikaans (`af`) | Not available in the bundled source | Missing |
| Arabic (`ar`) | Not available in the bundled source | Included (`ar`) |
| Catalan (`ca`) | Not available in the bundled source | Missing |
| Chinese, Simplified (`zh-cn`) | `mod_say_zh` | Missing |
| Chinese, Traditional (`zh-tw`) | `mod_say_zh` | Missing |
| Czech (`cs`) | Not available in the bundled source | Missing |
| Danish (`da`) | Not available in the bundled source | Missing |
| Dutch (`nl`) | `mod_say_nl` | Included (`nl`) |
| Finnish (`fi`) | Not available in the bundled source | Missing |
| French (`fr`) | `mod_say_fr` | Included (`fr`) |
| German (`de`) | `mod_say_de` | Included (`de`) |
| Greek (`el`) | Not available in the bundled source | Missing |
| Hebrew (`he`) | `mod_say_he` | Included (`he`) |
| Hungarian (`hu`) | `mod_say_hu` | Missing |
| Italian (`it`) | `mod_say_it` | Included (`it`) |
| Japanese (`ja`) | `mod_say_ja` | Missing |
| Korean (`ko`) | Not available in the bundled source | Missing |
| Norwegian (`no`) | Not available in the bundled source | Missing |
| Polish (`pl`) | `mod_say_pl` | Missing |
| Portuguese (`pt-pt`) | `mod_say_pt` | Included (`pt`) |
| Portuguese, Brazil (`pt-br`) | `mod_say_pt` | Shared (`pt`) |
| Romanian (`ro`) | Not available in the bundled source | Included (`ro`) |
| Russian (`ru`) | `mod_say_ru` | Included (`ru`) |
| Serbian, Cyrillic (`sr`) | Not available in the bundled source | Missing |
| Swedish (`sv-se`) | `mod_say_sv` | Included (`sv`) |
| Turkish (`tr`) | Not available in the bundled source | Included (`tr`) |
| Ukrainian (`uk`) | Not available in the bundled source | Included (`uk`) |
| Vietnamese (`vi`) | Not available in the bundled source | Missing |

Regional interface codes are not FreeSWITCH language names. For example, Spanish uses `default_language=es`, while its dialect and voice must match the installed pack. Shared phrase XML does not guarantee correct regional wording or pronunciation; test your chosen pack and variant. Directory names are case-sensitive.

Where phrase XML or a speech module is missing, recordings alone cannot provide complete voicemail menus, spoken numbers, or dates. Those languages need additional FreeSWITCH language support. Recorded business greetings can still be used independently.

## Find a sound pack

There are two types of sound pack:

- **Official FreeSWITCH sound packs**, published on the [FreeSWITCH sound releases](https://github.com/freeswitch/freeswitch-sounds/releases) page. Examples include Russian Elena and French June.
- **Community and third-party sound packs**, provided by community projects or commercial suppliers. The German Piper recordings below are a community sound pack.

The table links to downloads for each language and voice.

| Language and region | Download page | Directory under `sounds/` |
| --- | --- | --- |
| English, United States | [Callie](https://github.com/freeswitch/freeswitch-sounds/releases/tag/en-us-callie-1.0.53), [Allison](https://github.com/freeswitch/freeswitch-sounds/releases/tag/en-us-allison-1.0.2) | `en/us/callie` or `en/us/allison` |
| Spanish, Argentina | [Mario](https://github.com/freeswitch/freeswitch-sounds/releases/tag/es-ar-mario-1.0.0) | `es/ar/mario` |
| French, Canada | [June](https://github.com/freeswitch/freeswitch-sounds/releases/tag/fr-ca-june-1.0.51) | `fr/ca/june` |
| German, Germany | [Community Piper recordings](https://github.com/iexos/freeswitch-sounds-tts/releases/tag/v1), with two voice archives | For example, `de/de/piper`; use a separate voice directory for each voice you install |
| Portuguese, Brazil | [Karina](https://github.com/freeswitch/freeswitch-sounds/releases/tag/pt-BR-karina-1.0.51) | `pt/BR/karina` |
| Russian, Russia | [Elena](https://github.com/freeswitch/freeswitch-sounds/releases/tag/ru-RU-elena-1.0.51), [Kirill](https://github.com/freeswitch/freeswitch-sounds/releases/tag/ru-RU-kirill-1.0.0), [Vika](https://github.com/freeswitch/freeswitch-sounds/releases/tag/ru-RU-vika-1.0.0) | `ru/RU/elena`, `ru/RU/kirill`, or `ru/RU/vika` |
| Swedish, Sweden | [Jakob](https://github.com/freeswitch/freeswitch-sounds/releases/tag/sv-se-jakob-1.0.50) | `sv/se/jakob` |
| Chinese, mainland China | [Sinmei](https://github.com/freeswitch/freeswitch-sounds/releases/tag/zh-cn-sinmei-1.0.51) | `zh/cn/sinmei`; phrase XML is not shipped in FS PBX |

Choose the region as well as the language: an Argentinian Spanish pack is not a Spain or Mexico recording, and a Canadian French pack uses Canadian pronunciation. Preserve directory capitalization, including `BR` and `RU`.

For commercial third-party sound packs, [Westany's FreeSWITCH catalog](https://www.westany.com/freeswitch/) lists German, Italian, Portuguese, Polish, Arabic, Mexican Spanish, and several English variants. Confirm the region, prompt coverage, and required FreeSWITCH configuration with the supplier.

These links are sources for recordings. Check the [support table](#audio-support-for-the-interface-languages) for the speech module and phrase XML your language also needs. A downloadable pack does not supply missing language support automatically.

If your language or dialect is not listed, obtain or record a compatible FreeSWITCH prompt set. It must use the filenames and folders expected by the language's phrase XML and speech module. A business greeting alone is not a complete system sound pack.

## Enable a speech module

The build list includes the 14 available speech modules for these target languages. Non-English modules are built for use when needed, with autoload disabled by default. Existing installations retain their saved module choices.

Enable the module for your chosen language:

1. Open **Advanced → Modules** and click **Refresh** to discover installed module files.
2. Select **All** under Categories and search for the module listed in the support table, such as `mod_say_fr` for French. Keep the **Runtime** and **Autoload** filters on **Any status** so disabled modules are visible.
3. Click **Autoload disabled** on that module and confirm. The button should change to **Autoload enabled**, so FreeSWITCH loads the module on future starts.
4. Click **Start** and confirm to load it now.
5. Click **Refresh** and verify that its status is **Running**.

Autoload controls future starts; **Start** controls the running service. Enabling autoload or reloading XML alone does not start a stopped module. A FreeSWITCH restart is not required to start an installed speech module.

Repeat these steps for each language you need, using the module names in the table. Install the corresponding sound pack and check phrase support before testing calls.

You can also check a module over SSH. Replace `mod_say_fr` with your selected module:

```bash
fs_cli -x "module_exists mod_say_fr"
```

If it returns `false`, the module is not running. Use the steps below if you cannot find it on the Modules page.

### Add modules missing from the list

Additional language modules are disabled by default. After updating FreeSWITCH, follow these steps to find and enable the ones you need:

1. Clear the search, select **All** under Categories, and set **Runtime** and **Autoload** to **Any status**.
2. Click **Refresh**, then search by the full module name, such as `mod_say_ru`.
3. If you find it, enable **Autoload** and click **Start** as described above.

If it is still missing, check the installed binaries over SSH:

```bash
fs_cli -x "global_getvar mod_dir"
ls -1 /usr/lib/freeswitch/mod/mod_say_*.so
```

Use the directory returned by the first command if it differs from `/usr/lib/freeswitch/mod`.

| What you find | Next step |
| --- | --- |
| The required `.so` file exists | In **Advanced → Default Settings**, check the enabled `switch` / `mod` / `dir` setting. It must point to the installed module directory, and the web application must be able to read it. Return to **Modules** and click **Refresh**. |
| Only `mod_say_en.so` exists, or the required module is absent | Update FS PBX first so the installer has the current module build list, then follow [Upgrade FreeSWITCH](/docs/freeswitch-upgrade/). The current FS PBX build includes the speech modules listed above, even when their autoload setting is disabled. |
| The language has no speech module in the support table | Reinstalling cannot add a module that is not available in the bundled source. That language needs additional FreeSWITCH support; installing recordings alone is not enough. |

Complete the FreeSWITCH upgrade guide's planned restart before loading newly built modules. Then refresh the Modules page and enable the languages you need. Adding a module row manually, changing a language setting, or reloading XML does not install its binary.

## Install and select a call audio language

Use the following steps for any compatible sound pack. First identify its **language**, **dialect or region**, and **voice directory** from the downloaded files. Use those same three values throughout installation and configuration.

These are server-level sound defaults. An account's interface language does not give its calls a separate audio language. Existing dialplan or feature-specific language and sound-path overrides must also be checked when they are present.

You need SSH access with permission to install files, access to **Advanced → Variables** and **Status → SIP Status**, and a phone for test calls. Keep the original variable values and a backup of any XML file you edit. On a redundant installation, install the sounds and apply local XML changes on every server that can handle calls.

### 1. Obtain the sound files

Choose a language and voice from the [download sources](#find-a-sound-pack).

#### Official FreeSWITCH sound packs

On the release page, expand **Assets** and download the **four audio archives ending in `.tar.gz`**, with `8000`, `16000`, `32000`, and `48000` in their names. Choose the same language, voice, and release version for all four.

| File listed under Assets | What to do |
| --- | --- |
| `freeswitch-sounds-…-8000-….tar.gz` | Download this audio archive. |
| `freeswitch-sounds-…-16000-….tar.gz` | Download this audio archive. |
| `freeswitch-sounds-…-32000-….tar.gz` | Download this audio archive. |
| `freeswitch-sounds-…-48000-….tar.gz` | Download this audio archive. |
| `.tar.gz.md5`, `.tar.gz.sha1`, or `.tar.gz.sha256` | Optional checksums for verifying downloads. These contain no audio and are not extracted or installed. |
| `.msi` | Windows installer. Skip it for FS PBX. |
| **Source code (zip)** or **Source code (tar.gz)** | Skip these for sound-pack installation. |

The numbers are **sample rates in hertz**: `8000` means 8 kHz, `16000` means 16 kHz, and so on. They are versions of the same prompts for different call audio formats. Install all four rates when available so FreeSWITCH can use the appropriate version for a call; you do not need to select a rate in FS PBX.

#### Example: Russian Elena

On the [Russian Elena 1.0.51 release page](https://github.com/freeswitch/freeswitch-sounds/releases/tag/ru-RU-elena-1.0.51), the four audio downloads are:

- [freeswitch-sounds-ru-RU-elena-8000-1.0.51.tar.gz](https://github.com/freeswitch/freeswitch-sounds/releases/download/ru-RU-elena-1.0.51/freeswitch-sounds-ru-RU-elena-8000-1.0.51.tar.gz)
- [freeswitch-sounds-ru-RU-elena-16000-1.0.51.tar.gz](https://github.com/freeswitch/freeswitch-sounds/releases/download/ru-RU-elena-1.0.51/freeswitch-sounds-ru-RU-elena-16000-1.0.51.tar.gz)
- [freeswitch-sounds-ru-RU-elena-32000-1.0.51.tar.gz](https://github.com/freeswitch/freeswitch-sounds/releases/download/ru-RU-elena-1.0.51/freeswitch-sounds-ru-RU-elena-32000-1.0.51.tar.gz)
- [freeswitch-sounds-ru-RU-elena-48000-1.0.51.tar.gz](https://github.com/freeswitch/freeswitch-sounds/releases/download/ru-RU-elena-1.0.51/freeswitch-sounds-ru-RU-elena-48000-1.0.51.tar.gz)

To download and extract all four directly on your server, connect over SSH and run:

```bash
mkdir -p /tmp/freeswitch-language
(
  cd /tmp/freeswitch-language || exit 1
  for rate in 8000 16000 32000 48000; do
    archive="freeswitch-sounds-ru-RU-elena-${rate}-1.0.51.tar.gz"
    curl -fLO "https://github.com/freeswitch/freeswitch-sounds/releases/download/ru-RU-elena-1.0.51/$archive" || exit 1
    tar -xzf "$archive" || exit 1
  done
)
```

All four archives extract into the same voice folder, `/tmp/freeswitch-language/ru/RU/elena`. Each adds its own sample-rate folders. For example:

```text
/tmp/freeswitch-language/ru/RU/elena/
├── digits/
│   ├── 8000/
│   ├── 16000/
│   ├── 32000/
│   └── 48000/
├── ivr/
├── time/
└── voicemail/
```

The other prompt folders also contain sample-rate subfolders. Keep this structure intact and continue to **Step 2** to copy the voice into the sounds directory.

For official FreeSWITCH sound packs in other languages or voices, download their audio archives and extract each into the same temporary directory:

```bash
mkdir -p /tmp/freeswitch-language
tar -xzf /path/to/sound-pack.tar.gz -C /tmp/freeswitch-language
```

#### Community and third-party sound packs

Download the archive for your chosen language and voice, then follow the supplier's extraction instructions. These packs may combine several sample rates in one archive and use different folder layouts. Find the extracted voice folder containing categories such as `ivr`, `voicemail`, and `digits`, then use the general installation commands in **Step 2**.

The installed voice belongs under:

```text
/usr/share/freeswitch/sounds/<language>/<dialect>/<voice>/
```

The voice name identifies the directory. It does not have to be `callie`, but the directory name and all settings that reference it must agree. Packs generated with text-to-speech tools are still prerecorded audio; playing them does not require a live text-to-speech engine.

### 2. Install real files, including any linked prompts

Some packs contain symbolic links for alternate prompt filenames. FS PBX's sound selectors require regular files, so copy the linked audio into place when installing the pack.

For **Russian Elena**, after extracting the archives as shown above, run:

```bash
sudo install -d /usr/share/freeswitch/sounds/ru/RU/elena
sudo cp -RL /tmp/freeswitch-language/ru/RU/elena/. /usr/share/freeswitch/sounds/ru/RU/elena/
sudo chown -R www-data:www-data /usr/share/freeswitch/sounds/ru/RU/elena
sudo chmod -R u=rwX,go=rX /usr/share/freeswitch/sounds/ru/RU/elena
```

This copies Elena's prompt folders and all extracted sample rates into `/usr/share/freeswitch/sounds/ru/RU/elena/`. Keep `RU` uppercase. Continue to **Step 3** to select the voice.

**General installation commands:** use these for any official FreeSWITCH, community, or third-party sound pack. Replace `LANGUAGE`, `DIALECT`, and `VOICE` with your pack's directory names. Set `pack_source` to the extracted **voice folder**, which directly contains categories such as `ivr`, `voicemail`, and `digits`. Do not select a parent folder that contains the whole language/dialect/voice tree.

```bash
pack_language='LANGUAGE'
pack_dialect='DIALECT'
pack_voice='VOICE'
pack_source='/path/to/extracted/voice'
pack_target="/usr/share/freeswitch/sounds/$pack_language/$pack_dialect/$pack_voice"

sudo install -d "$pack_target"
sudo cp -RL "$pack_source/." "$pack_target/"
sudo chown -R www-data:www-data "$pack_target"
sudo chmod -R u=rwX,go=rX "$pack_target"
```

The `-L` option copies the contents of linked files. If copying reports a broken link, obtain the missing target before continuing. Use the service ownership appropriate to your installation if it differs from the standard FS PBX setup. Both FreeSWITCH and the web application must be able to read the files and traverse their directories.

Keep the existing English sounds installed. Place the new pack alongside them, preserving its internal layout; do not flatten sample-rate directories or rename individual prompts.

### 3. Set the FreeSWITCH variables

Open **Advanced → Variables**, find each of these variables, and use the directory names from your installation:

| Variable | Value |
| --- | --- |
| `default_language` | The language directory, such as `fr` or `ru` |
| `default_dialect` | The dialect directory, such as `ca` or `RU` |
| `default_voice` | The voice directory, such as `june` or `elena` |
| `sound_prefix` | `$${sounds_dir}/<language>/<dialect>/<voice>`, with the placeholders replaced by those directory names |

Keep the variables enabled and keep `$${sounds_dir}` exactly as shown in the prefix. If your installation uses a different sounds root, use that directory in the copy commands and confirm that `sounds_dir` points to it.

**German example:** if you installed a voice under `/usr/share/freeswitch/sounds/de/de/piper`, set `default_language` to `de`, `default_dialect` to `de`, `default_voice` to `piper`, and `sound_prefix` to `$${sounds_dir}/de/de/piper`. The same mapping applies to every other pack in the download table.

Update all four values. Changing only `default_language`, or leaving `sound_prefix` pointed at `en/us/callie`, leaves inconsistent sound paths. `default_country` is a separate setting and is not the selector for these recordings.

The current Variables page writes the configuration and requests an XML reload when you save. If it reports a synchronization failure, correct that error and use **Sync** before testing.

### 4. Check language phrase settings

Current FS PBX voicemail playback uses the selected `default_language`, `default_dialect`, and `default_voice` for ordinary prompts, phrase macros, and spoken numbers. It restores the previous call sound settings when voicemail finishes. A language's phrase XML and speech module are still required.

Other phrase-based features may use the sound prefix defined in FreeSWITCH's language XML. For those features, make sure the XML prefix points to the installed voice directory.

Find the language's XML file under:

```text
/etc/freeswitch/languages/<language>/
```

Use your configured FreeSWITCH configuration directory if it differs from `/etc/freeswitch`. Back up the file, then find the `sound-prefix` attribute on the opening `<language>` element. Set it to the same voice folder you selected on the Variables page, replacing the placeholders below:

```xml
sound-prefix="$${sounds_dir}/<language>/<dialect>/<voice>"
```

Keep the rest of the XML, including its phrase includes, intact. The XML attribute uses a hyphen (`sound-prefix`); the variable on the Variables page uses an underscore (`sound_prefix`). Changing `tts-voice` does not select these prerecorded WAV files.

#### Create a missing language file

If `/etc/freeswitch/languages/LANGUAGE/LANGUAGE.xml` is missing, create it using the template below. Replace `LANGUAGE` with the FreeSWITCH language code, such as `fr`. Replace `DIALECT` and `VOICE` with your sound pack's directory names, such as `ca` and `june`.

Create the directory and open the new file over SSH:

```bash
sudo mkdir -p /etc/freeswitch/languages/LANGUAGE
sudo nano /etc/freeswitch/languages/LANGUAGE/LANGUAGE.xml
```

Paste this XML, replace the placeholders, and save:

```xml
<include>
  <language name="LANGUAGE" say-module="LANGUAGE" sound-prefix="$${sounds_dir}/LANGUAGE/DIALECT/VOICE">
    <phrases>
      <macros>
        <X-PRE-PROCESS cmd="include" data="vm/*.xml"/>
        <X-PRE-PROCESS cmd="include" data="dir/sounds.xml"/>
        <X-PRE-PROCESS cmd="include" data="ivr/*.xml"/>
      </macros>
    </phrases>
  </language>
</include>
```

This template requires your language's phrase definitions in the `vm`, `dir`, and `ivr` subfolders. If those files are also missing, obtain compatible phrase definitions for your language from your sound-pack supplier.

Open `/etc/freeswitch/freeswitch.xml` and find `<section name="languages">`. If your language is not listed, add this line inside that section, replacing `LANGUAGE` with the same code:

```xml
<X-PRE-PROCESS cmd="include" data="languages/LANGUAGE/*.xml"/>
```

Save the file, then reload XML using the next step.

### 5. Reload and verify

1. Go to **Status → SIP Status**.
2. Click **Flush Cache**, then **Reload XML**.
3. Start new calls for testing.

After editing the language XML, you can also request the XML reload over SSH:

```bash
fs_cli -x "reloadxml"
```

To check the values currently loaded by FreeSWITCH:

```bash
fs_cli -x "global_getvar default_language"
fs_cli -x "global_getvar default_dialect"
fs_cli -x "global_getvar default_voice"
fs_cli -x "global_getvar sound_prefix"
```

The three directory values should match your chosen pack, and the prefix should resolve to the installed voice folder. These checks show the global values; an individual call or the language XML can still select a different path.

## Test what callers actually hear

Test both an IVR and voicemail. A successful test in one feature does not prove that all sound paths are correct.

1. Create or use a test **Virtual Receptionist** with at least one menu option. Dial its extension and enter an unassigned option. Its standard invalid-entry prompt should play in the new language.
2. Call an extension and let it reach voicemail. Check the standard recording instructions as well as the mailbox greeting.
3. From an extension, dial `*97` to check its voicemail, or `*98` to enter another mailbox's ID and password. Check the menus and spoken numbers. These are the default [feature codes](/docs/additional-information/feature-codes/); use your local codes if customized.
4. Test an incoming call and any other features your users rely on.

Watch `fs_cli` during the calls. An **Error Opening File** message shows the path FreeSWITCH actually tried to use. Compare its language, dialect, voice, filename, and any rate directory with the installed files.

Your own IVR greetings and voicemail greetings are separate from system sound packs. Record, upload, or generate them with AI in the desired language.

## FS PBX's additional sounds

FS PBX ships recordings beyond the standard FreeSWITCH sound packs. Their source files are in:

```text
/var/www/fspbx/resources/sounds
```

Current English feature prompts include recordings under `en/us/alloy` for wake-up calls, hotel room status, recording menus, voicemail escalation, emergency notifications, and call-center features.

Some scripts explicitly select these English paths. Installing another FreeSWITCH sound pack or changing the global variables therefore does not translate every FS PBX feature. Adding translated files alone also does not make those scripts select them automatically; the affected feature needs language-aware playback support.

When contributing translated audio, use the shipped files as the inventory, preserve their relative filenames, and identify the features they belong to so the matching playback changes can be reviewed. Keep custom business greetings in the application's recording or greeting workflow.

## Troubleshooting

| Symptom | What to check |
| --- | --- |
| The interface stays in the old language | Save the setting, sign out and sign back in, and check whether the account has its own override. |
| The desired interface language is missing | Its translation may not yet meet the completion threshold. See the [translation guide](/docs/additional-information/translations/). |
| The interface changes but calls remain in English | Configure call audio separately using the sound-pack steps above. |
| IVR creation or editing fails after installing sounds | Check the [application log](/docs/troubleshooting/retrieving-logs/) for unsupported symbolic links, unreadable directories, or missing files. Install real copies of linked prompts. |
| Prompts play, but spoken numbers or dates fail | Verify that the language's `mod_say_*` module is installed and running, and that its phrase XML and required audio files exist. |
| A prompt is silent, missing, or only a beep plays | Watch `fs_cli` for the attempted filename. Check pack completeness, folder layout, permissions, and the selected voice. Missing audio does not necessarily fall back to English. |
| Only some feature prompts remain English | Check for custom recordings, an explicit sound-path override, or FS PBX's additional English prompts. |

To return to the previous audio language, restore the variable values and XML prefix you saved before the change, then flush the cache, reload XML, and test new calls. The usual English defaults are `en`, `us`, `callie`, and `$${sounds_dir}/en/us/callie`.
