---
id: apidaze
title: Apidaze SMS Provider Configuration
slug: /configuration/messaging/apidaze
description: Configure Apidaze for SMS and MMS in FS PBX.
sidebar_position: 7
---

# Apidaze SMS Provider Configuration

FS PBX provides two-way SMS and MMS support via **Apidaze**, including inbound message handling, outbound delivery, and provider authentication. This guide explains how to configure your Apidaze credentials and enable messaging routing to extensions.

---

## 1. Required Environment Variables

Add the required environment variables to your `.env` file:

```env
APIDAZE_API_KEY=api_key
APIDAZE_API_SECRET=secret
```

### Variable Description

| Variable             | Purpose                                                                            |
| -------------------- | ---------------------------------------------------------------------------------- |
| **APIDAZE_API_KEY**    | Your Apidaze API key used to authenticate requests.                                |
| **APIDAZE_API_SECRET** | Your Apidaze API secret used together with the API key for secure provider access. |

## 2. Apply Configuration Changes

After updating your `.env` file, run:

```bash
php artisan config:cache
```

This ensures FS PBX loads the updated provider settings.

---

## 3. Webhook Setup

Apidaze delivers inbound SMS and MMS messages to FS PBX through a webhook.

To ensure FS PBX receives and processes incoming messages, configure your Apidaze webhook URL as:

```text
https://your-domain/webhook/apidaze/sms
```

### Where to Configure in Apidaze

1. Log in to your Apidaze account.

2. Open the messaging or webhook configuration area for your number or messaging application.

3. Set the inbound webhook URL to:

   ```text
   https://your-domain/webhook/apidaze/sms
   ```

4. Save the changes.

---

## 4. Enable SMS on a Phone Number in FS PBX

After credentials and webhook configuration are complete:

1. Go to **Advanced → Message Settings**
2. Add or edit an SMS-enabled number
3. Select **Apidaze** as the provider
4. Assign an extension for mobile app delivery
5. Optionally assign an email address for read-only email notifications

When configured:

* Inbound SMS/MMS → Apidaze → FS PBX → Extension’s Mobile App
* Replies → FS PBX → Apidaze → Original Sender

---

## 5. MMS Support

If your Apidaze number supports MMS, FS PBX can also process media attachments sent through the same messaging flow.

To use MMS media storage, S3-compatible storage must already be configured in your system. See the [S3 Configuration for Messages](/docs/configuration/messaging/s3-config-for-messages/) guide.

This allows users to:

* receive inbound picture messages in the mobile app
* reply to supported MMS conversations
* keep SMS and MMS history together in the same conversation thread

---

## Summary

To complete Apidaze messaging integration:

1. Add the `.env` variables:

   * `APIDAZE_API_KEY`
   * `APIDAZE_API_SECRET`
2. Run `php artisan config:cache`
3. Configure the Apidaze webhook to `/webhook/apidaze/sms`
4. Enable messaging for the number in **Message Settings**

Your Apidaze numbers are now ready for two-way SMS and MMS in FS PBX.
