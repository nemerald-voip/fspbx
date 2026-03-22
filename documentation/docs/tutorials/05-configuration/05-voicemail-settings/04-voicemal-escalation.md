---
id: voicemail-escalation
title: Voicemail Escalation
slug: /configuration/voicemail-settings/voicemail-escalation
description: Automatically notify internal extensions and external phone numbers when a new voicemail is received until someone accepts responsibility.
sidebar_position: 4
---

# Voicemail Escalation

Voicemail Escalation helps make sure important voicemails do not sit unnoticed in a mailbox.

When a new voicemail is left, FS PBX can automatically call one or more recipients and continue escalating the notification until someone accepts responsibility for the message.

This is useful for:

- after-hours voicemail coverage
- support teams
- sales callbacks
- front desk or dispatch workflows
- shared or team mailboxes

## How it works

When a voicemail arrives in a mailbox with escalation enabled:

1. FS PBX starts calling the recipients in the first priority group.
2. All recipients with the same priority are called at the same time.
3. If nobody accepts responsibility, FS PBX moves to the next priority group.
4. If all priority groups are exhausted, the system can retry the escalation based on your retry settings.
5. Once someone accepts responsibility, the escalation stops.

Recipients can be:

- internal extensions
- external phone numbers

## What recipients hear

When a recipient answers an escalation call, they are told that a new voicemail has been received.

They can then:

- press **1** to hear the voicemail message
- press **2** to decline responsibility
- press **3** to hear the caller ID number

After listening to the voicemail, they are prompted again and can:

- press **1** to accept responsibility
- press **2** to decline responsibility

Only one person can claim the message. Once someone accepts it, the escalation ends.

## Opening Voicemail Escalation

Voicemail Escalation is configured from the voicemail itself.

Go to the voicemail you want to protect, then open the **Escalation** tab.

This allows you to create an escalation rule that belongs to that specific mailbox.

---

# Field Reference

## Enable Voicemail Escalation

Turns escalation on or off for this mailbox.

- **On**: new voicemails will trigger the escalation workflow
- **Off**: the rule remains saved, but new voicemails will not trigger escalation calls

This is helpful if you want to temporarily disable escalation without deleting your settings.

---

## Rule Name

A friendly name for the escalation rule.

Use a name that makes the purpose clear, such as:

- `Mailbox 100 Escalation`
- `After Hours Support`
- `Sales Team Voicemail Escalation`

This field is mainly for identification in the interface.

---

## Description

Optional notes about the rule.

You can use this field to document the purpose of the escalation, such as:

- who this mailbox belongs to
- when the rule should be used
- what type of calls it covers

---

## Outbound Caller ID Mode

Controls what caller ID is shown to recipients when the escalation call is placed.

### Default

Uses the caller ID values entered in the escalation rule.

This is the best option when you want escalation calls to display a fixed caller ID number and name, such as a main company number.

### Mailbox

Uses the caller ID associated with the mailbox.

This is useful when you want escalation calls to look like they are coming from the mailbox owner or mailbox extension rather than from a shared company number.

---

## Caller ID Number

The phone number that recipients will see when they receive the escalation call.

Common examples:

- the main company number
- the direct number for the team
- the mailbox extension number, if appropriate for your environment

This field is especially useful when **Outbound Caller ID Mode** is set to **Default**.

---

## Caller ID Name

The name shown to recipients when the escalation call is delivered.

Examples:

- `Support Voicemail`
- `Sales Escalation`
- `Main Office`
- `After Hours Dispatch`

A clear caller ID name helps recipients immediately understand why they are being called.

---

## Retry Count

How many additional full escalation cycles should be attempted after the first pass.

### Example

If you set:

- **Retry Count = 1**

FS PBX will:

- try all configured priorities once
- then retry the entire escalation one more time if nobody accepted

A value of `0` means there is no retry after the first full pass.

---

## Retry Delay (Minutes)

How long FS PBX waits before starting the next retry cycle.

This delay is used **after all priority groups have already been tried** and nobody has accepted the voicemail.

### Example

If Retry Delay is set to `5`, the system will wait 5 minutes before trying the escalation again.

---

## Priority Delay (Minutes)

How long FS PBX waits before moving from one priority group to the next.

This delay is used **between priority groups**.

### Example

If Priority Delay is set to `2`:

