---
id: scheduled-job-server-setup
title: Scheduled Job Server Setup
slug: /configuration/scheduled-jobs/server-ownership/
sidebar_position: 1
---

# Scheduled Job Server Setup

Open **Advanced → System Settings → Scheduled Jobs** to choose which server runs shared background jobs. All server setup and management described below takes place on this page.

The **Scheduled job server** selection applies across all accounts to supported jobs, including directory synchronization, Contact Center callbacks, and recording archiving. On a redundant installation, one server runs these jobs while the other remains on standby.

## Single-server installations

If the panel shows **Single server**, jobs already run on that server. No server selection is needed.

## Set up a redundant pair

You can discover and approve servers, select the first owner, and request normal ownership transfers from either server's interface. Sign in as a super administrator to access these controls.

### Connect and discover the servers

1. If prompted, click **Create coordination secret** once, on one server only. On the other server, click **Refresh status** until the warning clears.
2. Open **Manage scheduled-job nodes**. In single-server mode, this section is called **Add a second server**.
3. Leave **Direct HTTPS address (optional)** empty and click **Discover servers**.
4. Check that both intended servers appear under **Discovered servers** with a **Verified** status.

Each server should have its own IP address or dedicated hostname. A shared failover hostname can switch between servers and should not be used as a saved server address.

If a server is missing, enter its direct HTTPS address in **Direct HTTPS address (optional)** and click **Discover servers** again.

### Approve the servers

1. Click **Approve** for each intended server, including the server you are currently using.
2. Confirm that both appear under **Registered servers**.
3. Open **Advanced → System Settings → Scheduled Jobs** on the other server and click **Refresh status** until both registrations appear there too.

Both servers must be verified before the first approval. Approving a server makes it available for selection; it does not make it the job owner.

### Select the first owner

1. Under **Transfer ownership to**, select the approved server that should run the jobs.
2. Click **Transfer ownership**.
3. Click **Refresh status** on both servers until they show the same **Job owner**.

The selected server shows **Active on this server**. The other server shows **Standby**. If the page asks you to wait, allow the change to appear on both servers before trying again.

## Check job status

| Field or status | Meaning |
| --- | --- |
| **Job owner** | The server selected to run shared jobs. |
| **This server** | The server you are currently viewing. |
| **Running now** | Jobs currently in progress and the server handling them. |
| **Active on this server** | This server is the job owner. |
| **Standby** | Another server is the job owner. |
| **Draining** / **Transfer in progress** | The current owner is finishing work before the other server takes over. |
| **Ownership unknown** | Jobs are waiting until a valid owner can be confirmed. Read the message shown in the panel. |
| **Unreachable** | FS PBX cannot contact that server. |

Click **Refresh status** to see the latest information. An empty **Running now** list can simply mean no jobs are due.

## Change the job owner

For planned maintenance, transfer ownership before taking the current owner out of service.

1. Open **Advanced → System Settings → Scheduled Jobs** on either server.
2. Select the other approved server under **Transfer ownership to**.
3. Click **Transfer ownership**.
4. Use **Refresh status** until both servers show the new **Job owner** and the transfer is complete.

The current owner finishes its running work before the new server takes over. There may be a short pause during the transfer.

Changing the failover DNS record does not change the job owner. Use **Transfer ownership** for scheduled jobs.

## Update a server address

If a registered server uses a shared failover hostname, enter its direct HTTPS address in the discovery field and click **Discover servers**. Beside the verified result for that server, click **Use this address**.

This updates the saved address without changing the job owner.

## Remove or replace a server

If the server being removed is the owner, complete an ownership transfer first. Then open **Manage scheduled-job nodes** on the current job owner:

1. Under **Registered servers**, click **Retire** beside the server being removed.
2. Click **Remove** if you also want to remove its entry from the list.
3. When the replacement is ready, click **Discover servers** and approve it.
4. Click **Rotate coordination secret** on the current owner, then use **Refresh status** on both servers until they are reachable.

Select the replacement under **Transfer ownership to** if it should run the jobs.

## If the current owner has failed

Use **Forced takeover** only after the previous owner has been confirmed powered off or isolated from the network. An unreachable page alone does not confirm that its jobs have stopped.

On the approved server that will take over:

1. Open **Advanced → System Settings → Scheduled Jobs**.
2. Select this server under **Transfer ownership to** and expand **Forced takeover**.
3. Enter the previous owner's address exactly as shown in the panel.
4. Select the confirmation checkbox and click **Force takeover**.
5. Click **Refresh status** to confirm that this server is now the **Job owner**.

Keep the previous server out of service until your administrator or support provider confirms it is ready to return.

## Troubleshooting

| Problem | What to do on the Scheduled Jobs page |
| --- | --- |
| **Discover servers** is disabled | If prompted, click **Create coordination secret** on one server only. Wait, then refresh the other server's status. |
| A server is missing | Enter its direct HTTPS address and click **Discover servers** again. |
| A server is unreachable | Check that its saved address belongs to that server. Read the displayed error and retry after access is restored. |
| Both servers are approved, but no owner is selected | Choose a server under **Transfer ownership to** and click **Transfer ownership**. |
| A transfer was accepted, but the old owner still appears | Wait for the transfer to finish, then click **Refresh status**. |
| A **Duplicate identity** warning appears | Do not approve the affected result. Ask your administrator or support provider to resolve the warning. |

If an error persists, share the message and the status shown on both servers with your support provider.
