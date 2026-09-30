import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import { parse } from '@vue/compiler-sfc';
import { computed, ref } from 'vue';

const source = fs.readFileSync(new URL('../../resources/js/Pages/ActiveCalls.vue', import.meta.url), 'utf8');
const script = parse(source).descriptor.scriptSetup.content.replace(/^import .+$/gm, '');
const flush = () => new Promise(resolve => setImmediate(resolve));
const result = (page = 1, uuid = 'current') => ({ data: { data: [{ uuid }], current_page: page, total: 150, links: [] } });

// Run the actual page setup with controlled HTTP responses and timers, without a live PBX.
function page() {
    const gets = [], posts = [], mounts = [], unmounts = [];
    const timers = new Map();
    let timerId = 0;
    const request = (list, url, options) => new Promise((resolve, reject) => list.push({ url, options, resolve, reject }));
    const context = vm.createContext({
        ref, computed, AbortController, URL, console,
        window: { location: { origin: 'https://pbx.test' } },
        trans: key => key,
        registerLicense: () => {},
        defineProps: () => ({
            showGlobal: false,
            pagination: { per_page: 50 },
            permissions: { hangup: true, view_global: true },
            routes: { data_route: '/api/active-calls/data', select_all: '/api/active-calls/select-all', action: '/api/active-calls/action' },
        }),
        onMounted: callback => mounts.push(callback),
        onUnmounted: callback => unmounts.push(callback),
        setTimeout: (callback, delay) => { timers.set(++timerId, { callback, delay }); return timerId; },
        clearTimeout: id => timers.delete(id),
        setInterval: () => 9999,
        clearInterval: () => {},
        axios: {
            get: (url, options) => request(gets, url, options),
            post: (url, options) => request(posts, url, options),
            isCancel: error => error.code === 'ERR_CANCELED',
        },
    });
    const state = vm.runInContext(`${script}\n;({
        data, loading, currentPage, filterData, selectedItems, selectPageItems, confirmAction,
        notificationShow, notificationMessages, getData, toggleRefreshing, handleSearchButtonClick,
        handleSortRequest, handlePageSizeChange, handleShowGlobal, handleShowLocal,
        handleRefreshButtonClick, renderRequestedPage, handleSelectAll, handleSelectPageItems,
        handleSingleItemActionRequest, handleBulkActionRequest,
    });`, context);
    mounts.forEach(callback => callback());
    return {
        state, gets, posts, timers,
        unmount: () => unmounts.forEach(callback => callback()),
        fire(delay) {
            const entry = [...timers].find(([, timer]) => timer.delay === delay);
            assert.ok(entry, `Expected a ${delay}ms timer`);
            timers.delete(entry[0]);
            entry[1].callback();
        },
    };
}

test('initial load, pagination and manual refresh use the data endpoint and preserve the current page', async () => {
    const p = page();
    assert.equal(p.gets.length, 1);
    assert.equal(p.gets[0].url, '/api/active-calls/data');
    assert.equal(p.gets[0].options.params.sort, '-created_epoch');
    p.gets[0].resolve(result());
    await flush();
    assert.equal(p.state.loading.value, false);

    p.state.renderRequestedPage('/api/active-calls/data?page=3');
    assert.equal(p.gets[1].options.params.page, 3);
    p.gets[1].resolve(result(3));
    await flush();
    p.state.handleRefreshButtonClick();
    assert.equal(p.gets[2].options.params.page, 3);
    p.gets[2].resolve(result(2)); // The last page disappeared while calls ended.
    await flush();
    assert.equal(p.state.currentPage.value, 2);
    p.state.selectPageItems.value = true;
    p.state.handleSelectPageItems();
    assert.equal(p.state.selectedItems.value[0], 'current');
    p.unmount();
});

test('search, sorting, account scope and page size reset pagination and select-all sends the search filter', async () => {
    const p = page();
    p.gets[0].resolve(result(3));
    await flush();
    p.state.filterData.value.search = 'Alice';
    p.state.handleSearchButtonClick();
    assert.equal(p.gets.at(-1).options.params.page, 1);
    assert.equal(p.gets.at(-1).options.params.filter.search, 'Alice');
    p.state.handleSortRequest('duration');
    assert.equal(p.gets.at(-1).options.params.sort, 'duration');
    p.state.handleSortRequest('duration');
    assert.equal(p.gets.at(-1).options.params.sort, '-duration');
    p.state.handleShowGlobal();
    assert.equal(p.gets.at(-1).options.params.filter.showGlobal, true);
    p.state.handleShowLocal();
    assert.equal(p.gets.at(-1).options.params.filter.showGlobal, false);
    p.state.handlePageSizeChange(100);
    assert.equal(p.gets.at(-1).options.params.per_page, 100);
    p.state.handleSelectAll();
    assert.equal(p.posts[0].options.filter.search, 'Alice');
    p.unmount();
});

test('older responses cannot overwrite a newer search or clear its loading state', async () => {
    const p = page();
    p.state.filterData.value.search = 'new';
    p.state.handleSearchButtonClick();
    assert.equal(p.gets[0].options.signal.aborted, true);
    p.gets[0].resolve(result(1, 'stale'));
    await flush();
    assert.equal(p.state.data.value.data.length, 0);
    assert.equal(p.state.loading.value, true);
    p.gets[1].resolve(result(1, 'fresh'));
    await flush();
    assert.equal(p.state.data.value.data[0].uuid, 'fresh');
    assert.equal(p.state.loading.value, false);
    p.unmount();
});

test('polling waits for completion, keeps the page, retries errors and stops when disabled', async () => {
    const p = page();
    p.gets[0].resolve(result(3));
    await flush();
    p.state.toggleRefreshing();
    assert.equal(p.gets[1].options.params.page, 3);
    assert.equal(p.state.loading.value, false);
    assert.equal(p.timers.size, 0);
    p.gets[1].reject(new Error('Network error'));
    await flush();
    assert.equal(p.state.notificationMessages.value.request[0], 'Network error');
    assert.equal(p.timers.size, 1);
    p.fire(5000);
    assert.equal(p.gets[2].options.params.page, 3);
    p.state.toggleRefreshing();
    p.gets[2].resolve(result(3));
    await flush();
    assert.equal(p.timers.size, 0);
    p.unmount();
});

for (const bulk of [false, true]) {
    test(`${bulk ? 'bulk' : 'single'} hangup refreshes the current page even with polling disabled`, async () => {
        const p = page();
        p.gets[0].resolve(result(2));
        await flush();
        if (bulk) {
            p.state.selectedItems.value = ['a', 'b'];
            p.state.handleBulkActionRequest('bulk_end_call');
        } else {
            p.state.handleSingleItemActionRequest('a', 'end_call');
        }
        p.state.confirmAction.value();
        assert.equal(p.posts[0].url, '/api/active-calls/action');
        assert.equal(p.posts[0].options.ids.length, bulk ? 2 : 1);
        p.posts[0].resolve({ data: { messages: { success: ['OK'] } } });
        await flush();
        p.fire(2000);
        assert.equal(p.gets[1].options.params.page, 2);
        p.unmount();
        assert.equal(p.gets[1].options.signal.aborted, true);
        assert.equal(p.timers.size, 0);
    });
}

test('unmount prevents delayed responses from updating the page or restarting polling', async () => {
    const p = page();
    p.state.toggleRefreshing();
    p.unmount();
    assert.equal(p.gets[0].options.signal.aborted, true);
    p.gets[0].resolve(result());
    await flush();
    assert.equal(p.state.data.value.data.length, 0);
    assert.equal(p.timers.size, 0);
});
