---
id: domains-multitenancy-101
title: Domains (Multi-Tenancy 101)
slug: /getting-started/domains-multitenancy-101/
sidebar_position: 2
---

# Domains (Multi-Tenancy 101)

In FS PBX, a **Domain** is the core “tenant” concept. If you’re coming from Asterisk, think of a Domain as **a fully isolated PBX instance** living inside one shared platform—its own users, extensions, devices, dialplans, voicemail, ring groups, call center configs, and (often) its own trunks—without needing separate servers.

---

## What a Domain is (in plain English)

A **Domain = one customer / one company / one office / one PBX “environment”** inside the same FS PBX system.

A Domain typically has:

* Its own **extensions** (100, 101, 102…)
* Its own **users** (who log into the web UI)
* Its own **devices** (phones, softphones, apps)
* Its own **call routing objects** (IVRs, ring groups, queues)
* Its own **voicemail**, **recordings**, **fax**, **SMS settings** (if enabled)
* Its own **permissions + admin roles**
* Its own **trunks/gateways** (optional, depending on how you design it)

Most importantly: a Domain keeps customers separated so you can host many companies on one FS PBX cluster safely.

---

## Why Domains matter

### 1) Isolation (the big one)

Two companies can both have an extension **100**, and it’s fine—because they’re in different Domains.

### 2) Clean administration

Each company admin only sees their own stuff (extensions, voicemails, recordings, etc.).

### 3) Scaling / hosting

Domains let you run a multi-tenant hosted PBX (or a multi-site enterprise) without deploying separate PBXs per customer.

---

## Domain Name vs Domain UUID

You’ll usually see:

* **Domain Name**: human-friendly (example: `acme.example.com` or `acme`)
* **Domain UUID**: internal unique ID (what the system uses to enforce separation)

In practice:

* Humans work with the **Domain Name**
* The database and routing logic rely on the **Domain UUID**

---

## How calls and registrations “know” which Domain they belong to

FS PBX can map a phone/call to the correct Domain using one or more of these:

### A) SIP registration domain (most common)

Phones register to something like:

* `acme.example.com` (tenant-specific)
* or a shared host with tenant identity carried in SIP fields

### B) Inbound DID routing

Inbound calls to a phone number (DID) are matched to the correct Domain, then routed inside that Domain.

### C) User/device assignment

Devices and extensions are explicitly assigned to a Domain. Even if two tenants both have extension 100, the system keeps them separate.

---

## Domains vs “Contexts” (for Asterisk folks)

In Asterisk, you often model separation with **dialplan contexts** (and sometimes separate DBs or separate instances).

In FreeSWITCH / FS PBX multi-tenancy:

* A Domain commonly maps to a dialplan **context**, but the *domain boundary* is broader than dialplan.
* It’s not just routing—it’s **data isolation + UI scope + permissions + provisioning + settings**.

So: **context is part of the story; Domain is the full tenant boundary.**

---

## What’s typically “inside” a Domain

Here’s the mental model that usually clicks for beginners:

**Domain**

* Extensions / Users
* Voicemail boxes & greetings
* Ring Groups / IVRs / Call Flows
* Queues / Agents (if you use contact center)
* Devices + provisioning
* Music on hold, recordings, time conditions
* Gateways / trunks (optional)
* Domain settings (caller ID defaults, voicemail policies, codecs, etc.)
* Permissions (who can see/edit what)

---

## Creating a Domain (typical workflow)

Your exact UI labels may vary by FS PBX version, but the flow is generally:

1. **Create the Domain**

* Pick a **domain name** you’ll recognize (often the customer’s subdomain).

2. **Set basic Domain settings**
   Common early settings:

* Default outbound caller ID (if you use a shared default)
* Timezone (important for schedules/time conditions)
* Voicemail policies (transcription, email delivery, retention)
* Recording defaults (if applicable)

3. **Create extensions inside the Domain**

* Add extension numbers and assign them to users/devices.

4. **Add users and assign permissions**

* Create a “Domain Admin” user for the customer if you want self-management.

5. **Provision or connect devices**

* Phones/softphones should register into the correct Domain.

6. **Add inbound numbers (DIDs) and routing**

* Map each DID to an extension, ring group, IVR, or call flow within that Domain.

7. **Test**

* Internal calls
* Inbound DID
* Outbound call
* Voicemail and call routing features

---

## Common “gotchas” (and how to avoid them)

### “My phone registers but calls don’t route correctly”

Usually one of:

* The device is registering into the wrong Domain
* The inbound DID is mapped to the wrong Domain
* The extension exists in multiple domains and you’re testing from the wrong tenant

**Fix:** confirm the device/extension’s Domain and confirm the inbound DID assignment.

### “Two customers have the same extension numbers—will that break things?”

No, that’s the point. As long as each extension belongs to a Domain, they’re isolated.

### “Do I need a separate SIP profile per Domain?”

Not necessarily. Many deployments use shared SIP profiles and rely on Domain-aware routing. Separate profiles can be useful for special network designs, but they aren’t required just for multi-tenancy.

### “Should each Domain have its own trunks?”

Depends on your business model:

* **Per-tenant trunks**: strongest separation, easiest billing per customer
* **Shared trunks**: simpler carrier management; needs careful caller ID/DID handling

---

## Quick glossary

* **Tenant**: a customer/company living inside your platform.
* **Domain**: FS PBX’s tenant container.
* **DID**: inbound phone number.
* **Extension**: internal endpoint (user/phone).
* **Context**: dialplan “namespace” for routing (Domain often maps to one).

---

## A simple real-world example

You host two companies on one FS PBX server:

**Domain: `alpha.example.com`**

* Extensions: 100–199
* DID: (555) 111-0000 → IVR → Ring Group 100

**Domain: `bravo.example.com`**

* Extensions: 100–199 (same numbers, no conflict)
* DID: (555) 222-0000 → Extension 100

Both can have **Extension 100**, **Voicemail 100**, **Ring Group 100**, etc., and they won’t see each other.


