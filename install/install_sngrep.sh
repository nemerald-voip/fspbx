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

print_success "Installing Sngrep..."

# Detect OS details
OS_CODENAME=$(lsb_release -cs)
CPU_ARCHITECTURE=$(dpkg --print-architecture)

# Check if sngrep is already installed
if command -v sngrep &>/dev/null; then
    print_success "Sngrep is already installed. Skipping installation."
    exit 0
fi

# Install dependencies for all installations
apt-get update
apt-get install -y git autoconf automake gcc make libncurses5-dev libpcap-dev libssl-dev libpcre3-dev

# Install Sngrep based on CPU architecture
if [[ "$CPU_ARCHITECTURE" == "arm"* ]]; then
    print_success "Installing Sngrep from source for ARM architecture..."
    
    cd /usr/local/src
    git clone https://github.com/irontec/sngrep.git
    cd sngrep
    ./bootstrap.sh
    ./configure
    make install

else
    print_success "Installing Sngrep from package repository..."
    
    # Add Sngrep repository for older Debian versions
    if [[ "$OS_CODENAME" == "jessie" ]]; then
        echo "deb [signed-by=/usr/share/keyrings/sngrep-keyring.gpg] http://packages.irontec.com/debian $OS_CODENAME main" | tee /etc/apt/sources.list.d/sngrep.list
        wget -qO- http://packages.irontec.com/public.key | gpg --dearmor -o /usr/share/keyrings/sngrep-keyring.gpg
        apt-get update
    fi

    apt-get install -y sngrep
fi

print_success "Sngrep installation completed successfully!"
