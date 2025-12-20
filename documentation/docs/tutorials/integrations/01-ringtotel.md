---
id: ringotel
title: Ringotel Provisioning Guide
slug: /additional-information/ringotel/
sidebar_position: 1
---

# Ringotel Provisioning Guide for FS PBX

## Overview

This guide walks you through provisioning Ringotel mobile and desktop softphone apps with FS PBX. Ringotel acts as a secure VoIP tunnel that routes voice traffic from softphone users to your PBX, providing centralized provisioning, enhanced security, and advanced mobility features.

### Key Benefits

- **Enhanced Security**: All traffic runs through Ringotel servers - only one IP address needs to be whitelisted in your firewall
- **Encrypted Communication**: Uses standard TLS and SRTP protocols
- **Centralized Management**: Remote configuration of softphone settings, features, BLFs, and contacts
- **Seamless Integration**: One-click integrations with PBX features
- **Cross-Platform**: Works on iOS, Android, Windows, Mac, and Linux

---

## Prerequisites

- **FS PBX Version**: 0.9.21 or later
- **Ringotel Account**: Sign up at [ringotel.co](https://ringotel.co/signup?rref=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJwaWQiOiIxNjI3NjYzNjMzNTE3MTM1NTY0NyIsImNyZWF0ZWQiOjE3NDYyMDE3NjM3NDIsImlhdCI6MTc0NjIwMTc2MywiZXhwIjoxOTAzODgxNzYzfQ.YKJFk0qeGepKj1_zX8RKD_MBQ7fSpfLzCXa2VCB8iN8)
- **Extension Credentials**: Have your extension passwords ready
- **Firewall Access**: Ability to configure firewall rules

---

## Part 1: Initial FS PBX Configuration

### Step 1: Update FS PBX

Ensure you're running FS PBX version 0.9.21 or later to access full Ringotel integration features.

```bash
# Install latest FS PBX updates
# After installation, Ringotel settings will be available
```

### Step 2: Access Ringotel Integration Settings in FS PBX

1. Log in to your FS PBX admin interface
2. Navigate to **Advanced → Mobile apps** category
3. This is where you'll configure the Ringotel API integration

### Step 3: Configure Ringotel API Integration in FS PBX

After obtaining your Ringotel credentials (from Part 2), configure FS PBX:

**Enter Ringotel Credentials:**

1. In FS PBX, go to **Advanced → Mobile apps**
2. Locate the **Ringotel Integration** section
3. Enter your **Organization ID** (from Ringotel portal)
4. Enter your **API Key** (generated in Ringotel portal)
5. Enter the **Webhook URL** (from Ringotel portal)
6. Click **Save**

**What This Integration Does:**

When properly configured, this integration enables:
- **Automatic User Sync**: When you create or modify an extension in FS PBX, it automatically updates in Ringotel
- **Real-Time Updates**: Extension changes (password, settings) are pushed to Ringotel via webhook
- **Seamless Provisioning**: Create extensions in FS PBX and they're instantly available for Ringotel provisioning
- **Bidirectional Communication**: FS PBX can notify Ringotel of configuration changes automatically

**Test the Integration:**

1. Create a test extension in FS PBX
2. Check your Ringotel admin portal to verify the extension appears
3. Modify the extension in FS PBX
4. Verify the changes reflect in Ringotel within seconds

If the integration isn't working, double-check:
- API key is entered correctly (no extra spaces)
- Organization ID matches exactly
- Webhook URL is complete and correct
- FS PBX has outbound internet connectivity
- Firewall allows outbound HTTPS connections to Ringotel

### Step 4: Locate Extension Credentials

To provision users, you'll need their SIP credentials:

1. Navigate to the **Extensions** list page
2. Click the **three dots** menu next to an extension
3. Select **SIP Credentials**
4. Note the extension number, username, and password

---

## Part 2: Ringotel Admin Portal Setup

### Step 1: Create Your Ringotel Account

1. Visit [ringotel.co](https://ringotel.co) and sign up
2. You'll be redirected to the Ringotel provisioning portal
3. The portal uses multi-tenant architecture - manage multiple customers under one account

### Step 2: Obtain Ringotel Organization Credentials

After creating your Ringotel account, you'll need to obtain two critical pieces of information for FS PBX integration:

**Organization ID and Webhook URL:**

1. Log into your Ringotel admin portal at [shell.ringotel.co](https://shell.ringotel.co)
2. Navigate to your **Organization → Settings**
3. Locate and copy your **Organization ID** (unique identifier for your organization)
4. Copy the **Webhook URL** (this will be used by FS PBX to send updates to Ringotel) commonly https://pbx.domain.com/webhook/ringotel

**Generate API Key:**

1. In the Ringotel admin portal, navigate to **Organization → API Keys** or **Settings → API Access**
2. Click **Create API Key** or **Generate New Key**
3. Give your API key a descriptive name (e.g., "FS PBX Integration")
4. Click **Create** or **Generate**
5. **IMPORTANT**: Copy the API key immediately - it will only be displayed once
6. Store the API key securely (you cannot retrieve it again later)

**Save These Credentials:**

You'll need these when configuring FS PBX integration in the next steps.

### Step 3: Create an Organization

An organization represents a company and can contain one or more PBX connections.

**Fill in the required fields:**

- **Organization Name**: Your company name (e.g., "ABC Company")
- **Domain**: Unique subdomain for user login (e.g., "abccompany")
  - This is NOT an FQDN domain
  - Users will use this to sign into Ringotel apps
  - **Cannot be changed after creation**
- **Region**: Select the region closest to your users' location
  - Determines connection routing and data storage
  - **Cannot be changed after creation**

**Optional settings** (can be changed later):
- Onboarding email language
- Tags
- Other organizational preferences

Click **Next** to create the organization.

---

## Part 3: Configure PBX Connection

### Step 1: Create Connection Profile

A Connection acts as a provisioning profile applied to all users under this connection.

**Connection Types:**
1. **PBX Connection**: Creates separate registration for each user to their PBX extension
2. **SIP Trunk Connection**: All users share a single SIP trunk (for simple call distribution)

For most deployments, use **PBX Connection**.

### Step 2: Configure Connection Settings

Fill in the following fields:

**Required:**
- **Connection Name**: Descriptive name (e.g., "Main PBX")
- **Transport Protocol**: Select one:
  - UDP (most common)
  - TCP
  - TLS (encrypted)
  - DNS-NAPTR (advanced)
- **IP Address or Domain**: Public IP or domain of your FS PBX
  - Example: `pbx.yourcompany.com` or `192.168.1.100`
- **SIP Port**: Default is 5060 (UDP/TCP) or 5061 (TLS)

**Optional Settings:**
- **Multi-tenant Mode**: Enable if your PBX has multi-tenant architecture
- **Outbound SIP Proxy**: Format: `<server_address>:<port>`
- **Audio Codecs**: Configure preferred codecs
- **Access Control List**: Set IP restrictions
- **DTMF Mode**: RFC2833, INFO, or INBAND
- **Registration TTL**: Default registration timeout
- **Custom SIP Headers**: Add custom headers if needed

Click **Create** to save the connection.

---

## Part 4: Configure Firewall

### Step 1: Whitelist Ringotel IPs

Ringotel servers connect to your PBX on behalf of users, so you must allow incoming traffic from Ringotel IPs.

1. **Find Ringotel IP Addresses**:
   - Visit the [Ringotel documentation](https://ringotel.atlassian.net/wiki/spaces/RSW/pages/1031864321)
   - Use the IP address for your selected region

2. **Configure Firewall Rules**:
   - Allow incoming traffic from Ringotel IPs to your SIP ports (TCP/UDP)
   - Allow incoming traffic to RTP ports (UDP)

3. **Important for FS PBX/FS PBX**:
   - **Do NOT** add Ringotel IP addresses to FS PBX ACL
   - This may cause outbound calls to fail
   - Ringotel works like any other SIP endpoint (IP phone/softphone)

### Step 2: Configure Fail2Ban (if applicable)

If using Fail2Ban, whitelist Ringotel IPs to prevent registration blocks.

---

## Part 5: Create and Provision Users

### Method 1: Manual User Creation

**Step-by-Step:**

1. In Ringotel admin portal, click **+ Add user**
2. Fill in the fields:
   - **Display Name**: User's full name (visible to team)
   - **User Email**: (Optional) For auto-sending provisioning instructions
   - **PBX Extension**: Extension number from your PBX
   - **SIP Username**: (Optional) If different from extension
   - **Authorization Name**: (Optional) If different from SIP username
   - **SIP Password**: Extension password from FS PBX

3. **Optional Information**:
   - Department
   - Position
   - Mobile number

4. Click **Create User**

**Result:**
- Ringotel generates separate login credentials for the user
- If email provided, provisioning instructions are sent automatically
- User receives email with QR code and login details

### Method 2: CSV Import (Bulk Provisioning)

**Prepare CSV File:**

Ringotel supports various PBX export formats. Your CSV should contain:
- Display name
- Email (optional)
- Extension number
- SIP username (if different from extension)
- SIP password

**Import Process:**

1. Click **Import Users**
2. **Select Format**: Choose your PBX format (FS PBX/FS PBX supported)
3. **Choose File**: Select your CSV file
4. Review the import preview showing number of users
5. Click **Next**
6. On the review screen:
   - Add/edit email addresses
   - Activate or deactivate users
   - Remove users you don't want to import
7. Click **Import**

**Result:**
- Users are created with auto-generated passwords
- Onboarding emails sent to provided addresses
- Passwords stored with one-way encryption

### Method 3: API Automation

For integration with existing systems, use the Ringotel Admin API:

- API Documentation: [Ringotel API Docs](https://documenter.getpostman.com/view/3136743/TVRg8W3a)
- Automate user creation when extensions are added to FS PBX
- Sync user changes in real-time

---

## Part 6: User Types

### Activated Users

- Can register with Ringotel apps
- Use all features within subscription plan
- Counted toward billing

### Deactivated Users

- Cannot use Ringotel apps
- Contact records pulled into other users' contact lists
- Useful for preconfiguring BLF contacts
- Can be activated anytime
- **Not charged**

Use deactivated users for employees with desk phones who need to appear in softphone BLF lists.

---

## Part 7: Configure PBX Features Integration

Ringotel integrates with PBX features so users can trigger them from the app without dialing feature codes.

### Step 1: Access Features Settings

1. Navigate to **Features** settings in Ringotel portal
2. Scroll down to **PBX Features** section

### Step 2: Configure Feature Codes

**Do Not Disturb (DND):**
- Specify the feature code to activate DND
- Specify the feature code to deactivate DND
- Users can toggle DND by changing availability status in the app

**Call Parking:**
- Select a pre-configured preset if it matches your PBX
- Or select **Custom** and provide your feature codes
- Users can park calls visually from the app

**Other Features:**
- Call recording
- Call pickup
- Call transfer
- Voicemail access
- Conference bridging

Refer to your FS PBX documentation for feature codes configured in your system.

### Step 3: Save Changes

Click **Save Changes** after configuring feature codes.

---

## Part 8: User Onboarding

### Step 1: Users Receive Provisioning Email

If you provided email addresses, users receive an email containing:
- Download links for iOS, Android, Windows, Mac, Linux apps
- Login credentials (domain and password)
- QR code for quick setup
- Setup instructions

### Step 2: Download Ringotel Apps

**Mobile:**
- iOS: App Store → Search "Ringotel"
- Android: Google Play → Search "Ringotel"

**Desktop:**
- Windows: Download from [ringotel.co](https://ringotel.co)
- Mac: Download from [ringotel.co](https://ringotel.co)
- Linux: Download from [ringotel.co](https://ringotel.co)

### Step 3: User Login

**Option 1: QR Code (Easiest)**
- Open Ringotel app
- Tap "Scan QR Code"
- Scan the QR code from provisioning email

**Option 2: Manual Login**
- Open Ringotel app
- Enter organization domain (e.g., "abccompany")
- Enter password from provisioning email
- Tap "Sign In"

### Step 4: Verify Connection

After login:
- Status indicator on menu icon should be **blue** (online)
- Switch to **Contacts** tab to see team members
- Real-time status indicators show who's available
- Switch to **Keypad** tab to make calls

---

## Part 9: Advanced Configuration

### BLF (Busy Lamp Field) Contacts

**Autoprovision BLF Contacts:**
1. Create deactivated users for extensions you want to monitor
2. These contacts automatically appear in Ringotel users' contact lists
3. Users see real-time status of monitored extensions

### Multiple Connections

**Use Cases:**
- Unify users from multiple PBX systems under one domain
- Migrate users from one PBX to another without disruption
- Separate production and testing environments

**Setup:**
1. Create additional connections in the same organization
2. Assign users to appropriate connections
3. All users appear in the same contact list

### Templates

Create templates to streamline connection creation:
- Reuse common settings across multiple connections
- Quickly deploy new customer organizations
- Maintain consistency across deployments

### Custom SIP Headers

Add custom SIP headers for advanced routing or tracking:
- Configure in Connection settings
- Applied to all users under that connection

---

## Part 10: Testing and Verification

### Test Inbound Calls

1. Call an extension provisioned with Ringotel
2. Verify the app rings on the user's device
3. Answer and check audio quality
4. Test call hold, transfer, and other features

### Test Outbound Calls

1. Make a call from Ringotel app using the Keypad
2. Verify call connects through PBX
3. Check caller ID presentation
4. Test DTMF functionality

### Test PBX Features

1. **DND**: Toggle availability status in app
2. **Call Parking**: Park a call and retrieve from another device
3. **Voicemail**: Access voicemail from app
4. **Call Recording**: Start/stop recording from app

### Test Presence and Chat

1. Verify real-time status updates between users
2. Send test chat messages
3. Test group chats
4. Verify message delivery and read receipts

---

## Part 11: Troubleshooting

### Users Can't Register

**Check:**
- Firewall rules allow Ringotel IPs
- SIP credentials are correct in Ringotel portal
- Extension exists and is enabled in FS PBX
- Transport protocol matches PBX configuration
- SIP port is correct

**For Multiple Registration Issues:**
- Check FS PBX Sofia profile settings
- Verify `multiple-registrations` parameter is enabled
- Check `max-registrations` limit

### Outbound Calls Fail

**Check:**
- Ringotel IPs NOT added to FS PBX ACL
- Outbound routes configured correctly in FS PBX
- User has correct dial permissions
- Outbound SIP proxy configured if needed

### Audio Quality Issues

**Check:**
- Network bandwidth and latency
- Codec configuration in Connection settings
- RTP ports open in firewall
- Consider enabling TLS/SRTP for encryption

### Push Notifications Not Working

**Check:**
- Users granted notification permissions
- App is not force-closed (iOS)
- Battery optimization disabled (Android)
- Ringotel has reliable push service - contact support if issues persist

### Feature Codes Don't Work

**Check:**
- Feature codes in Ringotel match FS PBX configuration
- Features enabled in FS PBX for the extensions
- Connection profile feature settings saved correctly

---

## Part 12: Management and Maintenance

### Password Resets

If a user loses their Ringotel credentials:

1. Log into Ringotel admin portal
2. Find the user in the user list
3. Click **three dots** → **Reset Password**
4. New password is generated
5. Share new credentials with user (QR code or manual)

**Note:** Passwords are one-way encrypted and cannot be retrieved.

### User Management

**Activate/Deactivate Users:**
- Click icon on user record in admin portal
- Changes take effect immediately
- Deactivated users don't count toward billing

**Update User Details:**
- Edit display name, department, position
- Update email address
- Modify extension if moved in PBX

**Delete Users:**
- Remove users no longer needed
- Frees up license for new users

### Monitoring

**Admin Portal Dashboard:**
- View active users
- Monitor registration status
- Check connection health
- Review usage statistics

---

## Part 13: Ringotel Integrations

Ringotel extends your PBX capabilities with one-click integrations to CRMs, cloud contacts, SMS providers, and business tools. These integrations automate workflows, improve productivity, and provide seamless access to customer data.

### Integration Categories

**Available Integrations:**

1. **CRM Systems**
   - Salesforce
   - HubSpot
   - Pipedrive
   - Zoho CRM
   - Microsoft Dynamics 365
   - Freshdesk
   - VTiger
   - GoHighLevel CRM
   - ActiveCampaign CRM
   - Wealthbox
   - Clio CRM
   - Halo PSA
   - Intercom

2. **Cloud Contacts**
   - Google Contacts (Google Workspace)
   - Microsoft 365
   - Zoho PhoneBridge

3. **SMS/MMS Providers**
   - Telnyx
   - Twilio
   - Bandwidth.com
   - BulkVS
   - Skyetel
   - Inteliquent

4. **Video Conferencing**
   - Jitsi (built-in integration)

5. **Automation & Webhooks**
   - Zapier (via webhooks)
   - Make (formerly Integromat)
   - n8n
   - Custom webhooks

6. **Developer APIs**
   - Admin API
   - Messaging API
   - RESTful APIs

---

### CRM Integrations

#### Benefits of CRM Integration

Integrate your IP PBX with various CRMs in just a few clicks and automate routine work for users. CRM integrations provide:

- **Caller Identification**: Automatically display customer information from CRM on incoming calls
- **Call Logging**: Automatically log all calls and call recordings to CRM
- **Contact Management**: Access and create CRM contacts directly from Ringotel app
- **Click-to-Call**: Make calls to CRM contacts with one click
- **Call History Sync**: View complete call history in CRM
- **Activity Tracking**: Track all communication activities in CRM

#### How to Enable CRM Integration (General Process)

**Step 1: Access Integration Settings**

1. Log into Ringotel admin portal
2. Navigate to your **Organization → Integrations** tab
3. Find your CRM in the available integrations list

**Step 2: Configure Integration**

1. Click **Enable Integration** on your CRM panel
2. Enter your CRM domain or connection details
   - Example for Pipedrive: `[your_domain].pipedrive.com`
3. Click **Save & Continue**

**Step 3: Authenticate**

1. You'll be redirected to your CRM's authentication page
2. Log in to your CRM account
3. Accept the requested permissions for Ringotel
4. You'll be redirected back to Ringotel portal

**Step 4: Map Users**

1. Map Ringotel users to corresponding CRM users
2. Users with matching email addresses are automatically mapped
3. Manually map users with different email addresses
4. Click **Save** to complete integration

**Step 5: User Access**

Users can now:
- See CRM contacts in Ringotel app
- View caller information on incoming calls
- Create new contacts in CRM from the app
- Access call history and recordings in CRM

#### Example: Pipedrive Integration

Integrating Ringotel with Pipedrive simplifies the calling experience for users by providing caller identification, easy access to CRM records

### Documentation

- **Ringotel Wiki**: [ringotel.atlassian.net/wiki](https://ringotel.atlassian.net/wiki)
- **User Manual**: [kb.ringotel.net](https://kb.ringotel.net)
- **API Docs**: [documenter.getpostman.com/view/3136743/TVRg8W3a](https://documenter.getpostman.com/view/3136743/TVRg8W3a)
- **FS PBX Forums**: [pbxforums.com](https://www.pbxforums.com)

### Support Channels

**Ringotel Support:**
- **24/7 Ticketing System**: Submit tickets anytime
- **Live Chat**: Available 8:00 AM - 10:00 PM UTC
- **Emergency Phone**: Available for critical issues
- **Email**: support@ringotel.co

**Response Times:**
- Critical issues: Immediate response
- General inquiries: Within 24 hours
- Feature requests: Reviewed and prioritized

### Video Tutorials

- **FS PBX Integration**: Search YouTube for "FS PBX Ringotel"
- **Getting Started**: Available on ringotel.co
- **Feature Demos**: Available on Ringotel YouTube channel

---

## Quick Reference Commands

### Finding Extension Passwords in FS PBX
```
Navigate to: Extensions → Three dots menu → SIP Credentials
```

### Ringotel IP Whitelist
```
Configure firewall rules for region-specific Ringotel IPs
Documentation: ringotel.atlassian.net/wiki/spaces/RSW/pages/1031864321
```

### CSV Import Format
```csv
Display Name,Email,Extension,Username,Password
John Doe,john@company.com,1001,1001,SecurePass123
Jane Smith,jane@company.com,1002,1002,SecurePass456
```

---

## Best Practices

1. **Start Small**: Test with a few users before full deployment
2. **Document Feature Codes**: Keep a reference of your FS PBX feature codes
3. **Use Templates**: Create connection templates for consistency
4. **Regular Backups**: Export user lists periodically
5. **Monitor Registrations**: Check registration status regularly
6. **Update Software**: Keep FS PBX and Ringotel apps updated
7. **User Training**: Provide basic training on Ringotel features
8. **Security**: Use TLS/SRTP when possible, regularly review firewall rules

---

## Conclusion

You've successfully provisioned Ringotel with FS PBX! Your users now have access to feature-rich softphone apps across all their devices with centralized management, enhanced security, and seamless PBX integration.

For ongoing support and advanced configurations, refer to the support resources above or contact the Ringotel team directly.

**Happy provisioning!**
