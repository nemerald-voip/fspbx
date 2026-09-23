#!/bin/bash
if [ -z "${BASH_VERSION:-}" ]; then exec /bin/bash "$0" "$@"; fi
set -Eeuo pipefail
source "$(dirname -- "$(readlink -f "$0")")/freeswitch-common.sh"

# Use a disposable build host of the same Debian release and architecture as
# the targets. Build dependencies are installed on this host.
[[ $(id -u) == 0 ]] || fs_die 'Run the package builder as root on a dedicated build host.'
OS_CODENAME=$(. /etc/os-release; printf '%s' "${VERSION_CODENAME:-}")
[[ $(. /etc/os-release; printf '%s' "$ID") == debian ]] || fs_die 'Debian is required.'
case "$OS_CODENAME" in bookworm|trixie) ;; *) fs_die 'Use Debian 12 or 13.' ;; esac
JOBS=${JOBS:-$(getconf _NPROCESSORS_ONLN)}
BUILD_ROOT=${BUILD_ROOT:-/usr/src/fspbx-freeswitch-package-build}
mkdir -p "$BUILD_ROOT"
BUILD_DIR=$(mktemp -d "$BUILD_ROOT/build-XXXXXX")
FS_BACKUP_DIR="$BUILD_DIR/metadata"
mkdir -m 700 "$FS_BACKUP_DIR"
STAMP=$(date -u +%Y%m%d%H%M%S)
ARTIFACT_DIR=${ARTIFACT_DIR:-$FS_APP_DIR/storage/app/freeswitch-packages/$OS_CODENAME/$STAMP}
[[ ! -e "$ARTIFACT_DIR" ]] || fs_die 'ARTIFACT_DIR must be a new directory, to avoid mixing package builds.'
trap fs_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
fs_protect_services
apt-get update
fs_apt_install build-essential ca-certificates debhelper devscripts dh-autoreconf \
    dpkg-dev equivs git pkg-config python3 libpcre2-dev libpq-dev libyuv-dev

build_dependency() {
    local name=$1 ref=$2 version=$3
    mkdir "$BUILD_DIR/$name"
    fs_clone "https://github.com/freeswitch/$name.git" "$ref" "$BUILD_DIR/$name/source"
    (
        cd "$BUILD_DIR/$name/source"
        printf '10\n' > debian/compat
        dch -b -m -v "$version+fspbx.$STAMP-1~$OS_CODENAME" --force-distribution -D "$OS_CODENAME" 'FS PBX dependency build.'
        DEBIAN_FRONTEND=noninteractive mk-build-deps -i -r -t 'apt-get -y --no-install-recommends' debian/control
        dpkg-buildpackage -b -us -uc -j"$JOBS"
    )
}
build_dependency sofia-sip "$SOFIA_SIP_VERSION" 1.13.18
build_dependency spandsp "$SPANDSP_VERSION" 3.1.1
# Installing development packages can replace distro development headers on
# this disposable builder. Runtime targets only receive the runtime libraries.
DEBIAN_FRONTEND=noninteractive apt-get install -y \
    "$BUILD_DIR"/sofia-sip/libsofia-sip-ua0_*.deb "$BUILD_DIR"/sofia-sip/libsofia-sip-ua-dev_*.deb \
    "$BUILD_DIR"/spandsp/libspandsp4_*.deb "$BUILD_DIR"/spandsp/libspandsp4-dev_*.deb
/sbin/ldconfig
fs_check_libraries

mkdir "$BUILD_DIR/freeswitch"
fs_clone https://github.com/nemerald-voip/freeswitch.git "$FREESWITCH_VERSION" "$BUILD_DIR/freeswitch/source"
cd "$BUILD_DIR/freeswitch/source"
python3 "$FS_CONFIG_TOOL" select-modules "$PWD" "$FS_APP_DIR/resources" \
    "${FREESWITCH_MODULES_FILE:-$FS_INSTALL_DIR/freeswitch-modules.conf}" debian/modules.conf
# Let dpkg-shlibdeps select the installed hiredis ABI on both Debian releases.
sed -i '/^Depends: libhiredis0\.10 | libhiredis0\.13 | libhiredis0\.14$/d' debian/control-modules
(cd debian && ./bootstrap.sh -c "$OS_CODENAME")
sed -i -e 's/libtiff5-dev/libtiff-dev/g' -e 's/libncurses5-dev/libncurses-dev/g' debian/control
printf '10\n' > debian/compat
# Activation is always an administrator action, even when packages are used.
sed -i 's/dh_systemd_start  /dh_systemd_start --no-start --no-stop-on-upgrade /' debian/rules
BASE_VERSION=$(sed -n 's/^AC_INIT(\[freeswitch\], \[\([0-9.]*\).*$/\1/p' configure.ac)
[[ "$BASE_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || fs_die 'Unable to determine the FreeSWITCH source version.'
dch -b -m -v "$BASE_VERSION+fspbx.$STAMP.g$(git rev-parse --short HEAD)-1~$OS_CODENAME" \
    --force-distribution -D "$OS_CODENAME" 'FS PBX FreeSWITCH build.'
sed -i -e 's/Environment="USER=freeswitch"/Environment="USER=www-data"/' \
    -e 's/Environment="GROUP=freeswitch"/Environment="GROUP=www-data"/' \
    -e '/^ExecStartPre=\/bin\/chown/i ExecStartPre=/bin/mkdir -p /var/run/freeswitch' \
    debian/freeswitch-systemd.freeswitch.service
# Do not change ownership of existing runtime data during package upgrades.
sed -i -e '/^      chown freeswitch \$x$/d' \
    -e 's/chown freeswitch:freeswitch/chown www-data:www-data/g' debian/freeswitch.postinst
DEBIAN_FRONTEND=noninteractive mk-build-deps -i -r -t 'apt-get -y --no-install-recommends' debian/control
export FS_CPPFLAGS="${FS_CPPFLAGS:--D_FORTIFY_SOURCE=2} -DHAVE_NUA_RELOAD_TLS"
dpkg-buildpackage -b -us -uc -j"$JOBS"

mkdir -p "$ARTIFACT_DIR"
ARTIFACT_DIR=$(realpath "$ARTIFACT_DIR")
for package in "$BUILD_DIR"/{sofia-sip,spandsp,freeswitch}/*.deb; do
    name=$(dpkg-deb -f "$package" Package)
    case "$name" in
        *-dbg|*-dbgsym|*-dev|*-doc) continue ;;
        freeswitch|libfreeswitch1|freeswitch-systemd|freeswitch-mod-*|libsofia-sip-ua0|libspandsp4)
            cp "$package" "$ARTIFACT_DIR/"
            basename "$package" >> "$ARTIFACT_DIR/runtime-packages.txt" ;;
    esac
done
printf '%s\n' "$OS_CODENAME" > "$ARTIFACT_DIR/debian-codename"
dpkg --print-architecture > "$ARTIFACT_DIR/architecture"
cp "$FS_BACKUP_DIR/source-versions.txt" "$ARTIFACT_DIR/"
cp debian/modules.conf "$ARTIFACT_DIR/modules.conf"
(cd "$ARTIFACT_DIR" && sha256sum -- *.deb runtime-packages.txt debian-codename architecture source-versions.txt modules.conf > SHA256SUMS)
fs_info "Package bundle: $ARTIFACT_DIR"
fs_info "Copy the complete bundle to a matching server and run: sudo bash install/install_freeswitch_packages.sh PACKAGE_DIR"
