#!/usr/bin/env python3
"""Restore a trusted FS PBX full backup to a freshly installed, isolated server."""

import argparse
import base64
import fcntl
import gzip
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile
import uuid

APP = Path("/var/www/fspbx")
APP_MEMBER = "var/www/fspbx"
# Keep the target's installed binaries, generated frontend, OS services and DB credentials.
DATA_ROOTS = (
    "var/lib/freeswitch/storage",
    "var/lib/freeswitch/recordings",
    "var/lib/freeswitch/images",
    "usr/share/freeswitch/sounds",
    "etc/freeswitch/tls",
    "usr/share/fusionpbx/templates/provision",
    "usr/share/fspbx/templates/provision",
    "etc/fusionpbx/resources/templates/provision",
)
APP_DATA = ("storage/app", "public/resources/templates/provision", "resources/freeswitch_scripts/custom")
APP_FILES = ("modules_statuses.json", "storage/oauth-private.key", "storage/oauth-public.key")
WEB_CHAIN = "etc/nginx/ssl/fullchain.pem"
WEB_KEY = "etc/nginx/ssl/private/privkey.pem"
ASSETS = ("storage/app/frontend", "storage/app/public/frontend", "storage/app/public/vite")
DB_CONNECTION_KEYS = {"DB_CONNECTION", "DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME",
                      "DB_PASSWORD", "DATABASE_URL"}
SERVICES = ("cron", "nginx", "php*-fpm.service", "supervisor", "event_guard", "freeswitch")
DB_NAME = "fusionpbx"
BEFORE = "fspbx_before_restore"


def within(name, root):
    return name == root or name.startswith(root + "/")


def run(args, **kwargs):
    return subprocess.run(args, check=True, **kwargs)


def read_small(archive, entry):
    if not entry.isfile() or entry.size > 1024 * 1024:
        raise ValueError(f"Expected a small regular file at {entry.name}.")
    return archive.extractfile(entry).read()


