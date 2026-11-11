---
id: sangoma-d-series
title: Sangoma D-Series
slug: /phone-provisioning/sangoma-d-series
sidebar_position: 22
---

# Sangoma D-Series

Use **sangoma/D-Series** for Digium/Sangoma **D40, D45, D50, D60, D62, D65, and D70** phones. This setup supports extension assignments, BLF, speed dial, and Park & Retrieve keys. Use firmware **2.8.3 or newer**. D60, D62, and D65 require **2.9.15 or newer** to use separate primary and backup outbound proxies.

**D80 is not supported by this setup.**

## Assign the device

1. Select the correct account and open **Devices**.
2. Create or edit the phone using its MAC address.
3. Select **sangoma/D-Series** as the **Device Template**.
4. Under **Lines**, assign an enabled extension. Check the SIP server, outbound proxy, transport, and port in **Advanced Line Settings**.
5. Assign a **Key Template** for a reusable button layout, or configure keys on this device, then save.

## Set DHCP Option 66

Configure DHCP Option **66** as a string on the phone's network. Supply the complete provisioning URL with the account's HTTP credentials:

```text
https://PROVISION_USER:PROVISION_PASSWORD@pbx.example.com/prov/
```

Replace the placeholders and keep the trailing slash. Percent-encode reserved characters in the username and password. If provisioning HTTP authentication is disabled, use `https://pbx.example.com/prov/`.

Use the `http_auth_username` and `http_auth_password` settings from the `provision` category in **Default Settings**, or the account's overrides in **Domain Settings**. These are separate from the extension's SIP credentials. Any configured source-IP restrictions must allow the phone's public address as seen by FS PBX.

Restart the phone to obtain the DHCP option. A previously configured phone may keep its saved server; change that address or factory-reset the phone when repurposing it. The **Digium/Sangoma Configuration Server** menu is not used for this setup.

If an older phone rejects the HTTPS certificate, check its date, time, and installed certificates. Some older firmware requires disabling SSL certificate validation in the phone's settings to connect. This disables verification of the server's identity.

## Configure buttons

| FS PBX tab | Use |
| --- | --- |
| **Function Keys** | Main-display line keys and Rapid Dial keys on all supported models. Keep key 1 assigned to **Line**. |
| **Side Keys** | Built-in Rapid Dial buttons on **D50 and D70**. Configure BLF, speed dial, Park & Retrieve, or N/A here. |
| **Expansion Keys** | **D65 with EXP150**. Keys 1–40 address the first module, 41–80 the second, and so on. Each module has two pages of 20 keys. Adding expansion keys enables the module. |

Line keys belong in **Function Keys**. Repeat a line assignment if you need multiple line buttons. N/A preserves an empty position and can clear a key inherited from a Key Template or legacy Device Profile. Per-device keys override the shared layout at matching positions within each tab.

BLF keys monitor and dial an extension. Speed Dial keys dial the entered destination. Park & Retrieve keys transfer an active call to the selected parking slot or retrieve its parked call.

On D65, Function Keys **1–6** describe the first page; additional Rapid Dial keys continue on later pages. On D70, Side Keys use groups of **ten per page**. Choose positions available on your phone, then save, sync, and check the buttons.

Shared phonebook directories are not supported by this setup.

## Set time and SIP transport

Set **`sangoma_d_time_zone`** in the `provision` category under **Default Settings**, with account overrides in **Domain Settings**:

| Value | Time zone |
| --- | --- |
| `America/Los_Angeles` | US Pacific; default |
| `America/Denver` | US Mountain |
| `America/Phoenix` | Arizona |
| `America/Chicago` | US Central |
| `America/New_York` | US Eastern |
| `Europe/London` | United Kingdom |

Enter a time-zone name as shown above. The phone adjusts for daylight saving automatically. Use `ntp_server_primary` to set the time server and `admin_password` to set an administrator PIN using digits only.

**D40/D45/D50/D70:** select **UDP** or **TCP** for SIP. **D60/D62/D65:** also support **TLS** on compatible firmware. For TLS, select **TLS** in the line settings and use the PBX's TLS port. Confirm that the phone's firmware supports your PBX's certificate. An HTTPS provisioning URL does not select SIP TLS.

## Verify and apply changes

1. Check **Last Contact** to confirm the phone reached provisioning.
2. Check **Registrations** for the assigned extension and selected transport.
3. Test inbound/outbound calls, BLF changes, speed dials, and parking/retrieval.
4. After later edits, save and select **Sync**. Use **Restart** when a full handset reboot is needed.

If the phone is not registered, initiate provisioning or restart it directly from the handset.

If keys do not update, verify the selected key area and any per-device overrides, then sync again. If no provisioning contact appears, check the MAC address, template assignment, DHCP option, saved provisioning server, HTTPS connectivity, and HTTP credentials. See the [Phone Provisioning Overview](/docs/phone-provisioning/) for authentication guidance.
