---
id: email-settings
title: Configure Email Settings
slug: /getting-started/email-settings
sidebar_position: 1
---

# Configure Email Settings

FS PBX uses Laravel's built-in mailer to send notifications such as voicemail alerts, password resets, license updates, and system messages. Correctly configuring email settings ensures all automated messages are delivered reliably.

* * * * *

🧩 Overview
-----------

Email delivery is handled through the Laravel `.env` configuration file, located in the root of your FS PBX installation (usually `/var/www/fspbx/.env`).

You can use several mail transport methods:

| Method | Description |
| --- | --- |
| `smtp` | Use an external mail server (recommended) |
| `sendmail` | Use the local `sendmail` binary |
| `log` | Write all outgoing emails to `storage/logs/laravel.log` (for debugging) |

* * * * *

⚙️ Step 1 -- Locate Your `.env` File
-----------------------------------

SSH into your FS PBX server and open the `.env` file:

`sudo nano /var/www/fspbx/.env`

* * * * *

⚙️ Step 2 -- Set Your Mail Driver and Credentials
------------------------------------------------

Add or edit the following lines.\
(Replace placeholders with your actual credentials.)

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourmailserver.com
MAIL_PORT=587
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=pbx@yourdomain.com
MAIL_FROM_NAME="FS PBX"
```

> 💡 **Tip:**
>
> -   If your provider uses SSL on port 465, set `MAIL_ENCRYPTION=ssl`.
>
>
> -   For unencrypted connections, leave it blank: `MAIL_ENCRYPTION=null`.

* * * * *

⚙️ Step 3 -- Apply Configuration
-------------------------------

After editing `.env`, rebuild the cached configuration so FS PBX recognizes the new mail settings:

`php artisan config:cache`

This step is **required** whenever you change environment variables such as mail credentials, database info, or queue settings.

* * * * *

🧪 Step 4 -- Test Email Delivery
-------------------------------

Log in to your **FS PBX Dashboard** and navigate to the **Users** page.

Create a **new user** (or select an existing one) and open the **Security** tab.

Click **"Reset Password"** to trigger a password-reset email.

-   ✅ If you receive the reset email in your inbox, your mail configuration is working correctly.

-   ⚠️ If you do **not** receive the message --- or you see an on-screen error --- proceed to the next step to **check the Laravel logs** for more details.
* * * * *

📄 Step 5 -- Check Logs if It Fails
----------------------------------

If no email arrives, check Laravel's logs for details:

`tail -f storage/logs/laravel.log`

Look for errors like:

-   **Authentication failed** → wrong username/password

-   **Connection refused** → firewall or wrong port

-   **SSL/TLS error** → wrong encryption mode

* * * * *


🧰 Optional -- Use Sendmail (Local MTA)
--------------------------------------

If you prefer local delivery (Postfix/Exim), set:

`MAIL_MAILER=sendmail
MAIL_FROM_ADDRESS=pbx@yourdomain.com
MAIL_FROM_NAME="FS PBX"`

Ensure your system's MTA is properly configured and running.

* * * * *

🧰 Optional -- Debugging Mode (Log Driver)
-----------------------------------------

If you want to debug email templates without actually sending them:

`MAIL_MAILER=log`

All messages will appear in `storage/logs/laravel.log`.

* * * * *


🔍 Example for Gmail SMTP
-------------------------

If using a Gmail or Google Workspace account:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=yourname@gmail.com
MAIL_PASSWORD=your_app_specific_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=yourname@gmail.com
MAIL_FROM_NAME="FS PBX"
```

> ⚠️ Gmail requires an *App Password* (not your normal login password).

* * * * *

🧾 Summary
----------

Once these steps are completed:

-   Email and system notifications will start working.

-   You can monitor errors in `storage/logs/laravel.log` or via the **Status -> Logs** page in FS PBX.