#!/usr/bin/env python3
"""Manage release metadata; the workflow builds and verifies the assets."""

import argparse
import json
import os
from pathlib import Path
import re
import subprocess
import sys


def run(*args, missing_ok=False, input=None):
    result = subprocess.run(args, input=input, capture_output=True, text=True)
    if result.returncode:
        if missing_ok and '(HTTP 404)' in result.stderr:
            return None
        raise RuntimeError(result.stderr.strip() or f'{args[0]} failed ({result.returncode})')
    return result.stdout.strip()


def version_tuple(version):
    if not re.fullmatch(r'(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)', version):
        raise ValueError(f'Expected a stable X.Y.Z version, got {version!r}')
    return tuple(map(int, version.split('.')))


def read_version(source):
    versions = re.findall(r"['\"]release['\"]\s*=>\s*['\"]([^'\"]+)['\"]", source)
    if len(versions) != 1:
        raise ValueError('config/version.php must contain exactly one release version')
    version_tuple(versions[0])
    return versions[0]


def bumped_version(source, previous=None):
    version = read_version(source)
    if previous is not None:
        old = read_version(previous)
        if version == old:
            return None
        if version_tuple(version) < version_tuple(old):
            raise ValueError(f'Release version must increase: {old} -> {version}')
    return version


class GitHub:
    def __init__(self, repository):
        if not re.fullmatch(r'[\w.-]+/[\w.-]+', repository):
            raise ValueError('GH_REPO must be owner/repository')
        self.repository = repository

    def api(self, path, data=None, missing_ok=False):
        args = ['gh', 'api', f'repos/{self.repository}/{path}']
        if data is not None:
            args += ['--method', 'POST', '--input', '-']
        output = run(*args, missing_ok=missing_ok, input=json.dumps(data) if data is not None else None)
        return json.loads(output) if output is not None else None

    def release(self, tag):
        published = self.api(f'releases/tags/{tag}', missing_ok=True)
        if published is not None:
            return published
        # The tag endpoint only returns published releases. Authenticated
        # release listings also include drafts, identified by their pending tag.
        page = 1
        while True:
            releases = self.api(f'releases?per_page=100&page={page}')
            for candidate in releases:
                if candidate['tag_name'] == tag:
                    return candidate
            if len(releases) < 100:
                return None
            page += 1

    def verify_tag(self, tag, commit):
        if self.api(f'commits/{tag}')['sha'] != commit:
            raise ValueError(f'{tag} already points to a different commit; bump the version instead')

    def cli(self, *args):
        return run('gh', 'release', *args, '--repo', self.repository)


def asset_names(version):
    archive = f'fspbx-frontend-{version}.tar.gz'
    return archive, f'{archive}.sha256'


def prepare(github, version, commit, root):
    tag = f'v{version}'
    if github.api(f'git/ref/tags/{tag}', missing_ok=True) is None:
        github.api('git/refs', {'ref': f'refs/tags/{tag}', 'sha': commit})
    github.verify_tag(tag, commit)
    release = github.release(tag)
    if release is None:
        notes = root / '.github' / 'release-notes' / f'{tag}.md'
        data = {'tag_name': tag, 'target_commitish': commit, 'name': tag,
                'draft': True, 'prerelease': False}
        if notes.is_file():
            data['body'] = notes.read_text(encoding='utf-8')
        else:
            data['generate_release_notes'] = True
        # Use the creation response: a new draft may not appear in a follow-up
        # release lookup immediately, even though creation succeeded.
        release = github.api('releases', data)
        if not release or release.get('draft') is not True or release.get('tag_name') != tag:
            raise RuntimeError('GitHub did not return the expected draft release')
    return {'version': version, 'tag': tag, 'commit': commit,
            'mode': 'build' if release['draft'] else 'published'}


def upload(github, version, commit, root):
    tag = f'v{version}'
    github.verify_tag(tag, commit)
    release = github.release(tag)
    if release is None or not release['draft']:
        raise ValueError('Assets may only be uploaded to a draft release')
    paths = [root / name for name in asset_names(version)]
    if any(not path.is_file() or path.stat().st_size == 0 for path in paths):
        raise ValueError('Both the frontend archive and its checksum must exist')
    github.cli('upload', tag, *(str(path) for path in paths), '--clobber')


def publish(github, version, commit):
    tag = f'v{version}'
    github.verify_tag(tag, commit)
    release = github.release(tag)
    if release is None:
        raise ValueError('Draft release is missing')
    available = {asset['name'] for asset in release['assets'] if asset['size'] > 0}
    if not set(asset_names(version)).issubset(available):
        raise ValueError('Release is missing its frontend archive or checksum')
    if release['draft']:
        latest = github.api('releases/latest', missing_ok=True)
        make_latest = latest is None or version_tuple(version) > version_tuple(latest['tag_name'].removeprefix('v'))
        github.cli('edit', tag, '--draft=false', '--prerelease=false', f'--latest={str(make_latest).lower()}')
        # Draft URLs use an untagged identifier; read the final public URL.
        release = github.release(tag)
    return release['html_url']


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command', choices=['prepare', 'upload', 'publish'])
    args = parser.parse_args()
    root = Path.cwd()
    previous = None
    if args.command == 'prepare' and os.environ.get('GITHUB_EVENT_NAME') == 'push':
        event = json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
        before = event['before']
        if not re.fullmatch(r'[0-9a-f]{40}', before):
            raise ValueError('Invalid previous commit in push event')
        if before != '0' * 40:
            previous = run('git', 'show', f'{before}:config/version.php')
    version = bumped_version((root / 'config' / 'version.php').read_text(), previous)
    if version is None:
        print('Release version is unchanged; nothing to publish.')
        outputs = {'mode': 'skip'}
    else:
        commit = run('git', 'rev-parse', 'HEAD')
        run('git', 'merge-base', '--is-ancestor', commit, 'origin/main')
        github = GitHub(os.environ['GH_REPO'])
        if args.command == 'prepare':
            outputs = prepare(github, version, commit, root)
        elif args.command == 'upload':
            upload(github, version, commit, root)
            outputs = {}
        else:
            outputs = {'url': publish(github, version, commit)}
    if os.environ.get('GITHUB_OUTPUT'):
        with open(os.environ['GITHUB_OUTPUT'], 'a') as output:
            for name, value in outputs.items():
                output.write(f'{name}={value}\n')


if __name__ == '__main__':
    try:
        main()
    except (RuntimeError, ValueError, OSError, KeyError) as error:
        print(f'Release failed: {error}', file=sys.stderr)
        sys.exit(1)
