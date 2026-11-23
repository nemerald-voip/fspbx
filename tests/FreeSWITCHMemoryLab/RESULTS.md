# FreeSWITCH memory investigation — 12 November 2026

**Completed:** 14 fresh-process memory experiments with full 30-minute recovery
observations, plus four short sequential performance cases. **The combined
allocator settings improve some recovery cases but do not prevent retention
around surviving call pools.** The original stale-call cause remains unknown.

## Scope and incident facts

The operator reports that several production calls all showed an age of about
212 hours and were ended using **Active Calls → Hangup**. How they became stuck,
their call feature, and their original channel state remain unknown. Production
allocator measurements and the successful manual recovery are supplied incident
evidence, not measurements taken from this test machine.

The button's route is `ActiveCalls.vue` →
`ActiveCallsController::handleAction()` (line 190) →
`FreeswitchEslService::killChannel()` (line 300), which sends `uuid_kill <uuid>`.
The lab uses this exact API command and separately checks session counts and
allocator cleanup. The UI lists channels through `show channels as json`; that
listing is not the amplified `uuid_getvar` workload used here.

An observability limitation in the current controller is that it does not check
the returned ESL body before reporting success. The service can return `-ERR`
as a string. Therefore the UI banner alone is not proof of channel termination.
There is no evidence that this happened during the incident; no unrelated UI
behavior was changed as part of this allocator investigation.

## Environment and method

The test machine runs FreeSWITCH **1.11.3**, commit
`7149bd22fdb6cee79d807e5f178d7fbe51cc5fd5`, and **glibc 2.36**. Production reported
1.11.1, commit `19eca6a`; that older commit is not present in the local source
checkout. The installed service was PID 703, running as `www-data`, with 37 total
sessions, no active calls and approximately 13 days of uptime. Its startup
environment had no `MALLOC_*`, `GLIBC_TUNABLES` or `LD_PRELOAD` overrides. The host
has about 7.75 GiB RAM and 495 MiB swap, initially nearly full.
Transparent huge pages are set to `always`; late survivor-process mapping
snapshots record substantial anonymous huge-page use. That policy was unchanged.

ContactCenter, Billing and StirShaken are installed/enabled in this checkout.
They are not loaded or invoked by the lab. All tested FreeSWITCH processes run as
`www-data`, using separate XML/databases/directories inside private network
namespaces. No Sofia/SIP module or production trunk is available. No application
database is used. The installed service, its configuration and its binary were
not changed or restarted.

The [repeatable procedure](README.md) and runner specify the exact call commands,
bounded read rates, startup environments, memory limits and observation periods.
Every test starts a fresh process. Polling compares 1.8 million UUID reads over
180 seconds with a half-rate case and an `eval` temporary-event control. Four
loopback calls provide eight media legs. Additional normal-completion and
`uuid_kill` cases last five minutes. Allocator statistics use `mallinfo2` and
`malloc_info`; no heap contents are scanned. RSS/swap come from `smaps_rollup`.

The original ten-case comparison uses generated continuous tone buffers. An
additional matched pair uses small repeating tone buffers, interleaves reads
across four calls, kills alternate calls, and keeps two calls/four legs active
through the recovery interval. That pair tests fragmentation around surviving
call pools, closer to production's continued call traffic. It still does not
reproduce the unknown stale-call mechanism or nine days of traffic.

A final pair explicitly joins loopback endpoints through `uuid_bridge`, exercising
the native bridge execution path for five-minute calls. It compares natural
completion with `uuid_kill`, then observes each fresh process for 30 minutes.

Two additional performance runs execute the default and combined-tuning workloads
sequentially, reversing their order on the second run. Their calls last 60 seconds
and recovery lasts 30 seconds. They are performance samples only and are excluded
from the set of full 30-minute recovery experiments.

## Confirmed mechanism

In this build, `uuid_getvar_function()` calls the duplicating channel-variable
getter (`src/mod/applications/mod_commands/mod_commands.c:6263`). The default
getter macro requests duplication (`src/include/switch_channel.h:326`).
`switch_channel_get_variable_dup()` copies the value into the session pool
(`src/switch_channel.c:979`, copy at line 1020). A 36-character UUID plus terminator
is aligned in APR storage, and APR obtains small nodes with a minimum size of
8,192 bytes (`libs/apr/memory/unix/fspr_pools.c:47`). Repeating the read retains
copies until that session pool is destroyed.

The measured growth is approximately **40 bytes per read**. Halving the request
rate halves growth. The temporary-event `eval` control shows negligible sustained
growth under the same count/rate. The combined allocator settings do not prevent
the live allocations from accumulating.

