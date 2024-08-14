#!/bin/bash

# Function to print success message
print_success() {
    echo -e "\e[32m$1 \e[0m"  # Green text
}

# Function to print error message
print_error() {
    echo -e "\e[31m$1 \e[0m"  # Red text
}

# Function to check if a command was successful
check_command() {
    if [ $? -eq 0 ]; then
        print_success "$1"
    else
        print_error "$2"
        exit 1
    fi
}

# Backup the sudoers file
SUDOERS_FILE="/etc/sudoers"
BACKUP_SUDOERS="/etc/sudoers.bak"
SUDOERS_TEMP=$(mktemp)
PERMISSION="www-data ALL=(ALL) NOPASSWD: /sbin/iptables"

if [ -f "$SUDOERS_FILE" ]; then
    sudo cp "$SUDOERS_FILE" "$BACKUP_SUDOERS"
    check_command "Sudoers file backed up successfully." "Failed to backup sudoers file."
else
    print_error "Sudoers file not found."
    exit 1
fi

# Check if the permission is already present
if ! sudo grep -qF "$PERMISSION" "$SUDOERS_FILE"; then
    # Add the permission if not present
    sudo cat "$SUDOERS_FILE" > "$SUDOERS_TEMP"
    echo "$PERMISSION" | sudo tee -a "$SUDOERS_TEMP" > /dev/null

    # Validate the new sudoers file
    sudo visudo -c -f "$SUDOERS_TEMP"
    if [ $? -eq 0 ]; then
        sudo cp "$SUDOERS_TEMP" "$SUDOERS_FILE"
        check_command "Sudoers file updated successfully." "Failed to update sudoers file."
    else
        print_error "The sudoers file update failed validation. The original file has been preserved."
        exit 1
    fi
else
    print_success "Permission already exists in the sudoers file. No changes made."
fi

# Cleanup temporary files
rm "$SUDOERS_TEMP"

print_success "Web server user now allowed execute iptables commands without password!"
