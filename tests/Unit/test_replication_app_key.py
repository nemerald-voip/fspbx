"""Exercise HA key transfer with real Laravel configuration and isolated files."""

import base64
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest


REPO = Path(__file__).resolve().parents[2]
KEY_A = 'base64:' + base64.b64encode(b'a' * 32).decode()
KEY_B = 'base64:' + base64.b64encode(b'b' * 32).decode()


class ReplicationAppKeyTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='fspbx-ha-key-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.env = {key: value for key, value in os.environ.items()
                    if key not in ['APP_KEY', 'APP_ENV', 'APP_BASE_PATH', 'APP_CONFIG_CACHE']}
        self.primary = self.fixture('primary', KEY_A)
        self.standby = self.fixture('standby', KEY_B)

    def fixture(self, name, key):
        root = self.root / name
        (root / 'bootstrap/cache').mkdir(parents=True)
        (root / 'config').mkdir()
        (root / 'vendor').symlink_to(REPO / 'vendor', target_is_directory=True)
        (root / 'bootstrap/app.php').write_text(
            "<?php return new Illuminate\\Foundation\\Application(dirname(__DIR__));\n")
        (root / 'config/app.php').write_text(
            "<?php return ['key' => env('APP_KEY'), 'cipher' => 'AES-256-CBC', "
            "'env' => 'testing', 'timezone' => 'UTC'];\n")
        (root / 'artisan').write_text(r'''<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->bootstrapWith([
    Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
    Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
]);
$console = new Illuminate\Console\Application($app, new Illuminate\Events\Dispatcher($app), 'test');
$console->add(new App\Console\Commands\HaAppKey());
$console->add(new Illuminate\Foundation\Console\ConfigClearCommand(new Illuminate\Filesystem\Filesystem()));
exit($console->run(new Symfony\Component\Console\Input\ArgvInput(),
                  new Symfony\Component\Console\Output\ConsoleOutput()));
''')
        (root / '.env').write_text(f'APP_KEY={key}\nDB_PASSWORD=local-password\n')
        (root / '.env').chmod(0o640)
        return root

    def run_helper(self, root, mode, payload='', **env):
        return subprocess.run(['php', str(root / 'artisan'), 'ha:app-key', mode, '--no-ansi'],
                              input=payload, text=True, capture_output=True,
                              env=self.env | env)

    def exported(self):
        result = self.run_helper(self.primary, 'export')
        self.assertEqual(result.returncode, 0, result.stderr)
        return result.stdout

    def cache_key(self, root, key):
        (root / 'bootstrap/cache/config.php').write_text(
            "<?php return ['app' => ['key' => '" + key + "', 'cipher' => 'AES-256-CBC', "
            "'env' => 'testing', 'timezone' => 'UTC']];\n")

    def assert_no_secrets(self, result):
        for secret in [KEY_A, KEY_B, 'local-password']:
            self.assertNotIn(secret, result.stdout + result.stderr)

    def test_transfer_preserves_other_settings_metadata_and_protected_backup(self):
        original = (f'# APP_KEY=comment\r\nexport APP_KEY = "{KEY_B}" # old key\r\n'
                    'DB_PASSWORD="local-password"\r\nOTHER="leave $this alone"').encode()
        env_file = self.standby / '.env'
        env_file.write_bytes(original)
        metadata = env_file.stat()
        self.cache_key(self.standby, KEY_B)
        payload = self.exported()
        check = self.run_helper(self.standby, 'check', payload)
        self.assertEqual(check.returncode, 0, check.stderr)
        self.assertEqual(env_file.read_bytes(), original)

        result = self.run_helper(self.standby, 'import', payload)
        self.assertEqual(result.returncode, 0, result.stderr)
        expected = original.replace(f'export APP_KEY = "{KEY_B}" # old key'.encode(),
                                    f'APP_KEY={KEY_A}'.encode())
        self.assertEqual(env_file.read_bytes(), expected)
        self.assertEqual(env_file.stat().st_mode, metadata.st_mode)
        self.assertEqual(env_file.stat().st_uid, metadata.st_uid)
        self.assertEqual(env_file.stat().st_gid, metadata.st_gid)
        backups = list(self.standby.glob('.env.ha-backup-*'))
        self.assertEqual(len(backups), 1)
        self.assertEqual(backups[0].read_bytes(), original)
        self.assertEqual(backups[0].stat().st_mode & 0o777, 0o600)
        self.assertFalse((self.standby / 'bootstrap/cache/config.php').exists())
        verify = self.run_helper(self.standby, 'verify', payload)
        self.assertEqual(verify.returncode, 0, verify.stderr)
        self.assert_no_secrets(result)

    def test_uncached_standby_can_import_and_retry_without_another_backup(self):
        payload = self.exported()
        for attempt in range(2):
            result = self.run_helper(self.standby, 'import', payload)
            self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(len(list(self.standby.glob('.env.ha-backup-*'))), 1)
        self.assertEqual(self.run_helper(self.standby, 'verify', payload).returncode, 0)

    def test_matching_keys_only_clear_stale_cache(self):
        original = f"APP_KEY='{KEY_A}'\nDB_PASSWORD=local-password\n"
        (self.standby / '.env').write_text(original)
        self.cache_key(self.standby, KEY_B)
        result = self.run_helper(self.standby, 'import', self.exported())
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual((self.standby / '.env').read_text(), original)
        self.assertEqual(list(self.standby.glob('.env.ha-backup-*')), [])
        self.assertFalse((self.standby / 'bootstrap/cache/config.php').exists())

    def test_primary_cached_key_mismatch_fails_without_exporting_secrets(self):
        self.cache_key(self.primary, KEY_B)
        result = self.run_helper(self.primary, 'export')
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('differs from its effective configuration', result.stderr)
        self.assert_no_secrets(result)

    def test_invalid_missing_duplicate_and_multiline_keys_fail_without_changes(self):
        payload = self.exported()
        for contents in ['APP_KEY=short\n', 'DB_PASSWORD=local-password\n',
                         f'APP_KEY={KEY_B}\nAPP_KEY={KEY_B}\n',
                         'APP_KEY="1234567890123456\n123456789012345"\n']:
            with self.subTest(contents=contents):
                (self.standby / '.env').write_text(contents)
                result = self.run_helper(self.standby, 'import', payload)
                self.assertNotEqual(result.returncode, 0)
                self.assertEqual((self.standby / '.env').read_text(), contents)
                self.assertEqual(list(self.standby.glob('.env.ha-backup-*')), [])
                self.assert_no_secrets(result)

    def test_different_cipher_fails_without_changes(self):
        original = (self.standby / '.env').read_bytes()
        payload = self.exported()
        different_cipher = json.dumps(json.loads(payload) | {'cipher': 'AES-128-CBC'})
        result = self.run_helper(self.standby, 'import', different_cipher)
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual((self.standby / '.env').read_bytes(), original)
        self.assert_no_secrets(result)

    def test_verification_detects_environment_override_after_import(self):
        payload = self.exported()
        result = self.run_helper(self.standby, 'import', payload)
        self.assertEqual(result.returncode, 0, result.stderr)
        verify = self.run_helper(self.standby, 'verify', payload, APP_KEY=KEY_B)
        self.assertNotEqual(verify.returncode, 0)
        self.assertIn('not using the primary application key', verify.stderr)
        self.assert_no_secrets(verify)

    def test_invalid_dotenv_errors_do_not_expose_the_bad_line(self):
        (self.standby / '.env').write_text(f'APP_KEY={KEY_B}\nPASSWORD=secret with spaces\n')
        self.cache_key(self.standby, KEY_B)
        result = self.run_helper(self.standby, 'import', self.exported())
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn('secret with spaces', result.stdout + result.stderr)

    def run_shell_copy(self, answer='y', invalid_primary=False, fail_remote='', supervisor_exit='0'):
        source = (REPO / 'install/setup_logical_replication.sh').read_text()
        helpers = source[source.index('application_key_tool() {'):
                         source.index('copy_schema_to_remote() {')]
        if invalid_primary:
            (self.primary / '.env').write_text('APP_KEY=invalid\n')
        # SSH is replaced by a local shell: the real quoting and Artisan command run,
        # but all files belong to the temporary standby and no SQL is executed.
        script = r'''
set -euo pipefail
LOCAL_IP=192.0.2.1
REMOTE_IP=192.0.2.2
REMOTE_PASS=unused
REMOTE_PG_PORT=5432
REPL_USER=fusionpbx
DB_NAME=fusionpbx
print_info() { echo "$*"; }
print_success() { echo "$*"; }
print_warn() { echo "$*"; }
print_error() { echo "$*"; }
SSH_STDIN() {
    printf 'helper\n' >> "$FIXTURE/actions"
    local command="$2"
    if [[ -n "$FAIL_REMOTE" && "$command" == *"'$FAIL_REMOTE'"* ]]; then
        return 17
    fi
    command="${command//$PRIMARY/$STANDBY}"
    bash -c "$command"
}
SSH() {
    if [[ "$2" == "supervisorctl restart all" ]]; then
        printf 'supervisor-restart\n' >> "$FIXTURE/actions"
        printf '%s\n' "$1" "$2" > "$FIXTURE/supervisor-command"
        return "$SUPERVISOR_EXIT"
    fi
    printf 'schema-reset\n' >> "$FIXTURE/actions"
}
''' + helpers + '\ndrop_and_create_schema_remote\n'
        return subprocess.run(['bash', '-c', script], input=answer + '\n',
                              text=True, capture_output=True,
                              env=self.env | {'APP_DIR': str(self.primary),
                                              'PRIMARY': str(self.primary),
                                              'STANDBY': str(self.standby),
                                              'FAIL_REMOTE': fail_remote,
                                              'SUPERVISOR_EXIT': supervisor_exit,
                                              'FIXTURE': str(self.root)})

    def test_shell_transfer_verifies_key_and_restarts_supervisor_services_before_schema_reset(self):
        result = self.run_shell_copy()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertEqual((self.root / 'actions').read_text(),
                         'helper\nhelper\nsupervisor-restart\nschema-reset\n')
        self.assertEqual((self.root / 'supervisor-command').read_text().splitlines(), [
            'root@192.0.2.2',
            'supervisorctl restart all',
        ])
        self.assertEqual(self.run_helper(self.standby, 'verify', self.exported()).returncode, 0)
        self.assert_no_secrets(result)

    def test_declining_confirmation_changes_nothing(self):
        original = (self.standby / '.env').read_bytes()
        result = self.run_shell_copy(answer='n')
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse((self.root / 'actions').exists())
        self.assertEqual((self.standby / '.env').read_bytes(), original)

    def test_key_failure_stops_before_schema_reset(self):
        original = (self.standby / '.env').read_bytes()
        result = self.run_shell_copy(invalid_primary=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse((self.root / 'actions').exists())
        self.assertEqual((self.standby / '.env').read_bytes(), original)

    def test_remote_import_failure_stops_before_schema_reset(self):
        original = (self.standby / '.env').read_bytes()
        result = self.run_shell_copy(fail_remote='import')
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn('schema-reset', (self.root / 'actions').read_text())
        self.assertFalse((self.root / 'supervisor-command').exists())
        self.assertEqual((self.standby / '.env').read_bytes(), original)
        self.assert_no_secrets(result)

    def test_remote_verification_failure_stops_before_schema_reset(self):
        result = self.run_shell_copy(fail_remote='verify')
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn('schema-reset', (self.root / 'actions').read_text())
        self.assertFalse((self.root / 'supervisor-command').exists())
        self.assertEqual(len(list(self.standby.glob('.env.ha-backup-*'))), 1)
        self.assert_no_secrets(result)

    def test_supervisor_failure_stops_before_schema_reset(self):
        result = self.run_shell_copy(supervisor_exit='1')
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual((self.root / 'actions').read_text(), 'helper\nhelper\nsupervisor-restart\n')
        self.assertIn('Setup stopped before the remote schema reset', result.stdout)
        self.assertEqual(self.run_helper(self.standby, 'verify', self.exported()).returncode, 0)
        self.assert_no_secrets(result)


if __name__ == '__main__':
    unittest.main()
