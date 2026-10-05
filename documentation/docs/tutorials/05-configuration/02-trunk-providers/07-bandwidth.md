---
id: bandwidth-trunk
title: Bandwidth
slug: /trunk-providers/bandwidth/
sidebar_position: 7
---

# Bandwidth

Connect FS PBX to Bandwidth using an **IP-authenticated SIP trunk**. This setup uses the **external** SIP profile with registration disabled. Bandwidth authorizes calls from your PBX's public IP address, so no gateway username or password is needed.

## Before you begin

Bandwidth supplies the proxy IP addresses during onboarding, either through your implementation specialist or in the welcome email. These are the addresses of its Session Border Controllers (SBCs). Use the addresses assigned to your service. Bandwidth provides a redundant pair and asks customers to configure both for outbound calling. See [Bandwidth's outbound calling integration guide](https://www.bandwidth.com/support/en/articles/12822927-outbound-calling-integration-guide).

Before creating the gateway:

- Confirm that Bandwidth has authorized your PBX's public IP address. For redundant FS PBX servers, provide each public IP that may send calls.
- Confirm that your Bandwidth numbers route to your PBX, using the destination SIP port and transport configured on its **external** SIP profile.
- Have the complete list of Bandwidth's incoming SIP signaling addresses ready for the **Provider IPs** tab.

Bandwidth's [PBX integration guide](https://www.bandwidth.com/support/en/articles/12822928-pbx-integration-guide) explains its IP authentication requirements.

## Create the gateway

Open **Accounts → Gateways** and click **Create**.

### Settings tab

| Setting | Value |
| --- | --- |
| Gateway | `Bandwidth01` |
| Gateway Enabled | **On** |
| Proxy | The outbound SBC IP address provided by Bandwidth. |
| SIP Profile | **external** |
| Context | **public** |
| Register | **False** |
| Username | Leave blank. |
| Password | Leave blank. |
| Expire Seconds | **800** |
| Retry Seconds | **30** |

Create a second gateway, such as `Bandwidth02`, with the second outbound SBC IP supplied by Bandwidth. Use the same settings for that gateway, then add it as an alternate in your outbound route as described below.

### Advanced tab

| Setting | Value |
| --- | --- |
| Domain | **Global** for a shared gateway, or the FS PBX account that owns this trunk. |
| Caller ID In From | **True** |
| FreeSWITCH Hostname | Leave blank to load the gateway on every FS PBX server. Enter a server's FreeSWITCH hostname only when the gateway should run on that server alone. |

**Caller ID In From** sends the call's caller ID number in the SIP From field. Leave **From User**, **From Domain**, and **Auth Username** blank, and keep the remaining advanced fields at their defaults.

### Provider IPs tab

Add Bandwidth's incoming SIP signaling addresses so FS PBX can accept calls from the provider. Bandwidth's [inbound calling integration guide](https://www.bandwidth.com/support/en/articles/12822925-inbound-calling-integration-guide) says to allow both SBC addresses supplied during onboarding.

1. Open **Provider IPs** and click **Add Item**.
2. Enter each incoming SIP address or CIDR range supplied by Bandwidth in a separate **IP / CIDR** row. Include both SBCs and any additional failover sources assigned to your service.
3. Click **Save** to save the gateway and its provider IPs.

FS PBX uses these entries to populate the **providers** access control list (ACL) and reloads it on the current server. Enter Bandwidth's addresses here; your PBX's public IP is the address Bandwidth must authorize on its side.

:::important
Provider IPs are required for all IP-authenticated trunks. Setting **Proxy** alone does not populate the providers ACL. Complete the **Provider IPs** tab even when outgoing calls already work.
:::

## Route and test calls

In **Dialplan → Outbound Routes**, select `Bandwidth01` as the **Gateway** and `Bandwidth02` as **Alternate Gateway 1**. This gives the route a second Bandwidth destination when the first cannot complete a call.

For incoming calls, add your Bandwidth numbers under **Dialplan → Phone Numbers** and choose the extension, ring group, or other destination that should receive each number. See [Phone Numbers](/docs/getting-started/phone-numbers/) for more on inbound routing.

- Confirm that both gateways are **Running**. **Registration disabled** is expected for this setup.
- Make an outbound call and check the displayed caller ID.
- Call a Bandwidth number from an outside phone and confirm it reaches the selected destination.
- Check audio in both directions and test calling through the alternate gateway before relying on failover.

If outbound calls fail, check the public IP authorized by Bandwidth, the assigned proxy addresses, and the FS PBX outbound route. If incoming calls fail, check the number's routing in Bandwidth, the destination SIP port, **Provider IPs**, and the FS PBX phone-number route. On redundant installations, verify the provider ACL and inbound calling on each server that may receive calls.
