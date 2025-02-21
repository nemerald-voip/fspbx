#!/bin/bash

# Set error handling
set -e

# Function to print success message
print_success() {
    echo -e "\e[32m$1 \e[0m"  # Green text
}

# Function to print error message
print_error() {
    echo -e "\e[31m$1 \e[0m"  # Red text
}

# Ensure Git is installed
if ! command -v git &> /dev/null; then
    apt update && apt install -y git
    print_success "Git installed successfully."
fi

# Define installation directory
INSTALL_DIR="/var/www/fspbx"

# Clone FS PBX repository
print_success "Cloning FS PBX repository..."
rm -rf $INSTALL_DIR
git clone --depth 1 https://github.com/nemerald-voip/fspbx.git $INSTALL_DIR
print_success "FS PBX repository cloned successfully."

# Run the main installer script
print_success "Running FS PBX installation script..."
#bash $INSTALL_DIR/install/install.sh
print_success "FS PBX installation completed successfully!"
