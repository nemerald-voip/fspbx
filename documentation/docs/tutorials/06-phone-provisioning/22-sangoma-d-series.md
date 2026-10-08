---
id: sangoma-d-series
title: Sangoma D-Series
slug: /phone-provisioning/sangoma-d-series
sidebar_position: 22
---

# Sangoma D-Series

Use the shared **sangoma/D-Series** device template for Digium/Sangoma D40, D45, D50, D60, D62, D65, and D70 phones. It provisions SIP lines, BLF, speed dial, N/A, and Park & Retrieve keys. Use firmware 2.8.3 or newer; D6x firmware 2.9.15 or newer is required for separate primary and backup outbound proxies.

D65 firmware **2.9.27** has been verified for HTTPS provisioning, TCP registration, and PBX Sync. The other supported models have not yet been hardware-tested with FS PBX.

**D80 is excluded.** Sangoma documents that model as requiring Switchvox or Asterisk DPMA provisioning, which this FS PBX template does not provide. See [Sangoma's provisioning guidance](https://sangomakb.atlassian.net/wiki/spaces/PG/pages/31228277).

## Assign the device

1. Select the correct account and open **Devices**.
2. Create or edit the phone using its MAC address.
3. Select **sangoma/D-Series** as the **Device Template**.
4. Under **Lines**, assign an enabled extension. Check the SIP server, outbound proxy, transport, and port in **Advanced Line Settings**.
5. Assign a **Key Template** for a reusable button layout, or configure keys on this device, then save.

The handset identifies its model when it requests configuration. Provisioning Preview uses the saved model or the phone's last provisioning contact. Before a model is known, the preview uses the D65 layout.

## Set DHCP Option 66

Configure DHCP Option **66** as a string on the phone's network. Supply the complete provisioning URL with the account's HTTP credentials:

```text
https://PROVISION_USER:PROVISION_PASSWORD@pbx.example.com/prov/
```

Replace the placeholders and keep the trailing slash. Percent-encode reserved characters in the username and password. If provisioning HTTP authentication is disabled, use `https://pbx.example.com/prov/`.

Use the `http_auth_username` and `http_auth_password` settings from the `provision` category in **Default Settings**, or the account's overrides in **Domain Settings**. These are separate from the extension's SIP credentials. Any configured source-IP restrictions must allow the phone's public address as seen by FS PBX.

Restart the phone to obtain the DHCP option. A previously configured phone may keep its saved server; redirect that configuration or plan a factory reset before repurposing the handset. The **Digium/Sangoma Configuration Server** menu is for DPMA, rather than this HTTPS provisioning workflow. See [Sangoma's D-series provisioning reference](https://sangomakb.atlassian.net/wiki/spaces/Phones/pages/23593101).

If the initial HTTPS download fails, check the phone's clock, NTP access, firmware, and certificate trust. Older firmware may require disabling SSL certificate validation in the handset's settings to reach the provisioning server. The template allows untrusted certificates on subsequent downloads; that setting cannot repair a blocked first download.

## Configure buttons

| FS PBX tab | Use |
| --- | --- |
| **Function Keys** | Main-display line keys and Rapid Dial keys on all supported models. Keep key 1 assigned to **Line**. |
| **Side Keys** | Built-in Rapid Dial buttons on **D50 and D70**. Configure BLF, speed dial, Park & Retrieve, or N/A here. |
| **Expansion Keys** | **D65 with EXP150**. Keys 1–40 address the first module, 41–80 the second, and so on. Each module has two pages of 20 keys. Adding expansion keys enables the module. |

Line keys belong in **Function Keys**. Repeat a line assignment if you need multiple line buttons. N/A preserves an empty position and can clear a key inherited from a Key Template or legacy Device Profile. Per-device keys override the shared layout at matching positions within each tab.

BLF keys monitor and dial an extension. Speed Dial keys dial the entered destination. Park & Retrieve keys transfer an active call to the selected parking slot or retrieve its parked call.

On D65, Function Keys 1–6 describe the first page. Additional Rapid Dial keys continue through the remaining non-line positions on subsequent pages. On D70, Side Keys use groups of ten per page. FS PBX does not truncate the administrator's layout; the handset determines which positions and pages it supports. Verify paging and labels on the target hardware.

Shared phonebook directories and DPMA applications are outside this template's scope. D50/D70 side panels and EXP150 modules have not been hardware-tested with FS PBX.

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

Use an IANA name rather than the numeric IDs used by the S-series. The phone applies the selected zone's daylight-saving rules. `ntp_server_primary` controls its time server. `admin_password` supplies the administrator PIN when it contains digits only; otherwise the template uses `789`.

**D40/D45/D50/D70:** select UDP or TCP for SIP. **D60/D62/D65:** also support TLS on compatible firmware. HTTPS provisioning and SIP TLS are separate settings: choosing an HTTPS provisioning URL does not change the line's SIP transport. For TLS, select the PBX's TLS listening port and verify firmware/certificate compatibility before deployment. D65 compatibility with the PBX's TLS certificate has not yet been verified.

The older D40/D45/D50/D70 proxy format provides one outbound proxy. Separate primary and backup proxy settings are emitted for D6x firmware 2.9.15 or newer. See [Sangoma's XML configuration reference](https://sangomakb.atlassian.net/wiki/spaces/Phones/pages/24510636).

## Verify and apply changes

1. Check **Last Contact** to confirm the phone reached provisioning.
2. Check **Registrations** for the assigned extension and selected transport.
3. Test inbound/outbound calls, BLF changes, speed dials, and parking/retrieval.
4. After later edits, save and select **Sync**. Use **Restart** when a full handset reboot is needed.

Sync and Restart require a reachable SIP registration. For an unregistered phone, initiate provisioning or restart from the handset. Last Contact alone does not prove registration or successful application of every setting.

If keys do not update, verify the selected key area and any per-device overrides, then sync again. If no provisioning contact appears, check the MAC address, template assignment, DHCP option, saved provisioning server, HTTPS connectivity, and HTTP credentials. See the [Phone Provisioning Overview](/docs/phone-provisioning/) for authentication guidance.
