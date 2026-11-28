---
id: backup-and-restore
title: Back up and restore FS PBX
slug: /backup-and-restore/
sidebar_position: 4.5
---

# Back up and restore FS PBX

Use a full FS PBX backup to recover a failed server or move to a fresh installation, including a move from Debian 11 to Debian 13. The replacement recovers the accounts, settings and files saved at the time of the backup. Active calls and pending Redis jobs cannot be recovered. Phones register again when you switch traffic to the replacement.

If you already restored a server and it still shows the installer's site name or temporary IP address, you can [repair its application settings](#repair-the-environment-after-an-earlier-restore) using the same archive.

## Prepare before a failure

1. In **Advanced → Default Settings**, find the **scheduled_jobs** category. Set **backup** to `true` **and enable the row**. A fresh installation ships the value `true` with the row disabled. Check **backup_time** (default `02:00`), **scheduled_jobs_timezone**, and **backup_path** (default `/var/backups/fspbx`). The root cron entry must run `php artisan schedule:run` every minute.
2. Create a full backup now and verify the next scheduled run produces another one. Run the following as root on the source. Use the archive path printed on success.

   ```bash
   cd /var/www/fspbx
   php artisan app:backup
   ```

3. Choose how many daily backups to keep on the PBX. The default is **5**. To change it, enable **backup_keep_count** in **scheduled_jobs** and enter a whole number of at least 1. Copy the full `backup_YYYY-MM-DD.tgz` archive off the server. It includes the database dump. A second successful backup on the same date replaces that day's archive. FS PBX removes the separate dump once it saves the archive. Each new backup date also removes one older standalone dump, starting with the oldest.

   Keep multiple dated copies **off the PBX**. Arrange an automated copy after successful completion and alert on missing, stale or failed copies. Protect the storage and transfer with encryption and restrict who can read or delete backups. The archive contains database credentials, private keys, messages and call audio.
4. Keep the installed FS PBX release, matching installer or clean install image, optional module versions and licenses, database extensions, storage mounts, DNS and carrier access in your recovery inventory. Keep the restore script with the recovery materials. Rehearse on an isolated replacement and record how long the copy, import and call tests take.

### What to keep

| Item | How to recover it |
| --- | --- |
| Accounts, users, extensions, gateways, call routing, CDRs, settings and native provisioning templates | The full archive includes a PostgreSQL dump. Restored users sign in with their existing credentials. |
| Voicemail, greetings, faxes, local recordings, custom sounds and hold music, application uploads and branding | The restore copies the standard data directories. The fresh install provides stock sounds and frontend assets. |
| Application settings and secrets | FS PBX restores your original `.env`, including the site name, web address, sign-in domains, encryption key, queue settings and custom integration credentials. It keeps the replacement's local PostgreSQL connection settings. Back up any separate files referenced by `.env`. |
| FreeSWITCH and legacy provisioning | The restore copies SIP TLS files, legacy provisioning templates and the application's custom FreeSWITCH scripts directory. The archive also carries the standard FreeSWITCH configuration for reviewing local edits. Install matching optional modules and binaries on the replacement. |
| Web TLS, firewall and host settings | The restore installs a current, matching web certificate and key saved as regular files in the standard Nginx SSL locations. Current full backups also include the standard Nginx site, Dehydrated/Certbot files, FusionPBX configuration and persistent iptables rules when present. Reapply site-specific settings on the replacement. Protect any certificates, key files or hooks stored elsewhere separately. |
| External data | Protect remote object storage, mounted media, custom upload or sound paths, symlink targets, externally supplied secrets and site-specific services separately. Verify a mounted filesystem is mounted at backup time. Record how to reconnect it on the replacement. |

### If you only have a database dump

