---
id: fanvil-provisioning
title: Fanvil XU Series
slug: /phone-provisioning/fanvil/
sidebar_position: 10
---

# Set up a Fanvil XU Series phone

Use this guide for the **X3U, X4U, X5U, and X6U**. You will need the phone's MAC address, the extension you want it to use, and access to the phone's web settings.

## Add the phone in FS PBX

1. Select the account that owns the extension. Open **Devices → Create** and enter the **MAC Address** from the phone's label.
2. Choose the Fanvil **XU Series** device template. Open **Lines**, assign the extension to line 1, and save the device.
3. To add a shortcut, edit the device and open **Function Keys**. For example, add a **Speed Dial** for reception. If your model has a side display, use **Side Keys** for its buttons. Save your changes.

## Enter the provisioning server

Find the phone's IP address on its status screen. From a computer on the same network, open that address in a browser, sign in as the phone administrator, and go to **System → Auto Provision**.

1. Under **Common Settings**, enter the FS PBX provisioning username and password in **Authentication Name** and **Authentication Password** if your account requires them. If you see **Download DeviceConfig enabled**, select it. [Where to find your provisioning credentials](01-overview.md#provisioning-authentication)
2. Open **Static Provisioning Server** and enter the following values. Replace `pbx.example.com` with your FS PBX hostname.

   | Field | What to enter |
   | --- | --- |
   | **Server Address** | `pbx.example.com` |
   | **Configuration File Name** | `prov/$mac.cfg` |
   | **Protocol Type** | **HTTPS** |
   | **Update Mode** | **Update After Reboot** |

   Keep the dollar sign in `$mac`. The phone uses its own MAC address to request the right file.
3. Click **Apply**, then select **AutoProvision Now**, or reboot the phone. [Fanvil static provisioning guide](https://fanvil.com/service/help/faq/auto%20provision/20220120/7074.html)

## Check the result

In FS PBX, check **Last Contact** under **Devices**, then open **Registrations** and look for your extension. Make a call from the Fanvil and call it back. If **Last Contact** is still blank or shows an older date, compare the phone's MAC address with the device record and recheck the server address, filename, and provisioning credentials.
