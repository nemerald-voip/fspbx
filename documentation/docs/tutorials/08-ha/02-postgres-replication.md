---
id: postgres-replication
title: PostgreSQL Logical Replication (Bi-Directional)
slug: /ha/postgres-replication
sidebar_position: 2
---

# Bi-Directional PostgreSQL Logical Replication


This script configures **logical replication** in both directions between two FS PBX nodes. It also bootstraps the remote schema and sets up publications/subscriptions.

> Works well when servers talk via **external or internal IPs**, and assumes **SSH keys are already exchanged both ways**.

The two nodes may run different PostgreSQL major versions. The script discovers
the online cluster containing the `fusionpbx` database independently on each
node, including its port and active configuration files.

## What It Sets

- Configures replication settings between two nodes
- Copies the primary's `APP_KEY` to the standby over SSH, preserving the standby's other `.env` settings
- **Firewall**: opens each discovered PostgreSQL port between peers
- **Schema bootstrap**: drops and recreates `public` on REMOTE (⚠️ destructive), copies schema from LOCAL
- Publications:
  - LOCAL → `fspbx_publication_a`
  - REMOTE → `fspbx_publication_b`
- Subscriptions:
  - REMOTE subscribes to LOCAL with `copy_data=true`
  - LOCAL subscribes to REMOTE with `copy_data=false`
  - both with `origin=none` (bi-di safe pattern)

## Application encryption key

Both servers must use the **same `APP_KEY`** so saved credentials and integration settings continue to work after failover.

During fresh standby setup, the replication script copies the primary's key and restarts the standby's services automatically. Other `.env` settings, including database passwords, remain unchanged. No manual key copying or service restart is needed.

## Run the Script on Primary node

```
sh /var/www/fspbx/install/setup_logical_replication.sh
```

Provide prompts:

* REMOTE IP, LOCAL IP

* db user passwords for local and remote. You can find these in your `.env` file.

Before changing either node, the script verifies that it can discover an online,
writable PostgreSQL cluster containing the `fusionpbx` database. Before the
destructive schema prompt, it also verifies the applied runtime settings and
database connectivity locally and in both network directions.

Application key checks also run before PostgreSQL configuration changes. The key is copied only after you accept the destructive standby setup prompt. A key validation, copy, or verification failure stops setup before the standby schema is dropped or the initial data copy begins.

The script uses the `ha:app-key` Artisan command on both servers. It passes the key through SSH standard input, without putting it in command-line arguments.

Safety Prompt (Destructive)
---------------------------

You'll be asked to confirm:

> "About to drop and recreate 'public' schema on REMOTE. This will delete ALL data in 'public' schema!"

The same confirmation also authorizes replacing the standby's `APP_KEY` with the primary's key and restarting all configured Supervisor processes on the standby. Retain the protected `.env.ha-backup-*` file until setup and failover checks are complete; restoring it later would make primary-encrypted data unreadable on the standby.

If this is a new standby or you explicitly prepared it for bootstrap---proceed with **Y**.

Verification
------------

- Confirm the script reports **Both nodes use the same application key** and **Supervisor services restarted on REMOTE with the shared APP_KEY**. Check service status with `supervisorctl status` and verify the configured encrypted features on each node before testing DNS failover. Do not paste keys or decrypted secrets into logs or support posts.

-   **Replication slots & subs**:

    ```
    SELECT subname, subenabled, subconninfo FROM pg_subscription;
    SELECT slot_name, active FROM pg_replication_slots;
    ```

-   **Sync state** on REMOTE after initial copy:

    ```
    SELECT bool_and(rel.srsubstate = 'r') AS all_tables_synced
    FROM pg_subscription_rel rel
    JOIN pg_subscription sub ON rel.srsubid = sub.oid
    WHERE sub.subname = 'fspbx_subscription_a_to_b';
    ```
-   Logs:

    -   `journalctl -u postgresql --no-pager`

    -   `tail -f /var/log/postgresql/postgresql-17-main.log` (path may vary)

Common Adjustments
------------------

-   **Password method**: If you require SCRAM-SHA-256, set `password_encryption = scram-sha-256` and change `pg_hba.conf` `md5` → `scram-sha-256` on both ends.

-   **Index bloat or vacuum**: Regular `VACUUM (ANALYZE)` recommended on active tables.

-   **Schema changes**: Make DDL changes on the **primary**, then verify they replicate cleanly before traffic cutover.


Rollback
--------

-   Drop subscriptions in reverse order:

    ```
    DROP SUBSCRIPTION IF EXISTS fspbx_subscription_b_to_a;
    DROP SUBSCRIPTION IF EXISTS fspbx_subscription_a_to_b;
    ```

-   Drop publications if retiring the link:

    ```
    DROP PUBLICATION IF EXISTS fspbx_publication_a;
    DROP PUBLICATION IF EXISTS fspbx_publication_b;
    ```

> Always snapshot (logical/physical backup) before major changes.
