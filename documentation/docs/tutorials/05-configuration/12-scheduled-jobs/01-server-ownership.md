---
id: scheduled-job-server-setup
title: Scheduled Job Server Setup
slug: /configuration/scheduled-jobs/server-ownership/
sidebar_position: 1
---

# Scheduled Job Server Setup

The **Scheduled job server** control chooses which FS PBX server runs coordinated background work. On a redundant installation, it gives that work one owner while the other servers remain on standby.

The selection is shared across all accounts and applies to LDAP directory synchronization and [Contact Center callbacks](/docs/configuration/contact-center/queue-callbacks/), including callback cleanup. Changing it on one page changes the same owner everywhere.

This control does not select the server for every scheduled task. S3 recording archiving retains its separate per-server setting, and scheduled announcements and certificate renewal retain their DNS-based selection.

## Single-server installations

If the panel shows **Single server**, coordinated jobs already run on that server. You do not need to discover or approve another server or create a coordination secret for standalone operation.

Continue below when setting up a replicated pair. Discovery uses an existing database replication configuration; it does not install or configure replication.

## Before you begin

Use a super administrator account with access to **Scheduled Jobs**. The required `scheduled_jobs_manage` permission is assigned to superadmins by default.

Confirm these prerequisites on every participating server:

- Both servers run compatible FS PBX versions and have the required database migrations installed independently on each database. Application updates do not run these migrations for you.
- [PostgreSQL replication](/docs/ha/postgres-replication/) works in both directions. The coordination settings, registered servers, and participating application data must replicate together. New tables must be included by refreshing the subscriptions after migrations.
- Each server has its own identity and a **direct HTTPS address**, such as `https://pbx-a.example.com` and `https://pbx-b.example.com`, reachable from both servers. Use addresses that always reach the named server, rather than a shared failover address.
- Server clocks are synchronized. The normal scheduler and background workers must run on each server after setup; selecting an owner does not replace those services.
- For Contact Center callbacks, both servers have the module runtime installed and [recording-file synchronization](/docs/ha/syncthing/) configured.

