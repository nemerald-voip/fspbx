#!/usr/bin/env bash
set -euo pipefail

fail() { echo "Photo processing setup: $*" >&2; exit 1; }
require_root() { [[ $(id -u) == 0 ]] || fail 'Run the installer/updater as root.'; }

package_installed() {
  [[ $(dpkg-query -W -f='${Status}' "$1" 2>/dev/null) == 'install ok installed' ]]
}

select_packages() {
  # Debian 12 uses libvips42; Debian 13 uses libvips42t64.
  photo_vips_package=libvips42
  if package_installed libvips42t64 || apt-cache show libvips42t64 >/dev/null 2>&1; then
    photo_vips_package=libvips42t64
  fi
  # libvips-tools supplies vips/vipsheader; the library depends on the JPEG,
  # PNG, WebP, TIFF, and HEIF libraries. nice/prlimit come from the base tools.
  photo_packages=("$photo_vips_package" libvips-tools coreutils util-linux)
  # Newer libheif packages split HEIC/AVIF decoders into plugins.
  if [[ $photo_vips_package == libvips42t64 ]] || apt-cache show libheif-plugin-libde265 >/dev/null 2>&1; then
    photo_packages+=(libheif-plugin-libde265 libheif-plugin-dav1d)
  fi
}

install_dependencies() {
  require_root
  local photo_missing=0 photo_package
  select_packages
  for photo_package in "${photo_packages[@]}"; do
    package_installed "$photo_package" || photo_missing=1
  done
  if [[ $photo_missing == 1 ]]; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get -o Acquire::Retries=3 -o DPkg::Lock::Timeout=120 update
    select_packages
    apt-get -o Acquire::Retries=3 -o DPkg::Lock::Timeout=120 install -y --no-install-recommends \
      "${photo_packages[@]}"
  fi
  echo 'Photo processing dependencies are installed.'
}

install_dependencies
