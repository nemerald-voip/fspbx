---
id: didforsale-trunk
title: DIDforSale
slug: /trunk-providers/didforsale/
sidebar_position: 4
---

# DIDforSale

Connect FS PBX to DIDforSale using an **IP-authenticated SIP trunk**. This setup sends outgoing calls through `term1.didforsale.com` and accepts incoming calls from DIDforSale's provider IPs. SIP registration stays disabled.

## Before you begin

Sign in to the [DIDforSale customer portal](https://portal.didforsale.com) and authorize your PBX's public IP address:

1. Open **Interconnection → Manage IP → Add IP ADDRESS → APPLY**.
2. Enter your PBX's public IP address and select **Enable Termination** to allow outbound calls.
3. Save the entry and configure your DIDforSale phone numbers to send incoming calls to it.

See [DIDforSale's IP configuration guide](https://docs.didforsale.com/sip-trunking/ip-configuration) for the provider-side steps and current gateway addresses.

## Create the gateway

Open **Accounts → Gateways** and click **Create**. Use a recognizable name, such as `DID-Term1`, so you can identify the gateway when selecting it in an outbound route.

### Settings tab

| Setting | Value |
| --- | --- |
| Gateway | `DID-Term1` |
| Gateway Enabled | **On** |
| Proxy | `term1.didforsale.com` |
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
| FreeSWITCH Hostname | Leave blank to load the gateway on every FS PBX server. Enter a server's FreeSWITCH hostname only when the gateway should run on that server alone. |

**Caller ID In From** sends the call's caller ID number in the SIP From field. Leave **From User**, **From Domain**, and **Auth Username** blank, and keep the remaining advanced fields at their defaults.

### Provider IPs tab

DIDforSale delivers incoming calls from multiple addresses. Add its complete incoming SIP address list so FS PBX can accept calls from each source.

1. Open **Provider IPs** and click **Add Item**.
2. Enter each provider address in a separate **IP / CIDR** row.
3. Click **Save** to save the gateway and its provider IPs.

The [published DIDforSale incoming IP list](https://docs.didforsale.com/sip-trunking/ip-configuration) includes the following addresses. Check that page for updates when configuring your gateway:

```text
66.209.76.70
66.209.76.71
66.209.76.72
66.209.76.73
66.209.76.101
66.209.76.102
66.209.76.103
66.209.76.104
```

FS PBX uses these entries to populate the **providers** access control list (ACL) and reloads it on the current server. Enter DIDforSale's addresses here; your PBX's public IP belongs in the DIDforSale portal.

:::important
Provider IPs are required for all IP-authenticated trunks. Setting **Proxy** alone does not populate the providers ACL. Complete the **Provider IPs** tab even when outgoing calls already work.
:::

## Route and test calls

Select `DID-Term1` in **Dialplan → Outbound Routes**. For incoming calls, add your DIDforSale numbers under **Dialplan → Phone Numbers** and choose the extension, ring group, or other destination that should receive each number. See [Phone Numbers](/docs/getting-started/phone-numbers/) for more on inbound routing.

- Confirm that the gateway is **Running**. **Registration disabled** is expected for this setup.
- Make an outbound call and check the displayed caller ID.
- Call a DIDforSale number from an outside phone and confirm it reaches the selected destination.
- Check audio in both directions.

If outbound calls fail, check that your public IP is authorized in DIDforSale, **Enable Termination** is selected, and the outbound route uses this gateway. If incoming calls fail intermittently, check that **Provider IPs** contains the full incoming address list. On redundant installations, verify the provider ACL and inbound calling on each server that may receive calls.
