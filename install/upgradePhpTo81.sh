#!/bin/bash

# Function to print success message
print_success() {
    echo -e "\e[32m$1 \e[0m"  # Green text
}

# Function to print error message
print_error() {
    echo -e "\e[31m$1 \e[0m"  # Red text
}

# Update and upgrade
apt update && apt upgrade -y
if [ $? -eq 0 ]; then
    print_success "System updated and upgraded successfully."
else
    print_error "Error occurred during update and upgrade."
    exit 1
fi

# Remove PHP 7.4
apt-get remove -y php7.4 php7.4-cli php7.4-fpm php7.4-pgsql php7.4-sqlite3 php7.4-odbc php7.4-curl php7.4-imap php7.4-xml php7.4-gd php7.4-ldap
if [ $? -eq 0 ]; then
    print_success "PHP 7.4 removed successfully."
else
    print_error "Error occurred during PHP 7.4 removal."
    exit 1
fi

apt autoremove -y
if [ $? -eq 0 ]; then
    print_success "Unused packages removed successfully."
else
    print_error "Error occurred during autoremove."
    exit 1
fi

# Install Sury Repo
apt -y install apt-transport-https lsb-release ca-certificates curl wget gnupg2
wget -qO- https://packages.sury.org/php/apt.gpg | gpg --dearmor > /etc/apt/trusted.gpg.d/sury-php-8.x.gpg
sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'
apt update
if [ $? -eq 0 ]; then
    print_success "Sury Repo installed and updated successfully."
else
    print_error "Error occurred during Sury Repo installation."
    exit 1
fi

# Install PHP 8.1
apt-get install -y php8.1 php8.1-cli php8.1-dev php8.1-fpm php8.1-pgsql php8.1-sqlite3 php8.1-odbc php8.1-curl php8.1-imap php8.1-xml php8.1-gd php8.1-mbstring php8.1-ldap
if [ $? -eq 0 ]; then
    print_success "PHP 8.1 installed successfully."
else
    print_error "Error occurred during PHP 8.1 installation."
    exit 1
fi

sudo apt install -y imagemagick php8.1-imagick
if [ $? -eq 0 ]; then
    print_success "Imagemagick and PHP Imagick installed successfully."
else
    print_error "Error occurred during Imagemagick and PHP Imagick installation."
    exit 1
fi

sudo apt-get install -y php8.1-zip
if [ $? -eq 0 ]; then
    print_success "PHP 8.1-zip installed successfully."
else
    print_error "Error occurred during PHP 8.1-zip installation."
    exit 1
fi

service php8.1-fpm restart
if [ $? -eq 0 ]; then
    print_success "PHP 8.1-fpm restarted successfully."
else
    print_error "Error occurred during PHP 8.1-fpm restart."
    exit 1
fi

# Switch PHP version to 8.1
sudo update-alternatives --config php
if [ $? -eq 0 ]; then
    print_success "PHP version switched to 8.1 successfully."
else
    print_error "Error occurred during PHP version switch."
    exit 1
fi

# Install predis (php-redis)
apt -y install php-redis
if [ $? -eq 0 ]; then
    print_success "Predis (php-redis) installed successfully."
else
    print_error "Error occurred during Predis (php-redis) installation."
    exit 1
fi

echo "All tasks completed successfully!"
