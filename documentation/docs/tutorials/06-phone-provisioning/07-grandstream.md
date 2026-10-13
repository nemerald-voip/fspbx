---
id: grandstream-provisioning
title: Grandstream
slug: /phone-provisioning/grandstream
sidebar_position: 7
---

# Grandstream

Use this guide to configure a Grandstream **GXP or GRP desk phone** directly from its web interface. The phone downloads its configuration from FS PBX using the server path and HTTP credentials you enter. Field names vary by model and firmware.

## Prepare FS PBX

1. Select the intended account and create or edit the device under **Devices**.
2. Enter the phone's **MAC address** and select the **Device Template** for its model or supported family.
3. Under **Lines**, assign an enabled extension and review its SIP server, proxy, transport, and port.
4. Assign a **Key Template** or configure individual function keys, then save.

Get the provisioning HTTP username and password from `http_auth_username` and `http_auth_password` in the `provision` category under **Default Settings**, with account overrides in **Domain Settings**. These are separate from the phone administrator login and the extension's SIP credentials. If source-IP restrictions are enabled, allow the address FS PBX sees from the phone's network.

## Enter the configuration server

Find the phone's IP address on its network status screen or in the router's DHCP leases. Open that address from a browser with network access to the phone and sign in as the phone administrator.

Go to **Maintenance > Upgrade and Provisioning**. In the **configuration** section, enter:

| Field | Value |
| --- | --- |
| Config Upgrade Via / Upgrade Via | HTTPS |
| Config Server Path | `pbx.example.com/prov/` |
| Config Server Username / Config HTTP/HTTPS User Name | FS PBX provisioning HTTP username |
| Config Server Password / Config HTTP/HTTPS Password | FS PBX provisioning HTTP password |
| Allow DHCP Option 43 and Option 66 Override Server | No, when using the manually entered server |

Replace `pbx.example.com` with your reachable FS PBX hostname. Use the hostname and path without `https://` for compatibility with older phones; the separate HTTPS selector chooses the protocol. Recent GRP firmware also accepts a scheme in the path. Grandstream documents the fields in its [GXP17xx guide](https://documentation.grandstream.com/knowledge-base/gxp17xx-series-administration-guide/) and [GRP administration guide](https://documentation.grandstream.com/knowledge-base/grp261x-grp262x-grp263x-series-administration-guide/).

Use a current, versioned FS PBX device template with the `/prov/` path shown above. Keep the trailing slash and let the phone choose its own configuration filenames. See the [provisioning URL guide](/docs/phone-provisioning/#provisioning-url) for the common setup.

Enter FS PBX details in **Config Server** fields. **Firmware Server** fields control software downloads and may have separate credentials. On older models, the configuration credentials may simply be labeled **HTTP/HTTPS User Name** and **HTTP/HTTPS Password**. Leave configuration filename prefixes and postfixes empty for the standard FS PBX setup.

Click **Save and Apply**, then use **Provision** if offered or reboot the phone. Allow it to finish downloading and applying its configuration.

## Verify and manage the phone

1. Confirm that **Last Contact** updates on the device in FS PBX.
2. Open **Registrations** and check the assigned extension and expected SIP transport.
3. Test inbound and outbound calls, then test the configured function keys.

HTTPS provisioning and SIP TLS are separate settings. The phone's SIP transport comes from its FS PBX line configuration. Last Contact confirms a request, not successful registration or application of every setting.

Make subsequent line and key changes in FS PBX, save, and select **Sync**. If the phone is unregistered, use its provisioning control or reboot it directly. Later provisioning can replace locally entered account and key settings.

## Troubleshooting

| Symptom | Administrator checks |
| --- | --- |
| Phone returns to a different server | Review DHCP provisioning overrides and any existing Grandstream cloud/provider assignment. |
| Last Contact does not update | Check HTTPS mode, the Config Server Path, DNS, firewall access, certificate trust, phone time, and source-IP restrictions. |
| Authentication fails | Check the configuration-server credentials. Firmware-server credentials alone may not authenticate configuration downloads. |
| Only some file requests succeed | Phones may probe several filenames. Check for a successful device configuration download and registration before treating every missing optional file as a failure. |
| Configuration downloads but registration fails | Verify the template, assigned extension, account domain, SIP proxy, transport, port, and firewall. |

For shared authentication settings, see the [Phone Provisioning Overview](/docs/phone-provisioning/).