def stage_archive(filename, stage, *, environment_only=False):
    """Read gzip once. Only write regular files beneath a new, private staging directory."""
    manifest = None
    roots = set()
    metadata = {}
    dump = None
    modules = set()
    template_links = {}
    external_roots = set()
    with gzip.open(filename, "rb") as gz, tarfile.open(fileobj=gz, mode="r|") as archive:
        for entry in archive:
            name = entry.name
            if name.startswith("/") or ".." in PurePosixPath(name).parts:
                raise ValueError("Unsafe path in archive.")
            name = str(PurePosixPath(name))
            if name == "fspbx-migration.json":
                if manifest is not None:
                    raise ValueError("Duplicate migration metadata.")
                manifest = json.loads(read_small(archive, entry))
                if not isinstance(manifest, dict) or manifest.get("format") != 1:
                    raise ValueError("Unsupported FS PBX backup format.")
                continue
            if name in (APP_MEMBER + "/.env", APP_MEMBER + "/config/version.php"):
                relative = name[len(APP_MEMBER) + 1:]
                if relative in metadata:
                    raise ValueError(f"Duplicate {relative} in backup.")
                metadata[relative] = read_small(archive, entry)
                continue
            if environment_only:
                continue
            if name in (WEB_CHAIN, WEB_KEY) and entry.isfile():
                dest = stage / name
                dest.parent.mkdir(parents=True, exist_ok=True)
                with dest.open("xb") as out:
                    out.write(read_small(archive, entry))
                continue
            if re.fullmatch(r"(?:.*/)?postgresql/fspbx_pgsql_\d{4}-\d\d-\d\d\.sql", name):
                if dump or not entry.isfile():
                    raise ValueError("Expected exactly one regular PostgreSQL dump.")
                dump = name
                with (stage / "database.dump").open("xb") as out:
                    shutil.copyfileobj(archive.extractfile(entry), out)
                continue

            app_root = name.startswith(APP_MEMBER + "/")
            relative = name[len(APP_MEMBER) + 1:] if app_root else name
            if app_root:
                if re.fullmatch(r"Modules/[^/]+/module\.json", relative):
                    modules.add(relative.split("/")[1])
                if any(within(relative, path) for path in ASSETS):
                    continue
            root = next((p for p in (APP_DATA if app_root else DATA_ROOTS) if within(relative, p)), None)
            if app_root and relative in APP_FILES:
                if not entry.isfile():
                    raise ValueError(f"Expected a regular file at {name}.")
                root = relative
            if not root:
                continue
            roots.add(root)
            target = stage / "files" / relative
            if entry.isdir():
                target.mkdir(parents=True, exist_ok=True)
            elif entry.isfile():
                target.parent.mkdir(parents=True, exist_ok=True)
                with target.open("xb") as out:
                    shutil.copyfileobj(archive.extractfile(entry), out)
                os.utime(target, (entry.mtime, entry.mtime))
                if entry.mode & 0o111:
                    target.chmod(0o700)
            elif entry.islnk():
                link = PurePosixPath(entry.linkname)
                prefix = PurePosixPath(APP_MEMBER) if app_root else PurePosixPath(".")
                if ".." in link.parts or not link.is_relative_to(prefix / root):
                    raise ValueError(f"Hard link outside the archived data directory at {name}.")
                original = stage / "files" / link.relative_to(prefix)
                if not original.is_file():
                    raise ValueError(f"Missing original file for the archived hard link {name}.")
                target.parent.mkdir(parents=True, exist_ok=True)
                os.link(original, target)
            elif entry.issym() and relative != root and "templates/provision" in root:
                base = APP if app_root else Path("/")
                dest = (base / relative).parent / entry.linkname
                allowed = (base / root, APP / "resources/provisioning")
                if not any(dest.resolve().is_relative_to(p.resolve()) for p in allowed):
                    raise ValueError(f"Reconcile the external provisioning link {name} before restoring.")
                if target in template_links:
                    raise ValueError("Duplicate template link in backup.")
                template_links[target] = entry.linkname
            elif entry.issym():
                dest = (APP if app_root else Path("/")) / relative
                if not dest.is_symlink() or os.readlink(dest) != entry.linkname or not dest.exists():
                    raise ValueError(f"Recover the external files and recreate the link {dest} on the destination first.")
                if root == relative:
                    external_roots.add(root)
            else:
                raise ValueError(f"Linked or special data at {name}. Back up its actual files and reconcile the path.")
        # tar stops at its end marker. Read the gzip trailer as well to detect truncation or a bad CRC.
        while gz.read(1024 * 1024):
            pass

    media = {"var/lib/freeswitch/storage", "var/lib/freeswitch/recordings"}
    required = {"storage/app"} | set((manifest or {}).get("media", media))
    if set(metadata) != {".env", "config/version.php"}:
        raise ValueError("The backup must contain the application environment and release.")
    version = release(metadata["config/version.php"])
    if manifest and (APP_MEMBER != manifest["app"] or version != manifest["release"]):
        raise ValueError("Archive contents disagree with the migration metadata.")
    if environment_only:
        return manifest or {}, metadata[".env"], version, set(), set()
    if not dump or required - roots or (manifest and dump != manifest["dump"]):
        raise ValueError("Incomplete full backup. The expected database and PBX media directories are required.")
    for missing in sorted(media - roots):
        print(f"The source did not back up /{missing}. Verify any external media before cutover.")
    # Materialize validated links only after all archive file writes, deepest first.
    for path in sorted(template_links, key=lambda p: len(p.parts), reverse=True):
        path.parent.mkdir(parents=True, exist_ok=True)
        path.symlink_to(template_links[path])
    return manifest or {}, metadata[".env"], version, roots - external_roots, modules


def release(contents):
    match = re.search(rb"""['"]release['"]\s*=>\s*['"](\d+\.\d+\.\d+)['"]""", contents)
    if not match:
        raise ValueError("Could not identify the FS PBX release.")
    return match[1].decode()


def dotenv(contents, entries=False):
    # Use the fresh install's parser for quoted secrets, multiline values and variable expansion.
    parse = (r"\Dotenv\Parser\Lines::process(preg_split('/\r\n|\n|\r/', $s))" if entries
             else r"(object) \Dotenv\Dotenv::parse($s)")
    code = ("require 'vendor/autoload.php'; $s=stream_get_contents(STDIN); try { echo json_encode("
            + parse + r", JSON_THROW_ON_ERROR); } catch (\Throwable $e) { exit(1); }")
    result = run(["runuser", "-u", "www-data", "--", "php", "-r", code], cwd=APP,
                 input=contents, stdout=subprocess.PIPE)
    return json.loads(result.stdout)


