#!/bin/bash
set -e

TARGET="/var/www/fspbx/public/app/edit"

# Install svn if it's not already installed
if ! command -v svn &> /dev/null; then
    echo "Installing subversion (svn)..."
    apt-get update
    apt-get install -y subversion
fi

echo "Removing existing folder: $TARGET"
rm -rf "$TARGET"

echo "Downloading latest edit app folder from GitHub..."
svn export --force https://github.com/nemerald-voip/fusionpbx/trunk/app/edit "$TARGET"

echo "Setting ownership and permissions..."
chown -R www-data:www-data "$TARGET"
chmod -R 755 "$TARGET"

echo "✅ /var/www/fspbx/public/app/edit has been updated from GitHub."
