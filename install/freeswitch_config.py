#!/usr/bin/env python3
"""Internal installer helper for configuration preservation and module checks."""

import argparse
import glob
import hashlib
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import sys
import xml.etree.ElementTree as ET

# Removed upstream between 1.10.12 and 1.11.3. Do not retain their old binaries
# or load entries when upgrading; supported/custom modules are handled separately.
RETIRED_MODULES = set("""
mod_abstraction mod_ladspa mod_mp4 mod_mp4v2 mod_oreka mod_rad_auth mod_rss
mod_sms_flowroute mod_snom mod_sonar mod_soundtouch mod_stress mod_cepstral
mod_clearmode mod_dahdi_codec mod_h26x mod_isac mod_mp4v mod_sangoma_codec
mod_theora mod_gsmopen mod_khomp mod_portaudio mod_skypopen mod_unicall
mod_cdr_mongodb mod_event_zmq mod_radius_cdr mod_rayo mod_portaudio_stream
mod_ssml mod_python mod_yaml mod_raven mod_xml_radius
""".split())
# BroadVoice is deliberately omitted from FS PBX installs as well.
EXCLUDED_MODULES = RETIRED_MODULES | {"mod_bv"}

def inventory(root):
    root = Path(root)
    result = {}

    def visit(path):
        info = path.lstat()
        row = {"mode": stat.S_IMODE(info.st_mode), "uid": info.st_uid, "gid": info.st_gid,
               "mtime_ns": info.st_mtime_ns}
        if path.is_symlink():
            row.update(type="symlink", target=os.readlink(path))
        elif path.is_dir():
            row["type"] = "directory"
        elif path.is_file():
            with path.open("rb") as stream:
                digest = hashlib.sha256()
                for data in iter(lambda: stream.read(1024 * 1024), b""):
                    digest.update(data)
            row.update(type="file", sha256=digest.hexdigest())
        else:
            raise ValueError(f"Unsupported special file in configuration: {path}")
        row["xattrs"] = {name: hashlib.sha256(os.getxattr(path, name, follow_symlinks=False)).hexdigest()
                        for name in os.listxattr(path, follow_symlinks=False)}
        result[str(path.relative_to(root))] = row
        if row["type"] == "directory":
            for child in sorted(path.iterdir()):
                visit(child)

    visit(root)
    return result


def differences(before, after):
    return sorted(key for key in before.keys() | after.keys() if before.get(key) != after.get(key))


def restore(backup, target):
    """Restore an installer snapshot without following destination symlinks."""
    backup, target = Path(backup), Path(target)
    expected = inventory(backup)
    if expected["."]["type"] != "directory":
        raise ValueError("The configuration backup must be a directory.")
    if target.is_symlink() or (target.exists() and not target.is_dir()):
        target.unlink()
    actual = inventory(target) if target.exists() else {}
    for key in sorted(actual, key=lambda item: len(Path(item).parts), reverse=True):
        if key == ".":
            continue
        if key not in expected or actual[key]["type"] != expected[key]["type"]:
            path = target / key
            if path.is_dir() and not path.is_symlink():
                path.rmdir()
            else:
                path.unlink()
    # GNU cp preserves ownership, ACLs, xattrs and symlinks. Remove changed files
    # first so neither an old symlink nor a hardlink can redirect the write.
    for key, row in expected.items():
        path = target / key
        if row["type"] == "directory":
            path.mkdir(parents=True, exist_ok=True)
        elif row != actual.get(key):
            if path.exists() or path.is_symlink():
                path.unlink()
            subprocess.run(["cp", "-a", "--", str(backup / key), str(path)], check=True)
    # Restore metadata from children to parents without traversing symlinks.
    for key in sorted(expected, key=lambda item: len(Path(item).parts), reverse=True):
        row = expected[key]
        path = target / key
        for name in os.listxattr(path, follow_symlinks=False):
            if name not in row["xattrs"]:
                os.removexattr(path, name, follow_symlinks=False)
        os.chown(path, row["uid"], row["gid"], follow_symlinks=False)
        shutil.copystat(backup / key, path, follow_symlinks=False)
    if differences(expected, inventory(target)):
        raise ValueError("Configuration restoration did not pass verification; retain the backup.")


