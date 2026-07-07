---
id: phonebooks
title: Phonebooks
slug: /phone-provisioning/phonebooks
sidebar_position: 2
---

# Phonebooks

Phonebooks let you push a **directory** to your desk phones. Your users see it on the phone under **Contacts** / **Directory** and can scroll or search it to place a call — with nothing typed into each phone by hand.

A phonebook can contain:

- **Internal extensions** — everyone in your account, pulled in automatically.
- **Contacts** — people you add yourself (customers, vendors, off-system numbers).
- **Both** — extensions plus your own contacts in one list.

---

## Create a phonebook

1. Open **Applications → Phonebooks**.
2. Click **Create**.
3. Give it a **Name** (for example, *Company Directory*).
4. Choose what goes in it (see below).
5. Click **Save**.

---

## Choose what's in the directory

On the phonebook screen, under **“What's in this directory?”**:

- **Internal extensions** — turn this **on** to include every enabled extension in your account (name + extension number). Turn it **off** if this phonebook should only contain the contacts you add.
- **Contacts** — click **+ Add contact**, enter a **First name**, **Last name**, and **Phone number**, then **Add**. Repeat for each person. Use the pencil to edit or the trash icon to remove.

This gives you three simple options:

| You want… | Internal extensions | Contacts |
|---|---|---|
| A staff directory | **On** | — |
| An external / customer list | **Off** | Add contacts |
| Everything in one list | **On** | Add contacts |

:::info Contacts belong to the phonebook you're editing
Adding a contact here does **not** change any other phonebook. Each phonebook keeps its own list.
:::

Click **Save** when you're done.

---

## Send a phonebook to your phones

There are two ways to get a phonebook onto phones.

### Option A — Make it the account default (easiest)

1. Edit the phonebook.
2. Turn on **Account default**.
3. Save.

Every phone set to **“Use account default”** (the standard setting) will download it. This is the simplest way to give your whole account one directory.

### Option B — Assign specific phonebooks to a phone

1. Edit the **device** on the Devices page.
2. Open the **Phonebook** tab.
3. Choose **Custom for this device**.
4. Pick one or more phonebooks.
5. Save.

Use this when a particular phone needs a different directory than the account default.

---

## Preview what the phone will get

- On the **phonebook** screen, click **Preview directory** to see the combined, sorted list.
- On the **Devices** page, click the **preview** (magnifying glass) icon on a device to see the exact files that phone downloads — including its directory.

The directory is sorted alphabetically by **last name**, matching how the phone displays it.

---

## How different phones handle multiple phonebooks

Phone brands differ, and the device **Phonebook** tab shows a reminder for the selected phone:

- **Grandstream** phones download **one** directory. If you assign several phonebooks, they are **merged into a single combined list** on the phone.
- **Yealink** phones show **each phonebook as its own directory** (up to the phone's limit).

Either way, the phonebooks you assign are what the phone receives.

---

## When changes show up on the phone

Phones don't pull the directory the instant you save — they download it **on a schedule or when they reboot**.

:::tip
To see a change right away, **reboot the phone**. Otherwise it updates on the phone's next scheduled download.
:::

---

## Good to know

- **A contact needs a phone number.** Contacts without a number are skipped.
- **Extensions hidden from the directory are left out.** If an extension is set not to appear in the directory, it won't be included even when *Internal extensions* is on.
- **Duplicates are removed.** If the same name and number appear twice (for example, across merged phonebooks), the phone shows it once.
- **Enable vs. disable.** Turn a phonebook **off** (Enabled = Off) to stop serving it without deleting it.
