#!/bin/bash

if [ -z "${BASH_VERSION:-}" ]; then
    exec /bin/bash "$0" "$@"
fi
set -Eeuo pipefail

source "$(dirname -- "$(readlink -f "$0")")/freeswitch-common.sh"
FRESH_INSTALL=false
case "${1:-}" in
    --fresh-install) FRESH_INSTALL=true; shift ;;
esac
[[ $# == 0 ]] || fs_die 'Usage: install_freeswitch.sh [--fresh-install]'
fs_preflight
fs_protect_services

JOBS=${JOBS:-$(getconf _NPROCESSORS_ONLN)}
BUILD_ROOT=${BUILD_ROOT:-/usr/src/fspbx-freeswitch-builds}
mkdir -p "$BUILD_ROOT"
BUILD_DIR=$(mktemp -d "$BUILD_ROOT/build-XXXXXX")
fs_info "Building FreeSWITCH $FREESWITCH_VERSION in $BUILD_DIR"
fs_info 'Avoid editing FreeSWITCH configuration until this installation finishes.'

apt-get update
fs_apt_install autoconf automake build-essential libtool libtool-bin pkg-config \
    git ca-certificates cmake ccache python3 rsync uuid-dev libssl-dev libpcre2-dev \
    libncurses-dev libjpeg-dev flac libgdbm-dev libdb-dev gettext \
    libpq-dev liblua5.2-dev libtiff-dev libperl-dev libcurl4-openssl-dev libsqlite3-dev \
    libspeexdsp-dev libspeex-dev libldns-dev libedit-dev libopus-dev libopencore-amrnb-dev \
    libmemcached-dev libhiredis-dev libshout3-dev libmpg123-dev libmp3lame-dev \
    yasm nasm libsndfile1-dev libuv1-dev libvpx-dev libavformat-dev libavcodec-dev \
    libavutil-dev libswscale-dev libswresample-dev libyuv-dev libvlc-dev flite1-dev \
    sox libsox-fmt-all sqlite3 unzip

fs_prepare_compiler_cache
export PKG_CONFIG_PATH="/usr/local/lib/pkgconfig:/usr/local/lib/$(gcc -dumpmachine)/pkgconfig:${PKG_CONFIG_PATH:-}"

fs_clone https://github.com/nemerald-voip/freeswitch.git "$FREESWITCH_VERSION" "$BUILD_DIR/freeswitch"
CONFIG_SOURCE="$FS_CONF_DIR"
if [[ "$FRESH_INSTALL" == true ]]; then
    CONFIG_SOURCE="$BUILD_DIR/default-config"
    mkdir -p "$CONFIG_SOURCE/autoload_configs"
    cp -a "$FS_APP_DIR/resources/autoload_configs/." "$CONFIG_SOURCE/autoload_configs/"
fi
python3 "$FS_CONFIG_TOOL" select-modules "$BUILD_DIR/freeswitch" "$CONFIG_SOURCE" \
    "${FREESWITCH_MODULES_FILE:-$FS_INSTALL_DIR/freeswitch-modules.conf}" "$BUILD_DIR/modules.conf"
if grep -qx 'languages/mod_v8' "$BUILD_DIR/modules.conf"; then
    fs_apt_install libnode-dev
fi

fs_build_dependencies

cd "$BUILD_DIR/freeswitch"
./bootstrap.sh -j
cp "$BUILD_DIR/modules.conf" modules.conf
export CPPFLAGS="${CPPFLAGS:-} -DHAVE_NUA_RELOAD_TLS"
./configure --enable-portable-binary --disable-dependency-tracking --enable-debug \
    --prefix=/usr --localstatedir=/var --sysconfdir=/etc --with-modinstdir="$FS_MOD_DIR" \
    --with-openssl
make -j "$JOBS"
ccache --show-stats > "$FS_BACKUP_DIR/compiler-cache-after.txt"
STAGE="$BUILD_DIR/stage"
make DESTDIR="$STAGE" install
python3 "$FS_CONFIG_TOOL" check-modules "$CONFIG_SOURCE" "$STAGE$FS_MOD_DIR"
export LD_LIBRARY_PATH="$STAGE/usr/lib:$STAGE/usr/lib/$(gcc -dumpmachine):${LD_LIBRARY_PATH:-}"
if ldd "$STAGE/usr/bin/freeswitch" | grep -q 'not found'; then
    fs_die 'The candidate FreeSWITCH binary has unresolved shared libraries.'
fi
LD_BIND_NOW=1 "$STAGE/usr/bin/freeswitch" -version > "$FS_BACKUP_DIR/candidate-version.txt"
unset LD_LIBRARY_PATH

fs_begin_install
# Replace program files by rename, avoiding writes into mapped library inodes.
# Configuration and application data are deliberately outside this copy.
rsync -a --delay-updates --exclude='/share/freeswitch/scripts/' "$STAGE/usr/" /usr/
/sbin/ldconfig

if [[ "$FRESH_INSTALL" == true ]]; then
    for directory in /var/lib/freeswitch /var/log/freeswitch /var/run/freeswitch /var/cache/fusionpbx; do
        install -d -o www-data -g www-data "$directory"
    done
    install -m 644 debian/freeswitch-systemd.freeswitch.service /lib/systemd/system/freeswitch.service
    sed -i -e 's/Environment="USER=freeswitch"/Environment="USER=www-data"/' \
        -e 's/Environment="GROUP=freeswitch"/Environment="GROUP=www-data"/' \
        -e '/^ExecStartPre=\/bin\/chown/i ExecStartPre=/bin/mkdir -p /var/run/freeswitch' \
        /lib/systemd/system/freeswitch.service
    if [[ -d /proc/vz || -e /proc/user_beancounters ]]; then
        sed -i 's/^CPUSchedulingPolicy=rr/;CPUSchedulingPolicy=rr/' /lib/systemd/system/freeswitch.service
    fi
    systemctl enable freeswitch
fi

# Retain previous sources; generated makefiles keep their original build path.
if [[ -e /usr/src/freeswitch || -L /usr/src/freeswitch ]]; then
    mv /usr/src/freeswitch "$FS_BACKUP_DIR/previous-source"
fi
ln -s "$BUILD_DIR/freeswitch" /usr/src/freeswitch
fs_finish
