"""Fixture checks for FS PBX backup packaging and restore. No production services or data."""
import hashlib
import importlib.util
from contextlib import ExitStack
import json
import os
from pathlib import Path
import subprocess
import tarfile
import tempfile
import unittest
from unittest.mock import patch

REPO = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("restore", REPO / "install/restore-fspbx.py")
restore = importlib.util.module_from_spec(spec)
spec.loader.exec_module(restore)
DUMP = "var/custom backup/postgresql/fspbx_pgsql_2026-01-02.sql"
KEY = "base64:" + "YWFh" * 10 + "YWE="


def put(root, name, data):
    path = root / name
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(data)


def parse_fixture(args, **kwargs):
    # Use the project's dotenv parser without switching accounts or booting Laravel.
    if args[:5] != ["runuser", "-u", "www-data", "--", "php"] or args[5] != "-r":
        raise AssertionError("Unexpected external command in environment fixture")
    kwargs["cwd"] = REPO
    return subprocess.run(args[4:], check=True, **kwargs)


class RecoveryTests(unittest.TestCase):
    def setUp(self):
        tmp = tempfile.TemporaryDirectory()
        self.addCleanup(tmp.cleanup)
        self.root = Path(tmp.name)
        self.stage = self.root / "stage"
        self.stage.mkdir()
        self.site = self.root / "site"
        self.app = self.site / "var/www/fspbx"
        self.media = "var/lib/freeswitch/storage"
        self.recordings = "var/lib/freeswitch/recordings"
        self.manifest = {"format": 1, "app": restore.APP_MEMBER, "dump": DUMP, "release": "2.2.8",
                         "modules": [], "cipher": "AES-256-CBC", "key_hash": hashlib.sha256(KEY.encode()).hexdigest()}
        put(self.root, "fspbx-migration.json", json.dumps(self.manifest).encode())
        put(self.site, DUMP, b"fixture dump")
        put(self.site, self.media + "/voicemail/message.wav", b"audio")
        put(self.site, self.recordings + "/example.wav", b"recording")
        put(self.app, "config/version.php", b"<?php return ['release' => '2.2.8'];")
        put(self.app, "storage/app/public/logo.png", b"branding")
        put(self.app, "storage/app/public/frontend/old-build", b"obsolete")
        put(self.app, ".env", f"APP_KEY={KEY}\nDB_PASSWORD=old\n".encode())
        put(self.app, "vendor/dependency", b"skip me")
        self.archive = self.root / "archive.partial"

    def package(self):
        # Exercise the real nightly packager with a symlinked install and a custom backup path.
        alias = self.root / "fspbx"
        alias.symlink_to(self.app)
        subprocess.run(["php", "-r", r"""
require 'vendor/autoload.php';
$packer = new ReflectionMethod(App\Console\Commands\BackupApp::class, 'package');
$packer->invoke(new App\Console\Commands\BackupApp, $argv[1], $argv[2], $argv[3], array_slice($argv, 4));
""", str(self.root), str(alias), str(self.site), DUMP, self.media, self.recordings], check=True, cwd=REPO)

    def test_packager_and_restore_agree(self):
        self.package()
        result, env, version, roots, modules = restore.stage_archive(self.archive, self.stage)
        self.assertEqual((result, version, modules), (self.manifest, "2.2.8", set()))
        self.assertEqual(env, (self.app / ".env").read_bytes())
        self.assertIn(self.media, roots)
        self.assertEqual((self.stage / "database.dump").read_bytes(), b"fixture dump")
        self.assertEqual((self.stage / "files" / self.media / "voicemail/message.wav").read_bytes(), b"audio")
        self.assertFalse((self.stage / "files/storage/app/public/frontend").exists())
        with tarfile.open(self.archive) as archive:
            self.assertNotIn(restore.APP_MEMBER + "/vendor/dependency", archive.getnames())

    def publish(self, root, date, keep_count=None, write_archive=True):
        dump = root / "postgresql" / f"fspbx_pgsql_{date}.sql"
        archive = root / f"backup_{date}.tgz"
        put(dump.parent, dump.name, b"database")
        partial = root / "archive.partial"
        if write_archive:
            with tarfile.open(partial, "w:gz") as contents:
                contents.add(dump, arcname=dump.name)
        args = ["php", "-r", r"""
require 'vendor/autoload.php';
$publish = new ReflectionMethod(App\Console\Commands\BackupApp::class, 'publishArchive');
try {
    $publish->invoke(new App\Console\Commands\BackupApp, $argv[1], $argv[2], $argv[3], $argv[4] ?? null);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage());
    exit(1);
}
""", str(partial), str(archive), str(dump)]
        if keep_count is not None:
            args.append(str(keep_count))
        return subprocess.run(args, cwd=REPO, capture_output=True, text=True)

    def test_retention_counts_full_archives_and_removes_the_packaged_dump(self):
        for setting, expected_count in ((None, 5), (2, 2), (1, 1)):
            with self.subTest(setting=setting):
                root = self.root / f"backups [{setting}]"
                dates = [f"2026-01-{day:02}" for day in range(1, 8)]
                put(root, "backup_copy-01-01.tgz", b"unrelated")
                for index, date in enumerate(dates):
                    result = self.publish(root, date, setting)
                    self.assertEqual(result.returncode, 0, result.stderr)
                    archive = root / f"backup_{date}.tgz"
                    os.utime(archive, (100 - index, 100 - index))
                    keep = dates[max(0, index + 1 - expected_count):index + 1]
                    self.assertEqual({p.name for p in root.glob("*.tgz")},
                                     {"backup_copy-01-01.tgz"} | {f"backup_{d}.tgz" for d in keep})
                    self.assertEqual(list((root / "postgresql").iterdir()), [])
                    with tarfile.open(archive) as contents:
                        self.assertEqual(contents.extractfile(f"fspbx_pgsql_{date}.sql").read(), b"database")

    def test_one_leftover_dump_rotates_per_new_archive_date(self):
        root = self.root / "backups"
        directory = root / "postgresql"
        oldest = "fusionpbx_pgsql_2025-12-30.sql"
        middle = "fspbx_pgsql_2025-12-31.sql"
        newest = "fusionpbx_pgsql_2026-01-01.sql"
        unrelated = {"unrelated.sql", "fusionpbx_pgsql_2026-01-01.sql.bak", "fusionpbx_pgsql_copy-01-01.sql"}
        for name in (newest, middle, oldest, *unrelated):
            put(directory, name, b"fixture")
        os.utime(directory / newest, (1, 1))
        put(directory / "archive", oldest, b"fixture")
        for day, remaining in ((2, {middle, newest}), (2, {middle, newest}), (3, {newest}), (4, set())):
            result = self.publish(root, f"2026-01-0{day}")
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertEqual({p.name for p in directory.iterdir() if p.is_file()}, unrelated | remaining)
        self.assertTrue((directory / "archive" / oldest).is_file())

    def test_failed_publication_preserves_dumps_and_previous_archive(self):
        root = self.root / "backups"
        put(root, "backup_2026-01-01.tgz", b"previous archive")
        put(root, "postgresql/fusionpbx_pgsql_2025-12-31.sql", b"old dump")
        result = self.publish(root, "2026-01-02", 1, write_archive=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual((root / "backup_2026-01-01.tgz").read_bytes(), b"previous archive")
        self.assertEqual((root / "postgresql/fusionpbx_pgsql_2025-12-31.sql").read_bytes(), b"old dump")
        self.assertEqual((root / "postgresql/fspbx_pgsql_2026-01-02.sql").read_bytes(), b"database")

    def test_invalid_limit_saves_the_new_archive_and_skips_rotation(self):
        root = self.root / "backups"
        put(root, "backup_2026-01-01.tgz", b"previous archive")
        put(root, "postgresql/fusionpbx_pgsql_2025-12-31.sql", b"old dump")
        result = self.publish(root, "2026-01-02", 0)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("backup_keep_count must be a positive integer", result.stderr)
        self.assertTrue((root / "backup_2026-01-02.tgz").is_file())
        self.assertTrue((root / "backup_2026-01-01.tgz").is_file())
        self.assertTrue((root / "postgresql/fusionpbx_pgsql_2025-12-31.sql").is_file())

    def test_full_archive_without_manifest(self):
        with tarfile.open(self.archive, "w:gz") as archive:
            for name in (DUMP, self.media, self.recordings, restore.APP_MEMBER):
                archive.add(self.site / name, arcname=name)
        self.assertEqual(restore.stage_archive(self.archive, self.stage)[0], {})
        self.assertEqual((self.stage / "database.dump").read_bytes(), b"fixture dump")

    def test_damaged_gzip_trailer_is_rejected(self):
        self.package()
        self.archive.write_bytes(self.archive.read_bytes()[:-6])
        with self.assertRaises((EOFError, OSError, tarfile.TarError)):
            restore.stage_archive(self.archive, self.stage)

    def test_incomplete_archive_and_unsafe_member_are_rejected(self):
        for name in (DUMP, "../escaped"):
            with self.subTest(name=name):
                with tarfile.open(self.archive, "w:gz") as archive:
                    archive.add(self.site / DUMP, arcname=name)
                with self.assertRaises(ValueError):
                    restore.stage_archive(self.archive, self.stage)

    def test_complete_environment_with_only_target_postgres_connection(self):
        dest = self.root / "destination"
        put(dest, ".env", b'APP_KEY=previous\nAPP_URL=https://temporary.example\nDB_PASSWORD="new local password"\n'
            b'DB_PORT=5433\nSESSION_DOMAIN=temporary.example\nFS_ESL_PW=new-esl\nTARGET_ONLY=unused\n')
        with patch.object(restore, "APP", dest), patch.object(restore, "run", parse_fixture):
            source = (f'APP_KEY={KEY}\r\n'.encode() + b'# operator notes\r\n\r\n'
                      b'APP_NAME="Site PBX"\r\nAPP_URL=https://pbx.example\r\nSESSION_DOMAIN=pbx.example\r\n'
                      b'SANCTUM_STATEFUL_DOMAINS=pbx.example\r\nQUEUE_CONNECTION=sync\r\nFS_ESL_PW=archived-esl\r\n'
                      b'MODULE_PRIVATE_KEY="first line\r\nDB_PASSWORD=old\r\n# part of key\r\nlast line"\r\n'
                      b'DB_PASSWORD=old\r\nCUSTOM_TOKEN="${DB_PASSWORD}"\r\n'
                      b'DATABASE_URL=postgres://old.example/pbx\r\nDB_TIMEZONE=Europe/London\r\n')
            current, merged = restore.prepare_environment(source, self.manifest)
            settings = restore.dotenv(merged)
            expected = restore.dotenv(source)
            expected.update(DB_PASSWORD="new local password", DB_PORT="5433")
            del expected["DATABASE_URL"]
            self.assertEqual(settings, expected)
            self.assertEqual(settings["DB_PASSWORD"], current["DB_PASSWORD"])
            self.assertIn(b'# operator notes\r\n\r\nAPP_NAME="Site PBX"\r\n', merged)
            self.assertIn(b'first line\r\nDB_PASSWORD=old\r\n# part of key\r\nlast line', merged)
            self.assertNotIn(b"TARGET_ONLY", merged)
            self.assertEqual(merged.count(b"\r\n"), merged.count(b"\n"))
            put(dest, ".env", merged)
            self.assertEqual(restore.prepare_environment(source, self.manifest)[1], merged)
            with self.assertRaisesRegex(ValueError, "encryption"):
                restore.prepare_environment(source, {**self.manifest, "key_hash": "mismatch"})

    def test_matching_database_preserves_exact_archived_bytes(self):
        dest = self.root / "destination"
        put(dest, ".env", b'DB_PASSWORD="old"\nINSTALLER_ONLY=irrelevant\n')
        source = (self.app / ".env").read_bytes() + b"UNKNOWN_SECRET='spaces # $'  # keep this comment"
        with patch.object(restore, "APP", dest), patch.object(restore, "run", parse_fixture):
            self.assertEqual(restore.prepare_environment(source, self.manifest)[1], source)

    def test_repair_reads_env_from_backup_without_staging_media_or_dump(self):
        self.package()
        manifest, env, version, _, _ = restore.stage_archive(self.archive, self.stage, environment_only=True)
        self.assertEqual((manifest, env, version), (self.manifest, (self.app / ".env").read_bytes(), "2.2.8"))
        self.assertEqual(list(self.stage.iterdir()), [])

    def test_environment_repair_requires_matching_restore_and_keeps_existing_databases(self):
        with ExitStack() as stack:
            stub = lambda name, **kw: stack.enter_context(patch.object(restore, name, **kw))
            stub("prepare_environment", return_value=({"APP_KEY": KEY}, b"repaired"))
            stub("dotenv", return_value={"APP_KEY": KEY})
            stub("pg_environment", return_value={})
            sql = stub("sql", return_value="destination")
            stub("restored_database", return_value=False)
            retained = stub("installer_database_exists", return_value=False)
            write = stub("install_environment")
            command = stub("run")
            with self.assertRaisesRegex(ValueError, "previously restored"):
                restore.repair_environment(b"source", {"cluster": "archive"})
            write.assert_not_called()
            retained.return_value = True
            with self.assertRaisesRegex(ValueError, "source PostgreSQL"):
                restore.repair_environment(b"source", {"cluster": "destination"})
            write.assert_not_called()
            restore.repair_environment(b"source", {"cluster": "archive"})
            write.assert_called_once_with(b"repaired", backup_dir="/root")
            self.assertEqual(command.call_count, 1)
            self.assertIn("config:clear", command.call_args.args[0])
            self.assertTrue(sql.call_args.args[2].startswith("ALTER DATABASE fusionpbx SET fspbx.restored"))

    def test_start_includes_installed_services_even_when_disabled(self):
        listing = ("cron.service enabled enabled\n"
                   "php7.4-fpm.service masked enabled\n"
                   "php8.4-fpm.service enabled enabled\n"
                   "supervisor.service disabled enabled\n"
                   "freeswitch.service enabled enabled\n")
        with patch.object(restore, "run", return_value=subprocess.CompletedProcess([], 0, listing)) as command:
            self.assertEqual(restore.installed_pbx_services(),
                             ["php8.4-fpm.service", "freeswitch.service", "supervisor.service", "cron.service"])
            self.assertIn("list-unit-files", command.call_args.args[0])
            self.assertIn("php*-fpm.service", command.call_args.args[0])

    def test_failed_service_start_reports_retry_and_attempts_remaining_services(self):
        services = ["nginx.service", "supervisor.service", "cron.service"]
        failure = subprocess.CalledProcessError(1, ["systemctl", "start", "nginx.service"])
        with patch.object(restore, "run", side_effect=[failure, None, None]) as command:
            with self.assertRaisesRegex(ValueError, r"Data restore completed.*systemctl start nginx\.service$"):
                restore.start_pbx_services(services)
            self.assertEqual([call.args[0] for call in command.call_args_list],
                             [["systemctl", "start", unit] for unit in services])

    def test_cleanup_and_service_start_only_after_success(self):
        with ExitStack() as stack:
            stub = lambda name, **kw: stack.enter_context(patch.object(restore, name, **kw))
            stub("pg_environment", return_value={})
            stub("sql", return_value="destination")
            stub("restored_database", return_value=False)
            stub("installer_database_exists", return_value=False)
            stub("standalone")
            stub("activate_database")
            services = ["php8.4-fpm.service", *restore.PBX_SERVICES]
            stub("installed_pbx_services", return_value=services)
            files = stub("install_files", side_effect=OSError("fixture file copy failed"))
            stub("install_environment")
            command = stub("run")
            args = (self.stage, {"cluster": "archive"}, {}, b"recovered", set(), "")
            with self.assertRaisesRegex(OSError, "file copy failed"):
                restore.apply(*args, recover_ha=False, web_tls=False)
            calls = [call.args[0] for call in command.call_args_list]
            self.assertIn(["systemctl", "stop", *services], calls)
            self.assertFalse(any(cmd[0] == "dropdb" or cmd[:2] == ["systemctl", "start"] for cmd in calls))
            files.side_effect = None
            for recover_ha in (False, True):
                with self.subTest(recover_ha=recover_ha):
                    command.reset_mock()
                    restore.apply(*args, recover_ha=recover_ha, web_tls=False)
                    calls = [call.args[0] for call in command.call_args_list]
                    cleanup = calls.index(["dropdb", "-w", restore.BEFORE])
                    self.assertEqual(calls[cleanup + 1:], [["systemctl", "start", unit] for unit in services])

    @unittest.skipUnless(os.getuid() == 0, "restored file ownership requires root")
    def test_install_only_the_staged_app_data(self):
        dest = self.root / "destination"
        put(dest, ".env", b"fresh\n")
        put(dest, "storage/app/public/frontend/fresh", b"fresh frontend")
        self.package()
        restore.stage_archive(self.archive, self.stage)
        with patch.object(restore, "APP", dest):
            restore.install_files(self.stage, {"storage/app"})
            restore.install_environment(b"merged\n", backup_dir=self.root)
            restore.install_environment(b"merged\n", backup_dir=self.root)
        backup, = self.root.glob("fspbx-env-before-repair-*")
        self.assertEqual(backup.read_bytes(), b"fresh\n")
        self.assertEqual(backup.stat().st_mode & 0o777, 0o600)
        self.assertEqual((dest / ".env").read_bytes(), b"merged\n")
        self.assertEqual((dest / ".env").stat().st_mode & 0o777, 0o600)
        logo = dest / "storage/app/public/logo.png"
        self.assertEqual(logo.read_bytes(), b"branding")
        self.assertEqual(logo.owner(), "www-data")
        self.assertEqual((dest / "storage/app/public/frontend/fresh").read_bytes(), b"fresh frontend")

    def test_certificate_pair_must_match(self):
        chain, key = self.stage / restore.WEB_CHAIN, self.stage / restore.WEB_KEY
        key.parent.mkdir(parents=True)
        subprocess.run(["openssl", "req", "-x509", "-nodes", "-newkey", "rsa:2048", "-days", "1",
                        "-subj", "/CN=example.test", "-keyout", key, "-out", chain],
                       check=True, stderr=subprocess.DEVNULL)
        self.assertTrue(restore.web_certificate_available(self.stage))
        subprocess.run(["openssl", "genrsa", "-out", key, "2048"], check=True, stderr=subprocess.DEVNULL)
        with self.assertRaisesRegex(ValueError, "do not match"):
            restore.web_certificate_available(self.stage)


if __name__ == "__main__":
    unittest.main()
