---
id: devices
title: Devices
slug: /getting-started/devices
sidebar_position: 4
---

# Devices

In FS PBX, **Devices** are primarily used for **auto-provisioning**—that is, generating and delivering phone configuration files so supported phones can “plug in and just work.”

This section is **not** a live view of what’s currently connected to the PBX.

---

## What a “Device” means in FS PBX

A **Device** is a record that describes a physical phone (or endpoint) so FS PBX can:

* Identify it (usually by **MAC address**)
* Apply the right **vendor/model template**
* Generate the correct **provisioning config**
* Assign one or more **lines** (extensions) to it
* Keep provisioning consistent after changes (passwords, line keys, BLFs, etc.)

Think of it as: **“How FS PBX builds configuration for a phone,”** not “which phones are online right now.”

---

## Common question: “Does this page show devices that are connected?”

**No.** The Devices section does **not** show which phones are currently connected/registered/online.

If you want to see live connectivity, you’d look at **registrations** (SIP registrations) and related status views—depending on your deployment and UI modules.

---

## Another common question: “Do I need to create Devices here before phones can connect?”

**No.** Phones do **not** need to be created in Devices in order to connect to the PBX.

* If you are **not using auto-provisioning**, you can skip Devices entirely.
* Phones can register normally as long as the **extension/account credentials** are correct and network access is in place.

---

## When you *should* use Devices

Use Devices when you want any of the following:

* **Auto-provisioning** (plug-and-play)
* Centralized management of phone configs
* Easy reassignment of phones to different users/extensions
* Consistent BLF / line-key layouts (where supported)
* Easier rollouts across many endpoints

If you’re deploying more than a handful of phones, Devices + provisioning usually saves a lot of time.

---

## What you typically do in the Devices section

A normal workflow looks like this:

1. **Create a Device**

* Vendor / Model (if applicable)
* **MAC address** (most common identifier)
* Optional: friendly name (e.g., “Front Desk Phone”)

2. **Assign one or more Lines**

* Link the device to an **extension** (or multiple extensions)
* Set line positioning (Line 1, Line 2, etc.)

3. **Provision the phone**

* Point the phone to your provisioning URL (or use DHCP option 66, depending on your environment)
* Reboot the phone so it downloads its config
