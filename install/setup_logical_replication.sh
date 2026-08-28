#!/bin/bash
if [ -z "$BASH_VERSION" ]; then
  exec bash "$0" "$@"
fi

# === COLOR FUNCTIONS ===
print_success() { echo -e "\e[32m$1 \e[0m"; }
print_error()   { echo -e "\e[31m$1 \e[0m"; }
print_info()    { echo -e "\e[36m$1 \e[0m"; }
print_warn()    { echo -e "\e[33m$1 \e[0m"; }

set -euo pipefail

# === CONFIGURATION ===
DB_NAME="fusionpbx"
REPL_USER="fusionpbx"
PUB_NAME_LOCAL="fspbx_publication_a"
PUB_NAME_REMOTE="fspbx_publication_b"
SUB_NAME_TO_REMOTE="fspbx_subscription_a_to_b"
SUB_NAME_TO_LOCAL="fspbx_subscription_b_to_a"

# Robust SSH defaults (keepalives + connection reuse)
SSH_OPTS=(
  -o ServerAliveInterval=10
  -o ServerAliveCountMax=18
  -o TCPKeepAlive=yes
  -o ConnectTimeout=15
  -o ControlMaster=auto
  -o ControlPersist=60
  -o ControlPath=/tmp/ssh_mux_%r@%h:%p
)

# SSH wrapper with retries (exponential backoff). Commands that receive stdin
# must use SSH_STDIN so the payload can be replayed on every attempt.
ssh_with_retries() {
  local stdin_file="$1"
  shift
  local tries=10 delay=2 rc=0 i

  for ((i=1; i<=tries; i++)); do
    if ssh "${SSH_OPTS[@]}" "$@" < "$stdin_file"; then
      return 0
    else
      rc=$?
    fi

    # OpenSSH uses 255 for transport/authentication failures. Any other status
    # came from the remote command and must be returned without retrying it.
    if (( rc != 255 )); then
      return "$rc"
    fi

    if (( i == tries )); then
      break
    fi

    print_warn "SSH attempt $i/$tries failed (rc=$rc). Retrying in $delay s..." >&2
    sleep "$delay"
    delay=$(( delay < 30 ? delay*2 : 30 ))
  done

  print_error "SSH failed after $tries attempts (last rc=$rc)." >&2
  return "$rc"
}

SSH() {
  ssh_with_retries /dev/null "$@"
}

SSH_STDIN() {
  local stdin_file rc
  stdin_file=$(mktemp)
  cat > "$stdin_file"

  if ssh_with_retries "$stdin_file" "$@"; then
    rc=0
  else
    rc=$?
  fi

  rm -f "$stdin_file"
  return "$rc"
}

# === PROMPTS ===
print_info "=== Logical Replication Bi-directional Setup ==="
read -p "Enter REMOTE SERVER IP: " REMOTE_IP
read -p "Enter LOCAL SERVER IP (this machine): " LOCAL_IP
read -s -p "Enter LOCAL Server DB password for 'fusionpbx' user: " LOCAL_PASS; echo ""
read -s -p "Enter REMOTE Server DB password for 'fusionpbx' user: " REMOTE_PASS; echo ""

# === VERIFY ROOT ===
if [ "$EUID" -ne 0 ]; then
    print_error "Please run this script as root"
    exit 1
fi

