#!/bin/bash

# Function to print success messages
print_success() {
    echo -e "\e[32m$1 \e[0m"
}

# Function to print error messages
print_error() {
    echo -e "\e[31m$1 \e[0m"
    exit 1
}

print_success "Installing Fail2Ban and Rsyslog for Nginx security..."

# ------------------------------
# 📌 Install Rsyslog & Enable It
# ------------------------------
if ! command -v rsyslogd &> /dev/null; then
    apt-get update && apt-get install -y rsyslog || print_error "Failed to install Rsyslog."
    systemctl enable rsyslog
    systemctl restart rsyslog
    print_success "Rsyslog installed and running."
else
    print_success "Rsyslog is already installed."
fi

# ------------------------------
# 📌 Install Fail2Ban
# ------------------------------
if ! command -v fail2ban-client &> /dev/null; then
    apt-get install -y fail2ban || print_error "Failed to install Fail2Ban."
    print_success "Fail2Ban installed successfully."
else
    print_success "Fail2Ban is already installed."
fi

# Ensure necessary directories exist
mkdir -p /etc/fail2ban/filter.d

# ------------------------------
# 📌 Configure Jail Rules
# ------------------------------
print_success "Configuring Fail2Ban jails..."
cat > /etc/fail2ban/jail.local <<EOL
[DEFAULT]
# Debian 12 has no log files, just journalctl
backend = systemd

[nginx-404]
enabled  = true
port     = 80,443
protocol = tcp
filter   = nginx-404
logpath  = /var/log/nginx/access*.log
maxretry = 30
bantime  = 3600
findtime = 60
action   = iptables-allports[name=nginx-404, protocol=all]

[nginx-dos]
enabled  = true
port     = 80,443
protocol = tcp
filter   = nginx-dos
logpath  = /var/log/nginx/access*.log
maxretry = 300
bantime  = 86400
findtime = 60
action   = iptables-allports[name=nginx-dos, protocol=all]

[nginx-badbots]
enabled  = true
port     = 80,443
protocol = tcp
filter   = nginx-badbots
logpath  = /var/log/nginx/access*.log
maxretry = 5
bantime  = 86400
findtime = 300
action   = iptables-allports[name=nginx-badbots, protocol=all]
EOL
print_success "Fail2Ban jail rules configured."

# ------------------------------
# 📌 Create Filters for Nginx Logs
# ------------------------------
print_success "Creating Fail2Ban filters..."

# nginx-404 filter
cat > /etc/fail2ban/filter.d/nginx-404.conf <<EOL
[Definition]
failregex = ^<HOST> .* "(GET|POST) .* HTTP.*" 404 .*$
ignoreregex =
EOL
print_success "nginx-404 filter created."

# nginx-dos filter
cat > /etc/fail2ban/filter.d/nginx-dos.conf <<EOL
[Definition]
failregex = ^<HOST> .* "(GET|POST) .* HTTP.*"
ignoreregex =
EOL
print_success "nginx-dos filter created."

# nginx-badbots filter
cat > /etc/fail2ban/filter.d/nginx-badbots.conf <<EOL
[Definition]
failregex = ^<HOST> .* "(GET|POST) .* HTTP.*" .*(python-requests|scrapy|curl|wget|java|Go-http-client).*$
ignoreregex =
EOL
print_success "nginx-badbots filter created."

# ------------------------------
# 📌 Ensure Logging Works for Fail2Ban
# ------------------------------
print_success "Configuring Rsyslog to log Fail2Ban messages..."

cat > /etc/rsyslog.d/10-fail2ban.conf <<EOL
if \$programname == 'fail2ban' then /var/log/fail2ban.log
& stop
EOL

# Restart Rsyslog to apply changes
systemctl restart rsyslog
print_success "Rsyslog configured for Fail2Ban."

# ------------------------------
# 📌 Restart Fail2Ban
# ------------------------------
print_success "Starting Fail2Ban service..."
systemctl restart fail2ban

# Wait for Fail2Ban to fully start
print_success "Waiting for Fail2Ban to start..."
sleep 10  # Waits 5 seconds (increase to 10 if needed)

# Check if Fail2Ban socket exists
if [ -S /var/run/fail2ban/fail2ban.sock ]; then
    print_success "Fail2Ban is running correctly."
else
    print_error "Fail2Ban is not running correctly."
    exit 1
fi
print_success "Fail2Ban and Rsyslog installed and configured successfully!"
