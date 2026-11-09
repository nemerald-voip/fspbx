#!/usr/bin/env bash
# Private PostgreSQL cluster; never reads application database credentials.
set -euo pipefail
cd "$(dirname "$0")/../.."
pgbin=/usr/lib/postgresql/17/bin
lab=$(mktemp -d /tmp/fspbx-flags-lab.XXXXXX)
chown postgres:postgres "$lab"
cleanup() {
    if [[ -f "$lab/data/postmaster.pid" ]]; then
        runuser -u postgres -- "$pgbin/pg_ctl" -D "$lab/data" -m immediate stop
    fi
    echo "Stopped disposable PostgreSQL. Evidence retained at $lab"
}
trap cleanup EXIT
runuser -u postgres -- "$pgbin/initdb" -D "$lab/data" --no-locale --encoding=UTF8 --auth=trust > "$lab/initdb.log"
runuser -u postgres -- "$pgbin/pg_ctl" -D "$lab/data" -l "$lab/postgres.log" -o "-p 16559 -h '' -k $lab" start
runuser -u postgres -- "$pgbin/createdb" -h "$lab" -p 16559 flags_test
runuser -u postgres -- "$pgbin/psql" -h "$lab" -p 16559 -d flags_test -c 'CREATE EXTENSION "uuid-ossp"' > /dev/null
FSPBX_FLAGS_PG_SOCKET="$lab" vendor/bin/phpunit tests/Unit/FlagFeatureIntegrationTest.php --do-not-cache-result
