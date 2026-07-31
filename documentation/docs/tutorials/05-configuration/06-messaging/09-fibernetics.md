---
id: fibernetics
title: Fibernetics SMS Provider Configuration
slug: /configuration/messaging/fibernetics
description: Configure Fibernetics HTTP SMS and MM7 MMS in FS PBX.
sidebar_position: 9
unlisted: true
---

# Fibernetics SMS and MMS Provider Configuration

FS PBX supports two-way messaging through Fibernetics:

- SMS is sent through the Fibernetics HTTPS SMS gateway.
- MMS is sent and received through MM7.
- Inbound SMS and MMS use the same public FS PBX endpoint.
- Inbound requests are restricted to the Fibernetics source networks you configure.

Fibernetics provisions SMS and MMS separately. Make sure both routes are active before testing MMS.

## Before you begin

Ask Fibernetics for:

- an HTTP SMS username and password
- an MM7 username and password
- confirmation that your FS PBX public outbound IP is allowed to send
- the source IP addresses or CIDR networks used for inbound SMS
- the source IP addresses or CIDR networks used for inbound MM7
- confirmation that each sending number or its associated NNID is authorized for Fibernetics messaging

The SMS and MM7 credentials may be different. Do not use the SMS credentials for MM7 unless Fibernetics explicitly confirms they are the same.

Your FS PBX server must also be publicly reachable over HTTPS, and Horizon or another Laravel queue worker must be running.

## Configure FS PBX

Edit the FS PBX `.env` file:

```bash
nano /var/www/fspbx/.env
```

Add the credentials and inbound source networks supplied by Fibernetics:

```dotenv
FIBERNETICS_SMS_USERNAME=your-http-sms-username
FIBERNETICS_SMS_PASSWORD=your-http-sms-password

FIBERNETICS_MM7_USERNAME=your-mm7-username
FIBERNETICS_MM7_PASSWORD=your-mm7-password
FIBERNETICS_MM7_SUBJECT=
FIBERNETICS_MM7_VERIFY_SSL=false

FIBERNETICS_WEBHOOK_IPS=74.205.x.x/29,74.205.x.x/29
FIBERNETICS_MMS_WEBHOOK_IPS=203.0.x.x/32
```

Replace `FIBERNETICS_MMS_WEBHOOK_IPS` with the actual source address or CIDR range Fibernetics provides. Confirm the SMS networks with Fibernetics as well, because provider network assignments can change.

The following optional settings already have application defaults and normally do not need to be added:

```dotenv
FIBERNETICS_SMS_URL=https://smshttpsgw.fibernetics.ca/cgi-bin/sendsms
FIBERNETICS_MM7_URL=https://mmsout.mms.fibernetics.ca:8091/mm7
FIBERNETICS_MM7_VERSION=6.8.0
FIBERNETICS_TIMEOUT=60
```

`FIBERNETICS_MM7_SUBJECT` is optional. Leave it blank if image-only MMS messages should not display a fixed title. Text entered with an outbound MMS is sent as its subject.

`FIBERNETICS_MM7_VERIFY_SSL` controls certificate verification for the MM7 connection. Keep the value required by your Fibernetics route and certificate configuration.

After changing `.env`, rebuild the cached configuration and restart the long-running queue workers:

```bash
php artisan config:cache
php artisan horizon:terminate
```

## Configure inbound SMS

Give Fibernetics this callback URL, replacing the hostname with your public FS PBX hostname:

```text
https://your-fspbx-hostname/webhook/fibernetics/sms
```

Fibernetics sends inbound SMS as an HTTP GET request. FS PBX accepts GSM, Windows-1252, and UTF-16BE message data and normalizes phone numbers to E.164 format.

The request is accepted only when its source address matches `FIBERNETICS_WEBHOOK_IPS`.

## Configure inbound MMS

Give Fibernetics the same endpoint without the SMS query string:

```text
https://your-fspbx-hostname/webhook/fibernetics/sms
```

