---
id: how-to-reset-superadmin-password
title: How to Reset Superadmin Password
slug: /configuration/users-and-groups/how-to-reset-superadmin-password/
sidebar_position: 1
---

# How to Reset Superadmin Password

### Overview

The `create:superadmin` Artisan command allows administrators to create a new Superadmin user or reset the password of an existing Superadmin in the FS PBX system. This command ensures that the Superadmin is assigned to the appropriate domain and has the necessary permissions.

### Usage

To execute the command, open a terminal and run:

`php artisan create:superadmin`

This command will prompt the user to enter an email address for the Superadmin account. If an account with the provided email already exists, the command will reset its password. If the account does not exist, it will create a new Superadmin user.

### Steps to Run the Command

1. **Access the server**

   * Ensure you have SSH or direct terminal access to the FS PBX server.

2. **Navigate to the FS PBX application directory**

   * Example:

      `cd /var/www/fspbx`

3. **Run the command**

   * Execute:

      `php artisan create:superadmin`

4. **Enter the email address**

   * The system will prompt:

      `Enter the Superadmin email address:`

   * Type the desired email and press Enter.

5. **View the output**

   * If the Superadmin account exists, the password will be reset.

   * If the account does not exist, it will be created.

   * The domain admin.localhost will be created or ensured to exist.

   * The user will be assigned to the Superadmin group.

### Expected Output

After successful execution, the terminal will display:

```
✅ FS PBX Superadmin Created Successfully!
=======================================
🔗 Login URL: https://your-pbx-domain.com
👤 Email: admin@example.com
🔑 Password: (hidden for security)
=======================================
(Use this password to log in, then change it immediately.)
```

### Security Considerations

* Do not share the generated password openly.

* Change the password immediately after logging in.

* Run this command only as an authorized administrator.

* Ensure the command is executed on a secure server.