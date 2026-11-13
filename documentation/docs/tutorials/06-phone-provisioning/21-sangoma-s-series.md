---
id: sangoma-s-series
title: Sangoma S-Series
slug: /phone-provisioning/sangoma-s-series
sidebar_position: 21
---

# Sangoma S-Series

Connect Sangoma **S300, S500, and S700** phones to FS PBX using **sangoma/S-Series**. You can set the provisioning server directly in the phone's web interface.

For P-series phones, follow the [Sangoma P-Series guide](/docs/phone-provisioning/sangoma-p-series/).

## Configure the device in FS PBX

1. Select the intended account and open **Devices**.
2. Create or edit the device using the phone's MAC address.
3. Select **sangoma/S-Series** as the **Device Template**.
4. Under **Lines**, assign the extension. In **Advanced Line Settings**, select **TCP** or **UDP** for **SIP Transport** and use the PBX's corresponding **SIP Port**. Review the server and proxy addresses.
5. Assign a **Key Template** for a shared layout, or configure individual **Function Keys**.
6. Save the device.

:::note SIP transport
Use **TCP** or **UDP** for this setup unless your administrator has confirmed TLS compatibility. TCP and UDP do not encrypt SIP signaling. **HTTPS** protects the configuration download separately.
:::

## Configure the phone's provisioning server

Find the phone's IP address in its network status. Open that address in a browser on the same network, sign in as the phone administrator, and go to **Management > Auto Provision**:

| Field | Value |
| --- | --- |
| Upgrade Mode | HTTPS |
| Config Server Path | `https://pbx.example.com/prov/` |
| HTTP/FTP/HTTPS UserName | The account's provisioning HTTP username |
| HTTP/FTP/HTTPS Password | The account's provisioning HTTP password |

Replace `pbx.example.com` with your FS PBX hostname. You can find the provisioning credentials as `http_auth_username` and `http_auth_password` under the `provision` category in **Domain Settings**.

Set **To Override Server** to **No** to keep the server address you entered. Use **Yes** only when your network supplies the provisioning address through DHCP.

### If the phone rejects the HTTPS certificate

Some older phones need these settings under **Management > Trusted CA** to download their configuration:

| Field | Value |
| --- | --- |
| Only Accept Trusted Certificates | Off |
| Common Name Validation | Off |

Save, then return to **Management > Auto Provision**. Turning these checks off disables verification of the server's identity, although the connection remains encrypted.

### Apply the configuration

Save and click **Autoprovision Now**. In FS PBX, check that the device's **Last Contact** updates and the extension appears in **Registrations**. Make an incoming and outgoing test call.

## Function keys

Open the device's **Function Keys** tab, or assign a **Key Template** for a shared layout. Choose from:

| Type | Configuration |
| --- | --- |
| Line | Select a device line. The same line can appear on multiple keys. |
| N/A | Leave a button unused or remove its assignment. |
| BLF | Select the extension to monitor and optionally set a label. |
| Speed Dial | Enter a dialable destination and an optional label. |
| Park & Retrieve | Select the parking slot and optionally set a label. |

Include at least one **Line** key and choose positions available on your phone. Save and **Sync**, then check the buttons on the phone.

### EXP100 expansion keys

The **S500 and S700** support up to six EXP100 modules, with 40 keys per module and a limit of 50 BLFs. Follow [Sangoma's EXP100 setup guide](https://sangomakb.atlassian.net/wiki/spaces/Phones/pages/19661220/Phones%2B-%2BEXP100%2BSetup) for cabling and power requirements.

Open **Expansion Keys** on the device or its assigned Key Template. Positions **1–40** belong to the first module, **41–80** to the second, and so on. Choose **Line**, **Speed Dial**, **BLF**, or **Park & Retrieve**, then save and sync.

## Clock and date settings

The clock defaults to **United States-Pacific Time**, **automatic daylight saving**, a **12-hour clock**, and **Month-Day-Year** dates.

To change these for an account, open **Domain Settings** and use the `provision` category. Use **Default Settings** to change the defaults for all accounts.

| Setting | Values |
| --- | --- |
| `sangoma_s_time_zone` | Choose a number from the location table below. Default: `6`. |
| `sangoma_s_daylight_saving_time` | `0` = Disable, `1` = Enable, `2` = Auto (default). |
| `sangoma_s_time_format` | `0` = 24 Hour, `1` = 12 Hour (default). |
| `sangoma_s_date_format` | `0` = Year-Month-Day, `1` = Month-Day-Year (default), `2` = Day-Month-Year. |

Use these values for `sangoma_s_time_zone`:

| Location | Value |
| --- | --- |
| United States-Pacific Time | `6` |
| United States-Mountain Time | `9` |
| United States-Mountain Time without daylight saving | `10` |
| United States-Central Time | `14` |
| United States-Eastern Time | `18` |
| United Kingdom-London | `39` |

For example, to use Eastern time for an account, add or edit an enabled setting with **Category** `provision`, **Setting Name** `sangoma_s_time_zone`, **Type** `numeric`, and **Value** `18`. Save and sync the phones.

Leave daylight saving at **Auto** for automatic seasonal changes. For locations that do not observe daylight saving, use the appropriate time zone and set daylight saving to **Disable**.

## Other preferences

These settings also use the `provision` category:

| Preference | Setting name |
| --- | --- |
| Primary time server | `ntp_server_primary` |
| Backup time server | `ntp_server_secondary` |
| Phone administrator password | `admin_password` |

## Apply future changes

After saving changes in FS PBX, select **Sync** from the device's action menu. Use **Restart** when you need to reboot the phone. If the phone is not registered, use **Autoprovision Now** in its web interface.
