#!/bin/bash
# Shared installer operations. Source this file; it does not run an install.

FS_INSTALL_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
FS_APP_DIR=$(dirname "$FS_INSTALL_DIR")
source "$FS_INSTALL_DIR/freeswitch-versions.sh"
FS_CONFIG_TOOL="$FS_INSTALL_DIR/freeswitch_config.py"
FS_CONF_DIR=${FREESWITCH_CONF_DIR:-/etc/freeswitch}
FS_MOD_DIR=${FREESWITCH_MOD_DIR:-/usr/lib/freeswitch/mod}
FS_POLICY_PATH=${FS_POLICY_PATH:-/usr/sbin/policy-rc.d}
FS_SERVICE_PATH=${FS_SERVICE_PATH:-/lib/systemd/system/freeswitch.service}
FS_LOCK_PATH=${FS_LOCK_PATH:-/run/lock/fspbx-freeswitch-install.lock}
FS_BACKUP_ROOT=${FREESWITCH_BACKUP_ROOT:-/var/backups/fspbx-freeswitch}
FS_CONFIG_PROTECTED=false
FS_RESTORE_ARMED=false
FS_POLICY_ACTIVE=false
FS_BACKUP_DIR=

fs_die() { printf 'Error: %s\n' "$*" >&2; exit 1; }
fs_info() { printf '%s\n' "$*"; }

