"""Isolated checks: never run apt or touch live configuration or services."""
import importlib.util
import json
import os
from pathlib import Path
import shlex
import shutil
import subprocess
import tempfile
import unittest

REPO = Path(__file__).resolve().parents[2]
TOOL = REPO / 'install/freeswitch_config.py'
spec = importlib.util.spec_from_file_location('fs_config', TOOL)
config = importlib.util.module_from_spec(spec)
spec.loader.exec_module(config)


class ConfigurationTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='fspbx-installer-test-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.conf = self.root / 'conf'
        (self.conf / 'autoload_configs').mkdir(parents=True)
        self.modules = self.conf / 'autoload_configs/modules.conf.xml'
        self.modules.write_bytes(b'<configuration>\r\n<modules><load module="mod_commands"/>\r\n<load module="mod_h26x"/></modules></configuration>\r\n')
        (self.conf / 'freeswitch.xml').write_text('<document/>')
        (self.conf / 'vars.xml').write_text('private variables')

    def snapshot(self):
        backup = self.root / 'backup'
        subprocess.run(['cp', '-a', str(self.conf), str(backup)], check=True)
        return backup

    def test_recovery_preserves_storage_tls_metadata_and_links(self):
        for storage in ('/dev/shm/core.db', 'pgsql://private-credentials'):
            with self.subTest(storage=storage):
                runtime = self.conf / 'autoload_configs/switch.conf.xml'
                runtime.write_text(storage)
                tls = self.conf / 'tls'
                tls.mkdir(exist_ok=True)
                pem = tls / 'agent.pem'
                pem.write_text('private key')
                pem.chmod(0o600)
                os.setxattr(tls, 'user.original', b'original')
                external = self.root / 'external.pem'
                external.write_text('external key unchanged')
                link = tls / 'link.pem'
                link.symlink_to(external)
                backup = self.snapshot()
                runtime.write_text('overwritten settings')
                pem.chmod(0o644)
                pem.write_text('overwritten certificate')
                link.unlink()
                link.write_text('overwritten link')
                (self.conf / 'unexpected.xml').write_text('new default')
                os.setxattr(tls, 'user.added', b'unwanted')
                config.restore(backup, self.conf)
                self.assertEqual(config.inventory(backup), config.inventory(self.conf))
                self.assertEqual(external.read_text(), 'external key unchanged')
                config.restore(backup, self.conf)
                shutil.rmtree(backup)
                link.unlink()

    def test_recovery_does_not_follow_replaced_directory_or_root_links(self):
        backup = self.snapshot()
        outside = self.root / 'outside'
        outside.mkdir()
        sentinel = outside / 'vars.xml'
        sentinel.write_text('keep')
        shutil.rmtree(self.conf)
        self.conf.symlink_to(outside)
        config.restore(backup, self.conf)
        self.assertFalse(self.conf.is_symlink())
        self.assertEqual(sentinel.read_text(), 'keep')
        shutil.rmtree(self.conf / 'autoload_configs')
        (self.conf / 'autoload_configs').symlink_to(outside)
        config.restore(backup, self.conf)
        self.assertEqual(sentinel.read_text(), 'keep')
        self.assertEqual(config.inventory(backup), config.inventory(self.conf))

    def test_entire_configuration_tree_is_restored_including_unknown_files(self):
        originals = {
            'sip_profiles/carrier/custom-gateway.xml': b'<gateway name="customer-specific"/>',
            'dialplan/custom/routing.xml': b'<extension name="custom-route"/>',
            '.provider/credentials': b'private provider credentials',
            'custom/plugin.conf': b'non-XML data\x00\xff',
        }
        for name, contents in originals.items():
            path = self.conf / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(contents)
            path.chmod(0o600)
        backup = self.snapshot()
        (self.conf / 'sip_profiles/carrier/custom-gateway.xml').write_text('installer default')
        (self.conf / 'dialplan/custom/routing.xml').unlink()
        (self.conf / '.provider/credentials').chmod(0o644)
        shutil.rmtree(self.conf / 'custom')
        (self.conf / 'custom').write_text('installer replaced a directory')
        (self.conf / 'new-installer-directory').mkdir()
        (self.conf / 'new-installer-directory/default.xml').write_text('remove this')
        # Exercise the installer wrapper while vars.xml remains untouched.
        self.run_shell('''
FS_BACKUP_DIR="$FIXTURE"
mv "$FIXTURE/backup" "$FIXTURE/config"
FS_CONF_DIR="$FIXTURE/conf"
FS_SERVICE_PATH="$FIXTURE/unit"
FS_CONFIG_PROTECTED=true
FS_RESTORE_ARMED=true
fs_restore_configuration
''')
        self.assertEqual(config.inventory(self.root / 'config'), config.inventory(self.conf))
        for name, contents in originals.items():
            self.assertEqual((self.conf / name).read_bytes(), contents)
        self.assertFalse((self.conf / 'new-installer-directory').exists())

    def test_broadvoice_is_excluded_from_build_and_removed_without_changing_other_modules(self):
        self.modules.write_text('<modules><load module="mod_commands"/><load module="mod_bv"/></modules>')
        source = self.root / 'source'
        for relative in ('applications/mod_commands', 'codecs/mod_bv'):
            directory = source / 'src/mod' / relative
            directory.mkdir(parents=True)
            (directory / 'Makefile.am').touch()
        baseline, output = self.root / 'baseline', self.root / 'selected'
        baseline.write_text('applications/mod_commands\ncodecs/mod_bv\n')
        config.select_modules(source, self.conf, baseline, output)
        self.assertEqual(output.read_text(), 'applications/mod_commands\n')
        config.prune_retired(self.conf)
        self.assertEqual(config.loaded_modules(self.conf), {'mod_commands'})
        modules = self.root / 'modules'
        modules.mkdir()
        (modules / 'mod_bv.so').touch()
        (modules / 'mod_commands.so').touch()
        config.retire_binaries(modules)
        self.assertFalse((modules / 'mod_bv.so').exists())
        self.assertTrue((modules / 'mod_commands.so').exists())

    def test_native_defaults_cover_exactly_the_fresh_installer_modules(self):
        baseline = {
            Path(line.strip()).name
            for line in (REPO / 'install/freeswitch-modules.conf').read_text().splitlines()
            if line.strip() and not line.lstrip().startswith('#')
        }
        autoload = config.loaded_modules(REPO / 'resources')
        self.assertEqual({'mod_say_en'}, {name for name in autoload if name.startswith('mod_say_')})
        selected = (baseline | autoload) - config.EXCLUDED_MODULES
        defaults = json.loads((REPO / 'resources/freeswitch_modules.json').read_text())
        self.assertEqual(set(defaults), selected)

    def test_english_only_upgrades_still_build_available_target_speech_modules(self):
        source = self.root / 'source'
        baseline = REPO / 'install/freeswitch-modules.conf'
        entries = [line.strip() for line in baseline.read_text().splitlines() if line.strip()]
        self.assertEqual(len(entries), len(set(entries)))
        for module in entries:
            directory = source / 'src/mod' / module
            directory.mkdir(parents=True)
            (directory / 'Makefile.am').touch()
        self.modules.write_text('<modules><load module="mod_say_en"/></modules>')
        output = self.root / 'modules.conf'
        config.select_modules(source, self.conf, baseline, output)
        expected = {'de', 'en', 'es', 'fr', 'he', 'hu', 'it', 'ja', 'nl', 'pl', 'pt', 'ru', 'sv', 'zh'}
        # Retain the previously shipped Thai module even though it no longer autoloads.
        expected.add('th')
        selected = {line.removeprefix('say/mod_say_') for line in output.read_text().splitlines() if line.startswith('say/mod_say_')}
        self.assertEqual(expected, selected)

    def test_removal_is_silent_preserves_comments_and_other_xml_bytes(self):
        before = self.modules.read_bytes() + b'<!-- <load module="mod_h26x"/> -->\r\n'
        self.modules.write_bytes(before)
        result = subprocess.run(['python3', str(TOOL), 'prune-retired', str(self.conf)], capture_output=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(result.stdout + result.stderr, b'')
        self.assertEqual(self.modules.read_bytes(), before.replace(b'<load module="mod_h26x"/>', b'', 1))
        config.prune_retired(self.conf)
        self.assertEqual(config.loaded_modules(self.conf), {'mod_commands'})

    def test_included_modules_and_missing_custom_module(self):
        includes = self.conf / 'autoload_configs/custom.xml'
        includes.write_text('<modules><load module="mod_pgsql"/><load module="mod_h26x"></load></modules>')
        self.modules.write_text('<modules><load module="mod_commands"/><X-PRE-PROCESS cmd="include" data="custom.xml"/></modules>')
        source = self.root / 'source'
        for relative in ('applications/mod_commands', 'databases/mod_pgsql'):
            path = source / 'src/mod' / relative
            path.mkdir(parents=True)
            (path / 'Makefile.am').touch()
        baseline, output = self.root / 'baseline', self.root / 'selected'
        baseline.write_text('applications/mod_commands\ncodecs/mod_h26x\n')
        config.select_modules(source, self.conf, baseline, output)
        self.assertEqual(output.read_text().splitlines(), ['applications/mod_commands', 'databases/mod_pgsql'])
        config.prune_retired(self.conf)
        self.assertNotIn('mod_h26x', includes.read_text())
        includes.write_text('<modules><load module="mod_custom"/></modules>')
        with self.assertRaisesRegex(ValueError, 'mod_custom'):
            config.select_modules(source, self.conf, baseline, output)

    def test_external_includes_are_not_modified(self):
        outside = self.root / 'external.xml'
        outside.write_text('<modules><load module="mod_h26x"/></modules>')
        self.modules.write_text(f'<modules><X-PRE-PROCESS cmd="include" data="{outside}"/></modules>')
        with self.assertRaisesRegex(ValueError, 'outside'):
            config.prune_retired(self.conf)
        self.assertIn('mod_h26x', outside.read_text())

    def test_package_plan_allows_retired_modules_but_rejects_unrelated_removals(self):
        plan = self.root / 'apt-plan'
        plan.write_text('Remv freeswitch-mod-h26x [1.10.12]\nRemv freeswitch-mod-bv [1.10.12]\nInst freeswitch [1.10.12] (1.11.3)\n')
        config.check_package_plan(plan)
        plan.write_text('Remv postgresql-15 [15.0]\n')
        with self.assertRaisesRegex(ValueError, 'unrelated packages: postgresql-15'):
            config.check_package_plan(plan)

    def run_shell(self, body, expected=0, extra_env=None):
        env = os.environ | {'FIXTURE': str(self.root), 'REPO': str(REPO)} | (extra_env or {})
        result = subprocess.run(['bash', '-c', 'set -Eeuo pipefail\nsource "$REPO/install/freeswitch-common.sh"\n' + body], env=env, capture_output=True, text=True)
        self.assertEqual(result.returncode, expected, result.stdout + result.stderr)
        return result

    def test_service_guard_restores_relative_symlink_on_failure(self):
        original = self.root / 'original-policy'
        original.write_text('#!/bin/sh\nexit 42\n')
        original.chmod(0o755)
        policy = self.root / 'policy-rc.d'
        policy.symlink_to('original-policy')
        self.run_shell('''
FS_BACKUP_DIR="$FIXTURE/metadata"
FS_POLICY_PATH="$FIXTURE/policy-rc.d"
mkdir "$FS_BACKUP_DIR"
trap fs_cleanup EXIT
fs_protect_services
status=0; "$FS_POLICY_PATH" freeswitch restart || status=$?
[[ $status == 101 ]]
status=0; "$FS_POLICY_PATH" unrelated restart || status=$?
[[ $status == 42 ]]
exit 17
''', 17)
        self.assertTrue(policy.is_symlink())
        self.assertEqual(os.readlink(policy), 'original-policy')

    def test_install_failure_restores_configuration_and_service_unit(self):
        self.snapshot()
        self.run_shell('''
FS_BACKUP_DIR="$FIXTURE"
mv "$FIXTURE/backup" "$FIXTURE/config"
FS_CONF_DIR="$FIXTURE/conf"
FS_SERVICE_PATH="$FIXTURE/installed-unit"
printf original > "$FIXTURE/freeswitch.service"
printf changed > "$FS_SERVICE_PATH"
FS_CONFIG_PROTECTED=true
fs_begin_install
trap fs_cleanup EXIT
printf overwritten > "$FS_CONF_DIR/vars.xml"
exit 19
''', 19)
        self.assertEqual((self.conf / 'vars.xml').read_text(), 'private variables')
        self.assertEqual((self.root / 'installed-unit').read_text(), 'original')

    def test_fresh_defaults_exclude_legacy_autoload_and_include_hidden_files(self):
        app = self.root / 'app'
        legacy = app / 'public/app/switch/resources/conf'
        (legacy / 'autoload_configs').mkdir(parents=True)
        (legacy / 'freeswitch.xml').write_text('<document/>')
        (legacy / '.keep').write_text('hidden')
        (legacy / 'autoload_configs/obsolete.xml').write_text('obsolete')
        canonical = app / 'resources/autoload_configs'
        canonical.mkdir(parents=True)
        (canonical / 'modules.conf.xml').write_text('<modules><load module="mod_commands"/></modules>')
        self.run_shell('''
FS_APP_DIR="$FIXTURE/app"
FS_CONF_DIR="$FIXTURE/fresh"
FRESH_INSTALL=true
# The sandbox cannot assign host www-data ownership; verify the request here.
chown() { [[ "$*" == "-R www-data:www-data $FS_CONF_DIR" ]]; }
fs_seed_configuration
''')
        self.assertTrue((self.root / 'fresh/.keep').is_file())
        self.assertFalse((self.root / 'fresh/autoload_configs/obsolete.xml').exists())
        self.assertEqual(config.loaded_modules(self.root / 'fresh'), {'mod_commands'})

    def test_fresh_install_rejects_existing_configuration_before_mutations(self):
        self.run_shell('''
FS_CONF_DIR="$FIXTURE/conf"
FS_BACKUP_ROOT="$FIXTURE/backups"
FS_LOCK_PATH="$FIXTURE/lock"
FS_SERVICE_PATH="$FIXTURE/unit"
FRESH_INSTALL=true
fs_preflight
''', 1)
        self.assertFalse((self.root / 'backups').exists())

    def existing_source_installation(self):
        self.modules.write_text('<modules><load module="mod_commands"/></modules>')
        modules = self.root / 'modules'
        modules.mkdir()
        (modules / 'mod_commands.so').write_bytes(b'installed module')
        binary = self.root / 'bin'
        binary.mkdir()
        server = binary / 'freeswitch'
        server.write_text('#!/bin/sh\n[ "$1" = -version ] || exit 1\nprintf "FreeSWITCH version: 1.11.3-release+git~20261016T051104Z~7149bd22fd~64bit\\n"\n')
        server.chmod(0o755)
        client = binary / 'fs_cli'
        client.write_text('#!/bin/sh\nexit 0\n')
        client.chmod(0o755)
        (self.root / 'unit').write_text('[Service]\nExecStart=/usr/bin/freeswitch -ncwait\n')
        # A skip must not query or start services, install packages, or build.
        for command in ('systemctl', 'apt-get', 'make'):
            stub = binary / command
            stub.write_text('#!/bin/sh\nprintf "Unexpected installer mutation\\n" >&2\nexit 99\n')
            stub.chmod(0o755)
        return os.environ | {
            'PATH': str(binary) + os.pathsep + os.environ['PATH'],
            'FREESWITCH_CONF_DIR': str(self.conf),
            'FREESWITCH_MOD_DIR': str(modules),
            'FS_BINARY_PATH': str(server),
            'FS_SERVICE_PATH': str(self.root / 'unit'),
            'FS_POLICY_PATH': str(self.root / 'policy'),
            'FS_LOCK_PATH': str(self.root / 'lock'),
            'FREESWITCH_BACKUP_ROOT': str(self.root / 'backups'),
            'BUILD_ROOT': str(self.root / 'builds'),
        }

    def test_main_install_skips_verified_installation_without_mutations(self):
        env = self.existing_source_installation()
        before = config.inventory(self.conf)
        result = subprocess.run(['bash', str(REPO / 'install/install_freeswitch.sh'), '--fspbx-install'],
                                env=env, capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn('already installed and verified. Skipping build.', result.stdout)
        self.assertEqual(before, config.inventory(self.conf))
        for name in ('backups', 'builds', 'policy'):
            self.assertFalse((self.root / name).exists(), name)

    def test_main_installer_freeswitch_step_opts_into_skip_behavior(self):
        env = self.existing_source_installation()
        installer = (REPO / 'install/install.sh').read_text()
        start = installer.index('print_success "Installing FreeSWITCH..."')
        end = installer.index('print_success "Installing FreeSWITCH Sounds..."', start)
        step = installer[start:end].replace('/var/www/fspbx/install/install_freeswitch.sh',
                                           shlex.quote(str(REPO / 'install/install_freeswitch.sh')))
        result = subprocess.run(['bash', '-c', 'print_success() { echo "$1"; }; print_error() { echo "$1" >&2; };\n' + step],
                                env=env, capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn('already installed and verified. Skipping build.', result.stdout)
        self.assertIn('FreeSWITCH installation step completed successfully.', result.stdout)
        self.assertFalse((self.root / 'backups').exists())

    def test_standalone_source_installer_still_rebuilds_a_complete_installation(self):
        env = self.existing_source_installation()
        before = config.inventory(self.conf)
        unit = (self.root / 'unit').read_bytes()
        result = subprocess.run(['bash', str(REPO / 'install/install_freeswitch.sh')],
                                env=env, capture_output=True, text=True)
        # The isolated apt-get stub stops us at the normal rebuild entry.
        self.assertEqual(result.returncode, 99, result.stdout + result.stderr)
        self.assertIn('Building FreeSWITCH', result.stdout)
        self.assertNotIn('Skipping build.', result.stdout)
        self.assertNotIn('Reinstalling with fresh configuration.', result.stdout)
        self.assertEqual(before, config.inventory(self.conf))
        self.assertEqual((self.root / 'unit').read_bytes(), unit)
        backups = list((self.root / 'backups').iterdir())
        self.assertEqual(len(backups), 1)
        self.assertEqual(before, config.inventory(backups[0] / 'config'))
        self.assertFalse((self.root / 'policy').exists())

    def test_standalone_fresh_install_keeps_its_existing_configuration_guard(self):
        env = self.existing_source_installation()
        for variables in ('<include/>', ''):
            with self.subTest(complete=bool(variables)):
                (self.conf / 'vars.xml').write_text(variables)
                before = config.inventory(self.conf)
                result = subprocess.run(['bash', str(REPO / 'install/install_freeswitch.sh'), '--fresh-install'],
                                        env=env, capture_output=True, text=True)
                self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
                self.assertIn('Existing FreeSWITCH configuration found. Run without --fresh-install.', result.stderr)
                self.assertEqual(before, config.inventory(self.conf))
                for name in ('backups', 'builds', 'policy'):
                    self.assertFalse((self.root / name).exists(), name)

    def test_main_install_rebuilds_partial_or_broken_installation(self):
        env = self.existing_source_installation()
        failures = {
            'missing configuration': (self.conf / 'freeswitch.xml', None),
            'empty variables': (self.conf / 'vars.xml', b''),
            'missing module configuration': (self.modules, None),
            'invalid module configuration': (self.modules, b'<modules>'),
            'missing enabled module': (self.root / 'modules/mod_commands.so', None),
            'missing service': (self.root / 'unit', None),
            'missing executable': (self.root / 'bin/freeswitch', None),
            'broken runtime libraries': (self.root / 'bin/freeswitch', b'#!/bin/sh\nexit 127\n'),
            'invalid version output': (self.root / 'bin/freeswitch', b'#!/bin/sh\nexit 0\n'),
            'missing client': (self.root / 'bin/fs_cli', None),
        }
        for label, (path, content) in failures.items():
            with self.subTest(label=label):
                original, mode = path.read_bytes(), path.stat().st_mode
                try:
                    if content is None:
                        path.unlink()
                    else:
                        path.write_bytes(content)
                    before = config.inventory(self.conf)
                    backups = set((self.root / 'backups').glob('*'))
                    result = subprocess.run(['bash', str(REPO / 'install/install_freeswitch.sh'), '--fspbx-install'],
                                            env=env, capture_output=True, text=True)
                    # The isolated apt-get stub stops us at the build entry.
                    self.assertEqual(result.returncode, 99, result.stdout + result.stderr)
                    self.assertIn('Reinstalling with fresh configuration.', result.stdout)
                    self.assertNotIn('Skipping build.', result.stdout)
                    self.assertEqual(before, config.inventory(self.conf))
                    created = set((self.root / 'backups').glob('*')) - backups
                    self.assertEqual(len(created), 1)
                    self.assertEqual(before, config.inventory(created.pop() / 'config'))
                    self.assertTrue((self.root / 'builds').is_dir())
                    self.assertFalse((self.root / 'policy').exists())
                finally:
                    path.write_bytes(original)
                    path.chmod(mode)

    def test_main_install_rejects_a_guard_without_a_backup_reference(self):
        env = self.existing_source_installation()
        policy = self.root / 'policy'
        contents = '#!/bin/sh\n# FS PBX FreeSWITCH install guard\nexit 101\n'
        policy.write_text(contents)
        result = subprocess.run(['bash', str(REPO / 'install/install_freeswitch.sh'), '--fspbx-install'],
                                env=env, capture_output=True, text=True)
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn('no valid backup reference', result.stderr)
        self.assertEqual(policy.read_text(), contents)
        self.assertFalse((self.root / 'builds').exists())

    def test_main_install_recovers_an_interrupted_service_guard(self):
        env = self.existing_source_installation()
        policy = self.root / 'policy'
        previous = self.root / 'backups/previous'
        previous.mkdir(parents=True)
        original = self.root / 'original-policy'
        original.write_text('#!/bin/sh\nexit 42\n')
        original.chmod(0o755)
        for kind in ('absent', 'file', 'symlink'):
            with self.subTest(original_policy=kind):
                saved = previous / 'policy-rc.d'
                if saved.exists() or saved.is_symlink():
                    saved.unlink()
                if kind == 'file':
                    saved.write_bytes(original.read_bytes())
                    saved.chmod(0o755)
                elif kind == 'symlink':
                    saved.symlink_to('original-policy')
                if policy.exists() or policy.is_symlink():
                    policy.unlink()
                policy.write_text(f'#!/bin/sh\n# FS PBX FreeSWITCH install guard. Original policy: {saved}\nexit 101\n')
                result = subprocess.run(['bash', str(REPO / 'install/install_freeswitch.sh'), '--fspbx-install'],
                                        env=env, capture_output=True, text=True)
                self.assertEqual(result.returncode, 99, result.stdout + result.stderr)
                self.assertIn('Recovered the service policy', result.stdout)
                if kind == 'absent':
                    self.assertFalse(policy.exists())
                else:
                    self.assertEqual(policy.read_bytes(), original.read_bytes())
                    self.assertEqual(policy.is_symlink(), kind == 'symlink')

    def fresh_configuration_source(self):
        app = self.root / 'app'
        legacy = app / 'public/app/switch/resources/conf'
        legacy.mkdir(parents=True)
        (legacy / 'freeswitch.xml').write_text('<document/>')
        (legacy / 'vars.xml').write_text('<include>fresh defaults</include>')
        canonical = app / 'resources/autoload_configs'
        canonical.mkdir(parents=True)
        (canonical / 'modules.conf.xml').write_text('<modules><load module="mod_commands"/></modules>')
        return app

    def test_fresh_reinstall_replaces_partial_configuration_and_keeps_new_service(self):
        env = self.existing_source_installation()
        app = self.fresh_configuration_source()
        (self.conf / 'vars.xml').write_text('')
        stale = self.conf / 'stale-gateway.xml'
        stale.write_text('old settings')
        outside = self.root / 'outside.xml'
        outside.write_text('unrelated file')
        (self.conf / 'external.xml').symlink_to(outside)
        before = config.inventory(self.conf)
        old_unit = (self.root / 'unit').read_bytes()
        self.run_shell('''
FRESH_INSTALL=true
fs_preflight --skip-if-installed
[[ "$FS_REINSTALL" == true && "$FS_ALREADY_INSTALLED" == false ]]
FS_APP_DIR="$FIXTURE/app"
fs_begin_install
printf 'fresh service' > "$FS_SERVICE_PATH"
chown() { [[ "$*" == "-R www-data:www-data $FS_BACKUP_DIR/fresh-config" ]]; }
systemctl() { [[ "$1" == daemon-reload || "$1" == list-units ]]; }
fs_finish
''', extra_env=env)
        self.assertEqual((self.conf / 'vars.xml').read_text(), '<include>fresh defaults</include>')
        self.assertFalse(stale.exists())
        self.assertFalse((self.conf / 'external.xml').exists())
        self.assertEqual(outside.read_text(), 'unrelated file')
        self.assertEqual((self.root / 'unit').read_text(), 'fresh service')
        backups = list((self.root / 'backups').iterdir())
        self.assertEqual(len(backups), 1)
        self.assertEqual(before, config.inventory(backups[0] / 'config'))
        self.assertEqual((backups[0] / 'freeswitch.service').read_bytes(), old_unit)

    def test_fresh_reinstall_failure_restores_partial_configuration_and_service(self):
        env = self.existing_source_installation()
        self.fresh_configuration_source()
        (self.conf / 'vars.xml').write_text('')
        (self.conf / 'stale.xml').write_text('preserve on failure')
        before = config.inventory(self.conf)
        old_unit = (self.root / 'unit').read_bytes()
        self.run_shell('''
FRESH_INSTALL=true
fs_preflight --skip-if-installed
FS_APP_DIR="$FIXTURE/app"
fs_begin_install
printf 'fresh service' > "$FS_SERVICE_PATH"
chown() { return 0; }
systemctl() { return 19; }
fs_finish
''', 19, extra_env=env)
        self.assertEqual(before, config.inventory(self.conf))
        self.assertEqual((self.root / 'unit').read_bytes(), old_unit)

    def test_fresh_reinstall_handles_missing_configuration_with_existing_program(self):
        env = self.existing_source_installation()
        self.fresh_configuration_source()
        shutil.rmtree(self.conf)
        self.run_shell('''
FRESH_INSTALL=true
fs_preflight --skip-if-installed
[[ "$FS_REINSTALL" == true && "$FS_ALREADY_INSTALLED" == false ]]
FS_APP_DIR="$FIXTURE/app"
fs_begin_install
printf 'fresh service' > "$FS_SERVICE_PATH"
chown() { return 0; }
systemctl() { [[ "$1" == daemon-reload || "$1" == list-units ]]; }
fs_finish
''', extra_env=env)
        self.assertEqual((self.conf / 'vars.xml').read_text(), '<include>fresh defaults</include>')
        self.assertEqual((self.root / 'unit').read_text(), 'fresh service')

    def test_repeated_upgrades_keep_configuration_and_separate_backups(self):
        for attempt in range(3):
            self.run_shell('''
FS_CONF_DIR="$FIXTURE/conf"
FS_BACKUP_ROOT="$FIXTURE/backups"
FS_LOCK_PATH="$FIXTURE/lock"
FS_SERVICE_PATH="$FIXTURE/unit"
FRESH_INSTALL=false
fs_preflight --skip-if-installed
[[ "$FS_ALREADY_INSTALLED" == false ]]
fs_begin_install
printf overwritten > "$FS_CONF_DIR/vars.xml"
''')
            self.assertEqual((self.conf / 'vars.xml').read_text(), 'private variables')
        self.assertEqual(len(list((self.root / 'backups').iterdir())), 3)

    def test_fresh_preflight_accepts_missing_and_empty_directories_before_build(self):
        for exists in (False, True):
            with self.subTest(existing_empty_directory=exists):
                fresh = self.root / 'fresh'
                if exists:
                    fresh.mkdir()
                self.run_shell('''
FS_CONF_DIR="$FIXTURE/fresh"
FS_BACKUP_ROOT="$FIXTURE/backups"
FS_LOCK_PATH="$FIXTURE/lock"
FS_SERVICE_PATH="$FIXTURE/unit"
FRESH_INSTALL=true
fs_preflight --skip-if-installed
[[ "$FRESH_INSTALL" == true ]]
[[ "$FS_ALREADY_INSTALLED" == false ]]
''')

    def test_upgrade_with_missing_vars_stops_instead_of_switching_to_fresh_mode(self):
        (self.conf / 'vars.xml').unlink()
        before = config.inventory(self.conf)
        self.run_shell('''
FS_CONF_DIR="$FIXTURE/conf"
FS_BACKUP_ROOT="$FIXTURE/backups"
FS_LOCK_PATH="$FIXTURE/lock"
FS_SERVICE_PATH="$FIXTURE/unit"
FRESH_INSTALL=false
fs_preflight
''', 1)
        self.assertEqual(before, config.inventory(self.conf))
        self.assertFalse((self.root / 'backups').exists())

    def test_config_edit_during_build_is_kept_and_stops_install(self):
        self.snapshot()
        self.run_shell('''
FS_BACKUP_DIR="$FIXTURE"
mv "$FIXTURE/backup" "$FIXTURE/config"
FS_CONF_DIR="$FIXTURE/conf"
FS_CONFIG_PROTECTED=true
trap fs_cleanup EXIT
printf administrator-edit > "$FS_CONF_DIR/vars.xml"
fs_begin_install
touch "$FIXTURE/should-not-install"
''', 1)
        self.assertEqual((self.conf / 'vars.xml').read_text(), 'administrator-edit')
        self.assertFalse((self.root / 'should-not-install').exists())


if __name__ == '__main__':
    unittest.main()
