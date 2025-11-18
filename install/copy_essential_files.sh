#!/bin/bash
if [ -z "$BASH_VERSION" ]; then exec bash "$0" "$@"; fi
set -Eeuo pipefail

# === COLOR HELPERS ===
print_success() { echo -e "\e[32m$1\e[0m"; }
print_error()   { echo -e "\e[31m$1\e[0m"; }
print_info()    { echo -e "\e[36m$1\e[0m"; }
print_warn()    { echo -e "\e[33m$1\e[0m"; }

trap 'print_error "Error on line $LINENO. Exiting."; exit 1' ERR

# === REQUIRE ROOT ===
if [[ $EUID -ne 0 ]]; then
  print_error "Please run this script as root."
  exit 1
fi

# === FILES / DIRECTORIES TO SYNC (absolute paths) ===
# Trailing slash on a directory copies its CONTENTS into the receiver path.
FILES=(
  "/var/www/fspbx/storage/app/public/android-chrome-192x192.png"
  "/var/www/fspbx/storage/app/public/android-chrome-384x384.png"
  "/var/www/fspbx/storage/app/public/apple-touch-icon.png"
  "/var/www/fspbx/storage/app/public/browserconfig.xml"
  "/var/www/fspbx/storage/app/public/favicon.ico"
  "/var/www/fspbx/storage/app/public/logo.png"
  "/var/www/fspbx/storage/app/public/mstile-150x150.png"
  "/var/www/fspbx/storage/app/public/safari-pinned-tab.svg"
  "/var/www/fspbx/storage/app/public/site.webmanifest"
  "/etc/nginx/sites-available/fspbx.conf"
  "/etc/nginx/ssl/fullchain.pem"
  "/etc/nginx/ssl/private/privkey.pem"
  "/etc/iptables/rules.v4"
  "/etc/freeswitch/tls/"   # directory → copy its contents
)

# === PROMPTS ===
print_info "=== FS PBX File Sync Helper ==="
read -r -p "Remote host/IP: " REMOTE_HOST
read -r -p "SSH port [22]: " SSH_PORT; SSH_PORT=${SSH_PORT:-22}
read -r -p "SSH user [root]: " SSH_USER; SSH_USER=${SSH_USER:-root}

echo ""
print_info "Direction:"
echo "  1) PUSH  (this server → remote)"
echo "  2) PULL  (remote → this server)"
read -r -p "Choose 1 or 2 [1]: " DIR_CHOICE; DIR_CHOICE=${DIR_CHOICE:-1}

read -r -p "Perform a DRY RUN first? [Y/n]: " DRY; DRY=${DRY:-Y}
read -r -p "Backup overwritten files on receiver? [Y/n]: " DO_BACKUP; DO_BACKUP=${DO_BACKUP:-Y}

# Optional service handling
read -r -p "After sync: test Nginx config and reload if valid? [y/N]: " RELOAD_NGINX; RELOAD_NGINX=${RELOAD_NGINX:-N}
echo ""

# === PRECHECKS ===
LOG="/var/log/fs-file-sync-$(date +%Y%m%d-%H%M%S).log"
BACKUP_TAG="$(date +%Y%m%d-%H%M%S)"
print_info "Logging to $LOG"

# SSH check
print_info "Checking SSH connectivity to $SSH_USER@$REMOTE_HOST:$SSH_PORT ..."
if ! ssh -o BatchMode=yes -p "$SSH_PORT" "$SSH_USER@$REMOTE_HOST" "echo ok" >/dev/null 2>&1; then
  print_warn "Passwordless SSH may not be configured or key agent not loaded. You'll be prompted if needed."
  ssh -p "$SSH_PORT" -o ConnectTimeout=10 "$SSH_USER@$REMOTE_HOST" "echo ok" >/dev/null
fi
print_success "SSH connectivity OK."

# Ensure rsync exists on both ends
if ! command -v rsync >/dev/null 2>&1; then
  print_error "rsync not found locally. Please install it (apt-get install -y rsync) and rerun."
  exit 1
fi

print_info "Checking rsync on remote..."
if ! ssh -p "$SSH_PORT" "$SSH_USER@$REMOTE_HOST" "command -v rsync" >/dev/null 2>&1; then
  print_warn "rsync missing on remote. Attempting to install via apt..."
  ssh -t -p "$SSH_PORT" "$SSH_USER@$REMOTE_HOST" "apt-get update && apt-get install -y rsync"
