#!/bin/bash

# Function to print success message
print_success() {
    echo -e "\e[32m$1 \e[0m"  # Green text
}

# Function to print error message
print_error() {
    echo -e "\e[31m$1 \e[0m"  # Red text
}


print_success  "Welcome to FS PBX installation script"

# Set the environment variable to suppress prompts
export DEBIAN_FRONTEND=noninteractive

# Run the upgrade with the option to keep the existing configuration files
apt update && apt -o Dpkg::Options::="--force-confold" upgrade -y

# Check if the upgrade was successful
if [ $? -eq 0 ]; then
    print_success "System updated and upgraded successfully."
else
    print_error "Error occurred during update and upgrade."
    exit 1
fi

# Unset the environment variable to restore normal behavior
unset DEBIAN_FRONTEND

# Install essential dependencies
print_success "Installing essential dependencies..."
apt-get install -y \
    wget \
    lsb-release \
    systemd \
    systemd-sysv \
    ca-certificates \
    dialog \
    nano \
    net-tools \
    gpg \
    ffmpeg \
    gnupg \
    ghostscript \
    libtiff-tools \
    libreoffice \
    libreoffice-base \
    libreoffice-common \
    libreoffice-java-common \
    supervisor \
    redis-server \
    software-properties-common \
    apt-transport-https
if [ $? -eq 0 ]; then
    print_success "Essential dependencies installed successfully."
else
    print_error "Error occurred during essential dependency installation."
    exit 1
fi

# Install SNMP and configure it
print_success "Installing and configuring SNMP..."
apt-get install -y snmpd
if [ $? -eq 0 ]; then
    echo "rocommunity public" > /etc/snmp/snmpd.conf
    service snmpd restart
    print_success "SNMP installed and configured successfully."
else
    print_error "Error occurred while installing SNMP."
    exit 1
fi

print_success "Configuring IPTables firewall rules..."
bash /var/www/fspbx/install/configure_iptables.sh
if [ $? -eq 0 ]; then
    print_success "IPTables configured successfully."
else
    print_error "Error occurred while configuring IPTables."
    exit 1
fi

print_success "Installing Sngrep..."
bash /var/www/fspbx/install/install_sngrep.sh
if [ $? -eq 0 ]; then
    print_success "Sngrep installed successfully."
else
    print_error "Error occurred while installing Sngrep."
    exit 1
fi

print_success "Installing PHP..."
bash /var/www/fspbx/install/install_php.sh
if [ $? -eq 0 ]; then
    print_success "PHP installed successfully."
else
    print_error "Error occurred while installing PHP."
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

# Install predis (php-redis)
apt -y install php8.1-redis
if [ $? -eq 0 ]; then
    print_success "Predis (php-redis) installed successfully."
else
    print_error "Error occurred during Predis (php-redis) installation."
    exit 1
fi

# Update PHP configuration settings in php.ini
sudo sed 's#post_max_size = .*#post_max_size = 80M#g' -i /etc/php/8.1/fpm/php.ini
if [ $? -eq 0 ]; then
    print_success "post_max_size updated to 80M in php.ini."
else
    print_error "Error occurred while updating post_max_size in php.ini."
    exit 1
fi

sudo sed 's#upload_max_filesize = .*#upload_max_filesize = 80M#g' -i /etc/php/8.1/fpm/php.ini
if [ $? -eq 0 ]; then
    print_success "upload_max_filesize updated to 80M in php.ini."
else
    print_error "Error occurred while updating upload_max_filesize in php.ini."
    exit 1
fi

sudo sed 's#memory_limit = .*#memory_limit = 512M#g' -i /etc/php/8.1/fpm/php.ini
if [ $? -eq 0 ]; then
    print_success "memory_limit updated to 512M in php.ini."
else
    print_error "Error occurred while updating memory_limit in php.ini."
    exit 1
fi

sudo sed 's#;max_input_vars = .*#max_input_vars = 8000#g' -i /etc/php/8.1/fpm/php.ini
if [ $? -eq 0 ]; then
    print_success "max_input_vars updated to 8000 in php.ini."
else
    print_error "Error occurred while updating max_input_vars in php.ini."
    exit 1
fi

sudo sed 's#; max_input_vars = .*#max_input_vars = 8000#g' -i /etc/php/8.1/fpm/php.ini
if [ $? -eq 0 ]; then
    print_success "max_input_vars (with space) updated to 8000 in php.ini."
