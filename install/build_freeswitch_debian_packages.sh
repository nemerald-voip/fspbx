#!/bin/bash

set -euo pipefail

# Build this once on a Debian build host, then copy ARTIFACT_DIR to target
# servers. Target servers should run install_freeswitch_packages.sh instead.
# run sudo ./install/build_freeswitch_debian_packages.sh to create .deb packages for the current Debian release codename (bookworm or trixie).

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
FREESWITCH_VERSION=${FREESWITCH_VERSION:-"v1.11"}
SOFIA_SIP_VERSION=${SOFIA_SIP_VERSION:-"100d3577f5c5a6790ab68a5e3425ab1a091236c5"}
JOBS=${JOBS:-$(getconf _NPROCESSORS_ONLN)}
BUILD_ROOT=${BUILD_ROOT:-"/usr/src/fspbx-freeswitch-package-build"}
ARTIFACT_DIR=${ARTIFACT_DIR:-"/var/www/fspbx/storage/app/freeswitch-packages/${OS_CODENAME}"}

if [[ -z "$OS_CODENAME" ]]; then
    print_error "Unable to detect Debian codename. Set OS_CODENAME=bookworm or OS_CODENAME=trixie."
    exit 1
fi

if [[ "$OS_CODENAME" != "bookworm" && "$OS_CODENAME" != "trixie" ]]; then
    print_error "Unsupported Debian codename: $OS_CODENAME"
    exit 1
fi

if [[ "$(id -u)" -ne 0 ]]; then
    print_error "Run this package builder as root."
    exit 1
fi

print_success "Building FreeSWITCH packages for Debian $OS_CODENAME..."
mkdir -p "$BUILD_ROOT" "$ARTIFACT_DIR"

apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y \
    build-essential ca-certificates curl debhelper devscripts dh-autoreconf \
    dpkg-dev equivs git gnupg lsb-release pkg-config

print_success "Building Sofia-SIP package with TLS reload support..."
rm -rf "$BUILD_ROOT/sofia-sip"
git clone https://github.com/freeswitch/sofia-sip.git "$BUILD_ROOT/sofia-sip"
cd "$BUILD_ROOT/sofia-sip"
git checkout "$SOFIA_SIP_VERSION"

# Add an FS PBX package version to debian/changelog so apt can distinguish
# this custom TLS-reload build from distro/upstream Sofia-SIP packages.
dch -b -m -v "1.13.18~fspbx1-1~${OS_CODENAME}1" --force-distribution -D "$OS_CODENAME" \
    "FS PBX build with nua_reload_tls support."
dpkg-buildpackage -b -us -uc -j"$JOBS"
cp "$BUILD_ROOT"/libsofia-sip-ua*.deb "$BUILD_ROOT"/sofia-sip-bin_*.deb "$ARTIFACT_DIR"/

print_success "Installing custom Sofia-SIP build packages on this build host for the FreeSWITCH compile..."
DEBIAN_FRONTEND=noninteractive apt-get install -y \
    "$ARTIFACT_DIR"/libsofia-sip-ua0_*.deb \
    "$ARTIFACT_DIR"/libsofia-sip-ua-dev_*.deb

print_success "Building FreeSWITCH package set..."
rm -rf "$BUILD_ROOT/freeswitch"
git clone --depth 1 --branch "$FREESWITCH_VERSION" https://github.com/nemerald-voip/freeswitch.git "$BUILD_ROOT/freeswitch"
cd "$BUILD_ROOT/freeswitch"

cat > debian/modules.conf <<'EOF'
applications/mod_callcenter
applications/mod_cidlookup
applications/mod_commands
applications/mod_conference
applications/mod_curl
applications/mod_db
applications/mod_dptools
applications/mod_enum
applications/mod_esf
applications/mod_expr
applications/mod_fifo
applications/mod_fsv
applications/mod_hash
applications/mod_hiredis
applications/mod_httapi
applications/mod_memcache
applications/mod_sms
applications/mod_spandsp
applications/mod_translate
applications/mod_valet_parking
applications/mod_voicemail
codecs/mod_amr
codecs/mod_b64
codecs/mod_g723_1
codecs/mod_g729
codecs/mod_opus
databases/mod_pgsql
dialplans/mod_dialplan_asterisk
dialplans/mod_dialplan_xml
endpoints/mod_loopback
endpoints/mod_rtc
endpoints/mod_sofia
event_handlers/mod_cdr_csv
event_handlers/mod_cdr_sqlite
event_handlers/mod_event_socket
formats/mod_local_stream
formats/mod_native_file
formats/mod_png
formats/mod_shout
formats/mod_sndfile
formats/mod_tone_stream
languages/mod_lua
loggers/mod_console
loggers/mod_logfile
loggers/mod_syslog
say/mod_say_en
xml_int/mod_xml_cdr
xml_int/mod_xml_scgi
EOF

(cd debian && ./bootstrap.sh -c "$OS_CODENAME")

sed -i -e 's/Environment="USER=freeswitch"/Environment="USER=www-data"/' debian/freeswitch-systemd.freeswitch.service
sed -i -e 's/Environment="GROUP=freeswitch"/Environment="GROUP=www-data"/' debian/freeswitch-systemd.freeswitch.service
sed -i -e '/^ExecStartPre=\/bin\/chown/i ExecStartPre=/bin/mkdir -p /var/run/freeswitch' debian/freeswitch-systemd.freeswitch.service

DEBIAN_FRONTEND=noninteractive mk-build-deps -i -r -t "apt-get -y --no-install-recommends" debian/control

export FS_CPPFLAGS="${FS_CPPFLAGS:-"-D_FORTIFY_SOURCE=2 -DHAVE_NUA_RELOAD_TLS"}"
dpkg-buildpackage -b -us -uc -j"$JOBS"
cp "$BUILD_ROOT"/*.deb "$ARTIFACT_DIR"/

print_success "Package artifacts written to $ARTIFACT_DIR"
print_success "Copy that directory to target servers and run install/install_freeswitch_packages.sh."