fs_preflight() {
    [[ $(id -u) == 0 ]] || fs_die 'Run this installer as root.'
    OS_CODENAME=$(. /etc/os-release; printf '%s' "${VERSION_CODENAME:-}")
    [[ $(. /etc/os-release; printf '%s' "$ID") == debian ]] || fs_die 'Debian 12 or 13 is required.'
    case "$OS_CODENAME" in
        bookworm|trixie) ;;
        *) fs_die "Supported operating systems are Debian 12 and 13; detected $OS_CODENAME." ;;
    esac
    for executable in python3 cp flock; do
        command -v "$executable" >/dev/null || fs_die "Required base tool is missing: $executable"
    done
    # Keep two source/package installers from changing the same host together.
    exec 9>"$FS_LOCK_PATH"
    flock -n 9 || fs_die 'Another FreeSWITCH installer is running.'
    if [[ "$FRESH_INSTALL" == true ]]; then
        [[ ! -e "$FS_CONF_DIR" || ( -d "$FS_CONF_DIR" && -z $(find "$FS_CONF_DIR/" -mindepth 1 -maxdepth 1 -print -quit) ) ]] || fs_die 'Existing FreeSWITCH configuration found. Run without --fresh-install.'
    else
        [[ -f "$FS_CONF_DIR/freeswitch.xml" && -f "$FS_CONF_DIR/vars.xml" ]] || fs_die 'Existing configuration not found. Use --fresh-install only for a new server.'
    fi
    [[ "$FS_CONF_DIR" == /* && "$FS_CONF_DIR" != / ]] || fs_die 'Invalid FreeSWITCH configuration directory.'
    FS_CONF_DIR=$(realpath -m "$FS_CONF_DIR")
    [[ "$FS_CONF_DIR" != / ]] || fs_die 'Invalid FreeSWITCH configuration directory.'
    umask 077
    mkdir -p "$FS_BACKUP_ROOT"
    chmod 700 "$FS_BACKUP_ROOT"
    FS_BACKUP_DIR=$(mktemp -d "$FS_BACKUP_ROOT/$(date -u +%Y%m%dT%H%M%SZ)-XXXXXX")
    if [[ -d "$FS_CONF_DIR" ]]; then
        # Snapshot the complete tree, including unknown/custom and hidden files.
        cp -a -- "$FS_CONF_DIR" "$FS_BACKUP_DIR/config"
        python3 "$FS_CONFIG_TOOL" compare "$FS_BACKUP_DIR/config" "$FS_CONF_DIR" >/dev/null
        FS_CONFIG_PROTECTED=true
    fi
    if [[ -e "$FS_SERVICE_PATH" ]]; then
        cp -a "$FS_SERVICE_PATH" "$FS_BACKUP_DIR/freeswitch.service"
    fi
    umask 022
    trap fs_cleanup EXIT
    trap 'exit 130' INT
    trap 'exit 143' TERM
    fs_info "Configuration backup: $FS_BACKUP_DIR"
}

fs_protect_services() {
    if [[ -f "$FS_POLICY_PATH" ]] && grep -q 'FS PBX FreeSWITCH install guard' "$FS_POLICY_PATH"; then
        fs_die "A previous service guard is still present at $FS_POLICY_PATH. Restore its saved policy before retrying."
    fi
    if [[ -e "$FS_POLICY_PATH" || -L "$FS_POLICY_PATH" ]]; then
        cp -a -- "$FS_POLICY_PATH" "$FS_BACKUP_DIR/policy-rc.d"
        if [[ -x "$FS_POLICY_PATH" ]]; then
            cp -L --preserve=mode -- "$FS_POLICY_PATH" "$FS_BACKUP_DIR/policy-runner"
        fi
    fi
    local temporary
    temporary=$(mktemp "${FS_POLICY_PATH}.fspbx-XXXXXX")
    cat > "$temporary" <<EOF
#!/bin/sh
# FS PBX FreeSWITCH install guard. Original policy: $FS_BACKUP_DIR/policy-rc.d
case "\$*" in *freeswitch*) exit 101 ;; esac
if [ -x '$FS_BACKUP_DIR/policy-runner' ]; then
    exec '$FS_BACKUP_DIR/policy-runner' "\$@"
fi
exit 0
EOF
    chmod 755 "$temporary"
    FS_POLICY_ACTIVE=true
    mv -fT -- "$temporary" "$FS_POLICY_PATH"
    export NEEDRESTART_MODE=l
}

fs_restore_configuration() {
    [[ "$FS_RESTORE_ARMED" == true ]] || return 0
    if [[ "$FS_CONFIG_PROTECTED" == true ]]; then
        if ! python3 "$FS_CONFIG_TOOL" compare "$FS_BACKUP_DIR/config" "$FS_CONF_DIR" > "$FS_BACKUP_DIR/config-changes.txt"; then
            python3 "$FS_CONFIG_TOOL" restore "$FS_BACKUP_DIR/config" "$FS_CONF_DIR" || return 1
        fi
    fi
    if [[ -f "$FS_BACKUP_DIR/freeswitch.service" ]]; then
        cp -a --remove-destination "$FS_BACKUP_DIR/freeswitch.service" "$FS_SERVICE_PATH"
    fi
}

fs_cleanup() {
    local status=$?
    trap - EXIT
    if ! fs_restore_configuration; then
        printf 'Configuration recovery failed. Backup: %s\n' "$FS_BACKUP_DIR" >&2
        status=1
    fi
    if [[ "$FS_POLICY_ACTIVE" == true ]]; then
        if [[ -e "$FS_BACKUP_DIR/policy-rc.d" || -L "$FS_BACKUP_DIR/policy-rc.d" ]]; then
            cp -a --remove-destination "$FS_BACKUP_DIR/policy-rc.d" "$FS_POLICY_PATH" || status=1
        else
            rm -f -- "$FS_POLICY_PATH" || status=1
        fi
    fi
    if [[ $status != 0 ]]; then
        printf 'FreeSWITCH installation is incomplete. Do not restart until the error is resolved. Backup: %s\n' "$FS_BACKUP_DIR" >&2
    fi
    exit "$status"
}

fs_apt_install() {
    DEBIAN_FRONTEND=noninteractive apt-get -o Dpkg::Options::=--force-confold install --no-remove -y "$@"
}

fs_install_build_dependencies() {
    fs_apt_install autoconf automake build-essential libtool libtool-bin pkg-config \
        git ca-certificates cmake ccache python3 rsync sudo curl wget libc-bin \
        uuid-dev libssl-dev libpcre2-dev libncurses-dev libjpeg-dev flac libgdbm-dev libdb-dev gettext \
        libpq-dev liblua5.2-dev libtiff-dev libperl-dev libcurl4-openssl-dev libsqlite3-dev \
        libspeexdsp-dev libspeex-dev libldns-dev libedit-dev libopus-dev libopencore-amrnb-dev \
        libmemcached-dev libhiredis-dev libshout3-dev libmpg123-dev libmp3lame-dev \
        yasm nasm libsndfile1-dev libuv1-dev libvpx-dev libavformat-dev libavcodec-dev \
        libavutil-dev libswscale-dev libswresample-dev libyuv-dev libvlc-dev flite1-dev \
        sox libsox-fmt-all sqlite3 unzip
    # Debian 13 removed python3-distutils; setuptools supplies its replacement.
    # Keep the release-specific tools from the original Debian compatibility fix.
    case "$OS_CODENAME" in
        bookworm) fs_apt_install mlocate python3-distutils ;;
        trixie) fs_apt_install plocate python3-setuptools ;;
        *) fs_die 'Build dependencies require Debian 12 or 13.' ;;
    esac
}

fs_begin_install() {
    if [[ "$FS_CONFIG_PROTECTED" == true ]]; then
        python3 "$FS_CONFIG_TOOL" compare "$FS_BACKUP_DIR/config" "$FS_CONF_DIR" > "$FS_BACKUP_DIR/config-changes.txt" ||
            fs_die 'Configuration changed during the build. No FreeSWITCH files were installed; rerun to include those changes.'
    fi
    FS_RESTORE_ARMED=true
}

fs_clone() {
    local url=$1 ref=$2 target=$3
    git init -q "$target"
    git -C "$target" remote add origin "$url"
    git -C "$target" fetch --depth 1 origin "$ref"
    git -C "$target" checkout -q --detach FETCH_HEAD
    printf '%s %s\n' "$url" "$(git -C "$target" rev-parse HEAD)" >> "$FS_BACKUP_DIR/source-versions.txt"
}

fs_prepare_compiler_cache() {
    command -v ccache >/dev/null || fs_die 'The compiler cache is not installed.'
    export CCACHE_DIR=${CCACHE_DIR:-/var/cache/fspbx-freeswitch/ccache}
    [[ "$CCACHE_DIR" == /* && "$CCACHE_DIR" != / ]] || fs_die 'Invalid compiler cache directory.'
    install -d -m 700 "$CCACHE_DIR"
    export CCACHE_MAXSIZE=${CCACHE_MAXSIZE:-2G}
    export CCACHE_COMPILERCHECK=content
    export CCACHE_BASEDIR="$BUILD_DIR"
    # Keep normal compiler/header/option validation. Normalize only the changing
    # build path, including debug information, so clean builds can share results.
    FS_BUILD_PREFIX_FLAGS="-ffile-prefix-map=$BUILD_DIR=/usr/src/fspbx-build -fdebug-prefix-map=$BUILD_DIR=/usr/src/fspbx-build"
    export CPPFLAGS="${CPPFLAGS:-} $FS_BUILD_PREFIX_FLAGS"
    case "${CC:-gcc}" in
        ccache\ *|*/ccache\ *) ;;
        *) CC="ccache ${CC:-gcc}" ;;
    esac
    case "${CXX:-g++}" in
        ccache\ *|*/ccache\ *) ;;
        *) CXX="ccache ${CXX:-g++}" ;;
    esac
    export CC CXX
    ccache --show-stats > "$FS_BACKUP_DIR/compiler-cache-before.txt"
}

