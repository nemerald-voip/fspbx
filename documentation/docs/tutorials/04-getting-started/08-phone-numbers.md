---
id: phone-numbers
title: Phone Numbers
slug: /getting-started/phone-numbers
sidebar_position: 7
---

# Phone Numbers (DIDs)

In FS PBX, **Phone Numbers** usually refer to your external numbers from a carrier—often called **DIDs**. These are the numbers people dial from the public phone network to reach your business.

If you’re coming from Asterisk: this is the “inbound DID” side of your trunking setup.

---

## What is a DID?

A **DID (Direct Inward Dialing)** number is a public phone number like:

* (555) 111-0000

When someone calls that number, the call comes from your carrier into FS PBX and must be routed to something inside your Domain.

---

## Phone Numbers vs Gateways (common confusion)

These two work together, but they are not the same:

* **Gateway** = the connection to your VoIP carrier (the “trunk”)
* **Phone Number (DID)** = the number that arrives on that connection
* **Destination** = where the call goes (extension, ring group, IVR, queue, etc.)

Simple flow:

**Caller → Carrier → Gateway → FS PBX → DID match → Destination**

---

## Do I need Phone Numbers for internal calling?

**No.** Internal extension-to-extension calling works without any DIDs.

You need Phone Numbers when you want:

* inbound calls from the public network
* “main line” numbers, direct lines, support lines, etc.

---

## Where Phone Numbers live in a multi-tenant system (Domains)

In FS PBX, Phone Numbers are typically assigned to a specific **Domain** so the system knows which tenant should receive the call.

This is what makes multi-tenancy work cleanly:

* Company A can have its own DIDs and routing
* Company B can have different DIDs and routing
* Calls don’t “bleed” across tenants

---

## What happens when a DID is called?

Each Phone Number (DID) should be mapped to a **Destination**, such as:

* **Extension** (direct line to a user)
* **Ring Group** (hunt group / simultaneous ring)
* **IVR** (auto-attendant: “Press 1 for Sales…”)
* **Queue** (call center)
* **Call Flow / Time Conditions** (day/night routing)
* **Voicemail** (for departments or after hours)

So when you add a phone number, the most important step is:
**decide where it should go.**

---

## Outbound caller ID (why phone numbers matter for outbound too)

Even though DIDs are mainly “inbound,” they often also determine what people see when you call out.

Common outbound setups:

* Each extension presents its own DID as caller ID
* Everyone presents the company main number
* Different departments present different numbers (Sales vs Support)

This is usually configured via extension settings, outbound routes, and/or domain defaults—depending on your setup.

---

## Typical setup workflow

1. **Confirm your Gateway is working**

* registration trunk or IP-auth trunk
* inbound calls are reaching your system

2. **Add your Phone Numbers (DIDs)**

* enter the DID in the correct format your system expects
* assign it to the correct **Domain**

3. **Set the Destination**
   Examples:

* Main number → IVR
* Sales number → Ring Group
* Direct line → Extension 101

4. **Test inbound**

* call each DID externally
* verify it hits the right destination and the right Domain

5. **Verify outbound caller ID**

* place outbound calls and confirm caller ID is correct

---

## Common gotchas

### “Inbound calls hit the PBX but don’t go anywhere”

Usually:

* the DID isn’t added, or
* the DID exists but has no destination, or
* the DID format doesn’t match what the carrier sends

### “My DID routes to the wrong tenant/domain”

Usually:

* the phone number is assigned to the wrong Domain, or
* multiple rules match and a different one wins

### “Caller ID looks wrong on outbound calls”

Usually:

* extension caller ID not set
* outbound route overrides caller ID
* carrier requires a specific caller ID format (often E.164)
