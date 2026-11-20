---
id: voip-innovations
title: VoIP Innovations
slug: /configuration/messaging/voip-innovations
---

# VoIP Innovations

FS PBX supports outbound and inbound SMS and MMS through VoIP Innovations (VI).
VI sends successful SMS delivery receipts back to FS PBX using the outbound
message UUID.

## Configure the test server

1. Enable PHP's SOAP extension for the PHP runtime that runs the messaging
   queue. Configure S3-compatible message storage for the account receiving
   MMS.
2. Configure the VI API account for the test server's public egress IP. Add the
   following settings to the application's environment.

   ```dotenv
   VI_SMS_USERNAME=
   VI_SMS_PASSWORD=
   VI_INBOUND_WEBHOOK_KEY=
   ```

   Use a new random 64-character hexadecimal webhook key. On the server,
   `openssl rand -hex 32` generates one.
3. Reload the application configuration and restart the managed messaging
   workers. Add an enabled lab DID under **Advanced → Message Settings** and
   select **VoIP Innovations**.
4. Configure the lab DID's VI **API POST** destination. Use the test server's
   HTTPS hostname and replace the placeholder with its webhook key.

   ```text
   https://pbx.example.com/webhook/voipinnovations/sms/WEBHOOK_KEY
   ```

   The URL is a credential. Redact it in request logging and monitoring at
   the web server and upstream proxy. The stored webhook record uses a redacted
   URL. Its payload still contains message content and inline MMS media, so
   apply your normal messaging data retention and access controls.

## Verify messaging

- Send an SMS to and from a handset. Confirm the inbound message reaches the
  configured account and the outbound message advances to `delivered`.
- Send one small JPEG with a caption to the handset. Outbound MMS currently
  supports one JPEG with a default limit of 1 MiB.
- Reply with a picture. Confirm the MMS appears with its attachment in the
  conversation. If the text appears alone, compare expected and stored media
  counts and check the account's object storage configuration.
- Verify a callback with an incorrect key returns HTTP 403. Verify a repeated
  inbound UUID produces a single conversation message.

Use the previous VI callback destination if you need to route the lab DID back
after testing.
