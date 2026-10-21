#!/bin/bash
set -euo pipefail

GREEN="\e[32m"; RED="\e[31m"; YELLOW="\e[33m"; RESET="\e[0m"
print_success(){ echo -e "${GREEN}$1${RESET}"; }
print_error(){ echo -e "${RED}$1${RESET}"; }
print_warn(){ echo -e "${YELLOW}$1${RESET}"; }
has_cmd(){ command -v "$1" >/dev/null 2>&1; }

require_root() {
  if [ "${EUID:-$(id -u)}" -ne 0 ]; then
    print_error "Please run as root: sudo bash $0"
    exit 1
  fi
}

write_sury_key_noninteractive() {
  mkdir -p /etc/apt/keyrings
  local tmp="/etc/apt/keyrings/sury-php.gpg.tmp.$$"

  curl -fsSL https://packages.sury.org/php/apt.gpg \
    | gpg --dearmor --batch --yes -o "$tmp"

  chmod 0644 "$tmp"
  mv -f "$tmp" /etc/apt/keyrings/sury-php.gpg
}

normalize_sury_repo() {
  print_success "Normalizing Sury PHP repo (prevents Signed-By conflicts)..."

  mkdir -p /root/php-install-audit/disabled-apt-sources

  # Move any existing Sury php sources out of sources.list.d to avoid Signed-By conflicts
  shopt -s nullglob
  for f in /etc/apt/sources.list.d/*.{list,sources}; do
    if grep -q "packages.sury.org/php" "$f" 2>/dev/null; then
      print_warn "Moving existing Sury source out of the way: $f"
      mv -f "$f" "/root/php-install-audit/disabled-apt-sources/$(basename "$f").$(date +%F-%H%M%S).bak"
    fi
  done
  shopt -u nullglob

  # Comment out any Sury php lines in /etc/apt/sources.list (rare)
  if grep -q "packages.sury.org/php" /etc/apt/sources.list 2>/dev/null; then
    print_warn "Commenting out Sury lines in /etc/apt/sources.list"
    cp -a /etc/apt/sources.list "/root/php-install-audit/sources.list.$(date +%F-%H%M%S).bak"
    sed -i 's|^\(deb .*packages\.sury\.org/php.*\)$|# DISABLED: \1|g' /etc/apt/sources.list
  fi

  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y || true
  apt-get install -y apt-transport-https ca-certificates curl wget gnupg2 lsb-release

  write_sury_key_noninteractive

  local codename
  codename="$(. /etc/os-release && echo "${VERSION_CODENAME}")"

  cat > /etc/apt/sources.list.d/php-sury.list <<EOF
deb [signed-by=/etc/apt/keyrings/sury-php.gpg] https://packages.sury.org/php/ ${codename} main
EOF

  apt-get update -y
  print_success "Sury repo ready."
}

apply_fspbx_php84_ini_overrides() {
  print_success "Applying FS PBX PHP 8.4 overrides (FPM + CLI)..."

  cat > /etc/php/8.4/mods-available/fspbx.ini <<'EOF'
; FS PBX overrides
post_max_size = 80M
upload_max_filesize = 80M
memory_limit = 512M
max_input_vars = 8000
session.gc_maxlifetime = 7200
EOF

  ln -sf /etc/php/8.4/mods-available/fspbx.ini /etc/php/8.4/fpm/conf.d/99-fspbx.ini
  ln -sf /etc/php/8.4/mods-available/fspbx.ini /etc/php/8.4/cli/conf.d/99-fspbx.ini
}

ensure_systemd_fspbx_override_php84() {
  if ! has_cmd systemctl; then
    print_warn "systemctl not found; skipping systemd override."
    return 0
  fi

  mkdir -p /etc/systemd/system/php8.4-fpm.service.d
  cat > /etc/systemd/system/php8.4-fpm.service.d/override.conf <<'EOF'
[Service]
RuntimeDirectory=php
RuntimeDirectoryMode=0755
ReadWritePaths=-/etc/freeswitch -/usr/share/freeswitch -/var/lib/freeswitch
EOF

  systemctl daemon-reload
  print_success "Ensured FS PBX systemd override for php8.4-fpm."
}

install_esl_php84_via_installer() {
  local installer="/var/www/fspbx/install/install_esl_extension.sh"

  if [ ! -f "$installer" ]; then
    print_error "ESL installer not found: $installer"
    exit 1
  fi

  chmod +x "$installer" || true

  print_success "Installing ESL via: $installer"
  if ! bash "$installer"; then
    print_error "ESL installer failed."
    exit 1
  fi
}

upgrade_php84_packages() {
  # Patch the existing installation without running the fresh-install setup.
  # CVE-2026-109618 and CVE-2026-109619 are fixed in PHP 8.4.27.
  local minimum_version="8.4.27"
  local ID="" VERSION_ID="" package status version candidate package_rows
  local cli_version fpm_version
  local -a packages=()
  local -a apt_options=(
    -o DPkg::Lock::Timeout=60
    -o Acquire::Retries=2
    -o Acquire::http::Timeout=30
    -o Acquire::https::Timeout=30
    -o Dpkg::Options::=--force-confdef
    -o Dpkg::Options::=--force-confold
  )

  . /etc/os-release
  case "${ID}:${VERSION_ID}" in
    debian:11|debian:12|debian:13) ;;
    *)
      print_error "PHP package upgrade supports Debian 11, 12, and 13. Check PHP security updates manually."
      return 1
      ;;
  esac

  export LC_ALL=C DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=l

  # Use exact installed package names, including extensions with their own
  # version numbering. Do not install another PHP branch or change repositories.
  package_rows="$(dpkg-query -W -f='${db:Status-Status}\t${binary:Package}\t${Version}\n' 'php8.4*' 2>/dev/null || true)"
  while IFS=$'\t' read -r status package version; do
    if [[ "$status" == installed ]]; then
      packages+=("$package")
    fi
  done <<< "$package_rows"

  for package in php8.4-cli php8.4-fpm php8.4-common; do
    status="$(dpkg-query -W -f='${db:Status-Status}' "$package" 2>/dev/null || true)"
    if [[ "$status" != installed ]]; then
      print_error "${package} is not installed. PHP security patching requires an existing PHP 8.4 installation."
      return 1
    fi
  done

  print_success "Refreshing package lists for the Debian ${VERSION_ID} PHP 8.4 update..."
  # A failed unrelated repository must not prevent installing a patched PHP
  # candidate. Keep APT authentication enabled and verify the installed version.
  if ! apt-get "${apt_options[@]}" -o APT::Update::Error-Mode=any update; then
    print_warn "Some package lists could not be refreshed. Checking available PHP packages; APT signature verification remains enabled."
  fi

  for package in php8.4-cli php8.4-fpm php8.4-common; do
    candidate="$(apt-cache policy "$package" | awk '/Candidate:/ {print $2; exit}')"
    if [[ -z "$candidate" || "$candidate" == '(none)' ]] ||
        ! dpkg --compare-versions "${candidate#*:}" ge "$minimum_version"; then
      print_error "${package}: candidate ${candidate:-unavailable} does not provide PHP ${minimum_version}. Check this server's PHP repository."
      return 1
    fi
  done

  print_success "Upgrading installed PHP 8.4 packages (configuration files are preserved)..."
  apt-get "${apt_options[@]}" install -y --only-upgrade --no-install-recommends --no-remove "${packages[@]}"

  for package in php8.4-cli php8.4-fpm php8.4-common; do
    version="$(dpkg-query -W -f='${Version}' "$package")"
    if ! dpkg --compare-versions "${version#*:}" ge "$minimum_version"; then
      print_error "${package} is still ${version}; PHP ${minimum_version} or newer is required."
      return 1
    fi
  done

  # Check the binaries launched after installation, not the PHP process that
  # started app:update (which continues using its original executable).
  cli_version="$(php8.4 -r 'echo PHP_VERSION;')"
  fpm_version="$(php-fpm8.4 -v | awk '/^PHP / {print $2; exit}')"
  if ! dpkg --compare-versions "$cli_version" ge "$minimum_version" ||
      ! dpkg --compare-versions "$fpm_version" ge "$minimum_version"; then
    print_error "The PHP CLI/FPM binaries are not both running version ${minimum_version} or newer."
    return 1
  fi

  php-fpm8.4 -t
  print_success "Restarting php8.4-fpm to load the security update..."
  if has_cmd systemctl; then
    systemctl restart php8.4-fpm
    systemctl is-active --quiet php8.4-fpm
  else
    service php8.4-fpm restart
    service php8.4-fpm status
  fi

  print_success "PHP security update verified: CLI ${cli_version}, FPM ${fpm_version}."
  print_warn "Long-running PHP workers must also restart. app:update handles the standard FS PBX workers."
}

# ---------------- MAIN ----------------
if [[ $# -gt 0 ]]; then
  if [[ $# -eq 1 && "$1" == --upgrade-only ]]; then
    require_root
    upgrade_php84_packages
    exit 0
  fi
  print_error "Usage: bash $0 [--upgrade-only]"
  exit 1
fi

require_root
export DEBIAN_FRONTEND=noninteractive

print_success "Starting PHP 8.4 installation..."

# If already installed, still ensure overrides + ESL are applied (idempotent)
if has_cmd php8.4; then
  print_success "php8.4 already present. Ensuring required packages + config..."
else
  normalize_sury_repo
fi

# Install PHP 8.4 + extensions required by FS PBX
print_success "Installing PHP 8.4 + extensions..."
apt-get install -y --no-install-recommends \
  php8.4 php8.4-cli php8.4-fpm \
  php8.4-pgsql php8.4-sqlite3 php8.4-odbc \
  php8.4-curl php8.4-imap php8.4-xml php8.4-gd php8.4-mbstring php8.4-ldap \
  php8.4-zip \
  php8.4-redis php8.4-igbinary php8.4-inotify \
  imagemagick php8.4-imagick php8.4-dev

# Apply FS PBX overrides
apply_fspbx_php84_ini_overrides

# Systemd override (if needed)
ensure_systemd_fspbx_override_php84

# Restart PHP-FPM
print_success "Restarting php8.4-fpm..."
if has_cmd systemctl; then
  systemctl restart php8.4-fpm
else
  service php8.4-fpm restart
fi

# Set CLI alternatives to 8.4 (non-interactive)
if has_cmd update-alternatives; then
  [ -x /usr/bin/php8.4 ] && update-alternatives --set php /usr/bin/php8.4 >/dev/null 2>&1 || true
  [ -x /usr/bin/phpize8.4 ] && update-alternatives --set phpize /usr/bin/phpize8.4 >/dev/null 2>&1 || true
  [ -x /usr/bin/php-config8.4 ] && update-alternatives --set php-config /usr/bin/php-config8.4 >/dev/null 2>&1 || true
fi

# Install ESL (custom module)
install_esl_php84_via_installer

print_success "PHP 8.4 installation completed successfully."
php8.4 -v | head -n 2 || true
