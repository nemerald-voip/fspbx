#!/usr/bin/env python3
"""Exercise release creation, retries and publication without contacting GitHub."""

import contextlib
import io
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import Mock, patch

import release


VERSION = '2.1.5'
COMMIT = 'a' * 40
TAG = 'v2.1.5'


def source(version):
    return f"<?php return ['release' => '{version}'];"


def metadata(draft=True, assets=True):
    return {'tag_name': TAG, 'draft': draft, 'html_url': f'https://github.com/example/fspbx/releases/tag/{TAG}',
            'assets': [{'name': name, 'size': 100} for name in release.asset_names(VERSION)] if assets else []}


class ReleaseTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        self.github = Mock(spec=release.GitHub)
        self.github.release.return_value = metadata()
        self.github.api.return_value = {'tag_name': 'v2.1.4'}

    def test_increasing_numeric_version(self):
        self.assertEqual(release.bumped_version(source('2.1.10'), source('2.1.9')), '2.1.10')

    def test_unchanged_version_is_skipped(self):
        self.assertIsNone(release.bumped_version(source(VERSION), source(VERSION)))

    def test_downgrade_is_rejected(self):
        with self.assertRaisesRegex(ValueError, 'must increase'):
            release.bumped_version(source('2.1.4'), source(VERSION))

    def test_malformed_or_ambiguous_version_is_rejected(self):
        for value in ['v2.1.5', '2.1', '2.1.05', '2.1.5-rc1', '2.1.5\nmode=published']:
            with self.subTest(value=value), self.assertRaises(ValueError):
                release.read_version(source(value))
        with self.assertRaises(ValueError):
            release.read_version(source(VERSION) + source(VERSION))

    def test_new_release_is_created_as_draft_at_exact_commit(self):
        self.github.api.return_value = None
        self.github.release.side_effect = [None, metadata()]
        result = release.prepare(self.github, VERSION, COMMIT, self.root)
        self.github.api.assert_any_call('git/refs', {'ref': f'refs/tags/{TAG}', 'sha': COMMIT})
        self.github.verify_tag.assert_called_once_with(TAG, COMMIT)
        self.github.cli.assert_called_once_with('create', TAG, '--draft', '--verify-tag',
                                               '--target', COMMIT, '--title', TAG, '--generate-notes')
        self.assertEqual(result['mode'], 'build')

    def test_written_notes_are_used_when_present(self):
        notes = self.root / '.github' / 'release-notes' / f'{TAG}.md'
        notes.parent.mkdir(parents=True)
        notes.write_text('## Improvements and fixes\n')
        self.github.release.side_effect = [None, metadata()]
        release.prepare(self.github, VERSION, COMMIT, self.root)
        self.assertEqual(self.github.cli.call_args.args[-2:], ('--notes-file', str(notes)))

    def test_tag_collision_does_not_create_release(self):
        self.github.verify_tag.side_effect = ValueError('different commit')
        with self.assertRaises(ValueError):
            release.prepare(self.github, VERSION, COMMIT, self.root)
        self.github.cli.assert_not_called()
        self.github.release.assert_not_called()
        self.github.api.assert_called_once_with(f'git/ref/tags/{TAG}', missing_ok=True)

    def test_failed_draft_can_be_rebuilt(self):
        self.github.release.return_value = metadata(assets=False)
        self.assertEqual(release.prepare(self.github, VERSION, COMMIT, self.root)['mode'], 'build')
        self.github.cli.assert_not_called()

    def test_published_release_is_verification_only(self):
        self.github.release.return_value = metadata(draft=False)
        self.assertEqual(release.prepare(self.github, VERSION, COMMIT, self.root)['mode'], 'published')
        release.publish(self.github, VERSION, COMMIT)
        self.github.cli.assert_not_called()

    def test_upload_replaces_draft_assets(self):
        for name in release.asset_names(VERSION):
            (self.root / name).write_bytes(b'package')
        release.upload(self.github, VERSION, COMMIT, self.root)
        self.assertEqual(self.github.cli.call_args.args[0:2], ('upload', TAG))
        self.assertEqual(self.github.cli.call_args.args[-1], '--clobber')

    def test_upload_cannot_modify_published_release(self):
        self.github.release.return_value = metadata(draft=False)
        with self.assertRaisesRegex(ValueError, 'draft release'):
            release.upload(self.github, VERSION, COMMIT, self.root)
        self.github.cli.assert_not_called()

    def test_upload_requires_both_nonempty_files(self):
        archive, checksum = release.asset_names(VERSION)
        (self.root / archive).write_bytes(b'package')
        for empty_checksum in [False, True]:
            if empty_checksum:
                (self.root / checksum).touch()
            with self.assertRaises(ValueError):
                release.upload(self.github, VERSION, COMMIT, self.root)
        self.github.cli.assert_not_called()

    def test_publish_requires_both_nonempty_uploaded_assets(self):
        for assets in [[], [{'name': release.asset_names(VERSION)[0], 'size': 100}],
                       [{'name': name, 'size': 0} for name in release.asset_names(VERSION)]]:
            self.github.release.return_value = {**metadata(), 'assets': assets}
            with self.assertRaises(ValueError):
                release.publish(self.github, VERSION, COMMIT)
        self.github.cli.assert_not_called()

    def test_new_version_becomes_latest_only_at_publication(self):
        release.publish(self.github, VERSION, COMMIT)
        self.github.cli.assert_called_once_with('edit', TAG, '--draft=false', '--prerelease=false', '--latest=true')

    def test_first_release_becomes_latest(self):
        self.github.api.return_value = None
        release.publish(self.github, VERSION, COMMIT)
        self.assertEqual(self.github.cli.call_args.args[-1], '--latest=true')

    def test_retry_of_older_release_does_not_replace_latest(self):
        self.github.api.return_value = {'tag_name': 'v2.1.10'}
        release.publish(self.github, VERSION, COMMIT)
        self.assertEqual(self.github.cli.call_args.args[-1], '--latest=false')

    def test_tag_verification_resolves_commit(self):
        github = release.GitHub('example/fspbx')
        with patch.object(github, 'api', return_value={'sha': 'b' * 40}):
            with self.assertRaisesRegex(ValueError, 'different commit'):
                github.verify_tag(TAG, COMMIT)

    def test_draft_lookup_after_tag_endpoint_returns_404(self):
        github = release.GitHub('example/fspbx')
        draft = metadata()
        with patch.object(github, 'api', side_effect=[None, [draft]]) as api:
            self.assertEqual(github.release(TAG), draft)
        self.assertEqual(api.call_args.args, ('releases?per_page=100&page=1',))

    def test_draft_lookup_includes_later_pages(self):
        github = release.GitHub('example/fspbx')
        draft = metadata()
        with patch.object(github, 'api', side_effect=[None, [{'tag_name': 'v1.0.0'}] * 100, [draft]]) as api:
            self.assertEqual(github.release(TAG), draft)
        self.assertEqual(api.call_args.args, ('releases?per_page=100&page=2',))

    def test_missing_draft_lookup_finishes_without_creation(self):
        github = release.GitHub('example/fspbx')
        with patch.object(github, 'api', side_effect=[None, []]):
            self.assertIsNone(github.release(TAG))

    def test_publication_returns_public_url_instead_of_draft_url(self):
        draft = {**metadata(), 'html_url': 'https://github.com/example/fspbx/releases/tag/untagged-example'}
        published = metadata(draft=False)
        self.github.release.side_effect = [draft, published]
        self.assertEqual(release.publish(self.github, VERSION, COMMIT), published['html_url'])

    def test_only_404_is_treated_as_missing(self):
        with patch('release.subprocess.run') as command:
            command.return_value = subprocess.CompletedProcess([], 1, '', 'gh: Not Found (HTTP 404)')
            self.assertIsNone(release.run('gh', 'api', 'example', missing_ok=True))
            for message in ['gh: Forbidden (HTTP 403)', 'connection reset', 'gh: API rate limit exceeded (HTTP 429)']:
                command.return_value = subprocess.CompletedProcess([], 1, '', message)
                with self.assertRaises(RuntimeError):
                    release.run('gh', 'api', 'example', missing_ok=True)

    def test_unchanged_push_does_not_contact_github(self):
        (self.root / 'config').mkdir()
        (self.root / 'config' / 'version.php').write_text(source(VERSION))
        event = self.root / 'event.json'
        event.write_text(json.dumps({'before': COMMIT}))
        output = self.root / 'output'
        with patch.object(Path, 'cwd', return_value=self.root), \
                patch.dict(os.environ, {'GITHUB_EVENT_NAME': 'push', 'GITHUB_EVENT_PATH': str(event),
                                        'GITHUB_OUTPUT': str(output)}, clear=True), \
                patch('sys.argv', ['release.py', 'prepare']), \
                patch('release.run', return_value=source(VERSION)) as command, \
                patch('release.GitHub') as github, contextlib.redirect_stdout(io.StringIO()):
            release.main()
        github.assert_not_called()
        command.assert_called_once_with('git', 'show', f'{COMMIT}:config/version.php')
        self.assertEqual(output.read_text(), 'mode=skip\n')


if __name__ == '__main__':
    unittest.main()
