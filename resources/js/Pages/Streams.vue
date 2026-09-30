<template>
    <MainLayout />
    <div class="m-3">
        <DataTable @search-action="search" @reset-filters="reset">
            <template #title>{{ $t('Streams') }}</template>
            <template #subtitle>
                <span class="flex flex-wrap items-center gap-2" role="status" aria-live="polite">
                    <span class="font-medium">{{ $t('Stream playback on this server') }}:</span>
                    <Badge :text="moduleLabel" v-bind="moduleBadge" />
                    <button type="button" :disabled="checkingModule" @click="refreshModuleStatus"
                        class="rounded px-2 py-1 text-sm font-medium text-indigo-600 hover:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50">
                        {{ $t('Refresh status') }}
                    </button>
                    <a v-if="routes.modules" :href="routes.modules" class="font-medium text-indigo-600 hover:underline">{{ $t('FreeSWITCH Modules') }}</a>
                </span>
                <span v-if="!checkingModule && moduleStatus === 'stopped'" class="mt-2 block text-amber-800">
                    {{ $t('Network streams cannot play until an administrator starts mod_shout. Enable automatic loading on the Modules page so it starts after a restart.') }}
                </span>
                <span v-else-if="!checkingModule && moduleStatus === 'unknown'" class="mt-2 block text-amber-800">
                    {{ $t('Could not verify mod_shout. Check the FreeSWITCH event socket connection and refresh the status.') }}
                </span>
            </template>
            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input v-model="filters.search" type="search" :aria-label="$t('Search streams')" :placeholder="$t('Search')"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                        @keydown.enter="search" />
                </div>
            </template>
            <template #action>
                <button v-if="permissions.create" type="button" @click="openForm()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    {{ $t('Create') }}
                </button>
            </template>
            <template #navigation>
                <Paginator v-bind="paginationProps" @pagination-change-page="navigate" :bulk-actions="bulkActions"
                    @bulk-action="requestBulkAction" :has-selected-items="selected.length > 0 && !busy" />
            </template>
            <template #table-header>
                <TableColumnHeader v-for="(column, index) in columns" :key="column.key"
                    :sortable="false" :class="`${index === 0 ? 'px-4' : 'px-2'} py-3.5 text-left text-sm font-semibold text-gray-900`">
                    <div class="flex items-center whitespace-nowrap">
                        <input v-if="index === 0 && hasActions" type="checkbox" :checked="pageSelected" @change="selectPage($event.target.checked)"
                            :aria-label="$t('Select this page')" class="mr-4 h-4 w-4 rounded border-gray-300 text-indigo-600" />
                        <button type="button" class="flex items-center gap-2" @click="sortBy(column.key)">
                            {{ $t(column.label) }}
                            <ChevronUpIcon v-if="sort.name === column.key && sort.order === 'asc'" class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sort.name === column.key" class="h-4 w-4 text-gray-500" />
                        </button>
                    </div>
                </TableColumnHeader>
                <TableColumnHeader :sortable="false" :header="$t('Account')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader :sortable="false" header="" class="px-2 py-3.5" />
            </template>
            <template v-if="pageSelected" #current-selection>
                <tbody><tr><td colspan="6" class="p-2 text-center text-sm">
                    {{ $t(':count items are selected.', { count: selected.length }) }}
                    <button v-if="selected.length !== data.total" type="button" class="rounded px-2 py-2 text-blue-500 hover:bg-blue-100" @click="selectAll">
                        {{ $t('Select all :total items', { total: data.total }) }}
                    </button>
                    <button type="button" class="rounded px-2 py-2 text-blue-500 hover:bg-blue-100" @click="selected = []">{{ $t('Clear selection') }}</button>
                </td></tr></tbody>
            </template>
            <template #table-body>
                <tr v-for="row in data.data" :key="row.stream_uuid">
                    <TableField class="px-4 py-2 text-sm text-gray-500">
                        <div class="flex items-center">
                            <input v-if="hasActions" v-model="selected" type="checkbox" :value="row.stream_uuid"
                                :aria-label="$t('Select :name', { name: row.stream_name })" class="mr-4 h-4 w-4 rounded border-gray-300 text-indigo-600" />
                            <button v-if="permissions.update && row.can_manage" type="button" class="max-w-xs truncate text-left hover:text-gray-900"
                                :title="row.stream_name" @click="openForm(row.stream_uuid)">{{ row.stream_name }}</button>
                            <span v-else class="max-w-xs truncate" :title="row.stream_name">{{ row.stream_name }}</span>
                        </div>
                    </TableField>
                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        <span class="block max-w-xs truncate" :title="row.stream_location">{{ row.stream_location }}</span>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm">
                        <button v-if="permissions.update && row.can_manage" type="button" :disabled="busy"
                            :aria-label="row.stream_enabled === 'true' ? $t('Disable stream') : $t('Enable stream')"
                            @click="execute(row.stream_enabled === 'true' ? 'disable' : 'enable', [row.stream_uuid], true)">
                            <Badge :text="row.stream_enabled === 'true' ? $t('Enabled') : $t('Disabled')" v-bind="badgeProps(row)" />
                        </button>
                        <Badge v-else :text="row.stream_enabled === 'true' ? $t('Enabled') : $t('Disabled')" v-bind="badgeProps(row)" />
                    </TableField>
                    <TableField class="px-2 py-2 text-sm text-gray-500"><span class="block max-w-xs truncate" :title="row.stream_description">{{ row.stream_description }}</span></TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.domain_uuid ? $t('Current account') : $t('Global')" />
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center justify-end">
                                <button v-if="previewUrl(row)" type="button" :class="iconButton" :title="$t('Listen in browser')" :aria-label="$t('Listen in browser')" @click="preview = row; previewError = false"><PlayCircleIcon class="h-5 w-5" /></button>
                                <button v-if="permissions.update && row.can_manage" type="button" :class="iconButton" :title="$t('Edit')" :aria-label="$t('Edit')" @click="openForm(row.stream_uuid)"><PencilSquareIcon class="h-5 w-5" /></button>
                                <button v-if="permissions.create" type="button" :class="iconButton" :disabled="busy" :title="$t('Copy')" :aria-label="$t('Copy')" @click="confirm('copy', [row.stream_uuid])"><DocumentDuplicateIcon class="h-5 w-5" /></button>
                                <button v-if="permissions.destroy && row.can_manage" type="button" :class="iconButton" :disabled="busy" :title="$t('Delete')" :aria-label="$t('Delete')" @click="confirm('delete', [row.stream_uuid])"><TrashIcon class="h-5 w-5" /></button>
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>
            <template #empty>
                <div v-if="!loading && data.data.length === 0" class="my-5 text-center">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ $t('No results found') }}</h3>
                </div>
            </template>
            <template #loading><Loading :show="loading" /></template>
            <template #footer>
                <Paginator v-bind="paginationProps" :page-size="perPage" :page-size-options="pagination.per_page_options"
                    :show-page-size-selector="true" @pagination-change-page="navigate" @page-size-change="changePageSize" />
            </template>
        </DataTable>
    </div>
    <StreamForm :show="showForm" :loading="loadingForm" :options="itemOptions" :header="formHeader"
        @close="closeForm" @success="notify" @error="handleError" @refresh-data="fetchData(currentPage)" />
    <ConfirmationModal :show="!!pending" :loading="busy" :header="$t('Are you sure?')" :text="confirmationText"
        :confirm-button-label="pending ? actionLabels[pending.action] : ''" :cancel-button-label="$t('Cancel')"
        @close="pending = null" @confirm="confirmPending" />
    <AddEditItemModal :show="!!preview" :header="$t('Listen in browser')" @close="preview = null">
        <template #modal-body>
            <template v-if="preview">
                <p class="mb-3 break-words text-sm font-semibold text-gray-900">{{ preview.stream_name }}</p>
                <audio :key="preview.stream_uuid" :src="previewUrl(preview)" controls preload="none" class="w-full" @error="previewError = true" />
                <p v-if="previewError" role="alert" class="mt-3 text-sm text-red-700">{{ $t('The browser could not play this stream. Check the endpoint and browser network restrictions.') }}</p>
                <p class="mt-3 text-sm text-gray-600">{{ $t('This connects from your browser, not FreeSWITCH. Browsers may block HTTP audio on an HTTPS page. Verify server playback with a test call on hold.') }}</p>
            </template>
        </template>
    </AddEditItemModal>
    <Notification :show="notification.show" :type="notification.type" :messages="notification.messages" @update:show="notification.show = false" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { trans } from '@i18n';
