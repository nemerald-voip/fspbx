---
id: retrieving-logs
title: Retrieving Logs in FS PBX
slug: /troubleshooting/retrieving-logs/
sidebar_position: 1
---

# Retrieving Logs in FS PBX

In **FS PBX**, all application logs are stored inside the `storage/logs` directory. These logs record system activity, errors, and debugging information --- making them an essential tool when troubleshooting or checking system behavior.

* * * * *

📂 Log File Location
--------------------

Laravel logs are stored here:

`/var/www/fspbx/storage/logs/`

The main file is:

`laravel.log`


* * * * *

🖥️ Viewing Logs in Real Time
-----------------------------

To **watch new log entries as they happen**, use the `tail` command from your FS PBX server's terminal.

`cd /var/www/fspbx
tail -f -n 200 storage/logs/laravel.log`

**Explanation:**

-   `tail` --- displays the end (latest lines) of a file

-   `-f` --- "follow" mode; automatically shows new log entries in real time

-   `-n 200` --- shows the last 200 lines initially

-   `storage/logs/laravel.log` --- the path to Laravel's main log file

* * * * *

🧠 When to Use This
-------------------

You might want to check the Laravel logs when:

-   🔍 You encounter an error in FS PBX and need detailed information for troubleshooting.

-   ⚡ You suspect something isn't working correctly and want to verify it in real time.

-   🧩 You're debugging background jobs, API calls, or provisioning tasks.

-   🐞 You're submitting a bug report on GitHub and need to include detailed log output.

* * * * *

🛑 Stopping the Log Stream
--------------------------

To stop watching the log, press:

`CTRL + C`

This exits `tail` and returns you to the command prompt.

* * * * *

🔍 Additional Tips
------------------

-   If the file grows large, you can clear it safely:

    `> storage/logs/laravel.log`

    *(This empties the file without deleting it.)*

-   To view logs without live updates:

    `cat storage/logs/laravel.log | less`

-   To search for specific keywords:

    `grep "Error" storage/logs/laravel.log`