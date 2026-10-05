---
id: questblue-trunk
title: QuestBlue
slug: /trunk-providers/questblue/
sidebar_position: 6
---

# QuestBlue

Connect FS PBX to QuestBlue using an **IP-authenticated SIP trunk**. This setup sends outgoing calls to `sbc.questblue.com` using the **external** SIP profile. QuestBlue identifies your PBX by its public IP address, so SIP registration stays disabled and no gateway username or password is needed.

## Before you begin

Sign in to the [QuestBlue customer portal](https://customer.questblue.com/) and prepare the trunk:

1. Open **SIP Trunks → Trunks**, click **Add Trunk**, and give the trunk a recognizable name.
2. Choose **Static IP Trunk**, select the appropriate region, and enter your PBX's public static IP address. Note the region's SBC address before saving.
3. Open **Telephone Numbers → Telephone Numbers** and edit each number you want to send to FS PBX. Select the new trunk in the **Trunk** dropdown and save.

These portal steps are also covered in [PortSIP's QuestBlue setup guide](https://support.portsip.com/portsip-communications-solution/configuring-sip-trunks/questblue-sip-trunk/configuring-questblue-ip-authentication-trunk).

Confirm that QuestBlue delivers calls to the SIP port and transport used by your **external** SIP profile. Obtain QuestBlue's complete, current list of incoming SIP signaling IP addresses or CIDR ranges from its portal or support team; you will add these to **Provider IPs** below.

## Create the gateway

Open **Accounts → Gateways** and click **Create**.

### Settings tab

| Setting | Value |
| --- | --- |
| Gateway | `QuestBlue` |
| Gateway Enabled | **On** |
| Proxy | `sbc.questblue.com` |
| SIP Profile | **external** |
| Context | **public** |
| Register | **False** |
| Username | Leave blank. |
| Password | Leave blank. |
| Expire Seconds | **800** |
| Retry Seconds | **30** |

Use the SBC address supplied for your trunk if QuestBlue assigns a different one.

### Advanced tab

| Setting | Value |
| --- | --- |
| Domain | **Global** for a shared gateway, or the FS PBX account that owns this trunk. |
| Caller ID In From | **True** |
| FreeSWITCH Hostname | Leave blank to load the gateway on every FS PBX server. Enter a server's FreeSWITCH hostname only when the gateway should run on that server alone. |

**Caller ID In From** sends the call's caller ID number in the SIP From field. Leave **From User**, **From Domain**, and **Auth Username** blank, and keep the remaining advanced fields at their defaults.

### Provider IPs tab

Add QuestBlue's incoming SIP signaling addresses so FS PBX can accept calls from the provider.

1. Open **Provider IPs** and click **Add Item**.
2. Enter each IP address or CIDR range supplied by QuestBlue in a separate **IP / CIDR** row. Include every source that may deliver calls, including failover addresses.
3. Click **Save** to save the gateway and its provider IPs.

FS PBX uses these entries to populate the **providers** access control list (ACL) and reloads it on the current server. Enter QuestBlue's addresses here; your PBX's public IP belongs in the QuestBlue trunk configuration.

:::important
Provider IPs are required for all IP-authenticated trunks. Setting **Proxy** alone does not populate the providers ACL. Complete the **Provider IPs** tab even when outgoing calls already work.
:::

## Route and test calls

Select `QuestBlue` in **Dialplan → Outbound Routes**. For incoming calls, add your QuestBlue numbers under **Dialplan → Phone Numbers** and choose the extension, ring group, or other destination that should receive each number. See [Phone Numbers](/docs/getting-started/phone-numbers/) for more on inbound routing.

- Confirm that the gateway is **Running**. **Registration disabled** is expected for this setup.
- Make an outbound call and check the displayed caller ID.
- Call a QuestBlue number from an outside phone and confirm it reaches the selected destination.
- Check audio in both directions.

If outbound calls fail, check the public IP authorized by QuestBlue, the proxy address, and the FS PBX outbound route. If incoming calls fail, check the number's trunk assignment in QuestBlue, the destination SIP port, **Provider IPs**, and the FS PBX phone-number route. On redundant installations, verify the provider ACL and inbound calling on each server that may receive calls.
