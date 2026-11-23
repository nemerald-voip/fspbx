---
id: grandstream-ht8x1-fxo
title: Grandstream HT841 / HT881 FXO Calling
slug: /phone-provisioning/grandstream-ht8x1-fxo
sidebar_position: 8
---

# Grandstream HT841 / HT881 FXO calling

Connect an analog telephone line to FS PBX so incoming calls reach a local destination and local extensions can dial out through that line. This setup also works when the gateway is behind a remote router with NAT.

The gateway registers an FXO port to FS PBX as an extension. FS PBX sends outgoing calls back through that registration using a saved Bridge and an outbound route.

## Before you start

The HT841 has four FXO ports; the HT881 has eight. Both have one FXS port for an analog phone or fax machine. Connect the telephone provider's line to an **FXO** port.

This guide uses the following example. Replace the extension and destination numbers to suit your account.

| Item | Example |
| --- | --- |
| Physical telephone-line port | FXO 3 |
| Extension assigned to that port | `9000` |
| FXO profile for manual setup | FXO Profile 2 |
| Incoming-call destination in FS PBX | `2000` |
| SIP transport | TCP |
| Outside-line access prefix | `9` |

Create extension `9000` in the same FS PBX account as the local extensions that will use the line. Destination `2000` must already exist in that account; it can be an extension, ring group, IVR, or another dialable destination.

