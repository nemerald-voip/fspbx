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

ensure_systemd_runtime_dir_override_php84() {
  # You had this for php8.1-fpm; keep it but fix to 8.4
  if ! has_cmd systemctl; then
    print_warn "systemctl not found; skipping systemd override."
    return 0
  fi

  mkdir -p /etc/systemd/system/php8.4-fpm.service.d
  cat > /etc/systemd/system/php8.4-fpm.service.d/override.conf <<'EOF'
[Service]
RuntimeDirectory=php
RuntimeDirectoryMode=0755
EOF

  systemctl daemon-reload
  print_success "Ensured systemd RuntimeDirectory override for php8.4-fpm."
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

# ---------------- MAIN ----------------
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
ensure_systemd_runtime_dir_override_php84

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