For an initial rollout, keep coordinated schedulers and workers stopped until migrations, replication, and ownership are verified. See the [technical HA guide](/docs/ha/scheduled-jobs/#rollout-prerequisites) for deployment details. For Contact Center, follow the [server verification commands](/docs/configuration/contact-center/queue-callbacks/#verify-the-server-before-enabling-callbacks) when bringing services online.

## 1. Open the server control

Open **System Settings > Scheduled Jobs**. The same control is also available under:

- **Contact Center Settings > Scheduled Jobs**, when the module is installed.
- **Account Settings > Directory Services**.

Find the **Scheduled job server** panel. If you cannot see the tab or management controls, use an account with the required permission.

## 2. Create the shared coordination secret

1. Open **Manage scheduled-job nodes**. In single-server mode, this section is called **Add a second server**.
2. If shown, click **Create coordination secret** on **one server only**.
3. Wait for database replication to deliver it to the other server.
4. Open the same panel directly on the other server and click **Refresh status**. Confirm it no longer asks you to create a secret.

The secret lets the servers verify each other's identity. If it is already configured, use the existing secret. Creating separate secrets on both servers prevents verification; do not create or rotate it separately on each server.

## 3. Automatically discover replicated servers

1. In **Manage scheduled-job nodes**, leave **Direct HTTPS address (optional)** empty.
2. Click **Discover servers**.
3. Review **Discovered servers**. Each result shows its address, hostname when available, verification status, and database identifier.

FS PBX reads host addresses from the existing PostgreSQL replication subscriptions and checks their HTTPS endpoints. It also checks this server and registered addresses. Subscription names do not need to follow a naming convention.

Disabled or old subscriptions can still contribute addresses, so an unreachable result may simply be a retired address. Discovery lists candidates; it does not add or approve them automatically.

| Discovery status | Meaning |
| --- | --- |
| **Verified** | The address passed the identity check and can be considered for approval. |
| **Approved** | This server is already registered. |
| **Unreachable** | FS PBX could not complete the signed HTTPS check. Read the accompanying error. |
| **Duplicate identity** | Conflicting servers reported the same database identity. Resolve the identity conflict before approval. |
| **Retired** | This identity was removed from service and cannot be approved again. |

### Add a direct address when discovery misses a server

If a replication host is a database-only address, an obsolete address, or not the PBX's HTTPS address:

1. Enter the intended server's address in **Direct HTTPS address (optional)**, for example `https://pbx-b.example.com`.
2. Click **Discover servers** again.
3. Confirm that the returned hostname and database identifier belong to the intended server.

Use an HTTPS base address without a login path. A manual address is also useful when adding a replacement server. It still has to pass verification and approval.

## 4. Approve the servers

For the first setup, **both servers must be reachable and verified before either is approved**. Initial registration requires a pair.

1. Perform the initial approvals from the server whose displayed database identifier comes first when compared as text. If you try from the other server, FS PBX returns **Manage scheduled-job membership on node …** with the required identifier. Match it to the discovered server and open that server's direct address.
2. On that server, click **Discover servers** and then **Approve** for each intended server, including the local server.
3. Confirm that both appear under **Registered servers**.
4. Wait for the registrations to replicate. On the other server, use **Refresh status** and confirm that the same servers are listed.

Approval adds a server to the shared registry. It does not select the job owner. Do not repeat the approvals independently on both servers.

After an owner is selected, perform later approvals, retirements, and secret rotation on the current owner.

## 5. Select the first owner

1. Stay on the server used for the initial approvals.
2. Under **Transfer ownership to**, select the approved server that should run coordinated jobs. You can choose either server.
3. Click **Transfer ownership**. This button also selects the first owner.
4. Use **Refresh status** on both servers after replication catches up.

Verify that both pages show the same **Job owner** and **Ownership version**. The chosen server should show **Active on this server**; the other should show **Standby**.

If FS PBX asks you to wait for both nodes to agree on membership and initial ownership settings, let replication finish and retry. If the message persists, check replication before continuing.

For Contact Center callbacks, select a server that can reach the agents and place outbound customer calls. New callback offers are made on the owner, so incoming queue traffic must also reach that server for callers to receive an offer.

## Read the status

| Field or status | Meaning |
| --- | --- |
| **Job owner** | The selected server authorized to run coordinated work. |
| **This server** | The server currently answering your browser request. |
| **Ownership version** | Increases on ownership changes so work from an earlier owner cannot start under the old selection. |
| **Running now** | Work actually in progress and the server handling it. An empty list can simply mean there is no work due. |
| **Active on this server** | This server owns coordinated jobs. |
| **Standby** | Another server owns coordinated jobs. |
| **Draining** / **Transfer in progress** | The current owner is finishing or releasing work before the transfer completes. |
| **Ownership unknown** | FS PBX cannot establish a valid owner. Coordinated work waits until the issue is resolved. |
| **Unreachable** | The owner's signed HTTPS check failed. This does not prove that its jobs or telephone calls have stopped. |

The control does not refresh automatically. Click **Refresh status** to check again; actions also refresh their results.

## Transfer ownership for planned maintenance

1. Confirm that both servers are reachable and replication is healthy.
2. In **Transfer ownership to**, select the other approved server.
3. Click **Transfer ownership**. A normal transfer can be requested from either server's interface.
4. Wait while the current owner finishes its work. Use **Refresh status** until the new server shows **Active on this server** and both pages agree on the owner and version.
5. Confirm the transfer is complete before stopping the former owner's services for maintenance.

There may be a pause while running work finishes and the ownership change replicates. Changing DNS or moving inbound telephone traffic does not transfer scheduled-job ownership. There is no automatic takeover when a peer becomes unreachable.

Use this control for transfers. Editing the owner value directly in Default Settings bypasses the handoff and can leave both servers acting as owner.

## If the current owner has failed

**Forced takeover** is for an administrator who has confirmed that the old owner is powered off or isolated from the network. An unreachable page alone is not enough.

After isolating the old owner:

1. Open the control directly on the approved server that will take over.
2. Select that server under **Transfer ownership to**, then expand **Forced takeover**.
3. Type the old owner's exact address shown in the panel.
4. Check **I confirm the old owner is powered off or network-fenced.** and click **Force takeover**.

Keep the old server isolated until its ownership state and application data have caught up. Before restarting its scheduler or workers, run `php artisan scheduled-jobs:verify-rejoin` and verify application-data replication. Follow the [HA recovery procedure](/docs/ha/scheduled-jobs/) when returning or replacing a server.

For callbacks, an uncertain call from the old owner may remain **Needs review**. Taking ownership does not automatically redial a customer whose previous call outcome is unknown.

## Troubleshooting

| Problem | What to do |
| --- | --- |
| Discover servers is disabled | Create the shared coordination secret once and wait for it to replicate. |
| A replicated server is missing or unreachable | Try its direct HTTPS address. Check connectivity, the shared secret, server clocks, and the reported error. Discovery results can remain cached for about 15 seconds. |
| Approval says to discover both nodes | Confirm that discovery verifies both the local server and its intended peer before the first approval. |
| Approval or management directs you to another node | Use the identified setup server for initial registration, or the current owner after setup. |
| Both servers are approved but no jobs run | Approval alone does not choose an owner. Complete **Transfer ownership**, then check the scheduler and workers if needed. |
| Transfer is accepted but the old owner still appears | Wait for running work and replication, then use **Refresh status**. Persistent disagreement requires a replication check. |
| Two addresses show the same database identifier | They may be aliases for one server. If **Duplicate identity** is shown, resolve the conflicting host identities before approving anything. |
| A server is being replaced | Follow the [replacement procedure](/docs/ha/scheduled-jobs/#replacing-a-server), retire the removed identity, and discover and approve the replacement. Rotate the coordination secret on the owner afterward and let it replicate. |