If provisioning the gateway from FS PBX, select the **HT8x1** Device Template and assign extension `9000` to **device Line 4**. Device Line 1 is FXS, so device Line 4 corresponds to physical FXO 3. See the [Grandstream provisioning guide](/docs/phone-provisioning/grandstream/#ht841--ht881-gateways) for the complete port mapping and configuration-server setup.

## Register the FXO port

If the port is already registered, retain its working account and connection settings and continue to incoming-call forwarding.

For manual setup, open the gateway's web interface and configure **FXO Profile 2**. Enter the FS PBX SIP server in **Primary SIP Server**, including its port when required, such as `pbx.example.com:5060` for an account using TCP port `5060`. Enter an **Outbound Proxy** if your connection settings require one. **Local SIP Port** is the gateway's own listening port, not the PBX destination port.

Set **SIP Registration** to **Yes**, **SIP Transport** to **TCP**, and **NAT Traversal** to **Keep-alive** for this gateway behind NAT. The server, port, and transport must match the connection settings for the FS PBX account.

Under **FXO Ports**, enter the following in row **3**:

| Field | Value |
| --- | --- |
| SIP User ID | `9000` |
| SIP Authenticate ID | `9000`, or the authentication ID configured for that extension |
| SIP Authentication Password | Extension `9000`'s SIP password |
| Name | A descriptive label, such as `HT841 FXO 3` |
| Profile ID | FXO Profile 2 |
| Hunting Group | Disabled |
| Routing ID / Request URI Routing ID | Leave blank |
| Enable Port | Yes |
| Unconditional Call Forward to PSTN | Leave blank |

Save and apply the settings. Open **Registrations** in FS PBX and confirm that `9000` is registered with the expected gateway and TCP transport. The SIP password is separate from the gateway administrator password and provisioning HTTP password.

If automatic provisioning assigned the port to FXO Profile 1, use that profile for the remaining device settings. The profile assigned to the port is what matters.

## Incoming telephone-line calls

On the gateway's **FXO Ports** page, scroll below the account rows to **Unconditional Call Forward to VOIP**. Configure row **3**:

| Field | Value |
| --- | --- |
| CID | `2000`, the destination to ring in FS PBX |
| SIP Server | Your reachable FS PBX SIP server hostname |
| SIP Destination Port | The PBX SIP listening port for this connection |

Use the PBX's destination port here, not the gateway's local SIP port. Leave **Unconditional Call Forward to PSTN** in the upper account row blank; that field forwards calls in the opposite direction.

Save and apply, then call the analog line's telephone number from an outside phone. The call should reach destination `2000`. Do not set the incoming destination to `9000`, which would send the call back toward the same FXO port.

For an automatically provisioned device, also save `grandstream_ht8x1_fxo_3_destination` with value `2000` in FS PBX, as described under [Keep settings after provisioning](#keep-settings-after-provisioning).

## Outgoing calls from local extensions

### Set one-stage dialing on the gateway

Open the profile assigned to FXO 3, **FXO Profile 2** in this example. Under **Channel Dialing**, set **Stage Method (1/2)** to **1**. In the same profile, set **Check SIP User ID for incoming INVITE** to **No**, because outgoing calls carry the outside telephone number as their destination.

Keep SIP registration enabled and retain the working incoming-call forwarding. Save and apply the changes. These device options are described in the [Grandstream HT841 / HT881 manual](https://documentation.grandstream.com/knowledge-base/ht841-ht881-user-manual/).

With **Stage Method = 2**, the alternative is to call `9000`, wait for the analog dial tone, and then dial the outside number. The following Bridge and route use **Stage Method = 1** so users dial the complete number once.

### Create the Bridge

In the same FS PBX account, open **Bridges** and add a Bridge:

| Field | Value |
| --- | --- |
| Name | `HT841 FXO 3` |
| Bridge Enabled | On |
| Action | Leave unselected |
| Destination Number | Leave blank |

Open **Advanced** and paste the following into **Raw Destination** as one line:

```text
${regex(${sofia_contact(*/9000@${domain_name})}|^(.*sofia/[^/]+/)(sip:)?[^@]+(@[^,]+)$|%1sip:${callee_id_number}%3)}
```

Replace only `9000` if you assigned a different extension to the port. Keep `${domain_name}` and `${callee_id_number}` exactly as written. Automatic text wrapping in the field is fine; do not insert line breaks or spaces into the expression. Save the Bridge.

The Bridge looks up the port's current registration and sends the outside number while retaining its TCP and NAT connection details. You do not need to enter the gateway's remote IP address in the Bridge. Use a dedicated extension with a single registration for this FXO port.

### Create the outbound route

Open **Outbound Routes** and add a route. This example uses North American numbers and an access prefix of `9`.

| Field | Value |
| --- | --- |
| Name | `HT841 outbound` |
| Gateway | `HT841 FXO 3` under **Bridges** |
| Dialplan Expression | `^9(?:\+?1)?([2-9]\d{2}[2-9]\d{2}\d{4})$` |
| Prefix Number | Leave blank |
| Domain | The account containing extension `9000` |
| Context | That account's context |
| Order | Before any other route that also matches these numbers |
| Enabled | Yes |

**Domain**, **Context**, and **Order** are under the route's **Advanced** tab. Use the same account and context as the calling extensions. Save the route.

This pattern removes the access prefix `9` and an optional leading `1` or `+1`, then sends **10 digits** to the analog line. For example, both `96105550123` and `916105550123` send `6105550123`. These are example numbers; use a reachable telephone number when testing.

If the telephone provider requires 11 digits, use this expression instead and dial `9`, then `1`, then the 10-digit number:

```text
^9([0-9]{10,11})$
```

This alternative removes only the initial `9` and preserves the following 10 or 11 digits. Choose the pattern that produces the digits your analog provider accepts. These examples do not cover short codes or international dialing.

When reviewing an existing route under **Rules**, keep the `${user_exists}` condition set to `false` and set the `destination_number` condition to the chosen pattern. Confirm that the enabled `set` action contains `callee_id_number=$1` before the action that calls the saved Bridge. Creating the route with the Bridge selected supplies those actions.

## Keep settings after provisioning

If FS PBX provisions this gateway, keep the matching settings under **Devices → edit device → Advanced**. Enter the setting name under **Name**, its value under **Value**, and enable each row:

| Name | Value for this example |
| --- | --- |
| `grandstream_ht8x1_fxo_3_destination` | `2000` |
| `grandstream_ht8x1_fxo_stage_method` | `1` |

Keep extension `9000` assigned to device **Line 4**. Save and sync the device. The destination setting applies to physical FXO 3; the stage-method setting applies to both FXO profiles. Device settings entered only in the gateway interface can be replaced by later provisioning.

## Check calls and line availability

1. Confirm extension `9000` is registered in FS PBX.
2. Call the analog telephone number from outside and confirm destination `2000` rings.
3. From a local extension, dial `9` followed by an outside number in the selected format. The gateway should dial through FXO 3 without a second dial tone.
4. Check audio in both directions. End calls from each end and confirm the analog line becomes available again.

Each FXO port handles one call at a time. With hunting disabled, this Bridge uses FXO 3 only. A call already using that line can prevent another incoming or outgoing call on it. For another physical line, assign a separate extension, create a Bridge using that extension's number, and select it in the appropriate outbound route.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Incoming calls do not reach FS PBX | Use **Unconditional Call Forward to VOIP** for the correct physical port. Check its destination, PBX server, and PBX SIP port. |
| Outgoing calls give a second dial tone | Set **Stage Method** to **1** in the profile assigned to the port. |
| Gateway rejects outside numbers | Set **Check SIP User ID for incoming INVITE** to **No** and leave **Routing ID** blank for this setup. |
| A different provider handles the call | Check the dialed prefix, route pattern, account/context, route order, and selected Bridge. |
| Gateway receives the call but the number fails | Check whether the analog provider expects 10 or 11 digits and whether the route preserves or removes the leading `1`. |
| Calls fail after the gateway has been idle | Confirm its registration is still present and review NAT keep-alive settings on the gateway and router. |
| Settings stop working after a sync | Save the incoming destination and stage method in the device's FS PBX **Advanced** settings. |
| Line stays busy after hangup | Review the provider's disconnect-detection requirements in the [Grandstream line settings](/docs/phone-provisioning/grandstream/#outgoing-calls-and-line-settings). |
