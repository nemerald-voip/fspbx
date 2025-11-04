#!/bin/bash
# Syncthing dual-server installer & folder pairer (Debian 12/13)
# - Installs Syncthing locally and on a remote host over SSH
# - Creates/normalizes config for www-data under /home/www-data/.local/state/syncthing
# - Adds each server as a device to the other
# - Creates the requested folders on both servers and shares them mutually
# - Shows progress so long pauses don’t look like a hang

if [ -z "$BASH_VERSION" ]; then exec bash "$0" "$@"; fi
set -euo pipefail

# ========= Pretty output =========
green(){ echo -e "\033[32m$1\033[0m"; }
red(){ echo -e "\033[31m$1\033[0m"; }
info(){ echo -e "\033[36m$1\033[0m"; }
warn(){ echo -e "\033[33m$1\033[0m"; }
die(){ red "$1"; exit 1; }

# ========= Spinner =========
spin_start(){
  _SPIN_CHARS='|/-\'; _SPIN_I=0
  _SPIN_MSG="$1"
  ( while :; do
      printf "\r%s %s" "${_SPIN_CHARS:_SPIN_I++%4:1}" "$_SPIN_MSG"
      sleep 0.15
    done ) &
  _SPIN_PID=$!
  disown $_SPIN_PID || true
}
spin_stop(){
  if [[ -n "${_SPIN_PID:-}" ]]; then
    kill "$_SPIN_PID" >/dev/null 2>&1 || true
    wait "$_SPIN_PID" >/dev/null 2>&1 || true
    unset _SPIN_PID
  fi
  printf "\r"
}

# ========= Prompt for peer =========
prompt_peer(){
  local default_user="root"
  local input peer_user peer_host
  echo
  info "Enter the remote server (formats accepted: 'user@1.2.3.4' or just '1.2.3.4')."
  read -r -p "Remote host [user@ip or ip]: " input
  if [[ -z "${input}" ]]; then
    read -r -p "Remote host is required. Try again: " input
    [[ -z "${input}" ]] && die "Remote host not provided."
  fi
  if [[ "$input" == *"@"* ]]; then
    peer_user="${input%@*}"
    peer_host="${input#*@}"
  else
    peer_user="${default_user}"
    peer_host="${input}"
  fi
  [[ -z "$peer_host" ]] && die "Invalid remote host."
  PEER_USER="$peer_user"
  PEER_HOST="$peer_host"
  PEER_ADDR="${PEER_USER}@${PEER_HOST}"
}

# ========= Fast connectivity checks =========
check_connectivity_fast(){
  info "Quick connectivity checks to $PEER_ADDR ..."
  if command -v ping >/dev/null 2>&1; then
    if ! ping -c 2 -W 1 "$PEER_HOST" >/dev/null 2>&1; then
      warn "Ping failed or host unreachable: $PEER_HOST"
      read -r -p "Continue anyway? [y/N] " ans
      [[ "${ans,,}" == "y" ]] || die "Aborting."
    fi
  fi
  if ! timeout 3 ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=2 -o NumberOfPasswordPrompts=0 "$PEER_ADDR" "exit" >/dev/null 2>&1; then
    warn "TCP/22 not reachable or SSH banner not available quickly on $PEER_HOST."
    read -r -p "Continue anyway? [y/N] " ans
    [[ "${ans,,}" == "y" ]] || die "Aborting."
  fi
  if ! ssh -o BatchMode=yes -o ConnectTimeout=3 -o StrictHostKeyChecking=accept-new "$PEER_ADDR" "echo ok" >/dev/null 2>&1; then
    red "SSH key-based auth failed or timed out for $PEER_ADDR."
    echo "Make sure passwordless SSH works:  ssh-copy-id $PEER_ADDR"
    die "Aborting."
  fi
  green "Connectivity looks good."
}

# ========= Constants =========
APT_KEYRING_DIR="/etc/apt/keyrings"
APT_KEYRING="${APT_KEYRING_DIR}/syncthing-archive-keyring.gpg"
APT_LIST="/etc/apt/sources.list.d/syncthing.list"
SYNCTHING_BIN="/usr/bin/syncthing"
SUPERVISOR_CONF="/etc/supervisor/conf.d/syncthing.conf"
ST_USER="www-data"
ST_HOME="/home/${ST_USER}"
ST_STATE_DIR="${ST_HOME}/.local/state/syncthing"
ST_CONFIG_XML="${ST_STATE_DIR}/config.xml"
API="http://127.0.0.1:8384/rest"
REMOTE_API="http://127.0.0.1:8384/rest"   # remote calls are tunneled via ssh
ST_ADDR_FROM="<address>127.0.0.1:8384</address>"
ST_ADDR_TO="<address>[::]:8384</address>"

