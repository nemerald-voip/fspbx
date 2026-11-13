---
id: yealink-rps
title: Yealink RPS
slug: /phone-provisioning/yealink-rps
sidebar_position: 4
---

# Yealink RPS

Yealink RPS is Yealink's **Redirection and Provisioning Service**. It stores a phone MAC address and the provisioning server assigned to that phone. On initial startup or after a factory reset, a supported Yealink phone can contact RPS and be redirected to FS PBX.

## Why use Yealink RPS?

Yealink RPS is useful when:

- Phones are delivered directly to users outside the office.
- The local network cannot provide DHCP option 66.
- You need repeatable, low-touch deployment across many locations.
- You want to reduce manual URL and credential entry.
- FS PBX should remain the source of each phone's complete configuration.

After you add the phone to RPS, connect it to the internet. It can then find FS PBX and download its settings.

Yealink describes RPS as a way to bind a phone MAC address to a provisioning-server URL for initial redirection. See the official [Yealink RPS overview](https://www.yealink.com/en/onepage/yealink-free-remote-provisioning-service).

## Before you start

You need:

- Access to the Yealink RPS Service portal with API Service enabled.
- The **Domain**, **AccessKey ID**, and **AccessKey Secret** shown under **Authentication Info** at **System > Integration > API**.
- A supported Yealink phone and its MAC address.
- The phone's serial number for regular Yealink accounts. MAC-only registration requires authorization from Yealink.
- A public FS PBX hostname with a trusted HTTPS certificate.
- Network access from the phone to Yealink RPS and FS PBX.
- An FS PBX device record with the correct Yealink template and line assignment.
- FS PBX provisioning HTTP credentials, if authentication is enabled.

The AccessKey credentials are API credentials for FS PBX to manage RPS. They are not the credentials a phone uses to download its configuration.

## Prepare FS PBX provisioning

1. Confirm that your public FS PBX hostname is correct. The provisioning address should look like:

   ```text
   https://pbx.example.com/prov/
   ```

2. Configure `http_auth_username` and `http_auth_password` in the `provision` category under **Default Settings** or as account overrides under **Domain Settings**.
3. Create the phone under **Devices**:
   - Enter the MAC address.
   - Select the Yealink device template matching the phone.
   - Assign the required extension or lines.
   - Save the device.

Provisioning HTTP credentials are separate from the Yealink AccessKey credentials and the extension's SIP credentials.

## Connect FS PBX to Yealink RPS

### 1. Add the YMCS API credentials

1. Sign in to the Yealink **RPS Service** portal.
2. In the left navigation, expand **System**.
3. Expand **Integration**.
4. Click **API**.
5. On the **API Service** page, locate **Authentication Info** at the top.
6. Note the **Domain**, then reveal or copy the **AccessKey ID** and **AccessKey Secret**.
7. Open **Devices** in FS PBX.
8. Click **Vendor Cloud** and open the **Yealink** tab.
9. Click **API Credentials**.
10. Select the **API Domain** matching the Domain shown by Yealink.
11. Enter the **AccessKey ID** and **AccessKey Secret** from the same **Authentication Info** section.
12. Enable **Require serial number** if you have a regular Yealink account. Leave it off if Yealink has authorized MAC-only registration.
13. Click **Save**.

**Require serial number** applies to every FS PBX account using these Yealink credentials. Enable it for a regular Yealink account; leave it off only if Yealink has authorized MAC-only registration.

| Require serial number | Required device values |
| --- | --- |
| Off | MAC address |
| On | MAC address and serial number |

Use only the three values in the top **Authentication Info** section. Do not use the **Event Subscription** token or the username and password under **RPS XML API**. If the portal also shows **Historical authentication > RPS Json**, do not use those credentials either.

This connection is shared by all FS PBX accounts using Yealink RPS. Limit access to the administrators responsible for those accounts.

### 2. Create or connect an RPS server

1. On the Yealink tab, click **Create RPS Server**.
2. Choose one of the following:
   - **Create New Server** creates a server in Yealink RPS and connects it to the current FS PBX account.
   - **Connect to Existing** selects a server already available through the connected YMCS account.

When creating a server, configure:

- **Server Name:** A short name for the customer or deployment.
- **Provisioning URL:** Normally `https://pbx.example.com/prov/`.
- **Provisioning Username:** The FS PBX HTTP provisioning username.
- **Provisioning Password:** The FS PBX HTTP provisioning password.

Check the suggested provisioning URL, username, and password before saving.

Each FS PBX account is paired with one Yealink RPS server. The AccessKey credentials can access all servers permitted by the connected YMCS account.

## Add a phone to Yealink RPS

1. Open **Devices** and edit the Yealink phone.
   If **Require serial number** is enabled, enter the phone's **Serial Number** and save the device before adding it to RPS.
2. Open the **Yealink RPS** tab.
3. Click **Add to Yealink RPS**.
4. Wait for the action to complete.
5. Return to the Devices list or reopen the device to refresh its status.

Before adding several phones at once, save a serial number for each phone that requires one. If an error lists missing serial numbers, fill them in and retry the selection.

The Vendor Cloud status progresses through:

| Status | Meaning |
| --- | --- |
| Adding to Yealink RPS | The phone is being added. |
| Listed in Yealink RPS | Yealink accepted the device record. |
| Could not add to Yealink RPS | Open the device's Yealink RPS tab to review the error. |
| Removing from Yealink RPS | FS PBX is removing the cloud record. |
| Not listed in Yealink RPS | FS PBX has no successful Yealink listing for the device. |

If the phone was previously configured, factory-reset it after the record is listed so it can discover its new settings.

## Confirm the phone actually provisioned

After connecting the phone, check:

1. **Vendor Cloud:** The device tooltip says **Listed in Yealink RPS**.
2. **FS PBX contact:** **Last Contact** on the Devices page shows a recent request.
3. **SIP registration:** The phone appears on the **Registrations** page.

Make an incoming and outgoing test call before handing over the phone.

## Refresh records from Yealink

Use **Devices > Vendor Cloud > Yealink > Sync Devices** when records were changed directly in RPS or the local listing status is stale.

This refreshes the cloud listing status of phones already added in FS PBX. To apply settings to a registered phone, use **Sync** from that device's action menu.

## Troubleshooting

### API Credentials Required

- Confirm that the selected API Domain matches the Domain under **Authentication Info** in Yealink API Service.
- Confirm that you entered the AccessKey ID and AccessKey Secret from the top **Authentication Info** section.
- Do not use the **Event Subscription** token or the **RPS XML API** username and password.
- Use the current **Authentication Info** credentials, not **Historical authentication**.
- Verify both the AccessKey ID and AccessKey Secret.
- If Yealink rotated the secret, update it in FS PBX.

### The device cannot be added

- Verify the current FS PBX account is connected to the intended RPS server.
- Confirm that the MAC address contains 12 hexadecimal characters.
- If **Require serial number** is enabled, confirm that the correct serial number is saved on the device.
- Check whether the MAC is already assigned to another YMCS account or server.
- Review the error shown in the Yealink RPS tab.

### 403 Forbidden when adding a device

For a regular Yealink account, open **Vendor Cloud > Yealink > API Credentials**, enable **Require serial number**, and save each phone's serial number before retrying. If the error persists, contact Yealink support to confirm your account permissions.

### The device is listed but Last Contact is empty

- Factory-reset a previously used phone.
- Confirm the phone model and firmware support RPS.
- Verify internet, DNS, HTTPS, and firewall access.
- Confirm that the RPS server points to the public FS PBX `/prov/` URL.
- Confirm that the phone trusts the FS PBX certificate.

### Last Contact updates but the phone does not register

- Verify the assigned extension and device lines.
- Check the SIP username, password, server address, and transport.
- Use the device's three-dot menu and select **Sync** after correcting its configuration.

### The status remains on Adding

Reopen the device and use **Refresh**. If the status still does not change, contact your FS PBX support team with the phone's MAC address and the displayed status.

## Removing devices and servers

Use **Remove from Yealink RPS** on the device when the phone is being retired, sold, or transferred.

Be careful with **Delete RPS Server**. FS PBX removes devices assigned to that server from Yealink RPS before deleting the server and disconnecting it from the current account.

## Security recommendations

- Use a publicly trusted HTTPS certificate.
- Limit access to Vendor Cloud settings and AccessKey credentials.
- Never reuse SIP passwords as provisioning HTTP passwords.
- Update both FS PBX and the Yealink RPS server when rotating provisioning HTTP credentials.
- Remove a phone from RPS before transferring it to another customer or provider.
