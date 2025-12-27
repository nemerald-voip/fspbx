---
id: voipms-trunk
title: VoIP.MS
slug: /trunk-providers/voipms/
sidebar_position: 1
---

# VoIP.MS 
We will be using VoIP.ms as our SIP trunk provider in this walk through, and it is assumed you're aware how to point a DID to a SIP User account. 

This walkthrough will guide you through how to setup a DID to an extension.

This setup also assumes you will be using a Softphone, and have some experience in setting it up.

## 1. Setting up a gateway
This assumes that you've got FS PBX setup on a host.

### Setup
Gateway - Accounts > Gateways

   * 1. Click "Add" in top right
   * 2. Gateway: newyork1.voip.ms (this describes the name of the gateway, it can be the fqdn)
   * 3. Username: XXXXXX_user
   * 4. Password: (obvious need of keying the password you chose on voip.ms)
   * 5. Proxy: newyork1.voip.ms:5080 (or fqdn of peer)
   
   * 6. Click "Save" top right.

Extensions - Accounts > Extensions

   * 1. Click "Create" in top right
   * 2. Basic Info will pop up, fill out all the required information, feel free to choose an extension in e.g. 1xx, 1xxx.
   * 3. Following, basic info will have more options after previous submit, change any settings here that you need
   * 4. Choose "SIP Credentials", click Show credentials, jot down the domain, username, and password
   
   * 5. Click "Save" top right.

 Inbound Routes - Dialplan > Phone Numbers

   * 1. Click "Create" in top right
   * 2. Settings: Key in number in question, along with country code, 
   * 3. Under "Call Routing" click Add
   * 4. Under "Choose Action", choose extension, and in "Target" choose the extension you created
   
   * 5 Click "Save" on the bottom right of the modal.

 Outbound Routes - Dialplan > Outbound Routes
 
   * 1. Click "Add" in top right
   * 2. Under "Gateway" choose the gateway in question
   * 3. Dialplan Expression, an empty text field, but with the drop down choose "11 Digits Long Distance"
   * 4. Tick the "Enabled" to the right, give it a description, or allow the system to choose its own.
   
   * 5. Click "Save" on the top right
   
This should give you inbound, and outbound calling capabilities.

## 2. Troubleshooting
* Github (https://github.com/nemerald-voip/fspbx/issues)

## Conclusion
By following these steps, you will have a fully working system.