import MainLayout from '../Layouts/MainLayout.vue';
import DataTable from './components/general/DataTable.vue';
import TableColumnHeader from './components/general/TableColumnHeader.vue';
import TableField from './components/general/TableField.vue';
import Paginator from './components/general/Paginator.vue';
import Loading from './components/general/Loading.vue';
import Badge from './components/general/Badge.vue';
import Notification from './components/notifications/Notification.vue';
import ConfirmationModal from './components/modal/ConfirmationModal.vue';
import AddEditItemModal from './components/modal/AddEditItemModal.vue';
import StreamForm from './components/forms/StreamForm.vue';
import { ChevronDownIcon, ChevronUpIcon, MagnifyingGlassIcon, PencilSquareIcon, TrashIcon, DocumentDuplicateIcon, PlayCircleIcon } from '@heroicons/vue/24/solid';

const props = defineProps({ routes: Object, permissions: Object, pagination: Object });
const columns = computed(() => [{ key: 'stream_name', label: trans('Name') }, { key: 'stream_location', label: trans('Location') }, { key: 'stream_enabled', label: trans('Enabled') }, { key: 'stream_description', label: trans('Description') }]);
const iconButton = 'flex h-9 w-9 items-center justify-center rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50';
const filters = ref({ search: '' });
const appliedFilters = ref({ search: '' });
const sort = ref({ name: 'stream_name', order: 'asc' });
const data = ref({ data: [], total: 0, links: [] });
const currentPage = ref(1);
const perPage = ref(props.pagination.per_page);
const loading = ref(false);
const busy = ref(false);
const selected = ref([]);
const pending = ref(null);
const notification = ref({ show: false, type: null, messages: null });
const showForm = ref(false);
const loadingForm = ref(false);
const itemOptions = ref({ item: {}, routes: {} });
const editing = ref(false);
const preview = ref(null);
const previewError = ref(false);
const moduleStatus = ref('unknown');
const checkingModule = ref(true);
const moduleLabel = computed(() => checkingModule.value ? trans('mod_shout: Checking…')
    : moduleStatus.value === 'running' ? trans('mod_shout: Running')
    : moduleStatus.value === 'stopped' ? trans('mod_shout: Not loaded') : trans('mod_shout: Unknown'));
