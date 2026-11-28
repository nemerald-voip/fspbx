---
id: set-up-a-fax-number
title: Set up a fax number
slug: /configuration/fax/set-up-a-fax-number/
sidebar_position: 1
---

# Set up a fax number

A fax server gives your team an inbox and a number to send faxes from. To receive outside faxes in that inbox, point the public fax number to it. You can also send a copy of each incoming fax to one or more email addresses.

## Create the fax inbox

1. Switch to the account that will use the fax number. Open **Phone Numbers** and make sure the number from your carrier is listed and enabled. If it is new, add it to the account first. See [Phone Numbers](../../04-getting-started/11-phone-numbers.md).
2. Open **Faxes**. Under **Fax Servers**, click **Create Fax Server**. Enter a **Name** your users will recognize, such as `Office Fax`, and keep the suggested **Extension**.
3. Select the public fax number under **Caller ID Number**. This is the number your team will see when choosing where to send a fax from. Enter a **Caller ID Name** if desired.
4. If anyone should receive incoming faxes by email, add their addresses under **Forward incoming faxes to email**. You can enter up to five. Leave this blank if the team will use the inbox only.
5. Click **Save**. The new inbox appears under **Fax Servers**.

## Route incoming faxes

1. Go back to **Phone Numbers** and edit the public fax number.
2. Under **Call routing**, set **Choose Action** to **Fax** and select **Office Fax** as the **Target**. Save the number.
3. Send a one-page fax to the public number from an outside fax service or machine. In **Faxes → Fax Servers**, open **Inbox** beside **Office Fax** and check that the page arrived. If you added email recipients, check that they received a copy.

If a voice phone rings instead, recheck the public number's **Call routing** target. If the page arrived in the inbox but an email copy is missing, check the forwarding address and the [email settings](../../04-getting-started/03-email-settings.md).

To send a test fax back, follow [Send, receive, and track faxes](02-send-and-track-faxes.md). To let users send documents by email, see [Email-to-Fax with Mailgun](03-mailgun-email‐to‐fax.md).
