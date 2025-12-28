---
id: gateways
title: Gateways
slug: /getting-started/geteways
sidebar_position: 6
---

# Gateways

In FS PBX, a **Gateway** is how your PBX connects to the outside world—usually to a SIP carrier (VoIP provider) so you can make and receive calls using real phone numbers (DIDs).

If you’re coming from Asterisk: **Gateway ≈ SIP Trunk registration/peer**.

---

## What a Gateway is (plain English)

A Gateway is the PBX’s connection details for a carrier, such as:

* Carrier host / IP / domain
* Username + password (if the trunk registers)
* Transport (UDP/TCP/TLS)
* Codec preferences (optional)
* NAT / keepalive behavior (depending on network)

A Gateway is typically used for:

* **Outbound calling** (PBX → carrier)
* **Inbound calls** (carrier → PBX), usually to your DIDs

---

## Gateway vs DID vs Routing

These often get mixed up:

* **Gateway:** the connection to the provider
* **DID:** the inbound phone number (like (555) 222-0000)
* **Destination / Call Routing:** what happens when a DID is called (extension, ring group, IVR, queue, etc.)

A good mental model:

**Carrier ⇄ Gateway ⇄ FS PBX ⇒ (DID match) ⇒ Destination (IVR/Ring Group/Extension)**

---

## Two common gateway types

### 1) Registration-based trunk (most common)

FS PBX “registers” to the carrier using a username/password.

* Pros: simple, common with ITSPs
* Cons: NAT/keepalive details matter more

### 2) IP-auth trunk (no registration)

Carrier sends calls to your public IP and expects outbound calls from your IP.

* Pros: often more stable, fewer registration issues
* Cons: requires static IP and provider-side configuration

---

## Do I need a Gateway for internal calling?

**No.** Extensions can call each other with no gateways at all.

You only need a Gateway when you want:

* outbound calling to PSTN (regular phone numbers)
* inbound DIDs from a provider

---

## Multi-tenant notes (Domains)

In a multi-tenant FS PBX system, you can design gateways in a few ways:

### Per-domain gateways (most common for hosting)

Each Domain/customer has their own carrier trunk(s).

* Cleaner separation
* Easier per-customer billing and caller ID control

### Shared gateways (common for internal enterprise)

Multiple Domains share one or more carrier connections.

* Simpler carrier management
* Requires more careful **caller ID**, **DID mapping**, and **routing** so calls land in the correct Domain

---

## Typical setup workflow

1. **Create the Gateway**

* Provider host
* Auth info (if registration trunk)
* Transport / port as required
* Apply any provider-specific requirements

2. **Confirm the gateway is healthy**

* If it registers: confirm it shows as registered (wherever your system displays it)
* If it’s IP-auth: confirm inbound calls reach your PBX and outbound is allowed

3. **Create Outbound Routes**

* Define dialing patterns (911, local, long distance, international, etc.)
* Select which gateway(s) the route uses
* Set caller ID rules if needed

4. **Add inbound DID routing**

* Map each DID to a destination inside the correct Domain:

  * extension
  * ring group
  * IVR
  * queue
  * call flow

5. **Test**

* Outbound: local + long distance (and 911/emergency per your policy)
* Inbound: each DID → correct destination
* Caller ID: shows correctly for each tenant/site

---

## Common questions & gotchas

### “I created a gateway—why can’t I make calls?”

Usually one of:

* No **outbound route** exists, or the dial pattern doesn’t match what you dialed
* Provider requires a specific dial format (E.164 like `+15551234567`)
* NAT/firewall/SIP ALG issues
* Wrong codecs or transport

### “Inbound calls hit the PBX but don’t reach the right tenant”

Usually:

* DID isn’t mapped correctly, or
* routing is pointing to the wrong Domain/destination

### “Do I need multiple gateways?”

Only if you want:

* separate carriers (failover / cost routing)
* multiple sites/tenants with separate trunks
* a dedicated gateway for specific call types (fax, international, etc.)
