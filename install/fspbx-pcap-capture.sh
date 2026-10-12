#!/bin/bash
# FS PBX PCAP capture service. Runs as root under Supervisor.
#
# tcpdump runs only while FS PBX has an active PCAP session, which the app
# signals by creating $FLAG. Packets are written as www-data into one-minute
# files that the app cuts into one PCAP per call and then deletes.

BASE=/var/lib/fspbx-pcap
SPOOL="$BASE/spool"
FLAG="$BASE/capture.on"
# Written by the app when SIP Capture (HEP over UDP) is on. HEP carries
# readable SIP for phones registered over TLS.
HEP_PORT_FILE="$BASE/hep.port"
RTP_RANGE="${FSPBX_PCAP_RTP_RANGE:-16384-32768}"
# Keep the SIP range in sync with PcapService::SIP_PORT_RANGE.
BASE_FILTER="(tcp and portrange 5060-5091) or (udp and (portrange 5060-5091 or portrange $RTP_RANGE))"

pid=""
running_hep=""

hep_port() {
    local port=""
    if [ -f "$HEP_PORT_FILE" ] && [ ! -L "$HEP_PORT_FILE" ]; then
        port=$(head -c 6 "$HEP_PORT_FILE" | tr -cd '0-9')
    fi
    if [ -n "$port" ] && [ "$port" -ge 1 ] && [ "$port" -le 65535 ]; then
        echo "$port"
    fi
}

stop_capture() {
    if [ -n "$pid" ]; then
        kill "$pid" 2>/dev/null
        wait "$pid" 2>/dev/null
        pid=""
        echo "$(date -Is) capture stopped"
    fi
}

trap 'stop_capture; exit 0' TERM INT

mkdir -p "$SPOOL"
chown www-data:www-data "$BASE" "$SPOOL"
chmod 0770 "$BASE" "$SPOOL"

while true; do
    if [ -f "$FLAG" ] && [ ! -L "$FLAG" ]; then
        hep=$(hep_port)
        # Restart when SIP Capture's port changes.
        if [ -n "$pid" ] && [ "$hep" != "$running_hep" ]; then
            stop_capture
        fi
        if [ -z "$pid" ] || ! kill -0 "$pid" 2>/dev/null; then
            filter="$BASE_FILTER"
            if [ -n "$hep" ]; then
                filter="$filter or (udp and dst port $hep)"
            fi
            # -Z drops to www-data before any output file is opened.
            tcpdump -i any -n -U -s 0 -B 16384 -G 60 -Z www-data -w "$SPOOL/%s.pcap" "$filter" &
            pid=$!
            running_hep="$hep"
            echo "$(date -Is) capture started${hep:+ (with SIP Capture on port $hep)}"
        fi
    else
        stop_capture
    fi
    sleep 5 &
    wait $!
done
