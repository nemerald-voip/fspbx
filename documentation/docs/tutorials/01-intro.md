---
id: intro
title: Introduction
slug: /intro
sidebar_position: 1
description: FS PBX is a modern, open-source, multi-tenant PBX for VoIP providers hosting customer accounts and organizations running their own phone systems.
---

# What is FS PBX?

FS PBX is a modern, open-source, multi-tenant PBX built on FreeSWITCH, Laravel, and Vue. It offers VoIP providers and organizations that run their own phone systems an alternative to FusionPBX, FreePBX, and 3CX.

## Host phone service for your customers

As a VoIP provider, you can serve many businesses from one installation. Each customer gets a separate account with its own numbers, extensions, users, and call routing. Two customers can both use extension 100 for reception, and each can set its own opening hours and holiday destinations. [How customer accounts work](04-getting-started/04-domains-multitenancy-101.md)

You can put your own branding on the customer portal and give customer administrators access to manage their accounts. When you roll out desk phones, reusable key templates let you give every receptionist at a customer site the same line and speed-dial buttons. [Portal branding](05-configuration/01-branding.md) · [Phone provisioning](06-phone-provisioning/01-overview.md)

## Run your own in-house PBX

If you manage phone service for your own organization, you can start with one account, connect your SIP carrier, and assign extensions to staff. From the web dashboard, your team can set up a new employee's phone, change a greeting, or update the holiday schedule. [Connect a carrier](04-getting-started/09-gateways.md)

For example, your main number can offer “Press 1 for Sales” and ring the sales team's phones during office hours. After closing, the same number can send callers to voicemail and email the team when someone leaves a message. [Set up Business Hours](05-configuration/18-business-hours.md) · [Voicemail basics](04-getting-started/06-voicemail-basics.md)

## Get started

For a new server, start with the [installation guide](02-installation.md). Once FS PBX is running, follow [First Login and Your First Account](04-getting-started/01-first-login-and-your-first-account.md) to set up your first customer or your own organization.
