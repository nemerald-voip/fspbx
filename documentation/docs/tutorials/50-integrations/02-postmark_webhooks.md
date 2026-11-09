---
sidebar_position: 17
title: Postmark Webhooks
---

# Postmark Webhooks

FS PBX uses Postmark webhooks for outgoing email status updates and incoming email-to-fax. Both use the same webhook address:

```text
https://your-fspbx-hostname/webhook/postmark
```

| Purpose | Configure in Postmark | Result in FS PBX |
| --- | --- | --- |
| Outgoing email events | The sending server's transactional stream | Updates **Logs > Emails** when Postmark reports delivery, a bounce, or a spam complaint. |
| Incoming email-to-fax | The receiving server's inbound stream | Sends email attachments as faxes for authorized senders. |

These streams may be on different Postmark servers. Configure the flows you use; enabling one does not configure the other. A fax notification email uses the outgoing email flow, while an email submitted for fax transmission uses the inbound flow.

## Prerequisites

- The FS PBX server must be reachable from the public internet over HTTPS.
- Background email and fax processing must be running on your FS PBX server.
- For outgoing events, FS PBX must send the email through the Postmark server where you configure the webhook.
- For email-to-fax, Postmark must already receive mail for your fax email domain, and the sender must be authorized to use a fax server in FS PBX. Configuring a webhook does not set up mail delivery to Postmark. See [Postmark inbound processing](https://postmarkapp.com/developer/webhooks/inbound-webhook).

## Shared webhook authentication

Edit the FS PBX `.env` file:

```bash
nano /var/www/fspbx/.env
```

Add dedicated credentials for Postmark to send with its webhook requests:

```dotenv
POSTMARK_WEBHOOK_USERNAME=fspbx-postmark
POSTMARK_WEBHOOK_PASSWORD=your-dedicated-random-webhook-password
```

Choose a strong random password. For example, `openssl rand -hex 32` generates a URL-safe password. These credentials are separate from FS PBX user accounts and the Postmark API token.

Both outgoing email events and incoming email-to-fax require matching credentials. FS PBX rejects requests with missing or incorrect credentials with **401 Unauthorized**. Leaving the FS PBX credentials blank also blocks webhook requests.

Use the same credentials for all Postmark webhooks pointing to this endpoint. Postmark's separate Basic Auth fields take precedence over credentials embedded in a webhook URL. See [Postmark webhook authentication](https://postmarkapp.com/developer/webhooks/webhooks-overview#protecting-your-webhook).

**Existing installations:** populate both `.env` settings and configure matching credentials on the outbound and inbound webhooks before upgrading to the version that enforces authentication. Otherwise, those webhooks will be rejected after the upgrade.

After editing `.env`, apply the settings by running these commands on the FS PBX server:

```bash
cd /var/www/fspbx
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan horizon:terminate
```

## Outgoing email events

### Configure email sending in FS PBX

If FS PBX sends through Postmark, configure its sending server and message stream in `.env`:

```dotenv
MAIL_MAILER=postmark
POSTMARK_TOKEN=your-postmark-server-token
POSTMARK_MESSAGE_STREAM_ID=outbound
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="FS PBX"
```

Copy `POSTMARK_TOKEN` from that server's **API Tokens** tab in Postmark. Postmark issues this token; do not generate your own. Apply any changes using the commands above.

These sending settings are only needed for mail sent through Postmark. Receiving email-to-fax through Postmark does not require changing an existing outgoing mail provider.

### Configure the transactional stream

In Postmark:

1. Open the Postmark server matching `POSTMARK_TOKEN` and select the sending transactional stream, usually `outbound`.
2. Go to **Settings > Webhooks**.
3. Add or edit the webhook.
4. Set the webhook URL:

```text
https://your-fspbx-hostname/webhook/postmark
```

5. Enable **Delivery**, **Bounce**, and **Spam Complaint**. Full email content is not required for these events.
6. Under **Basic auth credentials**, enter the values of `POSTMARK_WEBHOOK_USERNAME` and `POSTMARK_WEBHOOK_PASSWORD`.
7. Save and verify the webhook. Each enabled event should pass verification.

The webhook must be on the server and stream that actually send the messages. A webhook on your inbound fax server will not receive events for email sent through another server.

### What the email status means

FS PBX initially marks an email **Sent** when the mail provider accepts it. This does not confirm delivery to the recipient's mailbox. A later bounce or spam complaint changes the email's status to a failure and adds the provider's explanation.

Postmark's verification checks the connection and credentials. It does not send a real email or confirm delivery to a recipient.

## Incoming email-to-fax

1. Open the Postmark server that receives email for your fax email domain.
2. Select its **Inbound Message Stream**, then **Settings**.
3. In **Inbound webhook**, enter the URL with the shared webhook credentials:

```text
https://USERNAME:PASSWORD@your-fspbx-hostname/webhook/postmark
```

4. Replace `USERNAME` and `PASSWORD` with the values from FS PBX's `.env`. URL-encode special characters in each value; the suggested hex password needs no encoding.
5. Leave **Include raw email content in JSON payload** unchecked; it is not required for FS PBX email-to-fax.
6. Save changes and use **Check** to test the endpoint.

The inbound form does not have separate username and password fields. The credentials in the URL provide authentication. Treat the complete URL as a password and keep it out of shared screenshots and logs.

The person sending the email must still be authorized to send faxes in FS PBX. For a complete fax test, send a supported attachment from an authorized sender to a fax number you control, using an address such as `12025550123@your-fax-email-domain`, then check the fax result. **Check** alone does not confirm fax transmission.

## Troubleshooting

### The webhook returns 401 Unauthorized

Confirm the webhook username and password are populated in FS PBX and match Postmark exactly. Apply any `.env` changes using the commands above. Check the inbound email-to-fax URL separately if it uses another Postmark server. Use the dedicated webhook credentials rather than your Postmark API token or FS PBX login.

### Outgoing verification succeeds, but real email events do not arrive

Confirm the real email is being sent through the same Postmark server where the webhook is configured. A common mistake is updating `POSTMARK_TOKEN` to a new server token while the webhook remains configured on the old Postmark server.

### An incoming email does not create a fax

First confirm the message appears in Postmark's inbound stream. If it does not, check mail routing to Postmark. If Postmark reports a webhook error, check the saved inbound URL and credentials.

If the webhook check succeeds but no fax appears, confirm that the sender is authorized, the email recipient contains the destination fax number, and the message includes a supported attachment. Review the fax status and any failure notification in FS PBX. Before retrying, check whether a fax was already sent to avoid duplicates.

### An email stays on Sent after Postmark reports a failure

Confirm **Bounce** and **Spam Complaint** are enabled and verified on the sending stream. Open **Delivery Details** for the email in FS PBX to retrieve its latest provider information. Refreshing the email list alone does not retrieve missed events from Postmark.

A temporary delivery problem may appear in Postmark before it produces a bounce webhook. Also check the provider's actual failure reason: reactivating a suppressed address will not fix a nonexistent mailbox.

### An email stays on Sending

Review the email's details for an error and confirm that background email processing is running. If Postmark never accepted the message, a delivery webhook cannot resolve that send attempt. Correct the reported problem before retrying the email.
