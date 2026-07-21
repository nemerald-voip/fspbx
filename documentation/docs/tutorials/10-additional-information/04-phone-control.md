---
id: phone-control
title: Remote Phone Control
slug: /additional-information/phone-control/
sidebar_position: 4
---

# Remote Phone Control

FS PBX can remotely control a registered desk phone from the command line — put a call on hold, transfer it, start a conference, mute the line, or hang up — without touching the handset. This is useful for supervisors assisting an agent, scripted call handling, or diagnostics.

Two artisan commands are involved:

- `phone:click-to-dial` — make a phone dial a number
- `phone:control` — control a call already in progress

This guide covers `phone:control`. In every example, `101` is the extension being controlled and `example.com` is the domain (a domain name or UUID both work).

* * * * *

### Supported Phones and Actions

The vendor is detected automatically from the phone's registration — you don't need to specify it. Support is not identical across vendors; some actions only make sense for phones with a particular remote-control mechanism. This table is exactly what's implemented today, not a general claim of "full support" — if you run an action a phone doesn't have, `phone:control` refuses with an error listing what that phone does support, the same way it would for a typo'd action name.

| Action | Yealink | Snom | Poly Edge | Grandstream / other |
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

A few things this table is telling you:

- **Mute is a toggle on Yealink/Snom, but a specific on/off state on Poly/Grandstream** — use `mute-toggle` on the first two, `mute-on`/`mute-off` on the other two.
- **DND works the same way** — Yealink gets deterministic `dnd-on`/`dnd-off`, Snom only exposes a `dnd-toggle` (its phones ignore a deterministic DND setting pushed remotely), and Poly/Grandstream have no DND action at all.
- **`answer-call`** (remotely answering a ringing call) works on every vendor except Grandstream/generic phones, which have no remote-control mechanism to answer with.

Yealink, Snom, and Poly phones show the change on their own screen, the same as if a button had been pressed on the handset. Grandstream phones — and any other phone FS PBX doesn't specifically recognize — have no such remote-control feature, so FS PBX manages the call directly instead. Everything supported in this guide still works the same way on these phones, with one difference worth knowing: **the phone's screen will not update.** No hold icon, no blinking transfer key, no mute indicator. The call state itself is real (the caller really is on hold, muted, etc.) — only the display doesn't reflect it. Use `--list-calls` (below) to check status instead of looking at the phone.

Poly Edge additionally requires the phone's REST API to be enabled in provisioning — see Troubleshooting if commands don't seem to do anything.

**Polycom VVX, Trio, and CCX phones** get the same Poly column above, but only on **UCS firmware 6.4.2 or newer** — that's the release Poly added REST-API delivery over SIP NOTIFY, the mechanism all of this relies on. Older firmware silently accepts the request without acting on it, so those phones are treated as unrecognized and fall back to the same PBX-managed control as Grandstream (screen won't update, see above). This uses the exact same REST commands already verified against a Poly Edge phone — what's specific to VVX/Trio/CCX is the firmware-version detection that routes them there, which hasn't been confirmed yet against real VVX/Trio/CCX hardware. If you have one of these phones, `--list-uas` will show whether it was picked up as `poly` or fell back to `generic` — worth a quick check the first time.

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

Lists the extension's current calls and their state (`ACTIVE`, `HELD`, `RINGING`). This is also the best way to confirm an action worked — after sending a command, run `--list-calls` again and the state should reflect the change within a second or two.

Add `--dry-run` to any command below to preview what would be sent without actually sending it.

:::tip
If an extension can have more than one phone registered to it, get in the habit of adding `--agent` (or `--vendor`) to every command, not just when a command fails with an ambiguous-selection error. Without it, FS PBX picks the most recently registered phone, which can quietly target the wrong device if a second phone re-registers later. `--agent` is the most precise option — it matches the phone's exact model string from `--list-uas` — so prefer it first; fall back to `--vendor` when that's specific enough. See "Selecting a Specific Phone or Call" below for the full list of options.
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

**A Poly phone doesn't respond to any commands.** Poly Edge phones need their REST API enabled in provisioning (used for both `phone:control` and click-to-dial). Check the phone's provisioning template and re-provision if needed.

**A Grandstream (or unrecognized) phone's screen never changes, even though the command succeeds.** This is expected — see the note under Supported Phones above. Check `--list-calls` to confirm the actual state.

**On a Grandstream phone, `attended-transfer` doesn't seem to connect.** The consultation call rings the destination like any other call — if it isn't answered in time, it clears itself the same way an unanswered call would. Check `--list-calls`: you should see two calls, the original one `HELD` and the new one `ACTIVE` once it's picked up. If the second call isn't there, try again or confirm the destination extension is actually registered.

**A call placed with `phone:click-to-dial` doesn't show up when using `phone:control` on a Grandstream or generic phone.** Make sure both extensions are up to date — this was a known gap in earlier versions that's since been fixed.
