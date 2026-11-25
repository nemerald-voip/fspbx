---
id: business-hours
title: Business Hours
slug: /configuration/business-hours/
sidebar_position: 18
---

# Set up Business Hours

Use **Business Hours** to decide where a call goes based on the local day and time. In this example, the main number plays the **Main Menu** Virtual Receptionist on weekdays from 9 AM to 5 PM. Callers can press 1 for Sales. At other times, including office holidays, callers reach the **Reception** mailbox.

## Create the schedule

1. Switch to the account that owns the main phone number. Have the open and closed destinations ready. For this example, configure the **Main Menu** greeting and its 1 key to reach the Sales ring group, and make sure the Reception mailbox has an after-hours greeting.
2. Open **Business Hours** and click **Create**. Enter a **Name** such as `Main Office Hours`, keep the suggested unused **Extension**, and select the office's **Time Zone**. You can dial this extension internally to test the schedule.
3. Under **When do you want to receive calls?**, select **Only during specific hours**. In **Time Slots**, select Monday through Friday, set **From** to **9:00 AM** and **To** to **5:00 PM**, then set **Choose Action** to **Virtual Receptionist** and **Target** to **Main Menu**.
4. Under **Closed Hours**, set **Choose Action** to **Voicemail** and **Target** to the Reception mailbox. Click **Save**.

Each time slot can have its own weekdays, hours, and destination. For an office that closes for lunch, enter two slots, such as 9 AM–noon and 1–5 PM. The gap uses **Closed Hours**. If one destination should take calls around the clock except on holidays, choose **Always take calls (24/7)** and select that destination.

## Add a holiday or early closure

After you save, the schedule opens for editing. Select **Holidays** and click **Add Holiday**. You can also return here later by opening **Main Office Hours**. A matching holiday takes priority over the weekly schedule.

1. For a one-off closure, choose **Single Date**, enter a **Holiday Description**, and select the **Date**. Leave the times empty for a full-day closure. For an early closure, enter both **Start Time** and **End Time** for the period you want to override.
2. Set **Choose Action** to **Voicemail**, select the Reception mailbox as **Target**, and click **Save**.

Use **Date Range** for a closure spanning several dates. The holiday picker also offers named US, Canadian, and UK holidays and a **Recurring Pattern** option.

## Connect and test the main number

1. From an extension in the same account, dial the Business Hours extension. During the open slot, confirm the greeting plays and pressing 1 rings Sales. Outside that slot, confirm you hear Reception's voicemail greeting.
2. Open **Phone Numbers** and edit the main number. Under **Call routing**, set **Choose Action** to **Business Hours** and **Target** to **Main Office Hours**. Save the phone number.
3. Call the main number from an outside phone to verify it follows the same route. To verify a holiday before it arrives, use a separate test schedule with a **Single Date** exception for today and dial its extension during an otherwise open period.

If the internal test works but the outside call goes elsewhere, check the main number's saved target. If the call reaches the right schedule at the wrong time, check the schedule's **Time Zone**, selected weekdays, and holiday dates.
