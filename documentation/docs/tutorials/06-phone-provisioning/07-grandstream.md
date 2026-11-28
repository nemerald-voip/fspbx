---
id: grandstream-provisioning
title: Grandstream
slug: /phone-provisioning/grandstream
sidebar_position: 7
---

# Grandstream

Use this guide to configure a Grandstream **GXP or GRP desk phone**, or an **HT841 / HT881 gateway**, directly from its web interface. The device downloads its configuration from FS PBX using the server path and HTTP credentials you enter. Field names vary by model and firmware.

## Prepare FS PBX

1. Select the intended account and create or edit the device under **Devices**.
2. Enter the phone's **MAC address** and select the **Device Template** for its model or supported family.
3. Under **Lines**, assign an enabled extension and review its SIP server, proxy, transport, and port.
4. For desk phones, assign a **Key Template** or configure individual function keys. For HT841 / HT881 gateways, follow the port assignments below. Save the device.

Get the provisioning HTTP username and password from `http_auth_username` and `http_auth_password` in the `provision` category under **Default Settings**, with account overrides in **Domain Settings**. These are separate from the phone administrator login and the extension's SIP credentials. If source-IP restrictions are enabled, allow the address FS PBX sees from the phone's network.

## HT841 / HT881 gateways

Select the Grandstream **HT8x1** Device Template. Connect an analog phone or fax machine to **FXS** and telephone lines to **FXO**.

For a complete incoming and outgoing call setup, including a gateway behind a remote router, follow [Grandstream HT841 / HT881 FXO calling](/docs/phone-provisioning/grandstream-ht8x1-fxo/). The walkthrough covers registration, incoming-call forwarding, and dialing outside numbers from local extensions.

| Device line in FS PBX | HT841 port | HT881 port |
| --- | --- | --- |
| 1 | FXS | FXS |
| 2–5 | FXO 1–4 | FXO 1–4 |
| 6–9 | Not available | FXO 5–8 |

Assign an extension to each port you use. You can leave Line 1 unused when only connecting telephone lines. Keep the line numbers matched to the physical ports; removing a line disables that port and clears its account credentials on the next provisioning.

The gateway identifies its model when it contacts FS PBX. To select the model before its first contact, add `grandstream_ht8x1_model` with value `HT841` or `HT881` under the device's **Advanced** tab. Use **Name** for the setting name, **Value** for its value, and enable the row.

Each FXO port has its own credentials. FXO ports can share up to **two combinations of SIP server, proxy, transport, and registration interval**. Use matching values for ports sharing a connection. The FXS port has its own separate connection.

### Incoming telephone-line calls

Add the analog line's DID under **Phone Numbers** and choose its destination there. Give the extension that registers the FXO port the `public` context. Under the device's **Advanced** tab, add an enabled setting named `grandstream_ht8x1_fxo_N_destination`, replacing `N` with the physical FXO port number. Enter the DID in a format its inbound route accepts. For example, set `grandstream_ht8x1_fxo_1_destination` to `16105550101` for a North American line with that number.

Set a destination for every telephone line that needs automatic incoming-call routing. A blank or removed destination clears that forwarding setting. Incoming telephone-line calls do not ring the attached FXS phone by default.

### Outgoing calls and line settings

The template uses **one-stage dialing**. Create an outbound route to send the dialed number to one or more FXO ports in hunt order. If users currently dial the port extension and wait for a second dial tone, save `grandstream_ht8x1_fxo_stage_method` with value `2` before the next provisioning. [Set up an outbound route](/docs/phone-provisioning/grandstream-ht8x1-fxo/#outgoing-calls-from-local-extensions).

These optional settings go under the device's **Advanced** tab and apply to both FXO connections:

| Name | Values / default |
| --- | --- |
| `grandstream_ht8x1_fxo_stage_method` | `1` for direct dialing (default), `2` for a second dial tone |
| `grandstream_ht8x1_fxo_wait_for_dial_tone` | `0` to start dialing (default), `1` to wait for the provider's tone |
| `grandstream_ht8x1_fxo_number_of_rings` | Both FXO profiles wait `2` rings by default. Change the server-wide value under **Default Settings → provision**, or override it for an account or device. Increase it if the provider delivers caller ID later. |
| `grandstream_ht8x1_fxo_from_domain` | Defaults to the line's server address. An enabled text setting with an empty value clears **From Domain** on both FXO profiles. |
| `grandstream_ht8x1_fxo_country` | Grandstream country code for country-based AC termination, e.g. `0` for USA or `17` for Canada. Set only if required. |
| `grandstream_ht8x1_fxo_ac_termination` | `0` for country-based, `1` for impedance-based, `2` for auto-detect. Omit to retain the device setting. |
| `grandstream_ht8x1_fxo_current_disconnect` | `1` = detect loss of line current (default); `0` = disable |
| `grandstream_ht8x1_fxo_current_disconnect_threshold` | Disconnect duration in milliseconds; default `100` |
| `grandstream_ht8x1_fxo_disconnect_tone_detection` | `1` = detect disconnect tone; `0` = disable (default) |
| `grandstream_ht8x1_fxo_disconnect_tone` | Your telephone provider's tone definition in Grandstream's `fxoch` format |
| `grandstream_ht8x1_fxo_polarity_reversal` | `1` = detect polarity reversal; `0` = disable (default) |
| `grandstream_ht8x1_pstn_ring_thru_fxs` | `1` = also ring the FXS phone; `0` = disable (default) |

Use your telephone provider's requirements for disconnect detection. Save and provision the gateway, then test incoming calls, outgoing calls, and hangup detection on each connected telephone line. See Grandstream's [HT841 / HT881 manual](https://documentation.grandstream.com/knowledge-base/ht841-ht881-user-manual/) for its analog-line options.

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

Keep the `/prov/` path and trailing slash. See the [provisioning URL guide](/docs/phone-provisioning/#provisioning-url) for the common setup.

Enter FS PBX details in **Config Server** fields. **Firmware Server** fields control software downloads and may have separate credentials. On older models, the configuration credentials may simply be labeled **HTTP/HTTPS User Name** and **HTTP/HTTPS Password**. Leave configuration filename prefixes and postfixes empty for the standard FS PBX setup.

Click **Save and Apply**, then use **Provision** if offered or reboot the phone. Allow it to finish downloading and applying its configuration.

## Verify and manage the phone

1. Confirm that **Last Contact** updates on the device in FS PBX.
2. Open **Registrations** and check the assigned extension and expected SIP transport.
3. Test inbound and outbound calls, then test the configured function keys.

To use SIP TLS, select it separately in the device's line settings. HTTPS protects configuration downloads.

Make subsequent line and key changes in FS PBX, save, and select **Sync**. If the phone is unregistered, use its provisioning control or reboot it directly. Later provisioning can replace locally entered account and key settings.

## Troubleshooting

| Symptom | Administrator checks |
| --- | --- |
| Phone returns to a different server | Review DHCP provisioning overrides and any existing Grandstream cloud/provider assignment. |
| Last Contact does not update | Check HTTPS mode, the Config Server Path, DNS, firewall access, certificate trust, phone time, and source-IP restrictions. |
| Authentication fails | Check the configuration-server credentials. Firmware-server credentials alone may not authenticate configuration downloads. |
| Configuration downloads but registration fails | Verify the template, assigned extension, account domain, SIP proxy, transport, port, and firewall. |

For shared authentication settings, see the [Phone Provisioning Overview](/docs/phone-provisioning/).
