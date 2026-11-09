---
title: Outbound Messaging API
sidebar_position: 40
---

# Outbound Messaging API

Send SMS, MMS, and group messages from your application using a messaging number configured in FS PBX. This guide covers your first message, attachments, group messages, and delivery updates.

## Before you begin

You need:

- Your FS PBX address, an API key, and your account UUID. Ask your administrator for these. Administrators can create a key from a user's **API Keys** tab.
- An API user with access to the account and the `messages_send` permission.
- An enabled sending number in **Advanced → Message Settings**, with a working messaging provider. It does not need assigned members.

Messages sent through the API appear in **Status → Logs → Messages**. They do not appear in member conversations.

## Send your first SMS

Replace `pbx.example.com`, `ACCOUNT_UUID`, and `YOUR_API_TOKEN` with your details. Use your messaging number as `from` and the recipient's number as `to`:

```bash
curl -X POST 'https://pbx.example.com/api/v1/domains/ACCOUNT_UUID/messages' \
  -H 'Authorization: Bearer YOUR_API_TOKEN' \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: order-123-confirmation' \
  -d '{
    "from": "+12025550100",
    "to": "+12025550101",
    "text": "Your appointment is confirmed."
  }'
```

Both `from` and `to` **must use E.164 format**: `+` followed by the country code and phone number, with no spaces, parentheses, or hyphens. For example, use `+12025550100`, not `2025550100` or `(202) 555-0100`. National numbers are rejected with HTTP 400; the account's country setting is not used.

The same rules apply to every recipient in a group message. Phone numbers in responses and delivery callbacks use E.164 format.

