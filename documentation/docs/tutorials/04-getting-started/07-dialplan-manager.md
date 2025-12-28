---
id: dialplan-manager
title: Dialplan Manager
slug: /getting-started/dialplan-manager
sidebar_position: 7
---

# Dialplan Manager

The **Dialplan Manager** is an advanced area of FS PBX used to view and edit **FreeSWITCH dialplans** directly.

For most day-to-day PBX setups, you won’t need it—FS PBX already ships with a preconfigured dialplan that covers the common features (extensions, voicemail, ring groups, IVRs, outbound routes, etc.).

---

## What a Dialplan is (plain English)

A **dialplan** is the rule set that tells FreeSWITCH what to do when something happens, like:

* someone dials an extension (100)
* an inbound call arrives on a DID
* someone dials 911
* a call should go to voicemail
* a feature code is dialed (like * codes)
* calls should follow time-of-day logic

Think of it as: **“if this call matches X, then do Y.”**

---

## Who should use Dialplan Manager?

Dialplan Manager is mainly for:

* **advanced users**
* system admins comfortable with FreeSWITCH concepts
* people building custom behavior beyond what the standard FS PBX UI provides

If you’re new to VoIP or just migrating from another system, you’ll typically configure call flows using the regular FS PBX pages (Extensions, Ring Groups, IVRs, Routes, etc.) instead of editing dialplan directly.

---

## Do I need to change the dialplan to run FS PBX?

**No.** Most installations never touch it.

FS PBX’s default configuration is designed so you can:

* create domains
* add extensions/users/devices
* set up inbound and outbound calling
* build IVRs/ring groups
* run queues/contact center modules (if enabled)

…without editing dialplan manually.

---

## When Dialplan Manager *is* useful

You’ll usually open Dialplan Manager when you want to:

### Add new features not included by default

Examples:

* custom feature codes
* special routing logic for a niche use-case
* custom failover behavior
* carrier-specific call handling
* unusual caller ID rules or manipulations

### Build custom integrations

Examples:

* call routing based on database/API lookup
* CRM “screen-pop” behavior triggered from the call
* custom call tagging or headers for downstream systems

### Troubleshoot advanced routing

Sometimes you need to inspect the dialplan to understand why a call matched a certain rule.

---

## Important caution

Dialplan changes can affect live call routing. A small mistake can cause:

* calls not completing
* inbound DIDs routing incorrectly
* feature codes breaking
* unexpected call loops

Best practices:

* make changes carefully
* test in a non-production domain or staging system when possible
* document what you changed and why
