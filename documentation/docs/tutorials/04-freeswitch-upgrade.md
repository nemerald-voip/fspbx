---
id: freeswitch-upgrade
title: Upgrade FreeSWITCH
slug: /freeswitch-upgrade/
sidebar_position: 4
---

# Upgrade FreeSWITCH

**This update installs FreeSWITCH 1.11.3.**

FreeSWITCH is updated separately from FS PBX. You can upgrade directly from FreeSWITCH 1.10.12 or 1.11.1 using the steps below.

## Before you begin

- [Update FS PBX](03-updates.md) first.
- Use Debian 12 or 13, with internet access and sufficient free disk space for the update and backups.
- Schedule the final restart for a maintenance window. It interrupts active calls.
- Keep a full server backup or snapshot in case you need to restore the previous version.
- Avoid changing FreeSWITCH settings while the update runs.

## 1. Check the running version

```bash
sudo fs_cli -x version
```

## 2. Run the update script

```bash
cd /var/www/fspbx
sudo bash install/install_freeswitch.sh
```

The script automatically backs up and preserves the **entire `/etc/freeswitch` directory**, including custom settings and certificates stored there. It prints the backup location under `/var/backups/fspbx-freeswitch/`.

Your existing database settings and Variables are preserved. No manual reconfiguration is needed.

FreeSWITCH keeps running until you restart it manually. Avoid loading or reloading modules before that restart.

Wait for `FreeSWITCH installation complete.` before proceeding. If the script fails or is interrupted, resolve the error before restarting. A failed installation may require rerunning the script or restoring your server snapshot.

## 3. Restart when ready

```bash
sudo systemctl restart freeswitch
```

This activates the update and interrupts active calls. No additional configuration reload is required.

## 4. Verify

```bash
sudo systemctl status freeswitch --no-pager
sudo fs_cli -x version
sudo fs_cli -x 'sofia status'
```

Confirm that FreeSWITCH reports **1.11.3** and the expected SIP profiles are running. Test phone registration, inbound and outbound calls, audio, and any TLS, fax, or queue features used on this server.

If startup fails:

```bash
sudo journalctl -u freeswitch -n 100 --no-pager
```
