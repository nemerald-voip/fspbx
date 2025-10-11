---
slug: multi-fi-call-analysis
title: Call Analysis - JNT.fi (aka multi.fi)
authors: [lemstrom]
tags: [inbound, call-analysis]
---

Call Failure Analysis E.164 - Caller ID Format Issue

Working with multi.fi, we had problems with synchronizing connectivity, and ended up being mitigated by business opening (as ip auth) firewall settings, and regex alterations to dialplan.


<!-- truncate -->

================================

SIP Carrier - JNT.fi
JNT is the local telecom company that provides personal service. We offer services within everything that telecommunications stands for today: data, telephony, television and IT solutions. Ostrobothnia is our home and we deliver consumer services from Kokkola in the north to Kristinestad in the south, we deliver business services in a significantly larger area. It is a wonderfully diverse group that has one thing in common: everyone wants affordable and reliable services and good service in Swedish, Finnish or English. And we can offer that.

Finnish Mobile carrier

Call Details
------------

**Date/Time:** 2025-10-03 07:49:19 (UTC+3)\
**Caller:** Extension 1002\
**Destination:** +3584093255xx (Finnish mobile number)\
**Result:** Call Rejected (403 Forbidden)\
**Duration:** ~400ms

Call Flow
---------

### 1\. Incoming Call

-   **Source:** 47.33.196.xxx:63903
-   **Channel:** `sofia/internal/1002@pbx.xxxxxxxx.fi`
-   **Call-ID:** PfJrFXK5FBHrdWVaQv0-WA..
-   **Domain:** pbx.xxxxxxxx.fi

### 2\. Dialplan Processing

The call matched dialplan context `[pbx.xxxxxxxx.fi->sip.multi.fi.358d7110dxxx]` with regex:

regex

```
^(\+?358\d{7,11}|0\d{5,11})$
```

This regex matches Finnish phone numbers in two formats:

-   International: `+358...` or `358...` (7-11 digits)
-   National: `0...` (5-11 digits)

### 3\. Call Direction & Variables Set

```
call_direction: local → outbound
effective_caller_id_name: Service
effective_caller_id_number: 358678984xx
callee_id_number: +3584093255xx
hangup_after_bridge: true
```

### 4\. Bridge Attempt

**Bridge Command:**

```
bridge(sofia/gateway/8591fc83-869a-48ad-859d-f2df9612188d/+3584093255xx)
```

**Outbound Channel Created:**

-   Channel: `sofia/external/+3584093255xx`
-   UUID: 3156f103-1e93-4eac-add9-d15fe9f4afd1
-   Remote endpoint: 85.134.34.xxx

### 5\. Codec Negotiation

**Caller Offered:**

-   Opus (106) @ 48000/2
-   G722 (9) @ 8000
-   PCMU (0) @ 8000
-   PCMA (8) @ 8000
-   G729 (18) @ 8000
-   telephone-event (101) @ 8000

**Negotiated:**

-   **G722 @ 8000Hz** with 20ms ptime
-   DTMF: telephone-event/8000 (payload 101)

### 6\. Call Progression

1.  INVITE sent to gateway
2.  183 Session Progress received from 85.134.34.186
3.  Early media established
4.  **403 Forbidden received** immediately after
5.  Both legs terminated with:
    -   Outbound: `CALL_REJECTED`
    -   Inbound: `NORMAL_CLEARING` (mapped from 403)

Root Cause Analysis
-------------------

### The 403 Response - **CALLER ID FORMAT ISSUE**

**Resolution:** The call was rejected due to an incorrect caller ID format on the outbound route.

The `effective_caller_id_number` was set to:

```
effective_caller_id_number: 358678984xx
```

However, the upstream SIP provider (85.134.34.xxx) required the caller ID to be in **international E.164 format with a leading `+` sign**.

The outbound route configuration added the `+` prefix incorrectly or inconsistently, causing the provider to reject the call with a 403 Forbidden response.

### Why This Causes 403 Forbidden

Many SIP providers enforce strict caller ID validation:

-   They require E.164 format: `+[country code][number]`
-   Missing `+` prefix triggers fraud prevention mechanisms
-   The provider interprets malformed caller ID as unauthorized/spoofed
-   Results in immediate call rejection before routing

### Original Assumptions (Ruled Out)

The following were initially suspected but confirmed **not** the issue:

1.  **Service Restriction** ❌
    -   Account balance/credit was sufficient
    -   Service was active
2.  **Number Blocking** ❌
    -   Destination number was valid
    -   No geographic restrictions
3.  **Authorization Issue** ❌
    -   Gateway authentication was correct

### Billing Check Result

```
[check-suspension] No result found for domain_uuid: cdc2b4c7-2c9f-4d43-bf9c-8d76087c7ede
```

The local billing suspension check passed (no suspension found), so the issue is with the **upstream provider**, not the local PBX configuration.

Channel Naming Clarification
----------------------------

The bridge uses `sofia/gateway/[uuid]`, but FreeSWITCH creates the channel as `sofia/external/[number]`. This is **normal behavior**:

-   Gateways registered under the external profile create channels on `sofia/external`
-   The gateway UUID is resolved internally to the correct profile
-   The routing is working correctly

Solution Implemented
--------------------

### Fix: Correct Caller ID Format

**Update the outbound route to ensure the `effective_caller_id_number` includes the `+` prefix:**

xml

```
<action application="set" data="effective_caller_id_number=+358678984xx"/>
```

The correct format is:

-   ✅ `+358678984xx` (E.164 international format)
-   ❌ `358678984xx` (Missing + prefix)

### Prevention: Validate Caller ID Format

Add caller ID validation to your dialplan before bridging:

xml

```
<!-- Ensure caller ID has + prefix for international format -->
<action application="set" data="effective_caller_id_number=${regex(${effective_caller_id_number}|^\\+|+${effective_caller_id_number}|${effective_caller_id_number})}"/>
```

Or use a more robust approach:

xml

```
<condition field="${effective_caller_id_number}" expression="^\+">
  <action application="log" data="INFO Caller ID already in E.164 format"/>
  <anti-action application="set" data="effective_caller_id_number=+${effective_caller_id_number}"/>
  <anti-action application="log" data="NOTICE Added + prefix to caller ID: ${effective_caller_id_number}"/>
</condition>
```

### Debug Commands

bash

```
# Check gateway status
fs_cli -x "sofia status gateway 8591fc83-869a-48ad-859d-f2df9612188d"

# Enable SIP trace for next call
fs_cli -x "sofia global siptrace on"

# Check gateway registration
fs_cli -x "sofia status profile external"
```

### What's Working

-   ✅ Call routing and dialplan matching
-   ✅ Gateway resolution
-   ✅ Codec negotiation (G722)
-   ✅ SIP signaling to provider
-   ✅ Local billing checks

### What's Failing

-   ❌ **Caller ID format missing `+` prefix** (ROOT CAUSE)
-   ❌ Provider rejected due to invalid E.164 format

Technical Summary
-----------------

The FreeSWITCH system was functioning correctly for routing, but the **outbound route configuration had a caller ID formatting error**.

The call successfully:

-   ✅ Matched the dialplan
-   ✅ Routed through the correct gateway
-   ✅ Negotiated codecs
-   ✅ Reached the upstream provider

The failure was caused by:

-   ❌ **Missing `+` prefix on `effective_caller_id_number`**
-   ❌ Provider (85.134.34.186) requires strict E.164 format
-   ❌ Malformed caller ID triggered 403 Forbidden response

**Solution:** Update outbound route to set `effective_caller_id_number=+358678984xx` with the `+` prefix included.

Next Steps
----------

1.  ✅ **Issue Identified:** Caller ID format missing `+` prefix
2.  ✅ **Solution:** Update outbound route configuration to include `+` in `effective_caller_id_number`
3.  🔧 **Implement:** Add caller ID validation to prevent future occurrences
4.  🧪 **Test:** Place test call to verify 403 error is resolved
5.  📝 **Document:** Update configuration standards to require E.164 format for all outbound caller IDs