# === FUNCTIONS ===
validate_ipv4() {
  local ip="$1" octet
  local -a octets

  if [[ ! "$ip" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]; then
    return 1
  fi

  IFS=. read -r -a octets <<< "$ip"
  for octet in "${octets[@]}"; do
    if (( 10#$octet > 255 )); then
      return 1
    fi
  done
}

discover_postgres_here() {
  local database="$1"
  local version cluster port status owner remainder details candidate=""

  if ! command -v pg_lsclusters >/dev/null 2>&1; then
    print_error "pg_lsclusters is required to discover the active PostgreSQL cluster." >&2
    return 1
  fi

  while read -r version cluster port status owner remainder; do
    [[ "$status" == online* ]] || continue

    if details=$(runuser -u "$owner" -- psql -p "$port" -d "$database" -X -qAt -F $'\t' -c \
      "SELECT current_setting('server_version'), current_setting('config_file'), current_setting('hba_file'), pg_is_in_recovery();" 2>/dev/null); then
      candidate="$version"$'\t'"$cluster"$'\t'"$port"$'\t'"$owner"$'\t'"$details"

      # Prefer the conventional application port when more than one cluster is online.
      if [[ "$port" == "5432" ]]; then
        printf '%s\n' "$candidate"
        return 0
      fi
    fi
  done < <(pg_lsclusters --no-header)

  if [[ -n "$candidate" ]]; then
    printf '%s\n' "$candidate"
    return 0
  fi

  print_error "No online PostgreSQL cluster containing database '$database' was found." >&2
  pg_lsclusters >&2 || true
  return 1
}

discover_postgres_nodes() {
  local local_info remote_info

  print_info "Discovering PostgreSQL cluster on $LOCAL_IP..."
  local_info=$(discover_postgres_here "$DB_NAME")
  IFS=$'\t' read -r LOCAL_PG_VERSION LOCAL_PG_CLUSTER LOCAL_PG_PORT LOCAL_PG_OWNER \
    LOCAL_PG_SERVER_VERSION LOCAL_PG_CONF LOCAL_PG_HBA LOCAL_PG_IN_RECOVERY <<< "$local_info"

  print_info "Discovering PostgreSQL cluster on $REMOTE_IP..."
  remote_info=$(SSH_STDIN root@"$REMOTE_IP" bash -s -- "$DB_NAME" <<'EOF'
set -euo pipefail
database="$1"
candidate=""

if ! command -v pg_lsclusters >/dev/null 2>&1; then
  echo "pg_lsclusters is required to discover the active PostgreSQL cluster." >&2
  exit 1
fi

while read -r version cluster port status owner remainder; do
  [[ "$status" == online* ]] || continue

  if details=$(runuser -u "$owner" -- psql -p "$port" -d "$database" -X -qAt -F $'\t' -c \
    "SELECT current_setting('server_version'), current_setting('config_file'), current_setting('hba_file'), pg_is_in_recovery();" 2>/dev/null); then
    candidate="$version"$'\t'"$cluster"$'\t'"$port"$'\t'"$owner"$'\t'"$details"
    if [[ "$port" == "5432" ]]; then
      printf '%s\n' "$candidate"
      exit 0
    fi
  fi
done < <(pg_lsclusters --no-header)

if [[ -n "$candidate" ]]; then
  printf '%s\n' "$candidate"
  exit 0
fi

echo "No online PostgreSQL cluster containing database '$database' was found." >&2
pg_lsclusters >&2 || true
exit 1
EOF
)
  IFS=$'\t' read -r REMOTE_PG_VERSION REMOTE_PG_CLUSTER REMOTE_PG_PORT REMOTE_PG_OWNER \
    REMOTE_PG_SERVER_VERSION REMOTE_PG_CONF REMOTE_PG_HBA REMOTE_PG_IN_RECOVERY <<< "$remote_info"

  if [[ ! "$LOCAL_PG_PORT" =~ ^[0-9]+$ || -z "$LOCAL_PG_CONF" || -z "$LOCAL_PG_HBA" ]]; then
    print_error "Could not parse local PostgreSQL cluster details."
    exit 1
  fi
  if [[ ! "$REMOTE_PG_PORT" =~ ^[0-9]+$ || -z "$REMOTE_PG_CONF" || -z "$REMOTE_PG_HBA" ]]; then
    print_error "Could not parse remote PostgreSQL cluster details."
    exit 1
  fi
  if [[ "$LOCAL_PG_IN_RECOVERY" != "f" || "$REMOTE_PG_IN_RECOVERY" != "f" ]]; then
    print_error "Both PostgreSQL nodes must be writable primary clusters for bi-directional replication."
    exit 1
  fi

  LOCAL_PG_SERVICE="postgresql@${LOCAL_PG_VERSION}-${LOCAL_PG_CLUSTER}"
  REMOTE_PG_SERVICE="postgresql@${REMOTE_PG_VERSION}-${REMOTE_PG_CLUSTER}"

  print_success "LOCAL: PostgreSQL $LOCAL_PG_SERVER_VERSION, port $LOCAL_PG_PORT, config $LOCAL_PG_CONF"
  print_success "REMOTE: PostgreSQL $REMOTE_PG_SERVER_VERSION, port $REMOTE_PG_PORT, config $REMOTE_PG_CONF"
}

update_postgres_conf_file() {
  local pg_conf="$1"

  set_setting() {
    local setting="$1" value="$2"
    sed -Ei "/^[[:space:]#]*${setting}[[:space:]]*=/d" "$pg_conf"
    printf '%s = %s\n' "$setting" "$value" >> "$pg_conf"
  }

  [[ -f "$pg_conf" ]] || { print_error "PostgreSQL config not found: $pg_conf"; return 1; }
  set_setting listen_addresses "'*'"
  set_setting wal_level logical
  set_setting track_commit_timestamp on
  set_setting max_wal_senders 10
  set_setting max_replication_slots 48
  set_setting max_worker_processes 48
}

configure_postgres_conf() {
  local ip="$1" pg_conf="$2"
  print_info "Configuring postgresql.conf on $ip..."
  if [[ "$ip" == "$LOCAL_IP" ]]; then
    update_postgres_conf_file "$pg_conf"
  else
    SSH_STDIN root@"$ip" bash -s -- "$pg_conf" <<'EOF'
set -euo pipefail
pg_conf="$1"

set_setting() {
  local setting="$1" value="$2"
  sed -Ei "/^[[:space:]#]*${setting}[[:space:]]*=/d" "$pg_conf"
  printf '%s = %s\n' "$setting" "$value" >> "$pg_conf"
}

[[ -f "$pg_conf" ]] || { echo "PostgreSQL config not found: $pg_conf" >&2; exit 1; }
set_setting listen_addresses "'*'"
set_setting wal_level logical
set_setting track_commit_timestamp on
set_setting max_wal_senders 10
set_setting max_replication_slots 48
set_setting max_worker_processes 48
EOF
  fi
  print_success "postgresql.conf configured on $ip"
}

configure_pg_hba() {
  local ip="$1" peer_ip="$2" pg_hba="$3"
  local header="# >>> fspbx logical replication (${peer_ip}) >>>"
  local footer="# <<< fspbx logical replication (${peer_ip}) <<<"
  local BLOCK="$header
# Logical replication peer access
host    replication     ${REPL_USER}     ${peer_ip}/32       md5
host    all             all              ${peer_ip}/32       md5
$footer
"
  print_info "Configuring pg_hba.conf on $ip..."

  if [[ "$ip" == "$LOCAL_IP" ]]; then
    tmpfile=$(mktemp)
    awk -v h="$header" -v f="$footer" '
      BEGIN{skip=0}
      $0==h {skip=1}
      skip==0 {print}
      $0==f {skip=0}
    ' "$pg_hba" > "$tmpfile"
    grep -qE '^[[:space:]]*host[[:space:]]+all[[:space:]]+all[[:space:]]+127\.0\.0\.1/32[[:space:]]+md5' "$tmpfile" || \
      echo "host    all   all   127.0.0.1/32   md5" >> "$tmpfile"
    grep -qE '^[[:space:]]*host[[:space:]]+all[[:space:]]+all[[:space:]]+::1/128[[:space:]]+md5' "$tmpfile" || \
      echo "host    all   all   ::1/128        md5" >> "$tmpfile"
    printf "%s\n" "$BLOCK" >> "$tmpfile"
    chown --reference="$pg_hba" "$tmpfile"
    chmod --reference="$pg_hba" "$tmpfile"
    mv "$tmpfile" "$pg_hba"
  else
    SSH_STDIN root@"$ip" bash -s -- "$pg_hba" <<EOF
set -euo pipefail
PG_HBA_PATH="\$1"
HEADER="${header}"
FOOTER="${footer}"
tmpfile=\$(mktemp)
awk -v h="\$HEADER" -v f="\$FOOTER" '
  BEGIN{skip=0}
  \$0==h {skip=1}
  skip==0 {print}
  \$0==f {skip=0}
' "\$PG_HBA_PATH" > "\$tmpfile"
grep -qE '^[[:space:]]*host[[:space:]]+all[[:space:]]+all[[:space:]]+127\\.0\\.0\\.1/32[[:space:]]+md5' "\$tmpfile" || \
  echo "host    all   all   127.0.0.1/32   md5" >> "\$tmpfile"
grep -qE '^[[:space:]]*host[[:space:]]+all[[:space:]]+all[[:space:]]+::1/128[[:space:]]+md5' "\$tmpfile" || \
  echo "host    all   all   ::1/128        md5" >> "\$tmpfile"
cat >> "\$tmpfile" <<'BLKEND'
$BLOCK
BLKEND
chown --reference="\$PG_HBA_PATH" "\$tmpfile"
chmod --reference="\$PG_HBA_PATH" "\$tmpfile"
mv "\$tmpfile" "\$PG_HBA_PATH"
EOF
  fi
  print_success "pg_hba.conf configured on $ip"
}

drop_subscription_safe() {
  local sub_ip="$1" user="$2" db="$3" sub="$4"
  local port pass pass_shell existing

  if [[ "$sub_ip" == "$LOCAL_IP" ]]; then
    port="$LOCAL_PG_PORT"
    pass="$LOCAL_PASS"
    existing=$(PGPASSWORD="$pass" psql -w -h 127.0.0.1 -p "$port" -U "$user" -d "$db" -X -qAt \
      -v ON_ERROR_STOP=1 -c "SELECT 1 FROM pg_subscription WHERE subname = '$sub'")
    if [[ "$existing" == "1" ]]; then
      PGPASSWORD="$pass" psql -w -h 127.0.0.1 -p "$port" -U "$user" -d "$db" -X -q -v ON_ERROR_STOP=1 \
        -c "ALTER SUBSCRIPTION $sub DISABLE" \
        -c "ALTER SUBSCRIPTION $sub SET (slot_name = NONE)" \
        -c "DROP SUBSCRIPTION $sub"
    fi
  else
    port="$REMOTE_PG_PORT"
    pass="$REMOTE_PASS"
    printf -v pass_shell '%q' "$pass"
    SSH_STDIN root@"$sub_ip" bash -s -- "$port" "$user" "$db" "$sub" <<EOF
set -euo pipefail
export PGPASSWORD=$pass_shell
port="\$1"
user="\$2"
db="\$3"
sub="\$4"
existing=\$(psql -w -h 127.0.0.1 -p "\$port" -U "\$user" -d "\$db" -X -qAt -v ON_ERROR_STOP=1 \
  -c "SELECT 1 FROM pg_subscription WHERE subname = '\$sub'")
if [[ "\$existing" == "1" ]]; then
  psql -w -h 127.0.0.1 -p "\$port" -U "\$user" -d "\$db" -X -q -v ON_ERROR_STOP=1 \
    -c "ALTER SUBSCRIPTION \$sub DISABLE" \
    -c "ALTER SUBSCRIPTION \$sub SET (slot_name = NONE)" \
    -c "DROP SUBSCRIPTION \$sub"
fi
EOF
  fi
}

drop_replication_slot_if_exists() {
  local pub_ip="$1" user="$2" db="$3" slot="$4"
  local port pass pass_shell

  if [[ "$pub_ip" == "$LOCAL_IP" ]]; then
    port="$LOCAL_PG_PORT"
    pass="$LOCAL_PASS"
    PGPASSWORD="$pass" psql -w -h 127.0.0.1 -p "$port" -U "$user" -d "$db" -v ON_ERROR_STOP=1 -X -q <<SQL
DO \$\$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_replication_slots WHERE slot_name = '$slot') THEN
    PERFORM pg_drop_replication_slot('$slot');
  END IF;
END
\$\$;
SQL
  else
    port="$REMOTE_PG_PORT"
    pass="$REMOTE_PASS"
    printf -v pass_shell '%q' "$pass"
    SSH_STDIN root@"$pub_ip" bash -s -- "$port" "$user" "$db" <<EOF
set -euo pipefail
export PGPASSWORD=$pass_shell
port="\$1"
user="\$2"
db="\$3"
psql -w -h 127.0.0.1 -p "\$port" -U "\$user" -d "\$db" -v ON_ERROR_STOP=1 -X -q <<'SQL'
DO \$\$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_replication_slots WHERE slot_name = '$slot') THEN
    PERFORM pg_drop_replication_slot('$slot');
  END IF;
END
\$\$;
SQL
EOF
  fi
}

setup_firewall() {
  local ip="$1" peer_ip="$2" port="$3"
  print_info "Opening port $port on $ip for $peer_ip..."
  if [[ "$ip" == "$peer_ip" ]]; then
    print_warn "Skipping firewall on $ip for itself."
    print_success "Firewall rule applied on $ip"
    return 0
  fi
  if [[ "$ip" == "$LOCAL_IP" ]]; then
    iptables -C INPUT -p tcp --dport "$port" -s "$peer_ip" -j ACCEPT 2>/dev/null || iptables -A INPUT -p tcp --dport "$port" -s "$peer_ip" -j ACCEPT
    command -v netfilter-persistent &> /dev/null && netfilter-persistent save || print_warn 'iptables-persistent not installed.'
  else
    SSH_STDIN root@"$ip" bash -s -- "$peer_ip" "$port" <<'EOF'
set -euo pipefail
peer_ip="$1"
port="$2"
iptables -C INPUT -p tcp --dport "$port" -s "$peer_ip" -j ACCEPT 2>/dev/null || iptables -A INPUT -p tcp --dport "$port" -s "$peer_ip" -j ACCEPT
command -v netfilter-persistent &> /dev/null && netfilter-persistent save || echo '⚠️ iptables-persistent not installed.'
EOF
  fi
  print_success "Firewall rule applied on $ip"
}

restart_postgres() {
  local ip="$1" pg_service="$2"
  print_info "Restarting PostgreSQL on $ip..."
  if [[ "$ip" == "$LOCAL_IP" ]]; then
    systemctl restart "$pg_service"
  else
    SSH root@"$ip" "systemctl restart '$pg_service'"
  fi
  print_success "PostgreSQL restarted on $ip"
}

verify_postgres_runtime() {
  local ip="$1" port="$2" owner="$3"
  local attempts=30 result="" i

  print_info "Verifying PostgreSQL runtime settings on $ip..."
  if [[ "$ip" == "$LOCAL_IP" ]]; then
    for ((i=1; i<=attempts; i++)); do
      if result=$(runuser -u "$owner" -- psql -p "$port" -d "$DB_NAME" -X -qAt -v ON_ERROR_STOP=1 -c \
        "SELECT current_setting('listen_addresses') = '*'
          AND current_setting('wal_level') = 'logical'
          AND current_setting('track_commit_timestamp') = 'on'
          AND current_setting('max_wal_senders')::int >= 10
          AND current_setting('max_replication_slots')::int >= 48
          AND current_setting('max_worker_processes')::int >= 48
          AND NOT EXISTS (SELECT 1 FROM pg_hba_file_rules WHERE error IS NOT NULL);" 2>/dev/null) && [[ "$result" == "t" ]]; then
        print_success "PostgreSQL runtime settings verified on $ip"
        return 0
      fi
      sleep 1
    done
  else
    if SSH_STDIN root@"$ip" bash -s -- "$port" "$owner" "$DB_NAME" "$attempts" <<'EOF'
set -euo pipefail
port="$1"
owner="$2"
database="$3"
attempts="$4"

for ((i=1; i<=attempts; i++)); do
  if result=$(runuser -u "$owner" -- psql -p "$port" -d "$database" -X -qAt -v ON_ERROR_STOP=1 -c \
    "SELECT current_setting('listen_addresses') = '*'
      AND current_setting('wal_level') = 'logical'
      AND current_setting('track_commit_timestamp') = 'on'
      AND current_setting('max_wal_senders')::int >= 10
      AND current_setting('max_replication_slots')::int >= 48
      AND current_setting('max_worker_processes')::int >= 48
      AND NOT EXISTS (SELECT 1 FROM pg_hba_file_rules WHERE error IS NOT NULL);" 2>/dev/null) && [[ "$result" == "t" ]]; then
    exit 0
  fi
  sleep 1
done
exit 1
EOF
    then
      print_success "PostgreSQL runtime settings verified on $ip"
      return 0
    fi
  fi

  print_error "PostgreSQL on $ip did not return with the required logical-replication settings."
  return 1
}

verify_tcp_connection() {
  local source_ip="$1" target_ip="$2" target_port="$3" password="$4"
  local password_shell rc=0

  print_info "Testing PostgreSQL connection from $source_ip to $target_ip:$target_port..."
  if [[ "$source_ip" == "$LOCAL_IP" ]]; then
    if ! PGPASSWORD="$password" psql -w -h "$target_ip" -p "$target_port" -U "$REPL_USER" -d "$DB_NAME" \
      -X -qAt -v ON_ERROR_STOP=1 -c 'SELECT 1' | grep -qx 1; then
      rc=1
    fi
  else
    printf -v password_shell '%q' "$password"
    if ! SSH root@"$source_ip" "export PGPASSWORD=$password_shell; psql -w -h '$target_ip' -p '$target_port' -U '$REPL_USER' -d '$DB_NAME' -X -qAt -v ON_ERROR_STOP=1 -c 'SELECT 1'" | grep -qx 1; then
      rc=1
    fi
  fi

  if (( rc != 0 )); then
    print_error "PostgreSQL connection from $source_ip to $target_ip:$target_port failed. Verify the target server's '$REPL_USER' database password and network access."
    return "$rc"
  fi

  print_success "PostgreSQL connection from $source_ip to $target_ip:$target_port verified"
}

conninfo_quote() {
  local value="$1"
  value="${value//\\/\\\\}"
  value="${value//\'/\\\'}"
  printf "'%s'" "$value"
}

sql_literal() {
  local value="$1"
  value="${value//\'/\'\'}"
  printf "'%s'" "$value"
}

drop_and_create_schema_remote() {
  local remote_pass_shell
  print_warn "About to drop and recreate 'public' schema on REMOTE. This will delete ALL data in 'public' schema!"
  read -p "Proceed? [y/N]: " confirm
  if [[ "$confirm" =~ ^[Yy]$ ]]; then
    printf -v remote_pass_shell '%q' "$REMOTE_PASS"
    SSH root@"$REMOTE_IP" "export PGPASSWORD=$remote_pass_shell; psql -w -h 127.0.0.1 -p '$REMOTE_PG_PORT' -U '$REPL_USER' -d '$DB_NAME' -X -v ON_ERROR_STOP=1 -c 'DROP SCHEMA public CASCADE; CREATE SCHEMA public'"
    print_success "Schema dropped and recreated on REMOTE."
  else
    print_error "Aborted schema reset on REMOTE."
    exit 1
  fi
}

copy_schema_to_remote() {
  print_info "Copying schema from LOCAL to REMOTE using pg_dump..."
  PGPASSWORD="$LOCAL_PASS" pg_dump -w -h 127.0.0.1 -p "$LOCAL_PG_PORT" -U "$REPL_USER" -s "$DB_NAME" | \
  PGPASSWORD="$REMOTE_PASS" psql -w -h "$REMOTE_IP" -p "$REMOTE_PG_PORT" -U "$REPL_USER" -d "$DB_NAME" -X -v ON_ERROR_STOP=1
  print_success "Schema copied to REMOTE."
}

create_publication() {
  local ip="$1" pass="$2" pub_name="$3" port pass_shell
  print_info "Creating publication '$pub_name' on $ip..."
  if [[ "$ip" == "$LOCAL_IP" ]]; then
    port="$LOCAL_PG_PORT"
    PGPASSWORD="$pass" psql -w -h 127.0.0.1 -p "$port" -U "$REPL_USER" -d "$DB_NAME" -X -v ON_ERROR_STOP=1 <<SQLEND
DROP PUBLICATION IF EXISTS $pub_name;
CREATE PUBLICATION $pub_name FOR ALL TABLES;
SQLEND
  else
    port="$REMOTE_PG_PORT"
    printf -v pass_shell '%q' "$pass"
    SSH_STDIN root@"$ip" bash -s -- "$port" "$REPL_USER" "$DB_NAME" "$pub_name" <<EOF
set -euo pipefail
export PGPASSWORD=$pass_shell
port="\$1"
user="\$2"
database="\$3"
publication="\$4"
psql -w -h 127.0.0.1 -p "\$port" -U "\$user" -d "\$database" -X -v ON_ERROR_STOP=1 <<SQLEND
DROP PUBLICATION IF EXISTS \$publication;
CREATE PUBLICATION \$publication FOR ALL TABLES;
SQLEND
EOF
  fi
  print_success "Publication $pub_name created on $ip"
}

create_subscription_remote() {
  local conninfo conninfo_sql remote_pass_shell
  print_info "Creating subscription on REMOTE (to LOCAL, with data copy)..."
  conninfo="host=$LOCAL_IP port=$LOCAL_PG_PORT dbname=$DB_NAME user=$REPL_USER password=$(conninfo_quote "$LOCAL_PASS")"
  conninfo_sql=$(sql_literal "$conninfo")
  printf -v remote_pass_shell '%q' "$REMOTE_PASS"
  SSH_STDIN root@"$REMOTE_IP" bash -s -- "$REMOTE_PG_PORT" "$REPL_USER" "$DB_NAME" <<EOF
set -euo pipefail
export PGPASSWORD=$remote_pass_shell
port="\$1"
user="\$2"
database="\$3"
psql -w -h 127.0.0.1 -p "\$port" -U "\$user" -d "\$database" -X -v ON_ERROR_STOP=1 <<'SQLEND'
DROP SUBSCRIPTION IF EXISTS $SUB_NAME_TO_REMOTE;
CREATE SUBSCRIPTION $SUB_NAME_TO_REMOTE CONNECTION $conninfo_sql PUBLICATION $PUB_NAME_LOCAL WITH (origin = none, copy_data = true);
SQLEND
EOF
  print_success "Subscription $SUB_NAME_TO_REMOTE created on REMOTE."
}

wait_for_sync_complete() {
  local attempts="${1:-900}" sleep_s="${2:-2}" remote_pass_shell rc
  print_info "Waiting for initial data sync to complete on REMOTE SERVER (all tables synced check)..."
  printf -v remote_pass_shell '%q' "$REMOTE_PASS"
  if SSH_STDIN root@"$REMOTE_IP" bash -s -- "$REMOTE_PG_PORT" "$REPL_USER" "$DB_NAME" "$SUB_NAME_TO_REMOTE" "$attempts" "$sleep_s" <<EOF
set -euo pipefail
export PGPASSWORD=$remote_pass_shell
PORT="\$1"
USER="\$2"
DB="\$3"
SUB_NAME="\$4"
ATTEMPTS="\$5"
SLEEP_S="\$6"
for ((i=1; i<=ATTEMPTS; i++)); do
  if ! all_synced=\$(psql -w -h 127.0.0.1 -p "\$PORT" -U "\$USER" -d "\$DB" -X -qAt -v ON_ERROR_STOP=1 -c \
    "SELECT COALESCE(bool_and(rel.srsubstate = 'r'), false)
     FROM pg_subscription_rel rel
     JOIN pg_subscription sub ON rel.srsubid = sub.oid
     WHERE sub.subname = '\$SUB_NAME';"); then
    exit 3
  fi
  if [[ "\$all_synced" == "t" ]]; then
    echo "__SYNC_DONE__"
    exit 0
  fi
  sleep "\$SLEEP_S"
done
exit 2
EOF
  then
    rc=0
  else
    rc=$?
  fi
  case $rc in
    0) print_success "Initial sync complete. Proceeding to reverse replication."; return 0 ;;
    2) print_error "Sync did not complete within timeout."; exit 1 ;;
    255) print_error "SSH connection failed while waiting for sync."; exit 1 ;;
    *) print_error "The remote sync-status check failed (rc=$rc)."; exit 1 ;;
  esac
}