# Folders + rescan (seconds)
SYNC_FOLDERS=(
  "/usr/share/freeswitch/sounds/music|3600"
  "/var/cache/fusionpbx|300"
  "/var/lib/freeswitch/recordings|3600"
  "/var/lib/freeswitch/storage|3600"
  "/var/www/fspbx/public/resources/templates/provision|3600"
)

# ========= Helpers =========
require_root(){ [[ $EUID -eq 0 ]] || die "Run as root (sudo)."; }
ensure_pkg(){
  [[ $# -ge 1 && -n "${1:-}" ]] || die "ensure_pkg: missing package name"
  dpkg -s "$1" >/dev/null 2>&1 || { apt-get update -y; apt-get install -y "$1"; }
}
xml_replace(){ [[ -f "$1" ]] && grep -Fq "$2" "$1" && sed -i "s#${2//\//\\/}#${3//\//\\/}#" "$1" || true; }
ensure_supervisor(){ systemctl enable supervisor >/dev/null 2>&1 || true; systemctl start supervisor >/dev/null 2>&1 || true; }
write_supervisor(){
  [[ -f "$SUPERVISOR_CONF" ]] || cat > "$SUPERVISOR_CONF" <<EOF
[program:syncthing]
autorestart=true
directory=${ST_HOME}/
user=${ST_USER}
command=${SYNCTHING_BIN} serve --no-browser
environment=STNORESTART="1",HOME="${ST_HOME}"
stdout_logfile=/var/log/supervisor/syncthing-stdout.log
stderr_logfile=/var/log/supervisor/syncthing-stderr.log
EOF
  ensure_supervisor
  supervisorctl reread >/dev/null 2>&1 || true
  supervisorctl update >/dev/null 2>&1 || true
  supervisorctl start syncthing >/dev/null 2>&1 || true
}
first_boot_config(){
  if [[ ! -f "$ST_CONFIG_XML" ]]; then
    mkdir -p "$ST_HOME"; chown -R "$ST_USER:$ST_USER" "$ST_HOME"
    # one-time launch to generate config.xml
    sudo -u "$ST_USER" bash -lc "${SYNCTHING_BIN} --no-browser --home='${ST_STATE_DIR}' >/tmp/st-first.log 2>&1 & echo \$! > /tmp/st-first.pid"
    for _ in {1..20}; do [[ -f "$ST_CONFIG_XML" ]] && break; sleep 1; done
    [[ -f /tmp/st-first.pid ]] && { kill "$(cat /tmp/st-first.pid)" >/dev/null 2>&1 || true; rm -f /tmp/st-first.pid; }
    [[ -f "$ST_CONFIG_XML" ]] || die "Failed to generate Syncthing config."
  fi
}
disable_crash(){ xml_replace "$ST_CONFIG_XML" "<crashReportingEnabled>true</crashReportingEnabled>" "<crashReportingEnabled>false</crashReportingEnabled>"; }

accept_eula_xml(){ # set <urAccepted>-1</urAccepted> in config.xml
  if [[ -f "$ST_CONFIG_XML" ]]; then
    if grep -q '<urAccepted>' "$ST_CONFIG_XML"; then
      sudo -u "$ST_USER" sed -i 's#<urAccepted>.*</urAccepted>#<urAccepted>-1</urAccepted>#' "$ST_CONFIG_XML"
    else
      sudo -u "$ST_USER" awk '
        BEGIN{done=0}
        /<options>/ && !done { print; print "    <urAccepted>-1</urAccepted>"; done=1; next }
        { print }
      ' "$ST_CONFIG_XML" > "${ST_CONFIG_XML}.tmp" && sudo -u "$ST_USER" mv "${ST_CONFIG_XML}.tmp" "$ST_CONFIG_XML"
    fi
  fi
}
bind_any(){ xml_replace "$ST_CONFIG_XML" "$ST_ADDR_FROM" "$ST_ADDR_TO"; }
restart_st(){ supervisorctl restart syncthing >/dev/null 2>&1 || true; sleep 2; }
my_id(){ sudo -u "$ST_USER" "$SYNCTHING_BIN" cli --home="$ST_STATE_DIR" show system | jq -r .myID; }

fid(){ # stable ID from path
  local s="$1"
  echo -n "$s" | md5sum | awk '{print $1}'
}

# ========= REST API helpers (local) =========
api_key(){
  # Syncthing writes GUI API key inside config.xml; grab it
  if [[ -f "$ST_CONFIG_XML" ]]; then
    awk -F'[<>]' '/<apikey>/{print $3; exit}' "$ST_CONFIG_XML"
  fi
}
api_get(){ # $1 endpoint (without /rest), outputs JSON
  local ep="$1" key="$2"
  curl -fsS -H "X-API-Key: $key" "$API/$ep"
}
api_post(){ # $1 endpoint, $2 json, $3 key
  local ep="$1" json="$2" key="$3"
  curl -fsS -H "X-API-Key: $key" -H "Content-Type: application/json" -X POST -d "$json" "$API/$ep"
}
api_put(){ # $1 endpoint, $2 json, $3 key
  local ep="$1" json="$2" key="$3"
  curl -fsS -H "X-API-Key: $key" -H "Content-Type: application/json" -X PUT -d "$json" "$API/$ep"
}
api_need(){
  command -v curl >/dev/null 2>&1 || ensure_pkg curl
  command -v jq   >/dev/null 2>&1 || ensure_pkg jq
}

# Normalize folder JSON’s .devices to an array of objects with {deviceID}
jq_normalize_devices='
  .devices |= (
    if . == null then []
    elif (type == "array") then .
    elif (type == "object") then [.]
    else [] end
  )
  | .devices |= map(select(type=="object"))
'

add_device_to_folder_json(){ # stdin: folder JSON; $1 = deviceID
  jq --arg dev "$1" "$jq_normalize_devices |
    if any(.devices[]?; .deviceID? == \$dev) then . else
      .devices += [{\"deviceID\": \$dev}]
    end"
}

# Create/Update folder by REST
ensure_folder_local(){ # $1 path, $2 rescanSec
  local path="$1" rescan="$2" id; id="$(fid "$path")"
  # ========================= MODIFICATION START =========================
  # If the folder path exists, ensure its ownership is correct for the Syncthing user.
  if [[ -d "$path" ]]; then
    chown -R "$ST_USER:$ST_USER" "$path"
  fi
  # ========================== MODIFICATION END ==========================
  sudo -u "$ST_USER" mkdir -p "$path"
  local key; key="$(api_key)"; [[ -n "$key" ]] || die "API key not found"
  # get folder or skeleton
  local existing; existing="$(api_get "config/folders/$id" "$key" 2>/dev/null || true)"
  if [[ -n "$existing" ]] && echo "$existing" | jq -e . >/dev/null 2>&1; then
    # update path/label/rescan if changed
    existing="$(echo "$existing" | jq --arg p "$path" --arg l "$path" --argjson r "$rescan" '
      .id //= "'$id'";
      .path=$p | .label=$l | .rescanIntervalS=$r
    ')"
  else
    existing="$(jq -n --arg id "$id" --arg p "$path" --arg l "$path" --argjson r "$rescan" \
      '{id:$id,label:$l,path:$p,filesystemType:"basic",type:"sendreceive",rescanIntervalS:$r,devices:[]}')"
  fi
  api_put "config/folders/$id" "$existing" "$key" >/dev/null
  echo "$id"
}

share_folder_to_device_local(){ # $1 folderId, $2 deviceID
  local id="$1" dev="$2"
  local key; key="$(api_key)"
  local fjson; fjson="$(api_get "config/folders/$id" "$key")"
  [[ -n "$fjson" ]] && echo "$fjson" | jq -e . >/dev/null 2>&1 || die "Failed to fetch folder $id"
  fjson="$(echo "$fjson" | add_device_to_folder_json "$dev")"
  api_put "config/folders/$id" "$fjson" "$key" >/dev/null
}

rescan_local(){ # $1 folderId
  local id="$1" key; key="$(api_key)"
  api_post "db/scan?folder=$id" '{}' "$key" >/dev/null || true
}

accept_eula_runtime(){ # set urAccepted via runtime config
  local key; key="$(api_key)"
  # Pull options, set urAccepted=-1
  local opt; opt="$(api_get "config/options" "$key")"
  opt="$(echo "$opt" | jq '.urAccepted=-1 | .crashReportingEnabled=false')"
  api_put "config/options" "$opt" "$key" >/dev/null
}

# ========= Remote (SSH + REST via ssh -L tunnel) =========
ssh_run(){ ssh -o BatchMode=yes -o ConnectTimeout=3 -o StrictHostKeyChecking=accept-new "$PEER_ADDR" "$@"; }

remote_bootstrap(){
  ssh_run "set -euo pipefail
    for p in curl ca-certificates jq supervisor gnupg; do dpkg -s \$p >/dev/null 2>&1 || sudo apt-get install -y \$p; done
    sudo mkdir -p /etc/apt/keyrings
    if [ ! -s /etc/apt/keyrings/syncthing-archive-keyring.gpg ]; then
      curl -fsSL https://syncthing.net/release-key.gpg | sudo gpg --dearmor -o /etc/apt/keyrings/syncthing-archive-keyring.gpg
      sudo chmod 0644 /etc/apt/keyrings/syncthing-archive-keyring.gpg
    fi
    if [ ! -f /etc/apt/sources.list.d/syncthing.list ]; then
      echo 'deb [signed-by=/etc/apt/keyrings/syncthing-archive-keyring.gpg] https://apt.syncthing.net/ syncthing stable-v2' | sudo tee /etc/apt/sources.list.d/syncthing.list >/dev/null
    fi
    sudo apt-get update -y
    dpkg -s syncthing >/dev/null 2>&1 || sudo apt-get install -y syncthing
    sudo mkdir -p '${ST_HOME}'; sudo chown -R ${ST_USER}:${ST_USER} '${ST_HOME}'
    if [[ ! -f '${ST_CONFIG_XML}' ]]; then
      sudo -u ${ST_USER} ${SYNCTHING_BIN} --no-browser --home='${ST_STATE_DIR}' >/dev/null 2>&1 & sleep 3; pkill -u ${ST_USER} syncthing || true
    fi
    sudo sed -i \"s#${ST_ADDR_FROM//\//\\/}#${ST_ADDR_TO//\//\\/}#\" '${ST_CONFIG_XML}' || true
    # Set EULA and disable crash reporting in XML (defensive)
    if sudo test -f '${ST_CONFIG_XML}'; then
      if sudo grep -q '<urAccepted>' '${ST_CONFIG_XML}'; then
        sudo sed -i 's#<urAccepted>.*</urAccepted>#<urAccepted>-1</urAccepted>#' '${ST_CONFIG_XML}'
      else
        sudo awk 'BEGIN{d=0} /<options>/ && !d { print; print \"    <urAccepted>-1</urAccepted>\"; d=1; next } { print }' '${ST_CONFIG_XML}' > '${ST_CONFIG_XML}.tmp' && sudo mv '${ST_CONFIG_XML}.tmp' '${ST_CONFIG_XML}'
      fi
      sudo sed -i 's#<crashReportingEnabled>true</crashReportingEnabled>#<crashReportingEnabled>false</crashReportingEnabled>#' '${ST_CONFIG_XML}' || true
    fi
    # Supervisor
    sudo bash -lc 'cat >/etc/supervisor/conf.d/syncthing.conf <<EOF
[program:syncthing]
autorestart=true
directory=${ST_HOME}/
user=${ST_USER}
command=${SYNCTHING_BIN} serve --no-browser
environment=STNORESTART=\"1\",HOME=\"${ST_HOME}\"
stdout_logfile=/var/log/supervisor/syncthing-stdout.log
stderr_logfile=/var/log/supervisor/syncthing-stderr.log
EOF'
    sudo systemctl enable supervisor >/dev/null 2>&1 || true
    sudo systemctl start supervisor >/dev/null 2>&1 || true
    sudo supervisorctl reread >/dev/null 2>&1 || true
    sudo supervisorctl update >/dev/null 2>&1 || true
    sudo supervisorctl start syncthing >/dev/null 2>&1 || true
  "
}

remote_my_id(){ ssh_run "sudo -u ${ST_USER} ${SYNCTHING_BIN} cli --home='${ST_STATE_DIR}' show system | jq -r .myID"; }

# Create SSH tunnel to remote GUI/REST for API calls (background)
start_remote_tunnel(){
  # local port 28384 -> remote 127.0.0.1:8384
  ssh -N -L 28384:127.0.0.1:8384 -o BatchMode=yes -o StrictHostKeyChecking=accept-new "$PEER_ADDR" >/dev/null 2>&1 &
  TUN_PID=$!
  sleep 1
}
stop_remote_tunnel(){
  [[ -n "${TUN_PID:-}" ]] && kill "$TUN_PID" >/dev/null 2>&1 || true
}

remote_api_key(){
  ssh_run "awk -F'[<>]' '/<apikey>/{print \$3; exit}' '${ST_CONFIG_XML}'"
}
r_api_get(){ # $1 endpoint, $2 key
  local ep="$1" key="$2"
  curl -fsS -H "X-API-Key: $key" "http://127.0.0.1:28384/rest/$ep"
}
r_api_put(){ # $1 endpoint, $2 json, $3 key
  local ep="$1" json="$2" key="$3"
  curl -fsS -H "X-API-Key: $key" -H "Content-Type: application/json" -X PUT -d "$json" "http://127.0.0.1:28384/rest/$ep"
}
r_api_post(){ # $1 endpoint, $2 json, $3 key
  local ep="$1" json="$2" key="$3"
  curl -fsS -H "X-API-Key: $key" -H "Content-Type: application/json" -X POST -d "$json" "http://127.0.0.1:28384/rest/$ep"
}

ensure_folder_remote(){ # $1 path, $2 rescanSec
  local path="$1" rescan="$2" id; id="$(fid "$path")"
  # ========================= MODIFICATION START =========================
  # If the folder path exists on the remote, ensure its ownership is correct.
  ssh_run "if [ -d '$path' ]; then sudo chown -R ${ST_USER}:${ST_USER} '$path'; fi"
  # ========================== MODIFICATION END ==========================
  ssh_run "sudo -u ${ST_USER} mkdir -p '$path'"
  local key; key="$(remote_api_key)"
  local existing; existing="$(r_api_get "config/folders/$id" "$key" 2>/dev/null || true)"
  if [[ -n "$existing" ]] && echo "$existing" | jq -e . >/dev/null 2>&1; then
    existing="$(echo "$existing" | jq --arg p "$path" --arg l "$path" --argjson r "$rescan" '
      .id //= "'$id'"; .path=$p | .label=$l | .rescanIntervalS=$r
    ')"
  else
    existing="$(jq -n --arg id "$id" --arg p "$path" --arg l "$path" --argjson r "$rescan" \
      '{id:$id,label:$l,path:$p,filesystemType:"basic",type:"sendreceive",rescanIntervalS:$r,devices:[]}')"
  fi
  r_api_put "config/folders/$id" "$existing" "$key" >/dev/null
  echo "$id"
}
share_folder_to_device_remote(){ # $1 folderId, $2 deviceID
  local id="$1" dev="$2"
  local key; key="$(remote_api_key)"
  local fjson; fjson="$(r_api_get "config/folders/$id" "$key")"
  [[ -n "$fjson" ]] && echo "$fjson" | jq -e . >/dev/null 2>&1 || die "Failed to fetch remote folder $id"
  fjson="$(echo "$fjson" | add_device_to_folder_json "$dev")"
  r_api_put "config/folders/$id" "$fjson" "$key" >/dev/null
}
rescan_remote(){ # $1 folderId
  local id="$1" key; key="$(remote_api_key)"
  r_api_post "db/scan?folder=$id" '{}' "$key" >/dev/null || true
}
accept_eula_runtime_remote(){
  local key; key="$(remote_api_key)"
  local opt; opt="$(r_api_get "config/options" "$key")"
  opt="$(echo "$opt" | jq '.urAccepted=-1 | .crashReportingEnabled=false')"
  r_api_put "config/options" "$opt" "$key" >/dev/null
}

# ========= Run =========
require_root
prompt_peer
check_connectivity_fast
api_need

green "Setting up Syncthing locally and on ${PEER_ADDR}..."

# Local install
ensure_pkg curl; ensure_pkg ca-certificates; ensure_pkg jq; ensure_pkg supervisor; ensure_pkg gnupg
mkdir -p "$APT_KEYRING_DIR"
if [[ ! -s "$APT_KEYRING" ]]; then
  curl -fsSL https://syncthing.net/release-key.gpg | gpg --dearmor -o "$APT_KEYRING"
  chmod 0644 "$APT_KEYRING"
fi
[[ -f "$APT_LIST" ]] || echo "deb [signed-by=${APT_KEYRING}] https://apt.syncthing.net/ syncthing stable-v2" > "$APT_LIST"
apt-get update -y
dpkg -s syncthing >/dev/null 2>&1 || apt-get install -y syncthing

mkdir -p "$ST_HOME"; chown -R "$ST_USER:$ST_USER" "$ST_HOME"
first_boot_config
disable_crash
accept_eula_xml
bind_any
write_supervisor
restart_st

# Accept EULA / disable crash reporting at runtime too (prevents prompts)
accept_eula_runtime

# Peer bootstrap & config
remote_bootstrap

# IDs & mutual add
LOCAL_ID="$(my_id)"; [[ -n "$LOCAL_ID" && "$LOCAL_ID" != "null" ]] || die "Cannot get local device ID."
PEER_ID="$(remote_my_id)"; [[ -n "$PEER_ID" && "$PEER_ID" != "null" ]] || die "Cannot get peer device ID."
info "Local ID: $LOCAL_ID"
info "Peer  ID: $PEER_ID"

# Add devices mutually via REST (safer)
KEY_LOCAL="$(api_key)"; [[ -n "$KEY_LOCAL" ]] || die "Local API key not found"
start_remote_tunnel
KEY_REMOTE="$(remote_api_key)"; [[ -n "$KEY_REMOTE" ]] || { stop_remote_tunnel; die "Remote API key not found"; }

# Add remote device to local
dev_local="$(api_get 'config/devices' "$KEY_LOCAL" || echo '[]')"
if ! echo "$dev_local" | jq -e --arg id "$PEER_ID" 'any(.[]?; .deviceID?==$id)' >/dev/null; then
  api_put "config/devices/$PEER_ID" "$(jq -n --arg id "$PEER_ID" '{deviceID:$id,name:$id,addresses:["dynamic"]}')" "$KEY_LOCAL" >/dev/null
fi

# Add local device to remote
dev_remote="$(r_api_get 'config/devices' "$KEY_REMOTE" || echo '[]')"
if ! echo "$dev_remote" | jq -e --arg id "$LOCAL_ID" 'any(.[]?; .deviceID?==$id)' >/dev/null; then
  r_api_put "config/devices/$LOCAL_ID" "$(jq -n --arg id "$LOCAL_ID" '{deviceID:$id,name:$id,addresses:["dynamic"]}')" "$KEY_REMOTE" >/dev/null
fi

# Create & share folders both ways
echo
info "Creating & sharing folders (this may take ~20–40s total)"
spin_start "Working..."
for entry in "${SYNC_FOLDERS[@]}"; do
  path="${entry%%|*}"; rescan="${entry##*|}"

  # Local ensure + share to remote
  fid_local="$(ensure_folder_local "$path" "$rescan")"
  # extra guard: fetch JSON and add device defensively
  fjson_local="$(api_get "config/folders/$fid_local" "$KEY_LOCAL")"
  if echo "$fjson_local" | jq -e . >/dev/null 2>&1; then
    fjson_local="$(echo "$fjson_local" | add_device_to_folder_json "$PEER_ID")"
    api_put "config/folders/$fid_local" "$fjson_local" "$KEY_LOCAL" >/dev/null
  fi
  rescan_local "$fid_local"

  # Remote ensure + share to local
  fid_remote="$(ensure_folder_remote "$path" "$rescan")"
  fjson_remote="$(r_api_get "config/folders/$fid_remote" "$KEY_REMOTE")"
  if echo "$fjson_remote" | jq -e . >/dev/null 2>&1; then
    fjson_remote="$(echo "$fjson_remote" | add_device_to_folder_json "$LOCAL_ID")"
    r_api_put "config/folders/$fid_remote" "$fjson_remote" "$KEY_REMOTE" >/dev/null
  fi
  rescan_remote "$fid_remote"
done
spin_stop
echo

# Restart and quick connect checks
info "Restarting Syncthing..."
restart_st
ssh_run "sudo supervisorctl restart syncthing >/dev/null 2>&1 || true"

# Connectivity wait with spinner
spin_start "Waiting for devices to connect..."
# best-effort: just sleep a few seconds; Syncthing connects on its own
sleep 5
spin_stop

stop_remote_tunnel || true

green "Done. Syncthing installed and paired successfully!"
echo "Folders are now shared on both servers."
echo "Check:  supervisorctl status syncthing"
echo "GUI:    http://<local-ip>:8384. Make sure to set up a tunnel if you are accesing from a remote location."