#!/usr/bin/env python3
"""Summarize saved measurements without connecting to any FreeSWITCH process."""
import argparse
import array
import csv
import json
import math
from pathlib import Path
import statistics
import wave

MIB = 1024 * 1024


def inuse(row):
    return row["uordblks"] + row["hblkhd"]


def slope(rows):
    if len(rows) < 3:
        return None
    x = [r["monotonic"] - rows[0]["monotonic"] for r in rows]
    y = [inuse(r) for r in rows]
    xm, ym = statistics.mean(x), statistics.mean(y)
    return sum((a - xm) * (b - ym) for a, b in zip(x, y)) / sum((a - xm) ** 2 for a in x)


def media(path):
    if not path.exists():
        return None
    with wave.open(str(path), "rb") as wav:
        channels, width, rate = wav.getnchannels(), wav.getsampwidth(), wav.getframerate()
        frames = wav.getnframes()
        raw = wav.readframes(frames)
    if width != 2:
        return {"error": "expected 16-bit PCM"}
    # Exclude first/last second when counting silent 20 ms frames.
    samples = array.array("h", raw)
    step = rate // 50 * channels
    margin = rate * channels
    silent, longest, current, windows = 0, 0, 0, 0
    for offset in range(margin, max(margin, len(samples) - margin - step), step):
        window = samples[offset:offset + step]
        quiet = sum(s * s for s in window) / len(window) < 100
        windows += 1
        silent += quiet
        current = current + 1 if quiet else 0
        longest = max(longest, current)
    return {"seconds": frames / rate, "rate": rate, "channels": channels,
            "rms": math.sqrt(sum(s * s for s in samples) / len(samples)) if samples else 0,
            "silent_20ms_windows": silent,
            "analyzed_20ms_windows": windows, "longest_silent_ms": longest * 20,
            "clipped_samples": sum(abs(s) >= 32767 for s in samples),
            "samples": len(samples)}


def summarize(root, require_complete):
    summaries, all_rows = [], []
    for path in sorted(root.glob("*/samples.jsonl")):
        case = path.parent.name
        rows = [json.loads(line) for line in path.read_text().splitlines()]
        if not rows:
            continue
        all_rows.extend({"case": case, **row} for row in rows)
        phases = {r["phase"]: r for r in rows}
        full_workload = [r for r in rows if r["phase"] == "workload"]
        workload = full_workload
        if workload:
            # Exclude startup tone-buffer allocation and initial scheduling jitter.
            workload = [r for r in workload if r["monotonic"] >= workload[0]["monotonic"] + 30]
        poll_path = path.parent / "poll.json"
        poll = json.loads(poll_path.read_text()) if poll_path.exists() else None
        result_path = path.parent / "result.json"
        result = json.loads(result_path.read_text()) if result_path.exists() else None
        if require_complete:
            assert result and result["success"] and result["recovery_seconds"] >= 1800, case
            assert phases["recovery_complete"]["sessions"] == result.get("recovery_sessions", 0), case
            recovery = [r for r in rows if r["phase"] == "recovery"]
            assert phases["recovery_complete"]["monotonic"] - recovery[0]["monotonic"] >= 1800, case
            assert not (path.parent / "error.txt").exists(), case
            if poll:
                assert poll["errors"] == 0, case
        growth = slope(workload)
        cpu_seconds = cpu_percent = None
        if full_workload and "before_hangup" in phases:
            start, end = full_workload[0], phases["before_hangup"]
            cpu_seconds = end["cpu_seconds"] - start["cpu_seconds"]
            cpu_percent = 100 * cpu_seconds / (end["monotonic"] - start["monotonic"])
        summary = {"case": case, "complete": bool(result), "poll": poll,
                   "recovery_seconds": result["recovery_seconds"] if result else None,
                   "growth_bytes_per_second": growth,
                   "workload_cpu_seconds": cpu_seconds, "workload_cpu_percent": cpu_percent,
                   "workload_status_latency_ms_max": max((r["status_latency_ms"] for r in full_workload), default=None),
                   "minimum_host_available_mib": min(r["available_kib"] for r in rows) / 1024,
                   "maximum_process_swap_mib": max(r["swap_kib"] for r in rows) / 1024,
                   "snapshots": {}, "media": media(path.parent / "tone.wav") if result else None}
        for phase in ("baseline", "calls_started", "before_hangup", "after_hangup", "recovery_complete", "after_reclaim", "trim_complete"):
            if phase in phases:
                r = phases[phase]
                summary["snapshots"][phase] = {"utc": r["utc"], "rss_mib": r["rss_kib"] / 1024,
                    "uordblks_mib": r["uordblks"] / MIB, "mmap_mib": r["hblkhd"] / MIB,
                    "inuse_mib": inuse(r) / MIB, "free_mib": r["fordblks"] / MIB,
                    "arena_mib": r["arena"] / MIB, "heaps": r["heaps"]}
        summaries.append(summary)
    if require_complete:
        assert summaries, "no results"
    (root / "summary.json").write_text(json.dumps(summaries, indent=2) + "\n")
    if all_rows:
        with (root / "measurements.csv").open("w") as file:
            fields = list(dict.fromkeys(key for row in all_rows for key in row))
            writer = csv.DictWriter(file, fieldnames=fields)
            writer.writeheader()
            writer.writerows(all_rows)
    for row in summaries:
        snap = row["snapshots"]
        compact = lambda phase, key: round(snap[phase][key], 2) if phase in snap else None
        print(json.dumps({"case": row["case"], "complete": row["complete"],
            "growth_KiB_s": round(row["growth_bytes_per_second"] / 1024, 2) if row["growth_bytes_per_second"] is not None else None,
            "cpu_percent": round(row["workload_cpu_percent"], 2) if row["workload_cpu_percent"] is not None else None,
            "before_hangup_rss": compact("before_hangup", "rss_mib"),
            "after_hangup_rss": compact("after_hangup", "rss_mib"),
            "recovery_seconds": row["recovery_seconds"],
            "after_recovery_rss": compact("recovery_complete", "rss_mib"),
            "after_trim_rss": compact("trim_complete", "rss_mib")}))


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("output", type=Path)
    parser.add_argument("--require-complete", action="store_true")
    args = parser.parse_args()
    summarize(args.output, args.require_complete)