create_subscription_local() {
  local conninfo conninfo_sql
  print_info "Creating subscription on LOCAL (to REMOTE, no data copy)..."
  conninfo="host=$REMOTE_IP port=$REMOTE_PG_PORT dbname=$DB_NAME user=$REPL_USER password=$(conninfo_quote "$REMOTE_PASS")"
  conninfo_sql=$(sql_literal "$conninfo")
  PGPASSWORD="$LOCAL_PASS" psql -w -h 127.0.0.1 -p "$LOCAL_PG_PORT" -U "$REPL_USER" -d "$DB_NAME" -X -v ON_ERROR_STOP=1 <<SQL
DROP SUBSCRIPTION IF EXISTS $SUB_NAME_TO_LOCAL;
CREATE SUBSCRIPTION $SUB_NAME_TO_LOCAL CONNECTION $conninfo_sql PUBLICATION $PUB_NAME_REMOTE WITH (origin = none, copy_data = false);
SQL
  print_success "Subscription $SUB_NAME_TO_LOCAL created on LOCAL."
}

# === EXECUTION ===
if ! validate_ipv4 "$LOCAL_IP" || ! validate_ipv4 "$REMOTE_IP"; then
  print_error "LOCAL and REMOTE addresses must be valid IPv4 addresses."
  exit 1