else
    print_error "Error occurred while updating max_input_vars (with space) in php.ini."
    exit 1
fi

sudo sed -i 's/^\(;*\)\s*session.gc_maxlifetime\s*=.*/\1session.gc_maxlifetime = 7200/' /etc/php/8.1/fpm/php.ini
if [ $? -eq 0 ]; then
    print_success "session.gc_maxlifetime updated to 7200 in php.ini."
else
    print_error "Error occurred while updating session.gc_maxlifetime in php.ini."
    exit 1
fi

# Include the install_esl_extension.sh script
sh /var/www/fspbx/install/install_esl_extension.sh 
if [ $? -eq 0 ]; then
    print_success "ESL extension installation script executed successfully."
else
    print_error "Error occurred while executing ESL extension installation script."
    exit 1
fi

# Include the install_cron_jobs.sh script
sh /var/www/fspbx/install/install_cron_jobs.sh 
if [ $? -eq 0 ]; then
    print_success "Cron bob installation script executed successfully."
else
    print_error "Error occurred while executing cron job installation script."
    exit 1
fi

# Include the add_web_server_to_sudoers.sh script
sh /var/www/fspbx/install/add_web_server_to_sudoers.sh 
if [ $? -eq 0 ]; then
    print_success "add_web_server_to_sudoers.sh script executed successfully."
else
    print_error "Error occurred while executing add_web_server_to_sudoers.sh script."
    exit 1
fi

# Install Composer
curl -sS https://getcomposer.org/installer | php
if [ $? -eq 0 ]; then
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
    if [ $? -eq 0 ]; then
        print_success "Composer installed successfully."
    else
        print_error "Error occurred while setting up Composer."
        exit 1
    fi
else
    print_error "Error occurred during Composer installation."
    exit 1
fi

# Install Node.js
if [ $? -eq 0 ]; then
    if [ $? -eq 0 ]; then
        sudo mkdir -p /etc/apt/keyrings
        curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | sudo gpg --dearmor --batch --yes -o /etc/apt/keyrings/nodesource.gpg
        if [ $? -eq 0 ]; then
            NODE_MAJOR=20
            echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_MAJOR.x nodistro main" | sudo tee /etc/apt/sources.list.d/nodesource.list > /dev/null
            sudo apt-get update
            if [ $? -eq 0 ]; then
                sudo apt-get install -y nodejs
                if [ $? -eq 0 ]; then
                    print_success "Node.js installed successfully."
                else
                    print_error "Error occurred during Node.js installation."
                    exit 1
                fi
            else
                print_error "Error occurred during APT update after adding Node.js repository."
                exit 1
            fi
        else
            print_error "Error occurred while setting up Node.js GPG key."
            exit 1
        fi
    else
        print_error "Error occurred during installation of CA certificates, curl, or gnupg."
        exit 1
    fi
else
    print_error "Error occurred during APT update."
    exit 1
fi


# Change to the Freeswitch PBX directory
cd /var/www/fspbx/
if [ $? -eq 0 ]; then
    print_success "Changed to /var/www/fspbx/ directory."
else
    print_error "Error occurred while changing directory to /var/www/fspbx/."
    exit 1
fi

print_success "Installing Nginx..."
bash /var/www/fspbx/install/install_nginx.sh
if [ $? -eq 0 ]; then
    print_success "Nginx installed successfully."
else
    print_error "Error occurred while installing Nginx."
    exit 1
fi


# Nginx configuration
if [ -f /etc/nginx/sites-enabled/fusionpbx ]; then
    rm /etc/nginx/sites-enabled/fusionpbx
    if [ $? -eq 0 ]; then
        print_success "Removed old fusionpbx site from sites-enabled."
    else
        print_error "Error occurred while removing fusionpbx from sites-enabled."
        exit 1
    fi
else
    print_success "No existing fusionpbx site in sites-enabled to remove."
fi

if [ -f /etc/nginx/sites-available/fusionpbx ]; then
    rm /etc/nginx/sites-available/fusionpbx
    if [ $? -eq 0 ]; then
        print_success "Removed old fusionpbx site from sites-available."
    else
        print_error "Error occurred while removing fusionpbx from sites-available."
        exit 1
    fi
else
    print_success "No existing fusionpbx site in sites-available to remove."
