---
id: backup-and-restore
title: Back up and restore FS PBX
slug: /backup-and-restore/
sidebar_position: 4.5
---

# Back up and restore FS PBX

Use a full FS PBX archive to recover a failed server or move a working PBX to a fresh installation. The same procedure works for a planned Debian 11 to Debian 13 move. Calls, registrations and pending Redis jobs are live state. Phones register again after cutover. The recovery point for saved data is the backup you choose.

## Prepare before a failure

1. In **Advanced → Default Settings**, find the **scheduled_jobs** category. Set **backup** to `true` **and enable the row**. A fresh installation ships the value `true` with the row disabled. Check **backup_time** (default `02:00`), **scheduled_jobs_timezone**, and **backup_path** (default `/var/backups/fspbx`). The root cron entry must run `php artisan schedule:run` every minute.
2. Create a full backup now and verify the next scheduled run produces another one. Run the following as root on the source. Use the archive path printed on success.

   ```bash
   cd /var/www/fspbx
   php artisan app:backup
   ```

3. Set **backup_keep_count** in **scheduled_jobs** to the number of dated full archives to keep locally. The default is **5**, including when the setting is missing. Each run creates the PostgreSQL dump first, includes it in `backup_YYYY-MM-DD.tgz`, then removes the separate dump after the archive is saved. Another successful run on the same date replaces that day's archive. Each new dated archive also retires the oldest leftover standalone dump, if one exists.

   Keep multiple dated copies **off the PBX**. Arrange an automated copy after successful completion and alert on missing, stale or failed copies. Protect the storage and transfer with encryption and restrict who can read or delete backups. The archive contains database credentials, private keys, messages and call audio.
4. Keep the installed FS PBX release, matching installer or clean install image, optional module versions and licenses, database extensions, storage mounts, DNS and carrier access in your recovery inventory. Keep the restore script with the recovery materials. Rehearse on an isolated replacement and record how long the copy, import and call tests take.

### What to keep

| Item | How to recover it |
| --- | --- |
| Accounts, users, extensions, gateways, call routing, CDRs, settings and native provisioning templates | The full archive includes a PostgreSQL dump. Restored users sign in with their existing credentials. |
| Voicemail, greetings, faxes, local recordings, custom sounds and hold music, application uploads and branding | The restore copies the standard data directories. The fresh install provides stock sounds and frontend assets. |
| Application settings and secrets | The full archive contains the original `/var/www/fspbx/.env`. The restored application uses its branding, URLs, session domains, encryption key, queue settings and all custom variables and secrets. Only the connection to the replacement's local PostgreSQL database comes from the destination. Back up files referenced by environment variables separately. |
| FreeSWITCH and legacy provisioning | The restore copies SIP TLS files, legacy provisioning templates and the application's custom FreeSWITCH scripts directory. The archive also carries the standard FreeSWITCH configuration for reviewing local edits. Install matching optional modules and binaries on the replacement. |
| Web TLS, firewall and host settings | The restore installs a current, matching web certificate and key saved as regular files in the standard Nginx SSL locations. Current full backups also include the standard Nginx site, Dehydrated/Certbot files, FusionPBX configuration and persistent iptables rules when present. Reapply site-specific settings on the replacement. Protect any certificates, key files or hooks stored elsewhere separately. |
| External data | Protect remote object storage, mounted media, custom upload or sound paths, symlink targets, externally supplied secrets and site-specific services separately. Verify a mounted filesystem is mounted at backup time. Record how to reconnect it on the replacement. |

### If you only have a database dump

