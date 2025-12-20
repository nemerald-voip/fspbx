---
id: contact-center-module
title: Installing the FS PBX Contact Center Module
slug: /configuration/contact-center/installing-contact-center-module/
sidebar_position: 1
---

# Installing the FS PBX Contact Center Module

The FS PBX Contact Center is a premium module that adds advanced call center functionality to your FS PBX system. Before proceeding with the installation, please note that a valid license is required, which can be obtained through our sales team. Trial licenses are available for a 30-day evaluation period.

[YouTube Video Walkthrough](https://youtu.be/OEshZIH5qAs)

## Step 1: Ensure Git is Up-to-Date
Start by updating your local repository to the latest version by running the following command:

`git pull`

## Step 2: Check for Pending Laravel Migrations
Next, check if there are any pending Laravel migrations:

`php artisan migrate:status`

If there are pending migrations, run the following command to apply them:

`php artisan migrate`

## Step 3: Apply New Updates
To apply any new updates that have been downloaded, run the `app:update` Artisan command:

`php artisan app:update`

## Step 4: Add License Key via Web Interface
1. Open the FS PBX web interface and navigate to the /pro-features page.
1. Edit the **FS PBX Pro Features** and add your license key.
1. Activate the license and ensure it's valid.

## Step 5: Download the Contact Center Module
1. Go to the Downloads tab.
1. Click the Download button. This will download all modules that your license allows.
1. Once you receive confirmation that the modules have been successfully installed, switch back to SSH.

## Step 6: Run Update Command Again
Run the `app:update` command again to ensure all components are properly updated:

`php artisan app:update`

## ****Step 7: Assign Contact Center Admin Role****
1. Navigate to the /users page.
1. Assign the **Contact Center Admin** role to your user.
1. Log out and then log back in. The **Contact Center** tile should now be visible on the dashboard.

## Step 8: Set Up a Contact Center Agent
Go to the /extensions page.
Click on the three dots next to an extension and choose **Contact Center -> Make Agent**.

## Step 9: Create a Contact Center Queue
1. Go back to the dashboard and click on **Settings** under **Contact Center**.
1. Create your first contact center queue and assign the agent you just created.

## Step 10: Access the Contact Center Dashboard
Return to the main dashboard and click on **View All** under the **Contact Center** tile to open the Contact Center dashboard.

