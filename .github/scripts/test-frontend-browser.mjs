// Optional browser smoke test. Playwright is a release/test dependency only.
// PLAYWRIGHT_MODULE may point to an isolated installation outside the repo.
import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import assert from 'node:assert/strict';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ?? 'playwright');
const directories = process.argv.slice(2).map((directory) => path.resolve(directory));
const metadata = directories.map((directory) => JSON.parse(fs.readFileSync(path.join(directory, 'frontend.json'))));
assert.equal(metadata[0]?.kind, 'core');
const imports = Object.fromEntries(Object.entries(metadata[0].imports).map(([name, file]) => [name, `/0/${file}`]));
const failures = [];
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    if (url.pathname === '/') {
        response.setHeader('Content-Type', 'text/html');
        response.end(`<html><head><script type="importmap">${JSON.stringify({ imports })}</script></head><body><div id="probe"></div></body></html>`);
        return;
    }
    const [, index, ...parts] = decodeURIComponent(url.pathname).split('/');
    const root = directories[Number(index)];
    const file = root && path.resolve(root, ...parts);
    if (!root || !file.startsWith(root + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
        failures.push(`Missing asset: ${url.pathname}`);
        response.writeHead(404).end();
        return;
    }
    const contentType = { '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json' }[path.extname(file)] ?? 'application/octet-stream';
    response.setHeader('Content-Type', contentType);
    fs.createReadStream(file).pipe(response);
});
await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
let browser;
try {
    browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const page = await browser.newPage();
    await page.route('**/*', (route) => new URL(route.request().url()).hostname === '127.0.0.1' ? route.continue() : route.abort());
    page.on('pageerror', (error) => failures.push(error.message));
    await page.goto(`http://127.0.0.1:${server.address().port}/`);
    const result = await page.evaluate(async (packages) => {
        const vue = await import('@fspbx/runtime/vue');
        const inertia = await import('@fspbx/runtime/@inertiajs/vue3');
        const inertiaCore = await import('@fspbx/runtime/@inertiajs/core');
        const i18n = await import('@fspbx/runtime/i18n');
        if (inertia.router !== inertiaCore.router) throw new Error('Inertia router is duplicated');
        const app = vue.createApp({ render: () => vue.h('span', { id: 'translated' }, i18n.trans('Bundle smoke test')) });
        app.use(i18n.i18nVue, { lang: 'en-us', resolve: () => Promise.resolve({ default: { 'Bundle smoke test': 'Shared translation works' } }) });
        app.mount('#probe');
        await i18n.loadLanguageAsync('en-us');
        await vue.nextTick();
        const loaded = [];
        for (const [index, metadata] of packages.entries()) {
            if (index === 0) continue;
            const module = await import(`/${index}/${metadata.entry}`);
            const pageName = metadata.name === 'ContactCenter' ? 'Dashboard' : 'Account';
            const component = await module.resolvePage(pageName);
            if (!component.default || (!component.default.render && !component.default.setup)) throw new Error('Missing compiled component');
            loaded.push(metadata.name + '/' + pageName);
        }
        return { translation: document.querySelector('#translated').textContent, loaded };
    }, metadata);
    assert.equal(result.translation, 'Shared translation works');
    assert.deepEqual(failures, []);
    console.log(`Browser smoke passed: shared Vue/Inertia/i18n and ${result.loaded.join(', ')}.`);
} finally {
    await browser?.close();
    await new Promise((resolve) => server.close(resolve));
}
