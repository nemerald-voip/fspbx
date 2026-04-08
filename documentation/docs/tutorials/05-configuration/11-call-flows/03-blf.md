---
id: call-flow-blf
title: Call Flow Presence Button
slug: /configuration/call-flows/blf
sidebar_position: 3
---

Call Flow Presence Button
=================

FS PBX includes a feature that lets users monitor and toggle a Call Flow using a BLF key.

### What the BLF shows

- **LED ON:** Alternate mode is active
- **LED OFF:** Normal mode is active
- **Press the button:** Toggles the Call Flow

> If a PIN is configured on the Call Flow, the user will be prompted to enter it before the toggle is applied.

* * * * *

## Configuring the BLF Key

On Yealink phones (and most SIP phones), add a BLF with:

```text
Type: BLF
Value: flow<EXT> Example: flow333
Label: Day / Night
```

> Replace **\<EXT\>** with the Call Flow extension you want to monitor.
> Example: **flow333** for Call Flow extension **333**.

This creates a SIP subscription to:

`sip:flow333@yourdomain.com`

FS PBX then sends real-time presence updates indicating whether the Call Flow is currently in normal mode or alternate mode.

---

## How it works

The BLF monitors the Call Flow extension directly.

For example:

* Call Flow extension: `333`
* BLF value: `flow333`

When the button is pressed, FS PBX toggles the Call Flow status. The BLF lamp then updates automatically to reflect the new state.

### Typical use cases

* Day / Night mode
* Open / Closed routing
* Main office / After-hours routing
* Holiday routing

---

## Backend FreeSWITCH Configuration Requirements

You **must** enable the daemon in:

`/etc/freeswitch/autoload_configs/lua.conf.xml`

Add or un-comment:

```xml
<configuration name="lua.conf" description="LUA Configuration">
  <settings>

    <!-- YOUR EXISTING CONFIG -->

    <!-- FS PBX: Call Flow BLF daemon -->
    <param name="startup-script" value="lua/flow_blf.lua"/>
  </settings>
</configuration>
```

### Important

You **must** restart FreeSWITCH to apply changes:

```bash
systemctl restart freeswitch
```

---

## Notes

* The BLF URI format is `flow<EXT>@domain`
* Example: `flow333@yourdomain.com`
* The LED state is tied to the current Call Flow status in FS PBX
* Some phones may require a reboot or re-registration before BLF updates appear correctly


