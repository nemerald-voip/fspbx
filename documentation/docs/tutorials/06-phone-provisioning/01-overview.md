---
id: phone-provisioning
title: Overview
slug: /phone-provisioning/
sidebar_position: 1
---

# Phone Provisioning Overview

Phone provisioning lets you manage supported desk phones from FS PBX. Assign extensions, choose button layouts, and set phone preferences in one place, then sync the phones to apply changes.

:::tip Prefer to watch?
Watch the [FS PBX phone provisioning tutorial on YouTube](https://youtu.be/SoJlbCvLqYw) for a guided walkthrough of device templates, key templates, and phone setup.
:::

Available settings and key types vary by phone model. Follow the matching vendor guide below.

### Vendor cloud redirection

Vendor cloud services let new or factory-reset phones find FS PBX automatically. They are useful when shipping phones to remote users or sites where you cannot configure the local network.

- [Polycom ZTP](/docs/phone-provisioning/polycom-ztp/) redirects supported Poly phones to FS PBX.
- [Yealink RPS](/docs/phone-provisioning/yealink-rps/) redirects supported Yealink phones to FS PBX.

:::note Check that the phone is ready
After the phone is **Listed** in Vendor Cloud, check its **Last Contact** on the Devices page and confirm the extension appears in **Registrations**. Make a test call before handing over the phone.
:::

---

## Before you start

Make sure you have:

- An FS PBX account
- One or more extensions created
- A phone MAC address
- A matching device template for the phone model
- A provisioning URL that the phone can reach
- Provisioning HTTP credentials and/or an allowed source IP or CIDR range

*Note: For remote phones, the FS PBX URL should use a valid HTTPS certificate that the phone trusts.*

---

## 1. Add a Device

To provision a phone, you must first create a device record in FS PBX so the system recognizes the phone's MAC address.

**From the Devices menu:**

1. Go to **Devices**.
2. Click **Create**.
3. Enter the phone's **MAC address**.
4. Select the **Device Template** that matches the phone vendor and model.
5. Assign the phone to one or more extensions.
6. Choose a **Key Template** if the phone should inherit a shared button layout (see section below).
7. Save the device.

**From the Extensions menu (Streamlined Setup):**
You can also add or manage devices while setting up a user's extension.

1. Go to **Extensions** and edit an extension.
2. Open the **Devices** tab.
3. Add a new device or edit an existing one.
4. Select the device template and key template, then save.

---

## 2. Configure Button Layouts

### Key Templates vs. Device Profiles

**Key Templates** are the modern, preferred way to reuse button layouts across many phones. For example, you can create one Yealink T54W key template and assign it to 50 different devices. Use **Device Profiles** only if you require an older, profile-based workflow. You can only select one shared key source per device.

> **Pro Tip:** Per-device keys still work alongside Key Templates. If a device has its own key configured for the exact same button slot as the template, the device's unique key wins. This allows you to use a common template while customizing one or two specific buttons for a receptionist or manager.

### Create a Key Template

1. Go to **Devices** > **Key Templates**.
2. Click **Create**.
3. Enter a name and an optional description.
4. Add keys to the available areas (**Function Keys**, **Side Keys**, **Multi Purpose Keys**, and **Expansion Keys**), as supported by the phone.
5. Save the template.

Once saved, you can assign this template to devices via the device form, extension modal, or bulk update tool.

---

## 3. Connect the Phone

To download its configuration, the physical phone needs to know where to look. Enter the provisioning URL and any configured HTTP credentials into the phone’s web interface, or distribute them through DHCP or a vendor cloud service.

### Vendor setup guides

Use these guides for the phone's provisioning fields, URL format, and first-download checks:

| Phone family | Setup guide |
| --- | --- |
| Polycom / Poly VVX | [Enter the server and credentials directly](/docs/phone-provisioning/polycom/) |
| Yealink SIP phones | [Enter the server and credentials directly](/docs/phone-provisioning/yealink/) |
| Grandstream GXP / GRP | [Enter the server and credentials directly](/docs/phone-provisioning/grandstream/) |
| Sangoma P-Series | [Set up DHCP Option 66](/docs/phone-provisioning/sangoma-p-series/#set-dhcp-option-66) |
| Sangoma S-Series | [Enter the server and credentials directly](/docs/phone-provisioning/sangoma-s-series/#configure-the-phones-provisioning-server) |
| Sangoma D-Series | [Set up DHCP Option 66 and Rapid Dial keys](/docs/phone-provisioning/sangoma-d-series/) |
| VTech NG-Series | [Enter the server and configure speed dials](/docs/phone-provisioning/vtech-ng-series/) |

### Provisioning Authentication

Provisioning access can be restricted by source IP or CIDR range, HTTP credentials, or both.

> **Important:** Provisioning credentials are **HTTP credentials**, which are completely separate from the SIP extension username and password.

Configure these variables in the `provision` category under **Default Settings**:

- `cidr`
- `http_auth_username`
- `http_auth_password`

Enable one `cidr` row for each allowed source address or network, such as `203.0.113.25/32` for one address or `203.0.113.0/24` for a network. Use the source address that FS PBX sees. This is usually the site's public NAT address for a remote phone, or the phone's private address when the phone and FS PBX are on the same network.

| Security settings configured | Requirement |
| --- | --- |
| CIDR only | The source IP must match at least one enabled CIDR row. |
| HTTP username and password only | HTTP authentication must pass. |
| CIDR and HTTP credentials | The source IP must match **and** HTTP authentication must pass. |
| Neither | No additional CIDR or HTTP authentication check is applied. |

To require HTTP authentication, enable and fill in both the username and password settings.

For account-specific values, open **Domains**, select the account's settings, and add or copy the settings there. Account CIDR rows replace the default CIDR list; they are not merged with it. The same override behavior applies to the HTTP credential settings.

> **Security recommendation:** Use HTTPS even when CIDR restrictions are enabled. CIDR restrictions control where requests may originate but do not encrypt provisioning credentials or phone configuration files.

### Provisioning URL

Most phones use your FS PBX hostname with this path:

```text
https://pbx.example.com/prov/
```

Follow the vendor guide for the exact format. Some phones use a separate HTTPS selector and require only `pbx.example.com/prov/`; VTech NG-Series phones need `https://pbx.example.com/prov/{mac}.cfg`. Keep any required trailing slash or filename.

---

## Apply changes to a phone

Anytime you change a device, key template, profile, or extension assignment:

1. **Save** the change in FS PBX.
2. Select **Sync** from the device's action menu, or use the phone's provisioning controls.
3. Check **Last Contact** and **Registrations**, then test the changed feature.

*Note: Many phones have a button in their web interface for Auto Provision, Resync, Provision Now, or Reboot.*

---

## Troubleshooting

**If the phone does not provision:**

- Verify the MAC address in FS PBX exactly matches the phone.
- Confirm the device is assigned to the correct account.
- Check that the phone is using your FS PBX hostname and the `/prov/` path, with the URL format required by its vendor.
- Ensure the phone can reach the FS PBX server over the network and trusts the HTTPS certificate.
- Verify the HTTP Provisioning username and password are correct.
- If source-IP restrictions are enabled, confirm that the phone's network is allowed.
- Ensure the requested vendor/model template actually exists in the system.

**If the phone provisions but the buttons are wrong:**

- Check whether the device is set to use a **Key Template** or **Device Profile**.
- Look for per-device keys that might be overriding the template.
- Confirm the key is assigned to the correct area and index/slot.
- Ensure you rebooted or resynced the phone *after* saving the key changes.

**If the phone provisions but the extension does not register:**

- Confirm the extension is actually enabled in FS PBX.
- Verify the SIP username, SIP password, and server address are correct.
- Check your local firewall and NAT rules.