def loaded_modules(conf, files=None):
    conf = Path(conf).resolve()
    names, seen = set(), set()

    def read(path):
        path = path.resolve()
        if not path.is_relative_to(conf):
            raise ValueError(f"Module configuration outside the configuration directory: {path}")
        if path in seen:
            return
        seen.add(path)
        if files is not None:
            files.add(path)
        root = ET.parse(path).getroot()
        for node in root.iter():
            if node.tag == "load" and node.get("module"):
                name = node.get("module")
                if not name.startswith("mod_") or any(c not in "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_" for c in name):
                    raise ValueError(f"Unsupported module reference in {path}; review custom module loading.")
                names.add(name)
            if node.tag == "X-PRE-PROCESS" and node.get("cmd") == "include":
                pattern = node.get("data", "").replace("$${conf_dir}", str(conf))
                if "$" in pattern:
                    raise ValueError(f"Unresolved module include in {path}; review before upgrading.")
                candidate = Path(pattern)
                if not candidate.is_absolute():
                    candidate = path.parent / candidate
                for child in glob.glob(str(candidate)):
                    read(Path(child))

    for filename in ("pre_load_modules.conf.xml", "modules.conf.xml", "post_load_modules.conf.xml"):
        path = conf / "autoload_configs" / filename
        if path.exists():
            read(path)
    if not names:
        raise ValueError("No module load configuration found; set FREESWITCH_CONF_DIR to the installed directory.")
    return names


def select_modules(source, conf, baseline, output):
    source = Path(source)
    available = {p.parent.name: str(p.parent.relative_to(source / "src/mod"))
                 for p in (source / "src/mod").glob("*/mod_*/Makefile.am")}
    required = loaded_modules(conf) - EXCLUDED_MODULES
    missing = required - available.keys()
    if missing:
        raise ValueError("Enabled modules unavailable in the new source: " + ", ".join(sorted(missing))
                         + ". Review these modules before upgrading; configuration was not changed.")
    selected = {line.strip() for line in Path(baseline).read_text().splitlines()
                if line.strip() and not line.lstrip().startswith("#")
                and Path(line.strip()).name not in EXCLUDED_MODULES}
    selected.update(available[name] for name in required)
    for module in selected:
        if module not in available.values():
            raise ValueError(f"Build module unavailable in this source: {module}")
    Path(output).write_text("\n".join(sorted(selected)) + "\n")


def prune_retired(conf):
    files = set()
    loaded_modules(conf, files)
    pattern = re.compile(r'<!--.*?-->|<load\b[^>]*\bmodule\s*=\s*([\'"])(mod_\w+)\1[^>]*?(?:/>|>\s*</load>)', re.DOTALL)
    for path in files:
        original = path.read_bytes().decode("utf-8")
        updated = pattern.sub(lambda match: "" if match[2] in EXCLUDED_MODULES else match[0], original)
        if original != updated:
            path.write_bytes(updated.encode("utf-8"))


def retire_binaries(module_dir):
    for name in EXCLUDED_MODULES:
        path = Path(module_dir) / (name + ".so")
        if path.is_file() or path.is_symlink():
            path.unlink()


def check_package_plan(plan):
    allowed = {"freeswitch-" + name.replace("_", "-") for name in EXCLUDED_MODULES}
    allowed |= {name + "-dbg" for name in allowed}
    removals = {line.split()[1].split(":")[0] for line in Path(plan).read_text().splitlines()
                if line.startswith("Remv ")}
    if removals - allowed:
        raise ValueError("APT would remove unrelated packages: " + ", ".join(sorted(removals - allowed)))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest="command", required=True)
    for name in ("compare", "restore"):
        child = commands.add_parser(name)
        child.add_argument("backup")
        child.add_argument("target")
    child = commands.add_parser("select-modules")
    for name in ("source", "conf", "baseline", "output"):
        child.add_argument(name)
    child = commands.add_parser("check-modules")
    child.add_argument("conf")
    child.add_argument("module_dir")
    child = commands.add_parser("prune-retired")
    child.add_argument("conf")
    child = commands.add_parser("retire-binaries")
    child.add_argument("module_dir")
    child = commands.add_parser("check-package-plan")
    child.add_argument("plan")
    args = parser.parse_args()
    try:
        if args.command == "compare":
            changed = differences(inventory(args.backup), inventory(args.target))
            for name in changed:
                print(name)
            return bool(changed)
        if args.command == "restore":
            restore(args.backup, args.target)
        elif args.command == "select-modules":
            select_modules(args.source, args.conf, args.baseline, args.output)
        elif args.command == "check-modules":
            missing = [name for name in sorted(loaded_modules(args.conf) - EXCLUDED_MODULES) if not (Path(args.module_dir) / (name + ".so")).is_file()]
            if missing:
                raise ValueError("Enabled modules missing from the candidate build: " + ", ".join(missing))
        elif args.command == "prune-retired":
            prune_retired(args.conf)
        elif args.command == "retire-binaries":
            retire_binaries(args.module_dir)
        elif args.command == "check-package-plan":
            check_package_plan(args.plan)
        return 0
    except (OSError, ValueError, ET.ParseError, subprocess.CalledProcessError) as error:
        print(f"FreeSWITCH configuration check failed: {error}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
