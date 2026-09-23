"""Isolated checks: never run apt or touch live configuration or services."""
import importlib.util
import json
import os
from pathlib import Path
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
        selected = (baseline | config.loaded_modules(REPO / 'resources')) - config.EXCLUDED_MODULES
        defaults = json.loads((REPO / 'resources/freeswitch_modules.json').read_text())
        self.assertEqual(set(defaults), selected)

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

    def run_shell(self, body, expected=0):
        env = os.environ | {'FIXTURE': str(self.root), 'REPO': str(REPO)}
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

    def test_repeated_upgrades_keep_configuration_and_separate_backups(self):
        for attempt in range(3):
            self.run_shell('''
FS_CONF_DIR="$FIXTURE/conf"
FS_BACKUP_ROOT="$FIXTURE/backups"
FS_LOCK_PATH="$FIXTURE/lock"
FS_SERVICE_PATH="$FIXTURE/unit"
FRESH_INSTALL=false
fs_preflight
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
fs_preflight
[[ "$FRESH_INSTALL" == true ]]
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
