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
PG_VERSION="17"
PG_CONF="/etc/postgresql/${PG_VERSION}/main/postgresql.conf"
PG_HBA="/etc/postgresql/${PG_VERSION}/main/pg_hba.conf"
PG_SERVICE="postgresql"
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

# SSH wrapper with retries (exponential backoff)
SSH() {
  local tries=10 delay=2 rc i
  for ((i=1; i<=tries; i++)); do
    if ssh "${SSH_OPTS[@]}" "$@"; then return 0; fi
    rc=$?
    print_warn "SSH attempt $i/$tries failed (rc=$rc). Retrying in $delay s..."
    sleep "$delay"
    delay=$(( delay < 30 ? delay*2 : 30 ))
  done
  print_error "SSH failed after $tries attempts."
  return 255
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
configure_postgres_conf() {
  local ip=$1
  print_info "Configuring postgresql.conf on $ip..."
  if [[ "$ip" == "$LOCAL_IP" ]]; then
    sed -i "s/^#*listen_addresses.*/listen_addresses = '*'/" "$PG_CONF"
    sed -i "s/^#*wal_level.*/wal_level = logical/" "$PG_CONF"
    sed -i "s/^#*track_commit_timestamp.*/track_commit_timestamp = on/" "$PG_CONF"
    sed -i "s/^#*max_wal_senders.*/max_wal_senders = 10/" "$PG_CONF"
    sed -i "s/^#*max_replication_slots.*/max_replication_slots = 48/" "$PG_CONF"
    sed -i "s/^#*max_worker_processes.*/max_worker_processes = 48/" "$PG_CONF"
  else
    SSH root@"$ip" bash -s <<EOF
set -euo pipefail
sed -i "s/^#*listen_addresses.*/listen_addresses = '*'/" "${PG_CONF}"
sed -i "s/^#*wal_level.*/wal_level = logical/" "${PG_CONF}"
sed -i "s/^#*track_commit_timestamp.*/track_commit_timestamp = on/" "${PG_CONF}"
sed -i "s/^#*max_wal_senders.*/max_wal_senders = 10/" "${PG_CONF}"
sed -i "s/^#*max_replication_slots.*/max_replication_slots = 48/" "${PG_CONF}"
sed -i "s/^#*max_worker_processes.*/max_worker_processes = 48/" "${PG_CONF}"
EOF
  fi
  print_success "postgresql.conf configured on $ip"
}

configure_pg_hba() {
  local ip=$1 peer_ip=$2
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
    ' "$PG_HBA" > "$tmpfile"
    grep -qE '^[[:space:]]*host[[:space:]]+all[[:space:]]+all[[:space:]]+127\.0\.0\.1/32[[:space:]]+md5' "$tmpfile" || \
      echo "host    all   all   127.0.0.1/32   md5" >> "$tmpfile"
    grep -qE '^[[:space:]]*host[[:space:]]+all[[:space:]]+all[[:space:]]+::1/128[[:space:]]+md5' "$tmpfile" || \
      echo "host    all   all   ::1/128        md5" >> "$tmpfile"
    printf "%s\n" "$BLOCK" >> "$tmpfile"
    mv "$tmpfile" "$PG_HBA"
    chown postgres:postgres "$PG_HBA"
    chmod 640 "$PG_HBA"
  else
    SSH root@"$ip" bash -s <<EOF
set -euo pipefail
PG_HBA_PATH="${PG_HBA}"
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
chmod 640 "\$tmpfile"
chown postgres:postgres "\$tmpfile"
mv "\$tmpfile" "\$PG_HBA_PATH"
EOF
  fi
  print_success "pg_hba.conf configured on $ip"
}

# Resolve password env based on target IP
password_env_for_ip() {
  local ip="$1"
  if [[ "$ip" == "$LOCAL_IP" ]]; then
    echo "PGPASSWORD='$LOCAL_PASS'"
  else
    echo "PGPASSWORD='$REMOTE_PASS'"
  fi
}

drop_subscription_safe() {
  local sub_ip="$1" user="$2" db="$3" sub="$4"
  local psql_flags="-h 127.0.0.1 -U $user -d $db -v ON_ERROR_STOP=1 -X -q"
  if [[ "$sub_ip" == "$LOCAL_IP" ]]; then
    eval "$(password_env_for_ip "$sub_ip")" psql $psql_flags <<SQL || true
DO \$\$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_subscription WHERE subname = '$sub') THEN
    EXECUTE 'ALTER SUBSCRIPTION $sub DISABLE';
    EXECUTE 'ALTER SUBSCRIPTION $sub SET (slot_name = NONE)';
    EXECUTE 'DROP SUBSCRIPTION $sub';
  END IF;
END
\$\$;
SQL
  else
    SSH root@"$sub_ip" bash -s <<EOF
set -euo pipefail
export $(password_env_for_ip "$sub_ip")
psql $psql_flags <<'SQL'
DO \$\$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_subscription WHERE subname = '$sub') THEN
    EXECUTE 'ALTER SUBSCRIPTION $sub DISABLE';
    EXECUTE 'ALTER SUBSCRIPTION $sub SET (slot_name = NONE)';
    EXECUTE 'DROP SUBSCRIPTION $sub';
  END IF;
