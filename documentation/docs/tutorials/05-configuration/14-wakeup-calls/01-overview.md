---
id: wakeup-calls
title: Wakeup Calls
slug: /configuration/wakeup-calls/
sidebar_position: 1
---

# Wakeup Calls

Wakeup Calls let FS PBX call an extension at a scheduled date and time.

They are commonly used for:

* hotel room wake-up calls
* reminders for staff extensions
* scheduled call alerts
* recurring daily wake-up calls

When the wake-up time arrives, FS PBX calls the selected extension. The call can then be confirmed or snoozed from the phone prompts.

## Before you start

Make sure you have:

* permission to open **Wakeup Calls**
* an extension that should receive the wake-up call
* the scheduled job enabled

Users with self-record access can manage wake-up calls for their own extension. Administrators with all-record access can manage wake-up calls for other permitted extensions.

## Enable the scheduled job

Wakeup Calls will not run automatically until the scheduled job is enabled.

Go to:

```text
Advanced > Default Settings
```

Find the setting:

| Category | Setting Name | Type | Value |
| -------- | ------------ | ---- | ----- |
| `scheduled_jobs` | `wake_up_calls` | `boolean` | `true` |

Open the setting and make sure:

* the setting is enabled
* the value is set to `true`

After saving the setting, FS PBX can process due wake-up calls every minute.

## Open Wakeup Calls

Go to:

```text
Applications > Wakeup Calls
```

The page shows scheduled wake-up calls, their status, retry count, and next attempt time.

## Create a wake-up call

Click **Create**.

Enter the wake-up call details:

| Field | Description |
| ----- | ----------- |
| **Date** | The date when the extension should be called. |
| **Time** | The time when the extension should be called. |
| **Extension** | The extension that should receive the wake-up call. |
| **Daily Repeat** | Turn this on when the wake-up call should repeat every day. |
| **Status** | Use `scheduled` for a normal active wake-up call. |

Click **Save**.

The wake-up call must be scheduled for a future date and time.

## Daily repeat

Use **Daily Repeat** when the call should happen every day at the same time.

When a recurring wake-up call is confirmed, FS PBX schedules the next wake-up call for the following day.

## Remote wake-up settings

Administrators can control which extensions are allowed to initiate remote wake-up calls.

Click **Settings** on the Wakeup Calls page.

In **Remote Wakeup**, choose the extensions that should be allowed to schedule wake-up calls from a phone.

If remote wake-up is available on your system, a user can dial:

```text
*925
```

Then follow the phone prompts to schedule the wake-up call.

## Statuses

Wake-up calls can show different statuses as they move through the call process.

| Status | Meaning |
| ------ | ------- |
| **scheduled** | The wake-up call is waiting for its scheduled time. |
| **in_progress** | FS PBX is placing or handling the wake-up call. |
| **snoozed** | The call was snoozed and will try again at the next attempt time. |
| **completed** | The wake-up call was confirmed. |
| **failed** | FS PBX could not complete the wake-up call. |

The table also shows **Retry Count** and **Next Attempt** so you can see whether FS PBX will try the call again.

## Search and filters

Use the search field to find wake-up calls by extension or related call information.

Use the date range filter to narrow the list to a specific period.

If your account can access multiple domains, use **Show global** to view wake-up calls across accessible domains. Use **Show local** to return to the current domain view.

## Edit or delete a wake-up call

Use the row actions to edit or delete a wake-up call.

When editing, you can change:

* date and time
* extension
* daily repeat
* status

Deleting a wake-up call removes it from the schedule.

## Troubleshooting

### The wake-up call did not run

Check the following:

* `scheduled_jobs > wake_up_calls` is enabled and set to `true`
* the wake-up call time is in the future
* the selected extension is valid and can receive calls
* the status is `scheduled` or `snoozed`
* the next attempt time is not blank for a call that should still run

### The wake-up call happened at the wrong time

Check the date, time, and domain time zone. Wake-up call times are displayed in the configured local time zone, while FS PBX stores the scheduled time internally for processing.

### A user cannot create a wake-up call for another extension

This is controlled by permissions.

Users with self-record access can manage only their own extension. Administrators need all-record access to manage wake-up calls for other extensions.

### Phone scheduling with `*925` does not work

Check that:

* the feature code is available on the system
* the calling extension is included in the Remote Wakeup allowed list
* the extension has permission to use the wake-up call feature

## Summary

Use Wakeup Calls when FS PBX should automatically call an extension at a specific time.

For the simplest setup:

1. Enable `scheduled_jobs > wake_up_calls`.
2. Open **Applications > Wakeup Calls**.
3. Create a wake-up call for a future date and time.
4. Use **Daily Repeat** only when the call should repeat every day.