fi
if [[ "$LOCAL_IP" == "$REMOTE_IP" ]]; then
  print_error "LOCAL and REMOTE addresses must be different."
  exit 1
fi

discover_postgres_nodes

configure_postgres_conf "$LOCAL_IP" "$LOCAL_PG_CONF"
configure_postgres_conf "$REMOTE_IP" "$REMOTE_PG_CONF"

configure_pg_hba "$LOCAL_IP" "$REMOTE_IP" "$LOCAL_PG_HBA"
configure_pg_hba "$REMOTE_IP" "$LOCAL_IP" "$REMOTE_PG_HBA"

setup_firewall "$LOCAL_IP" "$REMOTE_IP" "$LOCAL_PG_PORT"
setup_firewall "$REMOTE_IP" "$LOCAL_IP" "$REMOTE_PG_PORT"

restart_postgres "$LOCAL_IP" "$LOCAL_PG_SERVICE"
restart_postgres "$REMOTE_IP" "$REMOTE_PG_SERVICE"

verify_postgres_runtime "$LOCAL_IP" "$LOCAL_PG_PORT" "$LOCAL_PG_OWNER"
verify_postgres_runtime "$REMOTE_IP" "$REMOTE_PG_PORT" "$REMOTE_PG_OWNER"

# Verify local authentication and both network directions before allowing the
# destructive remote bootstrap to begin.
verify_tcp_connection "$LOCAL_IP" 127.0.0.1 "$LOCAL_PG_PORT" "$LOCAL_PASS"
verify_tcp_connection "$REMOTE_IP" 127.0.0.1 "$REMOTE_PG_PORT" "$REMOTE_PASS"
verify_tcp_connection "$LOCAL_IP" "$REMOTE_IP" "$REMOTE_PG_PORT" "$REMOTE_PASS"
verify_tcp_connection "$REMOTE_IP" "$LOCAL_IP" "$LOCAL_PG_PORT" "$LOCAL_PASS"

print_success "All PostgreSQL preflight checks passed on both nodes."

drop_and_create_schema_remote
copy_schema_to_remote

create_publication "$LOCAL_IP" "$LOCAL_PASS" "$PUB_NAME_LOCAL"
create_publication "$REMOTE_IP" "$REMOTE_PASS" "$PUB_NAME_REMOTE"

# Ensure we can recreate cleanly
drop_subscription_safe "$REMOTE_IP" "$REPL_USER" "$DB_NAME" "$SUB_NAME_TO_REMOTE"
drop_replication_slot_if_exists "$LOCAL_IP" "$REPL_USER" "$DB_NAME" "$SUB_NAME_TO_REMOTE"
create_subscription_remote

wait_for_sync_complete

drop_subscription_safe "$LOCAL_IP" "$REPL_USER" "$DB_NAME" "$SUB_NAME_TO_LOCAL"
drop_replication_slot_if_exists "$REMOTE_IP" "$REPL_USER" "$DB_NAME" "$SUB_NAME_TO_LOCAL"
create_subscription_local

print_success "Bi-directional logical replication is now set up between LOCAL and REMOTE."
