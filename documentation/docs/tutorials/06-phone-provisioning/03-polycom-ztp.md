---
id: polycom-ztp
title: Polycom ZTP
slug: /phone-provisioning/polycom-ztp
sidebar_position: 3
---

# Polycom ZTP

Polycom ZTP, now called **Poly Zero Touch** by the vendor, is a cloud redirection service for supported Poly phones. It lets a new or factory-reset phone discover your FS PBX provisioning server without requiring someone to enter the provisioning URL at the phone or configure DHCP option 66 at the deployment site.

## Why use Polycom ZTP?

Polycom ZTP is most useful when:

- Phones are shipped directly to remote users or branch offices.
- You do not control the site's DHCP server.
- A deployment contains enough phones that manual setup would be slow or error-prone.
- Phones may be reassigned and need a repeatable factory-reset workflow.
- You want FS PBX to remain the source of the complete phone configuration.

After you add the phone to ZTP, connect it to the internet. It can then find FS PBX and download its settings.

## Before you start

You need:

- A Poly Zero Touch account with access to the REST API.
- A Poly Zero Touch API key. Not every Poly account role can use the APIs.
- A supported Poly phone with ZTP enabled and a factory-installed device certificate.
- The phone's MAC address.
- A public FS PBX hostname with a trusted HTTPS certificate.
- TCP 443 access from the phone to Poly Zero Touch and FS PBX.
- An FS PBX device record with the correct Poly template and line assignment.
- FS PBX provisioning HTTP credentials, if authentication is enabled.

Review Poly's current [device requirements](https://info.ztp.poly.com/docs/begin/device-requirements), [supported device information](https://h30434.www3.hp.com/t5/Poly-Phones-Knowledge-Base/What-is-Poly-ZTP/ta-p/9212778), and [Zero Touch API overview](https://info.ztp.poly.com/docs/api/api-overview) before deploying a model for the first time.

## Prepare FS PBX provisioning

1. Confirm that the public FS PBX application URL is correct and uses HTTPS.
2. Confirm that this provisioning URL is reachable from the phone's network:

   ```text
   https://pbx.example.com/prov/
   ```

3. Configure `http_auth_username` and `http_auth_password` in the `provision` category under **Default Settings** or as account overrides under **Domain Settings**.
4. Create the phone under **Devices**:
   - Enter the MAC address.
   - Select the Poly device template matching the phone.
   - Assign the required extension or lines.
   - Save the device.

Provisioning HTTP credentials are separate from the extension's SIP username and password.

## Connect FS PBX to Poly Zero Touch

### 1. Add the API key

1. Open **Devices**.
2. Click **Vendor Cloud**.
3. Open the **Polycom** tab.
4. Click **API Token**.
5. Enter the API key from Poly Zero Touch.
6. Click **Save**.

This connection is shared by all FS PBX accounts using Poly ZTP. Limit access to the administrators responsible for those accounts.

### 2. Create or connect an organization

1. On the Polycom tab, click **Create Organization**.
2. Choose one of the following:
   - **Create New Organization** creates a new Poly Zero Touch profile and connects it to the current FS PBX account.
   - **Connect to Existing** selects a profile that already exists in the connected Poly Zero Touch account.

When creating a profile, configure:

- **Organization Name:** A short name that identifies the customer or deployment.
- **Address:** The public FS PBX provisioning URL, normally `https://pbx.example.com/prov/`.
- **Username and Password:** The FS PBX provisioning HTTP credentials.
- **Polling and Quick Setup:** Optional Poly provisioning behavior.
- **DHCP, software, localization, and custom configuration:** Optional settings for deployments that need them.

Each FS PBX account is paired with one Poly Zero Touch profile. The API key can access all profiles permitted by the connected Poly account.

## Add a phone to Polycom ZTP

1. Open **Devices** and edit the Poly phone.
2. Open the **Polycom ZTP** tab.
3. Click **Add to Polycom ZTP**.
4. Wait for the action to complete.
5. Return to the Devices list or reopen the device to refresh its status.

The Vendor Cloud status progresses through:

| Status | Meaning |
| --- | --- |
| Adding to Polycom ZTP | The phone is being added. |
| Listed in Polycom ZTP | Poly accepted the device record. |
| Could not add to Polycom ZTP | Open the device's Polycom ZTP tab to review the error. |
| Removing from Polycom ZTP | FS PBX is removing the cloud record. |
| Not listed in Polycom ZTP | FS PBX has no successful Poly listing for the device. |

If the phone has been used before, factory-reset it after the record is listed. Poly states that automatic ZTP discovery depends on supported hardware, a factory-installed certificate, and ZTP being enabled in the phone's factory configuration.

## Confirm the phone actually provisioned

After connecting the phone, check:

1. **Vendor Cloud:** The device tooltip says **Listed in Polycom ZTP**.
2. **FS PBX contact:** **Last Contact** on the Devices page shows a recent request.
3. **SIP registration:** The phone appears on the **Registrations** page.

Make an incoming and outgoing test call before handing over the phone.

## Refresh records from Poly

Use **Devices > Vendor Cloud > Polycom > Sync Devices** when records were changed directly in Poly Zero Touch or the local listing status is stale.

This refreshes the cloud listing status of phones already added in FS PBX. To apply settings to a registered phone, use **Sync** from that device's action menu.

## Troubleshooting

### The device cannot be added

- Verify the API key and its Poly Zero Touch permissions.
- Confirm that the current FS PBX account is connected to the intended Poly profile.
- Verify the MAC address.
- Check whether the device is already claimed by another organization.
- Review the error shown in the Polycom ZTP tab.

### The device is listed but Last Contact is empty

- Confirm that the model and firmware support Poly Zero Touch.
- Factory-reset a previously used phone.
- Confirm that ZTP is enabled on the phone.
- Verify internet, DNS, HTTPS, and firewall access.
- Confirm that the profile points to the public FS PBX `/prov/` URL.

### Last Contact updates but the phone does not register

- Verify the assigned extension and device lines.
- Check the SIP username, password, server address, and transport.
- Use the device's three-dot menu and select **Sync** after correcting its configuration.

### The status remains on Adding

Reopen the device and use **Refresh**. If the status still does not change, contact your FS PBX support team with the phone's MAC address and the displayed status.

## Security recommendations

- Use a publicly trusted HTTPS certificate.
- Limit access to the Vendor Cloud settings and API token.
- Never reuse SIP passwords as provisioning HTTP passwords.
- Update both FS PBX and the Poly profile when rotating provisioning HTTP credentials.
- Remove a phone from Polycom ZTP before transferring it to another customer or provider.

