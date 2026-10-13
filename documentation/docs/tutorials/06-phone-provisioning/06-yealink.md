---
id: yealink-provisioning
title: Yealink
slug: /phone-provisioning/yealink
sidebar_position: 6
---

# Yealink

Use this guide to enter the FS PBX provisioning URL and credentials directly on a Yealink **SIP phone** through its web interface. Menu labels vary by model and firmware. For cloud redirection, see [Yealink RPS](/docs/phone-provisioning/yealink-rps/).

## Prepare FS PBX

1. Select the intended account and create or edit the phone under **Devices**.
2. Enter the **MAC address** and select the **Device Template** matching its model or supported family.
3. Under **Lines**, assign an enabled extension and review the SIP server, proxy, transport, and port.
4. Assign a **Key Template** or configure individual function keys, then save.

Get the provisioning HTTP credentials from `http_auth_username` and `http_auth_password` in the `provision` category under **Default Settings**, or the account's **Domain Settings** overrides. These are separate from the phone administrator login, SIP extension credentials, and Yealink RPS API credentials. If source-IP restrictions are enabled, allow the address FS PBX sees from the phone's network.

## Enter the provisioning URL and credentials

Find the phone's IP address on its status screen or in the router's DHCP leases. Open that address from a browser with access to the phone's network, and sign in using the phone's administrator account.

Open **Settings > Auto Provision** and enter:

| Field | Value |
| --- | --- |
| Server URL | `https://pbx.example.com/prov/` |
| User Name / Username | FS PBX provisioning HTTP username |
| Password | FS PBX provisioning HTTP password |

Replace the hostname with your reachable FS PBX hostname. Use a current, versioned FS PBX device template, and include `https://`, `/prov/`, and the trailing slash in the URL. The phone adds its own filenames. See the [provisioning URL guide](/docs/phone-provisioning/#provisioning-url) for the common setup.

For a manually assigned server, set provisioning **DHCP Active** and **PNP Active** to **Off** when those discovery services would otherwise supply a different destination. These switches control provisioning discovery, not DHCP addressing for the phone. Check any existing RPS assignment if the phone returns to another provider after a reset.

Click **Confirm** to save, then **Auto Provision Now** and accept the prompt. Allow the phone to finish applying the configuration and any resulting restart. Yealink illustrates these controls in its [SIP phone auto-provisioning guide](https://support.yealink.com/docs/sip-t46u/yealink-sip-ip-phones-auto-provisioning-guide-v1-5-pdf/2fc6c7bd89fb4fe692f3867ea363ff4a).

## Verify and manage the phone

1. Confirm that **Last Contact** updates on the FS PBX device.
2. Check **Registrations** for the assigned extension and expected SIP transport.
3. Test inbound and outbound calls, then verify the provisioned function keys.

An HTTPS Server URL controls configuration downloads. Select SIP TLS separately in the FS PBX device's line settings if required by the deployment. Last Contact alone does not confirm registration or that every setting was applied.

For later changes, save the device or Key Template in FS PBX and select **Sync**. Use **Auto Provision Now** from the phone's web interface if it has no reachable SIP registration. Keep ongoing configuration in FS PBX so a later download does not undo local changes.

## Troubleshooting

| Symptom | Administrator checks |
| --- | --- |
| Phone contacts the wrong server | Review the saved URL, provisioning DHCP/PNP switches, and any RPS assignment. |
| No provisioning contact | Check DNS, HTTPS access, the complete URL, HTTP username/password, certificate trust, phone time, and source-IP restrictions. |
| Authentication keeps failing | Use the effective account provisioning credentials rather than the extension password or RPS API keys. |
| Configuration downloads but the line is unregistered | Verify the MAC/account association, assigned extension, SIP server/proxy, transport, port, and firewall. |
| Buttons differ from FS PBX | Verify the selected template, Key Template, and per-device overrides. Save and run Auto Provision Now again. |

For shared authentication settings, see the [Phone Provisioning Overview](/docs/phone-provisioning/).
