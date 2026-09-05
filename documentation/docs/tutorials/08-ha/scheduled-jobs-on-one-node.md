---
id: ha-scheduled-jobs
title: Running Scheduled Jobs on One Node
slug: /ha/scheduled-jobs
sidebar_position: 4
---

# Running Scheduled Jobs on One Node

Some FS PBX background jobs must have **at most one authorized database writer**, even though both servers in a redundant pair run the scheduler. This is not an exactly-once delivery guarantee: jobs can fail, pause, or retry.

This guide explains how FS PBX decides which server is allowed to run those jobs, and what you need to configure after setting up a redundant pair.

## Why this matters

In a redundant deployment both nodes run the same code, the same scheduler, and their own local queue. Nothing stops both of them from starting the same job at the same minute.

For a job that only reads data, running twice is wasteful but harmless. For a job that **writes** data, running twice on a bi-directionally replicated database causes two problems:

* the same work is performed twice, for example directory users created, updated, or disabled on both sides
* both servers write the same rows locally, and those conflicting rows can **stall logical replication** when they reach the other node

A stalled subscription has to be repaired by hand, and it is a much bigger problem than the job simply not running.

Because of that, FS PBX takes the cautious side of the trade:

> A job runs only when the server can prove it is the active one. If that cannot be determined, the job is skipped and the reason is recorded.

Unclaimed work remains due and can be picked up on the next scheduler pass. Interrupted work may need a retry.

## How FS PBX identifies each node

FS PBX reads the PostgreSQL cluster system identifier from `pg_control_system()`. That identifier is created with the PostgreSQL cluster and remains stable across hostname, address, and replication subscription changes.

Replication subscription names are never used as node identities. FS PBX reads every host in `pg_subscription.subconninfo` only as a possible address to check. Disabled, manually named, and stale subscriptions are expected.

Before an address can become a scheduled-job node, FS PBX sends it a signed identity challenge over HTTPS. The response includes the PostgreSQL system identifier, an application-specific hash of `/etc/machine-id`, hostname, application version, request nonce, endpoint identity, and a secret-free coordination snapshot. The raw machine ID is never returned. A super administrator must approve the verified result.

Discovery never approves a node. An unreachable subscription host remains visible as a stale candidate. Multiple addresses returning the same database and host identity are aliases; one approved canonical endpoint is stored. The same database identity with different host fingerprints blocks approval, and a host that does not match its approved fingerprint cannot claim or commit work.

A full disk clone must receive a new machine ID before starting workers. Software cannot distinguish two hosts whose database identity and machine ID were both copied unchanged.

## Durable ownership

The shared `scheduled_jobs.active_node` setting stores the approved PostgreSQL system identifier of the current owner. `scheduled_jobs.active_node_generation` is incremented whenever ownership changes. Together they let a worker reject stale queued work after a handoff.

These settings remain editable under **Advanced > Default Settings**, using the normal edit, toggle, copy, and delete actions. Use the **Scheduled job server** control for a coordinated ownership transfer: direct setting edits do not drain running jobs or perform the signed handoff. A direct edit on a replica can create split ownership; it is an administrator override outside the handoff safety guarantee. Stop/fence workers first. Duplicate rows, disabled owner settings, and invalid generations fail closed rather than silently choosing a row.

An older hostname or IP value is not active automatically. When it matches exactly one approved node, the control shows a migration hint. Confirm it on that owner after a signed probe. Converting the representation preserves the generation; an actual transfer drains outstanding work, including older-generation claims. An unknown non-empty value is never treated as first-time setup.

The separate `scheduled_jobs.coordination_secret` authenticates peer requests and responses. It is not the certificate deployment secret. Rotate it after replacing or retiring a server.

## Setting it from the interface

Directory synchronization is the first consumer, so the global control is on its page.

1. Open **Account Settings**.
2. Select the **Directory Services** tab.
3. Find the **Scheduled job server** panel above the directory list.
4. If the peer secret is missing, create it on **one server only** and wait for that setting to replicate. Do not create separate secrets on both nodes.
5. Open **Manage scheduled-job nodes** (labelled **Add a second server** while the installation is still standalone), then click **Discover servers**. You can also enter a direct HTTPS address for a replacement server.
6. First-time HA approval requires two reachable, verified identities. Use the server with the lexicographically smaller PostgreSQL identifier for initial approvals. The API identifies the required writer if you use the other server. Approve both nodes there and wait for their registry rows to replicate.
7. Select the first owner on that initialization writer. Both signed snapshots must agree on the exact registry and ownership setting rows before initialization succeeds.

After initialization, perform approvals, retirements, secret rotation and legacy confirmation on the current owner. Normal ownership transfers can be requested from either UI node and are sent to that owner. Initial membership is a verified pair, not automatic discovery-based cluster membership.

