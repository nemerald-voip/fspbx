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

print_success "Checking and Installing IPTables..."

# Detect OS version dynamically
OS_VERSION=$(lsb_release -cs)

# Ensure iptables is installed
if ! command -v iptables &>/dev/null; then
    print_success "iptables not found, installing..."
    apt-get update && apt-get install -y iptables
fi

# Ensure iptables-legacy is set as default on Debian-based systems
if [[ "$OS_VERSION" == "buster" || "$OS_VERSION" == "bullseye" || "$OS_VERSION" == "bookworm" ]]; then
    print_success "Setting iptables-legacy as the default version..."
    apt-get install -y iptables-legacy
    update-alternatives --set iptables /usr/sbin/iptables-legacy
    update-alternatives --set ip6tables /usr/sbin/ip6tables-legacy
fi

# Confirm iptables is now available
if ! command -v iptables &>/dev/null; then
    print_error "iptables is still missing! Exiting."
    exit 1
fi

print_success "iptables is installed and configured correctly."


# Install iptables and set alternatives for older versions
if [[ "$OS_VERSION" == "buster" || "$OS_VERSION" == "bullseye" || "$OS_VERSION" == "bookworm" ]]; then
    apt-get install -y iptables
    update-alternatives --set iptables /usr/sbin/iptables-legacy
    update-alternatives --set ip6tables /usr/sbin/ip6tables-legacy
fi

# Remove UFW if installed
if command -v ufw &>/dev/null; then
    print_success "Removing UFW..."
    ufw reset
    ufw disable
    apt-get remove -y ufw
else
    print_success "UFW is not installed, skipping removal."
fi

# Flush existing rules
print_success "Flushing existing IPTables rules..."
iptables -P INPUT ACCEPT
iptables -P FORWARD ACCEPT
iptables -P OUTPUT ACCEPT
iptables -F
ip6tables -P INPUT ACCEPT
ip6tables -P FORWARD ACCEPT
ip6tables -P OUTPUT ACCEPT
ip6tables -F

# Allow loopback traffic
iptables -A INPUT -i lo -j ACCEPT
ip6tables -A INPUT -i lo -j ACCEPT

# Allow established and related connections
iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
ip6tables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT

# Block known SIP scanners and attack patterns
for agent in "friendly-scanner" "sipcli/" "VaxSIPUserAgent/" "pplsip" "system " "exec." "multipart/mixed;boundary"; do
    iptables -A INPUT -j DROP -p udp --dport 5060:5091 -m string --string "$agent" --algo bm --icase
    iptables -A INPUT -j DROP -p tcp --dport 5060:5091 -m string --string "$agent" --algo bm --icase
    iptables -A INPUT -j DROP -p udp --dport 35000 -m string --string "$agent" --algo bm --icase
    iptables -A INPUT -j DROP -p tcp --dport 35000 -m string --string "$agent" --algo bm --icase
    iptables -A INPUT -j DROP -p udp --dport 36000:36011 -m string --string "$agent" --algo bm --icase
    iptables -A INPUT -j DROP -p tcp --dport 36000:36011 -m string --string "$agent" --algo bm --icase
done

# Allow essential services
iptables -A INPUT -p tcp --dport 22 -j ACCEPT      # SSH
iptables -A INPUT -p tcp --dport 80 -j ACCEPT      # HTTP
iptables -A INPUT -p tcp --dport 443 -j ACCEPT     # HTTPS
iptables -A INPUT -p tcp --dport 7443 -j ACCEPT    # Web GUI
iptables -A INPUT -p tcp --dport 5060:5091 -j ACCEPT  # SIP TCP
iptables -A INPUT -p udp --dport 5060:5091 -j ACCEPT  # SIP UDP
iptables -A INPUT -p tcp --dport 35000 -j ACCEPT      # SIP TCP
iptables -A INPUT -p udp --dport 35000 -j ACCEPT      # SIP UDP
iptables -A INPUT -p tcp --dport 36000:36011 -j ACCEPT  # SIP TCP
iptables -A INPUT -p udp --dport 36000:36011 -j ACCEPT  # SIP UDP
iptables -A INPUT -p udp --dport 16384:32768 -j ACCEPT # RTP
iptables -A INPUT -p icmp --icmp-type echo-request -j ACCEPT  # Ping
iptables -A INPUT -p udp --dport 1194 -j ACCEPT  # OpenVPN

# Mark VoIP traffic for QoS (DSCP tagging)
iptables -t mangle -A OUTPUT -p udp -m udp --sport 16384:32768 -j DSCP --set-dscp 46
iptables -t mangle -A OUTPUT -p udp -m udp --sport 5060:5091 -j DSCP --set-dscp 26
iptables -t mangle -A OUTPUT -p tcp -m tcp --sport 5060:5091 -j DSCP --set-dscp 26
iptables -t mangle -A OUTPUT -p udp -m udp --sport 35000 -j DSCP --set-dscp 26
iptables -t mangle -A OUTPUT -p udp -m udp --sport 36000:36011 -j DSCP --set-dscp 26
iptables -t mangle -A OUTPUT -p tcp -m tcp --sport 36000:36011 -j DSCP --set-dscp 26

# Set default policies (block incoming traffic by default)
iptables -P INPUT DROP
iptables -P FORWARD DROP
iptables -P OUTPUT ACCEPT
ip6tables -P INPUT DROP
ip6tables -P FORWARD DROP
ip6tables -P OUTPUT ACCEPT

# Ensure iptables-persistent is installed and save rules
print_success "Preconfiguring iptables-persistent for automatic rule saving..."
echo iptables-persistent iptables-persistent/autosave_v4 boolean true | debconf-set-selections
echo iptables-persistent iptables-persistent/autosave_v6 boolean true | debconf-set-selections

print_success "Installing iptables-persistent..."
apt-get install -y iptables-persistent
if [ $? -eq 0 ]; then
    print_success "iptables-persistent installed successfully."
else
    print_error "Error occurred while installing iptables-persistent."
    exit 1
fi

print_success "Saving firewall rules..."
iptables-save > /etc/iptables/rules.v4
ip6tables-save > /etc/iptables/rules.v6

print_success "IPTables firewall rules configured successfully!"