fi

cp install/nginx_site_config.conf /etc/nginx/sites-available/fspbx.conf
if [ $? -eq 0 ]; then
    print_success "Copied new Nginx site config to sites-available."
else
    print_error "Error occurred while copying new Nginx site config."
    exit 1
fi

# Check if symbolic link already exists and remove it if necessary
if [ -L /etc/nginx/sites-enabled/fspbx.conf ]; then
    rm /etc/nginx/sites-enabled/fspbx.conf
    if [ $? -eq 0 ]; then
        print_success "Existing symbolic link for fspbx.conf removed."
    else
        print_error "Error occurred while removing existing symbolic link for fspbx.conf."
        exit 1
    fi
fi

# Create symbolic link for fspbx.conf
ln -s /etc/nginx/sites-available/fspbx.conf /etc/nginx/sites-enabled/fspbx.conf
if [ $? -eq 0 ]; then
    print_success "Linked new Nginx site config to sites-enabled."
else
    print_error "Error occurred while linking new Nginx site config."
    exit 1
fi

# Create directories for SSL certificates if they don't exist
sudo mkdir -p /etc/nginx/ssl/private
if [ $? -eq 0 ]; then
    print_success "SSL directory structure created successfully."
else
    print_error "Error occurred while creating SSL directory structure."
    exit 1
fi

# Generate self-signed SSL certificate and private key
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/nginx/ssl/private/privkey.pem -out /etc/nginx/ssl/fullchain.pem -subj "/C=US/ST=State/L=City/O=Organization/OU=Department/CN=example.com"
if [ $? -eq 0 ]; then
    print_success "Self-signed SSL certificate and key created successfully."
else
    print_error "Error occurred while generating self-signed SSL certificate and key."
    exit 1
fi


service nginx reload && service nginx restart
if [ $? -eq 0 ]; then
    print_success "Nginx reloaded and restarted successfully."
else
    print_error "Error occurred during Nginx reload and restart."
    exit 1
fi

# Ensure the FusionPBX cache directory exists
print_success "Setting up FusionPBX cache directory..."
mkdir -p /var/cache/fusionpbx
chown -R www-data:www-data /var/cache/fusionpbx

print_success "FusionPBX cache directory setup completed."

print_success "Installing FS PBX Apps..."
bash /var/www/fspbx/install/install_fusionpbx_apps.sh
if [ $? -eq 0 ]; then
    print_success "FS PBX Apps installed successfully."
else
    print_error "Error occurred while installing FS PBX Apps."
    exit 1
fi


# Copy .env.example to .env
cp .env.example .env
if [ $? -eq 0 ]; then
    print_success ".env file created successfully from .env.example."
else
    print_error "Error occurred while copying .env.example to .env."
    exit 1
fi

# Install Composer dependencies without interaction
composer install --no-dev --prefer-dist --optimize-autoloader --no-progress --no-interaction
if [ $? -eq 0 ]; then
    print_success "Composer dependencies installed successfully."
else
    print_error "Error occurred while installing Composer dependencies."
    exit 1
fi

# Regenerate Composer autoload files without interaction
# composer dump-autoload --no-interaction
# if [ $? -eq 0 ]; then
#     print_success "Composer autoload files regenerated successfully."
# else
#     print_error "Error occurred while regenerating Composer autoload files."
#     exit 1
# fi


# Generate application key
php artisan key:generate
if [ $? -eq 0 ]; then
    print_success "Application key generated successfully."
else
    print_error "Error occurred while generating application key."
    exit 1
fi

# Replace the main index file
cp install/index.php public/index.php
if [ $? -eq 0 ]; then
    print_success "Main index file replaced successfully."
else
    print_error "Error occurred while replacing the main index file."
    exit 1
fi

# Copy check_auth.php to public/resources
cp install/check_auth.php public/resources/check_auth.php
if [ $? -eq 0 ]; then
    print_success "check_auth.php copied to public/resources successfully."
else
    print_error "Error occurred while copying check_auth.php to public/resources."
    exit 1
fi

print_success "Installing FreeSWITCH..."
bash /var/www/fspbx/install/install_freeswitch.sh
if [ $? -eq 0 ]; then
    print_success "FreeSWITCH installed successfully."
else
    print_error "Error occurred while installing FreeSWITCH."
    exit 1
fi


