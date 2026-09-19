---
id: agent-call-tracking
title: Agent Calls Outside Queues
slug: /configuration/contact-center/agent-call-tracking
sidebar_position: 3
---

# Agent Calls Outside Queues

FS PBX tracks an agent's calls outside Contact Center so queues can skip that agent while the phone is busy. This includes outgoing external calls, local extension calls, and incoming calls delivered through the extension's directory entry, including ring groups.

Tracking starts before answer and lasts until the phone's channel hangs up. Both agents are tracked on a local agent-to-agent call. With overlapping calls, the agent remains busy until the last tracked channel ends.

The agent's chosen Available, On Break, or Logged Out status stays unchanged. Contact Center shows **On a non-queue call** as a separate activity indicator. Queue-delivered calls continue using the normal queue state and statistics.

## Requirements

- Use a callback agent with Contact set to `user/<extension>@<account-address>` in the same account. The Contact determines membership; the editable Agent ID does not.
- Each extension must map to one agent. Duplicate contacts, standby agents, and custom contact expressions require separate integration.
- FreeSWITCH must provide `callcenter_track`, and participating queues must have `skip_agents_with_external_calls=true`.
- Receiving-side tracking uses the `user/` directory endpoint. Custom calls made directly to `sofia/...` bypass that integration.

The native counter is local to the FreeSWITCH server handling the call. It does not provide distributed busy state between independent FreeSWITCH instances. An offer selected at the instant another call starts can still race with tracking.

## Updating and checking

The application update installs the shared `agent-call-track` dialplan before local and external routing, clears directory and dialplan caches, and reloads XML. It does not restart FreeSWITCH or reload active queues. Existing channels are not tracked retroactively.

The fresh-install template lives in the separate public repository at `public/app/dialplans/resources/switch/conf/dialplan/009_agent_call_track.xml`. Update200 (2.0.0) downloads that template for existing servers when it is missing, then installs the matching dialplan and editable detail rows. Publish the public template before releasing the application update.

For troubleshooting, set `DEBUG_MODE = true` near the top of `resources/freeswitch_scripts/agent_call_track.lua`. Messages use the `[agent_call_track.lua]` prefix and show tracking decisions and channel/agent UUIDs. Restore it to `false` afterward. Failure warnings are always enabled. This single script also provides the directory-time membership lookup; requiring it from the directory generator does not execute call tracking.

Agent membership is included in generated directory XML. With a warm directory cache, calls reuse that mapping without another FS PBX membership query. Agent assignment changes clear the old and new extension entries after the database transaction commits, including number aliases. FreeSWITCH still reads and updates its own internal call-center counter.

During a controlled call, inspect:

```text
callcenter_config agent list
uuid_getvar <call-uuid> cc_tracked_agent
```

The matching agent's `external_calls_count` should rise, queue offers should go to another eligible agent, and the count should return to zero after hangup. Check cancellation, overlapping calls, and a break/logout change during a call too. The chosen status must survive hangup. Generated directory XML contains SIP credentials; do not share it unredacted.

## Rollback

Disable the `agent-call-track` dialplan and restore the previous directory generator to remove its receiving-side hooks. Clear directory and dialplan caches on each server and reload XML. Keep the tracking helper available until existing channels finish. Do not reset live counters or unload `mod_callcenter` while tracked calls are active.

## Automated verification

From the repository root:

```bash
vendor/bin/phpunit --do-not-cache-result tests/Unit/AgentCallTrackingTest.php
vendor/bin/phpunit --do-not-cache-result Modules/ContactCenter/Tests/Unit
python3 tests/Unit/run-agent-call-tracking.py
node Modules/ContactCenter/Tests/Unit/agentAvailability.test.mjs
```

The Lua runner uses the installed Lua 5.2 shared library. These tests use fictional identities and do not place telephone calls.
