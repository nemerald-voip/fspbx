---
id: telnyx
title: Telnyx SMS Provider Configuration
slug: /configuration/messaging/telnyx
sidebar_position: 5
---

Telnyx SMS Provider Configuration
=================================

FS PBX provides full two-way SMS support via **Telnyx**, including inbound message handling, outbound delivery, and webhook signature verification for security. This guide explains how to configure your Telnyx credentials and enable SMS routing to extensions.

* * * * *

1\. Required Environment Variables
----------------------------------

Add **all** required environment variables to your `.env` file:

```
TELNYX_PUBLIC_KEY=
TELNYX_API_KEY=
TELNYX_BASE_URL=https://api.telnyx.com/v2
```

### Variable Description

| Variable | Purpose |
| --- | --- |
| **TELNYX_PUBLIC_KEY** | Public key used to verify Telnyx webhook signatures. Ensures inbound webhooks are legitimate. |
| **TELNYX_API_KEY** | Private API key used to send outbound SMS messages. |
| **TELNYX_BASE_URL** | API endpoint for Telnyx SMS requests. Default: `https://api.telnyx.com/v2`. |


2\. Apply Configuration Changes
-------------------------------

After updating your `.env` file, run:

`php artisan config:cache`

This is required for FS PBX to load updated provider settings.

* * * * *

3\. Webhook Setup (Required)
----------------------------

Telnyx delivers inbound SMS messages and status events through webhooks.\
To ensure FS PBX receives and processes incoming messages, set the webhook URL in your Telnyx Messaging Profile to:

`https://your-domain/webhook/telnyx/sms`

### Where to Configure in Telnyx

1.  Log in to the **Telnyx Portal**

2.  Go to **Messaging → Messaging Profiles**

3.  Select your messaging profile

4.  Under **Inbound Settings**, configure:

    -   **DLR Webhook URL** (optional)

    -   **Inbound Message Webhook URL** → `https://your-domain/webhook/telnyx/sms`

5.  Save

### Webhook Signature Validation

FS PBX will validate incoming Telnyx webhook signatures using the `TELNYX_PUBLIC_KEY` you entered in the `.env`.

-   If the signature is invalid

-   If the public key doesn't match

-   Or if the signature header is missing

...FS PBX will reject the request automatically.

This helps ensure **only authentic requests from Telnyx** are processed.

* * * * *

4\. Enable SMS on a Phone Number in FS PBX
------------------------------------------

After credentials and webhook configuration are complete:

1.  Go to **Advanced → Message Settings**

2.  Add or edit an SMS-enabled number

3.  Select **Telnyx** as the provider

4.  Assign an **extension** for mobile app delivery

5.  Optionally assign an **email address** for read-only email notifications

When configured:

-   Inbound SMS → Telnyx → FS PBX → Extension's Mobile App

-   Replies → FS PBX → Telnyx → Original Sender

* * * * *

Summary
-------

For full Telnyx SMS integration:

1.  Add `.env` variables (`TELNYX_PUBLIC_KEY`, `TELNYX_API_KEY`, etc.)

2.  Run `php artisan config:cache`

3.  Configure Telnyx webhooks to `/webhook/telnyx/sms`

4.  Enable SMS for each number in **Message Settings**

Your Telnyx numbers are now ready for reliable two-way SMS.