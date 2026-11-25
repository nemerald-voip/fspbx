---
id: email-settings
title: Configure Email Settings
slug: /getting-started/email-settings
sidebar_position: 3
---

# Configure Email Settings

Connect FS PBX to your mail provider so it can send password resets, voicemail notifications, and other outgoing messages. You’ll need SSH access to the PBX, the credentials from your mail provider, and a mailbox where you can receive a test message.

## 1. Configure outgoing mail

On the FS PBX server, open the configuration file.

```bash
sudo nano /var/www/fspbx/.env
```

### SMTP

Update the existing `MAIL_` entries with your provider’s SMTP settings. Set `MAIL_FROM_ADDRESS` to an address your provider allows you to send from. `MAIL_FROM_NAME` is the name recipients see in their inbox.

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD="your-smtp-password"
MAIL_FROM_ADDRESS=pbx@example.com
MAIL_FROM_NAME="Example Company PBX"
```

Use the port your provider specifies. On port **587**, FS PBX upgrades the connection to TLS if the server offers STARTTLS. If your provider requires TLS from the start, use port **465**.

For example, these two services can send PBX notifications on port **587** with STARTTLS. Verify your sender address or sending domain with the service you choose.

| SMTP service | `MAIL_HOST` | `MAIL_USERNAME` | `MAIL_PASSWORD` |
| --- | --- | --- | --- |
| [Twilio SendGrid](https://www.twilio.com/docs/sendgrid/for-developers/sending-email/integrating-with-the-smtp-api) | `smtp.sendgrid.net` | The literal word `apikey` | A SendGrid API key with **Mail Send** permission |
| [Postmark](https://postmarkapp.com/developer/user-guide/send-email-with-smtp) | `smtp.postmarkapp.com` | The SMTP token’s **Access Key** | The same token’s **Secret Key** |

For Postmark SMTP, enable SMTP access on your Postmark server and create the SMTP token in your **transactional** message stream’s **Settings**.

### Other delivery options

- **Postmark API** — Set `MAIL_MAILER=postmark` and use the server token, message stream, and sender settings in the [Postmark setup guide](../50-integrations/02-postmark_webhooks.md#configure-email-sending-in-fs-pbx).
- **Local mail server** — Set `MAIL_MAILER=sendmail` if Postfix or another sendmail-compatible service already handles outgoing mail on this server. Set the sender address and name as shown above.
- **Log only** — For testing on a development server, `MAIL_MAILER=log` records outgoing messages locally and skips inbox delivery.

## 2. Apply the settings

Save `.env`, then run both commands from the FS PBX installation directory.

```bash
cd /var/www/fspbx
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan horizon:terminate
```

The first command rebuilds the configuration cache. The second lets Horizon’s queue workers finish their current jobs and exit. On a standard installation, Supervisor restarts Horizon so queued notifications use the new mail settings. Repeat this step whenever you change the mail settings in `.env`.

## 3. Send a test email

1. In FS PBX, open **Status → Logs → Emails** and click **Send Test Email**.
2. Enter an email address you can check and click **Send**.
3. Check that the message arrives and that the sender address and name are what you expect.

## If the email does not arrive

In **Status → Logs → Emails**, find the test message by its recipient and time. Click the row and read **Send Log** for any errors or retries.

| What you see | What to check |
| --- | --- |
| Authentication failed or sender rejected | Verify the SMTP credentials and confirm the provider allows the address in `MAIL_FROM_ADDRESS`. |
| Connection timeout, refusal, or certificate error | Check the provider’s SMTP hostname and port. For a timeout or refusal, also check whether the PBX can reach that port. |
| **Sent**, but the inbox is empty | Check the recipient’s spam folder and your provider’s delivery or bounce history. **Sent** can mean the provider accepted the message for delivery. |
| The test arrives, but queued notifications still use the old settings | Repeat the commands in step 2 and run `sudo -u www-data php artisan horizon:status` from `/var/www/fspbx` to check that Horizon is running. |

To change the wording or language of outgoing messages, see [Email template languages](/docs/getting-started/language-settings/#email-template-languages).
