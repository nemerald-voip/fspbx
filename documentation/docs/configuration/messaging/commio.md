---
id: commio
title: Commio (ThinQ) SMS Provider Configuration
slug: /configuration/messaging/commio
sidebar_position: 3
---

Commio (ThinQ) SMS Provider Configuration
=========================================

FS PBX supports two-way SMS messaging through **Commio** (formerly ThinQ). This guide explains how to configure your Commio credentials, set up webhook security, and enable SMS on individual phone numbers.

* * * * *

1\. Required Environment Variables
----------------------------------

Add the following variables to your `.env` file:

```
THINQ_ACCOUNT_ID=
THINQ_USERNAME=
THINQ_TOKEN=
COMMIO_WEBHOOK_SECRET=
```

### Description of Each Variable

| Variable | Purpose |
| --- | --- |
| **THINQ_ACCOUNT_ID** | Your Commio/ThinQ account ID used for API requests. |
| **THINQ_USERNAME** | API username assigned in the Commio dashboard. |
| **THINQ_TOKEN** | API token or password paired with the username. |
| **COMMIO_WEBHOOK_SECRET** | Secret string used to validate inbound webhook signatures. |

> **Note:** Commio uses webhooks extensively for inbound SMS and status updates, and FS PBX validates these requests for security using the webhook secret.

* * * * *

2\. Apply Configuration Changes
-------------------------------

Whenever you modify `.env`, you must refresh Laravel's cached configuration:

`php artisan config:cache`

If you skip this step, FS PBX will use old values and SMS functionality may fail.

* * * * *

3\. Webhook Setup (Required)
----------------------------

Commio delivers inbound messages and status updates via webhooks.\
To enable inbound SMS in FS PBX, set your Commio messaging webhooks to:

`https://your-domain/webhook/commio/sms`

### Where to Configure Webhooks in Commio

1.  Log in to the **Commio (ThinQ) dashboard**

2.  Navigate to **Messaging → SMS Settings** or **Numbers → SMS**

3.  For each SMS-enabled number or application, set:

    -   **Inbound Message URL** → `https://your-domain/webhook/commio/sms`

    -   **Delivery Receipt URL** (optional) → same endpoint or your preferred one

### Webhook Signature Validation

FS PBX can optionally validate Commio webhook signatures using:

`COMMIO_WEBHOOK_SECRET=`

If you enter a secret here:

-   FS PBX will **reject** inbound requests without a valid signature

-   This prevents spoofed or unauthorized webhook calls

* * * * *

4\. Enable SMS on a Phone Number in FS PBX
------------------------------------------

Once provider credentials and webhooks are configured:

1.  Go to **Advanced → Message Settings**

2.  Add or edit a phone number

3.  Select **Commio** as the SMS provider

4.  Assign an **extension**

    -   The associated mobile app will receive inbound SMS

5.  Optionally assign an **email** for one-way email notifications

After saving, SMS messages sent to that number will:

-   Route through Commio to FS PBX

-   Be delivered to the selected extension's mobile app

-   Allow the user to reply directly through the app

* * * * *

Summary
-------

To configure Commio SMS support in FS PBX:

1.  Add credentials to `.env`

2.  Run `php artisan config:cache`

3.  Configure Commio webhooks to point to `/webhook/commio/sms`

4.  Assign Commio to the desired phone numbers in Message Settings

Your Commio-enabled numbers are now ready for two-way SMS messaging.