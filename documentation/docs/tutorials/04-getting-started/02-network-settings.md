---
id: network-settings
title: Network Addresses and FreeSWITCH Variables
sidebar_label: Network Addresses
slug: /getting-started/network-settings/
sidebar_position: 2
---

# Network Addresses and FreeSWITCH Variables

Before registering phones or connecting a carrier, tell FreeSWITCH which address to use on your server and which address other devices can reach. The right values depend on your network layout.

A fresh installation creates two variables:

| Name | Initial value |
| --- | --- |
| `external_sip_ip` | `$${local_ip_v4}` |
| `external_rtp_ip` | `$${local_ip_v4}` |

These are useful starting defaults when the detected server address is reachable by your phones and carrier. **They do not discover your public IP or finish NAT configuration.** A cloud server with a public IP mapped to a private interface needs the NAT setup below.

Use **Network Setup** on the Variables page to create the correct address variables automatically. You choose the network layout and addresses; FS PBX fills in the names, category, order, and server hostname.

## Choose your setup

| Your network | Start here |
| --- | --- |
| A public IP is assigned directly to the server's network interface | [Direct public IP](#direct-public-ip) |
| The server has a private IP and a router or cloud platform provides its public IP | [Behind NAT or a cloud public-IP mapping](#behind-nat-or-a-cloud-public-ip-mapping) |
| All phones and carrier connections use reachable private networks or a VPN | [Private network only](#private-network-only) |
| The server has separate voice, management, VPN, or replication interfaces | [Multiple network interfaces](#multiple-network-interfaces) |
| Two servers share replicated FS PBX configuration | [Two servers with shared configuration](#two-servers-with-shared-configuration) |

All addresses and hostnames in this guide are examples. Replace them with your own. The public addresses beginning with `192.0.2`, `198.51.100`, and `203.0.113` are reserved for documentation and will not work on your server.

## Use Network Setup

1. Open **Advanced → Variables** and expand **Network Setup**. This requires permission to view, add, and edit variables.
2. Check **Current server**. On a redundant installation, open that server through its own management address rather than a shared or load-balanced address.
3. Select **Network layout**: **Direct public IP**, **Behind NAT or a cloud public-IP mapping**, or **Private network or VPN only**.
4. Select **Local voice address** from the detected interfaces. Choose the interface intended to carry calls, rather than a management or replication interface.
5. For NAT, enter **Public SIP address**, using the address your router or cloud provider maps to this server. Audio uses the same public address unless you enable **Use a different public address for audio**.
6. Click **Review changes**. The review shows the existing rows and the values that will replace them, including conflicting definitions that will be disabled.
7. Click **Apply network settings**, then follow [Apply and verify](#apply-and-verify) to restart affected SIP profiles and test calls.

Network Setup saves an explicit `local_ip_v4` for the selected interface. Direct-public and private-network layouts keep `$${local_ip_v4}` as the two external values. NAT uses the public addresses you entered. All three rows receive this server's FreeSWITCH hostname, **Set**, category **IP Address**, and orders **0**, **10**, and **20**. Running setup again updates those rows.

**Shared rows need attention on HA systems.** Existing rows marked **All servers** may become specific to the current server or be disabled to remove conflicts. The review calls this out before applying. Existing rows for other hostnames stay unchanged. Configure the first node, wait for the rows to replicate, then run setup on the next node before syncing or restarting it.

FreeSWITCH must be running so setup can identify its hostname. An unavailable interface cannot be selected for application. If the hostname, selected interface, or saved rows change between review and application, refresh or review again. **Reload saved settings** discards the current form choices and reads the server again.

Saving synchronizes the current server's XML but does not restart SIP profiles or change operating-system addresses, routing, NAT, or firewall rules. A synchronization failure leaves the rows saved and shows a retry message. **Loaded FreeSWITCH variables** shows global values; **SIP Status** shows what running profiles use.

Use the manual editor below for DNS/STUN expressions, routed private external addresses, or profiles with different address requirements. Network Setup handles fixed IPv4 addresses for the common layouts.

## Understand the three variables

| Variable | What it controls in the shipped IPv4 SIP profiles |
| --- | --- |
| `local_ip_v4` | The local address used by `sip-ip` and `rtp-ip`. It must belong to the server. FreeSWITCH normally detects it automatically. |
| `external_sip_ip` | The address used by `ext-sip-ip` for advertised SIP signaling, which sets up and controls calls. |
| `external_rtp_ip` | The address used by `ext-rtp-ip` for advertised RTP media, which carries call audio. |

For a typical server behind a router, `local_ip_v4` is the server's private address and both external variables contain the public address mapped to it. Advertising a public address does not assign that address to the server or create firewall rules. See the [FreeSWITCH address reference](https://developer.signalwire.com/freeswitch/media-and-codecs/handling/#advertising-the-public-address-ext-sip-ip-and-ext-rtp-ip).

The value `$${local_ip_v4}` means “use the value of `local_ip_v4` when FreeSWITCH reads the configuration.” Keep both dollar signs when entering a reference. A literal IP should contain only the address, without a scheme, port, subnet suffix, or quotation marks. [FreeSWITCH variable reference](https://developer.signalwire.com/freeswitch/configuration/core-settings/#global-variables-in-varsxml)

These variables apply to the server, across accounts. The profile names **internal** and **external** describe different SIP services; both shipped IPv4 profiles use these address variables. Changing a variable only affects profiles that reference it. Check **SIP Profiles** if your profiles have been customized.

## Find your addresses first

On each server, run these read-only commands over SSH:

```bash
ip -br -4 address show
ip -4 route show
fs_cli -x 'switchname'
fs_cli -x 'global_getvar local_ip_v4'
```

Record:

- **Local voice address:** an address assigned to this server that should carry SIP and audio.
- **Public voice address, if needed:** the address your router or cloud platform maps to that local address for incoming SIP and RTP.
- **FreeSWITCH name:** the exact output of `switchname`, needed for hostname-specific rows on shared configurations.

If the intended public address appears on the server's interface, use the direct-public-IP example. If only its private counterpart appears, use the NAT example. A public address shown in a cloud dashboard can still be a NAT mapping.

Confirm the incoming mapping in the router or hosting control panel. An IP-check website only shows an outbound address, which may differ from the address that receives your calls. Also identify whether phones reach the PBX through the LAN, a VPN, the public internet, or a mixture of these.

## Edit variables manually

The following reference explains what Network Setup creates and how to handle custom configurations. For the common layouts, use the setup form above instead of entering these rows by hand.

1. Open **Advanced → Variables**. The page heading is **Switch Variables**.
2. Select the **IP Address** category, displayed as **Ip Address** in the list.
3. Edit an existing row, or click **New variable** for a missing variable.
4. Use the fields below, then click **Save**.

| Field | What to enter |
| --- | --- |
| Category | Select the existing **IP Address** category for all three variables. |
| Name | The exact variable name from your scenario. |
| Command | **Set**. |
| Order | Use the order shown in the example tables. |
| Hostname | Leave blank for a single server; use the exact FreeSWITCH name for server-specific rows in a shared configuration. |
| Value | The address or reference shown in your scenario, with your own addresses substituted. |
| Enabled | On for rows you want to apply. |
| Description | An optional short note, such as “Public address for PBX A.” |

### Order matters when values reference other variables

If you add an explicit `local_ip_v4`, give it **Order 0**. Set `external_sip_ip` to **Order 10** and `external_rtp_ip` to **Order 20**. This puts the local address before references to it.

Keep these rows in the same existing category: FS PBX writes categories first, then orders rows within each category. Do not rely on alphabetical display order or equal order values for dependencies. The original two external rows can both use Order 0 while they reference FreeSWITCH's built-in value; adding a local override makes the sequence important.

### Hostname selects the server

- A **blank Hostname** includes the row on every server that generates its configuration.
- A **matching Hostname** includes the row only on that server.
- A **different Hostname** excludes the row from that server's generated `vars.xml`.

Use the exact output of `fs_cli -x 'switchname'`, including its capitalization and any domain suffix. Do not enter `localhost`, an account domain, or the web address unless that is the actual switch name. Each server needs a distinct name. FS PBX uses the operating-system hostname as a fallback when FreeSWITCH cannot be reached; complete setup and verification with FreeSWITCH running.

**A hostname-specific row does not automatically replace a blank-hostname row.** Both can be written. Edit the original row to scope it, or disable the original when replacing it. Aim for one enabled definition of each address variable per server, rather than using duplicate rows to override each other.

## Direct public IP

Example: the server's voice interface owns `203.0.113.10`, and `global_getvar local_ip_v4` already returns that address.

| Name | Value | Order | Hostname |
| --- | --- | --- | --- |
| `external_sip_ip` | `$${local_ip_v4}` | 10 | Leave blank |
| `external_rtp_ip` | `$${local_ip_v4}` | 20 | Leave blank |

The initial values are suitable here. You do not need to add a `local_ip_v4` row when detection is correct. If detection selects another interface, use the [multiple-interface example](#multiple-network-interfaces) to select the intended address.

## Behind NAT or a cloud public-IP mapping

Example: the server owns `10.0.0.19`, and a router or cloud mapping sends traffic for `203.0.113.10` to it.

| Name | Value | Order | Hostname |
| --- | --- | --- | --- |
| `local_ip_v4` | `10.0.0.19` | 0 | Leave blank |
| `external_sip_ip` | `203.0.113.10` | 10 | Leave blank |
| `external_rtp_ip` | `203.0.113.10` | 20 | Leave blank |

The explicit `local_ip_v4` row makes the selected interface clear; it is optional when automatic detection already returns `10.0.0.19`. Keep the local address stable, for example with a static address or DHCP reservation.

**Do not set `local_ip_v4` to the router's public address.** FreeSWITCH needs to bind to the address that actually exists on the server.

The router/cloud configuration must also deliver the SIP ports and RTP UDP range used by this server to `10.0.0.19`, and allow their return traffic. Match the actual profile ports and configured media range. Setting these variables alone does not provide that connectivity.

If you have both LAN and internet phones, test both. Local-network classification in the SIP profile must match your reachable LAN/VPN networks. Phones using a public PBX address from inside the LAN may also need split DNS or NAT loopback. Do not switch the external variables back to a private address just to fix one local phone. [FreeSWITCH NAT and local-network settings](https://developer.signalwire.com/freeswitch/users-and-endpoints/sip-profiles/#nat-traversal)

## Private network only

Example: the server owns `10.20.0.10`, and all phones and carrier connections can reach it over the LAN or a routed VPN.

| Name | Value | Order | Hostname |
| --- | --- | --- | --- |
| `local_ip_v4` | `10.20.0.10` | 0 | Leave blank |
| `external_sip_ip` | `$${local_ip_v4}` | 10 | Leave blank |
| `external_rtp_ip` | `$${local_ip_v4}` | 20 | Leave blank |

Again, omit the explicit local row if detection is already correct. The word “external” in the variable name does not require a public address: the selected address must be reachable by the peer using it. Revisit this setup when adding internet phones or a public SIP carrier.

## Multiple network interfaces

Example: the server has `10.50.0.19` for management or replication and `10.0.0.19` for voice. Incoming voice traffic for public address `203.0.113.10` maps to `10.0.0.19`.

Use the three rows from the NAT example, explicitly setting `local_ip_v4` to **`10.0.0.19`**. Do this when automatic detection picks the management, VPN, or replication address instead. For a directly assigned public voice interface, set `local_ip_v4` to that interface's address and use `$${local_ip_v4}` for both external values.

Changing `local_ip_v4` affects every configuration reference to that variable. It does not change Linux routing or automatically correct the SIP profile's local-network ACL. Check routing and local-network classification as well as the selected address.

If different carriers or profiles genuinely need different local interfaces or public addresses, one shared set of three variables is insufficient. Configure the addresses or dedicated variables on the affected **SIP Profiles**; adding more unscoped rows with the same variable name does not select an address by carrier or call.

## Two servers with shared configuration

When two servers share replicated variable records, give each server its own address rows using **Hostname**. This example assumes each server has its own public address and NAT mapping:

| Server | FreeSWITCH name | Local voice IP | Public voice IP |
| --- | --- | --- | --- |
| A | `pbx-a` | `10.0.0.19` | `203.0.113.10` |
| B | `pbx-b` | `10.80.0.19` | `198.51.100.20` |

Create this final set of enabled rows, all in the existing **IP Address** category with Command **Set**:

| Name | Value | Order | Hostname |
| --- | --- | --- | --- |
| `local_ip_v4` | `10.0.0.19` | 0 | `pbx-a` |
| `external_sip_ip` | `203.0.113.10` | 10 | `pbx-a` |
| `external_rtp_ip` | `203.0.113.10` | 20 | `pbx-a` |
| `local_ip_v4` | `10.80.0.19` | 0 | `pbx-b` |
| `external_sip_ip` | `198.51.100.20` | 10 | `pbx-b` |
| `external_rtp_ip` | `198.51.100.20` | 20 | `pbx-b` |

Run **Network Setup** on each node to build these rows automatically. If configuring them manually from the initial defaults:

1. Edit the existing two external rows with server A's public address, order, and hostname.
2. Add server A's `local_ip_v4` row.
3. Use **New variable** to add server B's three rows with its values and hostname.
4. Check for any other enabled definitions of these names, especially rows with blank Hostname. Disable conflicting rows.
5. Wait for the saved rows to appear on both servers, then follow [Apply and verify](#apply-and-verify) on each server directly.

After synchronization, server A's generated file should contain A's three address definitions; server B's should contain B's. Both sets remain visible in the shared Variables list. That is expected.

**Sync XML acts on the server handling your web request.** Use a known direct management address for each node; a shared or load-balanced web address can send both requests to the same node. Database replication does not itself regenerate and reload the peer's local `vars.xml`. Generate that file separately on each server; do not copy A's generated file to B.

Two independent installations without shared configuration can each use the single-server examples with Hostname blank.

### Shared public IP, floating IP, or SIP proxy

The six-row example assumes separate public mappings. If both servers sit behind one public address, NAT must still direct each SIP and RTP flow to the correct server; identical public variable values alone do not arrange that. A floating address must actually move or be routed to the active node. A SIP proxy or SBC can also make the advertised signaling and media addresses different.

Use the addresses required by that network design. Hostname filtering selects configuration; it does not provide failover, move an IP, or transfer active calls.

## Changing public addresses

For a static public mapping, enter the known public IP directly. For a changing address, FreeSWITCH also supports these external-value forms:

| Value form | Requirement |
| --- | --- |
| `host:voice.example.com` | DNS must return this server's intended public voice address. |
| `stun:stun.example.com` | A reachable STUN service must return an address suitable for incoming voice traffic. |
| `auto-nat` | A compatible NAT-PMP/UPnP gateway must provide the mapping. |

These are alternatives for **external** values, not values for `local_ip_v4`. They are resolved when the SIP profile loads and are not continuous public-IP monitoring. A DNS or WAN-address change needs the affected profile to be refreshed through a restart and verified. `auto` selects a local address; it is not public-IP discovery. [FreeSWITCH external-address options](https://developer.signalwire.com/freeswitch/media-and-codecs/handling/#ext-ip-value-forms)

On two servers, avoid resolving both nodes through a shared round-robin or failover hostname unless the network is designed for that result. Each node must advertise an address whose traffic reaches the correct SIP and media services.

## Apply and verify

### Make the selected interface available at boot

Network Setup saves the selected local address explicitly, avoiding a fresh interface guess on each restart. That address must still exist before FreeSWITCH can bind its SIP profiles.

Without an explicit local definition, a failed startup address lookup can leave `local_ip_v4` as `127.0.0.1`, or an available alternate route can select the wrong interface. FreeSWITCH periodically checks network addresses, but that is not a guarantee that profiles started with incorrect addresses will recover automatically. Likewise, an explicit address does not make a delayed interface ready.

If this happens after a reboot, check when the voice interface receives its address and whether the operating system's network-online service actually waits for it. Once the address is available, check **SIP Status**, start or restart affected profiles, and test calls. Network Setup does not alter service startup dependencies or enable automatic profile restarts.

### Save and synchronize each server

Saving a variable writes the current server's `vars.xml` and requests an XML reload. If FS PBX reports a synchronization failure, the rows remain saved; fix the reported problem and click **Sync XML** to retry.

Once all rows are correct, click **Sync XML** on each server that uses them. Replicated rows and a successful save on server A do not confirm server B's running settings.

### Restart the affected SIP profiles

**Do this before putting a new server into service, or during a maintenance window. Restarting a SIP profile interrupts calls using it.**

An XML reload does not replace address settings already held by a running SIP profile. Open **SIP Status** and use **Restart** for each affected profile. The shipped IPv4 profiles are `internal` and `external`; custom profiles may use the variables too. **Rescan** is not a substitute for restarting after an IP/bind-address change.

The equivalent commands for the two standard profiles are:

```bash
fs_cli -x 'sofia profile internal restart'
fs_cli -x 'sofia profile external restart'
```

Use only the profiles present on your installation. See the [FreeSWITCH profile management commands](https://developer.signalwire.com/freeswitch/reference/cli-and-api/#profile-management-subcommands).

### Check the active addresses

On each server, run:

```bash
fs_cli -x 'switchname'
fs_cli -x 'global_getvar local_ip_v4'
fs_cli -x 'global_getvar external_sip_ip'
fs_cli -x 'global_getvar external_rtp_ip'
fs_cli -x 'sofia status profile internal'
fs_cli -x 'sofia status profile external'
```

The global values show the loaded variables. For a `host:` or `stun:` value, the global variable may still contain that expression; check the profile for its resolved address. Profile status shows the addresses actually in use: compare **SIP-IP** and **RTP-IP** with the local voice address, and **Ext-SIP-IP** and **Ext-RTP-IP**, when shown, with the intended external addresses. [FreeSWITCH address troubleshooting](https://developer.signalwire.com/freeswitch/troubleshooting/audio/)

For the two-server example, A must use `10.0.0.19` and `203.0.113.10`; B must use `10.80.0.19` and `198.51.100.20`.

To inspect just these definitions in the generated file on a standard installation:

```bash
grep -nE 'data="(local_ip_v4|external_sip_ip|external_rtp_ip)=' /etc/freeswitch/vars.xml
```

Use your configured FreeSWITCH configuration directory if it differs. Inspect the file here; make changes through **Variables**, because a later synchronization rewrites it.

### Test real calls

Register a phone and test inbound and outbound calls with audio in both directions. Include LAN, VPN, and internet phones where applicable, plus hold/resume and transfers. On redundant systems, test through each node separately.

A registered phone or a correct status screen does not prove the audio path works. If a call connects without two-way audio, check the advertised media address and the RTP firewall/NAT path. A call trace's SDP `c=` line identifies the media address and its `m=` line identifies the media port. [FreeSWITCH audio troubleshooting](https://developer.signalwire.com/freeswitch/troubleshooting/audio/)

## Common mistakes

| Symptom | Check first |
| --- | --- |
| A fresh cloud installation advertises a private address to internet peers | Replace the two external values with the public voice mapping. |
| A profile fails to start after an address change | Confirm its local bind address exists on this server and inspect the FreeSWITCH error. |
| External values still use the previous local address | Check category and order; load the local definition before references, then sync and restart the affected profiles. |
| Both nodes use one node's addresses | Look for blank-hostname rows, duplicate switch names, or a generated file copied between nodes. |
| A node's address rows are missing from its generated file | Compare Hostname with that node's exact `switchname` output and check Enabled. |
| Saved values look right but profile status is unchanged | Sync XML on that node and restart each affected SIP profile. |
| LAN calls work but internet calls have no audio | Check the external media address and incoming/return RTP mapping. |
| Internet calls work but LAN or VPN calls fail | Check routing, local-network classification, and how local phones resolve and reach the PBX. |
