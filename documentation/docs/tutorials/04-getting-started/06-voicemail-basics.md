---
id: voicemail-basics
title: Voicemail Basics
slug: /getting-started/voicemail-basics/
sidebar_position: 6
---

# Voicemail Basics

Set up an extension's mailbox, choose a numeric password, record a greeting, and leave a test message. You can then listen from a phone or the web interface and optionally receive messages by email.

This example uses **extension 100**. Substitute your own extension number throughout.

## Before you begin

- Select the account that owns the extension.
- [Create the extension](/docs/getting-started/create-your-first-extension/) and register a phone or softphone.
- Have a second registered extension available for a test call. No carrier or public phone number is needed for this test.
- For email notifications, first complete [Configure Email Settings](/docs/getting-started/email-settings/).

Mailbox numbers belong to an account. If two accounts both have extension 100, they have separate mailboxes; use a phone registered to the intended account when testing.

## 1. Enable the mailbox and set its password

1. Open **Extensions** and edit extension **100**.
2. Select the **Voicemail** tab.
3. Turn **Status** on.
4. Enter a numeric **Password** for accessing voicemail. This is the mailbox PIN, separate from the SIP password and the user's web-login password.
5. Add a **Description**, such as `Alex's voicemail`, if helpful.
6. Leave **Automatically Delete Voicemail After Email** off while testing, so messages remain available on the PBX.
7. Click **Save**.

Choose a PIN that is not the extension number or an obvious sequence. FS PBX checks it against the account's voicemail password policy when you save; follow any field error shown.

**Check:** reopen the extension and confirm voicemail is enabled. The mailbox should also appear on the **Voicemails** page in the same account. Use the extension's Voicemail tab for an existing extension rather than creating a second mailbox with the same number.

## 2. Record the greeting callers will hear

### Record from a phone

From a registered phone in the same account:

1. Dial **`*98`**.
2. Enter the mailbox number, **100**, then press **#**.
3. Enter its voicemail password, then press **#**.
4. Press **5** for mailbox options.
5. Press **1** to record an unavailable greeting.
6. Choose a greeting number from **1–9**, then follow the prompts to record and save it.

Use a short message identifying the person or department and asking the caller to leave a message. After recording, reopen the extension's **Voicemail** tab, choose the recording under **Select Greeting**, and click **Save**.

If the first-access tutorial starts, follow its prompts before returning to the mailbox options. **Play Voicemail Tutorial** controls that guidance in the extension's Voicemail tab; it is also available under **Voicemails → edit mailbox → Advanced**.

### Upload an existing greeting

1. Reopen the extension's **Voicemail** tab.
2. Under **Voicemail Greetings**, click the **+** button beside the greeting controls.
3. Choose **Upload**, select a WAV, MP3, or M4A file, and click **Upload**.
4. Confirm the uploaded recording is selected under **Select Greeting**, then click **Save**.
5. Use the play control to preview it.

The **Recorded Name** is separate from the greeting. It is used by the dial-by-name directory. To record it by phone, enter mailbox options with **5**, then press **3** and follow the prompts.

## 3. Leave a test message

1. From the second extension, call **100**.
2. Let the phone ring without answering.
3. Confirm that your selected greeting plays.
4. Leave a short message after the tone, then hang up.

**Check:** a new message appears for mailbox 100 on the Voicemails page, and its recording contains your voice clearly.

To test the mailbox directly without waiting for ringing, dial **`*99100`** from another extension in the same account. This uses `*99` followed by the mailbox number. Test the normal unanswered call as well, because a direct voicemail call does not verify the extension's unanswered-call routing.

These are the default [feature codes](/docs/additional-information/feature-codes/). Use your installation's configured codes if they have been changed.

## 4. Listen to messages

| Method | Steps |
| --- | --- |
| From extension 100's phone | Dial **`*97`** to access that extension's mailbox. Enter its voicemail password when prompted and follow the spoken menu. |
| From another extension in the same account | Dial **`*98`**, enter **100** and **#**, then the voicemail password and **#**. Follow the spoken menu. |
| From the web interface | Open **Voicemails**, find mailbox **100**, and click its **Messages** count or the **Check messages** envelope icon. Use the play button beside a message. |

The web message list also offers download and, when permitted, delete actions. Web access depends on the user's account and permissions; knowing a mailbox PIN does not grant web access.

## 5. Add email notifications

Once mail delivery works:

1. Open **Voicemails** and edit mailbox **100**.
2. On **Settings**, enter the recipient's **Email Address**.
3. Set **Voicemail Recording Delivery**:

   | Choice | What the email contains |
   | --- | --- |
   | **Attach recording** | An audio attachment. |
   | **Send download link** | A link to download the recording from FS PBX. |
   | **Do not include recording** | A notification without the recording or a download link. |

4. Keep **Automatically Delete Voicemail After Email** off if users should also retrieve messages from the phone or web interface.
5. Click **Save** and leave another test message.

**Check:** the email arrives at the intended address. Open the attachment or download link, if selected, and confirm it plays the test message. For download links, the recipient must be able to reach your FS PBX web address.

Choosing **Send download link** keeps automatic deletion disabled so the recording remains available. If you later enable automatic deletion with another delivery choice, messages are removed from the PBX after email is sent.

**Voicemail Transcription** is optional and requires a configured transcription provider. You can receive and listen to recordings without enabling it. See [Voicemail Email Notifications](/docs/configuration/voicemail-settings/email-notifications/) for more delivery guidance.

## If something does not work

| Symptom | Check first |
| --- | --- |
| The mailbox cannot be found | Confirm the mailbox is enabled and the phone belongs to the same account. With `*98`, enter the voicemail number, not the user's email. |
| The password is rejected | Use the numeric password from the mailbox's settings, not its SIP or web password. Save a new voicemail password if necessary. |
| The extension never reaches voicemail | Check its voicemail Status, ring timeout, and **Call Forward** settings. Forwarding or Call Sequence can send unanswered calls elsewhere. |
| The wrong greeting plays | Reopen **Select Greeting**, choose the intended recording, and save. A Recorded Name does not replace the unavailable greeting. |
| A message is visible but no email arrives | Confirm the mailbox's Email Address, test outgoing mail, and check the [application logs](/docs/troubleshooting/retrieving-logs/). |
| A message disappears after email delivery | Check **Automatically Delete Voicemail After Email**. Turn it off to retain future messages on the PBX. |
| The web message controls are unavailable | Check that the correct account is selected and the user has permission to view voicemail messages. |

Before handing over the mailbox, have its owner confirm the PIN works, the greeting is correct, and a test message can be played using their preferred method.
