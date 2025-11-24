---
id: sinch
title: Sinch SMS Provider Configuration
slug: /configuration/messaging/sinch
sidebar_position: 4
---

Sinch SMS Provider Configuration
================================

FS PBX supports two-way SMS through **Sinch**, using the Sinch (Inteliquent) message broker API for outbound messages and a secure inbound webhook for receiving messages. This guide explains how to configure your Sinch credentials, set up inbound security, and enable SMS for phone numbers.

* * * * *

1\. Required Environment Variables
----------------------------------

Add the following environment variables to your `.env`:

```
SINCH_AUTH_ID=
SINCH_API_KEY=
SINCH_MESSAGE_BROKER_URL=https://messagebroker.inteliquent.com/msgbroker/rest
SINCH_INBOUND_API_KEY=
```

### Description of Each Variable

| Variable | Purpose |
| --- | --- |
| **SINCH_AUTH_ID** | Sinch authentication ID used for outbound SMS API requests. |
| **SINCH_API_KEY** | API key paired with the Auth ID. |
| **SINCH_MESSAGE_BROKER_URL** | Endpoint used for sending outbound SMS messages. Default: `https://messagebroker.inteliquent.com/msgbroker/rest` |
| **SINCH_INBOUND_API_KEY** | Secret key used to validate inbound Sinch webhook requests. Prevents spoofing. |

> **Note:** Sinch requires custom authentication for outbound delivery and a secure API key for inbound webhook validation.

* * * * *

2\. Apply Configuration Changes
-------------------------------

After entering your `.env` values, refresh the Laravel config cache:

`php artisan config:cache`

If you skip this step, FS PBX will not load your updated Sinch configuration.

* * * * *

3\. Webhook Setup (Required)
----------------------------

Sinch delivers inbound SMS messages and status callbacks via webhook.\
To ensure FS PBX receives inbound messages correctly, configure Sinch to send all SMS webhooks to:

`https://your-domain/webhook/sinch/sms`

### Sinch Inbound Webhook Security

FS PBX uses the environment variable:

`SINCH_INBOUND_API_KEY=`

to verify inbound webhook requests.

If this value is set:

-   Sinch **must** include the same key in its webhook request header (typically `X-API-Key` or similar)

-   FS PBX will reject requests with mismatched or missing keys

This ensures only messages from Sinch are accepted.

### Where to Configure in Sinch

Depending on your Sinch/Inteliquent account:

1.  Log in to your **Sinch/Inteliquent portal**

2.  Navigate to:

    -   **Messaging → SMS Settings**, or

    -   **Numbers → SMS Configuration**, or

    -   **Message Broker Settings**

3.  Set:

    -   **Inbound Message URL** → `https://your-domain/webhook/sinch/sms`

    -   **Status Callback URL** → same endpoint (optional)

4.  Ensure your inbound secret/API key matches `SINCH_INBOUND_API_KEY`

* * * * *

4\. Enable SMS on a Phone Number in FS PBX
------------------------------------------

Once Sinch is configured and webhooks are set:

1.  Navigate to **Advanced → Message Settings**

2.  Add or edit the phone number you want to SMS-enable

3.  Select **Sinch** as the provider

4.  Assign an **extension** (recommended)

    -   Inbound messages will appear in the FS PBX mobile app for that extension

5.  Optionally assign an **email address** for read-only email notifications

From this point:

-   Inbound messages → Sinch → FS PBX → Mobile App

-   Outbound replies → Mobile App → Sinch → Recipient

* * * * *

Summary
-------

To configure Sinch SMS support in FS PBX:

1.  Add credentials to `.env`

2.  Run `php artisan config:cache`

3.  Configure Sinch webhooks to point to `/webhook/sinch/sms`

4.  SMS-enable each phone number in **Message Settings** and select Sinch

Your Sinch-enabled numbers are now fully capable of two-way SMS messaging.