---
title: Inbound Message Webhooks
sidebar_position: 30
---

# Inbound Message Webhooks

Receive incoming SMS and MMS in your own application. When someone texts your FS PBX number, FS PBX sends a `message.received` event to your webhook URL. The event format is the same for every supported messaging provider.

## Before you begin

You need a messaging-enabled number in **Advanced → Message Settings**, access to its settings, and a public HTTPS URL in your application that accepts JSON POST requests.

## 1. Choose where messages go

To use one webhook for the account:

1. Open **Account Settings → Messaging**.
2. Enter your application's URL in **Default webhook URL** and save.
3. Numbers use this webhook by default. To keep a number's messages with its members, edit that number in **Message Settings** and select **Members** after saving the account URL.

To choose a destination for an individual number, open it in **Message Settings** and change **Incoming messages go to**:

| Selection | Where incoming messages go |
| --- | --- |
| **Members** | The number's members and optional email address. |
| **Account webhook** | The URL in **Account Settings → Messaging**. Set up the account URL first to select this option. |
| **Custom webhook** | The **Webhook URL** you enter for this number. |

Save the number after making your selection. **Saving a custom webhook removes the number's members and email address.** Reassign them if you later switch back to **Members**. Numbers using the account webhook keep those assignments, which apply again if the account webhook is removed.

Messages forwarded to a webhook appear in **Status → Logs → Messages**, but do not appear in member conversations or go to email. Changes to delivery settings apply to new messages.

## 2. Copy your signing secret

In **Account Settings → Messaging**, use **Show** or **Copy** next to **Signing secret**. The same secret is also available when editing a number with a webhook selected.

Add this secret to your receiving application's secure settings. Your application uses it to [verify that requests came from FS PBX](#verify-the-signature). All messaging webhooks in the account share this secret; keep it private.

## 3. Receive a test message

Send an SMS from another phone to your FS PBX number. Your application should receive an HTTP POST with `Content-Type: application/json` and a body like this:

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

| Field | Meaning |
| --- | --- |
| `data.id` | Event ID. Use it to avoid processing the same event twice. |
| `data.event_type` | `message.received` for an incoming message. |
| `data.payload.id` | Message ID, also available in FS PBX message logs. |
| `data.payload.from` | The sender's phone number. |
| `data.payload.to` | The recipient numbers. |
| `data.payload.text` | The message text. |
| `data.payload.media` | Attached files, or an empty array for a text message. |

Phone numbers use international format where available; short-code senders are preserved.

For MMS, `type` is `MMS` and each `media` entry contains `url`, `content_type`, and `size`. Download attachments within 24 hours of the event. An `errors` entry with `code: media_unavailable` means an attachment could not be retrieved.

Supported group messages also include a `group_id` and a `participants` list of phone numbers. Group messaging availability depends on your provider.

## Verify the signature

Every request includes a `Signature` header. To verify it, calculate the HMAC-SHA256 of the **exact raw request body** using your account signing secret. Compare the lowercase hexadecimal result with the header using a constant-time comparison.

Verify before decoding the JSON. Decoding and re-encoding it can change the body and invalidate the signature. This PHP example uses `hash_equals` for the comparison:

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

$supportedEvents = ['message.received', 'message.sent', 'message.finalized'];
if (!in_array($event['data']['event_type'] ?? '', $supportedEvents, true)
    || !is_string($event['data']['id'] ?? null)) {
    http_response_code(400);
    exit;
}

// Add your application's handling here. Save the event before acknowledging it.
// Use data.id to process each event once, and acknowledge duplicates with 2xx.
// Return 5xx if your application cannot accept the event.

http_response_code(204);
```

Set `FSPBX_MESSAGE_WEBHOOK_SECRET` in your application's environment to the secret you copied. Add your message handling where indicated before using this example.

## 4. Acknowledge events

Return HTTP 2xx, such as `204 No Content`, promptly after accepting an event. FS PBX ignores the response body. To reply by SMS, send a separate request through the [Outbound Messaging API](./40-outbound-api.md).

By default, your application has three seconds to respond. If the request times out or receives a non-2xx response, FS PBX retries, up to three attempts in total. Each retry has the same `data.id`. Process that event only once, even if you receive it more than once. Events may arrive out of order.

## Check delivery

Open **Status → Logs → Messages** to check webhook delivery status, attempts, and the HTTP response status. If forwarding fails, check that your URL is reachable, the signing secrets match, and your application returns 2xx within the timeout. Messages are not redirected to members when a webhook fails.

If you also send through the outbound API, this URL receives delivery updates named `message.sent` and `message.finalized`. Verify and acknowledge them in the same way. See [delivery callbacks](./40-outbound-api.md#delivery-callbacks) for their meaning and an example.
