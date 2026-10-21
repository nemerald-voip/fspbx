"""Exercise the installer with fake packages/services; never run real apt."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest


REPO = Path(__file__).resolve().parents[2]


class PhpPackageUpgradeTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='fspbx-php-upgrade-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.bin = self.root / 'bin'
        self.bin.mkdir()
        source = (REPO / 'install/install_php.sh').read_text()
        # Override only the fixture OS and privilege check. Run the real mode
        # dispatcher and upgrade function with every mutating command stubbed.
        source = source.replace('. /etc/os-release', '. "$PHP_TEST_OS_RELEASE"')
        source = source.replace('# ---------------- MAIN ----------------',
                                'require_root() { :; }\n# ---------------- MAIN ----------------')
        self.script = self.root / 'install_php.sh'
        self.script.write_text(source)
        self.os_release = self.root / 'os-release'
        self.os_release.write_text('ID=debian\nVERSION_ID=12\n')
        self.state = {
            'installed': {name: '8.4.17-1' for name in
                          ('php8.4-cli', 'php8.4-fpm', 'php8.4-common', 'php8.4-opcache', 'php8.4-pgsql')},
            'candidate': '8.4.27-1+0~20261021.57+debian12~1.gbp7636b9',
        }
        self.state['installed']['php8.4-redis'] = '6.3.0-1'
        stub = self.bin / 'stub'
        stub.write_text('''#!/usr/bin/python3
import json, os, pathlib, subprocess, sys
root = pathlib.Path(os.environ['PHP_TEST_ROOT'])
state = json.loads((root / 'state.json').read_text())
name = pathlib.Path(sys.argv[0]).name
args = sys.argv[1:]
with (root / 'commands.jsonl').open('a') as log:
    log.write(json.dumps([name, *args]) + '\\n')
if name == 'dpkg-query':
    package = args[-1]
    if package == 'php8.4*':
        for package, version in state['installed'].items():
            print(f'installed\\t{package}\\t{version}')
        print('config-files\\tphp8.4-xdebug\\t3.4.0-1')
    elif package not in state['installed']:
        sys.exit(1)
    elif 'Status-Status' in args[1]:
        print('installed', end='')
    else:
        print(state['installed'][package], end='')
elif name == 'dpkg':
    assert args[0] == '--compare-versions'
    sys.exit(subprocess.run(['/usr/bin/dpkg', *args]).returncode)
elif name == 'apt-cache':
    print('  Candidate: ' + state.get('candidates', {}).get(args[-1], state['candidate']))
elif name == 'apt-get':
    assert os.environ['DEBIAN_FRONTEND'] == 'noninteractive'
    assert os.environ['NEEDRESTART_MODE'] == 'l'
    if 'update' in args:
        sys.exit(state.get('refresh_error', 0))
    assert 'install' in args, args
    if state.get('install_error'):
        sys.exit(state['install_error'])
    if not state.get('no_upgrade'):
        for package in state['installed']:
            if package != 'php8.4-redis':
                state['installed'][package] = state['candidate']
        (root / 'state.json').write_text(json.dumps(state))
elif name == 'php8.4':
    print(state.get('cli_version', state['installed']['php8.4-cli'].split('-')[0]), end='')
elif name == 'php-fpm8.4':
    if '-v' in args:
        print('PHP ' + state.get('fpm_version', state['installed']['php8.4-fpm'].split('-')[0]) + ' (fpm-fcgi)')
    else:
        assert args == ['-t'], args
        sys.exit(state.get('config_error', 0))
elif name == 'systemctl':
    assert args in (['restart', 'php8.4-fpm'], ['is-active', '--quiet', 'php8.4-fpm']), args
    sys.exit(state.get('restart_error' if args[0] == 'restart' else 'active_error', 0))
else:
    sys.exit('Unexpected command: ' + name + ' ' + repr(args))
''')
        stub.chmod(0o755)
        for command in ('apt-get', 'apt-cache', 'dpkg-query', 'dpkg', 'systemctl', 'service',
                        'php8.4', 'php-fpm8.4', 'curl', 'wget', 'gpg', 'mkdir', 'mv',
                        'ln', 'chmod', 'update-alternatives'):
            (self.bin / command).symlink_to(stub)

    def run_upgrade(self, expected=0, argument='--upgrade-only'):
        (self.root / 'state.json').write_text(json.dumps(self.state))
        log = self.root / 'commands.jsonl'
        log.write_text('')
        result = subprocess.run(
            ['bash', str(self.script), argument],
            env={**os.environ, 'PATH': f'{self.bin}:/usr/bin:/bin',
                 'PHP_TEST_ROOT': str(self.root), 'PHP_TEST_OS_RELEASE': str(self.os_release)},
            text=True, capture_output=True, timeout=10,
        )
        self.assertEqual(result.returncode, expected, result.stdout + result.stderr)
        self.commands = [json.loads(line) for line in log.read_text().splitlines()]
        return result.stdout + result.stderr

    def test_upgrades_debian_11_12_and_13_using_existing_packages(self):
        for release, version in ((11, '8.4.27-1+0~20261021.57+debian11~1.gbp7636b9'),
                                 (12, '8.4.27-1+0~20261021.57+debian12~1.gbp7636b9'),
                                 (13, '8.4.27-1~deb13u1')):
            with self.subTest(release=release):
                self.os_release.write_text(f'ID=debian\nVERSION_ID={release}\n')
                self.state['candidate'] = version
                output = self.run_upgrade()
                self.assertIn('PHP security update verified', output)
                installs = [cmd for cmd in self.commands if cmd[0] == 'apt-get' and 'install' in cmd]
                self.assertEqual(len(installs), 1)
                command = installs[0]
                self.assertIn('--only-upgrade', command)
                self.assertIn('--no-remove', command)
                self.assertIn('Dpkg::Options::=--force-confold', command)
                self.assertEqual({arg for arg in command if arg.startswith('php')},
                                 set(self.state['installed']))
                self.assertIn(['systemctl', 'restart', 'php8.4-fpm'], self.commands)
                self.assertIn(['systemctl', 'is-active', '--quiet', 'php8.4-fpm'], self.commands)

    def test_old_or_missing_candidates_do_not_install_or_restart(self):
        for version in ('8.4.9-1', '8.4.26-1', '8.4.27~rc1-1', '(none)'):
            with self.subTest(version=version):
                self.state['candidate'] = version
                self.assertIn('does not provide PHP 8.4.27', self.run_upgrade(1))
                self.assertFalse(any('install' in cmd or cmd[0] == 'systemctl' for cmd in self.commands))

    def test_fpm_candidate_is_checked_independently(self):
        self.state['candidates'] = {'php8.4-fpm': '8.4.26-1'}
        self.assertIn('php8.4-fpm: candidate 8.4.26-1', self.run_upgrade(1))
        self.assertFalse(any('install' in cmd for cmd in self.commands))

    def test_unavailable_php_on_debian_11_fails_before_apt(self):
        self.os_release.write_text('ID=debian\nVERSION_ID=11\n')
        self.state['installed'] = {}
        self.assertIn('existing PHP 8.4 installation', self.run_upgrade(1))
        self.assertFalse(any(cmd[0] == 'apt-get' for cmd in self.commands))

    def test_unsupported_os_and_unknown_options_do_not_run_commands(self):
        self.os_release.write_text('ID=ubuntu\nVERSION_ID=24.04\n')
        self.run_upgrade(1)
        self.assertEqual(self.commands, [])
        self.run_upgrade(1, '--unknown')
        self.assertEqual(self.commands, [])

    def test_unrelated_repository_refresh_failure_does_not_block_a_patched_php_candidate(self):
        self.state['refresh_error'] = 100
        output = self.run_upgrade()
        self.assertIn('Some package lists could not be refreshed', output)
        self.assertIn('PHP security update verified', output)
        self.assertIn(['systemctl', 'restart', 'php8.4-fpm'], self.commands)
        for command in self.commands:
            if command[0] == 'apt-get':
                self.assertNotIn('--allow-unauthenticated', command)
                self.assertFalse(any('AllowInsecure' in arg or 'AllowUnauthenticated' in arg
                                     for arg in command))

    def test_failed_refresh_with_an_old_or_missing_php_candidate_still_stops_patching(self):
        self.state['refresh_error'] = 100
        for version in ('8.4.26-1', '(none)'):
            with self.subTest(version=version):
                self.state['candidate'] = version
                output = self.run_upgrade(1)
                self.assertIn('does not provide PHP 8.4.27', output)
                self.assertNotIn('PHP security update verified', output)
                self.assertFalse(any('install' in cmd or cmd[0] == 'systemctl' for cmd in self.commands))

    def test_install_or_authentication_failure_is_not_reported_as_success(self):
        self.state['install_error'] = 100
        for refresh_error in (0, 100):
            with self.subTest(refresh_error=refresh_error):
                self.state['refresh_error'] = refresh_error
                self.assertNotIn('PHP security update verified', self.run_upgrade(100))
                self.assertFalse(any(cmd[0] == 'systemctl' for cmd in self.commands))

    def test_successful_apt_exit_with_unpatched_packages_is_rejected(self):
        self.state['no_upgrade'] = True
        self.assertIn('is still 8.4.17-1', self.run_upgrade(1))
        self.assertFalse(any(cmd[0] == 'systemctl' for cmd in self.commands))

    def test_old_runtime_binaries_are_rejected(self):
        for binary in ('cli_version', 'fpm_version'):
            with self.subTest(binary=binary):
                self.state[binary] = '8.4.17'
                self.assertIn('CLI/FPM binaries', self.run_upgrade(1))
                self.assertFalse(any(cmd[0] == 'systemctl' for cmd in self.commands))
                del self.state[binary]

    def test_config_restart_and_inactive_service_errors_are_not_success(self):
        for failure in ('config_error', 'restart_error', 'active_error'):
            with self.subTest(failure=failure):
                self.state[failure] = 1
                self.assertNotIn('PHP security update verified', self.run_upgrade(1))
                del self.state[failure]

    def test_already_patched_and_newer_packages_are_safe_to_rerun(self):
        for version in ('8.4.27-1~deb13u1', '8.4.28-1~deb13u1'):
            with self.subTest(version=version):
                self.state['candidate'] = version
                for package in self.state['installed']:
                    if package != 'php8.4-redis':
                        self.state['installed'][package] = version
                self.assertIn('PHP security update verified', self.run_upgrade())


if __name__ == '__main__':
    unittest.main()
