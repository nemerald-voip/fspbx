---
id: contact-center-hold-audio-sequences
title: Hold Audio Sequences
slug: /configuration/contact-center/hold-audio-sequences/
sidebar_position: 5
---

# Hold Audio Sequences

Each Contact Center queue has two hold-audio modes under **Settings → Caller Experience**:

* **Continuous stream** keeps the existing behavior: callers join audio already playing. The callback offer uses the delay in **Callbacks**. The queue's **Music on Hold** selection applies to this mode, and the field is shown only when it is selected.
* **Audio sequence** starts at the first step for each new queue visit. Audio repeats while the caller waits. A callback offer can be placed between clips.

Existing queues remain on continuous playback. This feature does not change Basic Queues.

## Build a sequence

1. Select **Audio sequence**.
2. Select **Add audio**, then choose an account recording or an individual file from a local Music on Hold library. **Upload clip** adds a reusable account recording.
3. Leave **Duration limit** blank for the whole clip, or enter the number of seconds to play from its beginning. The limit cannot exceed the source duration.
4. Arrange the steps with the **Move up**, **Move down**, and **Remove** buttons on each step. They are icon buttons and work with the keyboard.
5. Optionally select **Add callback offer**. The step names the digit callers press to accept, which comes from the **Callbacks** tab. This release supports one callback step per sequence.
6. Select **Preview sequence** to hear the clips, callback prompt, and configured acceptance digit, or use a single step's play button to hear just that clip. The step playing is highlighted. Preview does not create a callback.
7. **Save**. The status below the steps reads *Preparing*, *Ready*, *Failed*, or *Not prepared*; select **Refresh preparation status** to check it again.

For example:

`Music 20s → Commercial A → Callback offer → Music 20s → Commercial B → Music 60s`

Each step shows when it plays, for example `0:00 – 0:20`, and whether the clip is trimmed. The summary above the steps shows the step count, the total audio time, and the callback's position. There can be up to 40 steps and 30 minutes of audio. Queue announcements pause the sequence and resume where it stopped, so elapsed waiting time can be longer than the displayed audio time.

## Callback behavior

Enable and configure callbacks in the queue's **Callbacks** tab. The step uses the existing acceptance digit, availability checks, cutoff, number confirmation, priority, and retry settings.

In sequence mode, **Offer a callback after** is inactive; its value is retained for continuous mode. Without a callback step, callers receive no callback offer, even if callbacks are enabled.

The offer is attempted once per queue visit. If callbacks are unavailable at that moment, it is skipped. Later loops skip the callback step too. If callback capture returns the caller to the queue, playback resumes after the callback step and retains accrued priority and the original queue deadline.

Agents can connect, and callers can use exit keys or hang up, during any audio step or announcement.

## Preparing and changing audio

Saving prepares the files in the background. Calls do not wait for conversion. Until preparation succeeds, the previous prepared revision remains available; if there is none, callers hear the default music on hold, `local_stream://default`. The queue's own **Music on Hold** selection is kept but is not used in sequence mode.

Saving again also checks whether a selected source recording has changed. Unchanged audio segments are reused. Removing or editing the original recording does not change an already prepared revision. Save the sequence to prepare the new contents.

A waiting caller keeps the revision selected on entry. Editing the sequence or switching back to **Continuous stream** affects new queue entries. Published revisions are retained in this release so existing calls and peer servers can finish using them.

On redundant servers, the audio files must arrive through recording-file replication. A server with an incomplete new revision uses a complete previous revision when available, otherwise the default music on hold. Callback offers are suppressed during either fallback. The preparation status shows when the active files are missing locally.

## Installation and rollout

Install the matching main application and Contact Center module updates. The schema migration is:

```text
2026_09_22_000001_create_contact_center_audio_configurations_table.php
```

Run this migration separately on each database using your normal deployment procedure. Do not run migrations through `app:update`. Include `contact_center_audio_configurations` in the existing ordered application replication stream and refresh the logical-replication subscriptions before enabling sequence mode.

Restart the existing Horizon workers through your normal deployment procedure after updating job code. Audio preparation uses the existing `contact-center-ha` queue. The module's standard script links must include `contact_center_audio_sequence.lua`; `contact-center:install-ha` checks the runtime links.

Files live in each account's recordings directory under `contact-center-audio/{queue UUID}`, outside call-recording archives. Replicate this directory and its manifests. Both nodes need read access for FreeSWITCH and write access for the application worker. Keep the existing recordings disk and Lua recordings root aligned.

Pilot one queue, confirm readiness on every serving node, and make test calls before broader rollout. Save each queue you enable so its generated dialplan contains the new settings. Switching back to continuous playback is the rollback for new callers.

Audio uses local PCM WAV files and Linux's shared filesystem cache. Redis holds only small metadata and the existing queue-position cache. No audio bytes are stored in Redis. Compilation is limited to one job per node, with one FFmpeg thread and bounded subprocess execution. A local maintenance task cleans abandoned temporary builds and briefly warms newly replicated audio; it never deletes published revisions.

Capacity depends on the server, codecs, other calls, and enabled announcements. Validate the desired occupancy on your deployment hardware; the development lab results do not guarantee 500 waiting callers on every server.
