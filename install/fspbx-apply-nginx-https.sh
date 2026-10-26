#!/usr/bin/env bash
#
# Turns the FS PBX HTTPS hardening on or off.
#
# The hardening lives in one small nginx snippet, /etc/nginx/snippets/fspbx-https.conf,
# which the HTTPS server block of the FS PBX site includes. "off" writes exactly the TLS
# directives older installs already had; "on" writes stricter TLS settings and the HSTS header.
#
# Usage: fspbx-apply-nginx-https on|off|init|status [--allow-self-signed]
#
# Runs as root. FS PBX calls it through sudo from the System Settings page.

set -euo pipefail

SNIPPET="/etc/nginx/snippets/fspbx-https.conf"
SITE="/etc/nginx/sites-available/fspbx.conf"
CERTIFICATE="/etc/nginx/ssl/fullchain.pem"

action="${1:-}"
allow_self_signed=false
[[ "${2:-}" == "--allow-self-signed" ]] && allow_self_signed=true

fail() { echo "$1" >&2; exit "${2:-1}"; }

[[ $EUID -eq 0 ]] || fail "This command must run as root." 1

snippet_contents() {
    case "$1" in
        off)
            cat <<'EOF'
# fspbx-https: off
# Managed by FS PBX (System Settings > Web Server). Do not edit by hand.
ssl_protocols           TLSv1.2;
ssl_ciphers             HIGH:!ADH:!MD5:!aNULL;
EOF
            ;;
        on)
            cat <<'EOF'
# fspbx-https: on
# Managed by FS PBX (System Settings > Web Server). Do not edit by hand.
ssl_protocols             TLSv1.2 TLSv1.3;
ssl_ciphers               ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305;
ssl_prefer_server_ciphers off;
ssl_session_cache         shared:fspbx_ssl:10m;
ssl_session_timeout       1d;
ssl_session_tickets       off;
add_header Strict-Transport-Security "max-age=31536000" always;
EOF
            ;;
    esac
}

current_state() {
    if [[ ! -f "$SNIPPET" ]]; then
        echo "missing"
    elif grep -q '^# fspbx-https: on$' "$SNIPPET"; then
        echo "on"
    else
        echo "off"
    fi
}

certificate_is_self_signed() {
    [[ -r "$CERTIFICATE" ]] || return 1
    local issuer subject
    issuer="$(openssl x509 -in "$CERTIFICATE" -noout -issuer -nameopt RFC2253 2>/dev/null | sed 's/^issuer=//')"
    subject="$(openssl x509 -in "$CERTIFICATE" -noout -subject -nameopt RFC2253 2>/dev/null | sed 's/^subject=//')"
    [[ -n "$issuer" && "$issuer" == "$subject" ]]
}

apply() {
    local want="$1" workdir had_snippet=false

    grep -q 'include snippets/fspbx-https.conf;' "$SITE" 2>/dev/null \
        || fail "The FS PBX nginx site does not include snippets/fspbx-https.conf yet. Run the FS PBX update first." 2

    if [[ "$want" == "on" && "$allow_self_signed" == false ]] && certificate_is_self_signed; then
        fail "The nginx certificate is self-signed. Browsers cannot bypass the warning once HSTS is on. Install a trusted certificate first." 3
    fi

    workdir="$(mktemp -d /tmp/fspbx-nginx-https.XXXXXX)"
    trap 'rm -rf "$workdir"' RETURN

    snippet_contents "$want" > "$workdir/new.conf"

    if [[ -f "$SNIPPET" ]]; then
        cp -a "$SNIPPET" "$workdir/previous.conf"
        had_snippet=true
        if cmp -s "$workdir/new.conf" "$SNIPPET"; then
            echo "OK $want (unchanged)"
            return 0
        fi
    fi

    install -d -m 0755 "$(dirname "$SNIPPET")"
    install -m 0644 "$workdir/new.conf" "$SNIPPET"

    restore() {
        if [[ "$had_snippet" == true ]]; then
            install -m 0644 "$workdir/previous.conf" "$SNIPPET"
        else
            rm -f "$SNIPPET"
        fi
    }

    if ! nginx -t >"$workdir/test.log" 2>&1; then
        restore
        cat "$workdir/test.log" >&2
        fail "nginx rejected the new configuration. The previous one was restored." 4
    fi

    if ! systemctl reload nginx >"$workdir/reload.log" 2>&1; then
        restore
        cat "$workdir/reload.log" >&2
        systemctl reload nginx >/dev/null 2>&1 || true
        fail "nginx could not be reloaded. The previous configuration was restored." 5
    fi

    echo "OK $want"
}

case "$action" in
    on|off)
        apply "$action"
        ;;
    init)
        # Write the default (off) snippet only when there is none, so nothing changes on a fresh install.
        if [[ -f "$SNIPPET" ]]; then
            echo "OK $(current_state) (unchanged)"
        else
            install -d -m 0755 "$(dirname "$SNIPPET")"
            snippet_contents off > "$SNIPPET.new"
            install -m 0644 "$SNIPPET.new" "$SNIPPET"
            rm -f "$SNIPPET.new"
            echo "OK off"
        fi
        ;;
    status)
        current_state
        ;;
    *)
        fail "Usage: $(basename "$0") on|off|init|status [--allow-self-signed]" 64
        ;;
esac