If the source is still running, take a full backup. If you only have a database dump such as `fspbx_pgsql_YYYY-MM-DD.sql`, keep a copy and restore it into a separate empty database. This file is a PostgreSQL custom dump. Use `pg_restore --list FILE` to inspect its contents. Recover audio, uploads and the original `APP_KEY` from another copy or storage snapshot. If the key is lost, re-enter the affected encrypted credentials. [PostgreSQL restore reference](https://www.postgresql.org/docs/current/app-pgrestore.html)

## Choose the recovery point

**After a failure**, fence the failed server so it cannot return and register trunks or run jobs unexpectedly. Select the newest verified off-server copy taken before the loss or corruption. If it fails verification, keep it for investigation and try the preceding copy. Record the backup time so operators know which calls, messages and changes may be missing.

**For a planned move**, rehearse with an earlier archive. At cutover, divert inbound traffic, drain calls and queued work, and pause writes from phones, web users, integrations, the scheduler and workers. On a dedicated PBX, stop cron, Nginx, Supervisor, event_guard, FreeSWITCH and the installed PHP-FPM service after draining. Check for other scheduled commands or optional services still writing. Leave PostgreSQL and local Redis available. Run `php artisan app:backup` once more, copy that final archive and keep the source fenced. A live nightly backup can capture the database and files at slightly different times.

**If you have a redundant pair**, use the [HA recovery steps below](#redundant-installations) before choosing a source of data.

## Prepare the replacement

1. Complete a fresh FS PBX installation on a supported Debian release. An existing fresh Debian 13 installation satisfies this step. Match the FS PBX release and optional modules to the backup. The current installer selects the latest published release. If the backup is older, use a matching clean install image or provision the matching release before restoring. Use a PostgreSQL target and client compatible with the source dump, and install any additional PostgreSQL extensions or roles used at the site. Apply application upgrades after verifying recovery. [Installation guide](02-installation.md)
2. Keep the replacement isolated from production phones, carriers, mail, webhooks, scheduled work and any storage synchronization peers. Use a dedicated local database and cache. Record any cache, event socket or integration endpoints that need to change when the archived configuration is installed. Reserve disk for the archive, uncompressed staging files, installed media and the restored database.
3. Take a snapshot of the fresh destination, including its database and data volumes. Install `rsync` if needed. Use the restore script from this release or your recovery materials. For an installation that predates the script, copy the trusted `install/restore-fspbx.py` to the replacement.
4. Transfer a trusted full archive and verify its checksum against the copy recorded off-server. The example uses root shells and a protected SSH destination. Substitute your archive name, backup location and replacement host.

   Run these commands on the source or backup repository.

   ```bash
   cd /var/backups/fspbx
   archive=backup_YYYY-MM-DD.tgz
   sha256sum "$archive" > "$archive.sha256"
   ssh root@replacement 'install -d -m 0700 /root/pbx-recovery'
   scp "$archive" "$archive.sha256" root@replacement:/root/pbx-recovery/
   ```

   Verify the copy on the replacement.

   ```bash
   cd /root/pbx-recovery
   archive=backup_YYYY-MM-DD.tgz
   sha256sum -c "$archive.sha256"
   python3 /var/www/fspbx/install/restore-fspbx.py --check "$archive"
   ```

   The check reads the archive, validates the database inventory, application key and matching release, and removes its temporary files. Use `--work-dir /path/to/private-disk` on both commands if `/var/tmp` has insufficient space. A full test import and a test call establish whether the recovered PBX can serve traffic.

## Restore on the replacement

**Isolate the replacement before you run the restore.** FS PBX starts calling services, scheduled jobs and background workers automatically. During a rehearsal, block access to production phones, trunks, shared storage, mail and webhooks so the test server cannot place calls or deliver queued messages.

Apply the archive from the same root shell you used to check it.

```bash
python3 /var/www/fspbx/install/restore-fspbx.py --apply "$archive"
```

FS PBX imports the backup into a separate database first. It then stops the replacement's PBX services, switches to the recovered database and restores your media and settings. Once those steps succeed, it removes the temporary installer database. It starts each installed PHP-FPM service first, followed by Nginx, FreeSWITCH, event_guard, Supervisor and cron. Previously stopped or disabled services start too. Services masked in systemd stay off.

### Check the services before connecting phones

Use the service names printed by the restore. This example uses PHP 8.4. Substitute the PHP-FPM version installed on your replacement and omit any services the restore skipped.

```bash
nginx -t
systemctl is-active php8.4-fpm nginx freeswitch event_guard supervisor cron
supervisorctl status
fs_cli -x 'status'
fs_cli -x 'sofia status'
```

Look for `active` for each service, `RUNNING` for the workers you expect, and your SIP profiles in the FreeSWITCH output. If you used an older restore script that left services stopped, start the installed services on the isolated replacement, then repeat these checks.

```bash
systemctl start php8.4-fpm nginx freeswitch event_guard supervisor cron
```

On a standard installation FS PBX clears generated caches and rebuilds FreeSWITCH variables before startup. **If the restore requests a manual sync**, verify the FreeSWITCH configuration and cache directories on the replacement, then run the following before accepting calls. You can also sync Variables and clear the XML cache using the controls in your installed release.

```bash
cd /var/www/fspbx
sudo -u www-data php artisan freeswitch:prepare-restart && systemctl restart freeswitch
```

### Check your site and switch traffic

1. **Check the hostname and certificates.** The restore brings back your web address and sign-in domains (`APP_URL`, `SESSION_DOMAIN` and `SANCTUM_STATEFUL_DOMAINS`). Keep the account domain names your phones use. Check the Redis address, FreeSWITCH event socket settings and external integration endpoints on the replacement.

   If the restore asks for a web certificate, install the saved certificate chain and matching private key for your production hostname, or issue a new certificate. On a standard installation, Nginx reads `/etc/nginx/ssl/fullchain.pem` and `/etc/nginx/ssl/private/privkey.pem`. Set up renewal and check the SIP TLS certificate for phones separately. [Web certificate guide](/docs/configuration/web-server/lets-encrypt/)
2. **Restore site-specific files.** Reattach external storage, review custom FreeSWITCH configuration, load required modules, and reapply routing, firewall rules, provider allowlists and certificate renewal hooks. Keep the replacement's local database credentials and installed software. To compare saved settings, list the files with `tar -tzf "$archive"`. This example extracts the application and FreeSWITCH settings to a private directory.

   ```bash
   cd /root/pbx-recovery
   install -d -m 0700 review
   tar -xzf "$archive" -C review -- var/www/fspbx/.env etc/freeswitch
   ```

   If the previous Nginx site used a Dehydrated certificate directly, recover its saved files and reload Nginx after checking its configuration. Use the certificate directory name from the archived Nginx site and verify it covers the production hostname.

   ```bash
   cert_name=pbx.example.com
   tar -xzf "$archive" -C review -- "etc/dehydrated/certs/$cert_name"
   install -m 0644 "review/etc/dehydrated/certs/$cert_name/fullchain.pem" /etc/nginx/ssl/fullchain.pem
   install -m 0600 "review/etc/dehydrated/certs/$cert_name/privkey.pem" /etc/nginx/ssl/private/privkey.pem
   nginx -t && systemctl reload nginx
   ```

3. **Sign in and check routing.** Use a restored administrator account to check accounts, extensions, gateways, call routes and a saved encrypted integration. Check the FreeSWITCH hostname and any settings tied to it. In **Advanced → Variables → Network Setup**, set the replacement's local and advertised SIP/RTP addresses, sync and restart the affected profiles. Verify the web certificate using the production hostname over a local DNS override. [Network address guide](04-getting-started/02-network-settings.md)
4. **Switch traffic.** When ready, point the service IP or DNS, NAT, carrier routing and provisioning URLs at the replacement. Allow the necessary network access.

5. **Test a phone.** Register a phone and make inbound and outbound calls with two-way audio. Leave and retrieve a new voicemail, play an older message and recording, and check that the new call appears in the call detail records. Test provisioning and any queues, fax, messaging or provider integrations you use. Check which server runs scheduled jobs and any S3 upload setting tied to the old server's MAC address. Confirm the next full backup reaches off-server storage.

## Repair the environment after an earlier restore

If an older restore left the installer's site name, temporary web address or sign-in settings in place, use the updated script to recover your original settings. Run it as root on the isolated replacement with the **same full archive**. Replace the example filename with yours.

```bash
cd /root/pbx-recovery
archive=backup_YYYY-MM-DD.tgz
python3 /var/www/fspbx/install/restore-fspbx.py --repair-env "$archive"
```

The repair keeps your database records, media and local PostgreSQL connection. Before it changes `/var/www/fspbx/.env`, it saves a private copy under `/root` and prints the filename. It restores the archived settings and clears the application configuration cache. Compare the saved copy for any changes you made after the first restore, such as a new Redis address. Reload PHP-FPM and restart any running application workers so they pick up the settings. Then check the site name and sign in at the restored web address.

The settings that keep the replacement's values are `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` and `DATABASE_URL`.

Older successful restores left the fresh installer database under the name `fspbx_before_restore`. After the repair and acceptance checks, you can remove that database on the replacement. This command prompts for confirmation. Use the destination cluster's port if it differs from the installation default.

```bash
sudo -u postgres dropdb --interactive --port=5432 fspbx_before_restore
```

## Rollback and rehearsal

**If the script reports that data restore completed but a service failed to start**, your database and files are in place. FS PBX attempted to start the remaining services and listed each one that failed. Read the log for each service it named. For example, this command shows the Nginx log for the current boot.

```bash
journalctl -u nginx.service -b
```

Fix the cause, then run the `systemctl start` command printed by the restore. It retries just the affected services. Repeat the [service checks](#check-the-services-before-connecting-phones) before directing traffic to the replacement.

For an earlier failure, read the first reported error and keep the replacement isolated. An import error leaves the fresh database in use. A failure during the database switch or file restore can leave files or services partially changed and the installer database temporarily named `fspbx_before_restore`. Restore the clean destination snapshot, correct the problem, and rerun.

During a planned move, you can direct traffic back to the fenced source after taking the replacement offline. If the replacement has already accepted calls or changes, preserve its new data and reconcile the two recovery points before switching back. After a total loss, retry on a clean replacement using another verified backup. Keep the archive and snapshot until operators have accepted the recovered service.

Repeat the rehearsal after significant changes to versions, modules, storage paths or certificates. Use private test endpoints and keep outbound delivery blocked. Record the recovered backup timestamp, elapsed recovery time and the results of the call, voicemail and recording checks.

## Redundant installations

If one healthy peer survives, fence the failed node, verify the peer's application data and media, and direct service to the survivor. Rebuild the failed member as a fresh standby from that peer. Follow the replication, file sync and scheduled-job replacement procedures. [HA overview](08-ha/01-overview.md) · [Scheduled-job ownership and replacement](08-ha/04-scheduled-jobs-on-one-node.md)

If **all HA nodes have been lost or fenced**, restore one verified archive on a fresh, isolated replacement. Keep every former node powered off or disconnected. In the restore step, use the following command on the replacement. Cron and Supervisor start automatically here too.

```bash
python3 /var/www/fspbx/install/restore-fspbx.py --apply --fenced-ha-recovery "$archive"
```

The saved scheduled-job ownership settings come back with the archive. Rebuild database replication and file sync to a fresh peer, verify that the data matches, then approve the replacement nodes and complete the fenced-owner takeover before opening production traffic and integrations. Before restarting the scheduler or workers on a returning node, verify its application data and run `php artisan scheduled-jobs:verify-rejoin`. [Database replication](08-ha/02-postgres-replication.md) · [File sync](08-ha/03-syncthing.md) · [Scheduled-job recovery](08-ha/04-scheduled-jobs-on-one-node.md)