fi
print_success "rsync present on remote."

# === BUILD FILE LIST FOR RSYNC --relative ===
LIST_FILE="$(mktemp)"
cleanup() { rm -f "$LIST_FILE"; }
trap cleanup EXIT

for p in "${FILES[@]}"; do
  echo "$p" >> "$LIST_FILE"
done

# === RSYNC ARGS ===
# -a preserves perms/owner/group; --numeric-ids keeps exact UID/GID values.
RSYNC_BASE=(-aR --numeric-ids --human-readable --info=stats2,progress2 --mkpath --no-inc-recursive)
RSYNC_BASE+=(--ignore-missing-args)
[[ "$DRY" =~ ^[Yy]$ ]] && RSYNC_BASE+=(-n -v)
RSYNC_BASE+=(-e "ssh -p $SSH_PORT")

# Backups on receiver
if [[ "$DO_BACKUP" =~ ^[Yy]$ ]]; then
  BACKUP_DIR="/root/fs-sync-backups/$BACKUP_TAG"
  RSYNC_BASE+=(--backup --backup-dir="$BACKUP_DIR")
  if [[ "$DIR_CHOICE" == "1" ]]; then
    print_info "Receiver backups will be saved on REMOTE at: $BACKUP_DIR"
  else
    print_info "Receiver backups will be saved LOCALLY at: $BACKUP_DIR"
  fi
fi

# === RUN RSYNC ===
if [[ "$DIR_CHOICE" == "1" ]]; then
  # PUSH: local -> remote:/ (preserve absolute paths with --relative)
  print_info "PUSH: syncing from THIS server to $SSH_USER@$REMOTE_HOST ..."
  set +e
  rsync "${RSYNC_BASE[@]}" --files-from="$LIST_FILE" / "$SSH_USER@$REMOTE_HOST:/" 2>&1 | tee -a "$LOG"
  RC=${PIPESTATUS[0]}
  set -e
else
  # PULL: remote -> local /
  print_info "PULL: syncing from $SSH_USER@$REMOTE_HOST to THIS server ..."
  set +e
  rsync "${RSYNC_BASE[@]}" --files-from="$LIST_FILE" "$SSH_USER@$REMOTE_HOST:/" / 2>&1 | tee -a "$LOG"
  RC=${PIPESTATUS[0]}
  set -e
fi

if [[ $RC -ne 0 ]]; then
  print_error "rsync reported an error (code $RC). See $LOG for details."
  exit $RC
fi

print_success "Rsync step completed."

# === OPTIONAL: NGINX TEST/RELOAD ON RECEIVER ===
nginx_test_and_reload() {
  local SIDE=$1
  local TEST_CMD='command -v nginx >/dev/null 2>&1 && nginx -t'
  local RELOAD_CMD='systemctl reload nginx'
  if [[ "$SIDE" == "remote" ]]; then
    if ssh -p "$SSH_PORT" "$SSH_USER@$REMOTE_HOST" "$TEST_CMD"; then
      print_success "nginx -t passed on REMOTE."
      ssh -p "$SSH_PORT" "$SSH_USER@$REMOTE_HOST" "$RELOAD_CMD" && print_success "Nginx reloaded on REMOTE." || print_warn "Could not reload Nginx on REMOTE."
    else
      print_error "nginx -t FAILED on REMOTE. Check remote nginx config and logs."
    fi
  else
    if bash -lc "$TEST_CMD"; then
      print_success "nginx -t passed locally."
      bash -lc "$RELOAD_CMD" && print_success "Nginx reloaded locally." || print_warn "Could not reload Nginx locally."
    else
      print_error "nginx -t FAILED locally. Check nginx config and logs."
    fi
  fi
}

if [[ "$RELOAD_NGINX" =~ ^[Yy]$ && ! "$DRY" =~ ^[Yy]$ ]]; then
  if [[ "$DIR_CHOICE" == "1" ]]; then nginx_test_and_reload "remote"; else nginx_test_and_reload "local"; fi
fi

print_info "--------------------------------------------------"
print_success "Sync complete."
print_info "Log: $LOG"
if [[ "$DO_BACKUP" =~ ^[Yy]$ ]]; then
  print_info "Backups of overwritten files are in: ${BACKUP_DIR-<on receiver>}"
fi
print_info "Done."