If the source is still running, take a full backup. If only `fspbx_pgsql_YYYY-MM-DD.sql` survived, keep a copy and restore it into a separate empty database. The file is a PostgreSQL custom dump. Use `pg_restore --list FILE` to inspect its contents. Recover audio, uploads and the original `APP_KEY` from another copy or storage snapshot. If the key is lost, re-enter the affected encrypted credentials. [PostgreSQL restore reference](https://www.postgresql.org/docs/current/app-pgrestore.html)

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

From the same root shell on the replacement, apply the archive. For a rehearsal, production can stay online while the replacement is isolated from production networks and integrations.

```bash
python3 /var/www/fspbx/install/restore-fspbx.py --apply "$archive"
```

The script imports into a separate database first. After a successful import, it stops destination PBX services, switches databases, installs the media, and restores the application environment. It removes the fresh installer's database after the restore steps succeed. Use a fresh replacement and retain its snapshot for rollback.

On a standard installation the restore clears generated caches and rebuilds FreeSWITCH variables. If the script requests a manual FreeSWITCH sync, verify the switch and cache directories on the replacement, then run `sudo -u www-data php artisan freeswitch:prepare-restart` from `/var/www/fspbx`, or use the Variables sync and XML cache controls available in that release. PBX services stay stopped for host-specific setup.

1. **Web and SIP identity.** The restore brings back the original `APP_URL`, `SESSION_DOMAIN` and `SANCTUM_STATEFUL_DOMAINS`. Keep the production account names used by phones. Review host-specific Redis, FreeSWITCH event socket and integration settings before enabling services. Check the restore's web certificate message. If it requires a certificate, install the saved full chain and matching private key for the production names, or issue a new certificate. On a standard install Nginx reads `/etc/nginx/ssl/fullchain.pem` and `/etc/nginx/ssl/private/privkey.pem`. Configure renewal on the replacement and check SIP TLS separately. [Web certificate guide](/docs/configuration/web-server/lets-encrypt/)
2. **Host-specific files.** Reattach external storage, reconcile custom FreeSWITCH XML, load required modules, and reapply routing, firewall rules, provider allowlists and renewal hooks. Keep the destination's local database credentials, PHP, service definitions and installed application code. For reference, list saved files with `tar -tzf "$archive"`. Extract individual files to a root-only comparison directory. For example, from the recovery directory:

   ```bash
   install -d -m 0700 review
   tar -xzf "$archive" -C review -- var/www/fspbx/.env etc/freeswitch
   ```

   If the previous Nginx site used a Dehydrated certificate directly, recover its saved files before starting Nginx. Use the certificate directory name from the archived Nginx site and verify it covers the production hostname.

   ```bash
   cert_name=pbx.example.com
   tar -xzf "$archive" -C review -- "etc/dehydrated/certs/$cert_name"
   install -m 0644 "review/etc/dehydrated/certs/$cert_name/fullchain.pem" /etc/nginx/ssl/fullchain.pem
   install -m 0600 "review/etc/dehydrated/certs/$cert_name/privkey.pem" /etc/nginx/ssl/private/privkey.pem
   ```

3. **Start the web and voice services inside the isolated network.** These commands use the current installer's PHP-FPM unit.

   ```bash
   cd /var/www/fspbx
   sudo -u www-data php artisan config:clear
   nginx -t
   systemctl start php8.4-fpm nginx freeswitch
   fs_cli -x 'status'
   fs_cli -x 'sofia status'
   ```

4. Sign in with a restored administrator account. Verify accounts, extension counts, gateways, call routes and a saved encrypted integration. Check the FreeSWITCH hostname and any hostname-specific variables. In **Advanced → Variables → Network Setup**, set the replacement's local and advertised SIP/RTP addresses, sync and restart the affected profiles. Verify the web certificate using the production hostname over a local DNS override. [Network address guide](04-getting-started/02-network-settings.md)
5. When ready to accept traffic, direct the service IP or DNS, NAT, carrier routing and provisioning URLs to the replacement. Release the necessary network access. Start the remaining services and check the workers.

   ```bash
   systemctl start event_guard supervisor cron
   supervisorctl status
   ```

6. Register a test phone. Place inbound and outbound calls with two-way audio. Leave and retrieve a new voicemail, play an older message and recording, and check that a new CDR appears. Test provisioning and any queues, fax, messaging or encrypted provider integrations the site uses. Check the scheduled job server and any MAC-specific S3 upload setting. Confirm the next full backup reaches off-server storage.

## Repair the environment after an earlier restore

If the replacement still shows the installer's site name or temporary IP, use the updated restore script with the same full archive. Run it as root on the isolated replacement before returning it to service.

```bash
cd /root/pbx-recovery
python3 /var/www/fspbx/install/restore-fspbx.py --repair-env "$archive"
```

The repair saves a private copy of the current `.env` under `/root` before changing it, recovers the archived settings and clears the Laravel configuration cache. The seven destination connection fields are `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` and `DATABASE_URL`. The database records and media stay in place. Review any host-specific edits you made after the first restore. Reload PHP-FPM and restart any long-running application workers that were already running.

Older successful restores left the fresh installer database under the name `fspbx_before_restore`. After the repair and acceptance checks, you can remove that database on the replacement. This command prompts for confirmation. Use the destination cluster's port if it differs from the installation default.

```bash
sudo -u postgres dropdb --interactive --port=5432 fspbx_before_restore
```

## Rollback and rehearsal

If the restore fails, read the first reported error. Keep the replacement isolated. An import error leaves the fresh database in use. A failure after the switch can leave files or services partially changed and the installer database temporarily named `fspbx_before_restore`. Restore the clean destination snapshot, correct the problem, and rerun.

During a planned move, you can direct traffic back to the fenced source after taking the replacement offline. If the replacement has already accepted calls or changes, preserve its new data and reconcile the two recovery points before switching back. After a total loss, retry on a clean replacement using another verified backup. Keep the archive and snapshot until operators have accepted the recovered service.

Repeat the rehearsal after significant changes to versions, modules, storage paths or certificates. Use private test endpoints and keep outbound delivery blocked. Record the recovered backup timestamp, elapsed recovery time and the results of the call, voicemail and recording checks.

## Redundant installations

If one healthy peer survives, fence the failed node, verify the peer's application data and media, and direct service to the survivor. Rebuild the failed member as a fresh standby from that peer. Follow the replication, file sync and scheduled-job replacement procedures. [HA overview](08-ha/01-overview.md) · [Scheduled-job ownership and replacement](08-ha/04-scheduled-jobs-on-one-node.md)

If **all HA nodes have been lost or fenced**, recover one authoritative copy on an isolated fresh server first. Use `--fenced-ha-recovery` with `--apply` only in this situation. The restore omits PostgreSQL publications and subscriptions and retains the saved application ownership records. Keep cron, Supervisor and former nodes offline. Bring up the web interface for administration, rebuild replication and file sync to a fresh peer, and verify the data has converged. Approve replacement identities and follow the fenced-owner takeover procedure before enabling shared jobs. A returning node requires application-data verification and `php artisan scheduled-jobs:verify-rejoin` before its scheduler or workers restart. [Database replication](08-ha/02-postgres-replication.md) · [File sync](08-ha/03-syncthing.md) · [Scheduled-job recovery](08-ha/04-scheduled-jobs-on-one-node.md)
