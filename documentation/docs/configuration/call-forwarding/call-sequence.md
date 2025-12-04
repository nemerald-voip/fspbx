---
id: call-sequence
title: Call Sequence (Follow Me)
slug: /configuration/call-forwarding/call-sequence
sidebar_position: 1
---

Call Sequence (Follow Me)
===========================================

 **Call Sequence** feature allows your extension to ring multiple numbers in order --- such as your desk phone first, then your mobile phone, then another extension --- until someone answers.

Sequential Ring is ideal for:

-   On-call workflows

-   Overflow to mobile

-   Multi-device ringing

-   Ensuring important calls never go unanswered


* * * * *

What Sequential Ring Does
=========================

When **Call Sequence** is enabled:

1.  Your desk phone rings first for the number of rings (or seconds) you specify.

2.  If unanswered, FS PBX rings your backup destinations **one at a time in order**.

3.  Ringing stops immediately when any destination answers.

4.  Optionally, "answer confirmation" ensures voicemail systems don't accidentally pick up.

* * * * *

How to Enable Sequential Ring
=============================

### 1\. Open Your Extension Settings

Navigate to:

`Extensions → Edit → Call Forward (tab)`

You will see several forwarding options:

-   **Forward All Calls**

-   **When user is busy**

-   **When user does not answer the call**

-   **When Device Is Not Registered**

-   **Call Sequence (Follow Me)** ← *this is the Sequential Ring feature*

* * * * *

## Setting Up Call Sequence


Scroll to the **Call Sequence** section:

Enable it using the toggle:

`Call Sequence → ON`

### Step 1 --- Choose how long your devices ring first

Example:

`Ring my devices first for: 2 Rings (10s)`

### Step 2 --- Add backup destinations

Use the "Add Backup Destinations or Contacts" input:

-   Enter a phone number

-   Or choose an internal contact

-   Or select multiple items in order

Click **Add to Sequence**.

Each entry has:

-   **Delay** --- how long to wait before ringing it

-   **Ring for** --- how long that destination is allowed to ring

-   **Enable answer confirmation** --- prevents external voicemail from answering

You can add **as many steps as you need**.

* * * * *

## Presence Button (BLF Monitoring and Toggle from Your Phone)

FS PBX includes a feature that lets users toggle the **Call Sequence** using a BLF key. Once the destinatons are set in the dashboard you can enable/disable the feture by pressing a button on your phone.

### What the BLF shows

-   **LED ON:** Call Sequence (Follow Me) = Enabled

-   **LED OFF:** Call Sequence = Disabled

-   **Press the button:** Toggles the feature instantly

Each tenant can have their own extension numbers (100, 200, 300, etc.) without conflict --- the BLF system is domain-aware.

* * * * *

## Configuring the BLF Key


On Yealink phones (and most SIP phones), add a BLF with:

```
Type: BLF
Value: fm$EXT
Label: FollowMe
```

> Replace **$EXT** with the extension you want to monitor. Example - **fm100** for extension 100. 

This creates a SIP subscription to:

`sip:fm100@yourdomain.com`

FS PBX then sends real-time presence updates indicating whether Sequential Ring is enabled.

* * * * *

## Backend FreeSWITCH Configuration Requirements

You *must* enable the daemon in:

`/etc/freeswitch/autoload_configs/lua.conf.xml`

Add or un-comment:

```
<configuration name="lua.conf" description="LUA Configuration">
  <settings>

    <!-- YOUR EXISTING CONFIG -->

    <!-- FS PBX: FollowMe BLF daemon -->
    <param name="startup-script" value="lua/followme_blf.lua"/>
  </settings>
</configuration>
```

### Important

You **must reboot FreeSWITCH** to apply changes:

`systemctl restart freeswitch`