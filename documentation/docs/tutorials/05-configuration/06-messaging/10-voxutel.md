---
id: voxutel
title: Voxutel SMS and MMS Provider Configuration
slug: /configuration/messaging/voxutel
description: Configure Voxutel API credentials and Message Broker webhooks for SMS and MMS in FS PBX.
sidebar_position: 10
---

# Voxutel SMS and MMS Provider Configuration

FS PBX supports sending and receiving SMS and MMS through Voxutel. Outbound messages use a portal API key; inbound messages and delivery callbacks use a separate Message Broker signing key.

## Create the portal API key

1. Sign in to your **Voxutel portal**.
2. Open **Account & Billing → Users**.
3. Add a new user and assign the **Voxutel SMS API** role.
4. Open the **API Keys** tab and create a new key for that user.
5. Save that key as `VOXUTEL_API_KEY` in your FS PBX `.env` file.

Use the uppercase environment variable name exactly as shown. FS PBX sends this key as the Bearer token when submitting outbound messages.

## Configure FS PBX

Edit `/var/www/fspbx/.env` (or `.env` in your installation directory) and add:

```dotenv
VOXUTEL_API_KEY=
VOXUTEL_MESSAGE_BROKER_URL=https://api.voxutel.net/msgbroker/rest/publishMessages
VOXUTEL_INBOUND_API_KEY=
```

| Variable | Value |
| --- | --- |
| `VOXUTEL_API_KEY` | The key created in the portal's **API Keys** tab for the user with the **Voxutel SMS API** role. Used to send SMS and MMS. |
| `VOXUTEL_MESSAGE_BROKER_URL` | Set to `https://api.voxutel.net/msgbroker/rest/publishMessages` for outbound SMS and MMS. Use the complete URL, including `/publishMessages`; FS PBX does not append a path. |
| `VOXUTEL_INBOUND_API_KEY` | The Message Broker signing key used to authenticate inbound messages and delivery callbacks. It must match the key configured for your broker's webhook deliveries. |

Create a strong random signing secret and enter the same value in the Voxutel webhook settings and `VOXUTEL_INBOUND_API_KEY`. This shared secret is separate from the portal API key. Keep both keys private and use HTTPS for the send endpoint and webhook URL.

The repository's environment template is named `.env.example`; it includes these entries. Add them to your existing `.env` without replacing your other settings.

## Configure the Message Broker webhook

In the **Voxutel portal**, set up a webhook with these values:

| Setting | Value |
| --- | --- |
| Webhook URL | `https://your-fspbx-hostname/webhook/voxutel/sms` |
| Authentication | **HMAC SHA256** |
| Signature header | `X-Voxutel-Signature` |
| Timestamp header | `X-Voxutel-Timestamp` |
| Signing secret | A strong random secret shared with FS PBX through `VOXUTEL_INBOUND_API_KEY`. |

Replace the hostname with your public FS PBX hostname. Generate a random secret, for example with `openssl rand -hex 32`, and paste that exact value into both the portal's **Signing secret** field and `VOXUTEL_INBOUND_API_KEY` in your `.env`. Save the webhook settings, then apply the FS PBX configuration changes below.

Both SMS and MMS use this webhook, despite the `/sms` suffix. Your FS PBX server must be publicly reachable over HTTPS.

FS PBX requires both `X-Voxutel-Timestamp` and `X-Voxutel-Signature` headers. It verifies an HMAC-SHA256 signature of the timestamp, a period, and the raw request body using the broker signing key. The timestamp must be within five minutes of the server's clock. Requests with missing keys, missing headers, invalid signatures, or expired timestamps are rejected.

## Apply configuration changes

From your FS PBX installation directory, run these commands as the application service user:

```bash
php artisan config:cache
php artisan horizon:terminate
```

`config:cache` loads the updated `.env` values into Laravel's configuration cache. `horizon:terminate` lets Horizon's process manager restart the workers with the updated configuration. Ensure the process manager is running. If your installation uses standard Laravel queue workers instead of Horizon, use `php artisan queue:restart` for the worker restart.

Repeat these steps whenever either key or the broker URL changes.

## Enable messaging for a phone number

1. Confirm the number is enabled for messaging with Voxutel.
2. In FS PBX, open **Advanced → Message Settings**.
3. Add or edit the phone number and choose **Voxutel** as its provider.
4. Assign the extensions that should use the number. Users can access conversations in the FS PBX dashboard and, when configured, their mobile app.
5. Optionally assign an email address for read-only notifications. Replies must be sent through the dashboard or mobile app; replying to the notification email does not send an SMS or MMS.
6. Save, then test an outbound SMS and an inbound reply.

Repeat this setup for each phone number you want to use for messaging. Horizon or another Laravel queue worker must be running to process messages.

Once configured, messages follow these paths:

- **Inbound:** Sender → Voxutel → FS PBX → assigned users in the dashboard or mobile app, with optional email notifications.
- **Outbound:** Dashboard or mobile app → FS PBX → Voxutel → recipient.

## MMS support

Voxutel supports two-way MMS through the same provider configuration as SMS. There is no separate MMS API key or webhook URL.

Before testing picture messages:

1. Confirm your Voxutel number is enabled for MMS.
2. Configure S3-compatible media storage using the [S3 Configuration for Messages](/docs/configuration/messaging/s3-config-for-messages/) guide.
3. Complete the phone number and extension assignments in **Advanced → Message Settings**.
4. Send a picture message from the dashboard or configured mobile app, then send a picture back to the number to verify inbound delivery.

Users can receive picture messages, reply with supported media attachments, and keep SMS and MMS history together in the same conversation. Outbound MMS uses the same `/publishMessages` endpoint, and inbound MMS uses `/webhook/voxutel/sms`.

## Troubleshooting

- **Outbound authentication fails:** Check the portal user's **Voxutel SMS API** role and `VOXUTEL_API_KEY`, then rebuild the config cache and restart workers.
- **Outbound requests fail:** Confirm `VOXUTEL_MESSAGE_BROKER_URL` is `https://api.voxutel.net/msgbroker/rest/publishMessages` and the sending number is enabled for messaging.
- **Inbound requests are rejected:** Check that the broker signing key matches `VOXUTEL_INBOUND_API_KEY`, both signature headers are present, and the server clock is correct.
- **Messages do not appear:** Check the number's **Message Settings** entry, assigned extensions, and queue worker status.
- **SMS works but MMS fails:** Confirm the number is enabled for MMS, S3-compatible message storage is configured, and the media URLs can be retrieved by the receiving service.
