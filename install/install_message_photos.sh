#!/usr/bin/env bash
set -euo pipefail

fail() { echo "Photo processing setup: $*" >&2; exit 1; }
require_root() { [[ $(id -u) == 0 ]] || fail 'Run the installer/updater as root.'; }

package_installed() {
  [[ $(dpkg-query -W -f='${Status}' "$1" 2>/dev/null) == 'install ok installed' ]]
}

package_version() { dpkg-query -W -f='${Version}' "$1" 2>/dev/null; }

package_at_least() {
  package_installed "$1" && dpkg --compare-versions "$(package_version "$1")" ge "$2"
}

detect_platform() {
  # Keep backports on the matching Debian release; never mix stable releases.
  source /etc/os-release || fail 'Unable to read /etc/os-release.'
  photo_codename=${VERSION_CODENAME:-}
  local photo_platform_error
  case "${ID:-}:$photo_codename" in
    debian:bookworm|debian:trixie) return 0 ;;
    debian:*) photo_platform_error="Unsupported Debian release: $photo_codename. Expected bookworm or trixie." ;;
    *) photo_platform_error='Photo dependencies require Debian 12 or 13.' ;;
  esac

  [[ ${1:-} == --skip-unsupported ]] || fail "$photo_platform_error"
  echo "WARNING: Photo processing setup skipped. $photo_platform_error" >&2
  echo 'WARNING: Outbound photo conversion/compression may fail until compatible dependencies are installed.' >&2
  echo 'After upgrading the OS, run: bash /var/www/fspbx/install/install_message_photos.sh' >&2
  return 1
}

select_packages() {
  photo_vips_package=libvips42
  [[ $photo_codename != trixie ]] || photo_vips_package=libvips42t64
  photo_packages=("$photo_vips_package" libvips-tools coreutils util-linux)
  # Messaging decodes HEIC/AVIF inputs and writes JPEG output.
  photo_heif_packages=(libheif1 libheif-plugin-libde265 libheif-plugin-dav1d)
}

packages_ready() {
  local photo_package
  for photo_package in "${photo_packages[@]}" "${photo_heif_packages[@]}"; do
    package_installed "$photo_package" || return 1
  done
  # 1.18 fixes metadata handling for HEIC photos produced by newer iPhones.
  package_at_least libheif1 1.18.0 && package_at_least libvips-tools 8.13.0
}

ensure_bookworm_backports() {
  # Reuse an existing active backports source when available.
  if apt-cache policy | grep 'n=bookworm-backports[, ]' >/dev/null; then return; fi
  [[ -r /usr/share/keyrings/debian-archive-keyring.gpg ]] || fail 'Debian archive signing keys are missing.'
  local photo_source=/etc/apt/sources.list.d/fspbx-message-photos.sources
  local photo_source_tmp
  photo_source_tmp=$(mktemp)
  cat > "$photo_source_tmp" <<'SOURCE'
# FS PBX photo processing: packages built for Debian 12.
Types: deb
URIs: https://deb.debian.org/debian
Suites: bookworm-backports
Components: main
Signed-By: /usr/share/keyrings/debian-archive-keyring.gpg
SOURCE
  if ! cmp -s "$photo_source_tmp" "$photo_source"; then
    install -m 0644 "$photo_source_tmp" "$photo_source"
    echo 'Photo processing setup: enabled Debian bookworm-backports.'
  fi
  rm -f "$photo_source_tmp"
}

verify_tools() {
  local photo_operations photo_operation
  photo_operations=$(vips -l foreign)
  for photo_operation in jpegload pngload webpload heifload tiffload jpegsave; do
    [[ $photo_operations == *"($photo_operation)"* ]] || fail "libvips is missing $photo_operation."
  done
  vipsheader --version
  nice -n 10 prlimit --as=536870912 --cpu=3 --core=0 -- vips --version
  echo "Photo processing ready: libheif $(package_version libheif1), libvips $(package_version libvips-tools)."
}

install_dependencies() {
  require_root
  # Updates may defer photo setup on older systems without changing APT sources.
  if ! detect_platform "${1:-}"; then return 0; fi
  select_packages
  if packages_ready; then
    verify_tools
    return
  fi

  [[ ${1:-} != --check ]] || fail 'Photo dependencies need upgrading. Run install/install_message_photos.sh as root.'
  export DEBIAN_FRONTEND=noninteractive
  # Avoid unrelated automatic service restarts from needrestart during apt.
  export NEEDRESTART_MODE=l
  local photo_install=("${photo_packages[@]}") photo_package
  if [[ $photo_codename == bookworm ]]; then
    ensure_bookworm_backports
    for photo_package in "${photo_heif_packages[@]}"; do
      photo_install+=("$photo_package/bookworm-backports")
    done
  else
    photo_install+=("${photo_heif_packages[@]}")
  fi

  apt-get -o Acquire::Retries=3 -o DPkg::Lock::Timeout=120 update
  # Show the exact plan first and refuse removals or implicit downgrades.
  apt-get -s --no-remove --no-install-recommends install "${photo_install[@]}"
  if [[ ${1:-} == --dry-run ]]; then return; fi
  apt-get -o Acquire::Retries=3 -o DPkg::Lock::Timeout=120 install -y --no-remove --no-install-recommends \
    "${photo_install[@]}"
  packages_ready || fail 'Installation finished without the required libheif 1.18+ and decoder plugins.'
  verify_tools
}

if [[ ${BASH_SOURCE[0]} == "$0" ]]; then
  [[ $# == 0 || ( $# == 1 && ( $1 == --check || $1 == --dry-run || $1 == --skip-unsupported ) ) ]] \
    || fail 'Usage: install_message_photos.sh [--check|--dry-run|--skip-unsupported]. Dry-run configures the package source and shows the install plan.'
  install_dependencies "${1:-}"
fi
