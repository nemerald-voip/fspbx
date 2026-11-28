---
id: enable-2fa
title: How to Enable Two-Factor Authentication
slug: /configuration/enable-2fa
sidebar_position: 99
---

# Set up two-factor authentication

## Use an authenticator app

1. Open the user menu, select **My Account**, then select **Two-factor authentication**.
2. Select **Set up authenticator**. If FS PBX asks for your current password, enter it. If your organization manages your password, use your directory password.
3. In your authenticator app, scan the QR code or enter the setup key shown below it.
4. Enter the six-digit code from the app and select **Verify and turn on**.
5. Copy or download the recovery codes and keep them somewhere you can reach if you lose your phone. Each code works once. Select **I've saved my codes** to finish.

At your next sign-in, enter your password and the code from your authenticator. Under **Other ways to sign in**, you can use a saved recovery code or have FS PBX email a code to your sign-in address. Users with an enabled authenticator complete a challenge at every new sign-in.

From **My Account**, you can finish or discard an unfinished setup, view your recovery codes, generate new ones, or turn off two-factor authentication. Generating new recovery codes cancels the previous set.

## Require email codes for other users

A superadmin can require email verification for users who have yet to enable an authenticator. First confirm that the server can deliver email. In **Default Settings**, open the following setting.

| Field | Value |
| --- | --- |
| Category | `authentication` |
| Setting Name | `email_challenge` |
| Type | `Boolean` |
| Value | `True` |
| Enabled | Yes |

Save the setting, then run `php artisan cache:clear` from `/var/www/fspbx` to apply it immediately. To turn the requirement off, set the value to `False` and clear the cache again. Enrolled authenticator users can choose email as a backup with either setting.

Email-only users can select **Remember this device for 7 days**. If a code does not arrive, check the sign-in address and spam folder, then select **Send a new code**. Use the newest email.