The optional `Idempotency-Key` helps [prevent duplicate submissions](#prevent-duplicate-submissions). Choose a new key for each message you want to send.

### Understand the response

A successful request returns HTTP 200:

```json
{
  "data": {
    "id": "751ec0cb-4a4e-4462-85ac-f333cbaf77ee",
    "record_type": "message",
    "direction": "outbound",
    "type": "SMS",
    "from": {"phone_number": "+12025550100"},
    "to": [{"phone_number": "+12025550101", "status": "queued"}],
    "text": "Your appointment is confirmed.",
    "media": [],
    "received_at": "2026-11-05T00:00:00+00:00",
    "sent_at": null,
    "completed_at": null,
    "errors": []
  }
}
```

The `queued` status means FS PBX accepted your request. It does not mean the message has been delivered. Save `data.id` to match the message with [delivery callbacks](#delivery-callbacks) and message logs. `received_at` is the time FS PBX accepted the request.

### Request fields

| Field | What to send |
| --- | --- |
| `from` | Your enabled messaging number in this account, in E.164 format (for example, `+12025550100`). |
| `to` | One recipient's phone number in E.164 format. For group MMS, use an array of E.164 numbers. |
| `text` | Up to 1,600 characters. Required unless you provide an attachment. |
| `media_urls` | Optional array of public HTTPS links to attachments. |
| `type` | Optional `SMS` or `MMS`. Omit it to select the type automatically. Text to one recipient is SMS; attachments and all group messages are MMS. |

Include nonblank text or at least one attachment. Only the fields listed above are accepted.

## Send an MMS

Use the same endpoint and add `media_urls`. Your number and provider must support MMS.

```bash
curl -X POST 'https://pbx.example.com/api/v1/domains/ACCOUNT_UUID/messages' \
  -H 'Authorization: Bearer YOUR_API_TOKEN' \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: order-123-photo' \
  -d '{
    "from": "+12025550100",
    "to": "+12025550101",
    "text": "Here is your photo.",
    "media_urls": ["https://files.example.com/photo.jpg"]
  }'
```

You can omit `text` to send an attachment by itself. The response has `type: MMS` and lists your attachments in `media`.

Use direct HTTPS links that are publicly downloadable without signing in. Links requiring redirects or containing a username and password are not supported. Keep the files available until the message has been sent. If an attachment cannot be downloaded, the message fails.

| Provider | Maximum attachments per message |
| --- | --- |
| ClickSend, Commio, Fibernetics | 1 |
| VoIP.ms | 3 |
| Other providers | 10 |

The default total download limit is 50 MiB per message. Your provider may impose smaller file or message limits. The account's photo conversion and compression settings also apply.

## Send a shared group MMS

```bash
curl -X POST 'https://pbx.example.com/api/v1/domains/ACCOUNT_UUID/messages/group_mms' \
  -H 'Authorization: Bearer YOUR_API_TOKEN' \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: meeting-123-group' \
  -d '{
    "from": "+12025550100",
    "to": ["+12025550101", "+12025550102"],
    "text": "Our meeting starts at 10.",
    "type": "MMS"
  }'
```

All recipients participate in the same group conversation. Include at least two distinct recipient numbers, excluding the sending number. Repeated numbers count once.

Group MMS is available with Sinch for up to 20 recipients and supported Bandwidth local numbers for up to 10. Bandwidth toll-free numbers and other providers do not support group MMS.

Group messages are always MMS, even without attachments. You can add `media_urls` as in the MMS example. Responses and callbacks include `group_id` and a `participants` list.

## Prevent duplicate submissions

If you do not receive a response to your send request, retry it with the **same `Idempotency-Key` and request body**. For 24 hours, the same key, account, API user, and endpoint return the original acceptance response and message ID without creating another message.

- Use 1–255 letters, digits, hyphens, or underscores for the key.
- Reusing a key with different message details returns HTTP 409 (`idempotency_conflict`).
- Use a new key for each new message, including an intentional resend of a failed message.
- After 24 hours, reusing a key can create a new message.
- Without a key, each request can create a separate message.

If you receive `queue_unavailable` (HTTP 503), wait briefly and retry with the same key and body. A repeated acceptance response does not show the latest delivery status; use delivery callbacks for that.

## Delivery callbacks

To receive delivery updates in your application, edit the sending number in **Message Settings**. Under **Incoming messages go to**, select **Custom webhook** and enter your URL, or select **Account webhook** to use the URL from **Account Settings → Messaging**.

This is also where incoming messages for the number will go. See [Inbound Message Webhooks](./30-inbound-webhooks.md#1-choose-where-messages-go) before changing an existing number's destination. If **Members** is selected or no account webhook is configured, you can still send messages and check their results in message logs.

Webhook settings apply to messages submitted after the change. You cannot select a different webhook in a send request.

| Event | Meaning |
| --- | --- |
| `message.sent` | The provider accepted the message. This does not confirm delivery. |
| `message.finalized` | A delivery result is available. Check the status for each recipient. |

For example, a successfully delivered SMS produces a callback like this:

```json
{
  "data": {
    "id": "9e5c38e3-6077-4b34-a8ad-f1ac555c08cb",
    "record_type": "event",
    "event_type": "message.finalized",
    "occurred_at": "2026-11-05T00:00:05+00:00",
    "payload": {
      "id": "751ec0cb-4a4e-4462-85ac-f333cbaf77ee",
      "record_type": "message",
      "direction": "outbound",
      "type": "SMS",
      "from": {"phone_number": "+12025550100"},
      "to": [{"phone_number": "+12025550101", "status": "delivered"}],
      "text": "Your appointment is confirmed.",
      "media": [],
      "received_at": "2026-11-05T00:00:00+00:00",
      "sent_at": "2026-11-05T00:00:01+00:00",
      "completed_at": "2026-11-05T00:00:05+00:00",
      "errors": []
    }
  }
}
```

Match `data.payload.id` to the message ID from your send response. Use `data.id` to process each event only once. Retries keep the same event ID and contents. Events may arrive out of order, so a late `message.sent` should not replace a delivery result you already received.

### Recipient statuses

| Status | Meaning |
| --- | --- |
| `queued` | FS PBX accepted the request for sending. |
| `sent` | The provider accepted the message. Delivery is not yet confirmed. |
| `delivered` | The provider confirmed delivery. |
| `sending_failed` | The message could not be sent. |
| `delivery_failed` | The provider accepted the message but later reported that delivery failed. |
| `delivery_unconfirmed` | The provider reported that it could not confirm delivery. |

For group messages, check every entry in `to`: some recipients may receive the message while others fail. A finalized event reports the group's results once they are available for all recipients.

Some providers do not supply final delivery receipts. Those messages remain `sent`. If a provider later resolves a `delivery_unconfirmed` result, you receive a new event with the updated status for the same message.

When a message fails, `errors` includes an error `code`, a readable `detail`, and the affected number in `source.recipient`. Check **Status → Logs → Messages** for more information.

### Verify and acknowledge callbacks

Use the account **Signing secret** to verify the `Signature` header against the raw request body. See the [signature verification example](./30-inbound-webhooks.md#verify-the-signature). The same secret verifies incoming messages and outbound delivery updates.

Return HTTP 2xx after accepting the event. Response bodies are ignored. Failed callback delivery may be retried; this does not send the SMS or MMS again. Download attachments linked in callbacks within 24 hours of the event.

## Handle request errors

An invalid request returns an error instead of a message. For example, HTTP 400 with missing message content:

```json
{
  "error": {
    "type": "invalid_request_error",
    "message": "Provide text or at least one media URL.",
    "code": "invalid_parameter",
    "param": "text"
  }
}
```

| HTTP status | What to check |
| --- | --- |
| `400` | Correct the field identified by `error.param`. Check phone numbers, message content, and attachment limits. |
| `401` | Check the API key in your `Authorization: Bearer` header. |
| `403` | Ask your administrator to check the API user's account access and sending permission. |
| `404` | Check the account UUID in the request URL. |
| `409` | An idempotency key was reused with different details. Retry the original request, or use a new key for a new message. |
| `429` | Too many requests. Wait before trying again. |
| `503` | The request could not be queued. Retry with the same idempotency key and body. |

These errors use FS PBX's `error` object, rather than Telnyx's `errors` array. A delivery failure after an HTTP 200 response is reported through callbacks and message logs.