def rewrite_assignments(source, entries, expected, changed):
    """Edit whole dotenv entries, retaining all intervening comments, blank lines and line endings."""
    lines = iter(re.findall(r"([^\r\n]*)(\r\n|\r|\n|\Z)", source))
    pieces = []
    found = set()

    def assignment(name):
        value = expected[name]
        if value is None:  # A bare name unsets the variable in phpdotenv.
            return name
        value = value.replace("\\", "\\\\").replace('"', '\\"').replace("$", "\\$").replace("\r", "\\r").replace("\n", "\\n")
        return f'{name}="{value}"'

    for entry in entries:
        for line, ending in lines:
            if line.strip() and not line.lstrip().startswith("#"):
                break
            pieces.append(line + ending)
        block = [line + ending]
        for _ in range(entry.count("\n")):
            line, ending = next(lines)
            block.append(line + ending)
        name = re.sub(r"^export\s+", "", entry.split("=", 1)[0].strip()).strip("'\"")
        found.add(name)
        if name not in changed:
            pieces.extend(block)
        elif name in expected:
            pieces.append(assignment(name) + ending)
    pieces.extend(line + ending for line, ending in lines)
    result = "".join(pieces)
    additions = [name for name in expected if name in changed and name not in found]
    if additions:
        newline = next(iter(re.findall(r"\r\n|\r|\n", source)), "\n")
        if result and not result.endswith(("\r", "\n")):
            result += newline
        result += "".join(assignment(name) + newline for name in additions)
    return result.encode()


def prepare_environment(source, manifest):
    source_env = dotenv(source)
    key = source_env.get("APP_KEY") or ""
    if len(base64.b64decode(key[7:], validate=True) if key.startswith("base64:") else key.encode()) != 32:
        raise ValueError("Missing or invalid source APP_KEY.")
    if manifest and (manifest["cipher"] != "AES-256-CBC" or hashlib.sha256(key.encode()).hexdigest() != manifest["key_hash"]):
        raise ValueError("The archived .env does not match the source's active encryption configuration.")
    path = APP / ".env"
    if not path.is_file() or path.is_symlink():
        raise ValueError("Expected the fresh installation's regular .env file.")
    current = dotenv(path.read_bytes())
    expected = {k: v for k, v in source_env.items() if k not in DB_CONNECTION_KEYS}
    expected.update({k: v for k, v in current.items() if k in DB_CONNECTION_KEYS})
    if source_env == expected:
        return current, source
    candidate = source
    actual = source_env
    changed = set()
    entries = dotenv(source, entries=True)
    while actual != expected:
        missing = object()
        different = {k for k in actual.keys() | expected.keys() if actual.get(k, missing) != expected.get(k, missing)}
        if different <= changed:
            raise ValueError("Could not preserve the archived settings with the local PostgreSQL connection.")
        # A custom secret may itself reference a DB variable. Preserve its archived effective value.
        changed.update(different)
        candidate = rewrite_assignments(source.decode(), entries, expected, changed)
        actual = dotenv(candidate)
    return current, candidate


def pg_environment(target):
    if (target.get("DB_CONNECTION") != "pgsql" or target.get("DB_DATABASE") != DB_NAME
            or target.get("DB_HOST") not in ("127.0.0.1", "::1", "localhost")
            or target.get("DATABASE_URL") or not target.get("DB_USERNAME")):
        raise ValueError("The restore requires the fresh install's local fusionpbx PostgreSQL database.")
    return {**{k: v for k, v in os.environ.items() if not k.startswith("PG")},
            "PGHOST": target["DB_HOST"], "PGPORT": target.get("DB_PORT", "5432"),
            "PGUSER": target["DB_USERNAME"], "PGPASSWORD": target["DB_PASSWORD"], "PGCONNECT_TIMEOUT": "5"}


def sql(env, db, query):
    return run(["psql", "-X", "-w", "-At", "-v", "ON_ERROR_STOP=1", "-d", db, "-c", query],
               env=env, stdout=subprocess.PIPE, text=True).stdout.strip()


