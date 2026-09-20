---
id: contact-center-queue-callbacks
title: Queue Callbacks
slug: /configuration/contact-center/queue-callbacks/
sidebar_position: 4
---

# Queue Callbacks

Queue callbacks let callers leave the phone queue and receive a return call when an agent can help. FS PBX carries forward their waiting time and queue priority, so they do not have to stay on hold.

Callbacks are enabled separately for each Contact Center queue. You choose when to offer them, what customers hear, how many times to try, and when callbacks stop for the day.

## How a callback works

1. A caller waits in the queue. After the configured delay, they hear an offer to receive a callback.
2. They press the offered key, confirm a callback number, and record their name.
3. FS PBX confirms that the request was saved and ends the incoming call.
4. When an agent is available, FS PBX calls the agent first and reserves them for the callback.
5. After the agent answers, FS PBX calls the customer. The customer hears your greeting, their recorded name, and an instruction to press **1**.
6. The customer presses **1** and is connected to the agent.

The queue's routing rules and agent availability still determine who answers. A callback preserves waiting priority; it does not promise a particular callback time. Callers cannot choose a future appointment time, and callbacks do not carry over to the next day.

## Before you begin

- Install and activate the [Contact Center module](/docs/configuration/contact-center/installing-contact-center-module/).
- Have permission to edit Contact Center settings. A dashboard-only user can view callback history but cannot configure or cancel callbacks.
- Create and save the queue, assign agents, and confirm that ordinary queue calls work.
- Confirm that the account can make outbound calls to the number formats your callers use. Callback numbers are dialed as confirmed by the caller; FS PBX does not automatically add a country code or check the route while collecting the number.
- Complete the [server verification](#verify-the-server-before-enabling-callbacks) below on every participating PBX. Saving callback settings does not verify the runtime, event listener, scheduler, or workers.

On a redundant installation, first complete [Scheduled Job Server Setup](/docs/configuration/scheduled-jobs/server-ownership/). New callback offers and callback processing require the selected job owner. A standalone server uses its normal single-server mode without registering a second server.

## Verify the server before enabling callbacks

Run these checks over SSH on **each server**, including a standby. The examples use the standard installation at `/var/www/fspbx`, with application workers running as `www-data`. Use an account with sudo access.

Start in the application directory:

```bash
cd /var/www/fspbx
```

### 1. Verify the installed runtime

Check the callback script paths and required command-line tools:

```bash
php artisan contact-center:install-ha
```

**Expected result:** the command exits successfully and reports **Contact Center Lua links verified**. It checks that the matching module and shared Lua files are readable through the installed FreeSWITCH script paths, and that `/usr/bin/curl` and `/usr/bin/timeout` are executable. Despite its name, this command verifies the runtime; it does not copy scripts, start the event listener, or place a test call.

If the command is unavailable, confirm that Contact Center is installed and enabled. If it reports missing or incorrect Lua links, update the main application and Contact Center module together. If it reports missing binaries, install the `curl` and `coreutils` packages and repeat the check.

### 2. Verify FreeSWITCH

Confirm that FreeSWITCH answers its local event socket:

```bash
fs_cli -x 'status'
```

**Expected result:** FreeSWITCH returns its uptime and status, without a connection error.

Check the modules used for queue handling, callback scripts, and callback call legs:

```bash
fs_cli -x 'module_exists mod_callcenter'
```

```bash
fs_cli -x 'module_exists mod_lua'
```

```bash
fs_cli -x 'module_exists mod_loopback'
```

**Expected result:** each module check returns `true`. A successful `fs_cli` connection verifies CLI access; the application must also be able to connect using its own ESL settings, which the synchronization check below exercises.

The callback scripts also need local HTTPS access to FS PBX at `https://127.0.0.1/api/contact-center/local`. Ensure local web-server routing reaches the application without redirecting to another server. This endpoint requires an authorized call, so opening it in a browser is not a callback test. Both the FreeSWITCH service account and `www-data` need access to create and read caller-name recordings under `/var/lib/freeswitch/recordings`.

### 3. Verify the event listener

```bash
sudo supervisorctl status fspbx-contact-center-events
```

**Expected result:** the process shows **RUNNING** and stays running. It receives FreeSWITCH events needed to track agent availability and callback call outcomes. **STOPPED**, **EXITED**, **BACKOFF**, or **FATAL** requires investigation; a process that repeatedly restarts is not healthy.

The normal application/module update installs this Supervisor program. If its configuration is missing, or you need to repeat the module's service setup after fixing a failed update, run:

```bash
sudo php artisan module:update-ContactCenter
```

This is a repair action: it verifies the runtime, installs or updates the listener configuration, and starts or restarts the listener. It does not run migrations, select an owner, or restart Horizon. Repeat the Supervisor status check afterward.

### 4. Verify Horizon and the callback queue

```bash
sudo supervisorctl status 'horizon:*'
```

**Expected result:** the Horizon process shows **RUNNING**. Then check its application-level status:

```bash
sudo -u www-data php artisan horizon:status
```

**Expected result:** **Horizon is running.** A running Supervisor process can still contain paused workers, so also inspect the active worker pools:

```bash
sudo -u www-data php artisan horizon:supervisors
```

Confirm that a running supervisor serves the **contact-center-ha** queue, normally displayed as `redis:contact-center-ha` in the **Workers** column. This queue handles callback processing, retries, cleanup, and Contact Center synchronization. The module adds it to the existing Horizon pool; there is no separate callback queue-worker service to install.

If the queue is absent after a module-only update, confirm the module is enabled, then gracefully recycle Horizon so it loads the current code and queue configuration:

```bash
sudo -u www-data php artisan horizon:terminate
```

With the standard Supervisor configuration, Horizon starts again automatically after terminating. Repeat both Horizon checks and confirm the queue is present. If Horizon reports **paused** or **inactive**, resolve that state before testing callbacks.

### 5. Confirm ownership and runtime synchronization

Open **Contact Center Settings > Scheduled Jobs** directly on the server being checked:

1. Use **Refresh status**. Confirm **Single server** on a standalone PBX, or the agreed **Job owner** and **Ownership version** on a replicated installation.
2. Expand **Queue and agent synchronization** and review any runtime error.
3. Click **Reconcile servers**. This applies saved queue and agent configuration to FreeSWITCH and requests reconciliation on approved peers.
4. Use **Refresh** and confirm a successful result. On a replicated installation, verify **Ready** and a recent **Last successful synchronization** for each server, opening the peer directly when needed. A standalone installation may not display a registered-server table.

The synchronization status is the last reported result, not continuous health monitoring. A successful reconciliation checks application access to the live queue runtime; it does not prove that a customer can be called or that audio works. Finish with the [test-call procedure](#test-before-offering-callbacks-to-customers) after saving the queue's callback settings.

### Logs and recovery

Use these logs when a service fails or a test callback stalls:

| Log | What to investigate |
| --- | --- |
| `/var/log/fspbx-contact-center-events.err.log` and `.out.log` | Event-listener startup, process, and stderr/stdout messages. |
| `/var/www/fspbx/storage/logs/laravel.log` | Reported ESL, database, ownership, callback, and synchronization exceptions. Use the configured Laravel log destination if changed. |
| `/var/www/fspbx/storage/logs/horizon.log` | Worker startup and process output. Check Horizon's failed jobs for individual job exceptions. |
| FreeSWITCH's configured log | Callback media and local HTTP errors, including messages prefixed with `[contact_center_callback]` or `[contact_center_ha]`. |

After correcting a worker or listener outage, you can request immediate callback recovery on the selected owner:

```bash
sudo -u www-data php artisan contact-center:ha-work --callbacks
```

This queues recovery work and can resume eligible callbacks. It is not a read-only status check or a forced redial. A successful command exit only means the request returned; a standby skips it, and Horizon must process the queued work. Verify progress in **Callback history**. Requests with uncertain call outcomes still require reconciliation and are not retried merely because recovery was requested.

## Configure a queue

### 1. Open the Callbacks tab

From the FS PBX home dashboard, open **Settings** on the **Contact Center** tile. Select **Contact Centers**, choose the queue, and open **Callbacks**.

For a new queue, save it first. The **Callbacks** tab appears after the queue has been created.

Turn on **Enable queue callbacks**.

### 2. Prepare what the customer hears

Under **What the customer hears**, configure the two greeting fields:

| Field | What to configure |
| --- | --- |
| **Greeting we play when calling back** | Required when callbacks are enabled. Select or create an introduction identifying your business. End with “If you are…” because the caller's name recording plays next. |
| **How we ask them to accept** | Leave the default “Press 1 to be connected with an agent,” or select your own recording with the same instruction. The acceptance key remains **1**. |

For example, the complete return-call message could be:

> Hello, this is Example Company returning your call. If you are… **[the caller's recorded name]** …press 1 to be connected with an agent.

Use the greeting controls to select an existing recording or create one using text to speech, an upload, or a phone recording. **Play the whole sequence** previews the greeting, a sample name, and the acceptance instruction. The actual call uses the caller's accepted name recording.

The name recording is temporary and reused for retries. It is cleaned up after the callback sequence finishes and its calls have been checked. If the name file is unavailable when the return call is made, FS PBX plays the introduction and acceptance instruction without it; the customer must still press **1**.

### 3. Choose when to offer callbacks

Under **When we offer it**, set:

| Field | Initial value | How to use it |
| --- | --- | --- |
| **Offer a callback after** | **90 seconds** | The time a caller must wait before the offer. Allowed range: 10–3,600 seconds. |
| **Key the caller presses to accept** | **9** | Choose one digit from 0–9 that is not already a queue exit key. This key requests a callback; the return call always uses **1** to connect. |

Listen to **What callers hear in the queue** to preview the offer. Its preview omits the final spoken digit; the live offer speaks the selected key.

Check the queue's **Advanced** tab as well:

- **Max queue wait time (seconds)** must leave enough time for the offer and the caller's response. For example, a 90-second offer cannot help a caller who is sent to the fallback destination after 60 seconds.
- **Max wait time with no agents (seconds)** can also send callers out of the queue before they hear the offer.
- **Caller Exit Key** must not reuse the callback digit. The key `D` is reserved internally for callback deadlines.

Callers who leave the queue before the offer do not receive an automatic callback.

### 4. Set the daily cutoff and retries

Under **When we give up**, set:

| Field | Initial value | How to use it |
| --- | --- | --- |
| **Stop calling people back at** | **5:00 PM** | Choose the queue's daily callback cutoff. New offers stop and unfinished callbacks expire at this time. |
| **Time zone** | Account time zone | The clock used for the cutoff. Check it explicitly, especially when managing accounts in different regions. |
| **Try each callback up to** | **3 times** | Total customer attempts, including the first attempt. Allowed range: 1–5. |
| **Wait between tries** | **600 seconds** | Delay after an unsuccessful customer attempt before another can begin. Allowed range: 60–3,600 seconds. Agent availability can add more waiting time. |

For example, three attempts with 600 seconds between tries need at least 20 minutes between the first and third attempts, plus dialing and agent waiting time. A request accepted shortly before 5:00 PM may expire before using all three attempts.

Busy calls, unanswered calls, and customers who do not press **1** can be retried within these limits. A number with no matching outbound route also consumes an attempt. Waiting for an agent, or an agent failing to answer before customer dialing starts, does not use a customer attempt.

An uncertain call outcome can instead become **Needs review**, with no automatic retry, to avoid calling the same customer twice.

### 5. Save

Click **Save callback settings** and check for the confirmation or any field errors. The summary on the form reflects the values you entered.

To stop accepting callbacks for this queue, turn off **Enable queue callbacks** and save again. Outstanding requests stop being eligible for further attempts and expire as they are processed. Disabling callbacks is not a pause that carries the requests into another day.

## What callers do

After pressing the offered callback key, the caller hears their incoming phone number when it is available:

| Key | Action |
| --- | --- |
| **1** | Confirm the number just read back. |
| **2** | Enter a different number, followed by **#**. |
| **3** | Return to the live queue. |

If caller ID is missing or withheld, FS PBX asks for a number immediately. The caller can correct a manually entered number up to two times after the first entry. An enabled extension in the same account can also be used as the callback number.

Next, the caller records their name after the tone and presses **#** when finished. The recording can be up to ten seconds long. After playback, they press **1** to accept or **2** to record again.

The caller should wait for the message confirming that the callback request was saved. If number confirmation or name recording cannot be completed, they return to the live queue with their accrued priority and original waiting deadline. They are not offered another callback during that queue visit.

When FS PBX calls back, the customer must press **1** to reach the agent. Answering the phone alone, including an answering machine picking up, does not complete the callback.

## What agents do

The agent's phone shows a caller name beginning with **Callback -**. After answering, the agent hears:

> This is a requested callback. Please wait while we call the customer.

The agent stays on the line; no keypress is required. They hear ringback while FS PBX calls the customer and waits for confirmation, followed by a short tone before connection. If the customer cannot be reached or does not confirm, the agent hears an outcome message and is released.

## Monitor and cancel callbacks

Open the **Contact Center** dashboard and select one queue under **Filter by Contact Center**. **Callback history**, below the live calls, shows the 50 most recent requests for that queue. The statistics date range and agent filters do not filter this list.

The table shows the callback number, status, customer tries, next action, and request time. History times use the account's time zone; the callback cutoff uses the time zone saved in callback settings.

| Status | Meaning |
| --- | --- |
| **Waiting** | In line for a callback or waiting until the next retry. |
| **Preparing callback** / **Waiting for an agent** | FS PBX is finding an agent before calling the customer. |
| **Calling now** | An agent is reserved and the customer is being called. |
| **Customer confirmed** | The customer pressed 1; FS PBX is waiting for the final connection outcome. |
| **Completed** | FS PBX verified that the customer connected to an agent. |
| **Needs review** | The outcome is uncertain. No retry is scheduled. Check the call outcome and any surviving channels before arranging another callback. |
| **Failed** | The allowed customer attempts were used without a successful connection. |
| **Expired** | The callback deadline was reached, or callbacks were disabled before it completed. |
| **Cancelled** | An authorized user cancelled the request. |

Users with Contact Center settings permission can click **Cancel** on an eligible request. On a redundant installation, open the dashboard directly on the scheduled job owner to cancel it. Cancellation should not be treated as a hangup control for an already connected conversation.

## Test before offering callbacks to customers

1. Call the queue from a test number and keep its agents unavailable long enough to hear the offer.
2. Request a callback, confirm the number, record a name, and wait for the saved-request confirmation.
3. Check that the request appears in **Callback history**.
4. Make an assigned agent available. Confirm that the agent is called first and hears the callback introduction.
5. Answer the return call, listen to the greeting sequence, and press **1**. Verify two-way audio and the **Completed** outcome after the call.
6. Make another test request and leave the return call unanswered. Check that the customer attempt count and next retry reflect your settings. Cancel the test request when finished.

## Troubleshooting

| Problem | What to check |
| --- | --- |
| The Callbacks tab is missing | Save the queue first and confirm your Contact Center settings permission. |
| The offer never plays | Check that callbacks are enabled and saved, that the caller waits long enough, and that the queue does not time out first. Check the cutoff and time zone. On redundant servers, the incoming call must reach the scheduled job owner for a new offer. |
| The caller returns to the queue instead of saving a request | Complete the number confirmation and name recording. If it still fails, check local HTTPS access, recording permissions, and the Laravel and FreeSWITCH logs described above. |
| Requests remain waiting | Check assigned agents and their availability, the next retry time, and **Scheduled Jobs** ownership. Verify that Horizon is running and serving `contact-center-ha`, then inspect job failures and listener logs. |
| Return calls fail | Confirm that the caller's exact number format matches the account's outbound routing and that the outbound caller ID is accepted by the provider. |
| A request says Needs review | Read its error and compare callback history with Call History and active calls on the server that handled the attempt. Establish whether the previous call connected or is still active before making another call. |

On redundant servers, changing DNS or moving incoming calls to the other PBX does not move callback ownership. Use the shared [Scheduled job server control](/docs/configuration/scheduled-jobs/server-ownership/) for ownership changes.
