#!/usr/bin/env bash

set -euo pipefail

FULLCHAIN_SOURCE="${1:-}"
PRIVATE_KEY_SOURCE="${2:-}"
CERTIFICATE_PATH="${FSPBX_NGINX_CERTIFICATE_PATH:-/etc/nginx/ssl/fullchain.pem}"
PRIVATE_KEY_PATH="${FSPBX_NGINX_PRIVATE_KEY_PATH:-/etc/nginx/ssl/private/privkey.pem}"

[[ $EUID -eq 0 ]] || { echo "Certificate deployment must run as root." >&2; exit 1; }
[[ -r "$FULLCHAIN_SOURCE" ]] || { echo "Certificate file is not readable: $FULLCHAIN_SOURCE" >&2; exit 1; }
[[ -r "$PRIVATE_KEY_SOURCE" ]] || { echo "Private key is not readable: $PRIVATE_KEY_SOURCE" >&2; exit 1; }

openssl x509 -in "$FULLCHAIN_SOURCE" -noout >/dev/null
openssl pkey -in "$PRIVATE_KEY_SOURCE" -noout >/dev/null

certificate_public_key="$(openssl x509 -in "$FULLCHAIN_SOURCE" -pubkey -noout | openssl pkey -pubin -outform DER 2>/dev/null | sha256sum | awk '{print $1}')"
private_public_key="$(openssl pkey -in "$PRIVATE_KEY_SOURCE" -pubout -outform DER 2>/dev/null | sha256sum | awk '{print $1}')"

if [[ -z "$certificate_public_key" || "$certificate_public_key" != "$private_public_key" ]]; then
    echo "The certificate and private key do not match." >&2
    exit 1
fi

install -d -m 0755 "$(dirname "$CERTIFICATE_PATH")"
install -d -m 0700 "$(dirname "$PRIVATE_KEY_PATH")"

workdir="$(mktemp -d /tmp/fspbx-nginx-certificate.XXXXXX)"
cleanup() { rm -rf "$workdir"; }
trap cleanup EXIT

install -m 0644 "$FULLCHAIN_SOURCE" "$workdir/fullchain.pem"
install -m 0600 "$PRIVATE_KEY_SOURCE" "$workdir/privkey.pem"

had_certificate=false
had_private_key=false
if [[ -f "$CERTIFICATE_PATH" ]]; then
    cp -a "$CERTIFICATE_PATH" "$workdir/fullchain.previous.pem"
    had_certificate=true
fi
if [[ -f "$PRIVATE_KEY_PATH" ]]; then
    cp -a "$PRIVATE_KEY_PATH" "$workdir/privkey.previous.pem"
    had_private_key=true
fi

install -m 0644 "$workdir/fullchain.pem" "$CERTIFICATE_PATH"
install -m 0600 "$workdir/privkey.pem" "$PRIVATE_KEY_PATH"

rollback() {
    if [[ "$had_certificate" == true ]]; then
        install -m 0644 "$workdir/fullchain.previous.pem" "$CERTIFICATE_PATH"
    else
        rm -f "$CERTIFICATE_PATH"
    fi

    if [[ "$had_private_key" == true ]]; then
        install -m 0600 "$workdir/privkey.previous.pem" "$PRIVATE_KEY_PATH"
    else
        rm -f "$PRIVATE_KEY_PATH"
    fi

    nginx -t >/dev/null 2>&1 && systemctl reload nginx >/dev/null 2>&1 || true
}

if ! nginx -t; then
    rollback
    echo "Nginx rejected the new certificate; the previous certificate was restored." >&2
    exit 1
fi

if ! systemctl reload nginx; then
    rollback
    echo "Nginx could not reload the new certificate; the previous certificate was restored." >&2
    exit 1
fi
