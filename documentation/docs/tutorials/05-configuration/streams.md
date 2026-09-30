---
title: Streams
---

Use **Streams** to save network audio sources for Music on Hold. The page shows the current account's streams and shared global streams. Global changes require `stream_all` in addition to the relevant create, edit, or delete permission.

## Enter the location

1. Obtain a **direct MP3 audio endpoint** from the provider. A station homepage, embedded player, or login page is not an audio source. Open `.m3u` or `.pls` playlists as text and take the direct MP3 URL inside. HLS (`.m3u8`), AAC, Ogg, and Opus are not supported by this MP3 playback path.
2. Replace the URL scheme using this table. Preserve the hostname, explicit port, path, and query string. Encode spaces as `%20`. Do not prepend `shout://` to an entire HTTP URL.

| Provider URL | Location to save |
| --- | --- |
| `http://radio.example.com:8000/live.mp3` | `shout://radio.example.com:8000/live.mp3` |
| `https://radio.example.com/live` | `shouts://radio.example.com/live` |

These are examples, not working stations. A `.mp3` suffix is optional; the returned audio must actually be MP3. Use the final audio endpoint rather than a redirect or a URL that depends on browser cookies.

3. Enter a name, leave **Enabled** on, and save. Administrators with global access can select **Global** to make the stream available across accounts.
4. Select the stream in the destination's **Music on Hold** field and save that destination. Saving a stream alone does not assign it to any calls.

Existing custom FreeSWITCH locations can be kept unchanged when editing other fields. New or changed locations are checked for the documented `shout://` / `shouts://` format. Syntax validation cannot establish whether a remote server actually returns playable audio.

## Prepare FreeSWITCH and verify playback

On the **FreeSWITCH Modules** page, start **mod_shout** and enable its automatic loading after restart. If it is unavailable, the server administrator must install the matching module package for that FreeSWITCH installation.

The FreeSWITCH server needs DNS resolution and outbound connectivity to the audio host and port. A stream playing on your laptop does not establish that the PBX can reach it. On redundant servers, check each server that can handle calls.

An administrator can check the loaded module from the PBX shell:

```bash
fs_cli -x 'module_exists mod_shout'
```

The result must be `true`. To inspect a public, credential-free endpoint from the PBX, substitute its ordinary HTTP/HTTPS URL below:

```bash
curl --connect-timeout 5 --max-time 10 --max-filesize 2097152 --dump-header /tmp/stream-headers.txt --output /tmp/stream-sample.mp3 'https://radio.example.com/live'
ffprobe -v error -show_entries stream=codec_name,sample_rate,channels -of default=noprint_wrappers=1 /tmp/stream-sample.mp3
```

The bounded download can end with curl's timeout code for an endless live stream. Check that audio bytes were received; timeout alone is not success. Inspect the response headers for errors or redirects. `ffprobe` should identify `codec_name=mp3` and valid sample rate/channels. A header claiming audio content does not prove the bytes are valid MP3. Use the provider's final audio URL if the response redirects.

Finally, make a test call through the configured destination and place the call on hold. Listen for audio. If silent, inspect the FreeSWITCH log for stream connection errors, decoder errors, or an unavailable file format/module. **Listen in browser** is a separate browser preview; HTTPS pages may block HTTP audio, and browser playback does not test FreeSWITCH.

## Changes and existing destinations

FS PBX saves the location string into destination settings when a stream is selected. Renaming, changing, disabling, or deleting the catalog entry does not rewrite locations already stored in phone numbers, queues, or other destinations. After changing a location, reselect the stream and save each destination that uses it. Disabling removes the stream from new selections. Deleting removes the catalog entry. Copy creates a separate stream in the current account.

Implementation references: `getMusicOnHoldCollection()` and the other sound collections in `app/helpers.php` return `stream_location` as the selection value; `PhoneNumbersController` saves that value in `destination_hold_music`. FreeSWITCH's [mod_shout source](https://github.com/signalwire/freeswitch/blob/master/src/mod/formats/mod_shout/mod_shout.c) registers `shout` and `shouts`, translates them to HTTP and HTTPS in `shout_file_open()`, and decodes received audio with mpg123.
