---
id: syncthing
title: Syncthing File Replication (Bi-Directional)
slug: /ha/syncthing
sidebar_position: 3
---

# Syncthing File Replication (Bi-Directional)

This guide uses an **automated script** to install & pair Syncthing between two FS PBX servers and share core folders.

## What It Does

- Installs Syncthing on **both** servers
- Configured Syncthing
- Pairs devices automatically using REST + CLI
- Creates and shares these folders on **both** sides:

```
/usr/share/freeswitch/sounds/music
/var/cache/fusionpbx
/var/lib/freeswitch/recordings
/var/lib/freeswitch/storage
/var/www/fspbx/public/resources/templates/provision
```

- Works with **external or internal IPs**
- Assumes **SSH keys are already exchanged in both directions**

> Make sure you can SSH without a password from Node A → B and from B → A:

## Run the Script (on the Primary Server)

```
sh /var/www/fspbx/install/install_syncthing.sh
```

Confirm the script ran succesfully. Time to check the Synchting GUI for results.


## Tunnel Access (Recommended)

By default, the Syncthing web GUI listens on **port 8384**.  

For security reasons, **do not expose this port publicly**.

The safest and most common method to access it is through an **SSH tunnel**, which forwards the GUI port securely through your existing SSH connection.

### Example: Access the Syncthing GUI on server1

From your local workstation (macOS/Linux):

```bash
ssh -L 8384:localhost:8384 root@server1.example.com
```

Then open this URL in your browser: http://localhost:8384

You'll see the Syncthing interface for server1.

To access server2 instead:

```
ssh -L 8385:localhost:8384 root@server2.example.com
```

Then open: http://localhost:8385

💡 You can change the left-hand port (8384 / 8385) freely; it only matters on your local system.

### Windows users

If you use **PuTTY** or **MobaXterm**:

* Go to **Connection → SSH → Tunnels**

* Set:

    * Source port: `8384`

    * Destination: `localhost:8384`

* Click **Add** and connect normally.

* Then open http://localhost:8384 in your browser.

### Why tunnel instead of exposing?

| Reason | Explanation |
| --- | --- |
| Security | Prevents direct access to Syncthing GUI from the internet |
| Simplicity | Uses your existing SSH keys; no additional firewall rules |
| Auditability | All GUI traffic goes over your SSH session |
| Compliance | No open management ports visible externally |


If remote access is required for monitoring or automation, consider:
* Using a VPN or reverse proxy with HTTPS + auth instead of public exposure

Notes
---------------

-   If can add other folders to the list later in GUI.


Security
--------

-   File ACLs: ensure `www-data` (or your service user) can read/write target directories on both nodes.