---
id: scheduled-announcements
title: Scheduled Announcements
slug: /configuration/scheduled-announcements/
sidebar_position: 1
---

# Scheduled Announcements

Scheduled Announcements let FS PBX play a recording or sound to selected extensions at specific days and times.

They can be used for:

* school bells
* shift-change tones
* safety reminders
* daily announcements
* lunch, break, or closing notices
* any other recurring audio that should play automatically

A scheduled announcement is built around a **schedule**. The schedule contains the recording, the extensions that should hear it, the days and times when it should play, and any dates when it should be skipped.

## Before you start

Make sure you have:

* permission to open **Scheduled Announcements**
* at least one extension that should receive the announcement
* a recording or sound to play

## Enable the scheduled job

Scheduled Announcements will not run automatically until the scheduled job is enabled.

Go to:

```text
Advanced > Default Settings
```

Find the setting:

| Category | Setting Name | Type | Value |
| -------- | ------------ | ---- | ----- |
| `scheduled_jobs` | `scheduled_announcements` | `boolean` | `true` |

Open the setting and make sure:

* the setting is enabled
* the value is set to `true`

After saving the setting, FS PBX can process scheduled announcements every minute.

### Optional scheduled announcement settings

Most systems can use the defaults. These settings are available for timing and redundant-server behavior.

| Category | Setting Name | Default | Description |
| -------- | ------------ | ------- | ----------- |
| `scheduled_jobs` | `scheduled_announcements_active_fqdn` | blank | Optional active-node FQDN override. When blank, FS PBX uses `APP_URL`. |
| `scheduled_jobs` | `scheduled_announcements_authoritative_zone` | blank | Optional DNS zone override for authoritative DNS lookup discovery. |
| `scheduled_jobs` | `scheduled_announcements_node_ips` | blank | Optional comma-separated public IP override for this server. When blank, FS PBX tries to discover local and external IPs. |
| `scheduled_jobs` | `scheduled_announcements_dns_timeout_ms` | `800` | DNS active-node guard timeout in milliseconds. |
| `scheduled_jobs` | `scheduled_announcements_fire_window_seconds` | `15` | Maximum number of seconds after the scheduled time that an announcement may still run. |

For a standard single-server install, the most important setting is:

```text
scheduled_jobs > scheduled_announcements = true
```

## Create a schedule

Go to:

```text
Applications > Scheduled Announcements
```

Click **Create**.

## Schedule tab

The **Schedule** tab contains the main settings for the announcement.

### Enabled

Use **Enabled** as the master switch for the schedule.

When the schedule is off, none of its announcement times will run.

### Name

Enter a name that explains what this schedule is for.

Examples:

* School Bells
* Warehouse Break Reminder
* Daily Closing Announcement
* Morning Safety Message

### Time Zone

Choose the time zone used for the announcement times.

This is important when the PBX serves users in more than one time zone. The times in the schedule are interpreted using this selected time zone.

### Description

Use the description to explain the purpose of the schedule.

This is optional, but it helps other administrators understand why the schedule exists.

### Recording or Sound

Choose the recording or sound that should play for this schedule.

Every announcement time in the schedule plays the same recording.

You can also use the recording controls next to the field to:

* play the selected recording
* download it
* rename it
* delete it
* add a new recording

When adding a new recording, you can upload an audio file or record one from a phone.

To record from a phone:

1. Dial `*732` from your phone.
2. Enter any extension number when prompted and press `#`.
3. Follow the prompts to record your greeting.

After the recording is saved, select it in the schedule.

### Extensions

Select the extensions that should hear the announcement.

Search for an extension, add it to the list, and repeat until all target extensions are selected.

Only the selected extensions are called when the announcement plays.

### Busy Extensions

This controls what happens if one of the selected extensions is already on a call.

| Option | What it does |
| ------ | ------------ |
| **Skip** | Leaves busy extensions alone and plays the announcement only to available extensions. |
| **Force** | Sends the announcement to selected extensions even if the phone is already on a call. |

Use **Skip** when you do not want announcements to interrupt active calls.

Use **Force** when the announcement is more important than the current call state, such as for urgent tones or required notices.

### Starts and Ends

Use **Starts** and **Ends** if the schedule should only run during a specific date range.

Both fields are optional.

Examples:

* Leave both blank to run indefinitely.
* Set **Starts** for a schedule that should begin next semester.
* Set **Ends** for a temporary announcement campaign.

## Events

Events are the actual announcement times.

Each event row has:

* **Time**
* **Days**

Example:

| Time | Days |
| ---- | ---- |
| 08:00 AM | Monday through Friday |
| 12:00 PM | Monday through Friday |
| 03:15 PM | Monday through Friday |

Each row means:

> Play this schedule's recording at this time on these days.

You do not need to enable each event separately. If the row exists and the schedule is enabled, the event can run.

## Exclusions tab

Use **Exclusions** for dates when the schedule should not play.

Each exclusion has:

* **Date**
* **Comment**

Examples:

| Date | Comment |
| ---- | ------- |
| 11/26/2026 | Thanksgiving |
| 12/25/2026 | Christmas |
| 01/01/2027 | New Year's Day |

On an excluded date, the entire schedule is skipped for that day.

Exclusions are useful for:

* holidays
* school closures
* maintenance days
* special events
* days when bells or announcements should be silent

## Save and test

After entering the schedule details, click **Save Schedule**.

To test a schedule:

1. Create a short test event a few minutes in the future.
2. Select one or two test extensions.
3. Save the schedule.
4. Wait for the event time.
5. Check the **Runs** tab to confirm what happened.

## Runs tab

The **Runs** tab shows recent scheduler activity.

Use it to confirm whether an announcement:

* executed successfully
* was skipped because the server was standby
* was skipped because active server status was unknown
* was skipped because all selected extensions were busy
* was missed because it was discovered outside the allowed fire window
* failed because of a playback or FreeSWITCH error

The run log can also show the server that claimed or executed the announcement, the FreeSWITCH command response, and any error text.

## Timing behavior

Scheduled Announcements are designed for time-sensitive audio.

If an announcement is scheduled for 10:00 AM, it should play at 10:00 AM. If the system discovers the event too late, FS PBX logs it as missed instead of playing it late.

This helps prevent late bells or announcements from playing after their intended time has passed.

## Redundant server behavior

In redundant primary/standby deployments, each server may run the scheduler, but only the active server should execute announcements.

FS PBX checks the configured active-node DNS record before executing. If the server cannot confirm that it is active, the announcement is skipped rather than risking duplicate playback.

This behavior favors:

* no duplicate announcements
* no late announcements
* clear run logs when an announcement is skipped

## Troubleshooting

### The announcement did not play

Check:

* the schedule is enabled
* the scheduled job is enabled by an administrator
* the event time and selected days are correct
* the schedule time zone is correct
* the current date is not listed in Exclusions
* at least one extension is selected
* the selected recording exists and can be played
* the Runs tab for skipped, missed, or failed entries

### The beginning of the recording sounds clipped

Scheduled Announcements add a short silence lead-in before the recording to help phones open their speaker path before audio starts.

If the audio is still clipped, try adding a little silence to the beginning of the recording itself.

### Busy phones are being interrupted

Edit the schedule and set **Busy Extensions** to **Skip**.

### An announcement should interrupt busy phones

Edit the schedule and set **Busy Extensions** to **Force**.

## Summary

Use Scheduled Announcements when FS PBX should automatically play the same recording to selected extensions at specific days and times.

Create one schedule for each group of announcements that share the same recording and target extensions. Add events for the times it should play, and add exclusions for dates when it should stay silent.
