# FreeSWITCH allocator investigation lab

This is an opt-in runtime experiment, not an application unit test or an installed
FreeSWITCH feature. It creates fresh FreeSWITCH processes with their own XML,
databases, runtime directories, event sockets and logs. No SIP module, trunk,
application database, XML curl, CDR exporter or optional application module is
loaded. Four loopback calls supply eight media legs per process. Both ends play
generated tones; one call is recorded for a basic media continuity check.

It reproduces session-pool growth from repeated reads and measures allocator
retention. It does **not** reproduce an unidentified stale-call bug, 212 hours of
call state, production traffic, SIP/RTP transport, or perceptual audio quality.

## Requirements and isolation

Linux/systemd, glibc with `mallinfo2`, the installed FreeSWITCH binary and modules,
Python 3, GCC, Lua 5.2 headers, and the `www-data` account. Paths currently match
the FS PBX Debian installation. Run as root through the private-network unit
below; the runner verifies the namespace and then drops to `www-data` before
compiling the helper or launching FreeSWITCH. The root process has no call load.

The unit cannot write outside its disposable output directory. A cgroup limits
the entire lab to 1,400 MiB RAM, no swap, two CPU cores and 45 minutes. Each sample
also stops the experiment if host available memory falls below 1 GiB. Ensure the
host has additional headroom for its normal services. No unit is enabled or
installed persistently. The existing `freeswitch.service` is never restarted.

## Run

Use a new output directory and unit name on every run. From `/var/www/fspbx`:

```bash
lab_stamp=$(date -u +%Y%m%dT%H%M%SZ)
lab_dir="/tmp/fspbx-memory-$lab_stamp"
lab_unit="fspbx-memory-$lab_stamp"
install -d -o www-data -g www-data -m 0750 "$lab_dir"
systemd-run --unit="$lab_unit" \
  --property=PrivateNetwork=yes \
  --property=MemoryMax=1400M --property=MemorySwapMax=0 \
  --property=CPUQuota=200% --property=RuntimeMaxSec=2700 \
  --property=NoNewPrivileges=yes --property=ProtectSystem=strict \
  --property="ReadWritePaths=$lab_dir" \
  /usr/bin/python3 /var/www/fspbx/tests/FreeSWITCHMemoryLab/run.py \
  --output "$lab_dir" \
  --cases normal long_normal long_kill getvar getvar_half eval \
          tuned arena_only trim_only mmap_only
journalctl -u "$lab_unit" -f
```

Completion takes approximately 36 minutes. Every case needs a `result.json` with
`success: true`; an accepted systemd launch is not completion. Failed cases write
`error.txt` and stop the other cases. A short smoke test may instead use
`--cases normal getvar eval --smoke --recovery 5 --interval 2` with a separate
directory/unit. Smoke results do not satisfy the 30-minute recovery requirement.

## Experiments

| Case | Workload | End | Startup allocator environment |
| --- | --- | --- | --- |
| normal | Four calls, 10 seconds | Playback finishes | Defaults |
| long_normal | Four calls, 300 seconds | Playback finishes | Defaults |
| long_kill | Four calls, 300 seconds | `uuid_kill <uuid>` | Defaults |
| getvar | 1,800,000 UUID reads, aggregate 10,000/s over 180 seconds | `uuid_kill <uuid>` | Defaults |
| getvar_half | 900,000 reads, aggregate 5,000/s over 180 seconds | `uuid_kill <uuid>` | Defaults |
| eval | Same count/rate as getvar, temporary event-header reads | `uuid_kill <uuid>` | Defaults |
| tuned | Same as getvar | `uuid_kill <uuid>` | All three settings below |
| arena_only | Same as getvar | `uuid_kill <uuid>` | `MALLOC_ARENA_MAX=8` |
| trim_only | Same as getvar | `uuid_kill <uuid>` | `MALLOC_TRIM_THRESHOLD_=131072` |
| mmap_only | Same as getvar | `uuid_kill <uuid>` | `MALLOC_MMAP_THRESHOLD_=131072` |

The polling rate is intentionally amplified, split across four calls, with a
finite count and deadline. The `getvar_half` case tests dependence on read count;
the interval samples show dependence on time. The `eval` control uses only a
fixed, internally generated UUID expression, not caller-controlled expressions.

The runner removes inherited allocator overrides before applying each case's
environment. `identity.json` records `/proc/PID/environ`, actual version, loaded
modules, process command and network namespace. The tuned test therefore verifies
startup inheritance directly. It does not modify the host's systemd service file.

