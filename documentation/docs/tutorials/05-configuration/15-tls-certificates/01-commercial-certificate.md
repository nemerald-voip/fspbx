---
id: commercial-certificate
title: Commercial certificate
slug: /configuration/fspbx-tls-setup
sidebar_position: 1
---

# Using a commercial certificate with FreeSWITCH

This guide installs a certificate purchased from a commercial certificate authority (CA) for FreeSWITCH SIP-TLS and secure WebSockets (WSS). It does not replace the certificate used by Nginx for the FS PBX web interface.

## Before you begin

You need:

- The private key, such as `pbx.example.com.key`.
- The server certificate, such as `pbx.example.com.crt`.
- The CA intermediate bundle, unless it is already included in a full-chain file.
- A certificate whose Subject Alternative Names (SANs) include every hostname that phones and softphones use to reach FreeSWITCH.
- Root or sudo access to the FS PBX server.

:::important FreeSWITCH version

FreeSWITCH 1.11.1 or later can load a renewed certificate without interrupting calls. Existing FS PBX installations must upgrade FreeSWITCH separately from the normal FS PBX update:

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

:::

## 1. Prepare the certificate files

Temporarily upload the certificate, intermediate bundle, and private key to a secure directory on the server. Protect the private key while it is there:

```bash
sudo chown root:root pbx.example.com.key
sudo chmod 600 pbx.example.com.key
```

Confirm that the certificate has the expected names and validity dates:

```bash
openssl x509 -in pbx.example.com.crt -noout -subject -issuer -dates -ext subjectAltName
openssl pkey -in pbx.example.com.key -check -noout
```

## 2. Back up the current FreeSWITCH certificate

```bash
sudo cp -a /etc/freeswitch/tls "/etc/freeswitch/tls.backup-$(date +%Y%m%d-%H%M%S)"
sudo install -d -m 0750 -o www-data -g www-data /etc/freeswitch/tls
```

## 3. Build the FreeSWITCH certificate bundle

FreeSWITCH uses one `all.pem` file containing the server certificate, intermediate certificates, and private key—in that order.

If your CA supplied the server certificate and intermediate bundle separately:

```bash
sudo sh -c 'cat pbx.example.com.crt pbx.example.com.bundle.crt pbx.example.com.key > /etc/freeswitch/tls/all.pem'
```

If your CA supplied a full-chain file:

```bash
sudo sh -c 'cat fullchain.pem pbx.example.com.key > /etc/freeswitch/tls/all.pem'
```

Create the filenames used by FreeSWITCH services:

```bash
sudo ln -sfn all.pem /etc/freeswitch/tls/agent.pem
sudo ln -sfn all.pem /etc/freeswitch/tls/tls.pem
sudo ln -sfn all.pem /etc/freeswitch/tls/wss.pem
sudo ln -sfn all.pem /etc/freeswitch/tls/dtls-srtp.pem
sudo chown www-data:www-data /etc/freeswitch/tls/all.pem
sudo chmod 0640 /etc/freeswitch/tls/all.pem
```

Delete the temporary uploaded private key after you have confirmed that the installed certificate works.

## 4. Enable TLS in FS PBX

Installing a certificate does not enable the FreeSWITCH TLS listeners by itself.

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

Restarting a SIP profile can briefly interrupt registrations on that profile, so make this one-time change during a suitable maintenance window. Future certificate renewals on FreeSWITCH 1.11.1 or later only require `reloadcert`; they do not require another profile or service restart.

## 5. Load the certificate

Check the installed FreeSWITCH version:

```bash
sudo fs_cli -x version
```

For FreeSWITCH 1.11.1 or later, reload the certificate without restarting FreeSWITCH:

```bash
sudo fs_cli -x reloadcert
```

The reload applies to new TLS connections. Existing calls and connections are not restarted.

For a FreeSWITCH version older than 1.11.1, `reloadcert` is not available. A service restart is required and can interrupt active calls:

```bash
sudo systemctl restart freeswitch
```

## 6. Verify the certificate

Review the installed certificate:

```bash
sudo openssl x509 -in /etc/freeswitch/tls/all.pem -noout -subject -issuer -dates -ext subjectAltName
```

Test the SIP-TLS listener from another system, replacing the hostname if necessary:

```bash
openssl s_client -connect pbx.example.com:5061 -servername pbx.example.com -showcerts
```

Also check **Status > SIP Status** and confirm that the certificate status, issuer, expiration date, and file checks are correct.

Configure phones and softphones to verify the server certificate. The hostname configured on each device must match a SAN on the certificate, and the device must trust the issuing CA.
