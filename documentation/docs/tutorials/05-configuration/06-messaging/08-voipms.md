---
id: voipms
title: VoIP.ms SMS Provider Configuration
slug: /configuration/messaging/voipms
description: Configure VoIP.ms for SMS and MMS in FS PBX.
sidebar_position: 7
---

# VoIP.ms SMS Provider Configuration

FS PBX provides two-way SMS and MMS support via **VoIP.ms**, including inbound message handling, outbound delivery, and provider authentication. This guide explains how to configure your VoIP.ms credentials and enable messaging routing to extensions.

---

## 1. Required Environment Variables

Add the required environment variables to your `.env` file:

```env
VOIPMS_API_USERNAME=your_voipms_username
VOIPMS_API_PASSWORD=your_api_password
````

### Variable Description

| Variable                | Purpose                                                                               |
| ----------------------- | ------------------------------------------------------------------------------------- |
| **VOIPMS_API_USERNAME** | Your VoIP.ms API username used to authenticate requests.                              |
| **VOIPMS_API_PASSWORD** | Your VoIP.ms API password used together with the username for secure provider access. |

## 2. Apply Configuration Changes

After updating your `.env` file, run:

```bash
php artisan config:cache
```

This ensures FS PBX loads the updated provider settings.

---

## 3. Webhook Setup

VoIP.ms delivers inbound SMS and MMS messages to FS PBX through a webhook.

To ensure FS PBX receives and processes incoming messages, configure your VoIP.ms webhook URL (POST Request in a JSON Format) as:

```text
https://your-domain/webhook/voipms/sms
```

### Where to Configure in VoIP.ms

1. Log in to your VoIP.ms account.

2. Modify your DID to enable SMS/MMS.

3. Set the inbound webhook URL (POST Request in a JSON Format) to:

   ```text
   https://your-domain/webhook/voipms/sms
   ```

4. Save the changes.

---

## 4. Enable SMS on a Phone Number in FS PBX

After credentials and webhook configuration are complete:

1. Go to **Advanced → Message Settings**
2. Add or edit an SMS-enabled number
3. Select **VoIP.ms** as the provider
4. Assign an extension for message delivery
5. Optionally assign an email address for read-only email notifications

When configured:

* Inbound SMS/MMS → VoIP.ms → FS PBX → Assigned Extension
* Replies → FS PBX → VoIP.ms → Original Sender

---

## 5. MMS Support

If your VoIP.ms number supports MMS, FS PBX can also process media attachments sent through the same messaging flow.

To use MMS media storage, S3-compatible storage must already be configured in your system. See the [S3 Configuration for Messages](/docs/configuration/messaging/s3-config-for-messages/) guide.

This allows users to:

* receive inbound picture messages in the dashboard or mobile app
* reply to supported MMS conversations
* keep SMS and MMS history together in the same conversation thread

---

## Summary

To complete VoIP.ms messaging integration:

1. Add the `.env` variables:

   * `VOIPMS_API_USERNAME`
   * `VOIPMS_API_PASSWORD`

2. Run `php artisan config:cache`

3. Configure the VoIP.ms webhook to `/webhook/voipms/sms`

4. Enable messaging for the number in **Message Settings**

Your VoIP.ms numbers are now ready for two-way SMS and MMS in FS PBX.

```