fs_probe_library() {
    local package=$1 minimum=$2 library check libdir loaded
    local -a cflags libs
    pkg-config --atleast-version="$minimum" "$package" || return 1
    check="$FS_BACKUP_DIR/$package-check"
    case "$package" in
        libks2)
            library=libks2
            cat > "$check.c" <<'EOF'
#include <libks/ks.h>
int main(void) {
    __typeof__(&ks_init) volatile init = &ks_init;
    __typeof__(&ks_shutdown) volatile shutdown = &ks_shutdown;
    return init == 0 || shutdown == 0;
}
EOF
            ;;
        sofia-sip-ua)
            library=libsofia-sip-ua
            cat > "$check.c" <<'EOF'
#include <sofia-sip/nua.h>
#ifndef HAVE_NUA_RELOAD_TLS
#error TLS reload unavailable
#endif
int main(void) {
    int (*volatile reload_tls)(nua_t *, char const *) = nua_reload_tls;
    return reload_tls == 0;
}
EOF
            ;;
        spandsp)
            library=libspandsp
            cat > "$check.c" <<'EOF'
#include <spandsp.h>
int main(void) {
    __typeof__(&fax_init) volatile fax = &fax_init;
    __typeof__(&t38_terminal_init) volatile t38 = &t38_terminal_init;
    __typeof__(&dtmf_rx_parms) volatile dtmf = &dtmf_rx_parms;
    return fax == 0 || t38 == 0 || dtmf == 0;
}
EOF
            ;;
        *) return 1 ;;
    esac
    read -r -a cflags <<< "$(pkg-config --cflags "$package")"
    read -r -a libs <<< "$(pkg-config --libs "$package")"
    gcc "${cflags[@]}" "$check.c" -o "$check" "${libs[@]}" > "$check.log" 2>&1 || return 1
    ldd "$check" > "$check.libraries" 2>> "$check.log" || return 1
    if grep -q 'not found' "$check.libraries"; then return 1; fi
    # Headers and a .pc file alone do not prove the runtime loader will use the
    # matching library: /usr/local or LD_LIBRARY_PATH can shadow another copy.
    libdir=$(pkg-config --variable=libdir "$package") || return 1
    loaded=$(awk -v prefix="$library.so" 'index($1, prefix) == 1 && $2 == "=>" { print $3; exit }' "$check.libraries")
    [[ -n "$loaded" && -f "$libdir/$library.so" ]] || return 1
    [[ $(readlink -f "$loaded") == "$(readlink -f "$libdir/$library.so")" ]] || return 1
    LD_BIND_NOW=1 "$check" >> "$check.log" 2>&1
}

