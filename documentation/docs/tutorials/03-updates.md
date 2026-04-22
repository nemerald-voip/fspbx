---
id: how-to-update
title: How to update
slug: /updates
sidebar_position: 3
---

# How to update

This guide explains how to update FS PBX in both single-server and multinode deployments.

## Single-server deployments

The steps below apply to **single-server deployments**.

### 1. Pull the latest application updates

Run:

```bash
cd /var/www/fspbx
git pull
php artisan app:update
````

### 2. Check for pending database migrations

Run:

```bash
php artisan migrate:status
```

If any migrations are pending, apply them with:

```bash
php artisan migrate
```

---

## Multinode deployments

The steps below apply to **multinode deployments** where two servers are configured with **bidirectional logical replication**.

### Overview

In a multinode deployment, you will:

1. Update the application code on both servers
2. Pick one server to update first
3. Run `php artisan migrate` on the **first server**
4. Run `php artisan migrate:delete-last-batch` and then `php artisan migrate` on the **second server**
5. Refresh subscriptions on both servers

### 1. Update application code on both servers

Run the following on **both servers**:

```bash
cd /var/www/fspbx
git pull
php artisan app:update
```

### 2. Choose the first server

Decide which server you want to update first.

On the **first server**, check migration status:

```bash
php artisan migrate:status
```

If any migrations are pending, run:

```bash
php artisan migrate
```

### 3. Update the second server

On the **second server**, run:

```bash
php artisan migrate:delete-last-batch
php artisan migrate
```

### 4. Refresh logical replication subscriptions

After the migrations are complete, run the following on **both servers**:

```bash
php artisan db:refresh-subscriptions
```

---

## Examples

### Example 1: You start on Server A

If you begin the update on **Server A**, then:

**On Server A:**

```bash
php artisan migrate
```

**On Server B:**

```bash
php artisan migrate:delete-last-batch
php artisan migrate
```

### Example 2: You start on Server B

If you begin the update on **Server B**, then:

**On Server B:**

```bash
php artisan migrate
```

**On Server A:**

```bash
php artisan migrate:delete-last-batch
php artisan migrate
```

---

## Important rule

The names **Server A** and **Server B** are only labels used for reference.

What matters is the **order**:

* The server updated first runs:

```bash
php artisan migrate
```

* The server updated second runs:

```bash
php artisan migrate:delete-last-batch
php artisan migrate
```

---

## Quick reference

### On both servers

```bash
cd /var/www/fspbx
git pull
php artisan app:update
```

### On the first server

```bash
php artisan migrate:status
php artisan migrate
```

### On the second server

```bash
php artisan migrate:delete-last-batch
php artisan migrate
```

### Back on both servers

```bash
php artisan db:refresh-subscriptions
```

---

## Need help?

If you are unsure which server should be treated as the first or second server during an update, remember:

* **First server** = the server where you run `php artisan migrate` first
* **Second server** = the other server

If you get stuck during the update process, please reach out to support.


