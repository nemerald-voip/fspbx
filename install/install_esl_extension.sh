#!/bin/bash

# Function to print success message
print_success() {
    echo -e "\e[32m$1 \e[0m"  # Green text
}

# Function to print error message
print_error() {
    echo -e "\e[31m$1 \e[0m"  # Red text
}

# Find PHP extension directory
EXTENSION_DIR=$(php -r "echo ini_get('extension_dir');")
if [ -z "$EXTENSION_DIR" ]; then
    print_error "Failed to find PHP extension directory."
    exit 1
else
    print_success "PHP extension directory found: $EXTENSION_DIR"
fi

# Copy esl.so to the extension directory
cp "$(dirname "$0")/esl.so" "$EXTENSION_DIR"
if [ $? -eq 0 ]; then
    print_success "esl.so copied successfully to $EXTENSION_DIR"
else
    print_error "Error copying esl.so to $EXTENSION_DIR."
    exit 1
fi

# Get PHP version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
if [ -z "$PHP_VERSION" ]; then
    print_error "Failed to detect PHP version."
    exit 1
else
    print_success "PHP version detected: $PHP_VERSION"
fi

# Define PHP CLI and FPM ini paths
PHP_CLI_INI="/etc/php/$PHP_VERSION/cli/php.ini"
PHP_FPM_INI="/etc/php/$PHP_VERSION/fpm/php.ini"
PHP_FPM_CONF_DIR="/etc/php/$PHP_VERSION/fpm/conf.d/"
PHP_CLI_CONF_DIR="/etc/php/$PHP_VERSION/cli/conf.d/"

# Function to add extension to a PHP ini file
add_extension_once() {
    INI_FILE=$1
    if grep -q "extension=esl.so" "$INI_FILE"; then
        print_success "esl.so is already added to $INI_FILE"
    else
        echo "extension=esl.so" | sudo tee -a "$INI_FILE" > /dev/null
        if [ $? -eq 0 ]; then
            print_success "Added esl.so to $INI_FILE"
        else
            print_error "Error adding esl.so to $INI_FILE"
            exit 1
        fi
    fi
}

# Add esl.so to both CLI and FPM configurations
add_extension_once "${PHP_CLI_CONF_DIR}30-esl.ini"
add_extension_once "${PHP_FPM_CONF_DIR}30-esl.ini"

# Restart PHP-FPM to load the new extension
SERVICE_NAME="php$PHP_VERSION-fpm"
sudo systemctl restart $SERVICE_NAME
if [ $? -eq 0 ]; then
    print_success "$SERVICE_NAME restarted successfully."
else
    print_error "Error restarting $SERVICE_NAME."
    exit 1
fi

echo -e "\e[32m🎉 Installation completed successfully! ESL is now enabled for both CLI and FPM. \e[0m"
