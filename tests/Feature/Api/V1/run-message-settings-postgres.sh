#!/usr/bin/env bash
# Exercise Message Settings HTTP endpoints on a private PostgreSQL cluster.
set -euo pipefail
cd "$(dirname "$0")/../../../.."
lab=$(mktemp -d /tmp/fspbx-message-settings.XXXXXX)
pgbin=/usr/lib/postgresql/17/bin
chown postgres:postgres "$lab"
cleanup() {
    if [[ -f "$lab/data/postmaster.pid" ]]; then
        runuser -u postgres -- "$pgbin/pg_ctl" -D "$lab/data" -m immediate stop
    fi
    echo "Disposable PostgreSQL stopped. Evidence: $lab"
}
trap cleanup EXIT
runuser -u postgres -- "$pgbin/initdb" -D "$lab/data" --no-locale --encoding=UTF8 --auth=trust > "$lab/init.log"
runuser -u postgres -- "$pgbin/pg_ctl" -D "$lab/data" -l "$lab/postgres.log" \
    -o "-p 16551 -h '' -k $lab" start
FSPBX_MESSAGE_SETTINGS_LAB="$lab" vendor/bin/phpunit --stderr --do-not-cache-result tests/Feature/Api/V1/MessageSettingsCrudTest.php