Ask Fibernetics to deliver inbound MM7 `DeliverReq` requests to this URL as `multipart/related` HTTPS POST requests.

The MM7 receiver:

1. validates the source address against `FIBERNETICS_MMS_WEBHOOK_IPS`
2. records a safe webhook summary
3. uploads media directly to the account's S3-compatible storage
4. queues the message for normal FS PBX delivery
5. returns an MM7 response to Fibernetics

MMS attachments are not intentionally persisted on the PBX hard drive. Configure S3-compatible storage before testing MMS. See [Set Up S3 Storage for Messages](/docs/configuration/messaging/s3-config-for-messages/).

If media cannot be stored, FS PBX returns a temporary MM7 processing failure so Fibernetics can retry the delivery.

## Enable the phone number

In FS PBX:

1. Go to **Advanced → Message Settings**.
2. Add or edit the Fibernetics messaging number.
3. Select **Fibernetics** as the provider.
4. Assign the destination extension.
5. Optionally add an email address for read-only notifications.
6. Save the settings.

Use an E.164 number such as `+14165550100`. FS PBX URL-encodes the plus sign when it sends SMS through the Fibernetics gateway.

## Test the integration

Test each direction separately:

1. Send an outbound SMS from FS PBX and confirm it arrives.
2. Reply from the mobile phone and confirm the inbound SMS appears in **Messages**.
3. Send an outbound MMS with one attachment and confirm the image arrives.
4. Send an MMS back to the FS PBX number and confirm its media appears in the conversation.
5. Open **Logs → Inbound Webhooks** and look for entries named `fibernetics_messaging`.

FS PBX currently sends the first attachment in an outbound Fibernetics MMS. If a message contains additional attachments, they are not included in the MM7 submission.

## Troubleshooting

### Outbound SMS is rejected or never arrives

- Confirm the HTTP SMS username and password.
- Confirm the source number or its NNID is authorized by Fibernetics.
- Use E.164 source and destination numbers.
- Confirm Fibernetics has allowed the server's public outbound IP.

### Outbound MMS returns `Authorisation Required`

- Confirm the MM7 username and password, not the HTTP SMS credentials.
- Confirm Fibernetics has activated the MM7 route for that exact account.
- Confirm `FIBERNETICS_MM7_URL` points to the route supplied by Fibernetics.
- Rebuild the configuration cache and restart Horizon after changing credentials.

### No inbound webhook entry appears

- Confirm Fibernetics is sending to the correct HTTPS callback URL.
- Confirm the actual request source is included in the appropriate SMS or MMS IP list.
- If FS PBX is behind a reverse proxy, confirm Laravel resolves the original client IP correctly.
- Check that the selected date range on **Logs → Inbound Webhooks** includes the current date.

### A webhook entry exists but no message appears

- Confirm Horizon or another queue worker is running.
- Confirm the destination number exists in **Advanced → Message Settings**.
- Confirm the number is assigned to the correct extension.
- Check the Laravel log and failed jobs for a processing error.

### MMS reports an S3 storage error

Confirm the destination account has valid S3-compatible storage settings and that FS PBX can upload, read, and verify objects in the configured bucket.

## Temporary debug logging

For additional messaging diagnostics, temporarily enable:

```dotenv
MESSAGING_WEBHOOK_DEBUG=true
```

Then apply the change:

```bash
php artisan config:cache
php artisan horizon:terminate
```

Review the Laravel log while sending one message in each direction. Disable debug logging after testing to avoid unnecessary log volume.

## Summary

To enable Fibernetics messaging:

1. Configure the separate HTTP SMS and MM7 credentials.
2. Add the Fibernetics inbound SMS and MMS source networks.
3. Configure the SMS GET callback and MM7 POST receiver.
4. Configure S3-compatible storage for MMS.
5. Select Fibernetics for the phone number in **Message Settings**.
6. Test inbound and outbound SMS and MMS independently.
