---
id: ha-scheduled-jobs
title: Scheduled Jobs on Redundant Servers
slug: /ha/scheduled-jobs
sidebar_position: 4
---

# Scheduled Jobs on Redundant Servers

Manage scheduled-job server selection from **Advanced → System Settings → Scheduled Jobs**. On a redundant installation, the **Job owner** runs shared background jobs while the other server remains on standby.

This selection applies across all accounts to supported jobs, including directory synchronization, Contact Center callbacks, and recording archiving. Keeping one owner prevents the same work from starting on both servers.

## Choose the job owner

Open **Advanced → System Settings → Scheduled Jobs** on either server:

1. If prompted, click **Create coordination secret** on one server only. Wait for the warning to clear on the other server after clicking **Refresh status**.
2. Open **Manage scheduled-job nodes** and click **Discover servers**.
3. Verify and approve both intended servers. Each should use its own IP address or dedicated hostname.
4. Use **Refresh status** on both servers until both registrations appear.
5. Under **Transfer ownership to**, select the server that should run the jobs and click **Transfer ownership**.
6. Use **Refresh status** on both servers until they show the same **Job owner**.

The owner shows **Active on this server**; the other server shows **Standby**. You can make the first selection or request a normal transfer from either server's interface.

See [Scheduled Job Server Setup](/docs/configuration/scheduled-jobs/server-ownership/) for the complete guide to discovery, approval, and status messages.

## Planned maintenance

Before taking the current owner out of service, select the other approved server under **Transfer ownership to** and click **Transfer ownership**.

The current owner finishes its running jobs before the new owner takes over. Use **Refresh status** to confirm the transfer has completed before starting maintenance. **Running now** shows work currently in progress.

## DNS failover and scheduled jobs

A DNS failover does not move scheduled-job ownership. Use the **Scheduled Jobs** page to change the owner.

Saved server addresses must continue to reach their individual servers. If a registration uses a shared failover hostname, discover that server using its direct HTTPS address and click **Use this address** beside the verified result.

## If the owner is unavailable

An **Unreachable** status does not confirm that the server has stopped running jobs. Use **Forced takeover** only after the previous owner has been confirmed powered off or isolated from the network.

On the approved server taking over, open **Advanced → System Settings → Scheduled Jobs**, select that server, and expand **Forced takeover**. Enter the previous owner's address, complete the confirmation, and click **Force takeover**.

Keep the previous server out of service until your administrator or support provider confirms it is ready to return. See the [failed-owner steps](/docs/configuration/scheduled-jobs/server-ownership/#if-the-current-owner-has-failed) for the full page workflow.

## Replacing a server

If the server being removed is still the owner, transfer ownership first. On the current owner, use **Manage scheduled-job nodes** on the **Scheduled Jobs** page to retire the old server and discover and approve its replacement when ready.

Click **Rotate coordination secret** on the current owner after replacing a server. Use **Refresh status** on both servers to confirm they are reachable, then select the replacement as owner if needed.

## Troubleshooting

Start with **Refresh status** on both servers. Confirm that both intended servers are approved and show the same **Job owner**.

If no owner is selected, complete **Transfer ownership**. If a change is still in progress, wait and refresh again. For persistent errors, share the displayed message and both servers' status with your support provider.
