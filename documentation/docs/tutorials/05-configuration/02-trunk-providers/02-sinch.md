---
id: sinch-trunk
title: Sinch
slug: /trunk-providers/sinch/
sidebar_position: 2
---

# Sinch

This guide connects FS PBX to a Sinch trunk that uses **IP authentication**. Sinch identifies your PBX by its public IP address, and FS PBX sends calls to the gateway address supplied by Sinch. Registration stays disabled for this setup.

## Before you begin

Have the gateway IP address and the full list of incoming SIP addresses or CIDR ranges provided by Sinch ready. Confirm that Sinch has authorized your PBX's public IP address for the trunk. Sinch's gateway address goes in **Proxy**, its incoming SIP addresses go in **Provider IPs**, and your public address identifies your PBX to Sinch.

## Create the gateway

Open **Accounts → Gateways** and click **Create**. Give the gateway a recognizable name, such as `Sinch`, and keep **Gateway Enabled** switched on.

Set the following values on the **Settings** and **Advanced** tabs, then add Sinch's addresses on **Provider IPs** before saving:

| Tab | Setting | Value |
| --- | --- | --- |
| Settings | Proxy | The IP address provided by Sinch. |
| Settings | Register | **False** |
| Advanced | Caller ID In From | **True** |

On the **Settings** tab, enter Sinch's IP address in **Proxy** and set **Register** to **False**. This directs calls to Sinch without attempting SIP registration.

Then open **Advanced** and set **Caller ID In From** to **True**. This places the call's caller ID number in the SIP From field sent to Sinch. See the [FreeSWITCH gateway documentation](https://developer.signalwire.com/freeswitch/FreeSWITCH-Explained/Configuration/Sofia-SIP-Stack/Gateways-Configuration_7144069) for more about this option.

## Add Sinch's provider IPs

Open the gateway's **Provider IPs** tab. Click **Add Item** and enter each incoming SIP address or CIDR range supplied by Sinch in a separate **IP / CIDR** row. Include every address Sinch may use to deliver calls, including any failover sources.

Click **Save**. FS PBX adds these addresses to the **providers** access control list (ACL) and reloads it on the current server, allowing inbound calls from Sinch.

:::important
Provider IPs are required for all IP-authenticated trunks. Setting **Proxy** alone does not populate the providers ACL. Complete this tab even when outbound calls already work.
:::

## Route and test calls

Once the gateway is saved, select it in your **Dialplan → Outbound Routes** configuration. For incoming calls, add your Sinch numbers under **Dialplan → Phone Numbers** and choose where each number should ring. The [Phone Numbers guide](/docs/getting-started/phone-numbers/) explains inbound routing.

Check the connection with a few calls:

- Make an outbound call and confirm that the expected caller ID appears.
- Call a Sinch number from an outside phone and confirm it reaches the selected destination.
- Check that audio works in both directions.

The gateway may show **Registration disabled**, which is expected for this trunk. If calls fail, check the Sinch proxy address, the public IP authorized by Sinch, and your call routes. If outbound calls work but inbound calls fail, also check that **Provider IPs** contains Sinch's complete incoming SIP address list.