print_success "Installing FreeSWITCH Sounds..."
bash /var/www/fspbx/install/install_freeswitch_sounds.sh
if [ $? -eq 0 ]; then
    print_success "FreeSWITCH sounds installed successfully."
else
    print_error "Error occurred while installing FreeSWITCH sounds."
    exit 1
fi

print_success "Installing Fail2Ban and securing Nginx..."
bash /var/www/fspbx/install/install_fail2ban.sh
if [ $? -eq 0 ]; then
    print_success "Fail2Ban installed and configured successfully."
else
    print_error "Error occurred while installing Fail2Ban."
    exit 1
fi

# Ensure the /etc/fusionpbx directory exists
if [ ! -d "/etc/fusionpbx" ]; then
    sudo mkdir -p /etc/fusionpbx
    print_success "Created /etc/fusionpbx directory."
fi

# Copy the fusionpbx_config.conf file to /etc/fusionpbx
sudo cp /var/www/fspbx/install/fusionpbx_config.conf /etc/fusionpbx/config.conf
if [ $? -eq 0 ]; then
    print_success "Copied fusionpbx_config.conf to /etc/fusionpbx successfully."
else
    print_error "Error occurred while copying fusionpbx_config.conf."
    exit 1
fi

print_success "Installing PostgreSQL..."
bash /var/www/fspbx/install/install_postgresql.sh
if [ $? -eq 0 ]; then
    print_success "PostgreSQL installed successfully."
else
    print_error "Error occurred while installing PostgreSQL."
    exit 1
fi

# Update document root in config.conf
sudo sed -i 's|document.root = /var/www/fusionpbx|document.root = /var/www/fspbx/public|' /etc/fusionpbx/config.conf
if [ $? -eq 0 ]; then
    print_success "Updated document root in config.conf successfully."
else
    print_error "Error occurred while updating document root in config.conf."
    exit 1
fi

# Extract database credentials from config.conf
DB_NAME=$(grep '^database.0.name' /etc/fusionpbx/config.conf | cut -d ' ' -f 3)
DB_USERNAME=$(grep '^database.0.username' /etc/fusionpbx/config.conf | cut -d ' ' -f 3)
DB_PASSWORD=$(grep '^database.0.password' /etc/fusionpbx/config.conf | cut -d ' ' -f 3)

# Update .env file with database credentials
sudo sed -i "s|^DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" /var/www/fspbx/.env
if [ $? -eq 0 ]; then
    print_success "Updated DB_DATABASE in .env file successfully."
else
    print_error "Error occurred while updating DB_DATABASE in .env file."
    exit 1
fi

sudo sed -i "s|^DB_USERNAME=.*|DB_USERNAME=$DB_USERNAME|" /var/www/fspbx/.env
if [ $? -eq 0 ]; then
    print_success "Updated DB_USERNAME in .env file successfully."
else
    print_error "Error occurred while updating DB_USERNAME in .env file."
    exit 1
fi

sudo sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|" /var/www/fspbx/.env
if [ $? -eq 0 ]; then
    print_success "Updated DB_PASSWORD in .env file successfully."
else
    print_error "Error occurred while updating DB_PASSWORD in .env file."
    exit 1
fi


