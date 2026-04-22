---
id: enable-2fa
title: How to Enable Two-Factor Authentication
slug: /configuration/enable-2fa
sidebar_position: 99
---

# How to Enable Two-Factor Authentication by Email Challenge

Email Challenge adds an extra layer of security to user logins. When this setting is enabled, users will be required to complete a verification step by email during sign-in.

## Before you begin

Make sure your server is able to send email successfully. If email delivery is not working, users will not receive the verification message.

## Enable Email Challenge

1. Sign in to your server as a superadmin.
2. Open the **Default Settings** page.
3. Add or edit the following setting:

**Category:** `authentication`
**Subcategory:** `email_challenge`
**Type:** `Boolean`
**Value:** `True`
**Enabled:** True

4. Save the setting.

5. Clear the application cache by running the following command from the /var/www/fspbx folder:

```
php artisan cache:clear
```

## Example

Your setting should look like this:

* Category: `authentication`
* Subcategory: `email_challenge`
* Type: `Boolean`
* Value: `True`
* Enabled: enabled

## What this setting does

When enabled, the system will require users to complete an email verification challenge during login.

This adds an extra security step beyond the password and helps protect user accounts from unauthorized access.

## Test the feature

After saving the setting:

1. Sign out of the user account.
2. Sign back in.
3. Confirm that the system sends a verification email and prompts for the challenge during login.

## Disable Email Challenge

To turn the feature off, edit the same setting and either:

* change **Value** to `False`, or
* switch **Enabled** off

Then save the change and run:

```
php artisan cache:clear
```

from the `/var/www/fspbx` folder.

## Troubleshooting

If users are not receiving the challenge email:

* verify that outbound email is configured correctly on the server
* confirm the user’s email address is correct
* check spam or junk folders
* clear the cache after updating the setting
* allow up to one minute for the setting change to take effect

## Notes

Email Challenge is a simple way to add extra login protection using email verification.
