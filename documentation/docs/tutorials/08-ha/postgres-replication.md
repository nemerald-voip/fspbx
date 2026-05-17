---
id: postgres-replication
title: PostgreSQL Logical Replication (Bi-Directional)
slug: /ha/postgres-replication
sidebar_position: 3
---

# Bi-Directional PostgreSQL Logical Replication


This script configures **logical replication** in both directions between two FS PBX nodes. It also bootstraps the remote schema and sets up publications/subscriptions.

> Works well when servers talk via **external or internal IPs**, and assumes **SSH keys are already exchanged both ways**.

## What It Sets

- Configures replication settings between two nodes
- **Firewall**: opens TCP 5432 between peers
- **Schema bootstrap**: drops and recreates `public` on REMOTE (⚠️ destructive), copies schema from LOCAL
- Publications:
  - LOCAL → `fspbx_publication_a`
  - REMOTE → `fspbx_publication_b`
- Subscriptions:
  - REMOTE subscribes to LOCAL with `copy_data=true`
  - LOCAL subscribes to REMOTE with `copy_data=false`
  - both with `origin=none` (bi-di safe pattern)

## Run the Script on Primary node

```
sh /var/www/fspbx/install/setup_logical_replication.sh
```

Provide prompts:

* REMOTE IP, LOCAL IP

* db user passwords for local and remote. You can find these in your `.env` file.

> By default the script targets **PostgreSQL 17** (`/etc/postgresql/17/main/`). Adjust `PG_VERSION` if needed.

Safety Prompt (Destructive)
---------------------------

You'll be asked to confirm:

> "About to drop and recreate 'public' schema on REMOTE. This will delete ALL data in 'public' schema!"

If this is a new standby or you explicitly prepared it for bootstrap---proceed with **Y**.

Verification
------------

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