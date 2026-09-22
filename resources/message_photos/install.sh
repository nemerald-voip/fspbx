#!/bin/sh
# Explicit, administrator-run installation. Does not alter web/PHP services.
set -eu
test "$(id -u)" = 0 || { echo 'Run as root.' >&2; exit 1; }
source_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
apt-get install -y --no-install-recommends libvips-tools python3-venv
install -d -m 0755 /opt/fspbx-photo-worker
python3 -m venv /opt/fspbx-photo-worker/venv
/opt/fspbx-photo-worker/venv/bin/pip install 'pyvips==3.0.0'
install -m 0644 "$source_dir/worker.py" /opt/fspbx-photo-worker/worker.py
install -m 0644 "$source_dir/fspbx-photo-compression.service" /etc/systemd/system/fspbx-photo-compression.service
systemctl daemon-reload
systemctl enable --now fspbx-photo-compression.service
systemctl restart fspbx-photo-compression.service
systemctl is-active fspbx-photo-compression.service
