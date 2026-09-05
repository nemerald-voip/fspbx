#!/usr/bin/env bash
# Run from the repository root as root; never connects to application services.
set -euo pipefail
pgbin=/usr/lib/postgresql/17/bin
php -r 'foreach ([16541,16542,16381,16382] as $port) { $socket = @stream_socket_server("tcp://127.0.0.1:$port", $code, $error); if (!$socket) { fwrite(STDERR, "Lab port $port is unavailable.\n"); exit(1); } fclose($socket); }'
lab=$(mktemp -d /tmp/fspbx-coordination-lab.XXXXXX)
chown postgres:postgres "$lab"
lab_redis_a=''
lab_redis_b=''
cleanup() {
    if [[ -f "$lab/a/postmaster.pid" ]]; then runuser -u postgres -- "$pgbin/pg_ctl" -D "$lab/a" -m immediate stop; fi
    if [[ -f "$lab/b/postmaster.pid" ]]; then runuser -u postgres -- "$pgbin/pg_ctl" -D "$lab/b" -m immediate stop; fi
    if [[ -n "$lab_redis_a" ]]; then kill "$lab_redis_a" 2>/dev/null || true; fi
    if [[ -n "$lab_redis_b" ]]; then kill "$lab_redis_b" 2>/dev/null || true; fi
    echo "Stopped disposable services. Lab evidence retained at $lab"
}
trap cleanup EXIT
runuser -u postgres -- "$pgbin/initdb" -D "$lab/a" --no-locale --encoding=UTF8 --auth=trust
runuser -u postgres -- "$pgbin/initdb" -D "$lab/b" --no-locale --encoding=UTF8 --auth=trust
runuser -u postgres -- "$pgbin/pg_ctl" -D "$lab/a" -l "$lab/a.log" -o "-p 16541 -h 127.0.0.1 -k $lab -c wal_level=logical -c max_replication_slots=10" start
runuser -u postgres -- "$pgbin/pg_ctl" -D "$lab/b" -l "$lab/b.log" -o "-p 16542 -h 127.0.0.1 -k $lab -c wal_level=logical -c max_replication_slots=10" start
redis-server --bind 127.0.0.1 --port 16381 --dir "$lab" --logfile "$lab/redis-a.log" --save '' --appendonly no &
lab_redis_a=$!
redis-server --bind 127.0.0.1 --port 16382 --dir "$lab" --logfile "$lab/redis-b.log" --save '' --appendonly no &
lab_redis_b=$!
FSPBX_COORDINATION_LAB_DIR="$lab" php artisan test --do-not-cache-result tests/Unit/ScheduledJobPostgresReplicationTest.php
