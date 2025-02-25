#!/bin/bash

# Set error handling
set -e

# Define PostgreSQL version (default to 17)
DB_VERSION="${POSTGRESQL_VERSION:-17}"

# Function to print success messages
print_success() {
    echo -e "\e[32m$1 \e[0m"
}

# Function to print error messages
print_error() {
    echo -e "\e[31m$1 \e[0m"
}

print_success "Installing PostgreSQL $DB_VERSION..."

# Generate a random password for the database user
DB_PASSWORD=$(tr -dc A-Za-z0-9 </dev/urandom | head -c 20)

# Ensure keyrings directory exists
mkdir -p /etc/apt/keyrings

# Add PostgreSQL Official Repository
apt install -y gpg
echo "deb [signed-by=/etc/apt/keyrings/pgdg.gpg] http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor | sudo tee /etc/apt/keyrings/pgdg.gpg > /dev/null
sudo chmod 644 /etc/apt/keyrings/pgdg.gpg  # Ensure correct permissions


# Update package lists and upgrade system
apt-get update

# Install PostgreSQL and its client using the specified version
apt-get install -y sudo postgresql-"$DB_VERSION" postgresql-client-"$DB_VERSION"

if [ -d "/var/lib/postgresql/$DB_VERSION/main" ]; then
    print_success "PostgreSQL cluster already exists. Skipping initialization..."
else
    print_success "Creating new PostgreSQL cluster..."
    pg_createcluster "$DB_VERSION" main
fi


# Replace `scram-sha-256` with `md5` in `pg_hba.conf` to allow simple authentication
sed -i 's/scram-sha-256/md5/g' /etc/postgresql/"$DB_VERSION"/main/pg_hba.conf

# Restart PostgreSQL to apply changes
systemctl daemon-reload
systemctl restart postgresql

# Move to /tmp to prevent sudo + psql errors
cwd=$(pwd)
cd /tmp

# Verify PostgreSQL is running before proceeding
if ! sudo -u postgres psql -c "SELECT 1;" >/dev/null 2>&1; then
    print_error "PostgreSQL installation failed. Exiting..."
    exit 1
fi

print_success "PostgreSQL $DB_VERSION installed successfully."

# Create the FS PBX database and user
print_success "Configuring database and user..."
sudo -u postgres psql <<EOF
CREATE DATABASE fusionpbx;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE ROLE fusionpbx WITH SUPERUSER LOGIN PASSWORD '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON DATABASE fusionpbx TO fusionpbx;

-- Create the Freeswitch user if it doesn't exist
DO
\$do\$
BEGIN
   IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'freeswitch') THEN
      CREATE ROLE freeswitch WITH LOGIN PASSWORD '$DB_PASSWORD';
   END IF;
END
\$do\$;

ALTER USER freeswitch WITH PASSWORD '$DB_PASSWORD';

SELECT pg_reload_conf();
EOF

print_success "PostgreSQL database and users created successfully."

sed -i /etc/fusionpbx/config.conf -e s:"{database_password}:$DB_PASSWORD:"

print_success "Updated FusionPBX config with new password."
