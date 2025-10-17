# SMS Notifications for Voicemails

What's New
----------

-   **Automatic SMS Alerts:**\
    Instantly sends SMS notifications to users when they receive a new voicemail.

-   **Multiple SMS Providers Supported:**

    -   Bandwidth

    -   Sinch

    -   Commio

* * * * *

How to Enable
-------------

### 1\. Update Default Settings

1.  Navigate to **Default Settings** > **Voicemail** section.

2.  Update or set the following:

    -   `sms_notifications_enabled`

    -   `sms_notification_from_number`

    -   `sms_notification_text`

### 2\. Add Mobile Number to Mailbox

1.  Go to **Voicemails** and select the mailbox you want to enable SMS notifications for.

2.  Click **Edit**.

3.  In the **Advanced** section, find the field **"Mobile Number to Receive Voicemail Notifications"**.

4.  Enter the recipient's mobile number and save.

### 3\. Add Your Outbound SMS Number

1.  Navigate to **Advanced** > **Message Settings**.

2.  Add a new phone number---the same one you entered in step 1.

3.  Choose your SMS provider (**Bandwidth, Sinch, or Commio**) and save.

* * * * *

You're All Set!
---------------

With these steps complete, FS PBX will automatically send SMS notifications to users whenever they receive a new voicemail, using your chosen template and SMS provider.