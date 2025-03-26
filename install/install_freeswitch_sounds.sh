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

print_success "Installing FreeSWITCH Sounds..."

# Change working directory to FreeSWITCH source
cd /usr/src/freeswitch

# Compile and install sound files
print_success "Downloading and installing default FreeSWITCH sounds..."
make sounds-install moh-install
make hd-sounds-install hd-moh-install
make cd-sounds-install cd-moh-install

# Ensure music directory exists before moving files
mkdir -p /usr/share/freeswitch/sounds/music/default

# Move music files into the correct directory
if mv /usr/share/freeswitch/sounds/music/*000 /usr/share/freeswitch/sounds/music/default/ 2>/dev/null; then
    print_success "Music files moved to /usr/share/freeswitch/sounds/music/default successfully."
else
    print_error "No music files found to move. This may not be an error."
fi

print_success "FreeSWITCH sounds installation completed!"
