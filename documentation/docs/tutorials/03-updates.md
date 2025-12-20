---
id: how-to-update
title: How to update
slug: /updates
sidebar_position: 3
---

# How to update

:::info

Below steps apply to **single server deployments**.

:::

### Pull new updates and install them by running these commands:

   ```bash
   cd /var/www/fspbx
   git pull
   php artisan app:update
   ```
Check if there are any pending database updates.

   ```bash
   php artisan migrate:status
   ```
If you find any updates pending, run this command to install them. 
   ```bash
   php artisan migrate
   ```


:::info

Below steps apply to **multinode server deployments** where **Server A** and **Server B** are configured with **bidirectional logical replication**.

:::

### **1\. Update Application Code on Both Servers**

On **Server A** and **Server B**, pull the latest updates and apply them:

``` bash
cd /var/www/fspbx
git pull
php artisan app:update
```

* * * * *

### **2\. Check for Pending Database Migrations (Server A Only)**

On **Server A**, verify if any new migrations need to be applied:

`php artisan migrate:status`

If you see any pending migrations, run:

`php artisan migrate`

* * * * *

### **3\. Sync Migrations on Server B**

Because **Server B** replicates from **Server A**, it may incorrectly mark migrations as already applied. To ensure proper synchronization, reset and reapply the latest batch:

```bash
php artisan migrate:delete-last-batch
php artisan migrate
```

* * * * *

### **4\. Refresh Logical Replication Subscriptions**

Finally, on **both servers**, refresh the subscriptions to ensure replication consistency:

`php artisan db:refresh-subscriptions`
