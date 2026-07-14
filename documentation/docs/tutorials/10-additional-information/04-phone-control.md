---
id: phone-control
title: Remote Phone Control
slug: /additional-information/phone-control/
sidebar_position: 4
---

# Remote Phone Control

FS PBX can remotely control registered desk phones — hold, resume, transfer, conference, mute, end calls, and toggle DND — without any special FreeSWITCH modules. Commands are delivered to the phone as vendor Action-URI messages over SIP NOTIFY, using the phone's existing registration.

Two artisan commands are available:

- `phone:click-to-dial` — make a phone dial a number (see the Click-to-Dial API for the HTTP equivalent)
- `phone:control` — send call-control actions to a phone

## Supported Phones

| Vendor  | Detection                                  | Transport                       |
| ------- | ------------------------------------------ | ------------------------------- |
| Yealink | User agent contains `Yealink` or `SIP-T` (OEM builds) | SIP NOTIFY, `Event: ACTION-URI` |
| Snom    | User agent contains `snom` (firmware 10.1.82.0+) | SIP NOTIFY, `Event: xml` (silent minibrowser push) |

The vendor is detected automatically from the registered user agent. Both mechanisms are **key-press simulation** — the phone behaves exactly as if the key were pressed on the handset. There is no per-call addressing: state-dependent keys act on whichever call is selected on the phone's screen. FS PBX compensates with a call-state guard (see below).

## Basic Usage

```bash
# List registered phones for an extension and the detected vendors
php artisan phone:control 101 example.com --list-uas

# List the extension's active calls and their states
php artisan phone:control 101 example.com --list-calls

# Send an action (vendor auto-detected)
php artisan phone:control 101 example.com hold

# Preview without sending
php artisan phone:control 101 example.com hold --dry-run
```

The `domain` argument accepts a domain name or UUID.

## Actions

| Action                       | Yealink        | Snom                        | Notes                                                                 |
| ---------------------------- | -------------- | --------------------------- | --------------------------------------------------------------------- |
| `hold`                       | `F_HOLD`       | `F_HOLD`                    | Guarded: refused if the call is already on hold                        |
| `resume`                     | `F_HOLD`       | `F_HOLD`                    | Guarded: refused if the call is not on hold                            |
| `blind-transfer <dest>`      | `BTrans=<dest>`| `F_TRANSFER` + `numberdial` | Call must be **answered** first (Yealink ignores it while ringing)     |
| `attended-transfer <dest>`   | `ATrans=<dest>`| `F_HOLD` + `numberdial`     | Holds the call and dials a consultation call to `<dest>`               |
| `complete-transfer`          | `F_TRANSFER`   | `F_TRANSFER` + `F_OK`       | Completes an attended transfer; on Yealink while the consultation is still ringing this becomes a semi-attended transfer |
| `cancel-transfer`            | `CANCEL`       | `F_CANCEL`                  | Drops the consultation call; the original call **stays on hold** — follow with `resume` |
| `conference`                 | `F_CONFERENCE` | `F_CONFERENCE`              | Yealink: two-step (see below). Snom: a single press merges the held and active calls |
| `mute-toggle`                | `MUTE`         | `F_MUTE`                    | Toggles the local microphone; not observable server-side               |
| `end-call`                   | `CALLEND`      | `F_CANCEL`                  | Guarded: refused when there are no calls or several calls              |
| `dnd-on` / `dnd-off`         | `DNDOn` / `DNDOff` | —                       | Yealink only; requires DND enabled in provisioning (see Troubleshooting) |
| `dnd-toggle`                 | —              | `F_DND`                     | Snom only: the phone ignores deterministic DND settings pushed over NOTIFY, so only the toggle key is available |

### Attended transfer workflow

```bash
php artisan phone:control 101 example.com attended-transfer 102   # hold + consult 102
# talk to 102 (or not) ...
php artisan phone:control 101 example.com complete-transfer       # hand the call over
# — or abandon it —
php artisan phone:control 101 example.com cancel-transfer         # drop consultation
php artisan phone:control 101 example.com resume                  # get the caller back
```

### Conference workflow

**Yealink** local conferences are a two-step key flow:

```bash
# during an answered call:
php artisan phone:control 101 example.com conference   # holds the call, opens conference dial screen
# dial the second party on the phone, wait for answer, then:
php artisan phone:control 101 example.com conference   # merges into a local 3-way
```

Starting the second call from an existing transfer consultation will **not** merge — the conference must be initiated with the Conference key.

**Snom** merges in a single press: with one held call and one active call (e.g. after `attended-transfer`), `conference` joins them into a local 3-way immediately. Note that on Snom, `complete-transfer` does **not** work from an active conference — only from the two-call consultation state.

## The Call-State Guard

Because Action-URI keys act on the phone's selected call, `hold`, `resume`, and `end-call` first check FreeSWITCH's channel state for the extension and refuse to send when the result would be wrong or ambiguous:

- no active calls → refused
- more than one call → refused, with a list of the calls (`--list-calls` shows the same)
- `hold` when the call is already held / `resume` when it is not held → refused

`--force` bypasses the guard and sends the key anyway — the phone will act on whatever call is selected on its screen. `--dry-run` also skips the guard.

## Selecting a Phone

When an extension has several registered phones, narrow the target:

```bash
php artisan phone:control 101 example.com hold --vendor=yealink
php artisan phone:control 101 example.com hold --agent="SIP-T53W"   # regex or plain text
php artisan phone:control 101 example.com hold --lan-ip=10.0.0.25
php artisan phone:control 101 example.com hold --call-id=<registration-call-id>
```

Without a selector, the phone with the freshest registration is used and the others are reported as skipped.

## Troubleshooting

**`dnd-on` / `dnd-off` are silently ignored.** The phone's DND feature is disabled by provisioning. The Yealink templates render `features.dnd.allow` from the `yealink_dnd_allow` provisioning setting, which defaults to `0`. Set it to `1` (Default Settings for the whole system, or Domain Settings for one tenant), then re-provision the phone.

**Conference key does nothing / no merge option on the phone.** The `nway_conference` provisioning setting set to `true` renders `account.X.conf_type = 2` (network conference), which requires a conference server URI the templates do not configure — this disables the phone's Conference feature entirely. Set `nway_conference` to `false` (→ `conf_type = 0`, local conference) and re-provision.

**Re-provisioning a phone remotely.** Yealink phones re-fetch their config on a `check-sync` NOTIFY. FS PBX device tools can push this, and phones also poll on their auto-provision schedule.

**"Sent" does not guarantee delivery to the phone.** A successful send means FreeSWITCH's event system accepted the NOTIFY event. If the SIP profile name is wrong or the registration is stale, delivery can still fail inside mod_sofia — check the FreeSWITCH log. Verify hold/resume results with `--list-calls` (the call state changes within a second or two).

**Blind transfer does nothing.** The call must be answered before `blind-transfer` — the phone ignores `BTrans` while the call is still ringing.

**Snom notes.** Snom key control needs no special provisioning — the minibrowser `key` and `numberdial` fragments work without a trusted host. Snom DND is only available as `dnd-toggle`: the phone ignores `set:dnd_mode` pushed over NOTIFY even when `mb_trusted_hosts` is configured, so there is no deterministic on/off. Multi-key actions (transfers) are sent as one NOTIFY per key press with a short delay between them.
