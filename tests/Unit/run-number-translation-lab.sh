#!/usr/bin/env bash
# Disposable PostgreSQL only. Never connects to application databases or FreeSWITCH.
set -euo pipefail
translation_lab=$(mktemp -d /tmp/fspbx-number-translations.XXXXXX)
translation_pg_bin=/usr/lib/postgresql/17/bin
chown postgres:postgres "$translation_lab"
cleanup() {
    if [[ -f "$translation_lab/db/postmaster.pid" ]]; then
        runuser -u postgres -- "$translation_pg_bin/pg_ctl" -D "$translation_lab/db" -m immediate stop
    fi
    echo "Disposable PostgreSQL stopped. Evidence: $translation_lab"
}
trap cleanup EXIT
runuser -u postgres -- "$translation_pg_bin/initdb" -D "$translation_lab/db" --no-locale --encoding=UTF8 --auth=trust > "$translation_lab/init.log"
runuser -u postgres -- "$translation_pg_bin/pg_ctl" -D "$translation_lab/db" -l "$translation_lab/postgres.log" \
    -o "-p 16543 -h '' -k $translation_lab" start
FSPBX_NUMBER_TRANSLATION_LAB="$translation_lab" APP_CONFIG_CACHE="$translation_lab/no-config-cache" APP_ROUTES_CACHE="$translation_lab/no-routes-cache" LOG_CHANNEL=stderr \
    vendor/bin/phpunit --do-not-cache-result tests/Unit/NumberTranslationPostgresTest.php
