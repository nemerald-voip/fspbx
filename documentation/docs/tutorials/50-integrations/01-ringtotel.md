---
id: ringotel
title: Ringotel Provisioning Guide
slug: /integrations/ringotel/
sidebar_position: 1
---

# Ringotel Provisioning Guide for FS PBX

## Overview

This guide explains how to provision Ringotel mobile and desktop softphone apps with FS PBX.

Ringotel allows you to deploy and manage softphone users through a centralized cloud platform while keeping provisioning simple from the FS PBX dashboard. Once configured, users can sign in to Ringotel apps across multiple devices and access PBX-integrated calling features with minimal manual setup.

### Key Benefits

- **Simple App Provisioning**: Quickly deploy and manage Ringotel apps directly from the FS PBX dashboard without complicated manual configuration
- **Centralized User Management**: Manage users, app settings, contacts, and related options from one place inside FS PBX
- **Seamless PBX Integration**: Ringotel works smoothly with FS PBX features, making it easy for users to access calling, messaging, and other tools
- **Faster Onboarding**: Get new users up and running quickly with a streamlined setup process and minimal IT involvement
- **Cross-Platform Experience**: Deliver the same connected experience across iOS, Android, Windows, Mac, and Linux

## Prerequisites

Before you begin, make sure you have:

- A **Ringotel account**
- Access to the **FS PBX administrator dashboard**
- Ability to update **firewall rules** if needed
- Your public PBX domain available for webhook configuration

---

## Part 1: Initial Configuration

### Step 1: Create Your Ringotel Account

