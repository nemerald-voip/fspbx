---
id: voicemail-blf
title: Voicemail Presence Button
slug: /configuration/voicemail-settings/blf
sidebar_position: 3
---

Voicemail Presence Button
=================

FS PBX includes a feature that lets users monitor team voicemails using a BLF key. 

### What the BLF shows

-   **LED ON:** New voicemails

-   **LED OFF:** All voicemails have been checked

-   **Press the button:** Dials voicemail menu


* * * * *

## Configuring the BLF Key


On Yealink phones (and most SIP phones), add a BLF with:

```
Type: BLF
Value: vm$EXT
Label: Team VM
```

> Replace **$EXT** with the voicmemail extension you want to monitor. Example - **vm100** for voicemail extension 100. 

This creates a SIP subscription to:

`sip:vm100@yourdomain.com`

FS PBX then sends real-time presence updates indicating whether there are new voicmeails left at this extension.

* * * * *

## Backend FreeSWITCH Configuration Requirements

You *must* enable the daemon in:

`/etc/freeswitch/autoload_configs/lua.conf.xml`

Add or un-comment:

```
<configuration name="lua.conf" description="LUA Configuration">
  <settings>

    <!-- YOUR EXISTING CONFIG -->

    <!-- FS PBX: Voicemail BLF daemon -->
    <param name="startup-script" value="lua/vm_blf.lua"/>
  </settings>
</configuration>
```

### Important

You **must reboot FreeSWITCH** to apply changes:

`systemctl restart freeswitch`