---
id: send-and-track-faxes
title: Send, receive, and track faxes
slug: /configuration/fax/send-and-track-faxes/
sidebar_position: 2
---

# Send, receive, and track faxes

Use **Faxes** to send a document from your browser, read incoming pages, or check whether a fax arrived. If your account has several fax numbers, each has its own **Inbox**, **Sent**, and **Logs**.

## Send a fax

1. Open **Faxes** and click **Send New Fax**.
2. Under **Select Your Fax Number**, choose the number to send from. In **Fax recipient**, enter the receiving fax number or click **Phone book** to select a contact. Check the number before sending.
3. Under **Upload files**, attach your document. You can send one to five files, up to 20 MB each. A PDF is a good choice for preserving the page layout. If a file type is rejected, the form lists the types enabled for your system.
4. To add a cover page, select **Cover Letter** and enter your **Cover Letter Text**. Turn on **Send fax confirmation to my email** if you want an email when the fax succeeds or fails.
5. Click **Send Fax**. The confirmation that your fax is scheduled means FS PBX has accepted it for delivery. Check **Recent Outbound Faxes** for the result.

## Check delivery or retry a failed fax

On the fax dashboard, find the recipient in **Recent Outbound Faxes**. **Waiting** means the fax is queued, **Sending** means transmission is in progress, and **Sent** means it succeeded.

If the status is **Failed**, open **Logs** beside the sending fax server and find the attempt by its number and time. Check the result before trying again. If the recipient number was correct and **Retry outbound fax** is available, use it to retry the same fax. If the number was wrong, use **Send New Fax** and enter the corrected number.

## Read an incoming fax

Open **Faxes**, find your number under **Fax Servers**, and select **Inbox**. Find the fax by sender and date, then use **Preview** to read it or **Download** to save a copy. Use **Sent** beside the same fax server to find a copy of a fax you sent.

If your organization's fax number is missing, ask your administrator to check which fax servers you can access. Administrators can follow [Set up a fax number](01-set-up-a-fax-number.md).
