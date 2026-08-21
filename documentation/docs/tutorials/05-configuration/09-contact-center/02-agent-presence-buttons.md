---
id: agent-presence-buttons
title: Agent Presence Buttons
slug: /configuration/contact-center/agent-presence-buttons
sidebar_position: 2
---

# Agent Presence Buttons

FS PBX can monitor and change a call center agent's login and break status using two BLF keys. Status changes made from the phone, the FS PBX web interface, or automatically by the queue are reflected on the keys in real time.

## Before you begin

The phone's authenticated extension and the call center **Agent ID** must be the same. For example, extension `100` can use these keys for Agent ID `100`.

The compact BLF keys are self-service only. A phone cannot use them to change another agent's status, and the Agent ID must contain digits only.

## What the BLF keys show

| Key | LED on | LED off | Press the button |
| --- | --- | --- | --- |
| Agent Login | Available, Available On Demand, or On Break | Logged Out | Logs the agent in as Available, or logs out an agent who is already logged in |
| Agent Break | On Break | Any other status | Changes On Break to Available, or changes another status to On Break |

An agent who is **On Break is still logged in**, so both LEDs are on while the agent is on break.

The phone also plays a confirmation after each change: **Logged In**, **Logged Out**, **On Break**, or **Available**.

## Configuring the BLF keys

On Yealink phones and most other SIP phones, add these two BLF keys:

```text
Type: BLF
Value: agent<AGENT_ID>
Label: Agent Login

Type: BLF
Value: break<AGENT_ID>
Label: Agent Break
```

Replace `<AGENT_ID>` with the Agent ID assigned to the phone's extension.

For Agent ID `100`, configure:

```text
Type: BLF
Value: agent100
Label: Agent Login

Type: BLF
Value: break100
Label: Agent Break
```

These keys subscribe to:

```text
sip:agent100@yourdomain.com
sip:break100@yourdomain.com
```

Do not use the legacy `agent+<AGENT_ID>` format. It is not supported by these presence keys.

## Status examples

| Agent status | Agent Login LED | Agent Break LED |
| --- | --- | --- |
| Logged Out | Off | Off |
| Available | On | Off |
| Available On Demand | On | Off |
| On Break | On | On |

If the agent is logged out and presses **Agent Break**, the status becomes On Break and both LEDs turn on. Pressing **Agent Break** again changes the status to Available.

## FreeSWITCH configuration

The agent presence service must be enabled in:

`/etc/freeswitch/autoload_configs/lua.conf.xml`

Current FS PBX updates add this startup service automatically. Confirm the file contains exactly one entry:

```xml
<param name="startup-script" value="lua/agent_blf.lua"/>
```

If the entry is missing, add it inside the configuration's `<settings>` section.

### Restart FreeSWITCH

A complete FreeSWITCH restart is required after adding the startup service. `reloadxml` alone does not start it.

```bash
sudo systemctl restart freeswitch
```

## Notes and troubleshooting

- If a button press is rejected, confirm the phone's authenticated SIP username exactly matches the requested Agent ID.
- If the buttons work but the LEDs do not update, confirm the startup service is present and FreeSWITCH was completely restarted afterward.
- Some phones may need to be rebooted or re-registered after adding new BLF keys.
- The existing `*22`, `*23`, and `*24` agent status codes remain available and are not replaced by these BLF keys.
