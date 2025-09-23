---
sidebar_position: 3
---

# How to update
Pull new updates and install them by running these commands:

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