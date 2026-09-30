---
id: contact-center-queue-outcomes
title: Queue Outcomes
slug: /configuration/contact-center/queue-outcomes/
sidebar_position: 6
---

# Queue Outcomes

Queue outcomes explain what happened to callers waiting for your team. Use them to see how many callers reached an agent, hung up while waiting, chose to leave the queue, or reached the waiting limit.

| Outcome | Meaning |
| --- | --- |
| Answered | The caller connected to an agent in this queue. |
| Abandoned | The caller hung up while waiting for an agent. |
| Exited queue | The caller pressed the queue's exit key to leave the queue. The call can continue to the fallback destination, such as voicemail or another team. |
| Timed out | The caller reached the queue's waiting limit, including a limit set for when no agents are available. The queue's fallback settings determine what happens next. |
| Callback requested | The caller successfully requested a callback. This does not mean the callback has already been completed. |
| Other queue outcome | There is not enough information to place the call in one of the categories above. Open its call timeline to review the available details. |

## Understanding abandoned calls

**Exited queue**, **Timed out**, and **Callback requested** are separate outcomes. They count toward call volume but do not increase the abandoned call count.

For example, suppose 80 callers left a queue without reaching an agent:

- 21 hung up while waiting: **Abandoned**.
- 55 pressed the exit key: **Exited queue**.
- 4 reached the waiting limit: **Timed out**.

The report shows **21 abandoned calls**, with exits and timeouts listed separately. If seven of those 55 callers then leave voicemail messages, the total is still 80 calls. Voicemail tells you what happened after those callers left the queue.

## Reading the dashboard

The summary row at the top of **Performance** shows:

- **Handled Calls**: calls an agent was connected to, with their total talk minutes. This is the queue calls agents answered, the callbacks where an agent reached the customer, and the connected outbound calls the agents placed themselves. Abandoned calls and callback requests are never handled. A completed callback counts when it connected, which can be in a later period than the request. Outbound calls are counted only when one contact center is selected, and queue attempts to ring an agent or callback legs are never counted as outbound calls. Because callbacks and outbound calls happen outside the original queue call, Handled Calls does not match the queue call total.
- **Speed to answer**: the average wait before an agent answered, with the shortest and longest waits.
- **Service Level**: the percentage of answered calls that reached an agent within 60 seconds of entering the queue. It measures how quickly answered calls were picked up.
- **Abandonment rate**: abandoned calls as a percentage of all queue calls in the report, with the average wait before those callers hung up. Exits, timeouts, and callback requests are included in the total, so they lower the rate instead of disappearing from it.

**Queue Outcomes** places every queue call in exactly one outcome, so the outcomes always add up to the queue call total. The bar shows each outcome's share. The list shows its count, percentage, and details such as voicemail messages and short abandons, which are callers who hung up after waiting in the queue for less than 10 seconds. **Answered** and **Abandoned** are always listed; other outcomes appear only when they had calls in the report. **<1%** means the outcome happened but rounds below one percent.

The **Call Volume** chart stacks the same outcomes by hour, in the same colors.

For a queue-wide view, leave the agent filter set to all agents. Selecting agents switches the dashboard to those agents' calls. Calls that left the queue without an answer have no agent, so **Queue Outcomes** and **Abandonment rate** are hidden. **Call Volume** shows the calls the selected agents handled each hour, split into answered, called back, and outbound, so it adds up to **Handled Calls**. **Average Call Duration** covers the same calls.

## Reviewing an individual call

In **Call Details**, use the status filters to find calls that exited the queue, timed out, or have another queue outcome. Exports include the same statuses.

Open the call timeline to see the queue outcome, the reason when available, and what happened next. For example, a caller might exit the queue, reach voicemail, and leave a message. That call remains **Exited queue**, with voicemail shown separately.

Use **Voicemail message left** to find callers who left a message. Reaching voicemail alone does not mean a message was recorded. **Message status unknown** means the report cannot confirm whether a message was left.

The final hangup describes how the whole call ended. A caller hanging up after leaving a voicemail does not turn an earlier queue exit into an abandoned call.

## Setting the caller exit key

The exit key lets callers leave the queue while they are waiting.

1. Open the queue in **Contact Center Settings**.
2. In **Fallback Destination**, set **Caller Exit Key** to **Disabled** or choose one digit from **0** through **9**.
3. Check the fallback destination. This is where callers should go after pressing the exit key, such as voicemail or another team.
4. Save the queue and place a test call to confirm the key sends callers to the intended destination.

If you enable an exit key, tell callers which key to press in the queue greeting or announcements.
