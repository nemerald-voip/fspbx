#!/bin/bash
set -euo pipefail

# =========================
# FS PBX PHP Upgrade Script
# PHP 8.1 -> PHP 8.4 (Debian Bookworm+)
# - Fully non-interactive
# - Normalizes Sury repo (handles .list + .sources + sources.list)
# - Captures module lists and diffs them
# =========================

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

try_install() {
  local pkgs="$*"
  if DEBIAN_FRONTEND=noninteractive apt-get install -y $pkgs; then
    print_success "Installed: $pkgs"
    return 0
  else
    print_warn "Could not install one or more packages: $pkgs (continuing)"
    return 1
  fi
}

dump_sury_debug() {
  print_warn "---- Sury repo debug: packages.sury.org/php references ----"
  grep -R --line-number -E "packages\.sury\.org/php|signed-by|Signed-By" \
    /etc/apt/sources.list /etc/apt/sources.list.d/* 2>/dev/null || true
  print_warn "---- Keyrings present ----"
  ls -lah /etc/apt/keyrings 2>/dev/null || true
  ls -lah /usr/share/keyrings 2>/dev/null | grep -i sury || true
}

write_sury_key_noninteractive() {
  # Always overwrite key file without prompt using temp + mv
  mkdir -p /etc/apt/keyrings

  local tmp="/etc/apt/keyrings/sury-php.gpg.tmp.$$"
  curl -fsSL https://packages.sury.org/php/apt.gpg \
    | gpg --dearmor --batch --yes -o "$tmp"

  chmod 0644 "$tmp"
  mv -f "$tmp" /etc/apt/keyrings/sury-php.gpg
}

normalize_sury_repo() {
  print_success "Normalizing Sury PHP repo to avoid Signed-By conflicts (handles .list + .sources)..."

  mkdir -p /root/php-upgrade-audit

  # Disable any Sury PHP repo definitions in sources.list.d (both .list and .sources)
  shopt -s nullglob
  for f in /etc/apt/sources.list.d/*.{list,sources}; do
    if grep -q "packages.sury.org/php" "$f" 2>/dev/null; then
      print_warn "Disabling existing Sury source file: $f"
      cp -a "$f" "/root/php-upgrade-audit/$(basename "$f").bak.$(date +%F-%H%M%S)" || true
      mv -f "$f" "${f}.disabled.$(date +%F-%H%M%S)"
    fi
  done
  shopt -u nullglob

  # Comment out any Sury PHP lines in /etc/apt/sources.list (rare)
  if grep -q "packages.sury.org/php" /etc/apt/sources.list 2>/dev/null; then
    print_warn "Commenting out Sury lines in /etc/apt/sources.list"
    cp -a /etc/apt/sources.list "/root/php-upgrade-audit/sources.list.bak.$(date +%F-%H%M%S)" || true
    sed -i 's|^\(deb .*packages\.sury\.org/php.*\)$|# DISABLED: \1|g' /etc/apt/sources.list
  fi

  # Ensure tools exist (now that sources are cleaned, apt update should be readable)
  # Note: apt-get update can still fail if *other* repos are broken; that’s separate.
  DEBIAN_FRONTEND=noninteractive apt-get update || true
  DEBIAN_FRONTEND=noninteractive apt-get install -y apt-transport-https ca-certificates curl wget gnupg2 lsb-release

  # Write key without any prompt
  write_sury_key_noninteractive

  # Write ONE canonical Sury repo file
  local codename
  codename="$(. /etc/os-release && echo "${VERSION_CODENAME}")"
  cat > /etc/apt/sources.list.d/php-sury.list <<EOF
deb [signed-by=/etc/apt/keyrings/sury-php.gpg] https://packages.sury.org/php/ ${codename} main
EOF

  # Update must succeed; if not, dump debug and exit
  if ! DEBIAN_FRONTEND=noninteractive apt-get update; then
    print_error "apt-get update failed after normalizing Sury repo."
    dump_sury_debug
    exit 1
  fi

  print_success "Sury repo normalized and apt update is healthy."
}

capture_modules() {
  local label="$1"
  local bin="$2"
  local outdir="/root/php-upgrade-audit"
  mkdir -p "$outdir"

  if has_cmd "$bin"; then
    "$bin" -m | sort > "${outdir}/${label}_modules.txt"
    print_success "Captured ${bin} module list."
    return 0
  else
    print_warn "${bin} not found; skipping ${label} module capture."
    return 1
  fi
}

diff_modules() {
  local outdir="/root/php-upgrade-audit"
  if [ -f "${outdir}/php81_modules.txt" ] && [ -f "${outdir}/php84_modules.txt" ]; then
    diff -u "${outdir}/php81_modules.txt" "${outdir}/php84_modules.txt" \
      | tee "${outdir}/php81_vs_php84_module_diff.txt" >/dev/null || true

    print_success "Module diff saved to: ${outdir}/php81_vs_php84_module_diff.txt"
    print_success "Modules present in 8.1 but missing in 8.4 (if any):"
    comm -23 "${outdir}/php81_modules.txt" "${outdir}/php84_modules.txt" || true
  else
    print_warn "Missing module lists; diff skipped."
  fi
}

ensure_php84_module() {
  local mod="$1"
  local pkg="$2"

  # Normalize php -m output: strip brackets, trim whitespace, lowercase
  local has_mod
  has_mod() {
    php8.4 -m 2>/dev/null \
      | sed -e 's/\r$//' \
      | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' \
      | grep -vi '^\[.*\]$' \
      | tr '[:upper:]' '[:lower:]' \
      | grep -qx "$(echo "$mod" | tr '[:upper:]' '[:lower:]')"
  }

  if ! has_mod; then
    print_warn "PHP 8.4 module missing: ${mod}. Installing ${pkg}..."
    DEBIAN_FRONTEND=noninteractive apt-get install -y "${pkg}" || true
    systemctl restart php8.4-fpm >/dev/null 2>&1 || service php8.4-fpm restart >/dev/null 2>&1 || true
  fi

  if has_mod; then
    print_success "PHP 8.4 module present: ${mod}"
  else
    print_error "Still missing PHP 8.4 module: ${mod} after installing ${pkg}"
    print_warn "Debug: php8.4 -m | grep -i ${mod}:"
    php8.4 -m | grep -i "${mod}" || true
    exit 1
  fi
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

  # Verify using the TARGET binary only, with retry (handles transient state / path switching)
  local tries=10
  local i=1
  while [ $i -le $tries ]; do
    if /usr/bin/php8.4 -m 2>/dev/null | grep -qi '^esl$'; then
      print_success "✅ ESL loaded in PHP 8.4 after installer."
      return 0
    fi
    sleep 0.2
    i=$((i+1))
  done

  # If we got here, dump useful debug and fail hard
  print_error "❌ ESL not detected in php8.4 after ${tries} checks."

  print_warn "Debug: which php / php8.4"
  command -v php || true
  command -v php8.4 || true
  ls -lah /etc/php/8.4/cli/conf.d/30-esl.ini /etc/php/8.4/fpm/conf.d/30-esl.ini 2>/dev/null || true

  print_warn "Debug: php8.4 extension_dir"
  /usr/bin/php8.4 -r 'echo ini_get("extension_dir"), PHP_EOL;' || true

  print_warn "Debug: does the module file exist?"
  local ext_dir
  ext_dir="$(/usr/bin/php8.4 -r 'echo ini_get("extension_dir");' 2>/dev/null || true)"
  [ -n "$ext_dir" ] && ls -lah "$ext_dir/esl.so" 2>/dev/null || true

  print_warn "Debug: try loading explicitly (CLI)"
  [ -n "$ext_dir" ] && /usr/bin/php8.4 -n -d "extension_dir=$ext_dir" -d "extension=esl.so" -m 2>/dev/null | grep -i '^esl$' || true

  print_warn "Debug: last 60 lines of php8.4-fpm journal"
  if has_cmd systemctl; then
    journalctl -u php8.4-fpm -n 60 --no-pager 2>/dev/null || true
  fi

  exit 1
}

apply_fspbx_php84_ini_overrides() {
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

  systemctl restart php8.4-fpm 2>/dev/null || service php8.4-fpm restart
}

switch_nginx_to_php84() {
  local php84_sock="/run/php/php8.4-fpm.sock"
  local changed=0

  # Guardrails
  if [ ! -S "$php84_sock" ]; then
    print_warn "php8.4-fpm socket not found at $php84_sock yet. Ensuring php8.4-fpm is running..."
    systemctl restart php8.4-fpm >/dev/null 2>&1 || service php8.4-fpm restart >/dev/null 2>&1 || true
  fi

  if [ ! -S "$php84_sock" ]; then
    print_error "php8.4-fpm socket still missing at $php84_sock. Cannot safely update nginx."
    print_warn "Debug: ls -lah /run/php/"
    ls -lah /run/php/ || true
    exit 1
  fi

  print_success "Updating nginx fastcgi_pass to PHP 8.4 socket: $php84_sock"

  # Candidate nginx config roots
  local paths=(
    "/etc/nginx/sites-available"
    "/etc/nginx/sites-enabled"
    "/etc/nginx/conf.d"
    "/etc/nginx/nginx.conf"
  )

  # Update common patterns:
  # - unix:/run/php/php8.1-fpm.sock
  # - /run/php/php8.1-fpm.sock
  # - php8.1-fpm.sock (rare)
  # - 127.0.0.1:9000 (optional, only if you want to force unix sockets)
  for p in "${paths[@]}"; do
    if [ -d "$p" ]; then
      while IFS= read -r -d '' f; do
        if grep -qE 'fastcgi_pass\s+unix:\/run\/php\/php8\.1-fpm\.sock|fastcgi_pass\s+\/run\/php\/php8\.1-fpm\.sock|php8\.1-fpm\.sock' "$f"; then
          cp -a "$f" "/root/php-upgrade-audit/$(basename "$f").nginx.bak.$(date +%F-%H%M%S)" || true
          sed -i \
            -e 's|fastcgi_pass\s\+unix:/run/php/php8\.1-fpm\.sock;|fastcgi_pass unix:/run/php/php8.4-fpm.sock;|g' \
            -e 's|fastcgi_pass\s\+/run/php/php8\.1-fpm\.sock;|fastcgi_pass unix:/run/php/php8.4-fpm.sock;|g' \
            -e 's|php8\.1-fpm\.sock|php8.4-fpm.sock|g' \
            "$f"
          changed=1
        fi
      done < <(find "$p" -maxdepth 2 -type f -name '*.conf' -print0 2>/dev/null || true)
    elif [ -f "$p" ]; then
      if grep -qE 'php8\.1-fpm\.sock' "$p"; then
        cp -a "$p" "/root/php-upgrade-audit/nginx.conf.bak.$(date +%F-%H%M%S)" || true
        sed -i 's|php8\.1-fpm\.sock|php8.4-fpm.sock|g' "$p"
        changed=1
      fi
    fi
  done

  if [ "$changed" -eq 0 ]; then
    print_warn "No nginx fastcgi_pass references to php8.1 found. (Maybe you already use php8.4, or use TCP upstreams.)"
  fi

  # Validate nginx config before reload
  if ! nginx -t; then
    print_error "nginx -t failed after edits. Restoring may be required."
    exit 1
  fi

  # Reload nginx
  if has_cmd systemctl; then
    systemctl reload nginx || systemctl restart nginx
  else
    service nginx reload || service nginx restart
  fi

  print_success "nginx now points to php8.4-fpm."
}



# ================= MAIN =================
require_root

print_success "Starting PHP 8.1 -> 8.4 upgrade..."

# Capture 8.1 modules before changes
capture_modules "php81" "php8.1" || true

# Normalize Sury repo first (prevents Signed-By conflicts and interactive prompts)
normalize_sury_repo

# Upgrade system packages (non-interactive)
DEBIAN_FRONTEND=noninteractive apt-get upgrade -y
print_success "System packages upgraded."

# Install PHP 8.4 + extensions equivalent to your FS PBX 8.1 list
print_success "Installing PHP 8.4 + extensions..."
try_install php8.4 php8.4-cli php8.4-dev php8.4-fpm
try_install php8.4-pgsql php8.4-sqlite3 php8.4-odbc php8.4-curl php8.4-xml php8.4-gd php8.4-mbstring php8.4-ldap
try_install php8.4-zip

# Optional / may not exist depending on repo build
try_install php8.4-imap
try_install imagemagick php8.4-imagick
try_install php-redis

# Restart php-fpm 8.4
if has_cmd systemctl; then
  systemctl restart php8.4-fpm
else
  service php8.4-fpm restart
fi
print_success "php8.4-fpm restarted."

# Switch CLI alternatives to 8.4 (non-interactive)
if has_cmd update-alternatives; then
  [ -x /usr/bin/php8.4 ] && update-alternatives --set php /usr/bin/php8.4 >/dev/null 2>&1 || true
  [ -x /usr/bin/phpize8.4 ] && update-alternatives --set phpize /usr/bin/phpize8.4 >/dev/null 2>&1 || true
  [ -x /usr/bin/php-config8.4 ] && update-alternatives --set php-config /usr/bin/php-config8.4 >/dev/null 2>&1 || true
  print_success "update-alternatives set to PHP 8.4 where available."
fi

# Install ESL (hard-fail if missing/broken)
install_esl_php84_via_installer

# Prefer version-specific packages (best reliability)
ensure_php84_module "redis"     "php8.4-redis"
ensure_php84_module "igbinary"  "php8.4-igbinary"
ensure_php84_module "inotify"   "php8.4-inotify"

apply_fspbx_php84_ini_overrides

switch_nginx_to_php84

# Capture 8.4 modules + diff
capture_modules "php84" "php8.4" || true
diff_modules

print_success "PHP default version now:"
php -v || true

print_success "All tasks completed successfully!"