At the first sample after killing all calls, session count is zero but most
duplicated values are still allocated. By the second sample, those allocations
are freed. This agrees with the queued pool-destruction path in
`src/switch_core_memory.c:465` and its approximately one-second waits at lines
589 and 640. The tested cleanup takes seconds, not the prolonged interval
reported in production. Production's delay could involve much larger pools,
destruction backlog, external session references, swap I/O or another path; none
of those explanations has been established here. Session teardown can wait for
external references (`src/switch_core_session.c:1723`) before queuing its pool.

`fsctl reclaim_mem` calls logger/event/pool reclamation through
`switch_core_memory_reclaim_all()` (`src/switch_core.c:3139`), not `malloc_trim()`.
With this source's `PER_POOL_LOCK` branch, the explicit pool-recycle reclamation
body is compiled out (`src/switch_core_memory.c:554`). This is distinct from the
background destruction of queued pools.

## Measurements

The original ten cases completed successfully. Raw samples are UTC; table times
are Pacific Standard Time (UTC−08:00), all on 12 November 2026. Memory units are
binary MiB. The workload-end sample precedes explicit kills; naturally ending
calls may already be completing then. The post-recovery sample follows five
one-second cleanup samples and a further full 1,800-second observation.

| Case | Workload-end time | RSS then | Post-recovery time | RSS then | Heap in use (`uordblks`) | Heap free (`fordblks`) |
| --- | --- | ---: | --- | ---: | ---: | ---: |
| Normal, 10 seconds | 10:32:50 | 70.31 | 11:02:55 | 62.14 | 1.75 | 33.46 |
| Normal, 5 minutes | 10:37:45 | 105.52 | 11:07:50 | 76.00 | 1.77 | 50.86 |
| Kill, 5 minutes | 10:37:50 | 125.71 | 11:07:55 | 73.46 | 1.76 | 45.53 |
| UUID reads, defaults | 10:35:53 | 168.11 | 11:05:58 | 74.98 | 1.77 | 51.20 |
| UUID reads, half rate | 10:35:46 | 128.00 | 11:05:51 | 69.54 | 1.77 | 55.90 |
| Temporary-event control | 10:35:49 | 102.78 | 11:05:54 | 52.36 | 1.77 | 29.94 |
| All three allocator settings | 10:35:50 | 163.53 | 11:05:55 | 32.25 | 1.76 | 2.79 |
| Arena limit only | 10:35:51 | 180.61 | 11:05:56 | 112.45 | 1.75 | 92.85 |
| Trim threshold only | 10:35:47 | 159.83 | 11:05:53 | 33.27 | 1.79 | 5.31 |
| Mmap threshold only | 10:35:53 | 159.14 | 11:05:58 | 32.25 | 1.77 | 3.89 |

All these final samples have zero sessions. In addition to `uordblks`, every
process retains about 9.08 MiB of separately mmapped allocations (`hblkhd`).
Baseline RSS before calls was approximately 29 MiB. In the default UUID-read
case, final arena bytes were 52.97 MiB versus 4.55 MiB with combined tuning.
None of the lab processes used swap; minimum sampled host available memory during
this group was 1,619 MiB. Global swap counters include unrelated host activity.

The fixed thresholds help automatic recovery in this workload; the arena limit
alone does not. All tuned cases accumulate approximately the same live bytes per
read before hangup.

### Native bridges

Both native bridge cases also completed successfully:

| Case | Workload-end time / RSS | After cleanup: time / RSS | After 30 minutes: time / RSS |
| --- | --- | --- | --- |
| Natural completion | 10:59:31 / 59.75 MiB | 10:59:36 / 55.24 MiB | 11:29:36 / 57.04 MiB |
| `uuid_kill` | 10:59:33 / 69.08 MiB | 10:59:38 / 60.00 MiB | 11:29:38 / 61.43 MiB |

Both have zero remaining sessions and 1.78 MiB heap bytes in use at the final
sample. Final free heap bytes are 27.86 MiB and 32.97 MiB respectively, with
another 9.08 MiB mmapped in each. There was no sustained significant growth during
the five-minute calls and no prolonged cleanup delay. In the kill case, ending
one leg per native bridge ended all eight endpoint legs. The initial SQL call
listing was captured too early to populate; subsequent channel counts and hangup
propagation are the runtime evidence. The reusable runner now also verifies the
live bridge UUID, validated in a separate smoke test.

### Other calls still active

The interleaved-pool comparison also completed its full 30 minutes:

