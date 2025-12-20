---
id: vm-email-notifications
title: Voicemail Email Notifications
slug: /configuration/voicemail-settings/email-notifications/
sidebar_position: 1
---

# Voicemail Email Notifications

FS PBX can automatically send an email notification each time a new voicemail is received.\
Notifications can include caller details, timestamps, message length, transcription, and optionally the voicemail audio file as an attachment.

* * * * *

🧩 Overview
-----------

When a caller leaves a voicemail, FS PBX can immediately notify the mailbox owner by email.\
This makes it easy for users to stay informed --- even when they're away from their desk phone or softphone.

By default, **new FS PBX installations** already use the correct background job system to handle these notifications.\
If you upgraded from FusionPBX or an older FS PBX version, you may need to **update one setting** (explained below).

* * * * *

⚙️ Step 1 -- Verify Mail Settings
--------------------------------

Before enabling voicemail notifications, make sure your email settings are correctly configured in your `.env` file.\
If not, follow the [Email Settings Guide](tutorials/04-getting-started/01-email-settings.md) first.
 
* * * * *

📞 Step 2 -- Enable Notifications for an Extension
-------------------------------------------------

1.  Navigate to the **Extensions** page.

2.  Edit the extension you want to enable notifications for.

3.  Make sure the extension has a valid **Email Address** under the **Basic Info** tab.

4.  Then select the **Voicemail** tab.

Set the following options:

| Field | Description |
| --- | --- |
| **Status** | ✅ Enable voicemail for this extension. |
| **Voicemail Transcription** | Convert voicemail messages to text using AI-powered transcription. |
| **Automatically Delete Voicemail After Email** | Remove the voicemail from storage once the email has been successfully sent. |
| **Attach File to Email Notifications** | Include the voicemail audio file as an attachment in the email notification. |

Click **Save** when done.

* * * * *

⚙️ Step 3 -- Check Voicemail Queue Strategy
------------------------------------------

Voicemail delivery is handled by the job queue system.\
FS PBX supports two queue strategies: **legacy** and **modern**.

-   ✅ **Modern** (recommended) -- Uses Laravel queues for asynchronous and reliable delivery

-   ⚠️ **Legacy** -- (depreciated) Sends directly from the dialplan (synchronous, less reliable, no retries)

If your system was upgraded from FusionPBX, verify the following setting:

1.  Go to **Advanced → Default Settings**

2.  Search for `voicemail_queue_strategy`

3.  Make sure its **Value** is set to `modern`

4.  If not, edit the setting, save, and navigate to **Status -> SIP Status** and click on **Flush cache**.

> 💡 **Note:**\
> All new FS PBX installations already use `modern` by default.\
> Only older systems may still show `legacy`.

* * * * *

🧪 Step 4 -- Test the Notification
---------------------------------

To verify everything is working:

1.  Call your extension and leave a voicemail

2.  Wait a few seconds --- you should receive an email with caller details

3.  If attachments are enabled, the audio file will be included

4.  If transcriptions are enabled, the transcription will also be included

If you don't receive an email:

-   Check **`storage/logs/laravel.log`** for errors

-   Ensure your mail configuration is working (see Email Settings)

-   Restart the queues

```
php artisan queue:restart
```

* * * * *



🧾 Summary
----------

You have now:

-   Enabled voicemail notifications for your extensions

-   Verified your mailer configuration

-   Ensured your voicemail queue strategy is set to **modern**

With these settings in place, FS PBX will automatically email users whenever a new voicemail arrives --- ensuring no important message is missed.