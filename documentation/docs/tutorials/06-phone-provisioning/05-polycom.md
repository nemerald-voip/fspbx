---
id: polycom-provisioning
title: Polycom / Poly
slug: /phone-provisioning/polycom
sidebar_position: 5
---

# Polycom / Poly

Use this guide to point a Polycom or Poly VVX phone directly at FS PBX through its web interface. It covers phones running **UC Software in Generic / OpenSIP mode**. OBi Edition and Teams firmware use different workflows. For cloud redirection, see [Polycom ZTP](/docs/phone-provisioning/polycom-ztp/).

## Prepare FS PBX

1. Select the intended account and create or edit the phone under **Devices**.
2. Enter its **MAC address** and select the matching **Device Template**. Use **polycom/VVX** for supported VVX phones running UC Software.
3. Under **Lines**, assign an enabled extension and review its SIP server, proxy, transport, and port.
4. Assign a **Key Template** or configure individual function keys, then save.

Have the provisioning HTTP username and password ready. These are `http_auth_username` and `http_auth_password` in the `provision` category under **Default Settings**, with account overrides in **Domain Settings**. They are separate from the phone's administrator password and the extension's SIP credentials. If source-IP restrictions are enabled, allow the address FS PBX sees from the phone's network.

## Enter the provisioning server

Find the phone's IP address in its network status or the router's DHCP leases. From a computer with access to that network, open the phone's IP address in a browser and sign in as **Admin** using its current administrator password.

Open **Settings > Provisioning Server** and enter:

| Field | Value |
| --- | --- |
| Server Type | HTTPS |
| Server Address | `pbx.example.com/prov/` |
| Server User | FS PBX provisioning HTTP username |
| Server Password | FS PBX provisioning HTTP password |
| DHCP Menu > Boot Server | Static |

Replace `pbx.example.com` with the reachable FS PBX hostname. HTTPS is selected separately, so the address shown above uses the hostname and path. **Static** applies to provisioning-server discovery; the phone can still obtain its IP address by DHCP. Poly documents the server protocol, address, credentials, and static boot-server behavior in its [UC Software administrator guide](https://kaas.hpcloud.hp.com/pdf-public/pdf_9122088_en-US-1.pdf#page=531).

Use a current, versioned FS PBX device template with the `/prov/` path. Keep the trailing slash and let the phone request its own files. See the [provisioning URL guide](/docs/phone-provisioning/#provisioning-url) for the common setup.

Click **Save** and accept any restart prompt. If the phone does not restart or download immediately, reboot it after saving. Menu labels can vary with UC Software version.

## HTTPS certificates and ciphers on older phones

An older Polycom phone may reject the server's HTTPS certificate even when a current browser trusts it. Its CA store can be outdated, and its enabled cipher list may not support the server's certificate type. Some phones need **both a manual CA installation and a cipher change before the first configuration download**.

### Install the missing certificate authority

1. Obtain the required root CA certificate in PEM format from the certificate authority. Confirm which root signs the server's chain; for Let's Encrypt, use its [current CA certificates](https://letsencrypt.org/certificates/), such as **ISRG Root X1** or **ISRG Root X2**, as appropriate for that chain. The server must also supply its intermediate certificates.
2. In the phone's web interface, open **Settings > Network > TLS > Certificate Configuration** and select **CA Certificates**.
3. Install the CA in an available **Platform CA** slot, using the certificate file or download URL supported by that firmware. If a URL is required, the phone must already be able to access that certificate location. Verify the certificate fingerprint before accepting it.
4. Under **TLS Applications**, check the platform profile selected for **Provisioning**, normally **Platform 1**. In **TLS Profiles**, ensure that profile's CA selection includes the installed certificate, then save.

Platform CA certificates are available during initial provisioning. Poly explains their installation and profile selection in its [certificate guidance](https://kaas.hpcloud.hp.com/pdf-public/pdf_12968044_en-US-1.pdf#page=20). Check that the phone's date and time are correct when validating certificates.

### Adjust the provisioning cipher list when needed

Some UC Software releases include `!ECDSA` in their default cipher list. That exclusion can prevent an HTTPS connection to a server using an ECDSA certificate even after the CA is trusted.

1. Open **Settings > Network > TLS > TLS Profiles** and edit the platform profile used by **Provisioning**.
2. Change the cipher **Type** to **Custom** and start with the existing cipher list.
3. For an ECDSA server, remove the `!ECDSA` exclusion if present, leaving the remaining exclusions intact. The resulting list must allow a cipher supported by both the phone firmware and server.
4. Save, reboot, and check for a new **Last Contact** in FS PBX.

See [Poly's cipher configuration instructions](https://h30434.www3.hp.com/t5/Desk-and-IP-Conference-Phones/FAQ-Changing-cipher-suites-on-Poly-IP-Phones/td-p/9046307). Changing the list cannot add algorithms that the firmware does not implement; a supported firmware update or a compatible provisioning endpoint may still be needed. A profile can also be shared with SIP TLS, so check its application assignments before editing it.

These initial changes must be made on the phone when HTTPS provisioning is blocked: the phone cannot download a configuration that fixes the connection it needs for that download.

## Verify and manage the phone

1. Check that **Last Contact** updates on the device in FS PBX.
2. Open **Registrations** and confirm the assigned extension and expected SIP transport.
3. Test inbound and outbound calls and any BLF, speed-dial, or parking keys.

HTTPS protects configuration downloads; SIP uses the transport selected under the device's line settings. A recent Last Contact does not prove SIP registration or successful application of every setting.

Make later line and key changes in FS PBX, save, and use the device's **Sync** action. If it is unregistered, reboot it directly to retry provisioning. Provisioning can replace settings entered locally on the phone.

## Troubleshooting

| Symptom | Administrator checks |
| --- | --- |
| Phone uses an old provider or server | Confirm **Boot Server = Static** and the saved server address. Review any existing ZTP assignment before repurposing or resetting the phone. |
| Cannot open the web interface | Confirm the IP address, network access, administrator credentials, and that web access is enabled on the phone. |
| Last Contact does not update | Check DNS, HTTPS reachability, the server path, HTTP credentials, phone time, and source-IP restrictions. On older phones, follow the [CA installation and cipher steps](#https-certificates-and-ciphers-on-older-phones). |
| Download succeeds but registration fails | Check the account, assigned extension, SIP server/proxy, transport, port, and firewall. |
| Changes do not apply | Confirm the selected Device Template and key assignments, then sync or reboot. Check whether saved local overrides take precedence on that firmware. |

For shared authentication settings, see the [Phone Provisioning Overview](/docs/phone-provisioning/).
