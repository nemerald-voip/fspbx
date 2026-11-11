---
id: first-login-and-your-first-account
title: First Login and Your First Account
slug: /getting-started/first-login-and-your-first-account/
sidebar_position: 1
---

# First Login and Your First Account

After installing FS PBX, sign in with the generated administrator credentials and create an account for your business. This guide takes you from the installation completion screen to an account ready for extensions and phones.

In FS PBX, an **account** is a company or customer whose extensions, phone numbers, and settings belong together. Some screens call it a **Domain**. A **user** is a person's web login; creating an account does not create another user.

## Before you begin

:::important Required post-installation setup
Before signing in, configure the web URL and session settings to prevent URL-related 419 errors and set up a Let's Encrypt certificate for the web server. After signing in, redundant installations must also select a scheduled-job owner. Follow the [required steps after installation](/docs/installation/#required-steps-after-installation) in that order before putting the system into service.
:::

Have these ready:

- A completed [FS PBX installation](/docs/installation/).
- The **Login URL**, **Username**, and **Password** printed at the end of installation.
- A business name and the domain name you want its phones to use.
- Access to an email service for password resets and notifications. You will configure it below.

## 1. Sign in for the first time

1. Open the HTTPS web address configured during post-installation setup.
2. Enter the generated username and password. The username is an email address; enter it exactly as printed.
3. Confirm that the FS PBX dashboard opens.

The installer initially uses a self-signed web certificate. Replace it as part of the required [Let's Encrypt web certificate setup](/docs/configuration/web-server/lets-encrypt/). That guide also covers the application URL and session settings when changing from an IP address to a hostname.

If you lost the generated password, use [How to Reset Superadmin Password](/docs/configuration/users-and-groups/how-to-reset-superadmin-password/). You do not need to reinstall FS PBX to recover administrator access. For **419 — Page Expired**, follow the [419 troubleshooting guide](/docs/troubleshooting/error-419/).

### Select a job owner for redundant installations

Once signed in, open **Advanced → System Settings → Scheduled Jobs** and follow [Scheduled Job Server Setup](/docs/configuration/scheduled-jobs/server-ownership/). Select the owner and confirm that both servers show the same **Job owner** before continuing. Skip this step for a single-server installation.

### Understand the initial Admin Domain

A fresh installation creates **Admin Domain**, with the domain name `admin.localhost`, and a **superadmin** user. This user can administer the whole server and its accounts.

`admin.localhost` identifies that account inside FS PBX. It is not your public website address, and entering it into a remote phone does not give the phone a reachable PBX server.

For your business, create a separate account using the steps below. Keep Admin Domain for server administration.

## 2. Set your administrator email and password

1. While **Admin Domain** is selected, open **Users** and edit the administrator user you signed in with.
2. In **Basic Info**, enter your **First Name**, **Last Name**, and an **Email** address you control. Keep the existing superadmin role, then click **Save**.
3. Follow [Configure Email Settings](/docs/getting-started/email-settings/) to set up outgoing mail. Keep your administrator session open while doing this, and use your updated user when testing password-reset delivery.
4. Reopen the user, select **Security**, and click **Reset Password**. Confirm the request.
5. Open the reset email and use its link to choose your own password.
6. Sign in again using your updated email address and new password.

**Check:** the reset email arrives at your own address, and the new credentials open the dashboard. If the email does not arrive, use the checks in the email guide before signing out of your working session.

Once email delivery works, you can also enable the [email login challenge](/docs/configuration/enable-2fa/).

## 3. Create your business account

Open **Domains** and click **Create**. The form is titled **Create New Domain**.

| Field | What to enter |
| --- | --- |
| **Domain Label** | A recognizable business name, such as `Acme Office`. This is the friendly account name. |
| **Domain Name** | A unique account domain, such as `acme.example.com`. Replace the example with your own name; enter no `https://`, path, or port. |
| **Status** | Keep enabled. |

Click **Save**. The new account should appear in the Domains list with its label and host.

Creating an account does not create DNS records or issue a certificate. If phones will connect directly to `acme.example.com`, arrange DNS so that name resolves to the intended PBX address. Use a certificate covering the hostname for services that require a trusted certificate.

### Keep the three addresses distinct

| Name | Purpose | Example |
| --- | --- | --- |
| Web address | Where an administrator signs into FS PBX. | `https://pbx.example.com` |
| Account domain | Identifies the company and its SIP accounts. | `acme.example.com` |
| SIP server or proxy | The reachable hostname or IP a phone connects to. | `pbx.example.com` |

The account domain and SIP server can be the same hostname. When they differ, the phone needs the correct account domain **and** a reachable server/proxy. Use the extension's **SIP Credentials** and the phone's setup guide when configuring it.

See [Domains (Multi-Tenancy 101)](/docs/getting-started/domains-multitenancy-101/) for more about account separation.

## 4. Switch into the new account

Creating an account does not select it automatically.

1. Click the current account name in the top navigation. On smaller screens, look for the globe icon.
2. In **Select account**, search for the business label or domain name.
3. Select the new account and allow the page to reload.
4. Open **Account Settings → General**. Confirm that **Account Name** and **Domain** identify your business.

Check the selected account before adding extensions, users, or phone numbers. Resources created while Admin Domain is selected belong to that account instead.

## 5. Set the account's regional preferences

In **Account Settings → General**:

1. Keep **Account Status** enabled.
2. Under **Regional**, select the business's **Time Zone** and **Language**.
3. Click **Save**.
4. If you changed the language, sign out and back in, then select the business account again.

An empty regional selection inherits the system default. The account language applies to everyone using that account. Web language and call-audio language are configured separately; see [Language Settings and Sounds](/docs/getting-started/language-settings/).

## 6. Continue with phones and calling

Your account is ready when you can select it, reopen Account Settings, and see the saved name and regional preferences.

Continue in this order:

1. Configure [Network Addresses](/docs/getting-started/network-settings/) before registering phones or connecting a carrier.
2. [Create Your First Extension](/docs/getting-started/create-your-first-extension/), register two phones, and test an internal call with audio in both directions.
3. Follow [Voicemail Basics](/docs/getting-started/voicemail-basics/) to set a PIN, greeting, and message delivery.
4. Use [Phone Provisioning](/docs/phone-provisioning/) if FS PBX will configure desk phones automatically.
5. Add a [gateway](/docs/getting-started/geteways/) and [phone numbers](/docs/getting-started/phone-numbers/) when you are ready for external calls.

You can use your superadmin login to finish setup. Create additional [Users](/docs/getting-started/users/) when other people need web access; a web user is not required for every phone.
