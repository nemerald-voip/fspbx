#!/bin/bash
if [ -z "${BASH_VERSION:-}" ]; then
    exec /bin/bash "$0" "$@"
fi
set -Eeuo pipefail
source "$(dirname -- "$(readlink -f "$0")")/freeswitch-common.sh"

FRESH_INSTALL=false
if [[ "${1:-}" == --fresh-install ]]; then FRESH_INSTALL=true; shift; fi
[[ $# -le 1 ]] || fs_die 'Usage: install_freeswitch_packages.sh [--fresh-install] PACKAGE_DIR'
fs_preflight
PACKAGE_DIR=$(realpath "${1:-${FREESWITCH_DEB_DIR:-$FS_APP_DIR/storage/app/freeswitch-packages/$OS_CODENAME}}")
[[ -f "$PACKAGE_DIR/SHA256SUMS" && -f "$PACKAGE_DIR/runtime-packages.txt" ]] || fs_die 'Use a complete bundle from build_freeswitch_debian_packages.sh.'
[[ $(cat "$PACKAGE_DIR/debian-codename") == "$OS_CODENAME" ]] || fs_die 'The package bundle targets a different Debian release.'
[[ $(cat "$PACKAGE_DIR/architecture") == "$(dpkg --print-architecture)" ]] || fs_die 'The package bundle targets a different architecture.'
(cd "$PACKAGE_DIR" && sha256sum --check --strict SHA256SUMS)

STAGE="$FS_BACKUP_DIR/candidate"
mkdir "$STAGE"
RUNTIME_DEBS=()
while IFS= read -r filename; do
    [[ "$filename" != */* && "$filename" == *.deb && -f "$PACKAGE_DIR/$filename" ]] || fs_die 'Invalid runtime package list.'
    RUNTIME_DEBS+=("$PACKAGE_DIR/$filename")
    dpkg-deb -x "$PACKAGE_DIR/$filename" "$STAGE"
done < "$PACKAGE_DIR/runtime-packages.txt"
[[ ${#RUNTIME_DEBS[@]} -gt 0 && -x "$STAGE/usr/bin/freeswitch" ]] || fs_die 'The bundle has no FreeSWITCH binary.'
CONFIG_SOURCE="$FS_CONF_DIR"
if [[ "$FRESH_INSTALL" == true ]]; then CONFIG_SOURCE="$FS_APP_DIR/resources"; fi
python3 "$FS_CONFIG_TOOL" check-modules "$CONFIG_SOURCE" "$STAGE$FS_MOD_DIR"
# Locally compiled libraries take precedence over Debian libraries. Keep source
# installations on the source path unless those libraries have been migrated.
if /sbin/ldconfig -p | grep 'libsofia-sip-ua.so' | grep -q '/usr/local/'; then
    fs_die 'Source-built Sofia-SIP is installed in /usr/local. Use install_freeswitch.sh on this server.'
fi

fs_protect_services
apt-get update
LC_ALL=C apt-get -s -o Dpkg::Options::=--force-confold install "${RUNTIME_DEBS[@]}" > "$FS_BACKUP_DIR/apt-plan.txt"
python3 "$FS_CONFIG_TOOL" check-package-plan "$FS_BACKUP_DIR/apt-plan.txt"
fs_begin_install
if grep -q '^Remv ' "$FS_BACKUP_DIR/apt-plan.txt"; then
    # Permit removal of retired module packages only; validated above.
    DEBIAN_FRONTEND=noninteractive apt-get -o Dpkg::Options::=--force-confold install -y "${RUNTIME_DEBS[@]}"
else
    fs_apt_install "${RUNTIME_DEBS[@]}"
fi
/sbin/ldconfig
LD_BIND_NOW=1 /usr/bin/freeswitch -version > "$FS_BACKUP_DIR/candidate-version.txt"
if [[ "$FRESH_INSTALL" == true ]]; then
    for directory in /var/lib/freeswitch /var/log/freeswitch /var/run/freeswitch /var/cache/fusionpbx; do
        install -d -o www-data -g www-data "$directory"
    done
    if [[ -d /proc/vz || -e /proc/user_beancounters ]]; then
        sed -i 's/^CPUSchedulingPolicy=rr/;CPUSchedulingPolicy=rr/' "$FS_SERVICE_PATH"
    fi
    systemctl enable freeswitch
fi
fs_finish
