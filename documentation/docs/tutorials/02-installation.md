---
id: installation
title: Installation
slug: /installation
sidebar_position: 2
---

# Installation

Getting started with FS PBX is a breeze.

* * * * *

## Requirements

Before you begin, ensure you have met the following requirements:

- Debian 12 or 13
- 4GB of RAM (for production, more RAM is recommended)
- 30 GB Hard drive ( for production, an NVME hard drive with more space is recommended)

## Installation

<!-- :::tip

If you’re installing on **Debian 13**, a **SignalWire Personal Access Token** is required. Follow the guide here to create or update your token: [How To Create a SignalWire Personal Access Token](additional-information/signalwire-token.md)

::: -->

1. **Download and run the installation script**

   ```bash
    wget -O- https://raw.githubusercontent.com/nemerald-voip/fspbx/main/install/install-fspbx.sh | bash
   ```

## Required steps after installation

After the installer finishes, complete the following setup before putting FS PBX into service. Web URL settings and a Let's Encrypt web certificate are required for every installation. Scheduled-job owner selection is also required for redundant installations.

1. **Configure the web URL and sessions to prevent URL-related 419 errors.**

   In `/var/www/fspbx/.env`, set `APP_URL` to the HTTPS address users will open, configure `SANCTUM_STATEFUL_DOMAINS` for the hostnames used to sign in, and check `SESSION_DOMAIN`. Refresh the configuration cache after saving. Follow [Error 419 — Page Expired](/docs/troubleshooting/error-419/) for the exact settings and verification steps. Review these values again whenever the web address changes.

2. **Set up a Let's Encrypt certificate for the web server.**

   Configure DNS for your web hostname and follow [Let's Encrypt SSL certificate](/docs/configuration/web-server/lets-encrypt/). Choose the guide's **Single server** or **Redundant server pair** setup as appropriate, then verify that the configured HTTPS addresses open with a trusted certificate. This step secures the FS PBX web interface.

3. **Sign in to the portal.**

   Open your configured HTTPS web address and sign in with the **Username** and **Password** printed by the installer. Confirm that the dashboard opens. See [Sign in for the first time](/docs/getting-started/first-login-and-your-first-account/#1-sign-in-for-the-first-time) for login and password-recovery guidance.

4. **Select the scheduled-job owner on redundant installations.**

   Open **Advanced → System Settings → Scheduled Jobs** and follow [Scheduled Job Server Setup](/docs/configuration/scheduled-jobs/server-ownership/). Discover and approve both servers, then explicitly select the server that will run shared jobs. Approval alone does not select an owner. Refresh both servers until they show the same **Job owner**, with one **Active on this server** and the other **Standby**. Single-server installations do not need an owner selection when the panel shows **Single server**.

## Next steps

Continue with [First Login and Your First Account](/docs/getting-started/first-login-and-your-first-account/#2-set-your-administrator-email-and-password) to update your administrator email and password and create the account for your business.

Before registering phones or connecting a carrier, follow [Network Addresses and FreeSWITCH Variables](/docs/getting-started/network-settings/) to configure your server's local and advertised addresses. The guide covers direct public IPs, NAT, multiple interfaces, and two-server installations.