Only a super administrator can approve, retire, or select nodes. Other administrators can still see the job owner, this server, the ownership version, a pending transfer, reachability, and where work is actually running.

On a standalone installation the panel collapses to a single line: there is nothing to coordinate, so the ownership detail, the peer secret warning, and the transfer control stay out of the way until a second server exists.

The control is global. It is not repeated for every directory or tenant.

## Normal ownership handoff

FS PBX verifies the selected target and sends an idempotent prepare request directly to the current owner. The current owner enters **Draining**, rejects new execution claims, and waits for running claims to finish or expire. It then updates the owner and increments the generation in one database transaction.

LDAP fetches remote data outside the coordination lock. Every database write batch, including run creation, completion and failure, rechecks node identity, owner, generation, running claim and deadline under the same local PostgreSQL transaction lock used by handoff. Authorization is checked again before commit. An expired claim revokes permission; it does **not** prove the operating-system process stopped. A paused worker that resumes cannot commit after revocation.

The generic `scheduled-jobs:maintain` command checks for expired executions and progresses pending transfers each minute. Finishing an execution also attempts completion immediately; maintenance is the fallback when a worker crashes or is killed before it can report completion. It does not choose an owner or initiate a transfer. Maintenance writes only on an actual expiry or transfer transition, never a heartbeat.

The old owner stops immediately. The target starts only after logical replication delivers that ownership transaction. A delayed transaction therefore creates a safe pause rather than overlapping execution.

If the API response is lost, retrying the same browser action preserves its target, generation and idempotency key. The original owner returns completed requests even after it has stopped being owner. A signed status query can recover a lost acknowledgment. An acknowledgment is separate from the ownership transaction arriving on the UI node.

## Forced takeover

If the current owner cannot be reached, normal transfer stops. Before forcing a takeover:

1. Power off the old owner or network-fence it.
2. Open the approved takeover target's direct address and select that server.
3. Open **Forced takeover**.
4. Type the old owner's exact direct HTTPS endpoint.
5. Confirm that the old owner is fenced, then click **Force takeover**.

Keep the old server fenced until replication is repaired and verified. Do not restart its scheduler or workers before it has received the new owner and generation. Run `php artisan scheduled-jobs:verify-rejoin` on a returning approved node, then verify application-table replication health before restarting workers. Retired identities cannot pass this check and must not be restarted as coordination members.

This check is an operator restart prerequisite, not remote power fencing. FS PBX cannot prevent someone manually starting an isolated machine with a stale database. A forced takeover records a separate administrator audit event and supersedes pending drains without overwriting the original requester.

## Reading the status

The panel distinguishes these conditions:

| Status | Meaning |
| ------ | ------- |
| **Active on this server** | This server may create coordinated execution claims |
| **Standby** | Another approved server owns the work |
| **Draining** | The owner accepts no new claims while existing work finishes |
| **Pending transfer** | A handoff has been recorded but has not completed |
| **Unreachable** | A signed identity check to that approved endpoint failed |
| **Retired** | The node remains in audit history and cannot be selected |
| **Running on...** | An actual execution claim is active on the named node and generation |

The page does not poll automatically. Use **Refresh** for directory results and **Refresh status** for coordination status. Results also refresh after actions you initiate. Status reads perform no heartbeat writes. Short-lived reachability results are cached locally and registry rows change only when a node is approved, changed, or retired.

Coordination uses the application's default cache store, selected by `CACHE_DRIVER`, with no separate coordination cache setting. On redundant servers, configure `CACHE_DRIVER=redis` connected to each server's own local Redis instance. It caches peer checks and request nonces used to reject replays. The local node identifier is read from the connected PostgreSQL cluster, so a replacement cannot inherit its predecessor's cached identity. Ownership and execution records remain in PostgreSQL.

## Synchronizing on demand

The **Sync Now** button works from either server.

When you press it on the server that runs synchronization, the job is queued locally.

When you press it on the other server, FS PBX marks the directory as due instead of starting the job. Queues are local to each server, so starting it there would leave the job stranded. After the due update replicates, the selected owner collects it on a scheduler pass, normally within a minute. The worker claims execution before advancing `next_sync_at`; a stale unclaimed queue item does not advance it.

LDAP jobs use the dedicated `scheduled-jobs` Redis queue connection with a 900-second `retry_after`, a 600-second job deadline, and a matching Horizon supervisor. Other queues keep their existing settings. The CLI `ldap:sync` uses the same guarded service and a 600-second `pcntl` alarm. LDAP network waits are bounded and each search page rechecks authorization. Partial LDAP search results are rejected.

## Seeing where a sync ran

