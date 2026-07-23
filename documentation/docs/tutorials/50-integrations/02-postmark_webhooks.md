---
sidebar_position: 17
title: Postmark Email Log Webhook
---

# Postmark Email Log Webhook

FS PBX can use Postmark webhooks to update Email Logs when Postmark reports delivery, bounce, or spam complaint events. This lets the **Logs > Emails** page show provider delivery results without manually inspecting each transaction.

## Prerequisites

- FS PBX must be sending email through Postmark.
- The FS PBX server must be reachable from the public internet over HTTPS.
- Queue workers or Horizon must be running, because webhook processing is queued.

## Configure FS PBX

Edit the FS PBX `.env` file:

```bash
nano /var/www/fspbx/.env
```

Set Postmark as the mailer and add the Postmark server token:

```dotenv
MAIL_MAILER=postmark
POSTMARK_TOKEN=your-postmark-server-token
POSTMARK_MESSAGE_STREAM_ID=outbound
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="FS PBX"
```

`POSTMARK_TOKEN` must be the **server token** for the Postmark server that sends the FS PBX email. This token is manually generated from your end, and can be any combonation of characters. We suggest making this a secure string.

After editing `.env`, rebuild Laravel's cached config and restart the long-running workers:

```bash
php artisan config:cache
php artisan horizon:terminate
```

## Configure the Postmark webhook

In Postmark:

1. Open the Postmark server that uses the same server token configured in `POSTMARK_TOKEN`, and navigate to your Transactional Stream.
2. Go to **Settings > Webhooks**.
3. Add or edit the webhook.
4. Set the webhook URL:

```text
https://your-fspbx-hostname/webhook/postmark
```

5. Enable these events:

- **Delivery**
- **Bounce**
- **Spam Complaint**

6. Use the `outbound` message stream unless you configured a different stream in `POSTMARK_MESSAGE_STREAM_ID`.
7. Save the webhook.

Do not configure this webhook on a different Postmark server. Postmark webhooks are tied to the server that sends the message.

## Troubleshooting

### The webhook test creates a row, but a real email does not

Confirm the real email is being sent through the same Postmark server where the webhook is configured. A common mistake is updating `POSTMARK_TOKEN` to a new server token while the webhook remains configured on the old Postmark server.

### The Email Log stays on `sending`

This usually means Laravel created the log row before handing the message to Postmark, but Postmark rejected the send before the sent event fired. Suppressed or inactive recipients can behave this way. Check `sent_debug_info`, `failed_jobs`, and Laravel logs.

### The Email Log stays on `sent` until Delivery Details is opened

The Email Logs page does not poll Postmark on page load. It relies on webhook events and stored database state. If Postmark shows a transient delivery issue in the message details but does not send a Bounce or Spam Complaint webhook yet, the row may remain `sent` until a webhook arrives or Delivery Details performs a manual lookup.

### Confirm Laravel's active Postmark config

Run:

```bash
php artisan tinker --execute="dump([
    'postmark_token_prefix' => substr((string) config('services.postmark.token'), 0, 8),
    'postmark_token_length' => strlen((string) config('services.postmark.token')),
    'postmark_stream' => config('mail.mailers.postmark.message_stream_id'),
    'mail_default' => config('mail.default'),
]);"
```

The token prefix should match the token you expect, the stream should usually be `outbound`, and `mail_default` should be `postmark`.

### Check for webhook job failures

Run:

```bash
php artisan tinker --execute="dump(DB::table('failed_jobs')->where('exception','like','%ProcessPostmarkWebhookJob%')->latest('failed_at')->limit(5)->get(['queue','exception','failed_at'])->toArray());"
```

If failures appear, fix those errors first and then retry the webhook from Postmark.
