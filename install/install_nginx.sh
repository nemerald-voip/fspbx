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

print_success "Starting Nginx Installation..."

# Check if Nginx is already installed
if command -v nginx &>/dev/null; then
    print_success "Nginx is already installed. Skipping installation."
else
    print_success "Installing Nginx..."
    apt-get update
    apt-get install -y nginx
fi

# Remove default Nginx configuration
if [ -f /etc/nginx/sites-enabled/default ]; then
    rm -f /etc/nginx/sites-enabled/default
fi

# Reload and restart Nginx
print_success "Restarting Nginx..."
systemctl restart nginx
systemctl enable nginx

print_success "Nginx installation and configuration completed successfully!"
