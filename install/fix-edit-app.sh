#!/bin/bash
set -e

TARGET="/var/www/fspbx/public/app/edit"
TMPDIR="/var/www/fspbx-tmp-$$"

echo "Removing existing folder: $TARGET"
rm -rf "$TARGET"

echo "Cloning latest repo to $TMPDIR ..."
git clone --depth 1 --branch master https://github.com/nemerald-voip/fusionpbx.git "$TMPDIR"

echo "Copying 'edit' app folder into place..."
cp -r "$TMPDIR/app/edit" "$TARGET"

echo "Cleaning up temporary files..."
rm -rf "$TMPDIR"

echo "Setting ownership and permissions..."
chown -R www-data:www-data "$TARGET"
chmod -R 755 "$TARGET"

echo "✅ Edit app has been updated from GitHub."
