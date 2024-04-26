#!/bin/bash

# Function to print success message
print_success() {
    echo -e "\e[32m$1 \e[0m"  # Green text
}

# Function to print error message
print_error() {
    echo -e "\e[31m$1 \e[0m"  # Red text
}

# Find the PHP extension directory
EXTENSION_DIR=$(php -r "echo ini_get('extension_dir');")

if [ -z "$EXTENSION_DIR" ]; then
    print_error "Failed to find PHP extension directory."
    exit 1
else
    print_success "PHP extension directory found: $EXTENSION_DIR"
fi

# Copy the esl.so extension to the found directory
cp "$(dirname "$0")/esl.so" "$EXTENSION_DIR"
if [ $? -eq 0 ]; then
    print_success "esl.so copied successfully to $EXTENSION_DIR"
else
    print_error "Error occurred while copying esl.so to $EXTENSION_DIR."
    exit 1
fi

# Get PHP version
PHP_VERSION=$(php -v | grep -oP '^PHP \K[0-9]+\.[0-9]+')
if [ -z "$PHP_VERSION" ]; then
    print_error "Failed to find PHP version."
    exit 1
else
    print_success "PHP version found: $PHP_VERSION"
fi

# Get PHP configuration file for FPM
PHP_INI_PATH=$(php --ini | grep "Loaded Configuration File" | awk '{print $4}')
if [ -z "$PHP_INI_PATH" ]; then
    print_error "Failed to find PHP configuration file."
    exit 1
else
    print_success "PHP configuration file found: $PHP_INI_PATH"
fi

# Determine the PHP-FPM configuration path based on the PHP version
PHP_FPM_INI_PATH="/etc/php/$PHP_VERSION/fpm/php.ini"
PHP_FPM_CONF_DIR="/etc/php/$PHP_VERSION/fpm/conf.d/"

if [ -f "$PHP_FPM_INI_PATH" ]; then
    print_success "PHP-FPM configuration file for PHP $PHP_VERSION is located at: $PHP_FPM_INI_PATH"
else
    print_error "PHP-FPM configuration file not found for PHP $PHP_VERSION."
fi


# Append the extension configuration to the specific ini file in the conf.d directory
echo "extension=esl.so" | sudo tee -a "${PHP_FPM_CONF_DIR}30-esl.ini" > /dev/null
if [ $? -eq 0 ]; then
    print_success "esl extension added to PHP configuration successfully."
else
    print_error "Error occurred while adding esl extension to PHP configuration."
    exit 1
fi

# Restart PHP-FPM to load the new extension
SERVICE_NAME="php$PHP_VERSION-fpm"
sudo systemctl restart $SERVICE_NAME
if [ $? -eq 0 ]; then
    print_success "$SERVICE_NAME restarted successfully."
else
    print_error "Error occurred during $SERVICE_NAME restart."
    exit 1
fi

echo "All tasks completed successfully!"
