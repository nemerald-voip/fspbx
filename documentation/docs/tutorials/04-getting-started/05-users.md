---
id: users
title: Users
slug: /getting-started/users
sidebar_position: 5
---

# Users

In FS PBX, a **User** is a person’s login to the web dashboard. Users control **who can sign in**, **what they can see**, and **what they’re allowed to change**.

A User is not the same thing as an Extension.

---

## What a User is (plain English)

A **User** is used for:

* Logging into the **FS PBX web UI**
* Managing settings based on **permissions/roles**
* Accessing user features (depending on your setup), like:

  * voicemail UI
  * call history / recordings access
  * admin pages (extensions, routing, devices, etc.)

Think: **User = web account + permissions**.

---

## Users vs Extensions (most common confusion)

### Extension

* A **phone account** used for calling (SIP registration)
* Has things like: extension number, voicemail box, call routing settings
* Phones and softphones register using **extension credentials**

### User

* A **dashboard login** for a person (admin, receptionist, manager, agent, etc.)
* Has things like: username/email, password, permissions, domain access
* Does **not** need to exist for calling to work

In many setups, you link them together:

* **One User ↔ one Extension** (common)
  But they don’t have to be 1:1.

---

## Do I need to create a User for every phone/extension?

**No.** You only need a User if someone needs to:

* log into the FS PBX dashboard, or
* use web features that require authentication

If a phone is configured manually (or via provisioning) and only needs to place/receive calls, it can work fine with just an Extension.

---

## Why Users matter in a multi-tenant system (Domains)

FS PBX is multi-tenant, so Users are typically tied to:

* a specific **Domain** (tenant/company), and
* a set of **permissions**

This prevents customer A from seeing or editing customer B’s PBX data.

---

## Common types of Users

* **Domain Admin**: manages their own company (extensions, routing, voicemail settings, etc.)
* **Receptionist / Manager**: limited access (directory, call history, recordings, etc.)
* **Call Center Supervisor / Agent**: queue tools and reporting (if enabled)
* **System Admin**: manages the whole platform across Domains (service provider role)

---

## What you typically do with Users

1. **Create the User**

* name, email/username
* password (or invite flow, depending on your setup)

2. **Assign permissions / role**

* what menus they can access
* what actions they can perform (view/edit/delete/etc.)

3. **Assign Domain access**

* most users belong to one Domain
* system admins may access multiple Domains

4. *(Optional)* **Link a User to an Extension**

* convenient for “My Extension” style features
* helps map a dashboard user to a calling identity
