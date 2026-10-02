#!/usr/bin/env bash
# Creates a private PostgreSQL cluster and never reads application credentials.
set -euo pipefail
cd "$(dirname "$0")/../.."
pgbin=/usr/lib/postgresql/17/bin
lab=$(mktemp -d /tmp/fspbx-messaging-migration.XXXXXX)
chown postgres:postgres "$lab"
cleanup() {
    if [[ -f "$lab/data/postmaster.pid" ]]; then
        runuser -u postgres -- "$pgbin/pg_ctl" -D "$lab/data" -m immediate stop
    fi
    echo "Stopped disposable PostgreSQL. Evidence retained at $lab"
}
trap cleanup EXIT
runuser -u postgres -- "$pgbin/initdb" -D "$lab/data" --no-locale --encoding=UTF8 --auth=trust > "$lab/initdb.log"
runuser -u postgres -- "$pgbin/pg_ctl" -D "$lab/data" -l "$lab/postgres.log" \
    -o "-p 16549 -h '' -k $lab" start
runuser -u postgres -- "$pgbin/createdb" -h "$lab" -p 16549 fspbx_migration_test
FSPBX_MESSAGING_MIGRATION_PG_SOCKET="$lab" vendor/bin/phpunit tests/Unit/SharedMessagingMigrationTest.php --do-not-cache-result