END
\$\$;
SQL
EOF
  fi
}

drop_replication_slot_if_exists() {
  local pub_ip="$1" user="$2" db="$3" slot="$4"
  local psql_flags="-h 127.0.0.1 -U $user -d $db -v ON_ERROR_STOP=1 -X -q"
  if [[ "$pub_ip" == "$LOCAL_IP" ]]; then
    eval "$(password_env_for_ip "$pub_ip")" psql $psql_flags <<SQL || true
DO \$\$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_replication_slots WHERE slot_name = '$slot') THEN
    PERFORM pg_drop_replication_slot('$slot');
  END IF;
END
\$\$;
SQL
  else
    SSH root@"$pub_ip" bash -s <<EOF
set -euo pipefail
export $(password_env_for_ip "$pub_ip")
psql $psql_flags <<'SQL'
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
  local ip=$1 peer_ip=$2
  print_info "Opening port 5432 on $ip for $peer_ip..."
  if [[ "$ip" == "$peer_ip" ]]; then
    print_warn "Skipping firewall on $ip for itself."
    print_success "Firewall rule applied on $ip"
    return 0
  fi
  if [[ "$ip" == "$LOCAL_IP" ]]; then
    iptables -C INPUT -p tcp --dport 5432 -s "$peer_ip" -j ACCEPT 2>/dev/null || iptables -A INPUT -p tcp --dport 5432 -s "$peer_ip" -j ACCEPT
    command -v netfilter-persistent &> /dev/null && netfilter-persistent save || print_warn 'iptables-persistent not installed.'
  else
    SSH root@"$ip" bash -s <<EOF
iptables -C INPUT -p tcp --dport 5432 -s "$peer_ip" -j ACCEPT 2>/dev/null || iptables -A INPUT -p tcp --dport 5432 -s "$peer_ip" -j ACCEPT
command -v netfilter-persistent &> /dev/null && netfilter-persistent save || echo '⚠️ iptables-persistent not installed.'
EOF
  fi
  print_success "Firewall rule applied on $ip"
}

restart_postgres() {
  local ip=$1
  print_info "Restarting PostgreSQL on $ip..."
  if [[ "$ip" == "$LOCAL_IP" ]]; then
    systemctl restart "$PG_SERVICE"
  else
    SSH root@"$ip" "systemctl restart '$PG_SERVICE'"
  fi
  print_success "PostgreSQL restarted on $ip"
}

drop_and_create_schema_remote() {
  print_warn "About to drop and recreate 'public' schema on REMOTE. This will delete ALL data in 'public' schema!"
  read -p "Proceed? [y/N]: " confirm
  if [[ "$confirm" =~ ^[Yy]$ ]]; then
    SSH root@"$REMOTE_IP" "export PGPASSWORD='$REMOTE_PASS'; psql -h 127.0.0.1 -U '$REPL_USER' -d '$DB_NAME' -c 'DROP SCHEMA public CASCADE; CREATE SCHEMA public'"
    print_success "Schema dropped and recreated on REMOTE."
  else
    print_error "Aborted schema reset on REMOTE."
    exit 1
  fi
}

copy_schema_to_remote() {
  print_info "Copying schema from LOCAL to REMOTE using pg_dump..."
  PGPASSWORD="$LOCAL_PASS" pg_dump -h 127.0.0.1 -U "$REPL_USER" -s "$DB_NAME" | \
  PGPASSWORD="$REMOTE_PASS" psql -h "$REMOTE_IP" -U "$REPL_USER" -d "$DB_NAME"
  print_success "Schema copied to REMOTE."
}