def standalone(env, db):
    if sql(env, db, """SELECT EXISTS(SELECT FROM pg_subscription WHERE subdbid =
        (SELECT oid FROM pg_database WHERE datname=current_database())) OR EXISTS(SELECT FROM pg_publication)""") != "f":
        raise ValueError("This database has logical replication. Use the HA recovery procedure.")
    if sql(env, db, """SELECT EXISTS(SELECT FROM v_default_settings WHERE default_setting_category='scheduled_jobs'
        AND default_setting_subcategory='active_node' AND nullif(default_setting_value,'') IS NOT NULL)""") != "f":
        raise ValueError("Scheduled-job ownership is configured. Follow the HA replacement procedure.")
    if sql(env, db, "SELECT to_regclass('public.scheduled_job_nodes') IS NOT NULL") == "t":
        if sql(env, db, "SELECT EXISTS(SELECT FROM scheduled_job_nodes)") != "f":
            raise ValueError("This PBX has registered scheduled-job nodes. Follow the HA replacement procedure.")


def activate_database(env, staging):
    # Both renames commit together. A remaining connection or a name conflict rolls both back.
    sql(env, "postgres", f'BEGIN; ALTER DATABASE {DB_NAME} RENAME TO {BEFORE}; '
                         f'ALTER DATABASE {staging} RENAME TO {DB_NAME}; COMMIT;')


def restored_database(env):
    return sql(env, DB_NAME, "SELECT current_setting('fspbx.restored', true) IS NOT DISTINCT FROM 'on'") == "t"


def installer_database_exists(env):
    return sql(env, "postgres", f"SELECT EXISTS(SELECT FROM pg_database WHERE datname='{BEFORE}')") == "t"


def web_certificate_available(stage):
    chain, key = stage / WEB_CHAIN, stage / WEB_KEY
    if chain.is_file() and key.is_file():
        public = run(["openssl", "x509", "-in", chain, "-pubkey", "-noout"], stdout=subprocess.PIPE)
        private = run(["openssl", "pkey", "-in", key, "-passin", "pass:", "-pubout"], stdout=subprocess.PIPE)
        if public.stdout != private.stdout:
            raise ValueError("The archived web certificate and private key do not match.")
        if subprocess.run(["openssl", "x509", "-in", chain, "-checkend", "0", "-noout"],
                          stdout=subprocess.DEVNULL).returncode == 0:
            print("The standard web certificate and matching key are available for restore.")
            return True
    print("Prepare a valid web certificate on the destination. Review archived ACME files or arrange issuance.")
    return False


def install_files(stage, roots):
    for root in sorted(roots):
        src = stage / "files" / root
        if root in APP_FILES:
            dest = APP / root
            if dest.resolve() != dest:
                raise ValueError(f"Resolve the conflicting destination link {dest}.")
            run(["install", "-m", "0600", "-o", "www-data", "-g", "www-data", "--", src, dest])
            continue
        dest = (APP if root in APP_DATA else Path("/")) / root
        if dest.resolve() != dest or not src.is_dir():
            raise ValueError(f"Expected a real data directory at {dest}.")
        if not dest.parent.exists():
            run(["install", "-d", "-m", "0755", "--", dest.parent])
        dest.mkdir(exist_ok=True)
        run(["rsync", "-rltpog", "--ignore-times", "--chown=www-data:www-data", "--chmod=Du=rwx,Dg=rx,Do=,Fu=rwX,Fg=rX,Fo=",
             "--", str(src) + "/", str(dest) + "/"])


def install_environment(merged, backup_dir=None):
    path = APP / ".env"
    previous = path.read_bytes()
    if backup_dir is not None and previous != merged:
        with tempfile.NamedTemporaryFile(dir=backup_dir, prefix="fspbx-env-before-repair-", delete=False) as saved:
            saved.write(previous)
        print(f"Previous destination .env saved privately at {saved.name}.")
    owner = path.stat()
    with tempfile.NamedTemporaryFile(dir=APP, prefix=".restore-env-", delete=False) as output:
        temporary = Path(output.name)
        try:
            output.write(merged)
            output.flush()
            os.fchown(output.fileno(), owner.st_uid, owner.st_gid)
            os.replace(temporary, path)
        finally:
            temporary.unlink(missing_ok=True)


