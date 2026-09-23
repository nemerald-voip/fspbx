"""Build optimization checks using temporary libraries, sources, and caches only."""
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

REPO = Path(__file__).resolve().parents[2]


class BuildTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='fspbx-build-test-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)

    def run_shell(self, body, expected=0):
        env = os.environ | {'FIXTURE': str(self.root), 'REPO': str(REPO)}
        result = subprocess.run(['bash', '-c', '''set -Eeuo pipefail
source "$REPO/install/freeswitch-common.sh"
FS_BACKUP_DIR="$FIXTURE"
BUILD_DIR="$FIXTURE/build"
JOBS=2
''' + body], env=env, capture_output=True, text=True)
        self.assertEqual(result.returncode, expected, result.stdout + result.stderr)
        return result

    def sofia_fixture(self, version='1.13.18', tls_header=True, tls_symbol=True, name='library'):
        prefix = self.root / name
        (prefix / 'include/sofia-sip').mkdir(parents=True)
        (prefix / 'lib/pkgconfig').mkdir(parents=True)
        (prefix / 'include/sofia-sip/nua.h').write_text(
            ('#define HAVE_NUA_RELOAD_TLS 1\n' if tls_header else '') +
            'typedef struct nua_s nua_t;\nint nua_reload_tls(nua_t *, char const *);\n')
        source = prefix / 'library.c'
        source.write_text('int ' + ('nua_reload_tls' if tls_symbol else 'other_symbol') +
                          '(void *nua, char const *path) { return 0; }\n')
        subprocess.run(['gcc', '-shared', '-fPIC', '-Wl,-soname,libsofia-sip-ua.so.0',
                        str(source), '-o', str(prefix / 'lib/libsofia-sip-ua.so.0')], check=True)
        (prefix / 'lib/libsofia-sip-ua.so').symlink_to('libsofia-sip-ua.so.0')
        (prefix / 'lib/pkgconfig/sofia-sip-ua.pc').write_text(f'''prefix={prefix}
libdir=${{prefix}}/lib
includedir=${{prefix}}/include
Name: Sofia test fixture
Description: Isolated dependency reuse probe
Version: {version}
Libs: -L${{libdir}} -lsofia-sip-ua
Cflags: -I${{includedir}}
''')
        return prefix

    def probe_sofia(self, expected=0, reference='v1.13.18', runtime='library'):
        return self.run_shell(f'''
export PKG_CONFIG_LIBDIR="$FIXTURE/library/lib/pkgconfig"
export PKG_CONFIG_PATH=
export LD_LIBRARY_PATH="$FIXTURE/{runtime}/lib"
fs_dependency_ready sofia-sip-ua {reference}
''', expected)

    def test_release_dependency_reuse_checks_version_and_custom_refs(self):
        self.sofia_fixture(version='1.13.19')
        self.probe_sofia()
        self.assertIn('sofia-sip-ua 1.13.19 reused', (self.root / 'dependency-actions.txt').read_text())
        self.probe_sofia(expected=1, reference='v1.13.20')
        self.probe_sofia(expected=1, reference='custom-branch')
        self.probe_sofia(expected=1, reference='0123456789abcdef')

    def test_missing_dependency_is_not_reused(self):
        self.probe_sofia(expected=1)

    def test_version_alone_does_not_allow_missing_tls_header_support(self):
        self.sofia_fixture(tls_header=False)
        self.probe_sofia(expected=1)

    def test_version_alone_does_not_allow_missing_tls_symbol(self):
        self.sofia_fixture(tls_symbol=False)
        self.probe_sofia(expected=1)

    def test_runtime_library_must_match_development_files(self):
        self.sofia_fixture()
        self.sofia_fixture(name='shadow')
        self.probe_sofia(expected=1, runtime='shadow')

    def test_unresolved_runtime_dependency_is_not_reused(self):
        prefix = self.sofia_fixture()
        missing = prefix / 'lib/libmissing-fixture.so'
        source = prefix / 'missing.c'
        source.write_text('int missing(void) { return 0; }\n')
        subprocess.run(['gcc', '-shared', '-fPIC', str(source), '-o', str(missing)], check=True)
        (prefix / 'library.c').write_text('int missing(void);\nint nua_reload_tls(void *nua, char const *path) { return missing(); }\n')
        subprocess.run(['gcc', '-shared', '-fPIC', str(prefix / 'library.c'),
                        '-L' + str(prefix / 'lib'), '-lmissing-fixture',
                        '-Wl,-soname,libsofia-sip-ua.so.0', '-o',
                        str(prefix / 'lib/libsofia-sip-ua.so.0')], check=True)
        # Allow the executable to link even with the missing indirect library.
        pc = prefix / 'lib/pkgconfig/sofia-sip-ua.pc'
        pc.write_text(pc.read_text().replace('-lsofia-sip-ua', '-lsofia-sip-ua -Wl,--allow-shlib-undefined'))
        missing.unlink()
        self.probe_sofia(expected=1)

    def test_only_missing_dependencies_are_built_and_published(self):
        # Exercise new servers, partial upgrades, and repeat upgrades without
        # running network operations, host installation, or ldconfig.
        for missing in ('all', 'libks2', 'sofia-sip-ua', 'spandsp', 'none'):
            with self.subTest(missing=missing):
                self.run_shell('''
export MISSING=''' + missing + '''
fs_dependency_ready() { [[ "$MISSING" != all && "$MISSING" != "$1" ]]; }
fs_probe_library() { printf 'checked %s\\n' "$1" >> "$FIXTURE/events"; }
fs_check_libraries() { printf 'checked rest\\n' >> "$FIXTURE/events"; }
fs_clone() {
    printf 'clone %s\\n' "${3##*/}" >> "$FIXTURE/events"
    mkdir -p "$3"
    printf '#!/bin/sh\\nexit 0\\n' > "$3/autogen.sh"
    cp "$3/autogen.sh" "$3/configure"
    chmod +x "$3/configure"
}
cmake() { printf 'cmake\\n' >> "$FIXTURE/events"; }
make() { printf 'make\\n' >> "$FIXTURE/events"; }
fs_publish_dependencies() { printf 'publish\\n' >> "$FIXTURE/events"; }
fs_build_dependencies
''')
                events = (self.root / 'events').read_text().splitlines()
                clones = [line.removeprefix('clone ') for line in events if line.startswith('clone ')]
                expected = {'libks2': 'libks', 'sofia-sip-ua': 'sofia-sip', 'spandsp': 'spandsp'}
                self.assertEqual(clones, list(expected.values()) if missing == 'all' else
                                 [] if missing == 'none' else [expected[missing]])
                self.assertEqual(events.count('publish'), 0 if missing == 'none' else 1)
                self.assertEqual(events[-2:], ['checked libks2', 'checked rest'])
                (self.root / 'events').unlink()

    @unittest.skipUnless(shutil.which('ccache'), 'ccache is needed for real compiler cache checks')
    def test_cache_reuses_clean_builds_but_invalidates_changed_inputs(self):
        # Use the same paths relative to different build roots, as the installer
        # does. Include debug info and absolute include/source paths.
        for attempt, answer, adjustment in ((1, 7, 0), (2, 7, 0), (3, 9, 0), (4, 9, 2)):
            for language, extension, compiler in (('c', 'c', 'CC'), ('cxx', 'cpp', 'CXX')):
                src = self.root / f'build-{attempt}/freeswitch/{language}'
                src.mkdir(parents=True)
                (src / 'answer.h').write_text(f'#define ANSWER {answer}\n')
                (src / f'main.{extension}').write_text('#include "answer.h"\nint main(void) { return ANSWER + ADJUSTMENT; }\n')
                self.run_shell(f'''
export CCACHE_DIR="$FIXTURE/cache"
BUILD_DIR="$FIXTURE/build-{attempt}"
unset CC CXX CPPFLAGS
fs_prepare_compiler_cache
[[ "$CCACHE_MAXSIZE" == 2G ]]
cd "$BUILD_DIR/freeswitch/{language}"
${compiler} $CPPFLAGS -g -O2 -DADJUSTMENT={adjustment} -I"$PWD" -c "$PWD/main.{extension}" -o main.o
${compiler} main.o -o main
status=0
./main || status=$?
[[ $status == {answer + adjustment} ]]
ccache --print-stats > "$FIXTURE/stats-{attempt}"
''')
        stats = []
        for attempt in range(1, 5):
            stats.append(dict(line.split() for line in (self.root / f'stats-{attempt}').read_text().splitlines()))
        self.assertEqual(int(stats[0]['cache_miss']), 2)
        self.assertEqual(int(stats[1]['cache_miss']), 2)
        self.assertEqual(int(stats[1]['direct_cache_hit']) + int(stats[1]['preprocessed_cache_hit']), 2)
        self.assertEqual(int(stats[2]['cache_miss']), 4)
        self.assertEqual(int(stats[3]['cache_miss']), 6)

    @unittest.skipUnless(shutil.which('ccache') and shutil.which('cmake'), 'ccache and cmake are required')
    def test_cmake_uses_cache_for_c_and_cpp_across_clean_builds(self):
        for attempt in (1, 2):
            source = self.root / f'build-{attempt}/libks'
            source.mkdir(parents=True)
            (source / 'CMakeLists.txt').write_text('''cmake_minimum_required(VERSION 3.16)
project(cache_fixture LANGUAGES C CXX)
add_library(fixture SHARED fixture.c fixture.cpp)
''')
            (source / 'fixture.c').write_text('int c_value(void) { return 7; }\n')
            (source / 'fixture.cpp').write_text('int cpp_value() { return 9; }\n')
            self.run_shell(f'''
export CCACHE_DIR="$FIXTURE/cache"
BUILD_DIR="$FIXTURE/build-{attempt}"
unset CC CXX CPPFLAGS
fs_prepare_compiler_cache
cmake -S "$BUILD_DIR/libks" -B "$BUILD_DIR/libks/build" -DCMAKE_BUILD_TYPE=Release \\
    "-DCMAKE_C_FLAGS=$FS_BUILD_PREFIX_FLAGS" "-DCMAKE_CXX_FLAGS=$FS_BUILD_PREFIX_FLAGS"
ccache --zero-stats
cmake --build "$BUILD_DIR/libks/build" --parallel 2
ccache --print-stats > "$FIXTURE/stats-{attempt}"
''')
        stats = dict(line.split() for line in (self.root / 'stats-2').read_text().splitlines())
        self.assertEqual(int(stats['cache_miss']), 0)
        self.assertEqual(int(stats['direct_cache_hit']) + int(stats['preprocessed_cache_hit']), 2)


if __name__ == '__main__':
    unittest.main()