1. Visit [ringotel.co](https://ringotel.co/signup?rref=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJwaWQiOiIxNjI3NjYzNjMzNTE3MTM1NTY0NyIsImNyZWF0ZWQiOjE3NDYyMDE3NjM3NDIsImlhdCI6MTc0NjIwMTc2MywiZXhwIjoxOTAzODgxNzYzfQ.YKJFk0qeGepKj1_zX8RKD_MBQ7fSpfLzCXa2VCB8iN8) and create your account
2. After signup, you will be redirected to the Ringotel provisioning portal
3. Ringotel uses a multi-tenant architecture, so you can manage multiple customer organizations from one account

### Step 2: Access Ringotel Settings in FS PBX

1. Log in to your FS PBX admin interface
2. Navigate to **Advanced → Ringotel App Settings**
3. Open the Ringotel-related settings area

This is where you will connect FS PBX to your Ringotel account.

### Step 3: Obtain Your Ringotel API Credentials

After creating your Ringotel account, you will need to generate an API key and configure a webhook.

1. Log in to the Ringotel admin portal at [shell.ringotel.co](https://shell.ringotel.co)
2. Go to **Menu → Integrations → API Settings**
3. Generate your **API Key**
4. In the **Webhook URL** field, enter:

   `https://pbx.domain.com/sms/ringotelwebhook`

Replace `pbx.domain.com` with your actual FS PBX domain.

### Step 4: Configure the Ringotel Integration in FS PBX

After you have your API key, return to FS PBX and enter it into the Ringotel settings.

1. In FS PBX, go to **Advanced → Ringotel App Settings**
2. Open the **API Key** setting
3. Paste in your Ringotel API key
4. Click **Save**

### What This Integration Does

Once configured, the integration allows FS PBX and Ringotel to work together automatically.

- **Automatic User Sync**: Creating or updating extensions in FS PBX can sync those changes to Ringotel
- **Real-Time Updates**: Changes such as passwords or user settings can be pushed to Ringotel automatically
- **Simplified Provisioning**: Users created in FS PBX can be made available for Ringotel app provisioning quickly
- **Ongoing Coordination**: FS PBX can notify Ringotel when relevant configuration changes occur

### Test the Integration

After saving your API key, test the connection:

1. Create a test extension in FS PBX
2. Provision a Ringotel user for that extension
3. Check the Ringotel admin portal and confirm the user appears
4. Make a small update to the extension in FS PBX
5. Confirm the update is reflected in Ringotel

If the integration is not working, verify the following:

- The API key was entered correctly
- The webhook URL is complete and uses the correct PBX domain
- FS PBX has outbound internet access
- Your firewall allows outbound HTTPS connections to Ringotel

---

## Part 2: Activate the FS PBX Domain for Ringotel Integration

FS PBX can create the Ringotel organization for you directly from the dashboard.

1. Go to **Advanced → Ringotel App Settings**
2. Find the domain you want to activate
3. Click **Activate**
4. Fill in the required organization details
5. Click **Next** to create the organization in Ringotel

### Required Fields

- **Organization Name**: The company or customer name, such as `ABC Company`
- **Unique Organization Domain**: A unique login domain for the organization, such as `abccompany`
  - This does **not** need to be a fully qualified domain name
  - Users will enter this domain when signing in to Ringotel
  - This value **cannot be changed later**
- **Region**: Choose the region closest to your users
  - This affects service routing and data location
  - This value **cannot be changed later**

---

## Part 3: Create a Connection Profile

The connection profile defines how Ringotel connects to your FS PBX.

### Required Settings

- **Connection Name**: A descriptive name such as `Main PBX`
- **Transport Protocol**: Choose one of the following:
  - UDP
  - TCP
  - TLS
  - DNS-NAPTR
- **IP Address or Domain**: The local domain or address for the tenant in FS PBX
  - Example: `pbx.yourcompany.com`
- **SIP Port**: Usually `5060` for UDP/TCP or `5061` for TLS

### Optional Settings

- **Outbound SIP Proxy**: If needed, enter it in this format:

  `your_server_address:port`

  Example: `pbx.yourcompany.com:5060`

- **Audio Codecs**: Configure your preferred codecs if needed

Click **Create** to save the connection profile.

---

## Part 4: Understand Ringotel User Types

Ringotel supports two main user types.

### Activated Users

Activated users are regular app users.

- Can sign in to Ringotel apps
- Can use features included in your subscription
- Count toward billing

### Contact Users

Contact users are non-licensed directory entries.

- Cannot sign in to Ringotel apps
- Can appear in contact lists and BLF-style views for other users
- Can be activated later if needed
- Are **not** billed

Use contact users for employees who do not need the app themselves but should still appear in other users’ contact and presence views.

---

## Part 5: Configure PBX Feature Integration

Ringotel can work with PBX-integrated features so users can access functionality from the app more easily.

Depending on your deployment, this may include:

- Internal contacts and presence visibility
- BLF-related contact views
- Calling features connected to FS PBX
- Messaging and other integrated user tools where supported

Review your organization and connection settings to make sure users are assigned correctly and can access the features you want to expose through the app.

---

## Part 6: User Onboarding

### Step 1: Users Receive a Provisioning Email

If you entered user email addresses during setup, Ringotel can send a provisioning email that may include:

- Download links for supported apps
- Login credentials
- A QR code for quick setup
- Basic sign-in instructions

### Step 2: Download the Ringotel App

Users can install Ringotel on the platform of their choice.

**Mobile**
- iPhone and iPad: App Store
- Android: Google Play

**Desktop**
- Windows
- Mac
- Linux

Desktop downloads are available from [ringotel.co](https://ringotel.co).

### Step 3: User Login

Users can sign in in one of two ways.

#### Option 1: QR Code

1. Open the Ringotel app
2. Choose **Scan QR Code**
3. Scan the QR code from the provisioning email

#### Option 2: Manual Login

1. Open the Ringotel app
2. Enter the organization domain
3. Enter the assigned password
4. Tap or click **Sign In**

### Step 4: Verify the Connection

After login, confirm that the user is connected successfully.

- The status indicator should show as online
- The **Contacts** tab should display team members if configured
- The **Keypad** tab should allow outbound calling
- Presence and related PBX-integrated features should appear if enabled

---

## Conclusion

You have now configured Ringotel provisioning with FS PBX.

With the integration in place, you can create and manage users from the FS PBX dashboard, streamline app onboarding, and provide a consistent softphone experience across mobile and desktop platforms.

For advanced setup or troubleshooting, refer to your Ringotel portal settings and your FS PBX system configuration.