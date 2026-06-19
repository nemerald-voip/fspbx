#!/bin/bash

set -euo pipefail

print_success() {
    echo -e "\e[32m$1 \e[0m"
}

print_error() {
    echo -e "\e[31m$1 \e[0m"
}

detect_os_codename() {
    lsb_release -sc 2>/dev/null && return

    if [[ -r /etc/os-release ]]; then
        . /etc/os-release
        printf '%s\n' "${VERSION_CODENAME:-}"
    fi
}

OS_CODENAME=${OS_CODENAME:-$(detect_os_codename)}
PACKAGE_DIR=${1:-${FREESWITCH_DEB_DIR:-"/var/www/fspbx/storage/app/freeswitch-packages/${OS_CODENAME}"}}

if [[ "$(id -u)" -ne 0 ]]; then
    print_error "Run this package installer as root."
    exit 1
fi

if [[ ! -d "$PACKAGE_DIR" ]]; then
    print_error "FreeSWITCH package directory not found: $PACKAGE_DIR"
    exit 1
fi

mapfile -t RUNTIME_DEBS < <(
    find "$PACKAGE_DIR" -maxdepth 1 -type f -name '*.deb' \
        ! -name '*-dbg_*' \
        ! -name '*-dev_*' \
        ! -name '*-doc_*' \
        | sort
)

if [[ "${#RUNTIME_DEBS[@]}" -eq 0 ]]; then
    print_error "No runtime FreeSWITCH packages found in $PACKAGE_DIR"
    exit 1
fi

print_success "Installing FreeSWITCH packages from $PACKAGE_DIR..."
apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y "${RUNTIME_DEBS[@]}"

if [ -d "/etc/freeswitch.orig" ]; then
    print_success "Existing FreeSWITCH config backup found. Removing it..."
    rm -rf /etc/freeswitch.orig
fi

if [ -d "/etc/freeswitch" ]; then
    mv /etc/freeswitch /etc/freeswitch.orig
fi

mkdir -p /etc/freeswitch
cp -R /var/www/fspbx/public/app/switch/resources/conf/* /etc/freeswitch

chown -R www-data:www-data /etc/freeswitch
chown -R www-data:www-data /var/lib/freeswitch
chown -R www-data:www-data /usr/share/freeswitch
chown -R www-data:www-data /var/log/freeswitch
chown -R www-data:www-data /var/run/freeswitch
chown -R www-data:www-data /var/cache/fusionpbx

if [ -f "/lib/systemd/system/freeswitch.service" ]; then
    sed -i -e 's/Environment="USER=freeswitch"/Environment="USER=www-data"/' /lib/systemd/system/freeswitch.service
    sed -i -e 's/Environment="GROUP=freeswitch"/Environment="GROUP=www-data"/' /lib/systemd/system/freeswitch.service
    chmod 644 /lib/systemd/system/freeswitch.service
fi

if [ -d "/proc/vz" ] || [ -e "/proc/user_beancounters" ]; then
    print_success "Detected OpenVZ, disabling CPU scheduling for FreeSWITCH..."
    sed -i -e "s/CPUSchedulingPolicy=rr/;CPUSchedulingPolicy=rr/g" /lib/systemd/system/freeswitch.service
fi

systemctl daemon-reload
systemctl enable freeswitch

print_success "FreeSWITCH packages installed successfully."
