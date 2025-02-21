#!/bin/bash

# Set error handling
set -e

# Define installation variables
INSTALL_DIR="/var/www/fspbx"
BRANCH="main"  # Change to a specific branch if needed
PHP_VERSION="8.1"

echo "Starting FS PBX installation..."

# Ensure Git is installed
if ! command -v git &> /dev/null; then
    echo "Installing Git..."
    apt update && apt install -y git
fi

# Ensure required dependencies are installed
echo "Installing dependencies..."
apt install -y curl unzip software-properties-common

# Ensure Composer is installed
if ! command -v composer &> /dev/null; then
    echo "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
fi

# Clone FS PBX repository
echo "Cloning FS PBX repository..."
rm -rf $INSTALL_DIR
git clone --depth 1 --branch $BRANCH https://github.com/nemerald-voip/fspbx.git $INSTALL_DIR
