# Understanding IPTables on FSPBX  
## Example: Allow SNMP (UDP 161) from a Single Source IP

This document describes how to modify **FSPBX** firewall rules using the
`iptables-save` / `iptables-restore` method to allow **SNMP (UDP port 161)**
*only* from a specific source IP.

---

## Overview

- **Service:** SNMP  
- **Protocol:** UDP  
- **Port:** 161  
- **Allowed Source IP:** `192.160.x.x`  
- **Firewall:** iptables  
- **Persistence Method:** iptables-save / iptables-restore  

---

## 1. Backup Existing Rules

Always create a backup before making changes.

```bash
iptables-save > /root/iptables.backup.$(date +%F-%H%M)
```

---

## 2. Edit Persistent Rules File

Open the iptables rules file:

```bash
nano /etc/iptables/rules.v4
```

---

## 3. Add SNMP Rules

Within the `*filter` table and **before `COMMIT`**, add the following rules:

```conf
# Allow SNMP from approved monitoring host
-A INPUT -p udp -s 192.160.x.x --dport 161 -j ACCEPT

# Drop all other SNMP traffic
-A INPUT -p udp --dport 161 -j DROP
```

### Example Context

```conf
*filter
:INPUT DROP [0:0]
:FORWARD DROP [0:0]
:OUTPUT ACCEPT [0:0]

# Allow established connections
-A INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
-A INPUT -i lo -j ACCEPT

# SNMP (UDP 161)
-A INPUT -p udp -s 192.160.x.x --dport 161 -j ACCEPT
-A INPUT -p udp --dport 161 -j DROP

COMMIT
```

---

## 4. Apply the Rules

Reload the firewall configuration:

```bash
iptables-restore < /etc/iptables/rules.v4
```

---

## 5. Verify Rules

Confirm that the rules are active:

```bash
iptables -L INPUT -n -v | grep 161
```

Expected output:

```text
ACCEPT udp -- 192.160.x.x  0.0.0.0/0  udp dpt:161
DROP   udp -- 0.0.0.0/0        0.0.0.0/0  udp dpt:161
```

---

## 6. Verify SNMP Listener

Ensure SNMP is listening on UDP 161:

```bash
ss -lunp | grep :161
```

Expected output:

```text
udp UNCONN 0 0 0.0.0.0:161
```

---

## Security Notes

- `192.160.x.x` is a **public IP address**
- Do **not** expose SNMP broadly to the internet
- Recommended:
  - Use **SNMPv3** (authentication + encryption)
  - Restrict SNMP access to VPN or management networks only

---

## Rollback

To restore the previous firewall state:

```bash
iptables-restore < /root/iptables.backup.<timestamp>
```

---

## Change Log

- Added restricted SNMP (UDP 161) access for FSPBX