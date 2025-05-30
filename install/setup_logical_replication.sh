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

# === PROMPTS ===
print_info "=== Logical Replication Bi-directional Setup ==="
read -p "Enter REMOTE SERVER IP: " REMOTE_IP
read -p "Enter LOCAL SERVER IP (this machine): " LOCAL_IP
read -s -p "Enter LOCAL Server password for 'fusionpbx': " LOCAL_PASS; echo ""
read -s -p "Enter REMOTE Server password for 'fusionpbx': " REMOTE_PASS; echo ""

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
        ssh root@$ip 'bash -s' <<EOF
sed -i "s/^#*listen_addresses.*/listen_addresses = '*'/" "$PG_CONF"
sed -i "s/^#*wal_level.*/wal_level = logical/" "$PG_CONF"
sed -i "s/^#*track_commit_timestamp.*/track_commit_timestamp = on/" "$PG_CONF"
sed -i "s/^#*max_wal_senders.*/max_wal_senders = 10/" "$PG_CONF"
sed -i "s/^#*max_replication_slots.*/max_replication_slots = 48/" "$PG_CONF"
sed -i "s/^#*max_worker_processes.*/max_worker_processes = 48/" "$PG_CONF"
EOF
    fi
    print_success "postgresql.conf configured on $ip"
}

configure_pg_hba() {
    local ip=$1
    local peer_ip=$2
    print_info "Configuring pg_hba.conf on $ip..."
    local LINES="
# Logical replication peer access
host    replication     $REPL_USER     $peer_ip/32       md5
host    all             all            $peer_ip/32       md5
"
    if [[ "$ip" == "$LOCAL_IP" ]]; then
        LINES="$LINES
# Allow local TCP connections for schema copy and admin
host    all   all   127.0.0.1/32   md5
host    all   all   $LOCAL_IP/32   md5
"
        echo "$LINES" >> $PG_HBA
    else
        ssh root@$ip 'bash -s' <<EOF
echo "$LINES" >> "$PG_HBA"
EOF
    fi
    print_success "pg_hba.conf configured on $ip"
}

setup_firewall() {
    local ip=$1
    local peer_ip=$2
    print_info "Opening port 5432 on $ip for $peer_ip..."
    if [[ "$ip" == "$LOCAL_IP" ]]; then
        iptables -C INPUT -p tcp --dport 5432 -s $peer_ip -j ACCEPT 2>/dev/null || iptables -A INPUT -p tcp --dport 5432 -s $peer_ip -j ACCEPT
        command -v netfilter-persistent &> /dev/null && netfilter-persistent save || print_warn 'iptables-persistent not installed.'
    else
        ssh root@$ip 'bash -s' <<EOF
iptables -C INPUT -p tcp --dport 5432 -s $peer_ip -j ACCEPT 2>/dev/null || iptables -A INPUT -p tcp --dport 5432 -s $peer_ip -j ACCEPT
command -v netfilter-persistent &> /dev/null && netfilter-persistent save || echo '⚠️ iptables-persistent not installed.'
EOF
    fi
    print_success "Firewall rule applied on $ip"
}

restart_postgres() {
    local ip=$1
    print_info "Restarting PostgreSQL on $ip..."
    if [[ "$ip" == "$LOCAL_IP" ]]; then
        systemctl restart $PG_SERVICE
    else
        ssh root@$ip "systemctl restart $PG_SERVICE"
    fi
    print_success "PostgreSQL restarted on $ip"
}

drop_and_create_schema_remote() {
    print_warn "About to drop and recreate 'public' schema on REMOTE. This will delete ALL data in 'public' schema!"
    read -p "Proceed? [y/N]: " confirm
    if [[ "$confirm" =~ ^[Yy]$ ]]; then
        ssh root@$REMOTE_IP "PGPASSWORD='$REMOTE_PASS' psql -h 127.0.0.1 -U $REPL_USER -d $DB_NAME -c 'DROP SCHEMA public CASCADE; CREATE SCHEMA public'"
        print_success "Schema dropped and recreated on REMOTE."
    else
        print_error "Aborted schema reset on REMOTE."
        exit 1
    fi
}

