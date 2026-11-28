---
id: grandstream-ht8x1-fxo
title: Grandstream HT841 / HT881 FXO Calling
slug: /phone-provisioning/grandstream-ht8x1-fxo
sidebar_position: 8
---

# Grandstream HT841 / HT881 FXO calling

An analog line can use the same FS PBX inbound route as a SIP carrier. Each FXO port registers with its own extension and forwards incoming calls to the **DID assigned to that physical line**. For outgoing calls, FS PBX uses the ports' current registrations, so the gateway can be behind NAT with a changing IP address.

## Before you start

Connect each provider line to an **FXO** port. The HT841 has four and the HT881 has eight. Both have a separate **FXS** port for a phone or fax machine. This example uses two North American lines in the same FS PBX account.

| Physical port | FS PBX device line | Registration extension | Line's DID sent to FS PBX |
| --- | --- | --- | --- |
| FXO 1 | 2 | `921` | `16105550101` |
| FXO 2 | 3 | `922` | `16105550102` |

## Incoming telephone-line calls

1. In **Phone Numbers**, add each line's DID to the account that receives its calls. This creates the inbound route. Choose the destination, such as Business Hours, an IVR or a ring group, and configure any recording there.
2. In **Extensions**, create one extension per FXO port. Select **Use public inbound routes**. For an existing extension, set **Advanced Settings → Context** to `public`. Leave **First Name** and **Last Name** blank to preserve the outside caller's name. Use **Description** for your port label, such as `Front office copper line 1`. The context control requires the extension user-context permission. API v1 also accepts `user_context` on create and update.
3. In **Devices**, select the Grandstream **HT8x1** template. Assign each port's extension to the device line in the table and select **TCP** on each line. Use the account's SIP server and listening port. Device Line 1 is the separate FXS port. [Grandstream port mapping and provisioning setup](/docs/phone-provisioning/grandstream/#ht841--ht881-gateways).
4. Under **Devices → edit device → Advanced**, add and enable the following settings. Replace the example DIDs with the numbers on your copper lines, in a format their inbound routes match. Save and provision the gateway.

| Name | Value |
| --- | --- |
| `grandstream_ht8x1_fxo_1_destination` | `16105550101` |
| `grandstream_ht8x1_fxo_2_destination` | `16105550102` |

### Register the FXO port

For manual setup, enable **SIP Registration** on the assigned FXO profile. Set **Primary SIP Server** to the reachable PBX host and port, **SIP Transport** to **TCP**, and **NAT Traversal** to **Keep-alive** for a remote gateway. Enter the account's outbound proxy if required.

Under **FXO Ports**, enter each port's extension as **SIP User ID**, its authentication ID and SIP password, and the assigned profile ID. Enable the port, disable **Hunting Group**, and leave **Routing ID** and **Unconditional Call Forward to PSTN** blank.

In **Unconditional Call Forward to VoIP**, enter that port's DID in **CID**, the PBX hostname in **SIP Server**, and the PBX listening port in **SIP Destination Port**. For FXO 1 above, **CID** is `16105550101`. The outside caller's number comes from the analog line. Set **Number of Rings** to `2` and **Tel URI** to **Disabled** on both FXO profiles.

Open **Registrations** in FS PBX and confirm a single TCP registration for each port extension. Call each DID from an outside phone. Check its destination and confirm **Call Detail Records** shows an inbound call from the outside caller to the DID. To move a DID to a SIP carrier later, have the carrier deliver the same DID to FS PBX. Its Phone Numbers settings still control the call.

## Outgoing calls from local extensions

On the assigned FXO profile, use **Stage Method = 1** so the gateway dials the outside number in the SIP INVITE. **Check SIP User ID for incoming INVITE** must be **No** for this setup. The FS PBX template defaults to stage 1 and **Wait for Dial-Tone = No**. Apply the settings. On firmware 1.0.9.9, reboot after changing Stage Method if the old behavior persists.

### Create the outbound route

1. Open **Outbound Routes → Create** in the account containing the ports. Name the route and choose extension `921` under **Public-context extensions** as **Gateway**. Choose `922` as **Alternate Gateway 1**. For a third port use **Alternate Gateway 2**. A single-line site needs only the first port.
2. Under **Dialplan Expression**, enter the pattern below if users dial `9` for an outside line and your provider accepts ten digits. Leave **Prefix Number** blank. Under **Advanced**, select the calling extensions' account and context, and order the route before other routes matching those digits. To reserve lines for particular extensions, use a **Toll Allow** condition that matches those extensions. Save.

```text
^9(?:\+?1)?([2-9]\d{2}[2-9]\d{2}\d{4})$
```

Both `96105550123` and `916105550123` send `6105550123` to the analog line. If the provider requires 11 digits, use `^9(1[2-9]\d{2}[2-9]\d{2}\d{4})$` and dial `9` plus the full 11 digits.

### Hunt across ports

Open the saved route's **Rules** tab. The `destination_number` pattern captures the number to dial. Under **Then**, keep `set callee_id_number=$1` and `set hangup_after_bridge=true` ahead of the bridge actions. Routes created with only FXO ports use `set continue_on_fail=USER_BUSY` to advance on a busy response. If an existing route already handles other failure causes intentionally, include `USER_BUSY` in its list.

The following are the **Data** values for two separate **bridge** actions, in order. Each looks up the current registration and preserves its Sofia profile, transport, and NAT path. To hunt across four or eight ports, add one action per additional connected port in the same rule, changing only the extension number.

```text
${regex(${sofia_contact(*/921@${domain_name})}|^(.*sofia/[^/]+/)(?:sip:)?[^@]+(@[^,]+)$|%1sip:${callee_id_number}%2)}
```

```text
${regex(${sofia_contact(*/922@${domain_name})}|^(.*sofia/[^/]+/)(?:sip:)?[^@]+(@[^,]+)$|%1sip:${callee_id_number}%2)}
```

Place a call that occupies FXO 1, then dial a second outside number from another extension. If port 1 rejects the attempt as busy (SIP 486), FS PBX tries port 2. Once the gateway answers, an audible busy tone from the far end is already part of the call and cannot trigger this hunt. Check two-way audio, hang up, and verify that the next call can use port 1 again.

### Existing single-port Bridges and internal forwarding

A working saved Bridge can stay selected as the outbound route's **Gateway**. To create one, open **Bridges** in the same account, give it a name, enable it, leave **Action** unselected, and paste the corresponding port expression above into **Advanced → Raw Destination**. The outbound route must set `callee_id_number=$1` before calling the Bridge.

Sites that intentionally forward analog calls straight to an internal destination can keep the port extension in its account context and enter that destination under **Unconditional Call Forward to VoIP**. To retain two-stage dialing after provisioning, set `grandstream_ht8x1_fxo_stage_method` to `2` before syncing. [HT8x1 device settings](/docs/phone-provisioning/grandstream/#outgoing-calls-and-line-settings).

## Troubleshooting

| Symptom | Check |
| --- | --- |
| The port registers but an incoming call misses its route | Compare the port's **VoIP forward CID** with the DID under **Phone Numbers**. Check the port extension's **Context** is `public` and the forwarding server and port reach the PBX. |
| The port label replaces the outside caller's name | Clear **First Name** and **Last Name** on the FS PBX port extension. Put the label in **Description**. |
| Caller ID is missing | Check whether the gateway decoded caller ID from the line. Its `X-ATA-CallerID` SIP header can show what it received. If the number is there but **From** contains the port ID, check **Tel URI** is **Disabled** and review **From Domain**. If caller ID arrives late, try increasing **Number of Rings**. |
| Only calls with long caller names fail over UDP | Set the device lines to **TCP** and confirm TCP in **Registrations**. A large UDP INVITE can fragment. Where both ends support them, selecting only PCMU and PCMA also reduces the codec list. |
| Outgoing calls return 403 or ask for more digits | Check the assigned profile's **Stage Method** and **Check SIP User ID for incoming INVITE**. Reboot after applying a Stage Method change on affected firmware. |
| One line returns 503 after waiting for dial tone | Test **Wait for Dial-Tone = No** on its FXO profile. Compare the provider's dial tone and regional settings with a working line. |
| Calls stop after an idle period, or a line stays busy after hangup | Verify the registration and NAT keep-alive. On the gateway, match disconnect detection and AC termination to the analog provider. Use the local country for country-based termination, or select auto-detect where appropriate. [Grandstream HT841 / HT881 manual](https://documentation.grandstream.com/knowledge-base/ht841-ht881-user-manual/). |
