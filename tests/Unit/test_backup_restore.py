"""Fixture checks for FS PBX backup packaging and restore. No production services or data."""
import hashlib
import importlib.util
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
DUMP = "var/custom backup/postgresql/fusionpbx_pgsql_2026-01-02.sql"
KEY = "base64:" + "YWFh" * 10 + "YWE="


def put(root, name, data):
    path = root / name
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(data)


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
        self.assertIn(KEY.encode(), env)
        self.assertIn(self.media, roots)
        self.assertEqual((self.stage / "database.dump").read_bytes(), b"fixture dump")
        self.assertEqual((self.stage / "files" / self.media / "voicemail/message.wav").read_bytes(), b"audio")
        self.assertFalse((self.stage / "files/storage/app/public/frontend").exists())
        with tarfile.open(self.archive) as archive:
            self.assertNotIn(restore.APP_MEMBER + "/vendor/dependency", archive.getnames())

    def test_legacy_full_archive(self):
        with tarfile.open(self.archive, "w:gz") as archive:
            for name in (DUMP, self.media, self.recordings, restore.APP_MEMBER):
                archive.add(self.site / name, arcname=name)
        self.assertEqual(restore.stage_archive(self.archive, self.stage)[0], {})

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

    def test_environment_preserves_target_credentials_and_imports_multiline_secret(self):
        # Parse fixtures with the installed PHP dotenv library as the account running this test.
        def local_run(args, **kwargs):
            return subprocess.run(args[4:] if args[0] == "runuser" else args, cwd=REPO, check=True,
                                  **{k: v for k, v in kwargs.items() if k != "cwd"})

        dest = self.root / "destination"
        put(dest, ".env", b'APP_KEY=previous\nDB_PASSWORD="new local password"\nFS_ESL_PW=new-esl\n'
            b'GOOGLE_PRIVATE_KEY="previous\nvalue"\n# keep comment\n')
        with patch.object(restore, "APP", dest), patch.object(restore, "run", local_run):
            source = (self.app / ".env").read_bytes() + b'GOOGLE_PRIVATE_KEY="new\\nvalue"\nFS_ESL_PW=old\n'
            current, merged = restore.prepare_environment(source, self.manifest)
            settings = restore.dotenv(merged)
            self.assertEqual(settings["APP_KEY"], KEY)
            self.assertEqual(settings["DB_PASSWORD"], current["DB_PASSWORD"])
            self.assertEqual(settings["FS_ESL_PW"], "new-esl")
            self.assertEqual(settings["GOOGLE_PRIVATE_KEY"], "new\nvalue")
            self.assertEqual(merged.count(b"APP_KEY="), 1)
            self.assertIn(b"# keep comment", merged)
            with self.assertRaisesRegex(ValueError, "encryption"):
                restore.prepare_environment(source, {**self.manifest, "key_hash": "mismatch"})

    @unittest.skipUnless(os.getuid() == 0, "restored file ownership requires root")
    def test_install_only_the_staged_app_data(self):
        dest = self.root / "destination"
        put(dest, ".env", b"fresh\n")
        put(dest, "storage/app/public/frontend/fresh", b"fresh frontend")
        self.package()
        restore.stage_archive(self.archive, self.stage)
        with patch.object(restore, "APP", dest):
            restore.install_files(self.stage, {"storage/app"}, b"merged\n")
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
