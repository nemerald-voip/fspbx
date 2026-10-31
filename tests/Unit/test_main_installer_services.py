"""Run only the installer's final stage with isolated files and stubbed services."""

import os
from pathlib import Path
import subprocess
import tempfile
import unittest


REPO = Path(__file__).resolve().parents[2]


class MainInstallerServiceTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='fspbx-service-setup-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.app = self.root / 'app'
        self.config = self.root / 'supervisor'
        (self.app / 'install').mkdir(parents=True)
        self.config.mkdir()
        for name in ['horizon', 'fs-cdr-service', 'fs-esl-listener-emergency',
                     'fs-esl-listener-call-webhooks', 'reverb']:
            filename = name + '.conf'
            (self.app / 'install' / filename).write_bytes((REPO / 'install' / filename).read_bytes())

    def run_final_stage(self, **env):
        source = (REPO / 'install/install.sh').read_text()
        helpers = source[:source.index('# Detect OS codename')]
        final = source[source.index('# Copy Horizon configuration to Supervisor'):]
        script = (helpers + r'''
cd "$FIXTURE/app" || exit 98
sudo() {
    if [[ "$1" != cp ]]; then
        echo "Unexpected early service action: $*" >&2
        return 91
    fi
    [[ "${FAIL_COPY:-0}" == 0 ]] || return 14
    "$@"
}
php() {
    case "$*" in
        'artisan vendor:publish --provider=Laravel\Horizon\HorizonServiceProvider') return 0 ;;
        'artisan fspbx:initial-seed')
            printf 'initial-seed\n' >> "$FIXTURE/commands"
            return "${SEED_EXIT:-0}"
            ;;
        *) echo "Unexpected PHP invocation: $*" >&2; return 92 ;;
    esac
}
supervisorctl() { echo 'Supervisor activated before final setup' >&2; return 93; }
systemctl() { echo 'Services activated before final setup' >&2; return 94; }
sleep() { return 0; }
''' + final)
        script = script.replace('/var/www/fspbx', str(self.app))
        script = script.replace('/etc/supervisor/conf.d', str(self.config))
        return subprocess.run(['bash', '-c', script], capture_output=True, text=True,
                              env=os.environ | {'FIXTURE': str(self.root)} | env)

    def test_new_install_loads_templates_and_reaches_final_setup_without_starting_workers(self):
        result = self.run_final_stage()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertEqual((self.root / 'commands').read_text(), 'initial-seed\n')
        self.assertIn('All installation tasks completed successfully!', result.stdout)
        for path in (self.app / 'install').iterdir():
            self.assertEqual((self.config / path.name).read_bytes(), path.read_bytes())
        self.assertIn('numprocs=0', (self.config / 'fs-esl-listener-call-webhooks.conf').read_text())
        self.assertIn('autostart=true', (self.config / 'fs-esl-listener-call-webhooks.conf').read_text())

    def test_retry_replaces_existing_configurations_with_shipped_templates(self):
        for path in (self.app / 'install').iterdir():
            (self.config / path.name).write_text(
                f'[program:{path.stem}]\nnumprocs=1\nautostart=false\n; old configuration\n')
        for attempt in range(2):
            with self.subTest(attempt=attempt):
                result = self.run_final_stage()
                self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
                for path in (self.app / 'install').iterdir():
                    self.assertEqual((self.config / path.name).read_bytes(), path.read_bytes())

    def test_failed_initial_setup_does_not_report_installation_success(self):
        result = self.run_final_stage(SEED_EXIT='17')
        self.assertEqual(result.returncode, 1)
        self.assertIn('Error occurred during database seeding', result.stdout)
        self.assertNotIn('All installation tasks completed successfully!', result.stdout)

    def test_configuration_copy_failure_stops_before_final_setup(self):
        result = self.run_final_stage(FAIL_COPY='1')
        self.assertEqual(result.returncode, 1)
        self.assertFalse((self.root / 'commands').exists())
        self.assertIn('Unable to install Supervisor configuration', result.stdout)


if __name__ == '__main__':
    unittest.main()
