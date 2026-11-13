---
id: flags
title: Flags (optional)
slug: /configuration/flags/
sidebar_position: 99
---

Flags are an optional way to override a Ring Group or Virtual Receptionist destination. A manual flag is switched on or off by an operator; a scheduled flag follows weekly times or date ranges. Each override matches either the active or inactive state. The first matching row wins; otherwise, calls follow the normal route. A disabled flag never matches either state.

[Call Flows](./01-how-to-set-up-call-flows.md) are the recommended starting point for normal and alternate routing. Flags are disabled by default, have no default menu entry, and require explicit setup.

## Enable access

1. Complete the normal application update and database migrations.
2. In **Advanced → Default Settings**, find the row with **Category** `flags`, **Setting Name** `enabled`, and **Type** `boolean`. Set its **Value** to `true` and leave the row enabled. This switch applies server-wide; account overrides do not enable the feature.
3. In **Group Permissions**, manually grant the required permissions. None are assigned automatically, including for superadmins:

   | Permission | Allows |
   | --- | --- |
   | `flags_list_view` | Open the Flags page |
   | `flags_create` | Create flags |
   | `flags_update` | Edit or enable/disable flags |
   | `flags_delete` | Delete unused flags |
   | `flags_toggle` | Switch manual flags from the list |
   | `flags_assign` | Edit flag overrides on routes |

4. Sign out and sign in again to refresh your permissions. Open `/flags` on your PBX. If desired, add a menu entry manually; enabling Flags does not add one.

Flags belong to the selected account. Create a flag, then edit a Ring Group or Virtual Receptionist and open **Flag Overrides**. Choose the flag, the state to match, and the destination. Save the route. Drag override rows to change their priority. Editing these overrides also requires the normal Ring Group or Virtual Receptionist edit permission.

## Optional phone BLF

Enable **BLF** on the flag. On the FreeSWITCH server, manually add or uncomment this line inside `<settings>` in `/etc/freeswitch/autoload_configs/lua.conf.xml` (use your configured FreeSWITCH directory if different):

```xml
<param name="startup-script" value="lua/flag_blf.lua"/>
```

Restart FreeSWITCH during a maintenance window; this interrupts active calls:

```bash
systemctl restart freeswitch
```

Configure a phone BLF key with the value `flag<number>`, for example `flag1`. The lamp shows the flag's active state. Pressing the key toggles a manual flag, subject to its allowed-extension list and optional PIN. An empty allowed-extension list permits any extension in that account. Scheduled flags cannot be toggled manually.

Manual and scheduled routing overrides work without the BLF daemon. The daemon is needed for phone presence and scheduled lamp updates; FS PBX does not enable it automatically.

## Disable Flags

Set the global `flags` / `enabled` value back to `false`. Flags pages and override controls become unavailable, calls return to their normal routes, and saved flags and assignments remain for later use. The Flags BLF daemon exits when it detects the disabled switch.

To restore the feature, enable the setting again. If you use BLF, manually restart FreeSWITCH during a maintenance window to start its daemon again. To remove BLF startup permanently, comment out the startup line.
