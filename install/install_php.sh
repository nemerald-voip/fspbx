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

print_success "Starting PHP Installation..."

# Detect OS and architecture
OS_CODENAME=$(lsb_release -cs)
CPU_ARCHITECTURE=$(dpkg --print-architecture)

# Set default PHP version to 8.1 if not set
PHP_VERSION=${PHP_VERSION:-"8.1"}

print_success "Installing PHP version: $PHP_VERSION"

# Check if PHP is already installed
if command -v php &>/dev/null && php -v | grep -q "PHP $PHP_VERSION"; then
    print_success "PHP $PHP_VERSION is already installed. Skipping installation."
    exit 0
fi

# Install required dependencies
apt-get update
apt-get install -y apt-transport-https lsb-release ca-certificates curl wget gnupg2

# Add PHP repository from packages.sury.org
print_success "Adding PHP Repository..."
wget -qO- https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /etc/apt/keyrings/sury-php.gpg
echo "deb [signed-by=/etc/apt/keyrings/sury-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
apt-get update -y

# Install PHP and necessary extensions
print_success "Installing PHP $PHP_VERSION..."
apt-get install -y --no-install-recommends \
    php$PHP_VERSION php$PHP_VERSION-common php$PHP_VERSION-cli php$PHP_VERSION-dev \
    php$PHP_VERSION-fpm php$PHP_VERSION-pgsql php$PHP_VERSION-sqlite3 php$PHP_VERSION-odbc \
    php$PHP_VERSION-curl php$PHP_VERSION-imap php$PHP_VERSION-xml php$PHP_VERSION-gd \
    php$PHP_VERSION-mbstring php$PHP_VERSION-ldap php$PHP_VERSION-inotify

# Set PHP configuration file path dynamically
PHP_INI_FILE="/etc/php/$PHP_VERSION/fpm/php.ini"

# Update PHP configuration settings
print_success "Configuring PHP settings..."
sed -i 's#post_max_size = .*#post_max_size = 80M#g' $PHP_INI_FILE
sed -i 's#upload_max_filesize = .*#upload_max_filesize = 80M#g' $PHP_INI_FILE
sed -i 's#;max_input_vars = .*#max_input_vars = 8000#g' $PHP_INI_FILE
sed -i 's#; max_input_vars = .*#max_input_vars = 8000#g' $PHP_INI_FILE

# Install Ioncube if on x86 architecture
if [[ "$CPU_ARCHITECTURE" == "x86_64" ]]; then
    print_success "Installing Ioncube..."
    bash ./ioncube.sh
fi

sleep 2

# Restart PHP-FPM
print_success "Restarting PHP-FPM..."
systemctl daemon-reload
systemctl restart php$PHP_VERSION-fpm

sleep 6

mkdir -p /etc/systemd/system/php8.1-fpm.service.d
cat > /etc/systemd/system/php8.1-fpm.service.d/override.conf << 'EOF'
[Service]
RuntimeDirectory=php
RuntimeDirectoryMode=0755
EOF

systemctl daemon-reload

print_success "PHP $PHP_VERSION installation completed successfully!"
