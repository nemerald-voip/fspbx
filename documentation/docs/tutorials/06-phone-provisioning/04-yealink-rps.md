---
id: yealink-rps
title: Yealink RPS
slug: /phone-provisioning/yealink-rps
sidebar_position: 4
---

# Yealink RPS

Yealink RPS is Yealink's **Redirection and Provisioning Service**. It stores a phone MAC address and the provisioning server assigned to that phone. On initial startup or after a factory reset, a supported Yealink phone can contact RPS and be redirected to FS PBX.

FS PBX integrates with the Yealink Management Cloud Service (YMCS) Open API v2 to create RPS servers, connect existing servers, and add or remove device MAC addresses.

## Why use Yealink RPS?

Yealink RPS is useful when:

- Phones are delivered directly to users outside the office.
- The local network cannot provide DHCP option 66.
- You need repeatable, low-touch deployment across many locations.
- You want to reduce manual URL and credential entry.
- FS PBX should remain the source of each phone's complete configuration.

The normal startup flow is:

1. The phone starts with internet access and contacts Yealink RPS.
2. RPS finds the MAC address and redirects the phone to the assigned FS PBX provisioning server.
3. The phone contacts the FS PBX `/prov/` endpoint using the configured HTTP credentials.
4. FS PBX matches the MAC address and renders the assigned Yealink device template.
5. The phone applies the configuration and registers its SIP lines.

Yealink describes RPS as a way to bind a phone MAC address to a provisioning-server URL for initial redirection. See the official [Yealink RPS overview](https://www.yealink.com/en/onepage/yealink-free-remote-provisioning-service).

:::warning What RPS does not prove
A device can be listed in Yealink RPS without ever contacting RPS or FS PBX. **Listed in Yealink RPS** means the cloud record exists. It does not mean the phone downloaded configuration or registered to FreeSWITCH.
:::

## Before you start

You need:

- Access to the Yealink RPS Service portal with API Service enabled.
- The **Domain**, **AccessKey ID**, and **AccessKey Secret** shown under **Authentication Info** at **System > Integration > API**.
- A supported Yealink phone and its MAC address.
- A public FS PBX hostname with a trusted HTTPS certificate.
- Network access from the phone to Yealink RPS and FS PBX.
- An FS PBX device record with the correct Yealink template and line assignment.
- FS PBX provisioning HTTP credentials, if authentication is enabled.

The AccessKey credentials are API credentials for FS PBX to manage RPS. They are not the credentials a phone uses to download its configuration.

## Prepare FS PBX provisioning

1. Set the public FS PBX application URL correctly. The Yealink RPS form uses:

   ```text
   https://pbx.example.com/prov/
   ```

   This is built from the FS PBX application URL plus `/prov/`.

2. Configure `http_auth_username` and `http_auth_password` in the `provision` category under **Default Settings** or as account overrides under **Domain Settings**.
3. Create the phone under **Devices**:
   - Enter the MAC address.
   - Select a modern Yealink device template.
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
12. Click **Save**.

Use only the three values in the top **Authentication Info** section. Do not use the **Event Subscription** token or the username and password under **RPS XML API**. If the portal also shows **Historical authentication > RPS Json**, do not use those credentials either.

The AccessKey credentials and API Domain are stored as global FS PBX cloud-provider settings. Restrict access to administrators who are authorized to manage every account using this RPS integration.

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

FS PBX prefills the URL from its application URL and prefills the HTTP credentials from the account's provisioning settings. Verify all three values before saving.

Each FS PBX account is paired with one Yealink RPS server. The AccessKey credentials can access all servers permitted by the connected YMCS account.

## Add a phone to Yealink RPS

1. Open **Devices** and edit the Yealink phone.
2. Open the **Yealink RPS** tab.
3. Click **Add to Yealink RPS**.
4. Wait for the action to complete.
5. Return to the Devices list or reopen the device to refresh its status.

The Vendor Cloud status progresses through:

| Status | Meaning |
| --- | --- |
| Adding to Yealink RPS | FS PBX queued or is processing the API request. |
| Listed in Yealink RPS | Yealink accepted the device record. |
| Could not add to Yealink RPS | Yealink rejected the request or the API call failed. |
| Removing from Yealink RPS | FS PBX is removing the cloud record. |
| Not listed in Yealink RPS | FS PBX has no successful Yealink listing for the device. |

If the phone was previously configured, factory-reset it after the record is listed so it performs the vendor bootstrap process again.

## Confirm the phone actually provisioned

Check these three stages independently:

1. **Vendor Cloud:** The device tooltip says **Listed in Yealink RPS**.
2. **FS PBX contact:** **Last Contact** on the Devices page shows a recent request.
3. **SIP registration:** The phone appears on the **Registrations** page.

If only the first stage succeeds, RPS accepted the record but the phone did not complete provisioning.

## Refresh records from Yealink

Use **Devices > Vendor Cloud > Yealink > Sync Devices** when records were changed directly in RPS or the local listing status is stale.

Sync Devices:

- Downloads the current Yealink RPS device list.
- Rebuilds the local Vendor Cloud status for matching FS PBX devices.
- Does not create missing FS PBX device records.
- Does not make a phone contact RPS or download configuration.

## Troubleshooting

### API Credentials Required

- Confirm that the selected API Domain matches the Domain under **Authentication Info** in Yealink API Service.
- Confirm that you entered the AccessKey ID and AccessKey Secret from the top **Authentication Info** section.
- Do not use the **Event Subscription** token or the **RPS XML API** username and password.
- Do not use **Historical authentication > RPS Json** credentials; those belong to the previous API.
- Verify both the AccessKey ID and AccessKey Secret.
- If Yealink rotated the secret, update it in FS PBX.

### The device cannot be added

- Verify the current FS PBX account is connected to the intended RPS server.
- Confirm that the MAC address contains 12 hexadecimal characters.
- Check whether the MAC is already assigned to another YMCS account or server.
- Review the error shown in the Yealink RPS tab and the Laravel log.

### The device is listed but Last Contact is empty

- Factory-reset a previously used phone.
- Confirm the phone model and firmware support RPS bootstrap.
- Verify internet, DNS, HTTPS, and firewall access.
- Confirm that the RPS server points to the public FS PBX `/prov/` URL.
- Confirm that the phone trusts the FS PBX certificate.

### Last Contact updates but the phone does not register

- Verify the assigned extension and device lines.
- Check the SIP username, password, server address, and transport.
- Open **Registrations** and inspect FreeSWITCH logs.
- Use the device's three-dot menu and select **Sync** after correcting its configuration.

### The status remains on Adding

- Confirm that the FS PBX queue worker is running.
- Review Horizon and the Laravel log for a failed cloud-provider job.
- Reopen the device and use **Refresh** if the action completed but the UI is stale.

## Removing devices and servers

Use **Remove from Yealink RPS** on the device when the phone is being retired, sold, or transferred.

Be careful with **Delete RPS Server**. FS PBX removes devices assigned to that server from Yealink RPS before deleting the server and disconnecting it from the current account.

## Security recommendations

- Use a publicly trusted HTTPS certificate.
- Limit access to Vendor Cloud settings and AccessKey credentials.
- Never reuse SIP passwords as provisioning HTTP passwords.
- Update both FS PBX and the Yealink RPS server when rotating provisioning HTTP credentials.
- Remove a phone from RPS before transferring it to another customer or provider.
