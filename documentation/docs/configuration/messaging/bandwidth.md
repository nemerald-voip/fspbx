---
id: bandwidth
title: Bandwidth SMS Provider Configuration
slug: /configuration/messaging/bandwidth
sidebar_position: 2
---

Bandwidth SMS Provider Configuration
====================================

FS PBX supports full two-way SMS messaging through **Bandwidth**, including inbound message delivery and outbound replies from the mobile app. This guide explains how to configure Bandwidth credentials, enable SMS, and set up the required webhook endpoint.


* * * * *

1\. Required Environment Variables
----------------------------------

Add the following values to your `.env`:

```
BANDWIDTH_ACCOUNT_ID=
BANDWIDTH_API_TOKEN=
BANDWIDTH_API_SECRET=
BANDWIDTH_MESSAGE_BASE_URL=https://messaging.bandwidth.com/api/v2
BANDWIDTH_APPLICATION_ID=
```

### Variable Descriptions

| Variable | Purpose |
| --- | --- |
| **BANDWIDTH_ACCOUNT_ID** | Your Bandwidth account identifier. |
| **BANDWIDTH_API_TOKEN** | API token used to authenticate API requests. |
| **BANDWIDTH_API_SECRET** | Secret key paired with the API token. |
| **BANDWIDTH_MESSAGE_BASE_URL** | Base URL for Bandwidth messaging API. Default: `https://messaging.bandwidth.com/api/v2` |
| **BANDWIDTH_APPLICATION_ID** | Messaging Application ID assigned in your Bandwidth portal. |

* * * * *

2\. Apply Configuration Changes
-------------------------------

After updating your `.env`, you **must** refresh Laravel's configuration cache:

`php artisan config:cache`

Skipping this step will cause FS PBX to continue using outdated configuration values.

* * * * *

3\. Webhook Setup (Required)
----------------------------

Bandwidth delivers inbound SMS through webhooks.\
To ensure FS PBX processes inbound messages correctly, **all Bandwidth message webhooks must be pointed to**:

`https://your-domain/webhook/bandwidth/sms`

### Where to Configure This in Bandwidth

1.  Sign in to the **Bandwidth Dashboard**

2.  Go to **Applications** → select your messaging application

3.  Under **Messaging**, set:

    -   **Inbound Message Callback URL** → `https://your-domain/webhook/bandwidth/sms`

    -   **Status Callback URL** (optional) → same endpoint, unless you prefer a different one

Ensure the URL is publicly accessible.

* * * * *

4\. Enable SMS for Your Phone Numbers in FS PBX
-----------------------------------------------

Once Bandwidth credentials and webhooks are configured:

1.  Go to **Advanced → Message Settings**

2.  Add or edit a phone number

3.  Select **Bandwidth** as the SMS provider

4.  Assign an **extension** (required for mobile app delivery)

5.  Optionally assign an **email address** for email-only notifications

Inbound SMS will now be:

-   Received by Bandwidth

-   Posted to the FS PBX webhook

-   Delivered to the extension's mobile app

-   Replyable through the app using Bandwidth as the outbound provider

* * * * *

Summary
-------

To fully enable Bandwidth SMS in FS PBX:

1.  Add API credentials to `.env`

2.  Run `php artisan config:cache`

3.  Configure Bandwidth webhooks to `webhook/bandwidth/sms`

4.  Select Bandwidth for the appropriate phone numbers in **Message Settings**

Your system is now ready for two-way SMS messaging using Bandwidth.