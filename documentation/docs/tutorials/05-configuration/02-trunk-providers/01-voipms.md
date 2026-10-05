---
id: voipms-trunk
title: VoIP.MS
slug: /trunk-providers/voipms/
sidebar_position: 1
---

# VoIP.ms

Connect FS PBX to VoIP.ms using a **registration-based SIP trunk**. Create a VoIP.ms subaccount, then use its username and password in an FS PBX gateway with **Register** set to **True**.

For a standalone FS PBX server, create one subaccount and one gateway. For two redundant FS PBX servers, create **two separate subaccounts and two gateways**, with one gateway assigned to each server.

## 1. Create the VoIP.ms subaccount

If you need a VoIP.ms account, [sign up using the FS PBX referral link](https://voip.ms/en/code/fspbx).

Sign in to the VoIP.ms customer portal and open **Sub Accounts → Create Sub Account**.

| Setting | Value |
| --- | --- |
| Protocol | **SIP** |
| Authentication type | **User/Password Authentication** |
| Username | Create a username for this FS PBX server. |
| Password | Set a password for this subaccount. |
| Device type | **Asterisk, IP PBX, Gateway or VoIP Switch** |
| CallerID Number | **I use a system capable of passing its own CallerID** |
| DTMF Mode | **RFC2833 (AVT)**, under Advanced Options. |

Save the subaccount and note its **full SIP username**. VoIP.ms adds your main account number to the username you choose: for example, `pbx1` becomes `100000_pbx1`. Use the full username in FS PBX. See the [VoIP.ms subaccount guide](https://wiki.voip.ms/article/Sub_Accounts) for the portal options.

If you have two redundant servers, repeat this step for the second server, using a different username such as `100000_pbx2`. Keep each subaccount's username and password together for the matching gateway.

## 2. Create the FS PBX gateway

Open **Accounts → Gateways** and click **Create**.

### Settings tab

| Setting | Value |
| --- | --- |
| Gateway | A recognizable name, such as `VoIP.ms - Server 1`. |
| Gateway Enabled | **On** |
| Proxy | A hostname from the [VoIP.ms server list](https://wiki.voip.ms/article/Servers), such as `losangeles1.voip.ms`. |
| SIP Profile | **external** |
| Context | **public** |
| Register | **True** |
| Username | The full SIP username for this subaccount, such as `100000_pbx1`. |
| Password | This subaccount's password. |
| Expire Seconds | **800** |
| Retry Seconds | **30** |

### Advanced tab

| Setting | Value |
| --- | --- |
| Domain | The FS PBX account that owns the gateway, or **Global** for an intentionally shared gateway. |
| From Domain | The same hostname entered in **Proxy**, such as `losangeles1.voip.ms`. |
| From User | The same full subaccount username entered in **Username**. |
| Auth Username | The same full subaccount username entered in **Username**. |
| Caller ID In From | **False** |
| SIP CID Type | **RPID** |
| FreeSWITCH Hostname | Leave blank for a standalone server. For redundant servers, enter the exact FreeSWITCH hostname of the server that should run this gateway; see the next section. |

**Username**, **From User**, and **Auth Username** must all match. **From Domain** must match **Proxy**. The separate **Domain** field selects the FS PBX account; it is not the VoIP.ms server address.

Leave the remaining advanced fields at their defaults unless your installation requires otherwise, then click **Save**.

## 3. Assign gateways to redundant servers

Skip this section if you have a standalone server.

With database replication, both gateway records appear on both FS PBX servers. **FreeSWITCH Hostname** controls which server actually runs each gateway. Each server loads only the gateway whose hostname matches its own.

For example, if your servers are named `ny01` and `ny02`:

| Gateway | VoIP.ms subaccount username | FreeSWITCH Hostname | Runs on |
| --- | --- | --- | --- |
| VoIP.ms - Server 1 | `100000_pbx1` | `ny01` | Server 1 only |
| VoIP.ms - Server 2 | `100000_pbx2` | `ny02` | Server 2 only |

Create the first gateway while signed in to Server 1 and the second while signed in to Server 2. Use the corresponding subaccount credentials and hostname for each. Allow replication to finish before creating the second gateway; there should be two gateway records in total, with both visible on each server.

The value must match the name reported by FreeSWITCH, called its **switchname**. An administrator can check it by running this command on each server:

```bash
fs_cli -x 'switchname'
```

Copy the returned name exactly into **FreeSWITCH Hostname**. The two servers must have different names. Use `ny01` and `ny02` only if those are the names returned by your servers.

:::important
Set **FreeSWITCH Hostname** on both gateways in a redundant setup. A blank value loads that gateway on every server, so both servers would try to register the same subaccount. A name that matches neither server prevents the gateway from loading on either one.
:::

This setting assigns each registration to a server. Configure incoming-call failover and outbound routes separately, as described below.

## 4. Set up incoming and outgoing calls

### Route your VoIP.ms phone number

In the VoIP.ms portal, open **DID Numbers → Manage DID(s)** and edit your number. On the **Edit DID Settings** page:

1. Under **Routing Settings → Main**, select the **SIP/IAX** radio button, then choose your first server's subaccount from the dropdown, such as `[sub account] SIP/100000_pbx1`.
2. Under **DID Point of Presence**, select the same VoIP.ms server used in the gateway's **Proxy** and **From Domain** fields. For example, select **United States → Los Angeles 1, CA (losangeles1.voip.ms)** when using `losangeles1.voip.ms`.
3. Save the changes.

For two redundant servers, also configure the second subaccount as the incoming-call fallback:

1. Click **Failover** if the failover tabs are not already visible.
2. Open the **If Unreachable** tab.
3. Select **SIP/IAX** and choose the second server's subaccount, such as `[sub account] SIP/100000_pbx2`.
4. Keep **DID Point of Presence** set to the same server selected for the main route, then save the changes.

Both FS PBX gateways must register to that same VoIP.ms POP. In this example, both use `losangeles1.voip.ms` for **Proxy** and **From Domain**, while each uses its own subaccount credentials and **FreeSWITCH Hostname**.

Incoming calls normally use the **Main** subaccount. VoIP.ms uses **If Unreachable** when the primary subaccount cannot be reached, such as when its registration is unavailable. **If Busy** and **If No Answer** are separate routing conditions. See [VoIP.ms DID routing and failover options](https://wiki.voip.ms/article/Manage_DID).

### Route the number inside FS PBX

For a simple test, [create an extension](/docs/getting-started/create-your-first-extension/) and register a phone or softphone using its SIP credentials.

1. Open **Dialplan → Phone Numbers** and click **Create**.
2. Enter your DID and country code in the appropriate fields.
3. Under **Call Routing**, click **Add**, choose **Extension**, and select the extension that should receive calls.
4. Click **Save**.

You can select another destination, such as a ring group or IVR, when ready. See [Phone Numbers](/docs/getting-started/phone-numbers/) for more on inbound routing.

### Create an outbound route

1. Open **Dialplan → Outbound Routes** and click **Create**.
2. Select your VoIP.ms gateway in **Gateway**.
3. Choose a **Common Pattern** that matches how users dial, and check the resulting **Dialplan Expression**.
4. Save the route and set the extension's outbound caller ID to the number you want to present.

For redundant servers, the outbound route must include both gateways so calls can use the gateway running on the current server. Select the first in **Gateway** and the second in **Alternate Gateway 1**.

## 5. Verify the setup

- In **Accounts → Gateways**, confirm the gateway shows **Running** and **Registered** on its assigned server. In a redundant setup, check each server directly; the other server's gateway should not be running locally.
- In VoIP.ms, check **Sub Account Registration Status** on the portal's main page and confirm each subaccount is registered.
- Make an outbound call and check the displayed caller ID and audio in both directions.
- Call the DID from an outside phone and confirm it reaches the selected destination.
- Test keypad input through an IVR to confirm DTMF works.
- For redundant servers, test inbound failover and outbound calling through each server before relying on the setup.

If a gateway does not register, check its subaccount credentials, **Register**, **Proxy**, and **FreeSWITCH Hostname**. If it registers but incoming calls do not arrive, check the DID's subaccount routing, POP, and FS PBX phone-number routing.
