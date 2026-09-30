---
id: dynamic-routes
title: Dynamic Routes
slug: /configuration/dynamic-routes/
sidebar_position: 17
---

# Dynamic Routes

Dynamic Routes sends a call to a destination based on the phone number the caller originally dialed. Each route has an internal extension, a list of number-to-destination rules, and a fallback for calls that do not match any rule.

This is useful when several phone numbers share a Virtual Receptionist, Call Flow, or other common entry point, but need different destinations later in the call. You can maintain one shared menu and use the original number to choose the appropriate team.

## How it works

1. An incoming call reaches one of the account's phone numbers.
2. The call is transferred to the Dynamic Route's internal extension, either directly or after another destination such as a Virtual Receptionist.
3. FS PBX checks the route's **Match Rules** from top to bottom against the **Original DID**.
4. The first matching rule sends the call to its destination.
5. If no rule matches, the call uses the **Fallback Destination**.

**Original DID** means the number the customer called, rather than the customer's caller ID. The route's own **Extension** is the internal number used to enter this decision point.

For example, a caller dials `+12025550101`, reaches an IVR at `5000`, and presses a key that transfers to Dynamic Route `9500`. The route checks `+12025550101`; it does not look for `5000` or `9500` in the match rules.

The fallback applies when no rule matches. Once a rule selects a destination, that destination's own busy, timeout, and no-answer handling applies. An unanswered matching destination does not make Dynamic Routes try the next rule.

## Before you begin

- Select the account that owns the incoming phone numbers and destinations. Dynamic Routes are account-specific, including when managed by a superadmin.
- Confirm that inbound calls already reach the account. See [Phone Numbers](/docs/getting-started/phone-numbers/) for the incoming-number setup.
- Create the target extensions, ring groups, queues, or other destinations you intend to use.
- Have access to **Applications > Dynamic Routes**. The permissions are `dynamic_route_view`, `dynamic_route_create`, `dynamic_route_update`, and `dynamic_route_delete`.
- Use a unique internal extension for the route. FS PBX suggests an available number between `9500` and `9549`; you can choose another unused numeric extension.

Dynamic Routes uses the saved FreeSWITCH dialplan to make its routing decision. There is no separate Dynamic Routes background worker to configure.

## Create a Dynamic Route

### 1. Open the editor

Go to **Applications > Dynamic Routes** and click **Create**. The page is also available at `/dynamic-routes` on your PBX.

Under **Route Settings**, enter:

| Field | Configuration |
| --- | --- |
| **Name** | A descriptive name, such as `Branch Sales Routing`. |
| **Dynamic Route Enabled** | Leave enabled to make the route available for calls and destination selection. |
| **Extension** | The unique internal number used to reach this route, such as `9500`. Use digits only. |
| **Lookup Source** | Select **Original DID**, the currently supported lookup source. |
| **Description** | Optional notes explaining where this route is used. |

The internal extension must not conflict with another extension-like destination in the account. If the suggested range is full, enter another available number.

### 2. Add match rules

Under **Match Rules**, add one row for each original number that needs its own destination:

| Field | Configuration |
| --- | --- |
| **Match Value** | The incoming phone number, preferably in full international format such as `+12025550101`. |
| **Destination Type** | The kind of destination, such as **Ring Group**, **Extension**, or **Contact Center**. |
| **Destination** | The specific target within that type. |

Use the list controls to add, remove, and reorder rows. Rules are evaluated from top to bottom, and at least one rule is required. Each match value must be unique within the route, including equivalent formats of the same phone number.

Available destination types include:

- **Extension**, **Voicemail**, **Ring Group**, and **Contact Center**.
- **Virtual Receptionist**, **Business Hours**, **Schedule**, and **Call Flow**.
- **Bridge**, **Fax**, **Play Greeting**, **Conferences**, **Conference Centers**, and **AI Agent**, where the relevant feature and targets are available.
- **Check Voicemail**, **Company Directory**, and **Hang up**. These actions do not require a separate target.

For an external destination, configure a **Bridge** and select it. To apply opening hours after identifying the DID, choose **Business Hours** or **Schedule** as the destination.

### 3. Choose a fallback

Under **Fallback Destination**, select a **Destination Type** and, when required, a **Destination**.

Choose where callers should go when their original number is missing or does not match any rule. A reception ring group or shared voicemail can provide a useful fallback.

New routes default to **Hang up**. Change this if unmatched calls should continue to another destination.

### 4. Save

Click **Save** and resolve any validation errors. The route then appears in the list with its extension, lookup source, number of match rules, and enabled status.

Saving creates or updates the route's generated dialplan and clears the relevant local dialplan cache. Configure the route through **Dynamic Routes**; manual changes to its generated dialplan can be overwritten by a later save.

Creating a route does not automatically send any phone numbers to it. Connect it to your call path as described below.

## Connect the route to incoming calls

### Route a phone number directly

