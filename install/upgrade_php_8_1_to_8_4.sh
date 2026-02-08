#!/bin/bash
set -euo pipefail

# =========================
# FS PBX PHP Upgrade Script
# PHP 8.1 -> PHP 8.4 (Debian Bookworm+)
# - Non-interactive Dpkg (Fixes dhcpcd/cloud-init crashes)
# - Fixes NodeSource SHA1 key issues
# - Normalizes Sury repo
# =========================

GREEN="\e[32m"; RED="\e[31m"; YELLOW="\e[33m"; RESET="\e[0m"
print_success(){ echo -e "${GREEN}$1${RESET}"; }
print_error(){ echo -e "${RED}$1${RESET}"; }
print_warn(){ echo -e "${YELLOW}$1${RESET}"; }
has_cmd(){ command -v "$1" >/dev/null 2>&1; }

# Dpkg options to prevent interactive prompts (Fixes the dhcpcd crash)
APT_OPTS="-y -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold"

require_root() {
  if [ "${EUID:-$(id -u)}" -ne 0 ]; then
    print_error "Please run as root: sudo bash $0"
    exit 1
  fi
}

# Fix Broken Dpkg State immediately
fix_dpkg_state() {
  print_warn "Ensuring dpkg is in a clean state..."
  # If a previous run failed, this unlocks it
  DEBIAN_FRONTEND=noninteractive apt-get $APT_OPTS install --fix-broken || true
  DEBIAN_FRONTEND=noninteractive dpkg --configure -a || true
}

# Fix NodeSource SHA1 Error (2026 Policy Update)
fix_nodesource() {
  print_warn "Checking NodeSource GPG keys (fixing SHA1 rejection)..."
  
  # If the specific error exists or we just want to be safe, refresh the key
  # Remove old deprecated key locations
  rm -f /usr/share/keyrings/nodesource.gpg
  rm -f /etc/apt/keyrings/nodesource.gpg
  
  # Ensure dir exists
  mkdir -p /etc/apt/keyrings

  # Download fresh key (NodeSource updated their keys to be SHA256 compatible)
  curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg --yes

  # Update source list to use signed-by
  if [ -f /etc/apt/sources.list.d/nodesource.list ]; then
     echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_20.x nodistro main" > /etc/apt/sources.list.d/nodesource.list
     print_success "NodeSource key refreshed and source list updated."
  fi
}

try_install() {
  local pkgs="$*"
  # Added $APT_OPTS to prevent config prompts
  if DEBIAN_FRONTEND=noninteractive apt-get $APT_OPTS install $pkgs; then
    print_success "Installed: $pkgs"
    return 0
  else
    print_warn "Could not install one or more packages: $pkgs (continuing)"
    return 1
  fi
}

write_sury_key_noninteractive() {
  mkdir -p /etc/apt/keyrings
  local tmp="/etc/apt/keyrings/sury-php.gpg.tmp.$$"
  curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor --batch --yes -o "$tmp"
  chmod 0644 "$tmp"
  mv -f "$tmp" /etc/apt/keyrings/sury-php.gpg
}

