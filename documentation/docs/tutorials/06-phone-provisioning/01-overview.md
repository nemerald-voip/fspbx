---
id: phone-provisioning
title: Overview
slug: /phone-provisioning/
sidebar_position: 1
---

# Phone Provisioning Overview

Phone provisioning is the process of automating the setup, configuration, and management of physical VoIP phones. Instead of manually logging into every individual phone's web interface to type in settings by hand, FS PBX acts as a central management server that dictates how each phone should behave.

When a phone boots, reboots, or triggers a resync, it reaches out to a specific URL on the FS PBX server. FS PBX looks at the phone's unique MAC address, matches it to a device record in the system, and dynamically generates a configuration file tailored specifically for that exact phone. 

A provisioned phone will automatically download and apply:
- **Connectivity settings:** SIP usernames, passwords, and server/proxy addresses.
- **User interface:** Line keys, BLF (Busy Lamp Field) button layouts, and speed dials.
- **System preferences:** Time zones, language, dial plans, and firmware upgrade paths.
- **Network settings:** VLAN tags, QoS, and STUN/TURN configurations.

### Why use provisioning?
- **Scalability:** Configure one phone or one thousand phones in the exact same amount of time. 
- **Centralized Management:** If a user changes desks or needs a new button layout, you simply make the change in FS PBX and reboot the phone.
- **Consistency:** Ensures every device on your network adheres to the same security standards and baseline configurations.
- **Zero-Touch Deployment:** When paired with cloud provisioning (like Yealink RPS, Polycom ZTP or Grandstream GDMS), you can ship a brand new phone directly to a remote worker. The moment they plug it into the internet, it finds FS PBX and configures itself automatically.

---

## Before you start

Make sure you have:
- A working FS PBX domain
- One or more extensions created
- A phone MAC address
- A matching device template for the phone model
- A provisioning URL that the phone can reach
- Provisioning HTTP credentials

*Note: For remote phones, the FS PBX URL should use a valid HTTPS certificate that the phone trusts.*

---

## 1. Add a Device

To provision a phone, you must first create a device record in FS PBX so the system recognizes the phone's MAC address.

**From the Devices menu:**
1. Go to **Devices**.
2. Click **Create**.
3. Enter the phone's **MAC address**.
4. Select the **Device Template** that matches the phone vendor and model. *(This dictates which configuration files FS PBX will generate).*
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
4. Add keys to the available areas (**Main keys**, **Multi-purpose keys**, and **Expansion keys**).
5. Save the template. 

Once saved, you can assign this template to devices via the device form, extension modal, or bulk update tool.

---

## 3. Connect the Phone

To download its configuration, the physical phone needs to know where to look. You must enter a provisioning URL and authentication credentials into the phone’s web interface (or distribute them via DHCP/vendor cloud).

### Provisioning Authentication
For security, phones must authenticate before FS PBX will hand over the configuration files. 

> **Important:** Provisioning credentials are **HTTP credentials**, which are completely separate from the SIP extension username and password.

The HTTP username and password are configured in **Default Settings** under the following variables:
- `http_auth_username`
- `http_auth_password`

Both settings must be enabled and have values set. *Recommendation: Copy these settings to each tenant domain and override the values there. Once overridden on a domain, the global values no longer apply to that tenant.*

### Provisioning URL
Always use your FS PBX domain name as the base URL. However, the exact path depends entirely on the **Device Template** assigned to the phone in FS PBX. *(Note: Key Templates handle button layouts, but do not affect the URL).*

**1. Modern Provisioning URL (`/prov/`)**
Use this if the assigned Device Template includes **version information** in its name (e.g., `yealink/t54w (v1.0.5)`). These are newer, actively managed templates.
```text
https://pbx.example.com/prov/
```

**2. Legacy Provisioning URL (`/app/provision/`)**
Use this if the assigned Device Template is an older filesystem template displaying a **plain vendor/model path** without a version number (e.g., `yealink/t44w` or `polycom/6.x`).
```text
https://pbx.example.com/app/provision/
```

**How it works:** You only provide the base URL. When the phone reaches out, it automatically appends the file it needs. FS PBX reads the request, extracts the MAC address, finds the device record, and dynamically renders the matching template.

---

## Apply changes to a phone

Anytime you change a device, key template, profile, or extension assignment:
1. **Save** the change in FS PBX.
2. **Reboot or resync** the physical phone.
3. Confirm the phone downloads its new configuration and the extension registers.

*Note: Many phones have a button in their web interface for Auto Provision, Resync, Provision Now, or Reboot.*

---

## Troubleshooting

**If the phone does not provision:**
- Verify the MAC address in FS PBX exactly matches the phone.
- Confirm the device is assigned to the correct domain.
- Check that the phone is using the correct Provisioning URL (Modern vs. Legacy).
- Ensure the phone can reach the FS PBX server over the network and trusts the HTTPS certificate.
- Verify the HTTP Provisioning username and password are correct.
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

### Advanced: Enable provisioning debug logs

For deeper troubleshooting of the modern `/prov/` provisioning path, enable verbose provisioning logs in the FS PBX `.env` file:

```env
PROVISIONING_DEBUG=true
```

Run the following command to udpate the cache:

```bash
php artisan config:cache
```

Then reboot or resync the phone and watch the Laravel log:

```bash
tail -f storage/logs/laravel.log
```

The debug output shows how FS PBX processed the provisioning request, including the requested file, matched device, selected template, loaded lines, effective keys, and render result. This is useful when the phone reaches FS PBX but the wrong template, keys, or file type is being returned.

After troubleshooting, turn the flag off again:

```env
PROVISIONING_DEBUG=false
```
