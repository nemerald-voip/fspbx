---
id: mailgun-email-to-fax
title: 'Mailgun: Email-to-Fax'
slug: /configuration/fax/mailgun-email-to-fax
sidebar_position: 1
---

# Configure Email‐to‐Fax with Mailgun

FS PBX offers a modern, resource-efficient Email-to-Fax solution by directly integrating with Mailgun inbound email webhooks. This method eliminates IMAP polling and ensures faxes are processed instantly.

* * * * *

**Overview**
------------

With FS PBX, you can send faxes simply by emailing your documents to a special address. Mailgun receives the email, then securely relays it to your FS PBX instance for fax delivery. This guide explains how to configure both FS PBX and Mailgun, and outlines the required email format.

* * * * *

**1\. Prerequisites**
---------------------

-   **FS PBX** v0.9.59 or newer

-   **A working Mailgun account** with a verified inbound domain


* * * * *

**3\. Setting Up Mailgun**
--------------------------

1.  **Configure Your Domain**

    -   Log in to Mailgun.

    -   Go to **Receiving > Routes**.

2.  **Add a New Route**

    -   **Filter Expression:**\
        Match the recipient pattern you want (e.g., `match_recipient(".*@domain.com")`).

    -   **Forward Action:**\
        `https://your-fspbx-server.com/webhook/mailgun"`

3.  **Save the Route.**

* * * * *

**4\. Sending a Fax by Email**
------------------------------

To send a fax using Email-to-Fax, compose your email with these parameters:

1.  **To:**

    -   Enter the **destination fax number** (no spaces or dashes), followed by your domain.\
        Example:

        `9093655050@domain.com`

2.  **Subject:**

    -   Use your company's outbound **fax number**.

    -   *If left blank,* the system will use the fax number assigned to your email address.

3.  **Body (optional):**

    -   To include the message body as a **cover page**, put the word `body` in the subject line.

4.  **Attachment:**

    -   Attach the document(s) you want to fax.

* * * * *

### **Supported File Types**

You may attach any of the following file types:

-   `.pdf`

-   `.doc`, `.docx`

-   `.rtf`

-   `.xls`, `.xlsx`, `.csv`

-   `.txt`

* * * * *

**5\. How it Works**
--------------------

-   Mailgun receives your inbound email and immediately POSTs it to your FS PBX `/webhook/mailgun` endpoint.

-   FS PBX parses the email, extracts the fax destination, sender information, and attached files.

-   The system queues the fax and sends a **confirmation or rejection email** to the original sender.

* * * * *

**6\. Sample Email**
--------------------

| Field | Value |
| --- | --- |
| To | 9093655050@domain.com |
| Subject | 3105552222 body [Optional]|
| Body | [add text to be used as cover letter] |
| Attachment | `contract.pdf` |

* * * * *

**7\. Troubleshooting**
-----------------------

-   **No Confirmation Received:**\
    Check that your webhook URL is correct and publicly accessible.

-   **Attachment Not Sent:**\
    Only supported file types will be processed.

-   **Wrong Fax Number:**\
    Double-check that you have entered the number with no spaces/dashes.

-   **Check Fax Queue page to check the status of the fax:**\
    Navigate to `Status->Fax Queue` and check the status

* * * * *

**8\. Confirmation & Delivery Reports**
---------------------------------------

After submission, FS PBX will reply with a confirmation or rejection notice to the sender's email address.

* * * * *

**Questions?**
--------------

If you need help, please open an issue or contact the FS PBX support team.