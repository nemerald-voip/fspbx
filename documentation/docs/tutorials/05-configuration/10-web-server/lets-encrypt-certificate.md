---
id: lets-encrypt
title: Let's Encrypt SSL certificate
slug: /configuration/web-server/lets-encrypt/
sidebar_position: 1
---

# Let's Encrypt SSL certificate

FS PBX can issue, install, and renew a Let's Encrypt certificate for the Nginx web interface. The installer supports a single server and a two-server primary/standby deployment.

This certificate is separate from the certificate used by FreeSWITCH for SIP-TLS and secure WebSockets. See [Using a Let's Encrypt certificate with FreeSWITCH](/docs/configuration/tls-certificates/lets-encrypt/) for that certificate.

## Prerequisites

Before you begin, ensure:

- FS PBX is installed and Nginx is running.
- Every requested hostname has a public DNS record pointing to the server that answers for it.
- TCP ports 80 and 443 are publicly accessible.
- The command is run with `sudo` or as `root`.
- For redundant deployments, passwordless root SSH works in both directions between the direct node hostnames.

The installer uses an HTTP-01 challenge. It does not create or change DNS records.

## Run the installer

```bash
cd /var/www/fspbx
sudo php artisan app:install-lets-encrypt-certificate
```

The command requires an explicit deployment choice:

```text
Select how this Nginx certificate will be deployed:

  [1] Single server
      Issue, install, and renew the certificate only on this server.

  [2] Redundant server pair
      Use one certificate on two primary/standby FS PBX servers. ACME
      challenges and certificates are synchronized through passwordless
      root SSH so the floating hostname remains trusted after failover.

Select 1 or 2:
```

## Single server

Choose **1** and enter one or more hostnames separated by commas or spaces:

```text
portal.example.com, admin.example.com
```

Every name is included in one certificate as a Subject Alternative Name (SAN). All names must resolve to this server and reach its HTTP-01 challenge path on port 80.

The installer configures only the local server. It does not request SSH details or contact another node.

## Redundant server pair

Use three public DNS records:

```text
portal.example.com  A  203.0.113.10  # Floating name; points to the active node
pbx1.example.com    A  203.0.113.10  # Node 1 direct name
pbx2.example.com    A  203.0.113.20  # Node 2 direct name
```

Use direct, unproxied A/AAAA records. The renewal guard compares the authoritative address for the floating hostname with the direct hostname of each node. A proxied record or a floating CNAME does not provide the address needed for that decision.

Run the installer on the node that the floating hostname currently points to and choose **2**. The command asks for:

```text
Floating hostname: portal.example.com
This server hostname: pbx1.example.com
Peer server hostname: pbx2.example.com
Peer SSH target: root@pbx2.example.com
SSH port: 22
```

Before making changes, FS PBX displays the three certificate names, local server, peer server, and renewal owner for confirmation.

### Configure passwordless root SSH

The certificate hooks must connect in both directions. From node 1, verify access to node 2:

```bash
ssh -o BatchMode=yes -p 22 root@pbx2.example.com true
```

Then log in to node 2 and install its root public key on node 1:

```bash
sudo -i
test -s /root/.ssh/id_ed25519.pub || ssh-keygen -t ed25519
ssh-copy-id -p 22 root@pbx1.example.com
ssh -o BatchMode=yes -p 22 root@pbx1.example.com true
```

`ssh-copy-id` may ask for node 1's root password. If password-based root login is disabled, copy the `/root/.ssh/id_ed25519.pub` line through your server console or another trusted administrative channel and append it to `/root/.ssh/authorized_keys` on node 1. Keep `/root/.ssh` mode `0700` and `authorized_keys` mode `0600`.

Do not continue until both `BatchMode=yes` checks succeed without prompting for a password.

The installer then:

1. Verifies passwordless root SSH from node 1 to node 2 and from node 2 back to node 1.
2. Configures Dehydrated and the renewal command on both nodes.
3. Places a test HTTP-01 token on both nodes and retrieves it through all three public hostnames.
4. Requests one certificate containing the floating hostname and both direct node hostnames.
5. Installs the same certificate at `/etc/nginx/ssl/fullchain.pem` and private key at `/etc/nginx/ssl/private/privkey.pem` on both nodes.
6. Tests and reloads Nginx on both nodes.

Do not run separate certificate requests such as `portal.example.com, pbx1.example.com` on one server and `portal.example.com, pbx2.example.com` on the other. The floating hostname reaches only the active server during HTTP-01 validation. FS PBX solves this by publishing every challenge token to both servers and sharing one certificate.

### Automatic renewal and failover

Both nodes receive the renewal cron entry, but only the active node proceeds. FS PBX queries the authoritative DNS servers for the floating hostname:

- If it points to this node, this node runs Dehydrated and synchronizes the certificate to its peer.
- If it points to the peer, this standby node skips renewal.
- If authoritative DNS cannot be confirmed, its nameservers disagree, or the floating record contains addresses for both nodes or an unknown server, renewal fails closed.

After a DNS failover, renewal ownership follows the floating hostname automatically. An unchanged certificate is still checked and synchronized, allowing a temporary peer-copy failure to repair itself without requesting another certificate.

All SAN hostnames must remain publicly reachable during renewal. If a node is offline when renewal is due, restore it or temporarily issue a certificate without that node's direct hostname.

## Verify the certificate

Open every hostname included in the certificate. For a redundant installation:

```text
https://portal.example.com
https://pbx1.example.com
https://pbx2.example.com
```

Check the installed certificate and renewal cron:

```bash
sudo openssl x509 -in /etc/nginx/ssl/fullchain.pem -noout -subject -ext subjectAltName -dates
sudo crontab -l
```

The renewal line calls:

```text
0 3 * * * cd /var/www/fspbx && php artisan app:renew-nginx-certificate
```

You can run the same check manually:

```bash
sudo php artisan app:renew-nginx-certificate
```

## Application URL and sessions

Use the normal user-facing hostname for `APP_URL`. If users access FS PBX through multiple subdomains of the same parent domain, use a wildcard `SESSION_DOMAIN` and list every authenticated hostname in `SANCTUM_STATEFUL_DOMAINS`:

```dotenv
APP_URL=https://portal.example.com
SESSION_DOMAIN=.example.com
SANCTUM_STATEFUL_DOMAINS=portal.example.com,pbx1.example.com,pbx2.example.com
```

Refresh Laravel's configuration cache after changing `.env`:

```bash
php artisan config:cache
```

## Troubleshooting

Confirm Nginx and the HTTP-01 listener:

```bash
sudo nginx -t
sudo ss -ltnp | grep ':80 '
```

For a redundant installation, confirm passwordless SSH in both directions:

```bash
ssh -o BatchMode=yes root@pbx2.example.com true
ssh root@pbx2.example.com ssh -o BatchMode=yes root@pbx1.example.com true
```

Review Nginx and system logs when issuance or reload fails:

```bash
sudo journalctl -u nginx --no-pager
```