# Fetch the external IP address of the server
EXTERNAL_IP=$(curl -s http://checkip.amazonaws.com)
if [ $? -eq 0 ]; then
    print_success "External IP address fetched successfully: $EXTERNAL_IP."
else
    print_error "Error occurred while fetching the external IP address."
    exit 1
fi

# Update APP_URL in .env file with external IP
sudo sed -i "s|^APP_URL=.*|APP_URL=https://$EXTERNAL_IP|" /var/www/fspbx/.env
if [ $? -eq 0 ]; then
    print_success "Updated APP_URL in .env file successfully."
else
    print_error "Error occurred while updating APP_URL in .env file."
    exit 1
fi

# Update SESSION_DOMAIN in .env file with external IP
sudo sed -i "s|^SESSION_DOMAIN=.*|SESSION_DOMAIN=$EXTERNAL_IP|" /var/www/fspbx/.env
if [ $? -eq 0 ]; then
    print_success "Updated SESSION_DOMAIN in .env file successfully."
else
    print_error "Error occurred while updating SESSION_DOMAIN in .env file."
    exit 1
fi

# Update SANCTUM_STATEFUL_DOMAINS in .env file with external IP
sudo sed -i "s|^SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=$EXTERNAL_IP|" /var/www/fspbx/.env
if [ $? -eq 0 ]; then
    print_success "Updated SANCTUM_STATEFUL_DOMAINS in .env file successfully."
else
    print_error "Error occurred while updating SANCTUM_STATEFUL_DOMAINS in .env file."
    exit 1
fi


# Create a symbolic link from "public/storage" to "storage/app/public"
php artisan storage:link
if [ $? -eq 0 ]; then
    print_success "Storage link created successfully."
else
    print_error "Error occurred while creating storage link."
    exit 1
fi

# Copy assets to storage/app/public
sudo cp /var/www/fspbx/install/assets/* /var/www/fspbx/storage/app/public/
if [ $? -eq 0 ]; then
    print_success "Assets copied to storage/app/public successfully."
else
    print_error "Error occurred while copying assets to storage/app/public."
    exit 1
fi

# Download and replace the groups.php file
# sudo curl -o /var/www/fspbx/public/resources/classes/groups.php https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/resources/classes/groups.php
# if [ $? -eq 0 ]; then
#     print_success "groups.php file downloaded and replaced successfully."
# else
#     print_error "Error occurred while downloading and replacing groups.php file."
#     exit 1
# fi

# Download and replace the xml_cdr.php file
# sudo curl -o /var/www/fspbx/public/app/xml_cdr/resources/classes/xml_cdr.php https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/xml_cdr/resources/classes/xml_cdr.php
# if [ $? -eq 0 ]; then
#     print_success "xml_cdr.php file downloaded and replaced successfully."
# else
#     print_error "Error occurred while downloading and replacing xml_cdr.php file."
#     exit 1
# fi

# Download and replace the functions.php file
# sudo curl -o /var/www/fspbx/public/resources/functions.php https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/resources/functions.php
# if [ $? -eq 0 ]; then
#     print_success "functions.php file downloaded and replaced successfully."
# else
#     print_error "Error occurred while downloading and replacing functions.php file."
#     exit 1
# fi

# Download and replace the permissions.php file
# sudo curl -o /var/www/fspbx/public/resources/classes/permissions.php https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/resources/classes/permissions.php
# if [ $? -eq 0 ]; then
#     print_success "permissions.php file downloaded and replaced successfully."
# else
#     print_error "Error occurred while downloading and replacing permissions.php file."
#     exit 1
# fi

# Download and replace the dialplan.php file
# sudo curl -o /var/www/fspbx/public/app/dialplans/resources/classes/dialplan.php https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/dialplans/resources/classes/dialplan.php
# if [ $? -eq 0 ]; then
#     print_success "dialplan.php file downloaded and replaced successfully."
# else
#     print_error "Error occurred while downloading and replacing dialplan.php file."
#     exit 1
# fi

# Download and replace the fax_send.php file
# sudo curl -o /var/www/fspbx/public/app/fax_queue/resources/job/fax_send.php https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/fax_queue/resources/job/fax_send.php
# if [ $? -eq 0 ]; then
#     print_success "fax_send.php file downloaded and replaced successfully."
# else
#     print_error "Error occurred while downloading and replacing fax_send.php file."
#     exit 1
# fi

# Download and replace the ivr.conf.lua file
# sudo curl -o /var/www/fspbx/public/app/switch/resources/scripts/app/xml_handler/resources/scripts/configuration/ivr.conf.lua https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/switch/resources/scripts/app/xml_handler/resources/scripts/configuration/ivr.conf.lua
# if [ $? -eq 0 ]; then
#     print_success "ivr.conf.lua file downloaded and replaced successfully."
# else
#     print_error "Error occurred while downloading and replacing ivr.conf.lua file."
#     exit 1
# fi

# Download and replace the ivr.conf.lua file
# sudo curl -o /usr/share/freeswitch/scripts/app/xml_handler/resources/scripts/configuration/ivr.conf.lua https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/switch/resources/scripts/app/xml_handler/resources/scripts/configuration/ivr.conf.lua
# if [ $? -eq 0 ]; then
#     print_success "ivr.conf.lua file downloaded and replaced successfully."
# else
#     print_error "Error occurred while downloading and replacing ivr.conf.lua file."
#     exit 1
# fi

# Download and replace the hangup_tx.lua file
# sudo curl -o /var/www/fspbx/public/app/switch/resources/scripts/app/fax/resources/scripts/hangup_tx.lua https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/switch/resources/scripts/app/fax/resources/scripts/hangup_tx.lua
# if [ $? -eq 0 ]; then
#     print_success "hangup_tx.lua file downloaded and replaced successfully."
# else
#     print_error "Error occurred while downloading and replacing hangup_tx.lua file."
#     exit 1
# fi

# Download and replace the hangup_tx.lua file
# sudo curl -o /usr/share/freeswitch/scripts/app/fax/resources/scripts/hangup_tx.lua https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/switch/resources/scripts/app/fax/resources/scripts/hangup_tx.lua
# if [ $? -eq 0 ]; then
#     print_success "hangup_tx.lua file downloaded and replaced successfully."
# else
#     print_error "Error occurred while downloading and replacing hangup_tx.lua file."
#     exit 1
# fi


# Change ownership of the entire fspbx directory to www-data
sudo chown -R www-data:www-data /var/www/fspbx
if [ $? -eq 0 ]; then
    print_success "Ownership of /var/www/fspbx and its contents changed to www-data successfully."
else
    print_error "Error occurred while changing ownership of /var/www/fspbx."
    exit 1
fi

# Set directory permissions to 755
sudo find /var/www/fspbx -type d -exec chmod 755 {} \;
if [ $? -eq 0 ]; then
    print_success "All directories set to 755 permissions successfully."
else
    print_error "Error occurred while setting directory permissions."
    exit 1
fi

# Set file permissions to 644
sudo find /var/www/fspbx -type f -exec chmod 644 {} \;
if [ $? -eq 0 ]; then
    print_success "All files set to 644 permissions successfully."
else
    print_error "Error occurred while setting file permissions."
    exit 1
fi

# Change group ownership to www-data for storage and bootstrap/cache
sudo chgrp -R www-data /var/www/fspbx/storage /var/www/fspbx/bootstrap/cache
if [ $? -eq 0 ]; then
    print_success "Group ownership of storage and bootstrap/cache changed to www-data successfully."
else
    print_error "Error occurred while changing group ownership to www-data."
    exit 1
fi

# Set permissions to ug+rwx for storage and bootstrap/cache
sudo chmod -R ug+rwx /var/www/fspbx/storage /var/www/fspbx/bootstrap/cache
if [ $? -eq 0 ]; then
    print_success "Permissions set to ug+rwx for storage and bootstrap/cache successfully."
else
    print_error "Error occurred while setting permissions for storage and bootstrap/cache."
    exit 1
fi

# Set /var/www/fspbx as a safe directory for Git
sudo git config --global --add safe.directory /var/www/fspbx
if [ $? -eq 0 ]; then
    print_success "/var/www/fspbx added to Git's safe.directory list."
else
    print_error "Error occurred while adding /var/www/fspbx to Git's safe.directory list."
    exit 1
fi

# Update settings for email_queue service
# Copy email_queue service file
sudo cp /var/www/fspbx/public/app/email_queue/resources/service/debian.service /etc/systemd/system/email_queue.service
if [ $? -eq 0 ]; then
    print_success "email_queue service file copied successfully."
else
    print_error "Error occurred while copying fax_queue service file."
    exit 1
fi

# Update settings for email_queue service
sudo sed -i "s|WorkingDirectory=/var/www/fusionpbx|WorkingDirectory=/var/www/fspbx/public|" /etc/systemd/system/email_queue.service
if [ $? -eq 0 ]; then
    print_success "Updated WorkingDirectory for email_queue service successfully."
else
    print_error "Error occurred while updating WorkingDirectory for email_queue service."
    exit 1
fi

sudo sed -i "s|ExecStart=/usr/bin/php /var/www/fusionpbx/app/email_queue/resources/service/email_queue.php|ExecStart=/usr/bin/php /var/www/fspbx/public/app/email_queue/resources/service/email_queue.php|" /etc/systemd/system/email_queue.service
if [ $? -eq 0 ]; then
    print_success "Updated ExecStart for email_queue service successfully."
else
    print_error "Error occurred while updating ExecStart for email_queue service."
    exit 1
fi

# Update settings for fax_queue service
# Copy fax_queue service file
sudo cp /var/www/fspbx/public/app/fax_queue/resources/service/debian.service /etc/systemd/system/fax_queue.service
if [ $? -eq 0 ]; then
    print_success "fax_queue service file copied successfully."
else
    print_error "Error occurred while copying fax_queue service file."
    exit 1
fi

# Enable fax_queue service
sudo systemctl enable fax_queue
if [ $? -eq 0 ]; then
    print_success "fax_queue service enabled successfully."
else
    print_error "Error occurred while enabling fax_queue service."
    exit 1
fi

# Enable email_queue service
sudo systemctl enable email_queue
if [ $? -eq 0 ]; then
    print_success "email_queue service enabled successfully."
else
    print_error "Error occurred while enabling email_queue service."
    exit 1
fi

# Enable event_guard service
sudo systemctl enable event_guard
if [ $? -eq 0 ]; then
    print_success "event_guard service enabled successfully."
else
    print_error "Error occurred while enabling event_guard service."
    exit 1
fi

sudo sed -i "s|WorkingDirectory=/var/www/fusionpbx|WorkingDirectory=/var/www/fspbx/public|" /etc/systemd/system/fax_queue.service
if [ $? -eq 0 ]; then
    print_success "Updated WorkingDirectory for fax_queue service successfully."
else
    print_error "Error occurred while updating WorkingDirectory for fax_queue service."
    exit 1
fi

sudo sed -i "s|ExecStart=/usr/bin/php /var/www/fusionpbx/app/fax_queue/resources/service/fax_queue.php|ExecStart=/usr/bin/php /var/www/fspbx/public/app/fax_queue/resources/service/fax_queue.php|" /etc/systemd/system/fax_queue.service
if [ $? -eq 0 ]; then
    print_success "Updated ExecStart for fax_queue service successfully."
else
    print_error "Error occurred while updating ExecStart for fax_queue service."
    exit 1
fi

# Update settings for event_guard service
# Copy event_guard service file
sudo cp /var/www/fspbx/public/app/event_guard/resources/service/debian.service /etc/systemd/system/event_guard.service
if [ $? -eq 0 ]; then
    print_success "event_guard service file copied successfully."
else
    print_error "Error occurred while copying event_guard service file."
    exit 1
fi

# Update settings for event_guard service
sudo sed -i "s|WorkingDirectory=/var/www/fusionpbx|WorkingDirectory=/var/www/fspbx/public|" /etc/systemd/system/event_guard.service
if [ $? -eq 0 ]; then
    print_success "Updated WorkingDirectory for event_guard service successfully."
else
    print_error "Error occurred while updating WorkingDirectory for event_guard service."
    exit 1
fi

sudo sed -i "s|ExecStart=/usr/bin/php /var/www/fusionpbx/app/event_guard/resources/service/event_guard.php|ExecStart=/usr/bin/php /var/www/fspbx/public/app/event_guard/resources/service/event_guard.php|" /etc/systemd/system/event_guard.service
if [ $? -eq 0 ]; then
    print_success "Updated ExecStart for event_guard service successfully."
else
    print_error "Error occurred while updating ExecStart for event_guard service."
    exit 1
fi

# Reload systemd daemon to apply changes
sudo systemctl daemon-reload
if [ $? -eq 0 ]; then
    print_success "systemd daemon reloaded successfully."
else
    print_error "Error occurred while reloading systemd daemon."
    exit 1
fi


# Restart email_queue service
sudo service email_queue stop
if [ $? -eq 0 ]; then
    print_success "email_queue service stopped successfully."
else
    print_error "Error occurred while stopping email_queue service."
    exit 1
fi

sudo service email_queue start
if [ $? -eq 0 ]; then
    print_success "email_queue service started successfully."
else
    print_error "Error occurred while starting email_queue service."
    exit 1
fi

# Restart fax_queue service
sudo service fax_queue stop
if [ $? -eq 0 ]; then
    print_success "fax_queue service stopped successfully."
else
    print_error "Error occurred while stopping fax_queue service."
    exit 1
fi

sudo service fax_queue start
if [ $? -eq 0 ]; then
    print_success "fax_queue service started successfully."
else
    print_error "Error occurred while starting fax_queue service."
    exit 1
fi

# Restart event_guard service
sudo service event_guard stop
if [ $? -eq 0 ]; then
    print_success "event_guard service stopped successfully."
else
    print_error "Error occurred while stopping event_guard service."
    exit 1
fi

sudo service event_guard start
if [ $? -eq 0 ]; then
    print_success "event_guard service started successfully."
else
    print_error "Error occurred while starting event_guard service."
    exit 1
fi

# Install Supervisor
# sudo apt-get -y install supervisor
# if [ $? -eq 0 ]; then
#     print_success "Supervisor installed successfully."
# else
#     print_error "Error occurred while installing Supervisor."
#     exit 1
# fi

# Install Redis Server
# sudo apt-get -y install redis-server
# if [ $? -eq 0 ]; then
#     print_success "Redis Server installed successfully."
# else
#     print_error "Error occurred while installing Redis Server."
#     exit 1
# fi

# Copy Redis configuration
sudo cp install/redis.conf /etc/redis/redis.conf
if [ $? -eq 0 ]; then
    print_success "Redis configuration file copied successfully."
else
    print_error "Error occurred while copying Redis configuration file."
    exit 1
fi

# Restart Redis Server
sudo service redis-server restart
if [ $? -eq 0 ]; then
    sleep 6
    print_success "Redis Server restarted successfully."
else
    print_error "Error occurred while restarting Redis Server."
    exit 1
fi

# Copy Horizon configuration to Supervisor
sudo cp install/horizon.conf /etc/supervisor/conf.d/
if [ $? -eq 0 ]; then
    print_success "Horizon configuration file copied to Supervisor successfully."
else
    print_error "Error occurred while copying Horizon configuration file to Supervisor."
    exit 1
fi

# Publish Horizon's assets
php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider"
if [ $? -eq 0 ]; then
    print_success "Laravel Horizon assets published successfully."
else
    print_error "Error occurred while publishing Laravel Horizon assets."
    exit 1
fi

# Reload Supervisor to read new configuration
sudo supervisorctl reread
if [ $? -eq 0 ]; then
    sleep 6
    print_success "Supervisor reread configuration successfully."
else
    print_error "Error occurred while rereading Supervisor configuration."
    exit 1
fi

# Update Supervisor with new configuration
sudo supervisorctl update
if [ $? -eq 0 ]; then
    sleep 6
    print_success "Supervisor updated with new configuration successfully."
else
    print_error "Error occurred while updating Supervisor with new configuration."
    exit 1
fi

# Restart Supervisor
sudo systemctl restart supervisor
if [ $? -eq 0 ]; then
    sleep 6
    print_success "Supervisor restarted successfully."
else
    print_error "Error occurred while restarting Supervisor."
    exit 1
fi

# Restart Horizon processes under Supervisor
sudo supervisorctl restart horizon:*
if [ $? -eq 0 ]; then
    sleep 6
    print_success "Horizon processes restarted successfully."
else
    print_error "Error occurred while restarting Horizon processes."
    exit 1
fi

print_success "Seeding the database and configuring FS PBX..."
# Navigate to Laravel project directory
cd /var/www/fspbx
# Run Laravel's initial seed command
php artisan fspbx:initial-seed

# Check if the command was successful
if [ $? -eq 0 ]; then
    print_success "All installation tasks completed successfully!"
else
    print_error "Error occurred during database seeding and FS PBX configuration."
    exit 1
fi



# # Terminal graphic for FS PBX
# echo ""
# echo " ███████████  █████████     ███████████  ███████████  █████ █████"
# echo "░░███░░░░░░█ ███░░░░░███   ░░███░░░░░███░░███░░░░░███░░███ ░░███ "
# echo " ░███   █ ░ ░███    ░░░     ░███    ░███ ░███    ░███ ░░███ ███  "
# echo " ░███████   ░░█████████     ░██████████  ░██████████   ░░█████   "
# echo " ░███░░░█    ░░░░░░░░███    ░███░░░░░░   ░███░░░░░███   ███░███  "
# echo " ░███  ░     ███    ░███    ░███         ░███    ░███  ███ ░░███ "
# echo " █████      ░░█████████     █████        ███████████  █████ █████"
# echo "░░░░░        ░░░░░░░░░     ░░░░░        ░░░░░░░░░░░  ░░░░░ ░░░░░ "
# echo ""
# echo "Welcome to FS PBX!"
# echo ""

# # Congratulations message
# echo "=============================================================="
# echo "Congratulations! FS PBX has been installed successfully."
# echo "You can now access your PBX at https://your_server_ip or https://your_domain."
# echo "=============================================================="