fs_dependency_ready() {
    local package=$1 ref=$2 version
    # A branch or commit override may contain patches not represented by the
    # installed package version. Always build explicitly requested custom refs.
    [[ "$ref" =~ ^v?([0-9]+\.[0-9]+\.[0-9]+)$ ]] || return 1
    version=${BASH_REMATCH[1]}
    fs_probe_library "$package" "$version" || return 1
    printf '%s %s reused\n' "$package" "$(pkg-config --modversion "$package")" >> "$FS_BACKUP_DIR/dependency-actions.txt"
}

fs_check_libraries() {
    fs_probe_library sofia-sip-ua 1.13.18 || fs_die 'Sofia-SIP 1.13.18 or newer with working TLS reload support is required.'
    fs_probe_library spandsp 3.1.1 || fs_die 'SpanDSP 3.1.1 or newer with working fax, T.38, and DTMF support is required.'
    pkg-config --atleast-version=10.00 libpcre2-8 || fs_die 'PCRE2 development files are required.'
    pkg-config --modversion sofia-sip-ua spandsp libpcre2-8 > "$FS_BACKUP_DIR/dependency-versions.txt"
}

fs_publish_dependencies() {
    # Rename replacements so existing processes retain their mapped files.
    rsync -a --delay-updates "$BUILD_DIR/dependencies/usr/local/" /usr/local/
    /sbin/ldconfig
}

fs_build_dependencies() {
    local built=false
    if fs_dependency_ready libks2 "$LIBKS_VERSION"; then
        fs_info 'Using the installed libks library.'
    else
        fs_clone https://github.com/signalwire/libks.git "$LIBKS_VERSION" "$BUILD_DIR/libks"
        # CPack's changelog generation needs Git history; this is a library-only
        # install. Override libks's /usr default to match the staged copy below.
        cmake -S "$BUILD_DIR/libks" -B "$BUILD_DIR/libks/build" -DCMAKE_BUILD_TYPE=Release \
            -DCMAKE_INSTALL_PREFIX=/usr/local -DWITH_PACKAGING=OFF \
            "-DCMAKE_C_FLAGS=${CFLAGS:-} ${FS_BUILD_PREFIX_FLAGS:-}" \
            "-DCMAKE_CXX_FLAGS=${CXXFLAGS:-} ${FS_BUILD_PREFIX_FLAGS:-}"
        cmake --build "$BUILD_DIR/libks/build" --parallel "$JOBS"
        DESTDIR="$BUILD_DIR/dependencies" cmake --install "$BUILD_DIR/libks/build"
        printf 'libks2 %s built\n' "$LIBKS_VERSION" >> "$FS_BACKUP_DIR/dependency-actions.txt"
        built=true
    fi
    if fs_dependency_ready sofia-sip-ua "$SOFIA_SIP_VERSION"; then
        fs_info 'Using the installed Sofia-SIP library.'
    else
        fs_clone https://github.com/freeswitch/sofia-sip.git "$SOFIA_SIP_VERSION" "$BUILD_DIR/sofia-sip"
        (
            cd "$BUILD_DIR/sofia-sip"
            sh autogen.sh
            ./configure --prefix=/usr/local
            make -j "$JOBS"
            make DESTDIR="$BUILD_DIR/dependencies" install
        )
        printf 'sofia-sip-ua %s built\n' "$SOFIA_SIP_VERSION" >> "$FS_BACKUP_DIR/dependency-actions.txt"
        built=true
    fi
    if fs_dependency_ready spandsp "$SPANDSP_VERSION"; then
        fs_info 'Using the installed SpanDSP library.'
    else
        fs_clone https://github.com/freeswitch/spandsp.git "$SPANDSP_VERSION" "$BUILD_DIR/spandsp"
        (
            cd "$BUILD_DIR/spandsp"
            sh autogen.sh
            ./configure --prefix=/usr/local
            make -j "$JOBS"
            make DESTDIR="$BUILD_DIR/dependencies" install
        )
        printf 'spandsp %s built\n' "$SPANDSP_VERSION" >> "$FS_BACKUP_DIR/dependency-actions.txt"
        built=true
    fi
    if [[ "$built" == true ]]; then
        fs_publish_dependencies
    fi
    fs_probe_library libks2 2.0.11 || fs_die 'libks 2.0.11 or newer with working runtime libraries is required.'
    fs_check_libraries
}

