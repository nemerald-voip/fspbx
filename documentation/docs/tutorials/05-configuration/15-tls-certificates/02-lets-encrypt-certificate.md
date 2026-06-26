---
id: freeswitch-lets-encrypt-certificate
title: Let's Encrypt certificate
slug: /configuration/tls-certificates/lets-encrypt/
sidebar_position: 2
---

# Using a Let's Encrypt certificate with FreeSWITCH

FS PBX can issue, install, renew, and distribute a Let's Encrypt certificate for FreeSWITCH SIP-TLS and secure WebSockets (WSS). It can also create a self-signed certificate when a publicly trusted certificate is not required.

This certificate is separate from the Nginx certificate used by the FS PBX web interface. To secure the web interface, see the [web-server Let's Encrypt guide](../10-web-server/lets-encrypt-certificate.md).

## Before you begin

Make sure that:

- FreeSWITCH 1.11.1 or later is installed.
- Public DNS for every requested hostname points to the correct FS PBX server.
- TCP port 80 is open from the internet to every server named on the certificate.
- Nginx serves `/.well-known/acme-challenge/` from the configured ACME webroot.
- You have an email address for the Let's Encrypt account.

Existing installations must upgrade FreeSWITCH separately from the normal FS PBX update:

```bash
cd /var/www/fspbx
sudo bash install/install_freeswitch.sh
```

After the upgrade finishes, restart the FreeSWITCH service:

```bash
sudo systemctl daemon-reload
sudo systemctl restart freeswitch
sudo fs_cli -x version
```

Installing the new FreeSWITCH files does not replace the process that is already running. Until the service is restarted, the server continues running the old FreeSWITCH version and `reloadcert` may remain unavailable. A FreeSWITCH service restart interrupts active calls, so plan a maintenance window. New FS PBX installations already install the supported version.

After this one-time upgrade and restart, certificate issuance and renewal use `reloadcert`, so future certificate updates do not require restarting FreeSWITCH or interrupting calls.

## Enable TLS on the SIP profiles

Issuing a certificate does not enable the FreeSWITCH TLS listeners by itself. Configure the SIP profiles before issuing the certificate:

1. Open **Advanced > Variables**.
2. Under **SIP Profile: Internal**, set `internal_ssl_enable` to `true`. This enables SIP-TLS on the internal profile, normally on port `5061`.
3. If the external SIP profile also needs TLS, set `external_ssl_enable` to `true` under **SIP Profile: External**. The external TLS port is normally `5081`.
4. Save the changes.

The standard internal SIP profile already includes these required settings:

- `tls` uses `$${internal_ssl_enable}`
- `tls-sip-port` is `5061`
- `tls-cert-dir` uses `$${internal_ssl_dir}`
- `wss-binding` is `:7443`

You can review them under **Advanced > SIP Profiles > Internal**. The external profile uses the corresponding external variables and normally listens for TLS on port `5081`.

After enabling TLS, go to **Status > SIP Status**:

1. Click **Flush Cache**.
2. Click **Reload XML**.
3. Restart each SIP profile whose TLS setting changed so that FreeSWITCH opens its TLS listener.

Restarting a SIP profile can briefly interrupt registrations on that profile, so make this one-time change during a suitable maintenance window. Certificate issuance and renewal on FreeSWITCH 1.11.1 or later use `reloadcert` and do not require another profile or service restart.

## Choosing the hostnames (SANs)

The **Hostnames (SANs)** field lists every hostname that phones, softphones, or WebRTC clients use to reach FreeSWITCH over TLS. Enter them comma- or space-separated.

The **first** hostname is the *primary*:

- It becomes the certificate's Common Name.
- In a multi-server installation it is the **failover or proxy record** that FS PBX uses to determine which server is currently active. Only the active server renews automatically.

**Single server:** enter the public hostname that phones register to.

```text
pbx.example.com
```

If clients reach the server by more than one hostname (not common), add each one:

```text
pbx.example.com, voip.example.com
```

**Multiple servers (cluster):** enter the failover or proxy hostname **first**, then each server's own direct hostname. FS PBX derives the peer servers from those additional hostnames — each direct hostname is a server it replicates the certificate to — so there is no separate peer list to maintain.

```text
pbx.example.com, fs1.example.com, fs2.example.com
```

Here `pbx.example.com` is the failover/proxy record clients register to, and `fs1.example.com` / `fs2.example.com` are the two servers' direct hostnames. The same list is entered once and used on both servers:

- On **fs1**, FS PBX replicates the certificate to `fs2.example.com` and skips itself.
- On **fs2**, it replicates to `fs1.example.com` and skips itself.
- Whichever server `pbx.example.com` currently points to is the one that renews.

If phones also register directly to the individual servers (dual registration), those direct names are already covered because they are on the certificate.

## Issue a staging certificate first

1. Open **Status > SIP Status**.
2. Find the **FreeSWITCH TLS Certificate** section.
3. Enter the **Hostnames (SANs)** as a comma- or space-separated list (see [Choosing the hostnames](#choosing-the-hostnames-sans)).
4. Enter the **ACME account email**.
5. Leave **ACME challenge webroot** at `/var/www/fspbx/public` unless Nginx uses a different document root.
6. Enable **Use staging (test) directory**.
7. Enable **Auto-renew** if FS PBX should renew the certificate automatically.
8. Click **Issue certificate**.

Clicking **Issue certificate** saves the current settings before starting issuance. A staging certificate is intentionally not trusted by phones or browsers; it confirms that DNS, port 80, Nginx, and the ACME challenge are working without using production rate limits.

After staging succeeds, turn off **Use staging (test) directory** and click **Issue certificate** again to request the publicly trusted production certificate.

## Multiple FS PBX servers

For a redundant or clustered installation:

- List the failover or proxy hostname first, followed by each server's direct hostname (see [Choosing the hostnames](#choosing-the-hostnames-sans)).
- Open TCP port 80 to every listed server. Let's Encrypt may validate any hostname from outside your network.
- Set a **Peer push secret** (use **Rotate** to generate and save one). It authorizes certificate replication between servers and is stored in FS PBX settings. If your database is replicated across nodes it applies to every server automatically; otherwise set the same value on each server.
- Issue from the server the failover record currently points to. It coordinates the HTTP-01 challenge and replicates the resulting certificate to the other servers. If replication to any server fails, issuance fails so the servers never serve mismatched certificates — fix the unreachable server and issue again.

## What FS PBX does

After validation succeeds, FS PBX:

- Installs the certificate and private key in `/etc/freeswitch/tls/all.pem`.
- Maintains the FreeSWITCH certificate symlinks in the same directory.
- Distributes the certificate to configured peer nodes when applicable.
- Runs the FreeSWITCH `reloadcert` command so new TLS connections use the certificate without restarting FreeSWITCH.

You can run the same reload manually when needed:

```bash
sudo fs_cli -x reloadcert
```

## Phone trust

A phone only registers over TLS if it trusts the FreeSWITCH certificate. To make this work for Polycom phones, FS PBX resolves the issuing certificate authority and writes it to the Polycom custom CA provisioning setting (`device.sec.TLS.customCaCert2`) on every issue, renewal, and revocation.

Phones pick up the CA the next time they fetch their configuration, so **re-provision (reboot or resync) the phones** after issuing a production certificate. Until they do, a phone may report `Untrusted Certificate` and fail to register over TLS.

Notes:

- **Staging certificates use an untrusted test CA**, so phones do not trust them even after re-provisioning. Use a production certificate for live phones.
- **Very old phone firmware** may not include the Let's Encrypt root and can still reject a production certificate after re-provisioning. Update the firmware in that case.
- Other vendors generally rely on the publicly trusted Let's Encrypt root already built into their firmware, so no custom CA is needed.

## Automatic renewal

When **Auto-renew** is enabled, FS PBX checks the certificate regularly and renews it when fewer than 30 days remain. Successful renewals are installed, distributed to peers, and reloaded automatically. FS PBX emails the ACME account address after every renewal — on success, and on failure with the days remaining and a note that it will retry.

In a multi-server installation, only the server the failover record currently points to performs the renewal, and it replicates the new certificate to the other servers. If replication fails, the renewal is treated as failed and retried on the next run, so the servers never diverge.

Keep public DNS and TCP port 80 available for every SAN. Renewal uses the same HTTP-01 validation as the original request, including the pre-flight reachability check on every hostname.

## Self-signed certificates and revocation

FS PBX uses a self-signed certificate automatically when a publicly trusted certificate is not present: at installation, before any Let's Encrypt certificate is issued, and as the replacement after a revocation. A self-signed certificate keeps SIP-TLS working, but phones and browsers report a trust warning unless its CA is trusted on those devices. For Polycom phones, FS PBX pushes the self-signed certificate to the custom CA setting so they can trust it after re-provisioning.

Use **Revoke certificate** when the current Let's Encrypt certificate must no longer be trusted. FS PBX revokes it through the issuing account, replaces it with a self-signed certificate, distributes the replacement when applicable, reloads FreeSWITCH, and updates the Polycom custom CA setting to match.

## Troubleshooting

If the pre-flight check cannot connect on port 80, run these commands on the affected server:

```bash
sudo nginx -t
sudo systemctl status nginx --no-pager
sudo ss -ltnp | grep ':80 '
```

Nginx must listen on `0.0.0.0:80` and, when IPv6 is used, `[::]:80`. A listener only on `127.0.0.1:80` cannot receive a Let's Encrypt validation request from the internet.

Test the challenge path from a system outside the FS PBX network:

```bash
curl -v http://pbx.example.com/.well-known/acme-challenge/test
```

A `404 Not Found` response proves that the public HTTP request reached the correct Nginx challenge location. A timeout or connection refusal normally indicates DNS, firewall, NAT, cloud security group, or listener configuration trouble.

If issuance succeeds but clients still see the previous certificate, check the FreeSWITCH version and reload certificates again:

```bash
sudo fs_cli -x version
sudo fs_cli -x reloadcert
```
