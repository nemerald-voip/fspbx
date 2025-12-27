---
id: lets-encrypt
title: Let's Encrypt SSL certificate
slug: /configuration/web-server/lets-encrypt/
sidebar_position: 1
---

# Let's Encrypt SSL certificate

Securing your FS PBX installation with an SSL certificate is crucial for ensuring secure communications. This guide will walk you through the process of installing a **Let's Encrypt SSL certificate** using **Dehydrated** after setting up FS PBX.

## Prerequisites
Before you begin, ensure:

* FS PBX is installed and running.
* You have a domain name (e.g., pbx.example.com).
* Your server is publicly accessible on ports 80 and 443.
* A user with sudo privileges

# Step 1: Run the SSL Installation Command
FS PBX includes an **Artisan command** that automates the process of generating and installing SSL certificates. To execute the script:
1. Navigate to the FS PBX project directory:

   `cd /var/www/fspbx`

2. Run the command to install the certificate:

   `php artisan app:install-lets-encrypt-certificate`

3. Enter the domain when prompted (e.g., `fs-pbx.example.com`).

# Step 2: Verify SSL Installation
To confirm that the SSL certificate is working correctly, open a web browser and visit:

`https://fs-pbx.example.com`


# Step 3: Enable Auto-Renewal

The script automatically sets up a cron job to renew the certificate:

`0 3 * * * dehydrated -c && systemctl reload nginx`

# Conclusion

You have successfully installed and configured a **Let's Encrypt SSL certificate** for FS PBX using **Dehydrated**. Your PBX is now secured with HTTPS, and the certificate will automatically renew.

If you encounter any issues, check the logs with:

`sudo journalctl -u nginx --no-pager`

# Updating Environment Configuration

After creating the SSL certificate, it is recommended to update the `.env` file with the following settings:

```
APP_URL=https://fs-pbx.example.com
SESSION_DOMAIN=fs-pbx.example.com
SANCTUM_STATEFUL_DOMAINS=fs-pbx.example.com
```

**Key Points:**

-   **APP_URL**\
    This variable sets the base URL of your application.

-   **SESSION_DOMAIN**\
    This setting defines the primary domain for your session cookies.

      * You can use a wildcard here: `.example.com`

-   **SANCTUM_STATEFUL_DOMAINS**\
    **Important:** This configuration is an **array** of domains that Laravel Sanctum will treat as stateful. That means every domain from which you plan to make authenticated API requests must be added here.

    -   **Example:** If you have multiple subdomains (e.g., `fs-pbx.example.com`, `api.fs-pbx.example.com`, and `dashboard.fs-pbx.example.com`), then list all of them separated by commas:

        `SANCTUM_STATEFUL_DOMAINS=fs-pbx.example.com,api.fs-pbx.example.com,dashboard.fs-pbx.example.com`

These settings ensure that your application correctly handles secure sessions and authentication over HTTPS. After making changes to your `.env` file, run the following command to refresh the configuration cache:

`php artisan config:cache`
