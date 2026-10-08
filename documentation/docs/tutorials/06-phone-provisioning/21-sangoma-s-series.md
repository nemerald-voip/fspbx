---
id: sangoma-s-series
title: Sangoma S-Series
slug: /phone-provisioning/sangoma-s-series
sidebar_position: 21
---

# Sangoma S-Series

Use **sangoma/S-Series** to provision Sangoma S300, S500, and S700 phones. These models share their SIP account and function-key configuration format. Sangoma P-series phones use the separate [sangoma/P-Series template](/docs/phone-provisioning/sangoma-p-series/).

S-series phones accept the provisioning URL and HTTP credentials directly in their web interface, so DHCP Option 66 is optional for this setup. The [P-series FS PBX setup](/docs/phone-provisioning/sangoma-p-series/#set-dhcp-option-66) uses DHCP Option 66 instead. Older S-series firmware may need its certificate checks disabled before it can download an HTTPS configuration, as explained below.

## Configure the device in FS PBX

1. Select the intended account and open **Devices**.
2. Create or edit the device using the phone's MAC address.
3. Select **sangoma/S-Series** as the **Device Template**.
4. Under **Lines**, assign the extension. In **Advanced Line Settings**, select **TCP** or **UDP** for **SIP Transport** and use the PBX's corresponding **SIP Port**. Review the server and proxy addresses.
5. Assign a **Key Template** for a shared layout, or configure individual **Function Keys**.
6. Save the device.

### SIP transport and TLS limitation

Use **TCP** or **UDP** for the S-series deployment described here. Keep **HTTPS** for provisioning; configuration downloads and SIP registration use separate connections. TCP and UDP do not encrypt SIP signaling.

The S500 tested with firmware **2.0.4.91** offers only an RSA-based cipher for SIP TLS. Registration fails against a PBX serving an **ECDSA certificate**, even when certificate validation is disabled. No cipher-selection setting or firmware workaround has been verified; [Sangoma's firmware downloads](https://sangomakb.atlassian.net/wiki/spaces/DASD/pages/28311785/Driver%2BSoftware%2BDownloads%2B-%2BSangoma%2BS-Series%2BPhone%2BFirmware) list 2.0.4.91 for the S500.

TLS would require a compatible RSA certificate and cipher on the SIP endpoint. TLS compatibility has not been verified on the S300 or S700. The newer [P-series](/docs/phone-provisioning/sangoma-p-series/) uses a different firmware platform; P325 and P370 TLS registration has been verified with an ECDSA PBX certificate.

## Configure the phone's provisioning server

Find the phone's IP address in its network status or the router's DHCP leases. Open that address from a browser with access to the phone's network and sign in as the phone administrator. Go to **Management > Auto Provision**:

| Field | Value |
| --- | --- |
| Upgrade Mode | HTTPS |
| Config Server Path | `https://pbx.example.com/prov/` |
| HTTP/FTP/HTTPS UserName | The account's provisioning HTTP username |
| HTTP/FTP/HTTPS Password | The account's provisioning HTTP password |

Replace the example hostname with your reachable FS PBX hostname. The credentials come from `http_auth_username` and `http_auth_password` in the `provision` category under **Default Settings** or the account's **Domain Settings**. They are separate from the extension's SIP credentials.

If you are entering the server manually, check **To Override Server**. Enabling this allows DHCP provisioning options to replace the entered address. Set it to **No** when DHCP should not control the provisioning destination.

This web setup is also illustrated in [Sangoma's configuration-server guide](https://sangomakb.atlassian.net/wiki/spaces/Phones/pages/17695051).

### Check HTTPS certificate acceptance before the first download

Older S-series phones can reject the provisioning server's HTTPS certificate even when a current browser accepts it. If this prevents provisioning, open **Management > Trusted CA** and set:

| Field | Value |
| --- | --- |
| Only Accept Trusted Certificates | Off |
| Common Name Validation | Off |

Save these settings, then return to **Management > Auto Provision**. The shared FS PBX template also sets both checks to Off, but the initial change must be made on the phone if certificate validation is blocking the first download. HTTPS still encrypts the connection; these settings turn off the phone's verification of the server's identity. Installing a compatible trusted CA chain is an alternative when using a custom template that keeps validation enabled.

### Download and verify

Save the provisioning settings and click **Autoprovision Now**. Confirm a recent **Last Contact** in FS PBX, then check **Registrations** for the assigned extension and expected transport. Test inbound and outbound calls before handing over the phone. When managing the phone remotely, use these PBX checks rather than relying on access to its display.

## Function keys

The shared template supports these main-key types:

| Type | Configuration |
| --- | --- |
| Line | Select a device line. The same line can appear on multiple keys. |
| N/A | Leave a position unused or clear an inherited key. |
| BLF | Select the extension to monitor and optionally set a label. |
| Speed Dial | Enter a dialable destination and an optional label. |
| Park & Retrieve | Select the parking slot and optionally set a label. |

Include at least one **Line** key. With no saved function-key layout, the template supplies line keys for the assigned lines. Per-device keys override matching positions in an assigned Key Template or legacy Device Profile.

The common S-series configuration provides six account positions and 45 main-key positions. FS PBX uses the same configuration across these models; the handset determines which accounts, keys, and pages it supports. Verify the visible layout and test BLF and parking behavior on each deployed model. Expansion modules and shared phonebook directories are not currently included in this template.

## Settings and ongoing changes

The template uses `ntp_server_primary` and `ntp_server_secondary` for time synchronization, and `admin_password` for the phone's administrator password when configured. Manage these in the `provision` category under **Default Settings** or **Domain Settings**.

The clock defaults to **United States-Pacific Time**, **automatic daylight saving**, a **12-hour clock**, and **Month-Day-Year** dates. DHCP overrides for the time zone and NTP servers are disabled so the phone uses its provisioned settings.

Change these S-series settings in the `provision` category under **Default Settings**, or override them for an account in **Domain Settings**:

| Setting | Values |
| --- | --- |
| `sangoma_s_time_zone` | Vendor numeric time-zone ID. Default: `6` = United States-Pacific Time. |
| `sangoma_s_daylight_saving_time` | `0` = Disable, `1` = Enable, `2` = Auto (default). |
| `sangoma_s_time_format` | `0` = 24 Hour, `1` = 12 Hour (default). |
| `sangoma_s_date_format` | `0` = Year-Month-Day, `1` = Month-Day-Year (default), `2` = Day-Month-Year. |

S-series phones use numeric time-zone IDs rather than IANA names. The P-series setting `sangoma_p_time_zone` does not apply.

Examples for `sangoma_s_time_zone`:

| Location | Value |
| --- | --- |
| United States-Pacific Time | `6` |
| United States-Mountain Time | `9` |
| United States-Mountain Time without daylight saving | `10` |
| United States-Central Time | `14` |
| United States-Eastern Time | `18` |
| United Kingdom-London | `39` |

To use Pacific time by default, set **Category** to `provision`, **Setting Name** to `sangoma_s_time_zone`, **Type** to `numeric`, **Value** to `6`, and enable the setting in **Default Settings**. Enter the numeric ID, rather than `America/Los_Angeles` or `-8`.

For an account in Eastern time, create an enabled override with the same category, setting name, and type in that account's **Domain Settings**, using **Value** `18`. Other accounts continue to inherit the global default. Leave `sangoma_s_daylight_saving_time` at `2` for automatic seasonal changes, then save and sync the affected phones. For locations that do not observe daylight saving, use the appropriate zone and set daylight saving to `0`.

### DST Type, Start Week, and End Week

Keep **Daylight Saving Time** set to **Auto** for the normal provisioned setup. Auto uses the selected time zone's built-in seasonal rules. **DST Type**, **Start Week**, and **End Week** are manual-rule controls used with **Enable**. With Auto selected, the January values shown in those fields are not the active DST schedule.

If you intentionally choose manual DST, select **Week Type** for a rule that follows a weekday each year. For US locations that observe DST, the rule is:

| Field | Value |
| --- | --- |
| DST Type | Week Type |
| Start Week | March / Second in Month / Sunday / 02:00 |
| End Week | November / First in Month / Sunday / 02:00 |

These are the [current US DST rules](https://www.nist.gov/pml/time-and-frequency-division/popular-links/daylight-saving-time-dst). Other regions can use different rules. The shared template manages the DST mode and time zone, but does not populate custom day/week rules. Prefer Auto; changing those manual fields on the phone is unnecessary for the default Pacific setup.

### Apply changes

Save changes in FS PBX and select **Sync** from the device action menu. **Restart** requests a full reboot. Both actions require a reachable SIP registration; use **Autoprovision Now** or restart the handset directly during initial setup or when it is unregistered.

The template enables **Unregister On Reboot**, so the phone clears its SIP registration during a normal reboot.

## Troubleshooting

| Symptom | Checks |
| --- | --- |
| Last Contact does not update | Check the complete `/prov/` URL, HTTPS mode, DNS, certificate acceptance, HTTP credentials, source-IP restrictions, and DHCP overrides. |
| Last Contact updates but registration fails | Verify extension enablement, SIP credentials, account domain, proxy address, transport, port, and firewall access. |
| S500 stops registering after selecting TLS | Select TCP or UDP and the corresponding SIP port, then use Autoprovision Now in the phone's web interface. Firmware 2.0.4.91 cannot negotiate SIP TLS with an ECDSA PBX certificate. |
| Keys are incorrect | Check the Key Template or Device Profile and per-device overrides. Save and sync, then verify which key positions the handset supports. |
| Sync has no effect | Confirm an active registration and a reachable provisioning server. Try Autoprovision Now to check provisioning independently. |

For authentication settings and additional diagnostics, see the [Phone Provisioning Overview](/docs/phone-provisioning/).
