---
id: skyetel-trunk
title: Skyetel
slug: /trunk-providers/skyetel/
sidebar_position: 3
---

# Skyetel

Connect FS PBX to Skyetel using an **IP-authenticated SIP trunk**. This setup uses `out.skyetel.com` for outgoing calls and a provider IP list to allow incoming calls. SIP registration stays disabled.

## Before you begin

In Skyetel, create an IP Group containing your PBX's public IP address, SIP port, and transport, then assign your phone numbers to that group. The port and transport must match the FS PBX SIP profile receiving the calls. This guide uses the **internal** profile. See [Skyetel's IP Authentication guide](https://support.skyetel.com/hc/en-us/articles/360040710674-IP-Authentication) for the provider-side setup.

You will also need Skyetel's current incoming SIP IP addresses and CIDR ranges for the **Provider IPs** step below.

## Create the gateway

Open **Accounts → Gateways** and click **Create**.

### Settings tab

| Setting | Value |
| --- | --- |
| Gateway | `Skyetel` |
| Gateway Enabled | **On** |
| Proxy | `out.skyetel.com` |
| SIP Profile | **internal** |
| Context | **public** |
| Register | **False** |
| Username | Leave blank. |
| Password | Leave blank. |
| Expire Seconds | **800** |
| Retry Seconds | **30** |

### Advanced tab

| Setting | Value |
| --- | --- |
| Domain | **Global** for a shared gateway, or the FS PBX account that owns this trunk. |
| Caller ID In From | **True** |
| Suppress CNG | **True** |
| SIP CID Type | **PID** |
| Extension In Contact | **False** |
| FreeSWITCH Hostname | Leave blank to load the gateway on every FS PBX server. Enter a server's FreeSWITCH hostname only when the gateway should run on that server alone. |

Leave the other advanced fields at their defaults. These settings follow [Skyetel's FreeSWITCH/FusionPBX configuration](https://support.skyetel.com/hc/en-us/articles/360041177393-FusionPBX).

### Provider IPs tab

This step allows FS PBX to accept incoming calls from Skyetel.

1. Obtain the full incoming SIP address list from [Skyetel IP Addresses](https://support.skyetel.com/hc/en-us/articles/360041173493-Skyetel-IP-Addresses). Sign in if prompted, or request the current list from Skyetel support.
2. Open **Provider IPs** and click **Add Item**.
3. Enter each IP address or CIDR range in its own **IP / CIDR** row. Include Skyetel's standard and emergency/failover SIP sources.
4. Click **Save** to save the gateway and its provider IPs.

FS PBX adds these entries to the **providers** access control list (ACL) and reloads it on the current server. Use the complete incoming SIP list from Skyetel; resolving `out.skyetel.com` alone does not provide all inbound sources. Skyetel also documents this requirement in its [inbound trunk guide](https://support.skyetel.com/hc/en-us/articles/4410765264791-FreePBX-13-Create-Inbound-Trunk).

:::important
Provider IPs are required for all IP-authenticated trunks. The **Proxy** field controls outgoing calls, while **Provider IPs** allows incoming calls through the providers ACL. Complete both parts before testing.
:::

## Route and test calls

Select the Skyetel gateway in **Dialplan → Outbound Routes**. For incoming calls, add your Skyetel numbers under **Dialplan → Phone Numbers** and select the extension, ring group, or other destination that should receive each number. See [Phone Numbers](/docs/getting-started/phone-numbers/) for more on inbound routing.

- Confirm that the gateway is **Running**. **Registration disabled** is expected for this setup.
- Make an outbound call and check the displayed caller ID.
- Call a Skyetel number from an outside phone and confirm it reaches the selected destination.
- Check audio in both directions.

If outbound calls work but incoming calls fail, check the **Provider IPs** list, Skyetel's IP Group and number assignment, and the FS PBX phone-number route. On redundant installations, verify the provider ACL and inbound calling on each server that may receive calls.
