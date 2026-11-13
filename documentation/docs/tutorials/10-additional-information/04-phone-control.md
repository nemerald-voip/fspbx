---
id: phone-control
title: Remote Phone Control
slug: /additional-information/phone-control/
sidebar_position: 4
---

# Remote Phone Control

FS PBX lets administrators control a registered desk phone from the command line: hold or transfer a call, start a conference, mute, or hang up. Command-line use requires access to the FS PBX server.

Two artisan commands are involved:

- `phone:click-to-dial` — make a phone dial a number
- `phone:control` — control a call already in progress

This guide covers `phone:control`. In every example, `101` is the extension being controlled and `example.com` is the domain (a domain name or UUID both work).

### API and Browser Extension Permissions

For API or browser-extension access, assign an extension to the user's profile and grant the permissions they need:

| Permission | Allows |
| --- | --- |
| `phone_control_view` | View the user's phones and active calls. |
| `phone_control_call` | Place calls and use call controls. |
| `phone_control_view_all_records` | Use the granted view or call controls for other extensions in accounts the user can access. |

Without the last permission, users can only control their assigned extension. The standard user role includes view and call permissions; standard administrator roles also include access to other extensions. Review these permissions when creating custom roles.

* * * * *

### Supported Phones and Actions

Available actions depend on the phone. Use the table below to choose a supported command. An unsupported command returns an error listing the available actions.

| Action | Yealink | Snom | Poly | PBX-assisted phones |
| --- | :---: | :---: | :---: | :---: |
| `hold` | ✅ | ✅ | ✅ | ✅ |
| `resume` | ✅ | ✅ | ✅ | ✅ |
| `blind-transfer` | ✅ | ✅ | ✅ | ✅ |
| `attended-transfer` | ✅ | ✅ | ✅ | ✅ |
| `complete-transfer` | ✅ | ✅ | ✅ | ✅ |
| `cancel-transfer` | ✅ | ✅ | ✅ | ✅ |
| `conference` | ✅ | ✅ | ✅ | ✅ |
| `end-call` | ✅ | ✅ | ✅ | ✅ |
| `mute-toggle` | ✅ | ✅ | — | — |
| `mute-on` / `mute-off` | — | — | ✅ | ✅ |
| `answer-call` | ✅ | ✅ | ✅ | — |
| `dnd-on` / `dnd-off` | ✅ | — | — | — |
| `dnd-toggle` | — | ✅ | — | — |

:::note Polycom firmware requirement
**Polycom VVX, Trio, and CCX phones require UCS firmware 6.4.2 or newer** for the controls in the **Poly** column. Older versions use the **PBX-assisted phones** column and do not show remote actions on the phone's screen.
:::

Grandstream, Ringotel, and other PBX-assisted phones also may not show remote hold, transfer, or mute changes on their screens. Use `--list-calls` to check the call's status.

* * * * *

### Checking Registered Phones and Active Calls

Before controlling a phone, it helps to see what's registered and what calls are in progress:

```bash
php artisan phone:control 101 example.com --list-uas
```

Lists the phones registered to extension 101 and which vendor was detected for each.

```bash
php artisan phone:control 101 example.com --list-calls
```

Lists the extension's current calls and their state (`ACTIVE`, `HELD`, `RINGING`). Run it again after a command to check the result.

Add `--dry-run` to any command below to preview what would be sent without actually sending it.

:::tip
If an extension has several phones, run `--list-uas` and use `--agent` or `--vendor` to select the intended phone. Without a selector, commands target the most recently registered phone. See "Selecting a Specific Phone or Call" below for examples.
:::

* * * * *

### Hold and Resume

```bash
php artisan phone:control 101 example.com hold
php artisan phone:control 101 example.com resume
```

Requires the extension to have exactly one call. If the call is already on hold, `hold` is refused; if it isn't on hold, `resume` is refused — this keeps the two commands safe to use without checking state first.

* * * * *

### Blind Transfer

Sends the caller straight to another extension or number, without speaking to them first:

```bash
php artisan phone:control 101 example.com blind-transfer 200
```

The call must already be answered — if it's still ringing, run the command again once it's picked up.

* * * * *

### Attended Transfer

An attended transfer lets you speak with the destination before handing the caller over, the same as pressing Transfer and dialing a number on the phone itself:

```bash
php artisan phone:control 101 example.com attended-transfer 200
```

This puts the current caller on hold and starts a new call to `200`. Once you've spoken with them, finish the transfer:

```bash
php artisan phone:control 101 example.com complete-transfer
```

The caller and `200` are connected, and extension 101 drops off the call.

