#!/usr/bin/env python3
"""Disposable FreeSWITCH lab. Run only inside the documented isolated unit."""
import argparse
import concurrent.futures
import datetime
import json
import os
import pwd
from pathlib import Path
import re
import shutil
import socket
import subprocess
import threading
import time
import uuid

SOURCE = Path(__file__).resolve().parent
STOP = threading.Event()
TUNING = {"MALLOC_ARENA_MAX": "8", "MALLOC_TRIM_THRESHOLD_": "131072",
          "MALLOC_MMAP_THRESHOLD_": "131072"}
CASES = {
    "normal": ("none", 0, 10, {}),
    "long_normal": ("none", 0, 300, {}),
    "long_kill": ("none", 0, 300, {}),
    "getvar": ("uuid_getvar", 10000, 180, {}),
    "getvar_half": ("uuid_getvar", 5000, 180, {}),
    "eval": ("eval", 10000, 180, {}),
    "tuned": ("uuid_getvar", 10000, 180, TUNING),
    "arena_only": ("uuid_getvar", 10000, 180, {"MALLOC_ARENA_MAX": "8"}),
    "trim_only": ("uuid_getvar", 10000, 180, {"MALLOC_TRIM_THRESHOLD_": "131072"}),
    "mmap_only": ("uuid_getvar", 10000, 180, {"MALLOC_MMAP_THRESHOLD_": "131072"}),
    "survivors_default": ("uuid_getvar", 10000, 180, {}),
    "survivors_tuned": ("uuid_getvar", 10000, 180, TUNING),
    "bridge_normal": ("none", 0, 300, {}),
    "bridge_kill": ("none", 0, 300, {}),
}


def stamp():
    return datetime.datetime.now(datetime.timezone.utc).isoformat()


class ESL:
    def __init__(self, port, password):
        self.sock = socket.create_connection(("127.0.0.1", port), timeout=15)
        self.file = self.sock.makefile("rb")
        self.read()
        self.sock.sendall(f"auth {password}\n\n".encode())
        assert "+OK" in self.read()[0].get("Reply-Text", "")

    def read(self):
        headers = {}
        while True:
            line = self.file.readline()
            if not line:
                raise EOFError("ESL connection closed")
            if line in (b"\n", b"\r\n"):
                break
            key, value = line.decode().rstrip().split(":", 1)
            headers[key] = value.strip()
        size = int(headers.get("Content-Length", 0))
        body = self.file.read(size).decode() if size else ""
        return headers, body

    def api(self, command):
        self.sock.sendall(f"api {command}\n\n".encode())
        headers, body = self.read()
        return body if body else headers.get("Reply-Text", "")

    def close(self):
        self.file.close()
        self.sock.close()


def counters(path):
    result = {}
    for line in Path(path).read_text().splitlines():
        key, value = line.split(":", 1) if ":" in line else line.split(None, 1)
        match = re.match(r"\s*(\d+)", value)
        if match:
            result[key] = int(match[1])
    return result


def config(root, port, password, survivors=False, normal_bridge_seconds=None):
    modules = ["commands", "dptools", "dialplan_xml", "loopback", "tone_stream",
               "sndfile", "lua", "event_socket"]
    loads = "".join(f'<load module="mod_{m}"/>' for m in modules)
    tone = "tone_stream://%(1000,0,440);loops=-1" if survivors else "tone_stream://%(600000,0,440)"
    if normal_bridge_seconds is not None:
        tone = f"tone_stream://%({normal_bridge_seconds * 1000},0,440)"
    return f'''<document type="freeswitch/xml">
<section name="configuration">
<configuration name="switch.conf" description="isolated lab"><settings>
<param name="max-sessions" value="32"/><param name="sessions-per-second" value="20"/>
<param name="loglevel" value="warning"/><param name="core-db-name" value="{root}/db/core"/>
</settings></configuration>
<configuration name="modules.conf" description="lab modules"><modules>{loads}</modules></configuration>
<configuration name="event_socket.conf" description="private namespace only"><settings>
<param name="listen-ip" value="127.0.0.1"/><param name="listen-port" value="{port}"/>
<param name="password" value="{password}"/>
</settings></configuration>
<configuration name="lua.conf" description="no startup scripts"><settings/></configuration>
</section>
<section name="dialplan"><context name="memory_lab"><extension name="synthetic">
<condition field="destination_number" expression="^lab$">
<action application="answer"/>
<action application="playback" data="{tone}"/>
<action application="hangup" data="NORMAL_CLEARING"/>
</condition></extension></context></section>
<section name="directory"/>
</document>'''


