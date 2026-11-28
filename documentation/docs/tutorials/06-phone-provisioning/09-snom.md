---
id: snom-provisioning
title: Snom D862 and D865
slug: /phone-provisioning/snom/
sidebar_position: 9
---

# Set up a Snom D862 or D865

Assign an extension in FS PBX, then use Snom Phone Manager to tell the phone where to download its settings. Have the phone's MAC address and, if your account requires them, its FS PBX provisioning username and password ready.

## Add the phone in FS PBX

1. Select the account that owns the extension. Open **Devices → Create** and enter the **MAC Address** from the phone's label.
2. Choose the Snom **D8xx** device template. Open **Lines**, assign the extension to line 1, and save the device.
3. If you want programmable buttons, edit the device and open **Function Keys**. For example, add a **Speed Dial** button for reception. Use **Expansion Keys** for buttons on an attached expansion module. Save your changes.

## Connect the phone

1. Find the phone's IP address on its **Help** screen. On a computer on the same network, open that address in a browser and sign in to **Snom Phone Manager** as the phone administrator.
2. If your account uses provisioning credentials, open **Network → Advanced → HTTP Client**. Enter the FS PBX provisioning username and password and click **Apply**. Your administrator can find these in the [provisioning authentication settings](01-overview.md#provisioning-authentication).
3. Open **Maintenance → Provisioning**. In **Setting URL**, enter `https://pbx.example.com/prov/{mac}.xml`. Replace `pbx.example.com` with your FS PBX hostname. Keep `{mac}` exactly as shown, including the braces. The phone fills in its own MAC address.
4. Click **Apply**, then reboot the phone to use the new address. For later changes, save them in FS PBX and click **Update Settings** on the phone's **Provisioning** page. [Snom Phone Manager walkthrough](https://dsg.snom.com/m/products/d862-sum/en/latest.pdf)

## Check the result

In FS PBX, open **Devices** and check that **Last Contact** shows the recent download. Open **Registrations** and look for the assigned extension. Call another extension, then have it call you back. If **Last Contact** has updated but the extension is missing from **Registrations**, reopen the device's **Lines** tab and verify the extension and server address.
