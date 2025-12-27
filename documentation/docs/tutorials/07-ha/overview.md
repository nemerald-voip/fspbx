---
id: ha-overview
title: Primary–Standby FS PBX Architecture
slug: /ha/overview
sidebar_position: 1
---

import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

# Primary–Standby FS PBX (HA) — Overview

This guide covers a **two-node Primary–Standby** design for FS PBX with:

- **Database**: PostgreSQL logical replication (bi-directional)
- **Files**: Syncthing folder replication (bi-directional)
- **Failover**: A **floating DNS name** (e.g. `pbx.example.com`) that points to the active node
- **Nodes**: Two individual A/AAAA records (e.g. `server1.example.com`, `server2.example.com`) used for direct access and peer-to-peer replication

> Goal: If the primary fails, the standby already has fresh DB + files and can take over in minutes with a DNS switch (or an automated health probe).

## DNS Model

- `server1.example.com` → Node A (Primary)
- `server2.example.com` → Node B (Standby)
- `pbx.example.com` → **Floating** DNS record that points at the **active** node (A or B)

You can update the floating record using:
- A provider API (Route53, Cloudflare)
- A lightweight health probe/cron that flips to the healthy node
- (Optional) very low TTL (e.g., 30–60s) for faster cutover

## Traffic & Data Flows

- **SIP/RTP**: Flows to the **active** node (via `pbx.example.com`.
- **API/UI**: Users hit the floating name; web sessions and API calls reach the active node.
- **Database**: Logical replication **both ways**; initial copy runs A → B, then continuous bi-directional replication.
- **Files**: Syncthing mirrors recordings, voicemails, storage, and sounds across both nodes.

## Why Syncthing + Logical Replication?

- **PostgreSQL Logical Replication**: Granular, resilient, faster bootstrap; easy to control origin and copy-data semantics. Works well for PBX metadata, queues, and live tables.
- **Syncthing**: Efficient delta sync for large media trees (recordings/voicemail), resilient to interruptions, auto-recovers, and handles lots of small files gracefully.

## Prerequisites

- Both servers with FS PBX installed.
- **SSH key-based auth** configured **both directions** (A⇄B).
- Open firewall: TCP 22 (SSH), TCP 5432 (Postgres between peers).
- External or internal IPs are fine; as long as servers are able to communicate.

## Implementation Sequence

1. **Prepare DNS** records for `server1`, `server2`, and `pbx`.
2. Set up **Postgres replication** (bi-directional). See: [Bi-Directional PostgreSQL Setup](tutorials/07-ha/postgres-replication.md).
3. Set up **Syncthing** with the FS PBX folders. See: [Syncthing File Replication](tutorials/07-ha/syncthing.md).
4. Point your **floating DNS** (`pbx.example.com`) at the active node.
5. (Optional) Automate failover with a health probe + provider API.

<Tabs>
<TabItem value="pros" label="Pros">
<ul>
<li>Fast cutover (DNS flip), minimal data loss</li>
<li>Keeps media and DB continuously in sync</li>
<li>Works with public or private (internal) IPs</li>
</ul>
</TabItem>
<TabItem value="cons" label="Considerations">
<ul>
<li>DNS-based failover depends on TTL and client caching</li>
<li>Follow the advanced guide for updating your servers to ensure the schema changes are done correctly</li>
</ul>
</TabItem>
</Tabs>

## Operational Notes

- Apply FS PBX updates on the **primary first**, verify, then the standby.
- For emergency failover: update floating DNS → verify registrations → monitor calls.
