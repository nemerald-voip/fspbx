---
id: messaging-overview
title: SMS Messaging Overview
slug: /configuration/messaging/overview
sidebar_position: 1
---

SMS Messaging Overview
======================

FS PBX includes a modern, flexible SMS framework that allows your system to **send and receive SMS messages** using multiple supported providers. This article provides a high-level overview of how SMS works within FS PBX. Provider-specific setup guides will follow in separate articles.

* * * * *

Key Capabilities
----------------

### **Send & Receive SMS Through Multiple Providers**

FS PBX supports several SMS carriers and gives you the freedom to choose which provider handles each phone number.\
Once configured, any SMS-capable number can:

-   Receive inbound messages

-   Send outbound replies

-   Deliver messages to the mobile app

-   Optionally send notifications via email (read-only)

You can mix and match providers---each phone number can use a different provider if needed.

* * * * *

Mobile App Integration
----------------------

SMS messaging is tightly integrated into the **Mobile App**.\
When an inbound SMS arrives:

1.  The message is delivered to the extension associated with the number.

2.  The user receives the message instantly inside the mobile app.

3.  They can reply directly in the app, and the system will send the SMS back through the correct provider.

This makes the mobile app the primary and recommended interface for two-way SMS communication.

* * * * *

Email Notifications (Optional)
------------------------------

FS PBX can also send SMS messages to a user's **email address** for convenience.\
However:

-   Email delivery is *one-way only*

-   Users **cannot reply from email** to send an SMS back

-   Replies must be made inside the mobile app or the FS PBX interface (if applicable)

* * * * *

Enabling SMS on a Phone Number
------------------------------

Before a number can send or receive SMS, it must be configured in:

**Advanced → Message Settings**

For each phone number, you must set:

### **1\. SMS Provider**

Choose which carrier (e.g., Bandwidth, Sinch, Commio, Telnyx, ClickSend, etc.) will handle SMS for this number.

### **2\. Destination**

Assign the destination for inbound messages:

-   An **extension** (recommended)

    -   Delivers SMS directly to the mobile app associated with that extension

-   An **email address**

    -   Delivers SMS to email (read-only)

You can assign either or both.

Once configured, FS PBX automatically routes:

-   Inbound SMS → to the selected extension (and/or email)

-   Outbound replies → back through the correct provider to the original sender

* * * * *

System Notification SMS Numbers
-------------------------------

FS PBX also allows adding **system notification numbers** under Message Settings.

These numbers are used specifically for **system-generated messages**, such as:

-   Voicemail delivery notifications

-   Optional voicemail transcription summaries

(A separate article will cover SMS voicemail notifications in detail.)

These numbers are **not** attached to an extension and are not used for two-way messaging.

* * * * *

Logs
-------------------------------
SMS logs can be viewed in **Applications -> Messages**

* * * * *

Summary
-------

FS PBX provides a powerful, flexible SMS engine with:

-   Multi-provider support

-   Integrated two-way messaging in the mobile app

-   Optional email notifications

-   Provider and routing configuration per number

-   Support for system-level SMS notifications

Once a number is SMS-enabled and assigned to a provider and destination, FS PBX seamlessly handles the full messaging lifecycle: receiving inbound messages, delivering them to users, and sending outbound replies.