After termination, samples occur every second for five seconds, then every 15
seconds for at least 1,800 seconds. Only **after** that interval, `getvar` executes
`fsctl reclaim_mem`, samples for five seconds, then calls `malloc_trim(0)` once and
samples again. No `mallopt()` calls exist in the helper. The tuned and individual
setting cases never trim manually. `trim_only` means a startup threshold setting;
the separate explicit-trim experiment is the final part of `getvar`.

An additional matched comparison keeps other calls alive. Run a separate unit
and output directory with `--cases survivors_default survivors_tuned`; a 500 MiB
memory limit and one CPU core suffice for this pair. These cases use one-second
looping tones and a parked opposite leg, avoiding large pre-generated tone buffers.
They perform the same 1,800,000 interleaved reads across four calls, kill alternate
calls, then observe the remaining four media legs for 30 minutes. This deliberately
interleaves allocations from destroyed and surviving pools. Only the default case
then reclaims/trims; finally both cases end the remaining control calls. This is a
fragmentation test, not a reconstruction of the unknown production call feature.

To cover native bridge execution separately, run another unit with
`--cases bridge_normal bridge_kill`. These create four loopback endpoint pairs,
park the originating legs, then join them into two native bridges using
`uuid_bridge`. One case finishes its five-minute tone naturally; the other sends
`uuid_kill` to one leg of each bridge after five minutes. All eight legs must end,
followed by the same 30-minute observation. `show calls as json` is saved at setup.

## Evidence and interpretation

- `samples.jsonl`: UTC timestamps, session counts, total sessions, RSS and swap
  from `smaps_rollup`, `uordblks`, `fordblks`, arena size, mmapped allocation bytes,
  heap count, system available memory, process CPU, global swap counters/rates,
  and local `status` API response time. No heap contents are scanned.
- `actions.jsonl`: commands, responses and latency. Hangup matches the Active
  Calls page's `FreeswitchEslService::killChannel()` command. Tests wait for zero
  sessions; a successful API reply alone is not treated as completed cleanup.
- `poll.json`: completed read count, result errors, elapsed time and slowest
  batch. `luarun` acceptance is not completion; this file and counters are checked.
- `tone.wav`: local mixed tone recording. Check duration, silent runs and clipping;
  this cannot certify network jitter, SIP interoperability or perceived quality.
- `result.json`: completion timestamp, recovery duration, call setup duration and
  API action latency. `console.log` retains startup/diagnostic output.

Generate the combined CSV and summary, checking the full observation interval:

```bash
python3 -B tests/FreeSWITCHMemoryLab/analyze.py "$lab_dir" --require-complete
```

This writes `measurements.csv` and `summary.json`. It checks every discovered case
for a completed run, the expected remaining-session count, no polling errors and
at least 1,800 seconds of recovery samples. Also compare the case directories with
the requested case list. The additional ongoing-call pair intentionally has four
remaining sessions during that interval and zero after final cleanup.

`uordblks` excludes separately mmapped allocations. Inspect `hblkhd` too, especially
when comparing mmap thresholds or generated tone buffers. In-use allocator bytes
do not prove that all contents are logically necessary. RSS is physical footprint;
virtual mappings alone are not evidence of memory pressure. Global swap rates and
available memory include unrelated host activity.

The cases share a CPU quota and host. Their CPU/latency figures are descriptive,
not a controlled production performance benchmark. Startup arena placement and
large tone-buffer allocation can differ between processes. Repeat promising
comparisons individually before drawing performance or broad deployment claims.

For a separate sequential performance comparison, use a new unit/directory with
`--cases getvar tuned --duration 60 --recovery 30 --interval 5 --workers 1`.
Reverse case order on a repeat. The short recovery is intentional for performance
sampling and does not qualify as a 30-minute retention experiment; omit
`--require-complete` when summarizing it. The original recovery experiments must
still finish independently.

## Stop and rollback

Stop only the disposable unit named above:

```bash
systemctl stop "$lab_unit"
```

All child processes belong to that unit. The installed PBX and its allocator
environment are unchanged. Preserve the output directory as evidence; remove it
only when no longer needed. No recurring trim, call-killing policy or persistent
allocator change is installed.

For the previously described **production** mitigation, remove only the added
allocator settings from `/etc/systemd/system/freeswitch.service.d/30-memory.conf`
and run `systemctl daemon-reload`. Existing live `mallopt()` state is not undone
by that change. A later administrator-controlled FreeSWITCH restart without those
overrides restores startup defaults. This lab does not perform that rollback.

GNU documents [allocator parameters](https://sourceware.org/glibc/manual/latest/html_node/Malloc-Tunable-Parameters.html);
the Linux manual describes [`malloc_trim`](https://man7.org/linux/man-pages/man3/malloc_trim.3.html).
Threshold tuning controls automatic release opportunities; an explicit trim can
also release whole free pages within arenas. Neither operation frees memory that
the application still owns.
