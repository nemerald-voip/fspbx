import assert from 'node:assert/strict';
import { mkdtemp, readFile, readdir, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { compile } from '@mdx-js/mdx';
import yaml from 'js-yaml';
import { fetchReleases, renderRelease, syncReleases } from './sync-releases.mjs';

const release = (id, changes = {}) => ({
    id,
    tag_name: `v1.0.${id}`,
    name: '',
    published_at: '2026-09-23T12:00:00Z',
    draft: false,
    prerelease: false,
    body: 'Original release notes.',
    ...changes,
});
const response = body => new Response(JSON.stringify(body), { status: 200 });

async function temporaryDirectory(t) {
    const directory = await mkdtemp(path.join(os.tmpdir(), 'fspbx-release-test-'));
    t.after(() => rm(directory, { recursive: true, force: true }));
    return directory;
}

test('fetches every page and authenticates with the supplied token', async () => {
    const requests = [];
    const releases = await fetchReleases({
        token: 'test-token',
        fetchImpl: async (url, options) => {
            requests.push({ url, options });
            return response(requests.length === 1
                ? Array.from({ length: 100 }, (_, i) => release(i + 1))
                : [release(101)]);
        },
    });
    assert.equal(releases.length, 101);
    assert.equal(new URL(requests[1].url).searchParams.get('page'), '2');
    assert.equal(requests[0].options.headers.Authorization, 'Bearer test-token');
    assert.ok(requests[0].options.signal instanceof AbortSignal);
});

test('updates notes, excludes drafts and prereleases, and removes withdrawn releases while preserving manual posts', async t => {
    const directory = await temporaryDirectory(t);
    const manual = path.join(directory, '99.md');
    await writeFile(manual, 'A manually written post.');
    await syncReleases({ directory, fetchImpl: async () => response([release(1), release(2)]) });
    const count = await syncReleases({
        directory,
        fetchImpl: async () => response([
            release(1, { body: 'Corrected notes.' }),
            release(2, { prerelease: true }),
            release(3, { draft: true }),
        ]),
    });
    assert.equal(count, 1);
    assert.match(await readFile(path.join(directory, '1.md'), 'utf8'), /Corrected notes/);
    assert.deepEqual((await readdir(directory)).sort(), ['1.md', '99.md']);
    await syncReleases({ directory, fetchImpl: async () => response([]) });
    assert.deepEqual(await readdir(directory), ['99.md']);
    assert.equal(await readFile(manual, 'utf8'), 'A manually written post.');
});

test('a failure on a later API page leaves the previously generated history intact', async t => {
    const directory = await temporaryDirectory(t);
    await syncReleases({ directory, fetchImpl: async () => response([release(1)]) });
    const original = await readFile(path.join(directory, '1.md'), 'utf8');
    let page = 0;
    await assert.rejects(syncReleases({
        directory,
        fetchImpl: async () => ++page === 1
            ? response(Array.from({ length: 100 }, (_, i) => release(i + 1, { body: 'New notes' })))
            : new Response('Rate limit exceeded', { status: 403 }),
    }), /HTTP 403, page 2/);
    assert.equal(await readFile(path.join(directory, '1.md'), 'utf8'), original);
    assert.deepEqual(await readdir(directory), ['1.md']);
});

test('rejects malformed API responses and unsafe identifiers before changing files', async t => {
    const directory = await temporaryDirectory(t);
    for (const payload of [{ message: 'Not a release list' }, [release('../outside')], [release(1, { published_at: null })]]) {
        await assert.rejects(syncReleases({ directory, fetchImpl: async () => response(payload) }));
    }
    assert.deepEqual(await readdir(directory), []);
});

test('refuses to overwrite a manual post with a matching release filename', async t => {
    const directory = await temporaryDirectory(t);
    await writeFile(path.join(directory, '1.md'), 'Manual content');
    await assert.rejects(syncReleases({ directory, fetchImpl: async () => response([release(1)]) }), /manual blog post/);
    assert.equal(await readFile(path.join(directory, '1.md'), 'utf8'), 'Manual content');
});

test('escapes front matter and compiles GitHub Markdown without evaluating MDX expressions', async () => {
    const title = 'Release "quoted": update\nwith another line';
    const post = renderRelease(release(1, {
        name: title,
        tag_name: 'v1.0/variant',
        body: 'Use {account_id} with values < 10.\n\n```js\nconst data = { key: "value" };\n```',
    }));
    const [, frontMatter, body] = post.split('---\n');
    const metadata = yaml.load(frontMatter);
    assert.equal(metadata.title, title);
    assert.ok(metadata.date instanceof Date);
    assert.equal(metadata.date.toISOString(), '2026-09-23T12:00:00.000Z');
    assert.equal(metadata.slug, 'release-v1.0%2Fvariant');
    assert.equal(metadata.mdx.format, 'md');
    assert.match(body, /releases\/tag\/v1\.0%2Fvariant/);
    const compiled = String(await compile(body, { format: metadata.mdx.format }));
    assert.match(compiled, /\{account_id\}/);
    assert.match(renderRelease(release(2, { name: ' ', body: null })), /title: "FS PBX v1.0.2"/);
});
