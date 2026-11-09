---
id: message-retention
title: SMS and MMS Retention
slug: /configuration/messaging/retention
sidebar_position: 50
---

# SMS and MMS Retention

Automatic message cleanup is disabled by default. When enabled, it runs daily and permanently removes SMS/MMS history older than the configured number of days across all accounts.

In **Advanced → Default Settings**, select the **scheduled_jobs** category:

| Setting | Default | Purpose |
| --- | --- | --- |
| `delete_old_messages` | `false` | Set to `true` to enable daily cleanup. Keep the setting row enabled. |
| `days_keep_messages` | `90` | Days of message history to retain. Enter a positive whole number. |

The first run also removes existing history older than the retention period. Age is based on when a message was created. Pending messages are preserved.

Cleanup includes stored MMS attachments, original uploads retained after photo compression, related message records, and groups that become empty. Files still referenced by another message are preserved. If storage deletion fails, the message remains available for a later cleanup attempt. Storage credentials must allow deleting objects.

Older messages that contain only carrier-hosted attachment URLs lose their FS PBX history entry; FS PBX does not delete files hosted by the carrier. This setting does not delete messages from recipients' phones or external applications.

Cleanup uses the configured scheduled-job timezone. Large backlogs continue in batches. On redundant installations, it runs on the selected **Scheduled job server**; see [Scheduled jobs on one node](../../08-ha/04-scheduled-jobs-on-one-node.md). Complete the normal application migrations before enabling it, and keep message data replicated alongside scheduled-job coordination data.

## API and outbound webhook history

API request history and outbound webhook history have separate daily cleanup, enabled by default with a **30-day** retention period. These settings are also in **scheduled_jobs**:

| Records | Enable cleanup | Retention days |
| --- | --- | --- |
| Message API requests | `delete_old_message_api_requests` | `days_keep_message_api_requests` |
| Outbound message webhook events | `delete_old_outbound_message_webhook_events` | `days_keep_outbound_message_webhook_events` |
| Outbound webhook logs | `delete_old_outbound_webhook_logs` | `days_keep_outbound_webhook_logs` |

Each enable setting defaults to `true`, and each retention setting defaults to `30`. Keep the setting rows enabled; change an enable value to `false` to stop its cleanup. Existing administrator values are preserved during updates.

Only expired API request records and completed or failed webhook deliveries are removed. Pending deliveries and retries are preserved. This cleanup uses the selected Scheduled job server and does not remove SMS/MMS history or attachments.

## Inbound webhook history

The **Inbound Webhooks** log already has daily cleanup enabled by default through `prune_old_webhook_requests`. Requests older than **30 days**, including their stored payloads, headers, and exceptions, are removed. This is separate from SMS/MMS retention and does not delete message attachments.
