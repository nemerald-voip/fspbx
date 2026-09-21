import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import { parse } from '@vue/compiler-sfc';
import { computed, ref } from 'vue';
import moment from 'moment-timezone';

const flush = () => new Promise(resolve => setImmediate(resolve));
const result = (page = 1, id = 'current', extra = {}) => ({ data: {
    data: [{ uuid: id, user_uuid: id, domain_group_uuid: id, contact_uuid: id, callID: id, can_delete_target: true }],
    current_page: page, last_page: 3, total: 150, links: [], ...extra,
} });

// Exercise the actual Vue setup code with controlled HTTP responses and timers.
function page(name) {
    const source = fs.readFileSync(new URL(`../../resources/js/Pages/${name}.vue`, import.meta.url), 'utf8');
    const script = parse(source).descriptor.scriptSetup.content.replace(/^import[\s\S]*?from\s+['"][^'"]+['"];?/gm, '');
    const gets = [], posts = [], mounts = [], unmounts = [];
    const timers = new Map();
    let timerId = 0;
    const request = (list, url, options) => new Promise((resolve, reject) => list.push({ url, options, resolve, reject }));
    const context = vm.createContext({
        ref, computed, moment, AbortController, URL, console,
        window: { location: { origin: 'https://pbx.test' } },
        trans: key => key,
        registerLicense: () => {},
        usePage: () => ({ props: { auth: { can: {} } } }),
        defineProps: () => ({
            pagination: { per_page: 50 }, permissions: {},
            startPeriod: '2026-09-21T00:00:00Z', endPeriod: '2026-09-21T23:59:59Z', timezone: 'UTC',
            routes: { data_route: '/api/data', select_all: '/api/select-all', bulk_delete: '/api/delete', delete: '/api/delete', destroy_organization: '/api/delete', update_organization: '/api/update', generate: '/api/generate' },
        }),
        onMounted: callback => mounts.push(callback),
        onUnmounted: callback => unmounts.push(callback),
        setTimeout: (callback, delay) => { timers.set(++timerId, { callback, delay }); return timerId; },
        clearTimeout: id => timers.delete(id),
        axios: {
            get: (url, options) => request(gets, url, options),
            post: (url, options) => request(posts, url, options),
            put: (url, options) => request(posts, url, options),
            delete: (url, options) => request(posts, url, options),
            isCancel: error => error.code === 'ERR_CANCELED',
        },
    });
    const extra = name === 'SansayActiveCalls'
        ? 'toggleRefreshing, handleUpdateServerFilter, handleRefreshButtonClick,'
        : name === 'SansayRegistrations' ? 'handleSort, handleShowGlobal, handleShowLocal, handleUpdateServerFilter,'
        : name === 'UserLogs' ? 'handleShowGlobal, handleShowLocal, handleUpdateDateRange,'
        : name === 'ActivityLog' ? 'handleSortRequest, handleShowGlobal, handleShowLocal, propertyChanges,'
        : name === 'Reports' ? 'handleReportRequest, generatingReport,'
        : 'handleSortRequest,';
    const state = vm.runInContext(`${script}\n;({
        data, loading, filterData, notificationMessages, getData, handleSearchButtonClick,
        currentPage: typeof currentPage === 'undefined' ? null : currentPage,
        selectedItems: typeof selectedItems === 'undefined' ? null : selectedItems,
        selectPageItems: typeof selectPageItems === 'undefined' ? null : selectPageItems,
        refreshData: typeof refreshData === 'undefined' ? null : refreshData,
        handlePageSizeChange: typeof handlePageSizeChange === 'undefined' ? null : handlePageSizeChange,
        renderRequestedPage: typeof renderRequestedPage === 'undefined' ? null : renderRequestedPage,
        handleSelectAll: typeof handleSelectAll === 'undefined' ? null : handleSelectAll,
        handleSelectPageItems: typeof handleSelectPageItems === 'undefined' ? null : handleSelectPageItems,
        executeBulkDelete: typeof executeBulkDelete === 'undefined' ? null : executeBulkDelete,
        executeSingleDelete: typeof executeSingleDelete === 'undefined' ? null : executeSingleDelete,
        ${extra}
        ${name === 'Users' ? 'hasDirectories, selectableTotal,' : ''}
        ${name === 'ProFeatures' ? 'itemOptions, handleUpdateRequest, handleDeactivateRequest, handleInstallRequest, handleUninstallRequest,' : ''}
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

for (const name of ['Users', 'BusinessHours', 'SansayActiveCalls', 'RingotelAppSettings', 'SansayRegistrations', 'UserLogs', 'ProFeatures', 'DomainGroups', 'SpeedDial', 'WhitelistedNumbers', 'ActivityLog']) {
    test(`${name}: initial load, pagination, search and page size use axios`, async () => {
        const p = page(name);
        assert.equal(p.gets.length, 1);
        assert.equal(p.gets[0].url, '/api/data');
        p.gets[0].resolve(result());
        await flush();
        assert.equal(p.state.loading.value, false);
        p.state.renderRequestedPage('/api/data?page=3');
        assert.equal(p.gets[1].options.params.page, 3);
        p.gets[1].resolve(result(3));
        await flush();
        p.state.refreshData();
        assert.equal(p.gets[2].options.params.page, 3);
        p.state.filterData.value.search = 'Alice';
        p.state.handleSearchButtonClick();
        assert.equal(p.gets.at(-1).options.params.page, 1);
        assert.equal(p.gets.at(-1).options.params.filter.search, 'Alice');
        p.state.handlePageSizeChange(100);
        assert.equal(p.gets.at(-1).options.params.per_page, 100);
        if (p.state.handleSortRequest) {
            p.state.handleSortRequest('description');
            p.state.handleSortRequest('description');
            assert.equal(p.gets.at(-1).options.params.sort, '-description');
        }
        if (p.state.handleSelectAll) {
            p.state.handleSelectAll();
            const payload = p.posts[0].options;
            assert.equal((name === 'Users' ? payload : payload.filter).search, 'Alice');
        }
        p.unmount();
    });

    test(`${name}: stale responses and unmounted requests cannot overwrite rows`, async () => {
        const p = page(name);
        p.state.handleSearchButtonClick();
        assert.equal(p.gets[0].options.signal.aborted, true);
        p.gets[0].resolve(result(1, 'stale'));
        await flush();
        assert.equal(p.state.data.value.data.length, 0);
        assert.equal(p.state.loading.value, true);
        p.gets[1].resolve(result(1, 'fresh'));
        await flush();
        assert.equal(p.state.data.value.data[0].uuid, 'fresh');
        p.state.refreshData();
        p.unmount();
        assert.equal(p.gets[2].options.signal.aborted, true);
        p.gets[2].resolve(result(1, 'after-unmount'));
        await flush();
        assert.equal(p.state.data.value.data[0].uuid, 'fresh');
    });

    if (!['UserLogs', 'ProFeatures', 'ActivityLog'].includes(name)) test(`${name}: deletion refreshes the current page and recovers a removed last page`, async () => {
        const p = page(name);
        p.gets[0].resolve(result(3));
        await flush();
        p.state.selectedItems.value = ['current'];
        if (name === 'RingotelAppSettings') p.state.executeSingleDelete('account');
        else p.state.executeBulkDelete();
        assert.equal(p.posts[0].url, '/api/delete');
        p.posts[0].resolve({ data: { messages: { success: ['OK'] } } });
        await flush();
        assert.equal(p.gets[1].options.params.page, 3);
        p.gets[1].resolve(result(3, 'empty', { data: [], last_page: 2 }));
        await flush();
        assert.equal(p.gets[2].options.params.page, 2);
        p.gets[2].resolve(result(2));
        await flush();
        assert.equal(p.state.currentPage.value, 2);
        assert.equal(p.state.loading.value, false);
        assert.equal(p.state.selectedItems.value.length, 0);
        p.unmount();
    });

    test(`${name}: failed data loads release the spinner and display the error`, async () => {
        const p = page(name);
        p.gets[0].reject(new Error('Network error'));
        await flush();
        assert.equal(p.state.loading.value, false);
        assert.ok(JSON.stringify(p.state.notificationMessages.value).includes('Network error'));
        p.unmount();
    });
}

for (const action of ['handleUpdateRequest', 'handleDeactivateRequest', 'handleInstallRequest', 'handleUninstallRequest']) {
    test(`ProFeatures: ${action} refreshes the current page through axios`, async () => {
        const p = page('ProFeatures');
        p.gets[0].resolve(result(3));
        await flush();
        p.state.itemOptions.value = { item: { uuid: 'feature' } };
        p.state[action]({ update_route: '/api/update', deactivate_route: '/api/deactivate', install_route: '/api/install', uninstall_route: '/api/uninstall' });
        p.posts[0].resolve({ data: { messages: { success: ['OK'] } } });
        await flush();
        assert.equal(p.gets[1].options.params.page, 3);
        assert.equal(p.gets[1].url, '/api/data');
        p.unmount();
    });
}

for (const name of ['ProFeatures', 'DomainGroups', 'SpeedDial', 'WhitelistedNumbers']) {
    test(`${name}: selection uses loaded rows and ignores a stale select-all response`, async () => {
        const p = page(name);
        p.gets[0].resolve(result(1, 'visible'));
        await flush();
        p.state.selectPageItems.value = true;
        p.state.handleSelectPageItems();
        assert.deepEqual(Array.from(p.state.selectedItems.value), ['visible']);
        p.state.handleSelectAll();
        p.state.handleSearchButtonClick();
        p.posts[0].resolve({ data: { items: ['old-filter'] } });
        await flush();
        assert.equal(p.state.selectedItems.value.length, 0);
        p.unmount();
    });
}

test('Whitelisted Numbers: single deletion uses axios and preserves the current page', async () => {
    const p = page('WhitelistedNumbers');
    p.gets[0].resolve(result(3));
    await flush();
    p.state.executeSingleDelete('/api/whitelisted-numbers/number');
    assert.equal(p.posts[0].url, '/api/whitelisted-numbers/number');
    p.posts[0].resolve({ data: { messages: { success: ['Deleted'] } } });
    await flush();
    assert.equal(p.gets[1].options.params.page, 3);
    p.unmount();
});

test('Activity Log: scope changes clear old account rows and use boolean API filters', async () => {
    const p = page('ActivityLog');
    p.gets[0].resolve(result(3));
    await flush();
    p.state.handleShowGlobal();
    assert.equal(p.gets[1].options.params.page, 1);
    assert.equal(p.gets[1].options.params.filter.showGlobal, true);
    assert.equal(p.state.data.value.data.length, 0);
    p.state.handleShowLocal();
    assert.equal(p.gets[1].options.signal.aborted, true);
    assert.equal(p.gets[2].options.params.filter.showGlobal, false);
    const changes = p.state.propertyChanges({ description: 'updated', properties: { old: { description: null, unchanged: 1 }, attributes: { description: '<b>customer text</b>', unchanged: 1 } } });
    assert.equal(changes.length, 1);
    assert.equal(changes[0].old, 'Empty');
    assert.equal(changes[0].new, '<b>customer text</b>');
    const source = fs.readFileSync(new URL('../../resources/js/Pages/ActivityLog.vue', import.meta.url), 'utf8');
    assert.equal(source.includes('v-html'), false, 'Audit values must be rendered through escaped Vue text');
    p.unmount();
});

test('Reports: initial load, search, stale results and unmount use axios', async () => {
    const p = page('Reports');
    assert.equal(p.gets[0].url, '/api/data');
    p.state.filterData.value.search = 'Ringotel';
    p.state.handleSearchButtonClick();
    assert.equal(p.gets[0].options.signal.aborted, true);
    assert.equal(p.gets[1].options.params.filter.search, 'Ringotel');
    p.gets[0].resolve({ data: [{ id: 'stale' }] });
    await flush();
    assert.equal(p.state.data.value.length, 0);
    p.gets[1].resolve({ data: [{ id: 'stale-ringotel-users' }] });
    await flush();
    assert.equal(p.state.data.value[0].id, 'stale-ringotel-users');
    assert.equal(p.state.loading.value, false);
    p.state.handleSearchButtonClick();
    p.unmount();
    assert.equal(p.gets[2].options.signal.aborted, true);
});

test('Reports: generation sends a stable ID, prevents duplicate submits and releases pending state on errors', async () => {
    const p = page('Reports');
    p.gets[0].reject(new Error('Data unavailable'));
    await flush();
    assert.equal(p.state.loading.value, false);
    assert.ok(JSON.stringify(p.state.notificationMessages.value).includes('Data unavailable'));
    p.state.handleReportRequest('stale-ringotel-users');
    p.state.handleReportRequest('stale-ringotel-users');
    assert.equal(p.posts.length, 1);
    assert.equal(p.posts[0].url, '/api/generate');
    assert.equal(p.posts[0].options.reportId, 'stale-ringotel-users');
    p.posts[0].reject(new Error('Generation failed'));
    await flush();
    assert.equal(p.state.generatingReport.value, null);
    assert.ok(JSON.stringify(p.state.notificationMessages.value).includes('Generation failed'));
    p.unmount();
});

test('Users: directory metadata comes from the data response and protected users cannot be selected', async () => {
    const p = page('Users');
    p.state.filterData.value.source = 'directory';
    p.state.handleSearchButtonClick();
    assert.equal(p.gets[1].options.params.filter.source, 'directory');
    p.gets[1].resolve(result(1, 'local', {
        has_directories: true, selectable_total: 1,
        data: [{ user_uuid: 'local', can_delete_target: true }, { user_uuid: 'ldap', can_delete_target: false }],
    }));
    await flush();
    assert.equal(p.state.hasDirectories.value, true);
    assert.equal(p.state.selectableTotal.value, 1);
    p.state.selectPageItems.value = true;
    p.state.handleSelectPageItems();
    assert.deepEqual(Array.from(p.state.selectedItems.value), ['local']);
    p.unmount();
});

test('Sansay: switching servers resets selection and polling waits for completion on the current page', async () => {
    const p = page('SansayActiveCalls');
    p.gets[0].resolve(result(3));
    await flush();
    p.state.toggleRefreshing();
    assert.equal(p.gets[1].options.params.page, 3);
    assert.equal(p.state.loading.value, false);
    assert.equal(p.timers.size, 0);
    p.gets[1].reject(new Error('Network error'));
    await flush();
    p.fire(7000);
    assert.equal(p.gets[2].options.params.page, 3);
    p.state.selectedItems.value = ['old-server-call'];
    p.state.handleUpdateServerFilter({ value: 'server2' });
    assert.equal(p.state.selectedItems.value.length, 0);
    assert.equal(p.state.data.value.data.length, 0);
    assert.equal(p.gets[2].options.signal.aborted, true);
    assert.equal(p.gets[3].options.params.page, 1);
    assert.equal(p.gets[3].options.params.filter.server, 'server2');
    p.state.toggleRefreshing();
    p.gets[3].resolve(result());
    await flush();
    assert.equal(p.timers.size, 0);
    p.state.handleSelectAll();
    p.state.handleUpdateServerFilter({ value: 'NULL' });
    assert.equal(p.gets.at(-1).options.params.filter.server, null);
    p.posts[0].resolve({ data: { items: ['old-server-call'] } });
    await flush();
    assert.equal(p.state.selectedItems.value.length, 0);
    p.unmount();
});


test('User Logs: date changes and global scope travel to the API without a timezone filter', async () => {
    const p = page('UserLogs');
    assert.equal(p.gets[0].options.params.sort, '-timestamp');
    assert.equal(Object.hasOwn(p.gets[0].options.params.filter, 'timezone'), false);
    const dates = ['2026-09-20T04:00:00Z', '2026-09-21T03:59:59Z'];
    p.state.handleUpdateDateRange(dates);
    p.state.handleShowGlobal();
    assert.equal(p.gets.at(-1).options.params.filter.showGlobal, true);
    assert.deepEqual(Array.from(p.gets.at(-1).options.params.filter.dateRange), dates);
    p.state.handleSelectAll();
    assert.deepEqual(Array.from(p.posts[0].options.filter.dateRange), dates);
    p.state.handleShowLocal();
    assert.equal(p.gets.at(-1).options.params.filter.showGlobal, false);
    p.unmount();
});

test('Sansay Registrations: sorting, server changes and global scope use the current API filters', async () => {
    const p = page('SansayRegistrations');
    p.state.handleSort({ field: 'userPort', order: 'desc' });
    assert.equal(p.gets.at(-1).options.params.sort, '-userPort');
    p.state.handleShowGlobal();
    assert.equal(p.gets.at(-1).options.params.filter.showGlobal, true);
    p.state.handleUpdateServerFilter({ value: 'server2' });
    assert.equal(p.gets.at(-1).options.params.filter.server, 'server2');
    p.state.handleSelectAll();
    assert.equal(p.posts[0].options.filter.server, 'server2');
    p.state.handleShowLocal();
    assert.equal(p.gets.at(-1).options.params.filter.showGlobal, false);
    p.posts[0].resolve({ data: { items: ['stale-selection'] } });
    await flush();
    assert.equal(p.state.selectedItems.value.length, 0);
    p.unmount();
});
