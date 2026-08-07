---
id: twilio
title: Twilio SMS Provider Configuration
slug: /configuration/messaging/twilio
description: Configure Twilio for SMS and MMS in FS PBX.
sidebar_position: 9
---

Twilio SMS Provider Configuration
=================================

FS PBX provides two-way SMS and MMS support via **Twilio**, including inbound message handling, outbound delivery, delivery-status callbacks, and webhook signature verification for security. This guide explains how to configure your Twilio credentials and enable SMS routing to extensions.

* * * * *

1\. Prerequisites
-----------------

-   A Twilio account with an SMS-capable phone number.

-   **US numbers: A2P 10DLC registration is required.** Twilio blocks outbound SMS/MMS from unregistered US local (10-digit) numbers. Register a **Brand** and **Campaign** in the Twilio Console under **Messaging → Regulatory Compliance**, and attach your number to the campaign before sending. Toll-free numbers require **Toll-Free Verification** instead.

* * * * *

2\. Required Environment Variables
----------------------------------

Add **all** required environment variables to your `.env` file:

```
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_BASE_URL=https://api.twilio.com/2010-04-01
```

### Variable Description

| Variable | Purpose |
| --- | --- |
| **TWILIO_ACCOUNT_SID** | Your Twilio Account SID (starts with `AC`). Used to authenticate API requests and download MMS media. |
| **TWILIO_AUTH_TOKEN** | Your Twilio Auth Token. Used for outbound API authentication **and** to verify inbound webhook signatures. |
| **TWILIO_BASE_URL** | API endpoint for Twilio requests. Default: `https://api.twilio.com/2010-04-01`. |

You can find both values on the Twilio Console dashboard under **Account Info**.

3\. Apply Configuration Changes
-------------------------------

After updating your `.env` file, run:

`php artisan config:cache`

This is required for FS PBX to load updated provider settings.

* * * * *

4\. Webhook Setup (Required)
----------------------------

Twilio delivers inbound SMS/MMS messages and delivery-status events through webhooks.\
To ensure FS PBX receives and processes incoming messages, set the webhook URL for your Twilio phone number to:

`https://your-domain/webhook/twilio/sms`

### Where to Configure in Twilio

1.  Log in to the **Twilio Console**

2.  Go to **Phone Numbers → Manage → Active Numbers**

3.  Select your phone number

4.  Under **Messaging Configuration**, set:

    -   **A message comes in** → Webhook → `https://your-domain/webhook/twilio/sms` → HTTP POST

5.  Save

If your number is attached to a **Messaging Service**, configure the inbound webhook on the Messaging Service instead (**Messaging → Services → your service → Integration → Send a webhook**).

### Delivery-Status Callbacks (Optional)

To have outbound messages update to **delivered** or **failed** in FS PBX, set the **Delivery status callback** on your number or Messaging Service to the same URL:

`https://your-domain/webhook/twilio/sms`

### Webhook Signature Validation

FS PBX validates the `X-Twilio-Signature` header on every incoming webhook using your `TWILIO_AUTH_TOKEN`.

-   If the signature is invalid

-   If the auth token doesn't match

-   Or if the signature header is missing

...FS PBX will reject the request automatically.

This helps ensure **only authentic requests from Twilio** are processed.

* * * * *

5\. Enable SMS on a Phone Number in FS PBX
------------------------------------------

After credentials and webhook configuration are complete:

1.  Go to **Advanced → Message Settings**

2.  Add or edit an SMS-enabled number

3.  Select **Twilio** as the provider

4.  Assign an **extension** for mobile app delivery

5.  Optionally assign an **email address** for read-only email notifications

When configured:

-   Inbound SMS → Twilio → FS PBX → Extension's Mobile App

-   Replies → FS PBX → Twilio → Original Sender

* * * * *

## 6. MMS Support

If your Twilio number supports MMS, FS PBX can also process media attachments sent through the same messaging flow. Inbound media is downloaded from Twilio using your account credentials and stored in your configured object storage.

To use MMS media storage, S3-compatible storage must already be configured in your system. See the [S3 Configuration for Messages](/docs/configuration/messaging/s3-config-for-messages/) guide.

This allows users to:

* receive inbound picture messages in the mobile app
* reply to supported MMS conversations
* keep SMS and MMS history together in the same conversation thread

---

Summary
-------

For full Twilio SMS integration:

1.  Complete A2P 10DLC registration (US numbers)

2.  Add `.env` variables (`TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`)

3.  Run `php artisan config:cache`

4.  Configure Twilio webhooks to `/webhook/twilio/sms`

5.  Enable SMS for each number in **Message Settings**

Your Twilio numbers are now ready for reliable two-way SMS.
