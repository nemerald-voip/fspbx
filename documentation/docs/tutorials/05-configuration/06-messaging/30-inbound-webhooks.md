---
title: Inbound Message Webhooks
sidebar_position: 30
---

# Inbound Message Webhooks

Forward incoming SMS and MMS messages from any configured messaging provider to another application. FS PBX sends a consistent JSON event regardless of the provider that received the message.

## Configure delivery

You can forward all numbers to one account webhook, give individual numbers their own webhook, or both.

**Account webhook (optional):** open **Account Settings → Messaging**, enter a public HTTPS **Default webhook URL**, and save. Numbers use it by default. To keep a number with its members, choose **Members** for that number after saving the account URL.

**Per number:** in **Message Settings**, create or edit a number and choose where **Incoming messages go to**:

- **Members:** deliver to the number's members and email.
- **Account webhook:** forward to the account webhook. The option shows the account URL, or *Not set up* when there is none.
- **Custom webhook:** forward to a **Webhook URL** used only by this number. Saving removes the number's members and email, so members can no longer send or receive with it.

Numbers on the account webhook keep their saved members and email, and those apply again if the account webhook is removed.

**Signing secret:** when a webhook option is selected, the number form shows the account **Signing secret** with **Show** and **Copy**. Account Settings shows the same secret. It is created the first time it is shown, so there is nothing to save first. Configure it securely in your receiving application.

Webhook delivery is exclusive. These incoming messages remain in administrative message logs but do not appear in user conversations, increase unread counts, or go to Ringotel or email. Changing delivery settings affects new messages; it does not reroute messages already received.

The same account secret verifies the account webhook and every custom number webhook. Only users who can manage messaging settings can view it.

## Event format

Requests use HTTP POST with `Content-Type: application/json`:

```json
{
  "data": {
    "event_type": "message.received",
    "id": "9e5c38e3-6077-4b34-a8ad-f1ac555c08cb",
    "occurred_at": "2026-11-04T00:00:00+00:00",
    "record_type": "event",
    "payload": {
      "id": "751ec0cb-4a4e-4462-85ac-f333cbaf77ee",
      "record_type": "message",
      "direction": "inbound",
      "type": "SMS",
      "from": {"phone_number": "+12025550101"},
      "to": [{"phone_number": "+12025550100"}],
      "text": "Hello",
      "media": [],
      "received_at": "2026-11-04T00:00:00+00:00"
    }
  }
}
```

`data.id` identifies the event and stays the same across retries. `data.payload.id` identifies the stored FS PBX message. Numbers use international format where available; short-code senders are preserved.

MMS uses `type: MMS` and includes `media` entries containing `url`, `content_type`, and `size`. Download the files within 24 hours of forwarding. If an upstream attachment could not be retrieved, the payload includes an `errors` entry with `code: media_unavailable`; the message remains MMS.

Supported group messages also include `group_id` and `participants`, a list containing all participant numbers. Group capabilities depend on the number's existing provider.

## Verify the signature

The `Signature` header contains the lowercase hexadecimal HMAC-SHA256 of the **exact raw request body**, calculated with the account signing secret. Verify the body before decoding JSON; decoding and re-encoding it can change the bytes and invalidate verification.

Example PHP receiver:

```php
<?php

$secret = getenv('FSPBX_MESSAGE_WEBHOOK_SECRET');
$body = file_get_contents('php://input');
$signature = $_SERVER['HTTP_SIGNATURE'] ?? '';

if (!is_string($secret) || $secret === '' || !is_string($body)) {
    http_response_code(503);
    exit;
}

$expected = hash_hmac('sha256', $body, $secret);
if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit;
}

try {
    $event = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    http_response_code(400);
    exit;
}

if (($event['data']['event_type'] ?? '') !== 'message.received'
    || !is_string($event['data']['id'] ?? null)) {
    http_response_code(400);
    exit;
}

// Atomically store/queue the event, using data.id as a unique key.
// If that event was already accepted, acknowledge it without processing twice.
// Return success only after durable acceptance; return 5xx on a storage failure.

http_response_code(204);
```

The `Timestamp` header identifies the delivery-attempt time. The default signature covers the body only, not this header; use the signed event ID for duplicate protection. This is FS PBX shared-secret verification, not Telnyx Ed25519 verification.

## Acknowledgments and failures

Return any HTTP 2xx status promptly after accepting an event. The response body is ignored and cannot send an SMS reply. Delivery order is not guaranteed.

With the default delivery settings, each request has a three-second timeout and up to three attempts with increasing delays. A timeout can occur after your application has accepted a message, so always deduplicate by event ID.

Administrative message logs show the separate webhook delivery status, attempt count, and HTTP status. A forwarding failure does not change the message's received status and does not send it to members instead.