The directory list records the server that produced each run.

* A finished run shows the server name underneath its status.
* A run still in progress shows **Running on** with the server name.
* A run whose execution is expired or no longer running shows **Stale on** with the server name. Older runs without an execution link use the fifteen-minute fallback. These are read-only status calculations, not heartbeat updates.

## Which jobs use which method

Not every scheduled job uses the same mechanism yet.

| Job | How the server is chosen |
| --- | ------------------------ |
| Directory synchronization | Generic scheduled-job owner, generation, and execution claim |
| Scheduled announcements | Failover DNS record, see [Scheduled Announcements](../05-configuration/13-scheduled-announcements/01-overview.md) |
| TLS and Nginx certificate renewal | Failover DNS record recorded during certificate installation |
| Archive call recordings to S3 | A per-server setting keyed by MAC address |

The recording archive job predates this mechanism and still uses its own switch, a setting named after the server's MAC address. See [Archive Call Recordings to S3 Storage](../05-configuration/12-scheduled-jobs/archive-call-recordings-to-s3-storage.md) for that one.

Other jobs can adopt the generic coordinator later, but must create an execution claim and place their database mutations inside `ActiveNodeResolver::withExecution()`. Checking only whether a node is active, or obtaining a claim without guarding writes, is insufficient. External effects such as calls, uploads or certificate deployment require their own idempotency/fencing boundary before adopting this mechanism.

## Replacing a server

1. Fence the removed server.
2. If the removed server was owner, force takeover on the already-approved surviving node first.
3. Retire the removed identity on the current owner. Retired registry rows and their endpoints remain as audit history; the endpoint can now be reused by a new identity.
4. Establish logical replication with the replacement, under any subscription name. Run migrations independently and refresh both subscriptions.
5. Discover and approve the replacement from the current owner. A replacement must have its own PostgreSQL identifier and machine ID.
6. Transfer ownership normally if the replacement should own scheduled jobs.
7. Rotate the coordination secret and wait for replication.

## Troubleshooting

### Synchronization is not running on either server

Open **Account Settings > Directory Services** on either server and read the status line.

The most common cause on a new pair is that no approved owner has been chosen. Configure the peer secret, approve both direct endpoints, and select an owner.

### The chosen server no longer exists

Fence the removed server, approve the replacement's new PostgreSQL identifier, transfer ownership, retire the old identifier, and rotate the coordination secret.

### A discovered host is unreachable

Subscription connection strings are historical hints, not membership. An unreachable candidate may be a disabled or stale subscription. Check the address and HTTPS access; approve only the direct endpoint of the intended live server.

### The same identifier appears at multiple endpoints

Matching host fingerprints indicate aliases. Different fingerprints indicate a clone or invalid topology and block approval. Fence the conflicting host and repair its identity before continuing.

### Rollout prerequisites

Stop coordinated schedulers/workers during the initial rollout. Run the combined `2026_09_04_000001_create_scheduled_job_coordination_tables` migration independently on both databases, then refresh both logical subscriptions. Do not rely on `app:update` to run migrations. If an earlier experimental version of that migration has already run, reconcile its schema first; Laravel will not rerun an already-recorded migration just because the file changed.

The coordination tables, global settings and LDAP application tables must travel together in an ordered logical-replication stream in each direction. Do not split their commits across independently lagging subscriptions. Verify initial table synchronization and replication errors before enabling workers. Schema snapshots are not a substitute for checking application-data replication.

HA seeding retains the generic setting definitions but does not independently insert missing coordination settings on subscribers. The initialization writer creates owner/generation rows. Repair any pre-existing duplicate settings explicitly; no automatic merge or deletion is performed.

Refresh cached Laravel configuration and restart Horizon on both nodes after deploying the new queue connection and supervisor. Keep Redis local. Maintain clock synchronization and install `pcntl` for queue/CLI timeouts.

The opt-in test harness `bash tests/Unit/run-scheduled-job-lab.sh` starts two disposable PostgreSQL 17 clusters and two Redis processes on unused loopback ports. It tests ordered replication, local transaction-lock exclusion, a delayed ownership transaction, idempotent completion recovery and stale-worker rejection. It does not test production networking, fencing hardware or a live Active Directory server.

### Both servers appear to be running the job

Stop the schedulers and workers on the node that is not the selected owner. Confirm both nodes have received the same `active_node` and generation values, then inspect active execution rows before restarting anything.

## Summary

On a single server, coordinated jobs run without registry heartbeat writes. On a redundant pair, approve direct signed identities and select the PostgreSQL system identifier that owns the work. Normal handoff drains through the old owner; forced takeover requires the old owner to be fenced. If ownership or identity is uncertain, the jobs stop rather than risk duplicate writes.