const moduleBadge = computed(() => checkingModule.value
    ? { backgroundColor: 'bg-gray-50', textColor: 'text-gray-700', ringColor: 'ring-gray-600/20' }
    : moduleStatus.value === 'running'
        ? { backgroundColor: 'bg-green-50', textColor: 'text-green-700', ringColor: 'ring-green-600/20' }
        : { backgroundColor: 'bg-amber-50', textColor: 'text-amber-800', ringColor: 'ring-amber-600/20' });
const refreshModuleStatus = async () => {
    checkingModule.value = true;
    try {
        const response = await axios.get(props.routes.module_status);
        moduleStatus.value = ['running', 'stopped'].includes(response.data.status) ? response.data.status : 'unknown';
    } catch {
        moduleStatus.value = 'unknown';
    } finally {
        checkingModule.value = false;
    }
};
let listRequest = 0;
let formRequest = 0;
const hasActions = computed(() => props.permissions.create || props.permissions.update || props.permissions.destroy);
const pageSelected = computed(() => data.value.data.length > 0 && data.value.data.every(row => selected.value.includes(row.stream_uuid)));
const paginationProps = computed(() => ({ previous: data.value.prev_page_url, next: data.value.next_page_url, from: data.value.from, to: data.value.to, total: data.value.total, currentPage: data.value.current_page, lastPage: data.value.last_page, links: data.value.links }));
const actionLabels = computed(() => ({ copy: trans('Copy'), delete: trans('Delete'), enable: trans('Enable'), disable: trans('Disable') }));
const bulkActions = computed(() => [
    ...(props.permissions.create ? [{ id: 'copy', label: trans('Copy'), icon: 'DocumentDuplicateIcon' }] : []),
    ...(props.permissions.update ? [{ id: 'enable', label: trans('Enable'), icon: 'CheckCircleIcon' }, { id: 'disable', label: trans('Disable'), icon: 'XCircleIcon' }] : []),
    ...(props.permissions.destroy ? [{ id: 'delete', label: trans('Delete'), icon: 'TrashIcon' }] : []),
]);
const formHeader = computed(() => editing.value ? trans('Update Stream') : trans('Create Stream'));
const confirmationText = computed(() => pending.value?.action === 'delete'
    ? trans('Delete the selected streams? Locations already saved in destinations will remain in use.')
    : pending.value?.action === 'copy' ? trans('Copy the selected streams to the current account?')
    : trans('Change availability of the selected streams? Existing destination settings will remain in use.'));
