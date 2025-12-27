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

2. **Configure a custom domain and Let's Encrypt certificate (OPTIONAL)**

    Follow the steps in this article to configure your custom domain.
   
    [How to Secure FS PBX with a Let’s Encrypt SSL Certificate](05-configuration/10-web-server/lets-encrypt-certificate.md)

3. **Troubleshooting Error 419 (Page Expired)**

    Read [this article](08-troubleshooting/02-error-419.md) to learn how CSRF tokens protect your app and how to configure your server to prevent 419 responses.
   
### Usage
After completing the installation steps, you can access the application at your domain.