fs_seed_configuration() {
    [[ "$FRESH_INSTALL" == true ]] || return 0
    local source="$FS_APP_DIR/public/app/switch/resources/conf" entry
    [[ -f "$source/freeswitch.xml" ]] || fs_die 'FS PBX default configuration is missing.'
    mkdir -p "$FS_CONF_DIR"
    # Include hidden files, but never restore the obsolete legacy autoload tree.
    while IFS= read -r -d '' entry; do
        [[ $(basename "$entry") == autoload_configs ]] && continue
        cp -a -- "$entry" "$FS_CONF_DIR/"
    done < <(find "$source" -mindepth 1 -maxdepth 1 -print0)
    mkdir -p "$FS_CONF_DIR/autoload_configs"
    cp -a "$FS_APP_DIR/resources/autoload_configs/." "$FS_CONF_DIR/autoload_configs/"
    chown -R www-data:www-data "$FS_CONF_DIR"
}

fs_print_completion() {
    local bold= green= yellow= cyan= reset=
    local border='======================================================================'
    if [[ -t 1 && -z "${NO_COLOR+x}" && "${TERM:-}" != dumb ]]; then
        bold=$'\033[1m'
        green=$'\033[1;32m'
        yellow=$'\033[1;33m'
        cyan=$'\033[1;36m'
        reset=$'\033[0m'
    fi
    printf '\n%s%s%s\n' "$green" "$border" "$reset"
    printf '%s  FREESWITCH INSTALLATION COMPLETE%s\n' "$green" "$reset"
    printf '%s%s%s\n\n' "$green" "$border" "$reset"
    if [[ "$FRESH_INSTALL" == false ]]; then
        printf '  Configuration preserved. Generated XML cache cleared.\n\n'
    fi
    printf '%s  ACTION REQUIRED: MANUAL RESTART%s\n' "$yellow" "$reset"
    printf '  FreeSWITCH has not been restarted. Restart to use the new version.\n'
    printf '  Choose a suitable time: restarting interrupts active calls.\n\n'
    printf '  %s1. Restart when ready:%s\n' "$bold" "$reset"
    printf '     %ssudo systemctl restart freeswitch%s\n\n' "$cyan" "$reset"
    printf '  %s2. Verify the running version:%s\n' "$bold" "$reset"
    printf '     %ssudo fs_cli -x version%s\n\n' "$cyan" "$reset"
    printf '  %sConfiguration backup:%s\n     %s\n' "$bold" "$reset" "$FS_BACKUP_DIR"
    printf '\n%s%s%s\n\n' "$green" "$border" "$reset"
}

fs_finish() {
    fs_restore_configuration
    fs_seed_configuration
    python3 "$FS_CONFIG_TOOL" prune-retired "$FS_CONF_DIR"
    python3 "$FS_CONFIG_TOOL" retire-binaries "$FS_MOD_DIR"
    FS_CONFIG_PROTECTED=false
    FS_RESTORE_ARMED=false
    if [[ "$FRESH_INSTALL" == false ]]; then
        sudo -u www-data -- php "$FS_APP_DIR/artisan" freeswitch:prepare-restart --preserve-vars --no-interaction
    fi
    systemctl daemon-reload
    if [[ "$FRESH_INSTALL" == true ]]; then
        # Refresh optional ReadWritePaths that did not exist when PHP-FPM started.
        local service
        while read -r service _; do
            [[ -z "$service" ]] || systemctl restart "$service"
        done < <(systemctl list-units --type=service --state=running --no-legend 'php*-fpm.service')
    fi
    fs_print_completion
}