If you change your mind, cancel the transfer instead — the original caller is picked back up off hold automatically:

```bash
php artisan phone:control 101 example.com cancel-transfer
```

If you'd rather leave the caller on hold instead of resuming them right away, add `--no-resume`.

* * * * *

### Starting a Conference Call

To bring a third party into the call, start with an attended transfer, then merge instead of completing it:

```bash
php artisan phone:control 101 example.com attended-transfer 200
# once 200 answers:
php artisan phone:control 101 example.com conference
```

All three parties are now on the same call, on every supported phone.

* * * * *

### Muting a Call

```bash
php artisan phone:control 101 example.com mute-toggle
```

On phones that report mute state remotely, `mute-on` and `mute-off` are also available if you need a specific state rather than a toggle:

```bash
php artisan phone:control 101 example.com mute-on
php artisan phone:control 101 example.com mute-off
```

* * * * *

### Ending a Call

```bash
php artisan phone:control 101 example.com end-call
```

Refused if the extension has no active calls, or more than one (see "Selecting a Specific Phone or Call" below for handling multiple calls).

* * * * *

### Do Not Disturb

```bash
php artisan phone:control 101 example.com dnd-on
php artisan phone:control 101 example.com dnd-off
```

Support for DND varies by vendor. If `dnd-on`/`dnd-off` isn't available for a phone, try the toggle form instead:

```bash
php artisan phone:control 101 example.com dnd-toggle
```

If a phone doesn't have DND enabled at all, see Troubleshooting below.

* * * * *

### Answering a Call Remotely

On Yealink, Snom, and Poly Edge phones, a ringing call can be answered without touching the handset:

```bash
php artisan phone:control 101 example.com answer-call
```

Refused unless the extension has exactly one call and it's currently `RINGING`. Not currently available on Grandstream/generic phones.

* * * * *

### Selecting a Specific Phone or Call

If an extension has more than one phone registered, or more than one active call, FS PBX needs to know which one you mean. Narrow it down with one of these options, in order of preference:

```bash
php artisan phone:control 101 example.com hold --agent="SIP-T53W"
php artisan phone:control 101 example.com hold --vendor=yealink
php artisan phone:control 101 example.com hold --lan-ip=10.0.0.25
php artisan phone:control 101 example.com hold --call-id=<call-id-from-list-calls>
```

`--agent` is the most precise — it matches against the phone's registered model string (shown by `--list-uas`), so it's the right choice when several phones of different models (or one of each vendor) share an extension. `--vendor` is a good fallback when that alone is enough to disambiguate. `--lan-ip` and `--call-id` are for narrower cases (two identical phones, or a specific registration).

Without a selector, FS PBX picks the most recently registered phone and reports any others it skipped. Run `--list-uas` or `--list-calls` first if you're not sure which one you need.

* * * * *

### ✅ Quick Test

1. Call extension 101 from another phone so it has an active call.
2. Run `php artisan phone:control 101 example.com --list-calls` and confirm the call shows as `ACTIVE`.
3. Run `php artisan phone:control 101 example.com hold`, then `--list-calls` again — the state should now read `HELD`.
4. Run `php artisan phone:control 101 example.com resume` to bring the call back.

* * * * *

### Troubleshooting

**DND commands don't seem to do anything.** The phone's Do Not Disturb feature may be turned off in its provisioning settings. For Yealink phones, check the "DND Allowed" setting under Default Settings (system-wide) or Domain Settings (per tenant), enable it, and re-provision the phone.

**The Conference command doesn't open a conference screen on the phone.** This usually means the phone's local conference feature is disabled in provisioning rather than a network conference server URI it doesn't have configured. Check the "N-Way Conference" setting and make sure it's set to use the phone's built-in (local) conferencing, then re-provision.

**Blind transfer does nothing.** The call must be answered first — most phones silently ignore a transfer request while still ringing.

**A Poly phone doesn't respond to any commands.** Enable the phone's REST API in its provisioning settings, then sync it. For Polycom VVX, Trio, and CCX, check that the phone runs UCS 6.4.2 or newer.

**A Grandstream (or unrecognized) phone's screen never changes, even though the command succeeds.** This is expected — see the note under Supported Phones above. Check `--list-calls` to confirm the actual state.

**On a Grandstream phone, `attended-transfer` doesn't seem to connect.** The consultation call rings the destination like any other call — if it isn't answered in time, it clears itself the same way an unanswered call would. Check `--list-calls`: you should see two calls, the original one `HELD` and the new one `ACTIVE` once it's picked up. If the second call isn't there, try again or confirm the destination extension is actually registered.