- priority 0 is called first
- if no one accepts, FS PBX waits 2 minutes
- then priority 1 is called
- then waits 2 minutes again before moving to priority 2, if needed

This allows each priority group time to respond before the escalation moves on.

---

# Recipients

The **Recipients** section defines who should be called when a voicemail arrives.

Recipients can be:

- internal extensions
- external phone numbers

## Add Recipient(s)

Use this field to add the people or numbers that should receive escalation calls.

You can:

- search for an internal extension
- enter an external phone number manually

After choosing or entering recipients, click **Add Selected Recipients**.

---

## Recipient List

Each recipient you add appears in the list below.

For each recipient, you can configure:

### Priority

Determines when that recipient is called.

- lower numbers are called first
- recipients with the same priority are called at the same time

### Example

- `101` with priority `0`
- `102` with priority `0`
- `+12135551000` with priority `1`

In this example:

1. Extensions `101` and `102` are called first at the same time
2. If nobody accepts, the external number is called next

### Active

Controls whether that recipient is currently included in the escalation workflow.

- **On**: recipient will be called
- **Off**: recipient remains saved but will be skipped

This is useful when someone is temporarily unavailable and you do not want to remove them completely.

---

# Email Notifications

Voicemail Escalation can optionally send email notifications when the escalation finishes.

These emails are sent when the escalation reaches a final result:

- someone accepted the voicemail
- nobody accepted the voicemail and the escalation failed

## Success Notification Emails

One or more email addresses that should receive a message when someone accepts responsibility for the voicemail.

Use this when supervisors, shared inboxes, or management teams should be notified that the voicemail has been claimed.

Examples:

- `support@company.com`
- `manager@company.com`

---

## Failure Notification Emails

One or more email addresses that should receive a message when the escalation completes without anyone accepting the voicemail.

This is useful when you want to alert a supervisor or fallback team that the voicemail still needs attention.

Examples:

- `afterhours@company.com`
- `dispatch@company.com`

---

## Attach Voicemail to Completion Emails

When enabled, FS PBX attaches the voicemail recording to the completion email.

This can be helpful when:

- managers need immediate access to the message
- the voicemail should be reviewed without logging into the PBX
- the escalation is being used for urgent situations

If this option is disabled, the email will still include the escalation result and activity log, but without the voicemail file attached.

---

# Example Setup

Here is a simple example:

- **Rule Name:** `Mailbox 100 Escalation`
- **Outbound Caller ID Mode:** `Default`
- **Caller ID Number:** `999`
- **Caller ID Name:** `Support Escalation`
- **Retry Count:** `1`
- **Retry Delay:** `1`
- **Priority Delay:** `2`

Recipients:

- `101 - Elena Dawson` → Priority `0`
- `+12135551000` → Priority `1`

Completion Emails:

- Success → `support@company.com`
- Failure → `manager@company.com`

### What happens

1. A new voicemail is left in mailbox `100`
2. Extension `101` is called first
3. If nobody accepts, FS PBX waits 2 minutes
4. The external number is called next
5. If still nobody accepts, FS PBX waits 1 minute
6. The escalation is retried one more time
7. If someone accepts, the success email is sent
8. If nobody accepts after all attempts, the failure email is sent

---

# Tips

- Use clear caller ID names so recipients immediately recognize escalation calls
- Keep your first priority group small and focused
- Use lower priority numbers for primary responders
- Use external mobile numbers as fallback recipients
- Add completion emails for visibility and auditing
- Review retry and delay settings carefully so escalation timing matches your real workflow

---

# Best Practices

## For urgent mailboxes

Use short delays and a small first-priority group.

Example:

- Priority Delay: `1`
- Retry Count: `1`

This keeps the escalation moving quickly.

## For team mailboxes

Place the main team members at the same priority so they are all notified at once.

## For after-hours workflows

Use internal extensions first, then mobile numbers as a fallback.

## For management visibility

Enable both success and failure email notifications.

---

# Saving Your Changes

After configuring the rule, click **Save**.

Once saved, new voicemails in that mailbox will follow the escalation rule as long as **Enable Voicemail Escalation** remains turned on.

---

# Summary

Voicemail Escalation helps make voicemail response more reliable by:

- notifying the right people automatically
- escalating across priorities
- retrying when necessary
- stopping as soon as someone accepts responsibility
- sending optional completion emails for visibility

If your team depends on timely voicemail response, this feature provides a simple and structured way to make sure important messages are not missed.