normalize_sury_repo() {
  print_success "Normalizing Sury PHP repo..."

  mkdir -p /root/php-upgrade-audit

  shopt -s nullglob
  for f in /etc/apt/sources.list.d/*.{list,sources}; do
    if grep -q "packages.sury.org/php" "$f" 2>/dev/null; then
      cp -a "$f" "/root/php-upgrade-audit/$(basename "$f").bak.$(date +%F-%H%M%S)" || true
      mv -f "$f" "${f}.disabled.$(date +%F-%H%M%S)"
    fi
  done
  shopt -u nullglob

  if grep -q "packages.sury.org/php" /etc/apt/sources.list 2>/dev/null; then
    sed -i 's|^\(deb .*packages\.sury\.org/php.*\)$|# DISABLED: \1|g' /etc/apt/sources.list
  fi

  # Ensure basic tools
  DEBIAN_FRONTEND=noninteractive apt-get update || true
  DEBIAN_FRONTEND=noninteractive apt-get $APT_OPTS install apt-transport-https ca-certificates curl wget gnupg2 lsb-release

  write_sury_key_noninteractive

  local codename
  codename="$(. /etc/os-release && echo "${VERSION_CODENAME}")"
  cat > /etc/apt/sources.list.d/php-sury.list <<EOF
deb [signed-by=/etc/apt/keyrings/sury-php.gpg] https://packages.sury.org/php/ ${codename} main
EOF

  if ! DEBIAN_FRONTEND=noninteractive apt-get update; then
    print_error "apt-get update failed. Check internet connection or repo status."
    exit 1
  fi
}

capture_modules() {
  local label="$1"
  local bin="$2"
  local outdir="/root/php-upgrade-audit"
  mkdir -p "$outdir"

  if has_cmd "$bin"; then
    "$bin" -m | sort > "${outdir}/${label}_modules.txt"
    return 0
  fi
  return 1
}

diff_modules() {
  local outdir="/root/php-upgrade-audit"
  if [ -f "${outdir}/php81_modules.txt" ] && [ -f "${outdir}/php84_modules.txt" ]; then
    diff -u "${outdir}/php81_modules.txt" "${outdir}/php84_modules.txt" > "${outdir}/diff.txt" || true
    print_success "Module diff saved to ${outdir}/diff.txt"
  fi
}

ensure_php84_module() {
  local mod="$1"
  local pkg="$2"

  local has_mod
  has_mod() {
    php8.4 -m 2>/dev/null | grep -qi "$mod"
  }

  if ! has_mod; then
    print_warn "Installing missing module ${mod}..."
    DEBIAN_FRONTEND=noninteractive apt-get $APT_OPTS install "${pkg}" || true
  fi
}

install_esl_php84_via_installer() {
  local installer="/var/www/fspbx/install/install_esl_extension.sh"

  if [ ! -f "$installer" ]; then
    print_error "ESL installer not found: $installer"
    exit 1
  fi
  chmod +x "$installer" || true
  
  print_success "Running ESL Installer..."
  bash "$installer" || true

  if /usr/bin/php8.4 -m 2>/dev/null | grep -qi '^esl$'; then
    print_success "✅ ESL loaded in PHP 8.4."
    return 0
  fi
  
  print_error "❌ ESL failed to load. Please check /var/www/fspbx/install/install_esl_extension.sh manually."
  # We do not exit 1 here to allow the rest of the update to finish, but warn heavily
}

apply_fspbx_php84_ini_overrides() {
  cat > /etc/php/8.4/mods-available/fspbx.ini <<'EOF'
post_max_size = 80M
upload_max_filesize = 80M
memory_limit = 512M
max_input_vars = 8000
session.gc_maxlifetime = 7200
EOF

  ln -sf /etc/php/8.4/mods-available/fspbx.ini /etc/php/8.4/fpm/conf.d/99-fspbx.ini
  ln -sf /etc/php/8.4/mods-available/fspbx.ini /etc/php/8.4/cli/conf.d/99-fspbx.ini
}

switch_nginx_to_php84() {
  local php84_sock="/run/php/php8.4-fpm.sock"
  
  # Ensure service is up
  systemctl restart php8.4-fpm || true
  
  if [ ! -S "$php84_sock" ]; then
    print_error "PHP 8.4 socket not found. Skipping Nginx update."
    return
  fi

  print_success "Updating Nginx configs to PHP 8.4..."
  grep -rl "php8.1-fpm.sock" /etc/nginx/sites-available/ | xargs sed -i 's/php8.1-fpm.sock/php8.4-fpm.sock/g' || true
  
  if nginx -t; then
    systemctl reload nginx
    print_success "Nginx reloaded with PHP 8.4."
  else
    print_error "Nginx config check failed. Please check manually."
  fi
}

# ================= MAIN =================
require_root

print_success "Starting PHP Upgrade (Safe Mode)..."

fix_dpkg_state
fix_nodesource

capture_modules "php81" "php8.1" || true

normalize_sury_repo

# Upgrade system packages (Fixing prompts)
DEBIAN_FRONTEND=noninteractive apt-get $APT_OPTS upgrade 
print_success "System packages upgraded."

# Install PHP 8.4
try_install php8.4 php8.4-cli php8.4-dev php8.4-fpm \
  php8.4-pgsql php8.4-sqlite3 php8.4-odbc php8.4-curl \
  php8.4-xml php8.4-gd php8.4-mbstring php8.4-ldap \
  php8.4-zip php8.4-imap imagemagick php8.4-imagick php-redis

if has_cmd update-alternatives; then
  update-alternatives --set php /usr/bin/php8.4 >/dev/null 2>&1 || true
fi

install_esl_php84_via_installer

ensure_php84_module "redis" "php8.4-redis"
ensure_php84_module "igbinary" "php8.4-igbinary"

apply_fspbx_php84_ini_overrides
switch_nginx_to_php84

capture_modules "php84" "php8.4" || true
diff_modules

print_success "Upgrade Complete. Current Version:"
php -v