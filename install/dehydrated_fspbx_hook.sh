#!/usr/bin/env bash

set -euo pipefail

CONFIG_FILE="${FSPBX_CERT_CONFIG:-/etc/dehydrated/fspbx-deployment.conf}"
DEPLOY_HELPER="${FSPBX_DEPLOY_HELPER:-/usr/local/sbin/fspbx-deploy-nginx-certificate}"

[[ -r "$CONFIG_FILE" ]] || { echo "FS PBX certificate deployment config is missing." >&2; exit 1; }
# shellcheck source=/dev/null
source "$CONFIG_FILE"

MODE="${MODE:-single}"
WELLKNOWN_DIR="${WELLKNOWN_DIR:-/var/www/fspbx/public/.well-known/acme-challenge}"

validate_token() {
    [[ "${1:-}" =~ ^[A-Za-z0-9_-]{1,255}$ ]]
}

ssh_peer() {
    ssh -o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new \
        -p "$PEER_SSH_PORT" "$PEER_SSH_TARGET" "$@"
}

deploy_challenge() {
    local domain="$1" token="$2" value="$3"

    [[ "$MODE" == "redundant" ]] || return 0
    validate_token "$token" || { echo "Invalid ACME challenge token name." >&2; return 1; }

    ssh_peer install -d -m 0755 "$WELLKNOWN_DIR"
    printf '%s' "$value" | ssh_peer sh -c "'umask 022; cat > \"$WELLKNOWN_DIR/$token\"'"
}

clean_challenge() {
    local domain="$1" token="$2" value="$3"

    [[ "$MODE" == "redundant" ]] || return 0
    validate_token "$token" || { echo "Invalid ACME challenge token name." >&2; return 1; }

    ssh_peer rm -f "$WELLKNOWN_DIR/$token"
}

deploy_files() {
    local domain="$1" keyfile="$2" certfile="$3" fullchainfile="$4" chainfile="$5"

    "$DEPLOY_HELPER" "$fullchainfile" "$keyfile"

    if [[ "$MODE" != "redundant" ]]; then
        return 0
    fi

    [[ "$domain" =~ ^[A-Za-z0-9.-]+$ ]] || { echo "Invalid certificate directory name." >&2; return 1; }

    local certdir
    certdir="$(dirname "$keyfile")"
    ssh_peer install -d -m 0700 "$certdir"
    rsync -a --delete -e "ssh -o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new -p $PEER_SSH_PORT" \
        "$certdir/" "$PEER_SSH_TARGET:$certdir/"
    ssh_peer "$DEPLOY_HELPER" "$certdir/fullchain.pem" "$certdir/privkey.pem"
}

deploy_cert() {
    deploy_files "$1" "$2" "$3" "$4" "$5"
}

unchanged_cert() {
    deploy_files "$1" "$2" "$3" "$4" "$5"
}

handler="${1:-}"
shift || true

case "$handler" in
    deploy_challenge|clean_challenge|deploy_cert|unchanged_cert)
        "$handler" "$@"
        ;;
    sync_cert|deploy_ocsp|invalid_challenge|request_failure|generate_csr|startup_hook|exit_hook)
        ;;
    *)
        # Dehydrated deliberately calls an unknown hook to verify that hook
        # scripts safely ignore events introduced by newer versions.
        ;;
esac
