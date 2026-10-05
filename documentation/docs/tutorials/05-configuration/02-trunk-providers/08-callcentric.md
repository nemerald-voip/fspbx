---
id: callcentric-trunk
title: CallCentric
slug: /trunk-providers/callcentric/
sidebar_position: 8
---

# CallCentric

Connect FS PBX to CallCentric using a **registration-based SIP trunk**. FS PBX registers to `sip.callcentric.net` with your CallCentric SIP username and password. This setup uses the **external** SIP profile with **Register** set to **True**.

## Before you begin

Sign in to [My CallCentric](https://my.callcentric.com/) and open **Extensions**. Select the extension that FS PBX will use and click **Modify** to set its SIP password. This is the extension's **SIP/Phone password**, which can differ from your web login password.

Use the correct SIP username for that extension:

- For the default extension, **100**, use your full CallCentric account number beginning with `1777`.
- For another extension, append its three-digit extension number to the account number. For example, account `17770001234` and extension `101` use `17770001234101`.

Use this full username in all three FS PBX fields: **Username**, **From User**, and **Auth Username**. Your purchased phone number (DID) is configured separately for inbound routing.

See CallCentric's [extension instructions](https://www.callcentric.com/faq/35) and [SIP configuration guide](https://www.callcentric.com/support/device/other) for its credential and server settings.

## Create the gateway

Open **Accounts → Gateways** and click **Create**.

### Settings tab

| Setting | Value |
| --- | --- |
| Gateway | `CallCentric` |
| Gateway Enabled | **On** |
| Proxy | `sip.callcentric.net` |
| SIP Profile | **external** |
| Context | **public** |
| Register | **True** |
| Username | Your full CallCentric SIP username, as described above. |
| Password | The SIP/Phone password for that CallCentric extension. |
| Expire Seconds | **800** |
| Retry Seconds | **30** |

### Advanced tab

| Setting | Value |
| --- | --- |
| Domain | **Global** for a shared gateway, or the FS PBX account that owns this trunk. |
| From User | The same full SIP username entered in **Username**. |
| Auth Username | The same full SIP username entered in **Username**. |
| From Domain | `sip.callcentric.net` |
| Realm | `sip.callcentric.net` |
| Outbound Proxy | `sip.callcentric.net` |
| Caller ID In From | **True** |
| FreeSWITCH Hostname | Leave blank for a standalone server. On redundant servers, enter the exact FreeSWITCH hostname of the server that should run this registration. |

**Proxy**, **From Domain**, **Realm**, and **Outbound Proxy** all use `sip.callcentric.net`. The separate **Domain** field selects the FS PBX account that owns the gateway. Leave the remaining advanced fields at their defaults.

For redundant installations, use a separate CallCentric extension and gateway for each server's registration. Set **FreeSWITCH Hostname** on each gateway to the name reported by `fs_cli -x 'switchname'` on its assigned server. Incoming-call failover must also be configured in CallCentric; assigning a gateway to a server does not create a failover rule.

### Provider IPs tab

Add CallCentric's published networks to allow incoming SIP traffic through the FS PBX **providers** access control list (ACL).

1. Open **Provider IPs** and click **Add Item**.
2. Add each network below in a separate **IP / CIDR** row.
3. Click **Save** to save the gateway and its provider IPs.

```text
204.11.192.0/22
199.87.144.0/21
```

Check CallCentric's current [network address list](https://www.callcentric.com/faq/9/254) when configuring the gateway. FS PBX populates the providers ACL from these entries and reloads it on the current server. **Register** stays **True**; the gateway continues to authenticate with its SIP username and password.

## Route incoming and outgoing calls

In CallCentric, use **DID Forwarding** or **Call Treatments** to send each phone number to the CallCentric extension registered by this gateway. Check existing forwarding rules if calls go to another destination. See [CallCentric's incoming-call routing options](https://www.callcentric.com/faq/18).

In FS PBX:

1. Open **Dialplan → Phone Numbers**, add your CallCentric DID, and choose the extension, ring group, or other destination that should receive calls. See [Phone Numbers](/docs/getting-started/phone-numbers/) for the setup steps.
2. Open **Dialplan → Outbound Routes** and select `CallCentric` as the gateway for the numbers users will dial.
3. Set the outbound caller ID for your FS PBX extensions and verify the number presented on a test call.

:::important Incoming number matching
CallCentric sends the dialed DID in the **SIP To header**, as documented in its [DID routing example](https://www.callcentric.com/support/device/asterisk/17_pjsip). Have your administrator verify that FS PBX uses this called number for inbound number matching, especially when several DIDs share one gateway. In FreeSWITCH, this value is `sip_to_user`. A gateway can be registered successfully while incoming calls still fail because the wrong number is used to find the route.
:::

## Verify the setup

- In **Accounts → Gateways**, confirm the gateway is **Running** and **Registered**.
- In My CallCentric, confirm that the selected extension shows as registered.
- Make an outbound call and check the displayed caller ID and audio in both directions.
- Call each DID from an outside phone and confirm it reaches the correct FS PBX destination.
- Test keypad input through an IVR if you use one.

If registration fails, check the full SIP username, the extension's SIP password, and the server fields above. If the gateway registers but incoming calls fail, check CallCentric's forwarding rules, **Provider IPs**, and the incoming number used to match your FS PBX phone-number route. For redundant installations, verify registration and the provider ACL on each assigned server before testing failover.
