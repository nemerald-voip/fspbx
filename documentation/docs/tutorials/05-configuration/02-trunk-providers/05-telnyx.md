---
id: telnyx-trunk
title: Telnyx
slug: /trunk-providers/telnyx/
sidebar_position: 5
---

# Telnyx

Connect FS PBX to Telnyx using an **IP-authenticated SIP trunk**. This setup uses `sip.telnyx.com` for outgoing calls and the **external** SIP profile in FS PBX. SIP registration stays disabled, and Telnyx identifies your PBX by its public IP address.

## Before you begin

Sign in to the [Telnyx Mission Control portal](https://portal.telnyx.com) and prepare the connection:

1. Open **Voice → SIP Trunking**, create a **SIP Connection**, and select **IP Address** as the connection type.
2. Add your PBX's public IP address. Set the destination SIP port and inbound transport to match the **external** SIP profile receiving calls in FS PBX.
3. In the connection's **Inbound** settings, select the **US** SIP region for the example in this guide. This determines which Telnyx addresses deliver incoming calls.
4. Create or select an **Outbound Voice Profile** and attach the SIP Connection to it so outbound calling is enabled.
5. Assign your Telnyx phone numbers to this SIP Connection and save the changes.

See [Telnyx's SIP trunk setup guide](https://support.telnyx.com/en/articles/8096455-how-to-configure-a-sip-trunk) and [IP Address connection instructions](https://support.telnyx.com/en/articles/4245868-sip-connection-types) for the portal steps. Telnyx explains the inbound region selection in its [SIP signaling guide](https://sip.telnyx.com/).

## Create the gateway

Open **Accounts → Gateways** and click **Create**.

### Settings tab

| Setting | Value |
| --- | --- |
| Gateway | `Telnyx` |
| Gateway Enabled | **On** |
| Proxy | `sip.telnyx.com` |
| SIP Profile | **external** |
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

Add Telnyx's incoming SIP signaling addresses so FS PBX can accept calls from the provider.

1. Open **Provider IPs** and click **Add Item**.
2. Enter each signaling IP address for your selected inbound SIP region in a separate **IP / CIDR** row.
3. Click **Save** to save the gateway and its provider IPs.

For the **US** SIP region used in this guide, Telnyx publishes these two signaling addresses:

```text
192.76.120.10
64.16.250.10
```

Add both addresses. Check the current [Telnyx SIP Signaling Addresses](https://sip.telnyx.com/) when configuring the gateway. If you select another inbound SIP region in Telnyx, use every signaling address listed for that region.

FS PBX uses these entries to populate the **providers** access control list (ACL) and reloads it on the current server. Enter Telnyx's addresses here; your PBX's public IP belongs in the Telnyx SIP Connection.

:::important
Provider IPs are required for all IP-authenticated trunks. Setting **Proxy** alone does not populate the providers ACL. Complete the **Provider IPs** tab even when outgoing calls already work.
:::

## Route and test calls

Select `Telnyx` in **Dialplan → Outbound Routes**. For incoming calls, add your Telnyx numbers under **Dialplan → Phone Numbers** and choose the extension, ring group, or other destination that should receive each number. See [Phone Numbers](/docs/getting-started/phone-numbers/) for more on inbound routing.

- Confirm that the gateway is **Running**. **Registration disabled** is expected for this setup.
- Make an outbound call and check the displayed caller ID.
- Call a Telnyx number from an outside phone and confirm it reaches the selected destination.
- Check audio in both directions.

If outbound calls fail, check the public IP on the Telnyx SIP Connection, its Outbound Voice Profile, and the FS PBX outbound route. If incoming calls fail, check the number's SIP Connection assignment, the destination SIP port, **Provider IPs**, and the FS PBX phone-number route. On redundant installations, verify the provider ACL and inbound calling on each server that may receive calls.