1. Open **Phone Numbers** and edit the incoming number.
2. In **Call routing**, choose **Dynamic Route** under **Choose Action**.
3. Select the route under **Target**, for example `9500 - Branch Sales Routing`.
4. Save the phone number.
5. Repeat for each phone number that should use the same route.

Only enabled Dynamic Routes from the current account appear in the target list. Review the full call-routing sequence so the call reaches the Dynamic Route at the intended point.

### Route a Virtual Receptionist key

1. Open the shared **Virtual Receptionist** and edit or add the required menu key.
2. Set **Action** to **Dynamic Route**.
3. Select the route under **Target** and save the key.
4. Confirm that the relevant incoming phone numbers route to this Virtual Receptionist.

The selected key is the same for every caller. Dynamic Routes then uses the original DID to choose that caller's destination.

You can also select **Dynamic Route** as a Call Flow's default or alternate destination, or in another supported destination selector. Avoid routing a destination back into the same path indefinitely.

## Example: one menu for two branches

Suppose two branches share the same greeting and menu:

> Press 1 for sales. Press 2 for support.

Sales calls should reach the branch whose phone number was dialed, while support calls go to one shared team.

Create a Dynamic Route named **Branch Sales Routing**, using an available extension such as `9500`:

| Original number | Destination Type | Destination |
| --- | --- | --- |
| `+12025550101` | Ring Group | East Branch Sales |
| `+12025550102` | Ring Group | West Branch Sales |

Set the fallback to a shared reception ring group. These are example numbers; enter your actual DIDs and select your existing ring groups.

Then configure:

1. Both phone numbers route to the same Virtual Receptionist.
2. IVR key **1** uses **Dynamic Route → Branch Sales Routing**.
3. IVR key **2** routes directly to the shared support destination.

When a customer calls the East number and presses 1, the East sales group rings. Calling the West number and pressing the same key rings the West sales group. The shared menu does not need a separate copy for each branch.

## Example: one business-hours schedule for 50 DIDs

A client has 50 phone numbers. Every number follows the same company opening hours and holidays, but each needs its own destination when the company is open.

With a separate Business Hours entry for each DID, you maintain 50 copies of the same schedule. Each copy sends calls to that number's individual destination. Changing the company hours or adding a holiday then requires updating all 50 entries.

Dynamic Routes lets you share one Business Hours entry while keeping each DID's destination:

**Incoming DID → Company Business Hours → Dynamic Route → That DID's destination**

| Configuration | Separate Business Hours entries | Shared Business Hours with Dynamic Routes |
| --- | --- | --- |
| Opening hours | Repeated in 50 entries | Maintained in one entry |
| Holiday changes | Applied to each of the 50 entries | Applied once to the shared entry |
| Individual DID destinations | Configured inside each Business Hours entry | Listed as rules in one Dynamic Route |

### Set it up

1. Create a Dynamic Route named **Company DID Routing**. Add a match rule for each of the client's 50 phone numbers and select its destination. Set an appropriate fallback for any unmatched number.
2. Create one **Business Hours** entry named **Company Business Hours**. Set the company's **Time Zone** and regular **Time Slots**.
3. For each open-hours time slot, set **Choose Action** to **Dynamic Route** and **Target** to **Company DID Routing**.
4. Configure **Closed Hours** for calls outside the normal schedule, such as a shared after-hours voicemail. Add company holidays and their routing overrides under **Holidays** on this same Business Hours entry.
5. Edit each of the 50 **Phone Numbers**. Under **Call routing**, set **Choose Action** to **Business Hours** and **Target** to **Company Business Hours**, then save.

The Dynamic Route's match rules might look like this:

| Match Value | Destination Type | Destination |
| --- | --- | --- |
| DID 1 | Ring Group | Ring Group A |
| DID 2 | Ring Group | Ring Group B |
| DID 3 | Extension | 100 |
| … | … | … |
| DID 50 | Virtual Receptionist | IVR 500 |

Replace `DID 1` through `DID 50` with the actual incoming phone numbers; these labels are only placeholders.

Business Hours evaluates the schedule and holiday overrides first. When the applicable action sends the call to **Company DID Routing**, the Dynamic Route reads the original number and sends it to the corresponding ring group, extension, or IVR. Closed-hours and holiday actions follow the destinations you configured for those conditions.

To change company opening hours or add a holiday, edit **Company Business Hours** once. All 50 numbers use that shared configuration. To change only one DID's destination, edit its rule in **Company DID Routing**.

Test several DIDs during an open period, then verify the closed-hours and holiday paths. Confirm both that the shared schedule is applied and that open-hours calls still reach their individual destinations.

## Phone-number matching

Enter a complete international number with a leading `+` when possible. FS PBX recognizes valid phone numbers and saves them in international format. The generated rule matches these common forms of that number:

- International format with a leading `+`.
- International format without the `+`.
- National digits, including a national trunk prefix where applicable.
- National significant digits without the country code or trunk prefix.

For example, the rule `+12025550101` matches `+12025550101`, `12025550101`, and `2025550101`. You do not need three separate rows. Attempting to add equivalent numbers to the same route produces **Each match value must be unique.**