def repair_environment(source, manifest):
    target, merged = prepare_environment(source, manifest)
    env = pg_environment(target)
    if manifest.get("cluster") == sql(env, DB_NAME, "SELECT system_identifier::text FROM pg_control_system()"):
        raise ValueError("This is the source PostgreSQL cluster. Repair only the restored replacement.")
    if target.get("APP_KEY") != dotenv(merged)["APP_KEY"]:
        raise ValueError("The archived application key does not match the restored replacement.")
    marked = restored_database(env)
    if not marked and not installer_database_exists(env):
        raise ValueError("Environment repair requires a previously restored replacement.")
    install_environment(merged, backup_dir="/root")
    run(["runuser", "-u", "www-data", "--", "php", "artisan", "config:clear", "--no-interaction"], cwd=APP)
    if not marked:
        # Older restores used the retained installer database as their only restore marker.
        sql(env, "postgres", f"ALTER DATABASE {DB_NAME} SET fspbx.restored = 'on'")
    print("Restored the archived application settings with the replacement's PostgreSQL connection. "
          "Restart any running application workers and reload PHP-FPM to use the recovered settings.")


def apply(stage, manifest, target, merged, roots, toc, recover_ha, web_tls):
    env = pg_environment(target)
    if manifest.get("cluster") == sql(env, DB_NAME, "SELECT system_identifier::text FROM pg_control_system()"):
        raise ValueError("This is the source PostgreSQL cluster. Restore on a fresh replacement.")
    if not manifest.get("cluster") and dotenv(merged)["APP_KEY"] == target.get("APP_KEY"):
        raise ValueError("The destination already uses the archived key. Restore on a fresh installation.")
    if restored_database(env) or installer_database_exists(env):
        raise ValueError("A prior restore was applied. Return to the clean destination snapshot for a new full restore.")
    standalone(env, DB_NAME)
    staging = "fspbx_restore_" + uuid.uuid4().hex
    run(["createdb", "-w", "-T", "template0", staging], env=env)
    try:
        selection = []
        if not manifest:
            # Old schema-filtered dumps explicitly create public and omit its extension dependencies.
            # template0 provides public. Preserve all its table, grant and data entries.
            toc_path = stage / "restore.list"
            toc_path.write_text("\n".join(line for line in toc.splitlines()
                                if not re.match(r"^\d+;\s+\d+\s+\d+\s+SCHEMA - public(?:\s|$)", line)) + "\n")
            selection = ["--use-list", toc_path]
            if " EXTENSION - uuid-ossp " not in toc:
                sql(env, staging, 'CREATE EXTENSION IF NOT EXISTS "uuid-ossp"')
        print("Loading the database into a new staging database...", flush=True)
        run(["pg_restore", "-w", "--exit-on-error", "--no-owner", "--no-tablespaces",
             "--no-publications", "--no-subscriptions", *selection, "-d", staging, stage / "database.dump"], env=env)
        if not recover_ha:
            standalone(env, staging)
        print("Stopping destination PBX services. Keep the replacement isolated until cutover.", flush=True)
        run(["systemctl", "stop", *SERVICES])
        activate_database(env, staging)
    except BaseException:
        # Remove only the interrupted staging DB. A successful swap already renamed it.
        run(["dropdb", "-w", "--if-exists", staging], env=env)
        raise
    install_files(stage, roots)
    # Clear the fresh installation's dedicated cache before switching to the recovered cache settings.
    for command in ("config:clear", "cache:clear"):
        run(["runuser", "-u", "www-data", "--", "php", "artisan", command, "--no-interaction"], cwd=APP)
    install_environment(merged)
    if web_tls:
        for member, mode in ((WEB_CHAIN, "0644"), (WEB_KEY, "0600")):
            dest = Path("/") / member
            if dest.resolve() != dest:
                raise ValueError(f"Resolve the destination certificate link {dest} before restore.")
            run(["install", "-m", mode, "--", stage / member, dest])
    for command in ("config:clear", "view:clear"):
        run(["runuser", "-u", "www-data", "--", "php", "artisan", command, "--no-interaction"], cwd=APP)
    # The native cache flush removes the contents of the configured directory. On a
    # different host, reconcile any custom paths before using them for writes or cleanup.
    standard_paths = sql(env, DB_NAME, """
        SELECT count(DISTINCT expected.subcategory) = 3 AND bool_and(s.default_setting_value IS NOT DISTINCT FROM expected.value)
        FROM v_default_settings s JOIN (VALUES ('cache','method','file'), ('cache','location','/var/cache/fusionpbx'),
            ('switch','conf','/etc/freeswitch')) AS expected(category, subcategory, value)
        ON s.default_setting_category = expected.category AND s.default_setting_subcategory = expected.subcategory
        WHERE s.default_setting_enabled = 'true'
    """) == "t"
    if (standard_paths and all(Path(p).resolve() == Path(p) for p in ("/var/cache/fusionpbx", "/etc/freeswitch"))
            and (APP / "app/Console/Commands/PrepareFreeswitchRestart.php").is_file()):
        run(["runuser", "-u", "www-data", "--", "php", "artisan", "freeswitch:prepare-restart", "--no-interaction"], cwd=APP)
    else:
        print("Before starting FreeSWITCH, verify the switch and cache directories on this host, "
              "synchronize Variables and flush the generated FreeSWITCH XML cache for the installed release.")
    if recover_ha:
        print("HA cold recovery: keep the scheduler, workers and former nodes offline until replication and ownership are verified.")
    # Only reached after this invocation successfully renamed the installer DB and finished recovery.
    sql(env, "postgres", f"ALTER DATABASE {DB_NAME} SET fspbx.restored = 'on'")
    run(["dropdb", "-w", BEFORE], env=env)
    print("Restore applied. The temporary installer database was removed. PBX services are stopped. "
          "Complete the network and service checks in the recovery guide before accepting traffic.")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("archive", type=Path, help="full backup_YYYY-MM-DD.tgz")
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument("--check", action="store_true", help="validate the backup and installed release (default)")
    mode.add_argument("--apply", action="store_true", help="replace data on this fresh destination and leave PBX services stopped")
    mode.add_argument("--repair-env", action="store_true",
                      help="recover only .env on an already restored replacement using its existing database connection")
    parser.add_argument("--fenced-ha-recovery", action="store_true",
                        help="recover data from a fully fenced HA installation without restoring replication connections")
    parser.add_argument("--work-dir", type=Path, default=Path("/var/tmp"), help="directory for private temporary files")
    args = parser.parse_args()
    if os.geteuid() != 0:
        parser.error("run as root so credentials and temporary media remain private")
    os.umask(0o077)
    if not (APP / "artisan").is_file():
        raise ValueError("Install FS PBX at /var/www/fspbx before restoring.")
    if args.apply and not shutil.which("rsync"):
        raise ValueError("Install rsync on the replacement before applying a restore.")
    with tempfile.TemporaryDirectory(prefix="fspbx-restore-", dir=args.work_dir) as tmp:
        stage = Path(tmp)
        manifest, source, version, roots, modules = stage_archive(args.archive, stage, environment_only=args.repair_env)
        if version != release((APP / "config/version.php").read_bytes()):
            raise ValueError(f"Install matching FS PBX release {version} on the destination.")
        if args.repair_env:
            with open("/run/lock/fspbx-restore.lock", "a") as lock:
                fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
                repair_environment(source, manifest)
            return
        for module in modules | set(manifest.get("modules", [])):
            if not (APP / "Modules" / module / "module.json").is_file():
                raise ValueError(f"Install the matching {module} module on the destination.")
        target, merged = prepare_environment(source, manifest)
        web_tls = web_certificate_available(stage)
        toc = run(["pg_restore", "--list", stage / "database.dump"], stdout=subprocess.PIPE, text=True).stdout
        for table in ("v_domains", "v_extensions", "v_default_settings"):
            if f" TABLE DATA public {table} " not in toc:
                raise ValueError(f"Missing PBX table data in dump: {table}.")
        if re.search(r"\b(?:PUBLICATION|SUBSCRIPTION)\b", toc) and args.apply and not args.fenced_ha_recovery:
            raise ValueError("Replication objects found. See the recovery guide for HA replacement.")
        if not manifest:
            print("Legacy full backup. Verify external files, web certificates and encryption key provenance.")
        print(f"FS PBX {version}. Validated the full archive, database inventory and application key.", flush=True)
        if args.apply:
            with open("/run/lock/fspbx-restore.lock", "a") as lock:
                fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
                apply(stage, manifest, target, merged, roots, toc, args.fenced_ha_recovery, web_tls)
        else:
            run(["pg_restore", "--file=/dev/null", stage / "database.dump"])
            print("Check complete. Rehearse --apply on an isolated replacement to validate database import and calls.")


if __name__ == "__main__":
    try:
        main()
    except (OSError, ValueError, KeyError, tarfile.TarError, EOFError, subprocess.CalledProcessError) as error:
        sys.exit(f"Restore stopped: {error}")
