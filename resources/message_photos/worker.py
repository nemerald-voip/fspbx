#!/usr/bin/env python3
"""A serial local socket broker. Each photo is decoded in a fresh subprocess.

Run under the supplied systemd unit: its cgroup, not a libvips cache setting,
enforces the aggregate memory/CPU ceiling for broker and decoder together.
"""
import json
import math
import os
from pathlib import Path
import re
import socket
import stat
import subprocess
import sys
import time

ROOT = Path('/var/lib/fspbx-photo-compression')
SOCKET = '/run/fspbx-photo-compression/worker.sock'
MAX_BYTES = 50 * 1024 * 1024
MAX_PIXELS = 100_000_000
IDENTIFIER = re.compile(r'[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}')


def validate(request):
    identifier = request.get('id', '')
    converting = request.get('op') == 'convert'
    budget = MAX_BYTES if converting else request.get('budget')
    if not isinstance(identifier, str) or not IDENTIFIER.fullmatch(identifier):
        raise ValueError('request')
    if request.get('op') not in ('compress', 'convert') or type(budget) is not int or not 16000 <= budget <= (MAX_BYTES if converting else 650000):
        raise ValueError('request')
    directory = ROOT / identifier
    source = directory / 'input'
    if directory.is_symlink() or not stat.S_ISREG(source.lstat().st_mode):
        raise ValueError('request')
    if source.stat().st_size > MAX_BYTES or source.stat().st_size == 0:
        raise ValueError('size')
    return source, directory / 'output.jpg', budget


def compress(request):
    source, output, budget = validate(request)
    reduce_size = request.get('op') == 'compress'
    # Reject non-photo signatures before dispatching to a native loader.
    with source.open('rb') as handle:
        header = handle.read(32)
    jpeg = header.startswith(b'\xff\xd8\xff')
    png = header.startswith(b'\x89PNG\r\n\x1a\n')
    webp = header[:4] == b'RIFF' and header[8:12] == b'WEBP'
    heif = header[4:8] == b'ftyp' and header[8:12] in (b'heic', b'heix', b'hevc', b'hevx', b'mif1', b'msf1', b'avif', b'avis')
    tiff = header[:4] in (b'II*\x00', b'MM\x00*', b'II+\x00', b'MM\x00+')
    if not any((jpeg, png, webp, heif, tiff)):
        raise ValueError('format')

    import pyvips
    pyvips.cache_set_max(0)
    pyvips.block_untrusted_set(True)
    pyvips.operation_block_set('VipsForeignLoad', True)
    for loader in ('VipsForeignLoadJpeg', 'VipsForeignLoadPng', 'VipsForeignLoadWebp', 'VipsForeignLoadHeif', 'VipsForeignLoadTiff'):
        pyvips.operation_block_set(loader, False)
    # ImageMagick remains blocked. Header checks also run inside the cgroup.
    info = pyvips.Image.new_from_file(str(source), access='sequential', fail_on='error')
    if info.width <= 0 or info.height <= 0 or info.width * info.height > MAX_PIXELS or max(info.width, info.height) > 30000:
        raise ValueError('dimensions')
    if info.get_typeof('n-pages') and info.get('n-pages') > 1:
        raise ValueError('animated')
    if (jpeg and source.stat().st_size <= budget) or (png and not reduce_size):
        return {'ok': True, 'unchanged': True, 'encodes': 0}
    edge = 1600 if reduce_size else max(info.width, info.height)
    del info

    # Decode/shrink once; only the small RGB image is retained for the fallback.
    small = pyvips.Image.thumbnail(str(source) + '[fail-on=error]', edge,
                                   height=edge, size='down', export_profile='srgb')
    if small.hasalpha():
        small = small.flatten(background=[255, 255, 255])
    small = small.colourspace('srgb')
    if reduce_size:
        small = small.copy_memory()
    options = dict(strip=True, optimize_coding=False, interlace=False, subsample_mode='on')
    encoded = small.jpegsave_buffer(Q=80 if reduce_size else 90, **options)
    encodes = 1
    if reduce_size and len(encoded) > budget:
        scale = min(0.8, math.sqrt(budget / len(encoded)) * 0.85)
        encoded = small.resize(scale).jpegsave_buffer(Q=70, **options)
        encodes = 2
    if len(encoded) > budget:
        raise ValueError('size')
    # Exclusive creation prevents an existing link/file from being overwritten.
    with output.open('xb') as handle:
        handle.write(encoded)
    return {'ok': True, 'encodes': encodes, 'bytes': len(encoded)}


def child():
    try:
        result = compress(json.loads(sys.stdin.readline(4096)))
    except ValueError as error:
        result = {'ok': False, 'error': str(error) if str(error) in ('request', 'size', 'dimensions', 'animated', 'format') else 'invalid'}
    except Exception:
        # No image metadata, paths, or native decoder error contents in responses.
        result = {'ok': False, 'error': 'decode'}
    print(json.dumps(result), flush=True)


def serve():
    os.umask(0o007)
    Path(SOCKET).unlink(missing_ok=True)
    with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as server:
        server.bind(SOCKET)
        server.listen(4)
        last_cleanup = 0
        while True:
            connection, _ = server.accept()
            if time.monotonic() - last_cleanup > 60:
                # A killed PHP job may leave its request behind. Never follow links
                # or remove anything except our two files in stale UUID folders.
                for directory in ROOT.iterdir():
                    try:
                        if IDENTIFIER.fullmatch(directory.name) and not directory.is_symlink() and directory.is_dir() and time.time() - directory.stat().st_mtime > 3600:
                            for name in ('input', 'output.jpg'):
                                (directory / name).unlink(missing_ok=True)
                            directory.rmdir()
                    except OSError:
                        pass
                last_cleanup = time.monotonic()
            with connection:
                connection.settimeout(3)
                try:
                    with connection.makefile('rb') as stream:
                        request = json.loads(stream.readline(4096))
                    if request.get('op') == 'ping':
                        response = {'ok': True}
                    elif request.get('op') in ('compress', 'convert'):
                        try:
                            validate(request)
                            process = subprocess.run([sys.executable, __file__, '--child'],
                                input=json.dumps(request), text=True, capture_output=True, timeout=15,
                                env={**os.environ, 'VIPS_CONCURRENCY': '1', 'OMP_NUM_THREADS': '1'})
                            response = json.loads(process.stdout) if process.returncode == 0 else {'ok': False, 'error': 'decode'}
                        except subprocess.TimeoutExpired:
                            response = {'ok': False, 'error': 'timeout'}
                    else:
                        response = {'ok': False, 'error': 'request'}
                    connection.sendall(json.dumps(response).encode() + b'\n')
                except (ValueError, OSError):
                    try:
                        connection.sendall(b'{"ok":false,"error":"request"}\n')
                    except OSError:
                        pass


if __name__ == '__main__':
    child() if '--child' in sys.argv else serve()
