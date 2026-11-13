---
id: sangoma-p-series
title: Sangoma P-Series
slug: /phone-provisioning/sangoma-p-series
sidebar_position: 20
---

# Sangoma P-Series

Connect Sangoma **P325, P330, and P370** phones to FS PBX using the **sangoma/P-Series** device template.

Set the provisioning address through **DHCP Option 66** on the phone's network. The phone's **Sangoma Configuration Server** menu is not used for this setup.

## Prepare provisioning

Before adding the device, confirm that:

- The extension exists and is enabled in the intended FS PBX account.
- The phone can resolve and reach your provisioning hostname over HTTPS and your SIP server on the selected transport and port.
- You have the phone's MAC address and the provisioning credentials configured for that account.

Use this provisioning URL with your PBX hostname:

```text
https://pbx.example.com/prov/
```

Configure provisioning authentication in the `provision` category under **Default Settings**, or use **Domain Settings** for account-specific overrides. The HTTP credential settings are `http_auth_username` and `http_auth_password`; these are separate from the extension's SIP credentials. If `cidr` restrictions are enabled, allow the phone's source address as seen by FS PBX, usually the site's public IP for a remote deployment.

## Create the device and assign lines

1. Select the correct FS PBX account, then open **Devices** and create or edit the device.
2. Enter the phone's **MAC address** and select **sangoma/P-Series** as the **Device Template**.
3. Under **Lines**, assign the required extension or extensions.
4. Open **Advanced Line Settings** and verify the SIP server, proxy addresses, **SIP Transport**, and **SIP Port** for your deployment.
5. Assign a **Key Template** if the phone should use a shared button layout, then save.

### SIP transport and TLS

The template supports UDP, TCP, and TLS. To register over TLS, explicitly select **TLS** in **SIP Transport** and set **SIP Port** to your PBX's TLS listening port. Verify that TLS is enabled on the PBX and that the port is reachable from the phone's network.

An HTTPS provisioning URL does not select TLS for SIP registration. After applying the configuration, check **Registrations** to confirm the expected transport.

## Set phone preferences

Manage these values in the `provision` category under **Default Settings**, with **Domain Settings** overrides where accounts need different values:

| Setting | Administrator guidance |
| --- | --- |
| `sangoma_p_time_zone` | Use an IANA time zone such as `America/Los_Angeles` or `Europe/London`. The default is `America/Los_Angeles`. |
| `ntp_server_primary` | Set a time server reachable by the phone. The template defaults to `0.pool.ntp.org`. |
| `admin_password` | Set the phone administrator PIN using digits only. |

Save the settings and sync affected devices to apply them.

## Configure function keys

Use **Devices > Key Templates** for reusable layouts, or edit a device's **Function Keys** for individual assignments. Per-device keys override a shared layout at matching key positions. A device can use either a **Key Template** or a legacy **Device Profile** as its shared layout.

Keep key **1** assigned to **Line** and select an existing device line. Configure the remaining keys as needed:

| Type | Configuration and behavior |
| --- | --- |
| Line | Select a device line. Assign the same line to multiple keys when additional line buttons are needed. |
| N/A | Leave a position unused. This can also clear a key inherited from a shared layout. |
| BLF | Select the extension to monitor and optionally set a label. The key shows its call status and dials the extension. |
| Speed Dial | Enter the destination and an optional label. The destination must be dialable through the assigned extension. |
| Park & Retrieve | Select a configured parking slot and optionally set a label. The key parks an active call or retrieves a call from that slot. |

For example, a six-key layout for extension 100 could be:

| Key | Type | Value | Label |
| --- | --- | --- | --- |
| 1 | Line | Line 1 — 100 | — |
| 2 | Line | Line 1 — 100 | — |
| 3 | N/A | — | — |
| 4 | BLF | 101 | Reception |
| 5 | Speed Dial | A valid dialable number | Main Office |
| 6 | Park & Retrieve | Park 1 (5901) | Park 1 |

Choose key positions available on your phone. Save and sync, then check the buttons on the handset. Expansion modules and shared phonebook directories are not supported by this setup.

## Set DHCP Option 66

Configure Option **66** as a **string** on the DHCP server serving the phone's network. Some routers label it **TFTP Server Name** or **Boot Server**; enter the complete HTTPS URL, including the provisioning HTTP credentials when authentication is enabled:

```text
https://PROVISION_USER:PROVISION_PASSWORD@pbx.example.com/prov/
```

Replace all placeholders with your account's values. If a credential contains reserved URL characters, percent-encode its username or password component. Keep the `/prov/` path and trailing slash. When HTTP authentication is not configured, use `https://pbx.example.com/prov/` instead.

Apply the DHCP option to the intended phone network or reservations. Restart the phone so it obtains the option. An unconfigured phone should discover the server; if multiple servers appear, select the HTTPS destination for FS PBX. A previously configured phone can keep using its saved server, so changing DHCP alone may not redirect it. Reconfigure its saved destination or perform a planned factory reset when repurposing it.

## Verify provisioning

1. Confirm the phone received the intended DHCP Option 66 value.
2. Let the phone finish downloading and applying its configuration.
3. Check **Last Contact** on the FS PBX device record to confirm the phone reached the provisioning service.
4. Check **Registrations** for the assigned extension and expected SIP transport.
5. Test inbound and outbound calls, BLF status changes, speed dials, and parking and retrieval before handing over the phone.

## Apply subsequent changes

Save changes in FS PBX, then select **Sync** from the device's action menu. Use **Restart** when you need to reboot the phone.

Both actions depend on a reachable SIP registration. For initial setup or an unregistered phone, trigger provisioning or a restart directly on the handset after correcting connectivity or provisioning settings. Keep ongoing configuration changes in FS PBX, since later provisioning can replace settings entered directly on the phone.

## Troubleshooting

| Symptom | Administrator checks |
| --- | --- |
| Last Contact does not update | Verify DHCP Option 66, any previously saved configuration server, the MAC address, account, template assignment, `/prov/` URL, DNS, HTTPS access, HTTP credentials, and source-IP restrictions. |
| Last Contact updates, but the extension is unregistered | Verify that the extension is enabled and assigned to a device line. Check SIP credentials, server and proxy addresses, transport, port, and firewall access. |
| Keys are missing or incorrect | Check the assigned Key Template or Device Profile and any per-device overrides. Confirm key 1 is a Line key, save and sync, then check which positions the handset displays. |
| BLF or parking keys do not work | Verify the selected extension or parking slot, test the destination from the phone, and confirm the phone remains registered. |
| Sync or Restart has no effect | Check for an active, reachable registration. If absent, use the handset's provisioning or restart controls and verify that Last Contact updates. |
| Time is incorrect | Check `sangoma_p_time_zone`, account overrides, and access to the configured NTP server, then sync the device. |

For provisioning credentials and access settings, see the [Phone Provisioning Overview](/docs/phone-provisioning/).
