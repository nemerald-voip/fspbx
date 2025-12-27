---
id: branding
title: Branding
slug: /configuration/branding/
sidebar_position: 1
---

# Branding
FS PBX allows custom branding by letting administrators upload their own logos and favicons. This guide explains how to upload and configure the necessary image files to ensure full functionality across different devices and browsers.

## 1. Generate the Required Files
To generate all required favicon and logo files, use [Real Favicon Generator](https://realfavicongenerator.net/). Upload your branded logo, and the tool will generate a complete set of files.

### Required Files
* **Favicons:**

   * favicon.ico
   * favicon-16x16.png
   * favicon-32x32.png
* **Apple and Android Icons:**

  * apple-touch-icon.png
  * android-chrome-192x192.png
  * android-chrome-384x384.png

* **Windows Tile and Safari Icons:**

  * mstile-150x150.png
  * safari-pinned-tab.svg

* **Configuration Files:**

  * browserconfig.xml
  * site.webmanifest

* **Main Logo:**

  * logo.png (Main branding logo used in FS PBX)

## 2. Uploading the Files
### Step 1: Upload the Files
Copy the generated favicon and logo files to the `/storage/app/public/` directory:

`/var/www/fspbx/storage/app/public/`

### Step 2: Ensure proper permissions:

`chmod -R 755 /var/www/fspbx/storage/app/public/`

`chown -R www-data:www-data /var/www/fspbx/storage/app/public/`

## 3. Configuring FS PBX to Use the New Logo
### Step 1: Update the Logo
To apply the new logo in FS PBX:

1. Log in to FS PBX.
1. Navigate to **Advanced > Default Settings**.
1. Find the setting `menu_brand_image` under the **Theme** category.
1. Modify the value to: `/storage/logo.png`

5. Click **Save** and apply changes.

### Step 2: Update the Favicon
To apply the new favicon:

1. Go to **Advanced > Default Settings**.
1. Find the setting `favicon` under the **Theme** category.
1. Modify the value to: `/storage/favicon.ico`
1. Click Save and apply changes.

## 4. Verifying the Changes
After uploading the files and updating settings, clear your browser cache verify that everything is working correctly:

## 5. Troubleshooting
* Icons Not Updating?

  * Try clearing your cache or restarting the web server.

* Permissions Issue?

  * Ensure the www-data user has read access to `/var/www/fspbx/storage/app/public/`.

* Incorrect Logo or Favicon Path?

  * Double-check that the menu_brand_image and favicon settings under Advanced > Default Settings are correctly set to `/storage/logo.png` and `/storage/favicon.ico`, respectively.

## Conclusion
By following these steps, you will have a fully branded FS PBX with custom logos and favicons optimized for all devices and platforms.



<!-- # Create a Page

Add **Markdown or React** files to `src/pages` to create a **standalone page**:

- `src/pages/index.js` → `localhost:3000/`
- `src/pages/foo.md` → `localhost:3000/foo`
- `src/pages/foo/bar.js` → `localhost:3000/foo/bar`

## Create your first React Page

Create a file at `src/pages/my-react-page.js`:

```jsx title="src/pages/my-react-page.js"
import React from 'react';
import Layout from '@theme/Layout';

export default function MyReactPage() {
  return (
    <Layout>
      <h1>My React page</h1>
      <p>This is a React page</p>
    </Layout>
  );
}
```

A new page is now available at [http://localhost:3000/my-react-page](http://localhost:3000/my-react-page).

## Create your first Markdown Page

Create a file at `src/pages/my-markdown-page.md`:

```mdx title="src/pages/my-markdown-page.md"
# My Markdown page

This is a Markdown page
```

A new page is now available at [http://localhost:3000/my-markdown-page](http://localhost:3000/my-markdown-page). -->
