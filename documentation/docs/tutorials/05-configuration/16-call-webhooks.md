---
sidebar_position: 16
title: Call Webhooks
---

# Call Webhooks

Call webhooks let a CRM react when an inbound call is offered to a specific extension or Basic Queue agent. FS PBX does not send events for IVR navigation, queue entry, voicemail, trunks, fax, or other account-wide call activity.

## Enable the listener

The call webhook listener is installed but disabled by default. Enable it only on servers that use call webhooks. One listener handles every configured account on the server.

Set `autostart=true` in the Supervisor configuration:

```bash
sudo sed -i 's/^autostart=false$/autostart=true/' /etc/supervisor/conf.d/fs-esl-listener-call-webhooks.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status fs-esl-listener-call-webhooks
```

After `supervisorctl update`, the status should be `RUNNING`. If it remains `STOPPED`, start it explicitly:

```bash
sudo supervisorctl start fs-esl-listener-call-webhooks
```

Enabling a webhook in Account Settings controls event delivery but does not start the server listener. The Supervisor service must also be enabled.

The listener caches the enabled account configurations for 24 hours. Saving, enabling, disabling, rotating, or deleting a configuration in Account Settings invalidates that cache immediately. Direct database changes can take up to 24 hours to be detected and should be avoided.

To disable the listener again:

```bash
sudo supervisorctl stop fs-esl-listener-call-webhooks
sudo sed -i 's/^autostart=true$/autostart=false/' /etc/supervisor/conf.d/fs-esl-listener-call-webhooks.conf
sudo supervisorctl reread
sudo supervisorctl update
```

Listener output is available in:

```text
/var/log/fs-esl-listener-call-webhooks.out.log
/var/log/fs-esl-listener-call-webhooks.err.log
```

## Configure a webhook

1. Open **Account Settings** and select **Call Webhooks**.
2. Enter a public HTTPS endpoint.
3. Select the Ringing, Answered, and Ended events your CRM needs.
4. Save the configuration and copy the signing secret. The complete secret is shown only once.
5. Use **Test Webhook** to send a signed `call.test` request.

One endpoint receives events for every mapped extension and Basic Queue agent in the account. Use `data.target` to select the correct CRM user.

## Events

- `call.ringing`: the extension or queue agent is being offered the call.
- `call.answered`: that target answered the call.
- `call.ended`: that target's interaction finished or stopped ringing.

For ring-all calls, each offered target has its own lifecycle. Losing targets receive `call.ended`, normally with `state.outcome` set to `canceled`.

## Request format

```json
{
  "id": "event-uuid",
  "type": "call.ringing",
  "occurred_at": "2026-07-12T15:04:05.123Z",
  "data": {
    "interaction_id": "stable-call-or-member-uuid",
    "channel_uuid": "current-agent-channel-uuid",
    "domain_uuid": "account-uuid",
    "direction": "inbound",
    "caller": {
      "name": "Alice",
      "number": "12025550100"
    },
    "destination_number": "100",
    "target": {
      "type": "queue_agent",
      "extension_uuid": "extension-uuid",
      "extension": "100",
      "call_center_agent_uuid": "agent-uuid",
      "agent_id": "100",
      "agent_name": "Support Agent",
      "call_center_queue_uuid": "queue-uuid",
      "queue_extension": "700",
      "queue_name": "Support"
    },
    "state": {
      "answered_at": null,
      "ended_at": null,
      "outcome": null,
      "hangup_cause": null
    }
  }
}
```

Direct calls use `target.type: extension` and have `null` queue fields. Queue calls use `target.type: queue_agent` and include the underlying extension when it can be mapped uniquely.

Use `interaction_id`, `target.type`, and the target UUID as the screen-pop lifecycle key. Do not use `channel_uuid`; it can change or be absent during a queue offer.

## Verify requests

Requests include these headers:

- `Signature`: lowercase HMAC-SHA256 of the exact JSON request body using the signing secret.
- `Timestamp`: Unix timestamp generated for the delivery attempt.
- `X-FS-PBX-Event-ID`: the event UUID.
- `X-FS-PBX-Event-Type`: the event type.

Reject stale timestamps according to your security policy and compare signatures with a constant-time comparison. Deduplicate requests by the event ID because a retry can deliver the same event more than once. Strict delivery order is not guaranteed.
