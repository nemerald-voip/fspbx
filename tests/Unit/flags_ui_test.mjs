// Optional isolated browser check: PLAYWRIGHT_MODULE=/path/to/playwright/index.mjs node tests/Unit/flags_ui_test.mjs
import assert from 'node:assert/strict';
import path from 'node:path';
import { createServer } from 'vite';
import vuePlugin from '@vitejs/plugin-vue';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ?? 'playwright');
const root = process.cwd();
const entry = `
import { createApp, h, nextTick } from 'vue';
import Vueform from '@vueform/vueform';
import config from '/resources/js/vueform.config.js';
import { i18nVue } from '/resources/js/i18n.mjs';
import axios from 'axios';
import RingGroup from '/resources/js/Pages/components/forms/UpdateRingGroupForm.vue';
import Ivr from '/resources/js/Pages/components/forms/UpdateVirtualReceptionistForm.vue';
import Flag from '/resources/js/Pages/components/forms/FlagForm.vue';
window.axios = axios;
let app;
window.mountForm = async (type, authorized) => {
  app?.unmount();
  const options = {
    permissions: { manage_settings: true, manage_flags: authorized },
    ring_group: { ring_group_uuid: '33333333-3333-4333-8333-333333333333' },
    item: { ivr_menu_uuid: '33333333-3333-4333-8333-333333333333', type: 'manual' },
    member_options: [], call_distributions: [], routing_types: [], greetings: [],
    ring_back_tones: [], prompt_repeat_options: [], sounds: [], flag_options: [],
    extension_options: [], timezones: [], flag_overrides: [],
    routes: { update_route: '/test-save', store_route: '/test-save' },
  };
  app = createApp({ render: () => h({ ring: RingGroup, ivr: Ivr, flag: Flag }[type], {
    ref: (value) => window.probe = value, options, show: true, loading: false, header: 'Test',
  }) });
  app.use(i18nVue, { lang: 'en-us', resolve: () => Promise.resolve({}) });
  app.use(Vueform, { ...config, services: { axios } });
  app.mount('#app');
  await nextTick();
  window.form = window.probe.$.setupState.form$;
};
`;
const server = await createServer({
    configFile: false, root, publicDir: false, cacheDir: path.resolve('storage/framework/flags-vite'),
    server: { host: '127.0.0.1', port: 0, fs: { allow: [root, path.resolve('node_modules')] } },
    resolve: { alias: {
        '@generalComponents': path.resolve('resources/js/Pages/components/general'),
        '@icons': path.resolve('resources/js/Pages/components/icons'),
        '@i18n': path.resolve('resources/js/i18n.mjs'),
    } },
    plugins: [vuePlugin(), {
        name: 'flags-test-page',
        configureServer(server) {
            server.middlewares.use((request, response, next) => {
                if (request.url !== '/__flags_test') return next();
                response.setHeader('Content-Type', 'text/html');
                response.end('<div id="app"></div><script type="module" src="/__flags_entry.js"></script>');
            });
        },
        resolveId(id) { if (id === '/__flags_entry.js') return '\0flags-entry'; },
        load(id) { if (id === '\0flags-entry') return entry; },
    }],
});
let browser;
try {
    await server.listen();
    browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const page = await browser.newPage();
    const errors = [];
    const submissions = [];
    let stage;
    page.on('pageerror', error => errors.push(stage + ': ' + error.stack));
    await page.route('**/test-save', async route => {
        submissions.push(route.request().postDataJSON());
        await route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ errors: { name: ['Server validation reached.'] } }) });
    });
    await page.goto(`http://127.0.0.1:${server.httpServer.address().port}/__flags_test`);
    await page.waitForFunction(() => !!window.mountForm);
    for (const type of ['ring', 'ivr']) {
        for (const authorized of [false, true]) {
            stage = type + ':' + authorized;
            await page.evaluate(([type, authorized]) => window.mountForm(type, authorized), [type, authorized]);
            await page.waitForTimeout(350); // Let the real modal and VueForm tab transitions settle.
            const tab = page.getByRole('tab', { name: 'Flag Overrides', exact: true });
            assert.equal(await tab.count(), authorized ? 1 : 0, `${type}: tab permission`);
            if (type === 'ivr') assert.equal(await page.getByRole('tab', { name: 'Keys', exact: true }).count(), 1);
            if (authorized) {
                await tab.click();
            }
            const before = submissions.length;
            await page.evaluate(() => window.form.submit());
            await page.waitForFunction(() => !window.form.submitting);
            assert.equal(submissions.length, before + 1, `${type}: server receives unvalidated form`);
            if (!authorized) assert.equal(Object.hasOwn(submissions.at(-1), 'flag_overrides'), false);
        }
    }
    stage = 'flag';
    await page.evaluate(() => window.mountForm('flag', true));
    const before = submissions.length;
    await page.evaluate(() => window.form.submit());
    await page.waitForFunction(() => !window.form.submitting);
    assert.equal(submissions.length, before + 1, 'Flag editor uses server validation');
    assert.deepEqual(errors, []);
    console.log('Flags browser checks passed: hidden controls, authorized tabs, safe payloads, server validation.');
} finally {
    await browser?.close();
    await server.close();
}