copy_schema_to_remote() {
    print_info "Copying schema from LOCAL to REMOTE using pg_dump..."
    PGPASSWORD="$LOCAL_PASS" pg_dump -h "$LOCAL_IP" -U "$REPL_USER" -s "$DB_NAME" | \
    PGPASSWORD="$REMOTE_PASS" psql -h "$REMOTE_IP" -U "$REPL_USER" -d "$DB_NAME"
    print_success "Schema copied to REMOTE."
}

create_publication() {
    local ip=$1
    local pass=$2
    local pub_name=$3
    print_info "Creating publication '$pub_name' on $ip..."
    if [[ "$ip" == "$LOCAL_IP" ]]; then
        PGPASSWORD="$pass" psql -h $LOCAL_IP -U "$REPL_USER" -d "$DB_NAME" <<SQLEND
DROP PUBLICATION IF EXISTS $pub_name;
CREATE PUBLICATION $pub_name FOR ALL TABLES;
SQLEND
    else
        ssh root@$ip bash <<EOF
export PGPASSWORD='$pass'
psql -h 127.0.0.1 -U $REPL_USER -d $DB_NAME <<SQLEND
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
    ssh root@$REMOTE_IP "PGPASSWORD='$REMOTE_PASS' psql -h 127.0.0.1 -U $REPL_USER -d $DB_NAME -c 'DROP SUBSCRIPTION IF EXISTS $SUB_NAME_TO_REMOTE'"
    ssh root@$REMOTE_IP "PGPASSWORD='$REMOTE_PASS' psql -h 127.0.0.1 -U $REPL_USER -d $DB_NAME -c \"$sql\""
    print_success "Subscription $SUB_NAME_TO_REMOTE created on REMOTE."
}

wait_for_sync_complete() {
    local attempts=300
    print_info "Waiting for initial data sync to complete on REMOTE SERVER (all tables synced check)..."
    for ((i=1; i<=$attempts; i++)); do
        all_synced=$(ssh root@$REMOTE_IP "PGPASSWORD='$REMOTE_PASS' psql -h 127.0.0.1 -U $REPL_USER -d $DB_NAME -t -A -c \"SELECT bool_and(rel.srsubstate = 'r') AS all_tables_synced FROM pg_subscription_rel rel JOIN pg_subscription sub ON rel.srsubid = sub.oid WHERE sub.subname = '$SUB_NAME_TO_REMOTE';\"" | xargs)
        if [[ "$all_synced" == "t" ]]; then
            print_success "Initial sync complete. Proceeding to reverse replication."
            return 0
        fi
        sleep 15
    done
    print_error "Sync did not complete within timeout."
    exit 1
}


create_subscription_local() {
    print_info "Creating subscription on LOCAL (to REMOTE, no data copy)..."
    local sql="CREATE SUBSCRIPTION $SUB_NAME_TO_LOCAL CONNECTION 'host=$REMOTE_IP dbname=$DB_NAME user=$REPL_USER password=$REMOTE_PASS' PUBLICATION $PUB_NAME_REMOTE WITH (origin = none, copy_data = false)"
    PGPASSWORD="$LOCAL_PASS" psql -h $LOCAL_IP -U $REPL_USER -d $DB_NAME -c 'DROP SUBSCRIPTION IF EXISTS '"$SUB_NAME_TO_LOCAL"
    PGPASSWORD="$LOCAL_PASS" psql -h $LOCAL_IP -U $REPL_USER -d $DB_NAME -c "$sql"
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

create_subscription_remote
wait_for_sync_complete
create_subscription_local

print_success "Bi-directional logical replication is now set up between LOCAL and REMOTE."
