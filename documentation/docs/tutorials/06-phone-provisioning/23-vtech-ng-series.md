---
id: vtech-ng-series
title: VTech NG-Series
slug: /phone-provisioning/vtech-ng-series
sidebar_position: 23
---

# VTech NG-Series

Connect your VTech NG-Series hotel phone to FS PBX and set up one-touch speed-dial buttons. Select **vtech/NG-Series** for these models:

| Family | Models |
| --- | --- |
| Corded | NG-S3211, NG-S3211W, NG-S3212, NG-S3311 |
| Cordless | NG-S3111, NG-S3112, NG-S3411, NG-S3411W, NG-S3412 |

This setup supports one extension per phone. Analog NG-A models are not covered by this guide.

## Assign the device

1. Open **Devices** in the correct account and add the phone's MAC address.
2. Select **vtech/NG-Series** as the **Device Template**.
3. Assign the extension to **Line 1**.
4. Check the SIP server, outbound proxies, transport, and port in **Advanced Line Settings**.
5. Save the device.

## Point the phone at FS PBX

Open the phone's web interface and go to **Servicing → Provisioning**. Enter this **Server URL**, replacing `pbx.example.com` with your PBX hostname:

```text
https://pbx.example.com/prov/{mac}.cfg
```

Keep `{mac}` exactly as written. The phone fills in its own MAC address, so you can use the same URL for every phone.

Enter the account's provisioning username and password in **Server Authentication Name** and **Server Authentication Password**. You can find these as `http_auth_username` and `http_auth_password` under the `provision` category in **Domain Settings**.

Save and restart the phone. Once it shows as registered, make an incoming and outgoing test call.

## Configure speed dials

:::note Supported key type
Choose **Speed Dial** for the phone's programmable buttons. BLF, line appearances, and monitored call parking are not supported by this setup. Speed-dial buttons call a number but do not show whether an extension is busy.
:::

1. Open the device's **Function Keys** tab, or assign a **Key Template** for a shared layout.
2. Choose the button position and set **Type** to **Speed Dial**.
3. Select the extension or enter the number to dial.
4. **Save**, then select **Sync** from the device's action menu.
5. Press the button on the phone to check that it calls the intended number.

| Positions | Use |
| --- | --- |
| 1–10 | Base speed-dial buttons, where fitted |
| 13 | Cordless handset Service button on NG-S3112 and NG-S3411/NG-S3411W/NG-S3412 |
| 14 | Cordless handset Emergency button on those same models |

For NG-S3111, follow VTech's instructions for handset speed dials; they cannot be configured through FS PBX.

To remove a button assignment, clear its value or select **N/A**, then save and sync the phone.

## Optional settings

To change these preferences for an account, open **Domain Settings** and use the `provision` category. For one phone, use its device settings. Enable each setting you want to apply, then save and sync the affected phones.

| Preference | Setting name | Value |
| --- | --- | --- |
| Time zone | `vtech_time_zone` | For example, `America/New_York` |
| Voicemail access number | `voicemail_number` | `*97` by default |
| Time server | `ntp_server_primary` | `pool.ntp.org` by default |
| Phone administrator password | `admin_password` | Up to 15 letters and numbers |
| VLAN tagging | `vtech_vlan_wan_enable` | `1` to enable, `0` to disable |
| Voice VLAN | `vtech_vlan_wan_id` | Your network's VLAN ID |
| VLAN priority | `vtech_vlan_wan_priority` | `0`–`7` |

To turn off VLAN tagging, set `vtech_vlan_wan_enable` to `0` and leave that setting enabled.