def run_case(args, name, index):
    mode, rate, duration, tuning = CASES[name]
    survivors = name.startswith("survivors_")
    native_bridge = name.startswith("bridge_")
    if args.duration is not None:
        duration = args.duration
    if args.smoke:
        duration = 4
    root = args.output / name
    root.mkdir()
    for part in ("conf", "log", "run", "db", "scripts", "temp", "recordings", "storage", "cache", "certs"):
        (root / part).mkdir()
    password = uuid.uuid4().hex
    port = 18021 + index
    (root / "conf/freeswitch.xml").write_text(config(root, port, password, survivors,
        duration if name == "bridge_normal" else None))
    (root / "scripts/stats.lua").write_text(
        f"local f = assert(package.loadlib('{args.output}/allocator.so', 'luaopen_memory_lab'))\n"
        "stream:write(tostring(f(argv[1] or 'stats')))\n")
    command = ["/usr/bin/freeswitch", "-nf", "-nc", "-nonat", "-np", "-nocal",
               "-base", str(root), "-mod", "/usr/lib/freeswitch/mod"]
    for part, flag in (("conf", "conf"), ("log", "log"), ("run", "run"), ("db", "db"),
                       ("scripts", "scripts"), ("temp", "temp"), ("recordings", "recordings"),
                       ("storage", "storage"), ("cache", "cache"), ("certs", "certs")):
        command += [f"-{flag}", str(root / part)]
    env = {k: v for k, v in os.environ.items()
           if not k.startswith(("MALLOC_", "GLIBC_TUNABLES", "LD_PRELOAD"))}
    env.update(tuning)
    log = (root / "console.log").open("w")
    process = subprocess.Popen(command, env=env, stdout=log, stderr=subprocess.STDOUT)
    esl = None
    records = (root / "samples.jsonl").open("w", buffering=1)
    actions = (root / "actions.jsonl").open("w", buffering=1)
    previous = None
    latencies = []

    def action(command):
        start = time.monotonic()
        reply = esl.api(command)
        elapsed = (time.monotonic() - start) * 1000
        latencies.append(elapsed)
        actions.write(json.dumps({"utc": stamp(), "command": command,
                                  "reply": reply, "elapsed_ms": elapsed}) + "\n")
        if reply.startswith("-ERR"):
            raise RuntimeError(f"{name}: {command}: {reply}")
        return reply

    def sample(phase):
        nonlocal previous
        if STOP.is_set():
            raise RuntimeError("another case failed or memory guard triggered")
        system = counters("/proc/meminfo")
        if system["MemAvailable"] < 1024 * 1024:
            STOP.set()
            raise RuntimeError("host available memory below 1 GiB; stopping lab")
        vm = counters("/proc/vmstat")
        proc = counters(f"/proc/{process.pid}/smaps_rollup")
        stat = Path(f"/proc/{process.pid}/stat").read_text().rsplit(")", 1)[1].split()
        cpu = (int(stat[11]) + int(stat[12])) / os.sysconf("SC_CLK_TCK")
        probe_start = time.monotonic()
        status = esl.api("status")
        status_latency_ms = (time.monotonic() - probe_start) * 1000
        total = int(re.search(r"(\d+) session\(s\) since startup", status)[1])
        concurrent = int(re.search(r"(\d+) session\(s\) - peak", status)[1])
        if phase == "workload" and mode != "none" and concurrent != 8:
            raise RuntimeError(f"{name}: workload lost calls ({concurrent} of 8 remain)")
        alloc = json.loads(esl.api("lua stats.lua stats"))
        now = time.monotonic()
        row = {"utc": stamp(), "monotonic": now, "phase": phase, "pid": process.pid,
               "sessions": concurrent, "total_sessions": total, "rss_kib": proc["Rss"],
               "swap_kib": proc["Swap"], "available_kib": system["MemAvailable"],
               "pswpin": vm["pswpin"], "pswpout": vm["pswpout"], "cpu_seconds": cpu,
               "status_latency_ms": status_latency_ms,
               **alloc}
        if previous:
            dt = now - previous["monotonic"]
            row.update(cpu_percent=100 * (cpu - previous["cpu_seconds"]) / dt,
                       swap_in_pages_s=(vm["pswpin"] - previous["pswpin"]) / dt,
                       swap_out_pages_s=(vm["pswpout"] - previous["pswpout"]) / dt)
        previous = row
        records.write(json.dumps(row) + "\n")
        print(json.dumps({"case": name, **row}), flush=True)
        return row

    def observe(seconds, phase):
        deadline = time.monotonic() + seconds
        while time.monotonic() < deadline:
            sample(phase)
            STOP.wait(min(args.interval, max(0, deadline - time.monotonic())))

    try:
        for _ in range(600):
            if process.poll() is not None:
                raise RuntimeError(f"{name}: FreeSWITCH exited {process.returncode}")
            try:
                esl = ESL(port, password)
                break
            except (OSError, EOFError):
                time.sleep(0.1)
        if esl is None:
            raise RuntimeError("ESL startup timeout")
        inherited = [v.decode() for v in Path(f"/proc/{process.pid}/environ").read_bytes().split(b"\0")
                     if v.startswith((b"MALLOC_", b"GLIBC_", b"LD_PRELOAD="))]
        (root / "identity.json").write_text(json.dumps({"utc": stamp(), "pid": process.pid,
            "command": command, "tuning": tuning, "inherited_allocator_environment": inherited,
            "version": esl.api("version"), "modules": esl.api("show modules as json"),
            "network_namespace": os.readlink(f"/proc/{process.pid}/ns/net")}, indent=2))
        observe(3 if args.smoke else 15, "baseline")
        ids = []
        setup_started = time.monotonic()
        for _ in range(4):
            channel = str(uuid.uuid4())
            ids.append(channel)
            # Loopback supplies two synthetic, bridged media legs; no SIP profile or trunk.
            playback_ms = duration * 1000 if name in ("normal", "long_normal") else (duration + 120) * 1000
            tone = "tone_stream://%(1000,0,660);loops=-1" if survivors else f"tone_stream://%({playback_ms},0,660)"
            application = "&park()" if survivors or native_bridge else f"&playback({tone})"
            dial = (f"originate {{origination_uuid={channel},memory_lab_uuid={channel},"
                    "loopback_bowout=false,loopback_bowout_on_execute=false,"
                    "hangup_after_bridge=true,originate_timeout=10}loopback/lab/memory_lab/XML "
                    f"{application}")
            reply = action(dial)
            assert channel in reply, reply
        if native_bridge:
            for left, right in zip(ids[::2], ids[1::2]):
                action(f"uuid_bridge {left} {right}")
                # The SQL channel list updates asynchronously; verify the live bond.
                for _ in range(20):
                    partner = esl.api(f"eval uuid:{left} ${{variable_bridge_uuid}} ").strip()
                    if partner == right:
                        break
                    time.sleep(0.05)
                else:
                    raise RuntimeError(f"{name}: native bridge did not establish")
            action("show calls as json")
        action(f"uuid_record {ids[0]} start {root}/tone.wav")
        active = sample("calls_started")
        assert active["sessions"] == 8, f"expected 8 loopback legs, got {active['sessions']}"
        if mode != "none":
            action(f"luarun {args.output}/source/poll.lua {mode} {root}/poll.json {rate} {duration} " + " ".join(ids))
        observe(duration, "workload")
        if mode != "none":
            deadline = time.monotonic() + 60
            while not (root / "poll.json").exists() and time.monotonic() < deadline:
                observe(1, "poll_finishing")
            poll = json.loads((root / "poll.json").read_text())
            assert poll["errors"] == 0 and poll["count"] == rate * duration, poll
        sample("before_hangup")
        if name not in ("normal", "long_normal", "bridge_normal"):
            for channel in (ids[::2] if survivors or native_bridge else ids):
                action(f"uuid_kill {channel}")
        hangup_at = stamp()
        # One-second samples first, then 15-second samples through a full 30 minutes.
        for _ in range(5):
            time.sleep(1)
            sample("early_cleanup")
        remaining_sessions = 4 if survivors else 0
        assert sample("after_hangup")["sessions"] == remaining_sessions
        observe(args.recovery, "recovery")
        sample("recovery_complete")
        if name in ("getvar", "survivors_default"):
            action("fsctl reclaim_mem")
            observe(5, "after_reclaim")
            action("lua stats.lua trim")
            observe(5, "after_trim")
            sample("trim_complete")
        if survivors:
            for channel in ids[1::2]:
                action(f"uuid_kill {channel}")
            observe(5, "survivors_cleanup")
            assert sample("survivors_complete")["sessions"] == 0
        (root / "result.json").write_text(json.dumps({"case": name, "completed_utc": stamp(),
            "hangup_utc": hangup_at, "recovery_seconds": args.recovery, "success": True,
            "recovery_sessions": remaining_sessions,
            "setup_duration_ms": (active["monotonic"] - setup_started) * 1000,
            "action_latency_ms": latencies}, indent=2))
    except Exception as error:
        STOP.set()
        (root / "error.txt").write_text(repr(error))
        raise
    finally:
        if esl:
            try:
                esl.api("fsctl shutdown now")
            except (OSError, EOFError):
                pass
            esl.close()
        try:
            process.wait(timeout=10)
        except subprocess.TimeoutExpired:
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait()
        records.close()
        actions.close()
        log.close()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--cases", nargs="+", choices=CASES, default=list(CASES)[:7])
    parser.add_argument("--recovery", type=int, default=1800)
    parser.add_argument("--interval", type=int, default=15)
    parser.add_argument("--duration", type=int, help="override workload seconds for a separate performance run")
    parser.add_argument("--workers", type=int, help="limit simultaneous cases; use 1 for sequential performance runs")
    parser.add_argument("--smoke", action="store_true")
    args = parser.parse_args()
    if args.duration is not None and not 1 <= args.duration <= 600:
        parser.error("duration must be between 1 and 600 seconds")
    if args.workers is not None and not 1 <= args.workers <= len(args.cases):
        parser.error("workers must be between 1 and the number of cases")
    args.output = args.output.resolve()
    # Refuse to share the installed PBX's network namespace, including on reruns.
    if os.readlink("/proc/self/ns/net") == os.readlink("/proc/1/ns/net"):
        parser.error("requires a private network namespace; see README")
    if os.getuid() == 0:
        account = pwd.getpwnam("www-data")
        os.setgroups([])
        os.setgid(account.pw_gid)
        os.setuid(account.pw_uid)
    args.output.mkdir(parents=True, exist_ok=True)
    os.environ["TMPDIR"] = str(args.output)
    (args.output / "source").mkdir()
    for filename in ("run.py", "allocator.c", "poll.lua", "analyze.py"):
        shutil.copyfile(SOURCE / filename, args.output / "source" / filename)
    subprocess.run(["gcc", "-shared", "-fPIC", "-Wall", "-Wextra", "-Werror", "-O2",
                    "-I/usr/include/lua5.2", "-o", str(args.output / "allocator.so"),
                    str(SOURCE / "allocator.c")], check=True)
    with concurrent.futures.ThreadPoolExecutor(max_workers=args.workers or len(args.cases)) as executor:
        futures = [executor.submit(run_case, args, name, index) for index, name in enumerate(args.cases)]
        for future in concurrent.futures.as_completed(futures):
            future.result()


if __name__ == "__main__":
    main()