Numbers entered without `+` are interpreted using the account's `country` setting, with `US` as the fallback when no country is configured. A number entered with an explicit international calling code uses that code. Verify the account country before entering national-format numbers.

Values that are not recognized as valid phone numbers, such as the internal identifier `9005`, remain exact literal matches. A value such as `9005` only helps if the call's **Original DID** actually contains `9005`.

**Match Value** is not a regular-expression or wildcard field. Enter the number itself, without expressions such as `^...$` or a wildcard suffix. The generated phone-number variants are digit strings, optionally preceded by `+`; arbitrary carrier prefixes and punctuation in the live DID are not automatically removed.

Some versions of the form still describe all matching as exact, including the leading plus sign. Recognized phone numbers use the equivalent-format behavior above. After upgrading an older installation, open and save existing Dynamic Routes to regenerate their matching rules.

## Verify the routing

### Test each DID and the fallback

1. Call the first DID from an external phone and follow the configured menu path. Confirm that its intended destination receives the call.
2. Repeat for every DID in the route. Using the same external caller number helps demonstrate that the decision follows the called number.
3. Route a separate test DID through the same path without adding it to the match list. Confirm that it reaches the fallback.
4. Test the chosen destination's no-answer behavior separately. It should follow that destination's configuration.

Dialing `9500` from an internal extension does not reproduce an inbound call to one of the listed DIDs. It may use the fallback because the expected original DID is absent or different. Use actual inbound calls to verify carrier number matching.

### Inspect the original DID on a live call

If a test call takes the fallback unexpectedly, inspect the caller's active channel on the PBX handling that call:

```bash
fs_cli -x 'show channels'
```

Identify the incoming caller channel UUID. Replace `CALL_UUID` below with that UUID, then run:

```bash
fs_cli -x 'uuid_getvar CALL_UUID caller_destination'
```

**Expected result:** the original incoming number in a format matched by your rule. This is the value Dynamic Routes actually tests.

The standard inbound `caller-details` dialplan sets `caller_destination` from the SIP To-header user. To compare it with the incoming SIP value, run:

```bash
fs_cli -x 'uuid_getvar CALL_UUID sip_to_user'
```

If `caller_destination` is blank or contains an unexpected value, inspect the inbound route and any custom dialplan that changes it. A provider using a different number in the To header can require inbound dialplan adjustments before Dynamic Routes sees the intended DID.

After the call reaches the Dynamic Route, you can also inspect its marker on that channel:

```bash
fs_cli -x 'uuid_getvar CALL_UUID dynamic_route_uuid'
```

A populated value identifies the Dynamic Route that ran. It does not prove that the selected destination answered.

For a deeper check, open [Dialplan Manager](/docs/getting-started/dialplan-manager/) and locate **Dynamic Route: your route name**. Confirm the entry extension, the conditions using `${caller_destination}`, and the final fallback action. Make routine corrections in the Dynamic Routes editor and save again.

## Edit, disable, or delete a route

Click the route name or its edit button to change settings. Use the **Enabled** badge in the list, or **Dynamic Route Enabled** in the form, to turn the route on or off.

Disabling the route disables its generated dialplan, including its fallback, and removes it from new destination selections. Existing phone numbers or menu keys pointing to that extension are not automatically redirected. Update those call paths before disabling or deleting a route that is in use.

Deleting a route removes its match rules and generated dialplan. If you change the route's extension, revisit and save the phone numbers, IVR keys, Call Flows, and other destinations that referenced the previous extension.

On a replicated installation, verify that changes and the corresponding dialplan reach each server that may handle the call, and test each call path after its cache is refreshed. Saving successfully on one server does not establish that another server is serving the updated configuration.

## Troubleshooting

| Problem | What to check |
| --- | --- |
| Dynamic Routes is missing from Applications | Check the application update, menu access, and `dynamic_route_view` permission. The page is `/dynamic-routes`. |
| The route is missing from a destination list | Confirm that it is saved, enabled, and belongs to the currently selected account. |
| Extension validation fails | Use digits only and choose an extension that is not already assigned to another destination in the account. |
| A match value is rejected as a duplicate | Remove the extra row for the same phone number. International and national variants can represent one rule. |
| Every call goes to the fallback | Confirm the calls reach this route, then inspect `caller_destination` on the incoming channel and compare it with the match values. Check the account country and resave an older route if necessary. |
| The wrong branch receives a call | Verify the original DID and each rule's selected destination. Also check that the IVR key points to the intended Dynamic Route. |
| A call ends when no rule matches | Check whether the fallback is still set to its initial **Hang up** action. |
| A matching destination does not answer | Check that destination's availability and timeout handling. Dynamic Route fallback is only for unmatched numbers. |
| An edited route behaves differently on another PBX | Check database replication and that server's generated dialplan/cache before repeating the test. |
| Calls fail after disabling, deleting, or renumbering a route | Update the incoming-number, IVR, or other routing references that still transfer to its old extension. |