const notify = (type, messages) => notification.value = { show: true, type, messages };
const handleError = (error) => notify('error', error.response?.data?.errors || error.response?.data?.messages || { error: [error.response?.data?.message || error.message || trans('Request failed.')] });
const fetchData = async (page = 1, silent = false) => {
    const requestId = ++listRequest;
    if (!silent) loading.value = true;
    try {
        const res = await axios.get(props.routes.data_route, { params: { filter: appliedFilters.value, page, per_page: perPage.value, sort: `${sort.value.order === 'desc' ? '-' : ''}${sort.value.name}` } });
        if (requestId !== listRequest) return;
        if (!res.data.data.length && page > 1) return fetchData(Math.max(1, res.data.last_page), silent);
        data.value = res.data;
        currentPage.value = res.data.current_page;
    } catch (err) { if (requestId === listRequest) handleError(err); }
    finally { if (requestId === listRequest) loading.value = false; }
};
const search = () => { appliedFilters.value = { ...filters.value }; selected.value = []; fetchData(1); };
const reset = () => { filters.value = { search: '' }; search(); };
const sortBy = (name) => { sort.value = { name, order: sort.value.name === name && sort.value.order === 'asc' ? 'desc' : 'asc' }; fetchData(1); };
const navigate = (url) => { if (url) fetchData(Number(new URL(url, window.location.origin).searchParams.get('page')) || 1); };
const changePageSize = (size) => { perPage.value = size; selected.value = []; fetchData(1); };
const selectPage = (checked) => { selected.value = checked ? data.value.data.map(row => row.stream_uuid) : []; };
const selectAll = async () => {
    const snapshot = JSON.stringify(appliedFilters.value);
    try {
        const res = await axios.post(props.routes.select_all, { filter: appliedFilters.value });
        if (snapshot === JSON.stringify(appliedFilters.value)) selected.value = res.data.items;
    } catch (err) { handleError(err); }
};
const openForm = async (uuid = null) => {
    const requestId = ++formRequest;
    editing.value = !!uuid;
    itemOptions.value = { item: {}, routes: {} };
    showForm.value = true;
    loadingForm.value = true;
    try {
        const res = await axios.post(props.routes.item_options, uuid ? { itemUuid: uuid } : {});
        if (requestId === formRequest) itemOptions.value = res.data;
    } catch (err) { if (requestId === formRequest) { closeForm(); handleError(err); } }
    finally { if (requestId === formRequest) loadingForm.value = false; }
};
const closeForm = () => { ++formRequest; showForm.value = false; };
const confirm = (action, items) => { if (!busy.value) pending.value = { action, items: [...items] }; };
const requestBulkAction = (action) => { if (selected.value.length) confirm(action, selected.value); };
const confirmPending = () => { if (pending.value) execute(pending.value.action, pending.value.items); };
const execute = async (action, items, silent = false) => {
    if (busy.value) return;
    busy.value = true;
    try {
        const res = await axios.post(props.routes.bulk_action, { action, items });
        pending.value = null;
        if (!silent) { selected.value = []; notify('success', res.data.messages); }
        await fetchData(currentPage.value, silent);
    } catch (err) { pending.value = null; handleError(err); }
    finally { busy.value = false; }
};
const badgeProps = (row) => row.stream_enabled === 'true'
    ? { backgroundColor: 'bg-green-50', textColor: 'text-green-700', ringColor: 'ring-green-600/20' }
    : { backgroundColor: 'bg-gray-50', textColor: 'text-gray-700', ringColor: 'ring-gray-600/20' };
const previewUrl = (row) => {
    if (!/^shouts?:\/\//.test(row.stream_location || '')) return null;
    const value = row.stream_location.replace(/^shout(s?):\/\//, 'http$1://');
    try { const url = new URL(value); return url.username || url.password ? null : value; } catch { return null; }
};
onMounted(() => { fetchData(); refreshModuleStatus(); });
</script>
