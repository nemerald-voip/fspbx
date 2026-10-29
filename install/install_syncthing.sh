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
# Fresh-install networking is embedded here; no application update code is loaded.
syncthing_network_setup(){
  sudo php /dev/stdin "$@" <<'SYNCTHING_SETUP_PHP'
<?php

declare(strict_types=1);

class SyncthingNetworkSetup
{
    public const RELAY = 'dynamic+https://relays.syncthing.net/endpoint';

    public function __construct(
        public string $rulesDir = '/etc/iptables',
        public string $ufwConfigPath = '/etc/ufw/ufw.conf',
    ) {}

    /** Configure a newly generated file before Syncthing is first started. */
    public function initializeConfig(string $path): void
    {
        $document = self::xml(self::read($path));
        $xpath = new DOMXPath($document);
        $matches = $xpath->query('/configuration/options');
        if ($matches->length !== 1) {
            throw new RuntimeException('Cannot identify Syncthing options');
        }
        $options = $matches->item(0);
        foreach ([
            'listenAddress' => ['tcp://0.0.0.0:22000', self::RELAY],
            'localAnnounceEnabled' => ['false'],
            'globalAnnounceEnabled' => ['true'],
            'relaysEnabled' => ['true'],
        ] as $name => $values) {
            foreach (iterator_to_array($xpath->query('./'.$name, $options)) as $node) {
                $options->removeChild($node);
            }
            foreach ($values as $value) {
                $node = $document->createElement($name);
                $node->appendChild($document->createTextNode($value));
                $options->appendChild($node);
            }
        }
        $meta = stat($path);
        self::atomicWrite($path, $document->saveXML(), $meta['mode'] & 0777, [$meta['uid'], $meta['gid']]);
    }

    public function command(array $args, ?string $input = null, int $timeout = 15, bool $check = true): array
    {
        // Files avoid pipe deadlocks with large saved firewalls. Array arguments
        // bypass the shell, and errors never include credentials or config bodies.
        $streams = [tmpfile(), tmpfile(), tmpfile()];
        if (in_array(false, $streams, true)) {
            foreach ($streams as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
            throw new RuntimeException('Cannot allocate command streams');
        }
        $process = null;
        try {
            self::writeStream($streams[0], $input ?? '');
            rewind($streams[0]);
            $process = proc_open($args, $streams, $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('Cannot run '.basename($args[0]));
            }
            $deadline = hrtime(true) + $timeout * 1_000_000_000;
            do {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                if (hrtime(true) >= $deadline) {
                    throw new RuntimeException(basename($args[0]).' timed out');
                }
                usleep(20_000);
            } while (true);
            rewind($streams[1]);
            rewind($streams[2]);
            $result = ['code' => $status['exitcode'], 'stdout' => stream_get_contents($streams[1]),
                'stderr' => stream_get_contents($streams[2])];
            if ($check && $result['code'] !== 0) {
                throw new RuntimeException(basename($args[0]).' failed (exit '.$result['code'].')');
            }
            return $result;
        } finally {
            if (is_resource($process)) {
                if (proc_get_status($process)['running']) {
                    proc_terminate($process, 9);
                }
                proc_close($process);
            }
            foreach ($streams as $stream) {
                fclose($stream);
            }
        }
    }

    private static function writeStream($stream, string $data): void
    {
        for ($offset = 0, $length = strlen($data); $offset < $length; $offset += $written) {
            $written = fwrite($stream, substr($data, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Cannot write configuration data');
            }
        }
    }

    public static function read(string $path): string
    {
        $data = @file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException('Cannot read '.$path);
        }
        return $data;
    }

    public static function atomicWrite(string $path, string $data, int $mode = 0600, ?array $owner = null): void
    {
        if (is_link($path)) {
            throw new RuntimeException('Refusing symlink: '.$path);
        }
        $temporary = tempnam(dirname($path), '.'.basename($path));
        if ($temporary === false || dirname($temporary) !== realpath(dirname($path))) {
            if ($temporary !== false) {
                unlink($temporary);
            }
            throw new RuntimeException('Cannot create temporary configuration file');
        }
        try {
            if (!chmod($temporary, $mode) || ($owner !== null &&
                (!chown($temporary, $owner[0]) || !chgrp($temporary, $owner[1])))) {
                throw new RuntimeException('Cannot preserve file permissions');
            }
            $stream = fopen($temporary, 'wb');
            if ($stream === false) {
                throw new RuntimeException('Cannot open temporary configuration file');
            }
            try {
                self::writeStream($stream, $data);
                if (!fflush($stream) || !fsync($stream)) {
                    throw new RuntimeException('Cannot persist configuration file');
                }
            } finally {
                fclose($stream);
            }
            if (!rename($temporary, $path)) {
                throw new RuntimeException('Cannot replace '.$path);
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }

    public static function xml(string $xml): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            if (!$document->loadXML($xml, LIBXML_NONET) || $document->doctype !== null) {
                throw new RuntimeException('Invalid or unsupported XML configuration');
            }
            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function address(string $value): string
    {
        $packed = @inet_pton($value);
        if ($packed === false) {
            throw new RuntimeException('Peer address is not a usable server IP');
        }
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            $packed = substr($packed, 12);
        }
        $ip = inet_ntop($packed);
        $first = ord($packed[0]);
        $unsafe = strlen($packed) === 4
            ? ($ip === '0.0.0.0' || $first === 127 || ($first >= 224 && $first <= 239)
                || ($first === 169 && ord($packed[1]) === 254))
            : ($ip === '::' || $ip === '::1' || $first === 255
                || ($first === 254 && (ord($packed[1]) & 0xc0) === 0x80));
        if ($unsafe) {
            throw new RuntimeException('Peer address is not a usable server IP');
        }
        return $ip;
    }

    public function firewallRule(string $ip): array
    {
        $ip = $this->address($ip);
        return ['-s', $ip.(str_contains($ip, ':') ? '/128' : '/32'), '-p', 'tcp', '-m', 'tcp',
            '--dport', '22000', '-m', 'comment', '--comment', 'fspbx-syncthing', '-j', 'ACCEPT'];
    }

    public static function addPersistentRule(string $contents, array $rule): string
    {
        $lines = explode("\n", rtrim($contents, "\n"));
        $starts = array_keys($lines, '*filter', true);
        if (count($starts) !== 1) {
            throw new RuntimeException('Cannot identify the saved iptables filter table');
        }
        $start = $starts[0];
        $end = null;
        for ($i = $start + 1; $i < count($lines); $i++) {
            if ($lines[$i] === 'COMMIT') {
                $end = $i;
                break;
            }
        }
        if ($end === null) {
            throw new RuntimeException('Incomplete saved iptables filter table');
        }
        // Tokens here are fixed flags and validated IPs; no shell escaping is
        // needed in iptables-save syntax. Accept its quoted comment form too.
        $full = '-A INPUT '.implode(' ', $rule);
        foreach (array_slice($lines, $start + 1, $end - $start - 1) as $line) {
            $normalized = str_replace(['"fspbx-syncthing"', "'fspbx-syncthing'"], 'fspbx-syncthing', $line);
            if (preg_replace('/\s+/', ' ', trim($normalized)) === $full) {
                return $contents;
            }
        }
        $position = $start + 1;
        while ($position < $end && ($lines[$position] === '' || str_starts_with($lines[$position], ':')
            || str_starts_with($lines[$position], '#'))) {
            $position++;
        }
        array_splice($lines, $position, 0, [$full]);
        return implode("\n", $lines)."\n";
    }

    public function allowPeers(array $peers): void
    {
        if (trim($this->command(['systemctl', 'is-enabled', 'netfilter-persistent'], check: false)['stdout']) !== 'enabled') {
            throw new RuntimeException('Enabled netfilter-persistent is required to preserve the TCP rule after reboot');
        }
        // UFW's oneshot unit can remain active after UFW is disabled or
        // removed. Check its boot configuration and actual chains instead.
        if (file_exists($this->ufwConfigPath)) {
            $count = preg_match_all('/^\h*ENABLED\h*=(.*)$/m', self::read($this->ufwConfigPath), $matches);
            if ($count !== 1 || !preg_match('/^(?:no|"no"|\'no\')\h*(?:#.*)?$/', trim($matches[1][0]))) {
                throw new RuntimeException('UFW is enabled or its configuration is unclear; manual review required');
            }
        }
        foreach (['firewalld', 'nftables'] as $service) {
            if ($this->command(['systemctl', 'is-active', $service], check: false)['code'] === 0) {
                throw new RuntimeException($service.' manages this firewall; manual review required');
            }
        }
        $changes = [];
        foreach ([4, 6] as $family) {
            $ips = array_unique(array_filter($peers, fn ($ip) => str_contains($ip, ':') === ($family === 6)));
            if (!$ips) {
                continue;
            }
            sort($ips);
            $binary = $family === 4 ? 'iptables' : 'ip6tables';
            $path = $this->rulesDir.'/rules.v'.$family;
            $original = $updated = self::read($path);
            $live = $this->command([$binary, '-w', '5', '-S'])['stdout'];
            if (preg_match('/^(?:-N |:)ufw-/m', $original."\n".$live)) {
                throw new RuntimeException('UFW chains exist in the live or saved firewall; manual review required');
            }
            foreach ($ips as $ip) {
                $updated = self::addPersistentRule($updated, $this->firewallRule($ip));
            }
            $this->command([$binary.'-restore', '--test'], input: $updated);
            $changes[] = compact('binary', 'path', 'original', 'updated', 'ips');
        }
        // Validate every file/family before changing any persisted or live rule.
        foreach ($changes as $change) {
            ['binary' => $binary, 'path' => $path, 'original' => $original, 'updated' => $updated, 'ips' => $ips] = $change;
            if ($updated !== $original) {
                $meta = stat($path);
                self::atomicWrite($path, $updated, $meta['mode'] & 0777, [$meta['uid'], $meta['gid']]);
            }
            foreach ($ips as $ip) {
                $rule = $this->firewallRule($ip);
                if ($this->command(array_merge([$binary, '-w', '5', '-C', 'INPUT'], $rule), check: false)['code'] !== 0) {
                    $this->command(array_merge([$binary, '-w', '5', '-I', 'INPUT', '1'], $rule));
                }
            }
        }
    }
}

try {
    if (count($argv) !== 3 || !in_array($argv[1], ['--initialize-config', '--allow-peer'], true)) {
        throw new RuntimeException('Use --initialize-config CONFIG_PATH or --allow-peer PEER_IP');
    }
    $setup = new SyncthingNetworkSetup();
    if ($argv[1] === '--initialize-config') {
        $setup->initializeConfig($argv[2]);
    } else {
        $setup->allowPeers([$setup->address($argv[2])]);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Syncthing setup failed: '.$error->getMessage()."\n");
    exit(1);
}
SYNCTHING_SETUP_PHP
}

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
    [[ ! -f "$ST_HOME/.config/syncthing/config.xml" ]] || die "Existing legacy Syncthing configuration found. Use app:update for existing-server network changes."
    # Generate files offline, then set the network defaults before first start.
    sudo -u "$ST_USER" "$SYNCTHING_BIN" generate --home="$ST_STATE_DIR"
    [[ -f "$ST_CONFIG_XML" ]] || die "Failed to generate Syncthing config."
    syncthing_network_setup --initialize-config "$ST_CONFIG_XML"
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
restart_st(){ supervisorctl restart syncthing >/dev/null; sleep 2; }
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
    $(declare -f syncthing_network_setup)
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
      if sudo test -f '${ST_HOME}/.config/syncthing/config.xml'; then
        echo 'Existing legacy Syncthing configuration found. Use app:update for existing-server network changes.' >&2
        exit 1
      fi
      sudo -u ${ST_USER} ${SYNCTHING_BIN} generate --home='${ST_STATE_DIR}'
      syncthing_network_setup --initialize-config '${ST_CONFIG_XML}'
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
    # Allow the paired server before starting the synchronization service.
    syncthing_network_setup --allow-peer '${LOCAL_SYNC_IP}'
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

# Determine the paired addresses for firewall rules and initial device setup.
ssh_connection="$(ssh_run 'printf "%s\n" "$SSH_CONNECTION"')"
read -r LOCAL_SYNC_IP _ _ _ <<< "$ssh_connection"
PEER_SYNC_IP="$(php -r '
$host = trim($argv[1], "[]");
$ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : (gethostbynamel($host)[0] ?? null);
if ($ip === null) {
    $ip = dns_get_record($host, DNS_AAAA)[0]["ipv6"] ?? null;
}
if ($ip === null) {
    fwrite(STDERR, "Cannot resolve the peer IP.\n");
    exit(1);
}
echo $ip;
' "$PEER_HOST")"
[[ "$LOCAL_SYNC_IP" =~ ^[0-9a-fA-F:.]+$ && "$PEER_SYNC_IP" =~ ^[0-9a-fA-F:.]+$ ]] || die "Cannot determine peer IPs."
LOCAL_TCP_ADDRESS="tcp://${LOCAL_SYNC_IP}:22000"
PEER_TCP_ADDRESS="tcp://${PEER_SYNC_IP}:22000"
[[ "$LOCAL_SYNC_IP" == *:* ]] && LOCAL_TCP_ADDRESS="tcp://[${LOCAL_SYNC_IP}]:22000"
[[ "$PEER_SYNC_IP" == *:* ]] && PEER_TCP_ADDRESS="tcp://[${PEER_SYNC_IP}]:22000"

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
syncthing_network_setup --allow-peer "$PEER_SYNC_IP"
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
  api_put "config/devices/$PEER_ID" "$(jq -n --arg id "$PEER_ID" --arg address "$PEER_TCP_ADDRESS" '{deviceID:$id,name:$id,addresses:[$address,"dynamic"]}')" "$KEY_LOCAL" >/dev/null
fi

# Add local device to remote
dev_remote="$(r_api_get 'config/devices' "$KEY_REMOTE" || echo '[]')"
if ! echo "$dev_remote" | jq -e --arg id "$LOCAL_ID" 'any(.[]?; .deviceID?==$id)' >/dev/null; then
  r_api_put "config/devices/$LOCAL_ID" "$(jq -n --arg id "$LOCAL_ID" --arg address "$LOCAL_TCP_ADDRESS" '{deviceID:$id,name:$id,addresses:[$address,"dynamic"]}')" "$KEY_REMOTE" >/dev/null
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

# Load the final configuration.
info "Restarting Syncthing..."
restart_st
ssh_run "sudo supervisorctl restart syncthing >/dev/null"

stop_remote_tunnel || true

green "Syncthing installed; devices and folders configured."
echo "Folders are now shared on both servers."
echo "Check:  supervisorctl status syncthing"
echo "GUI:    http://<local-ip>:8384. Make sure to set up a tunnel if you are accesing from a remote location."
