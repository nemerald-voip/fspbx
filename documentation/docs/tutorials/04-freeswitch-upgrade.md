---
id: freeswitch-upgrade
title: Upgrade or reinstall FreeSWITCH
slug: /freeswitch-upgrade/
sidebar_position: 4
---

# Upgrade or reinstall FreeSWITCH

FS PBX application updates do not automatically upgrade FreeSWITCH. Follow this procedure when an FS PBX feature requires a newer FreeSWITCH version or when you need to reinstall the supported build.

## Before you begin

- Plan a maintenance window. Restarting FreeSWITCH interrupts active calls.
- Make sure the server has internet access to download packages and source code.
- Make sure FS PBX is on the version whose FreeSWITCH build you want to install. See [How to update FS PBX](03-updates.md).
- Back up any manual changes under `/etc/freeswitch`.

## 1. Check the running version

```bash
sudo fs_cli -x version
```

If you are upgrading for a feature that requires a minimum FreeSWITCH version, compare that requirement with the reported version. Stop here when the requirement is already met. Continue if an upgrade is required or if you need to reinstall the current supported build.

## 2. Build and install FreeSWITCH

```bash
cd /var/www/fspbx
sudo bash install/install_freeswitch.sh
```

The script builds and installs the FreeSWITCH version supported by the current FS PBX checkout. It replaces `/etc/freeswitch` with the configuration included with FS PBX and saves the previous directory as `/etc/freeswitch.orig`. An older `/etc/freeswitch.orig` backup is replaced.

The running FreeSWITCH process is not restarted automatically. It continues using the old binary until you restart the service in step 4.

## 3. Confirm restart preparation

Before it finishes, the script rebuilds `/etc/freeswitch/vars.xml` from the FS PBX database and flushes the generated FreeSWITCH XML cache. Confirm that the output includes:

```text
Rebuilt vars.xml from the FS PBX database.
Flushed the FreeSWITCH XML cache.
FreeSWITCH variables and XML cache prepared successfully.
```

:::warning Preparation warning

If the script reports that automatic restart preparation was incomplete, do not restart FreeSWITCH yet:

1. Open **Advanced > Variables** and click **Sync XML**.
2. Open **Status > SIP Status** and click **Flush Cache**.

After these two actions succeed, continue with the service restart below.

:::

## 4. Restart FreeSWITCH

```bash
sudo systemctl daemon-reload
sudo systemctl restart freeswitch
```

This restart activates the newly installed binary and interrupts active calls.

## 5. Verify the installation

```bash
sudo systemctl status freeswitch --no-pager
sudo fs_cli -x version
```

Confirm that the service is running and that `fs_cli` reports the expected version. Also confirm that the expected SIP profiles are running under **Status > SIP Status**.

When restart preparation completed successfully, you do not need to reload XML, rescan the SIP profiles, or restart each profile separately.

If FreeSWITCH does not start, review the service log:

```bash
sudo journalctl -u freeswitch -n 100 --no-pager
```
