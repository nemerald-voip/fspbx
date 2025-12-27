---
id: vm-sms-notifications
title: Voicemail SMS Notifications
slug: /configuration/voicemail-settings/sms-notifications/
sidebar_position: 2
---

Voicemail SMS Notifications
=================

FS PBX can automatically send an SMS notification each time a new voicemail is received.
Notifications can include caller details, timestamps, message length, transcription, and optionally the voicemail audio file as an attachment.


-   **Multiple SMS Providers Supported:**

    -   Bandwidth

    -   Sinch

    -   Commio

    -   Telnyx

    -   ClickSend

* * * * *

How to Enable
-------------

### 1\. Update Default Settings

Navigate to **Default Settings** → **Voicemail** and update or set the following:

-   **`sms_notifications_enabled`**\
    Toggles the SMS notification feature on or off. When disabled, no voicemail SMS alerts will be sent regardless of other settings.

-   **`sms_notification_from_number`**\
    The default **outgoing SMS number** used to send voicemail notifications.\
    ⚠️ **Important:** The outbound SMS number you configure in Step 3 **must match this exact number**, otherwise SMS notifications will not be sent.

-   **`sms_notification_text`**\
    The template text used for voicemail notification messages.

-   **`sms_notification_include_transcription`**\
    Determines whether voicemail transcription text should be included in the SMS notification (if available).

### 2\. Add Mobile Number to Mailbox

1.  Go to **Voicemails** and select the desired mailbox.

2.  Click **Edit**.

3.  In the **Advanced** section, locate **Mobile Number to Receive Voicemail Notifications**.

4.  Enter the number that should receive SMS alerts and save.

### 3\. Add Your Outbound SMS Number

1.  Navigate to **Advanced** → **Message Settings**.

2.  Add a new phone number --- **this must be the same number set in `sms_notification_from_number`**.

3.  Choose your SMS provider (Bandwidth, Sinch, Commio, Telnyx, or ClickSend).

4.  Save.

* * * * *

You're All Set!
---------------

Once configured, FS PBX will automatically send SMS notifications --- and optionally voicemail transcriptions --- to users whenever they receive a new voicemail, using your selected SMS provider and notification template.