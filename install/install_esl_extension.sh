#!/bin/sh
set -eu

print_success() { printf "\033[32m%s\033[0m\n" "$1"; }
print_error()   { printf "\033[31m%s\033[0m\n" "$1"; }
print_warn()    { printf "\033[33m%s\033[0m\n" "$1"; }

# Must be root
if [ "$(id -u)" -ne 0 ]; then
  print_error "Run as root: sudo sh $0"
  exit 1
fi

PHP_BIN="/usr/bin/php8.4"
FPM_SERVICE="php8.4-fpm"

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
ESL_SO_SRC="${SCRIPT_DIR}/esl-8.4.so"

if [ ! -x "$PHP_BIN" ]; then
  print_error "php8.4 not found at $PHP_BIN"
  exit 1
fi

if [ ! -f "$ESL_SO_SRC" ]; then
  print_error "Missing ESL module: $ESL_SO_SRC"
  print_error "Place your compiled module at: /var/www/fspbx/install/esl-8.4.so"
  exit 1
fi

EXTENSION_DIR="$("$PHP_BIN" -r 'echo ini_get("extension_dir");')"
if [ -z "$EXTENSION_DIR" ]; then
  print_error "Failed to detect PHP 8.4 extension_dir."
  exit 1
fi
print_success "PHP 8.4 extension_dir: $EXTENSION_DIR"

# Install module (overwrite)
install -m 0644 -o root -g root "$ESL_SO_SRC" "$EXTENSION_DIR/esl.so"
print_success "Installed: $EXTENSION_DIR/esl.so"

# Enable extension for CLI + FPM
CLI_INI_DIR="/etc/php/8.4/cli/conf.d"
FPM_INI_DIR="/etc/php/8.4/fpm/conf.d"

mkdir -p "$CLI_INI_DIR" "$FPM_INI_DIR"

echo "extension=esl.so" > "$CLI_INI_DIR/30-esl.ini"
echo "extension=esl.so" > "$FPM_INI_DIR/30-esl.ini"

print_success "Enabled ESL in:"
print_success "  $CLI_INI_DIR/30-esl.ini"
print_success "  $FPM_INI_DIR/30-esl.ini"

# Restart FPM
if command -v systemctl >/dev/null 2>&1; then
  systemctl restart "$FPM_SERVICE"
else
  service "$FPM_SERVICE" restart
fi
print_success "Restarted: $FPM_SERVICE"

# Verify module loads in CLI
if "$PHP_BIN" -m | grep -qi '^esl$'; then
  print_success "✅ ESL loaded in PHP 8.4 (CLI)."
else
  print_error "❌ ESL did not load in PHP 8.4."
  print_warn "Check dependencies with:"
  print_warn "  ldd \"$EXTENSION_DIR/esl.so\""
  exit 1
fi

print_success "🎉 ESL installation completed successfully for PHP 8.4."
