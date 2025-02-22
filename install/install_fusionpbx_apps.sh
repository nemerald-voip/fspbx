#!/bin/bash

# Set error handling
set -e

# Function to print success messages
print_success() {
    echo -e "\e[32m$1 \e[0m"
}

# Function to print error messages
print_error() {
    echo -e "\e[31m$1 \e[0m"
}

print_success "Starting FS PBX Apps Installation..."

# Define the installation directory
APP_DIR="/var/www/fspbx/public/app"

# Ensure the apps directory exists
mkdir -p "$APP_DIR"
chown -R www-data:www-data "$APP_DIR"

# List of FS PBX apps and their correct repositories
declare -A APPS=(
    ["transcribe"]="https://github.com/fusionpbx/fusionpbx-app-transcribe.git"
    ["speech"]="https://github.com/fusionpbx/fusionpbx-app-speech.git"
    ["device_logs"]="https://github.com/fusionpbx/fusionpbx-app-device_logs.git"
    ["dialplan_tools"]="https://github.com/fusionpbx/fusionpbx-app-dialplan_tools.git"
    ["edit"]="https://github.com/fusionpbx/fusionpbx-app-edit.git"
    ["sip_trunks"]="https://github.com/fusionpbx/fusionpbx-app-sip_trunks.git"
)

# Clone all apps into the app directory
for APP_NAME in "${!APPS[@]}"; do
    REPO_URL="${APPS[$APP_NAME]}"
    
    print_success "Installing $APP_NAME from $REPO_URL..."
    
    # Define the full clone path
    APP_PATH="$APP_DIR/$APP_NAME"

    # Remove existing directory if it exists (to avoid conflicts)
    rm -rf "$APP_PATH"

    # Clone the latest version from the correct GitHub repository
    git clone --depth 1 "$REPO_URL" "$APP_PATH"

    # Set correct permissions
    chown -R www-data:www-data "$APP_PATH"
    print_success "$APP_NAME installed successfully."
done

print_success "All FS PBX Apps installed successfully!"