create_publication() {
  local ip=$1 pass=$2 pub_name=$3
  print_info "Creating publication '$pub_name' on $ip..."
  if [[ "$ip" == "$LOCAL_IP" ]]; then
    PGPASSWORD="$pass" psql -h 127.0.0.1 -U "$REPL_USER" -d "$DB_NAME" <<SQLEND
DROP PUBLICATION IF EXISTS $pub_name;
CREATE PUBLICATION $pub_name FOR ALL TABLES;
SQLEND
  else
    SSH root@"$ip" bash -s <<EOF
set -euo pipefail
export PGPASSWORD='$pass'
psql -h 127.0.0.1 -U "$REPL_USER" -d "$DB_NAME" <<'SQLEND'
DROP PUBLICATION IF EXISTS $pub_name;
CREATE PUBLICATION $pub_name FOR ALL TABLES;
SQLEND
EOF
  fi
  print_success "Publication $pub_name created on $ip"
}

create_subscription_remote() {
  print_info "Creating subscription on REMOTE (to LOCAL, with data copy)..."
  local sql="CREATE SUBSCRIPTION $SUB_NAME_TO_REMOTE CONNECTION 'host=$LOCAL_IP dbname=$DB_NAME user=$REPL_USER password=$LOCAL_PASS' PUBLICATION $PUB_NAME_LOCAL WITH (origin = none, copy_data = true)"
  SSH root@"$REMOTE_IP" "export PGPASSWORD='$REMOTE_PASS'; psql -h 127.0.0.1 -U '$REPL_USER' -d '$DB_NAME' -c 'DROP SUBSCRIPTION IF EXISTS $SUB_NAME_TO_REMOTE'"
  SSH root@"$REMOTE_IP" "export PGPASSWORD='$REMOTE_PASS'; psql -h 127.0.0.1 -U '$REPL_USER' -d '$DB_NAME' -c \"$sql\""
  print_success "Subscription $SUB_NAME_TO_REMOTE created on REMOTE."
}

wait_for_sync_complete() {
  local attempts="${1:-900}" sleep_s="${2:-2}"
  print_info "Waiting for initial data sync to complete on REMOTE SERVER (all tables synced check)..."
  SSH root@"$REMOTE_IP" bash -s <<EOF
set -euo pipefail
export PGPASSWORD='$REMOTE_PASS'
ATTEMPTS=$attempts
SLEEP_S=$sleep_s
SUB_NAME="$SUB_NAME_TO_REMOTE"
DB="$DB_NAME"
USER="$REPL_USER"
for i in \$(seq 1 "\$ATTEMPTS"); do
  all_synced=\$(psql -h 127.0.0.1 -U "\$USER" -d "\$DB" -t -A -c \
    "SELECT COALESCE(bool_and(rel.srsubstate = 'r'), false)
     FROM pg_subscription_rel rel
     JOIN pg_subscription sub ON rel.srsubid = sub.oid
     WHERE sub.subname = '\$SUB_NAME';" | xargs || echo "f")
  if [[ "\$all_synced" == "t" ]]; then
    echo "__SYNC_DONE__"
    exit 0
  fi
  sleep "\$SLEEP_S"
done
exit 2
EOF
  case $? in
    0) print_success "Initial sync complete. Proceeding to reverse replication."; return 0 ;;
    2) print_error "Sync did not complete within timeout."; exit 1 ;;
    *) print_error "SSH connection failed while waiting for sync."; exit 1 ;;
  esac
}

create_subscription_local() {
  print_info "Creating subscription on LOCAL (to REMOTE, no data copy)..."
  local sql="CREATE SUBSCRIPTION $SUB_NAME_TO_LOCAL CONNECTION 'host=$REMOTE_IP dbname=$DB_NAME user=$REPL_USER password=$REMOTE_PASS' PUBLICATION $PUB_NAME_REMOTE WITH (origin = none, copy_data = false)"
  PGPASSWORD="$LOCAL_PASS" psql -h 127.0.0.1 -U "$REPL_USER" -d "$DB_NAME" -c 'DROP SUBSCRIPTION IF EXISTS '"$SUB_NAME_TO_LOCAL"
  PGPASSWORD="$LOCAL_PASS" psql -h 127.0.0.1 -U "$REPL_USER" -d "$DB_NAME" -c "$sql"
  print_success "Subscription $SUB_NAME_TO_LOCAL created on LOCAL."
}

# === EXECUTION ===
configure_postgres_conf "$LOCAL_IP"
configure_postgres_conf "$REMOTE_IP"

configure_pg_hba "$LOCAL_IP" "$REMOTE_IP"
configure_pg_hba "$REMOTE_IP" "$LOCAL_IP"

setup_firewall "$LOCAL_IP" "$REMOTE_IP"
setup_firewall "$REMOTE_IP" "$LOCAL_IP"

restart_postgres "$LOCAL_IP"
restart_postgres "$REMOTE_IP"

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