| Stage | Defaults: time / RSS | Combined tuning: time / RSS | Remaining media legs |
| --- | --- | --- | ---: |
| Before partial hangup | 10:44:26 / 101.89 MiB | 10:44:27 / 102.14 MiB | 8 |
| After cleanup | 10:44:31 / 101.89 MiB | 10:44:32 / 102.00 MiB | 4 |
| After 30 minutes | 11:14:31 / 103.59 MiB | 11:14:32 / 102.57 MiB | 4 |
| After built-in reclaim | 11:14:31 / 103.59 MiB | Not performed | 4 |
| After explicit trim | 11:14:41 / 84.67 MiB | Not performed | 4 |
| After ending the control calls | 11:14:46 / 32.54 MiB | 11:14:37 / 31.77 MiB | 0 |

At 30 minutes, default heap allocations were **37.59 MiB in use and 39.09 MiB
free**. With tuning they were **37.57 MiB in use and 37.96 MiB free**. Each had
another 9.08 MiB mmapped. Approximately 36 MiB had been freed after the first two
calls ended, yet neither configuration automatically returned much physical
memory during the observation. Allocations belonging to the surviving calls
correctly remained in use.

The default case's one-shot trim at 11:14:36 returned 1 and took about 5.1 ms as
measured by the API caller. RSS fell by **18.92 MiB**, with in-use heap bytes still
37.59 MiB and all four control legs still active. The combined-settings case had
no manual trim. Ending its remaining calls then allowed memory recovery.
Arena size stayed approximately 76.6 MiB through trimming: physical memory was
released without removing most of the allocator's address space.

This is a bounded reproduction of freed allocator retention around surviving
call pools. It demonstrates that **the three allocator settings do not guarantee
automatic recovery**. It does not establish that production's allocation layout,
stale-call trigger, or long cleanup delay was the same. The ongoing calls were not
recorded through the trim step, so their continued presence is not an audio-quality
measurement of trimming itself.

In the completed all-calls-ended default case, `fsctl reclaim_mem` at
11:05:58 PST did not change the 74.98 MiB RSS. `malloc_trim(0)` at 11:06:03
returned 1, but RSS only fell to 74.21 MiB immediately and 74.23 MiB five seconds
later. Allocator in-use heap bytes remained 1.77 MiB. Thus this case reproduces
retention but **not** production's large trim recovery. A positive trim return
value must not be read as a promise that all `fordblks` memory was released.

