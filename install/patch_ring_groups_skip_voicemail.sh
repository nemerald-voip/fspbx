#!/usr/bin/env bash
# Patch FusionPBX-shipped ring_groups/index.lua so the per-member
# `destination_ignore_voicemail` column flows from the database into the
# per-leg dial-string, suppressing voicemail / no-answer-forward pickup
# for that member.
#
# Idempotent: re-running is a no-op once the patches are in place.
# Safe to run on every install / upgrade.

set -Eeuo pipefail

LUA="${1:-/usr/share/freeswitch/scripts/app/ring_groups/index.lua}"

print_success() { echo -e "\e[32m$1\e[0m"; }
print_warn()    { echo -e "\e[33m$1\e[0m"; }
print_error()   { echo -e "\e[31m$1\e[0m" >&2; }

if [[ ! -f "$LUA" ]]; then
    print_warn "ring_groups lua not found at $LUA — skipping (FreeSWITCH not installed yet?)"
    exit 0
fi

if grep -q "destination_ignore_voicemail" "$LUA"; then
    print_success "ring_groups lua already patched for Skip Voicemail — skipping"
    exit 0
fi

print_success "Patching $LUA for ring-group Skip Voicemail toggle..."

python3 - "$LUA" <<'PY'
import re, sys, pathlib

path = pathlib.Path(sys.argv[1])
src = path.read_text()

# 1. Add destination_ignore_voicemail to the SELECT clause that
#    pulls per-destination columns out of v_ring_group_destinations.
sql_pattern = re.compile(
    r"(d\.destination_number, d\.destination_timeout, d\.destination_prompt,)"
)
if not sql_pattern.search(src):
    print("ERROR: could not locate destination SELECT clause in lua", file=sys.stderr)
    sys.exit(1)
src = sql_pattern.sub(r"\1 d.destination_ignore_voicemail,", src, count=1)

# 2. Inject voicemail-suppression vars into the per-leg dial-string when
#    the member has destination_ignore_voicemail set. Anchor on the
#    `dialed_extension=` concatenation that builds dial_string_user for
#    the user_exists branch.
inject_anchor = re.compile(
    r"(\n([\t ]+)dial_string_user = dial_string_user \.\. \"dialed_extension=\" \.\. row\.destination_number)"
)
m = inject_anchor.search(src)
if not m:
    print("ERROR: could not locate dial_string_user dialed_extension concat", file=sys.stderr)
    sys.exit(1)

indent = m.group(2)
block = (
    f"\n{indent}-- BEGIN fspbx ring_group_skip_voicemail\n"
    f"{indent}if (row.destination_ignore_voicemail == \"t\"\n"
    f"{indent}\t\tor row.destination_ignore_voicemail == true\n"
    f"{indent}\t\tor row.destination_ignore_voicemail == \"true\"\n"
    f"{indent}\t\tor row.destination_ignore_voicemail == \"1\"\n"
    f"{indent}\t\tor row.destination_ignore_voicemail == 1) then\n"
    f"{indent}\tdial_string_user = dial_string_user\n"
    f"{indent}\t\t.. \"voicemail_enabled=false,\"\n"
    f"{indent}\t\t.. \"forward_no_answer_enabled=false,\"\n"
    f"{indent}\t\t.. \"forward_busy_enabled=false,\"\n"
    f"{indent}\t\t.. \"bypass_no_answer_forward=true,\"\n"
    f"{indent}\t\t.. \"hangup_after_bridge=false,\";\n"
    f"{indent}end\n"
    f"{indent}-- END fspbx ring_group_skip_voicemail"
)
src = inject_anchor.sub(block + r"\1", src, count=1)

path.write_text(src)
print(f"patched {path}")
PY

print_success "ring_groups lua patched."

if command -v fs_cli >/dev/null 2>&1; then
    fs_cli -x "reloadxml" >/dev/null 2>&1 || true
    print_success "Reloaded FreeSWITCH XML."
fi
