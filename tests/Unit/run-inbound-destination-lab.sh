#!/usr/bin/env bash
# Disposable PostgreSQL and a standalone Lua interpreter. No live calls or app DB.
set -euo pipefail
inbound_lab=$(mktemp -d /tmp/fspbx-inbound.XXXXXX)
inbound_pg_bin=/usr/lib/postgresql/17/bin
chown postgres:postgres "$inbound_lab"
cleanup() {
    if [[ -f "$inbound_lab/db/postmaster.pid" ]]; then
        runuser -u postgres -- "$inbound_pg_bin/pg_ctl" -D "$inbound_lab/db" -m immediate stop
    fi
    echo "Disposable PostgreSQL stopped. Evidence: $inbound_lab"
}
trap cleanup EXIT
runuser -u postgres -- "$inbound_pg_bin/initdb" -D "$inbound_lab/db" --no-locale --encoding=UTF8 --auth=trust > "$inbound_lab/init.log"
runuser -u postgres -- "$inbound_pg_bin/pg_ctl" -D "$inbound_lab/db" -l "$inbound_lab/postgres.log" \
    -o "-p 16544 -h '' -k $inbound_lab" start
export FSPBX_INBOUND_LAB="$inbound_lab"
php tests/Unit/inbound_destination_probe.php setup
"${LUA_BIN:-lua}" tests/Unit/inbound_destination_test.lua