Upstream glibc 2.36's `mtrim()` scans free-list chunks in every arena but only
calls `systrim()` for the main arena. That source distinction provides a possible
explanation for remaining allocator free space after an explicit trim; this lab
did not profile each retained chunk's position. See the
[versioned implementation](https://github.com/bminor/glibc/blob/glibc-2.36/malloc/malloc.c#L5034).

### CPU, response time and audio

The separate sequential performance runs completed 600,000 reads per case.
Process CPU is measured over the entire workload interval, not a selected subset
of samples. One core corresponds to 100%:

| Run order | Default CPU seconds / wall seconds | Tuned CPU seconds / wall seconds |
| --- | ---: | ---: |
| Default, then tuned | 13.04 / 60.001 | 12.71 / 60.000 |
| Tuned, then default | 12.04 / 60.003 | 12.41 / 60.937 |

The small difference changes direction between runs. This does not establish a
consistent CPU penalty or a zero-cost guarantee. The high-rate temporary-event
control is materially more expensive than direct reads in the simultaneous run;
its benefit is allocation lifetime, not universal performance improvement.

Completed tone recordings in the original ten cases and both sequential runs
contain no clipped samples or silent 20 ms windows after excluding the first and
last second. These are generated local 8 kHz PCM recordings, not handset/carrier
audio tests. They cannot establish network jitter, packet loss, perceptual quality
or production call-setup latency. API latency measurements are saved in the raw
samples/actions and are local to the namespace.

The later native-bridge natural-completion recording also has no analyzed silent
windows or clipping. The **untuned native-bridge kill control** has one 260 ms
silent interval at recording offset **100.10–100.36 seconds**, well before its
five-minute hangup. Its cause was not established. This is not evidence of a tuning
regression, but it prevents claiming uniformly clean audio across the entire lab.

## Fix, mitigation and unresolved questions

| Investigation question | Answer supported by this run |
| --- | --- |
| Why were the production calls stale? | Unknown; their pre-hangup state was not captured. |
| What allocated the large pools? | Repeated UUID reads allocate session-pool copies in the synthetic test. Production's allocating call path remains unidentified. |
| Does growth depend on time or polling? | Under the bounded workload it tracks read count: about 40 bytes/read, with half the rate producing half the growth. |
| Why was production cleanup slow? | Unresolved. Lab allocations were freed within about two seconds; production's multi-gigabyte swap pressure and cleanup ownership were not reproduced. |
| Live allocations or retained free memory? | Both stages are visible and measured separately above. Free allocator bytes can remain resident after calls disappear. |
| Does tuning recover automatically? | It helps when every synthetic call ends, but fails to release fragmented free space while other pools remain active, even after 30 minutes. |
| CPU, latency or audio cost? | No consistent CPU change in two short sequential comparisons. Matched tuning recordings have no detected gaps/clipping; a separate untuned native bridge has an unexplained 260 ms gap. Production cost/quality remains unvalidated. |
| Is a source fix established? | No incident-specific fix yet. Avoiding repeated pool-backed reads fixes the demonstrated growth mechanism where that polling actually occurs. |

No incident-specific application or FreeSWITCH fix is justified yet: the stale
call mechanism and the production allocation owner have not been identified.
The added files are a disposable reproduction/measurement tool, not an installed
workaround. No automatic call killing, call-duration limit or recurring trim was
introduced.

The existing ContactCenter audio code already avoids repeated pool-backed reads
in its frequent polling path by using temporary event headers
(`Modules/ContactCenter/Resources/freeswitch/contact_center_queue_audio.lua:125`).
The player also takes event snapshots (line 24). This is consistent with the
tested allocation lifetime improvement; it does not implicate ContactCenter in
the incident, establish production's deployed revision, or justify broad
replacement of unrelated one-time reads. The artificial high-rate `eval` test
also uses more CPU, so the alternative is not a free optimization at every rate.

Allocator settings are a **mitigation candidate**, not a fix for stale calls or
live pool growth. GNU documents that fixed trim/mmap thresholds disable adaptive
threshold adjustment; the trim threshold concerns releasable memory at the end
of an arena. The Linux manual documents that an explicit trim can also release
whole free pages within arenas. Those operations have different opportunities
to return memory. See [GNU allocator parameters](https://sourceware.org/glibc/manual/latest/html_node/Malloc-Tunable-Parameters.html)
and [`malloc_trim(3)`](https://man7.org/linux/man-pages/man3/malloc_trim.3.html).

The lab verifies eight arenas in fresh processes using the combined settings.
Production's live arena limit change did not remove its existing 128 arenas, so
these startup tests are not equivalent to the already-running production
allocator. Absolute memory footprints also cannot be compared directly: module
sets, call counts, RAM/swap pressure, FreeSWITCH versions and uptime differ.

To determine the original cause on recurrence, preserve a private `uuid_dump`
for each stale UUID before hangup, the channel/call lists, exact UTC hangup time,
and matching FreeSWITCH logs covering call creation and termination. Capture the
application, application data, channel state, linked-leg UUIDs, queue/callback
variables and hangup/destroy events. Continue allocator/RSS measurements after
hangup. A missing channel alone does not prove that its pool has been destroyed.
Capture only the affected records and allocator counters; do not scan the heap.

The [rollback instructions](README.md#stop-and-rollback) stop only disposable lab
units. Production rollback removes the added allocator settings, reloads systemd
and restores startup defaults only on a later administrator-controlled restart;
removing the file cannot undo live `mallopt()` changes.

## Delivered evidence and verification

- Procedure and tools: this directory's `README.md`, `run.py`, `poll.lua`,
  `allocator.c` and `analyze.py`.
- Durable evidence directory:
  `/var/www/fspbx/storage/app/diagnostics/freeswitch-memory-20261112/`.
  `measurements.csv` combines all valid runs; `manifest.json` identifies the
  groups and observation lengths; `evidence.tar.gz` retains the original samples,
  action responses, process identities, synthetic WAVs and source snapshots.
- Valid full runs originated in `/tmp/fspbx-memory-lab2-20261112`,
  `/tmp/fspbx-memory-survivors2-20261112` and
  `/tmp/fspbx-memory-bridges-20261112`. Short performance runs are in
  `/tmp/fspbx-memory-performance1-20261112` and
  `/tmp/fspbx-memory-performance2-20261112`. Failed startup/early-ending harness
  attempts and smoke runs are excluded from the results.
- The analyzer's full-duration checks pass for all 14 recovery cases; all
  expected polling counts completed without read errors. The C helper compiled
  with `-Wall -Wextra -Werror`; Python syntax checks passed. No application PHP or
  frontend behavior was changed, so application builds were not needed.
- At 11:30 PST, only the original FreeSWITCH PID **703** remained. Its service was
  active, its environment still had no allocator overrides, its total session
  count was still **37**, and it had zero active sessions. All valid lab units
  were inactive with successful results. No persistent service/drop-in, timer,
  cron entry or FreeSWITCH binary change was made.
