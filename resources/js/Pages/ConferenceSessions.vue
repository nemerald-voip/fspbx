<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="fetchData(1)" @reset-filters="resetFilters">
            <template #title>{{ isDetails ? $t('Session Participants') : $t('Conference Sessions') }}</template>
            <template #subtitle>
                {{ $t('Room: :room', { room: room.conference_room_name }) }}
                <span v-if="isDetails" class="ml-3">
                    {{ formatDate(conference_session.start_epoch) }} · {{ conference_session.profile }}
                </span>
            </template>
            <template #action>
                <div class="flex flex-wrap items-center gap-3">
                    <a v-if="isDetails || permissions.rooms" :href="isDetails ? routes.sessions : routes.rooms" class="secondary-button">
                        {{ isDetails ? $t('Back to Sessions') : $t('Back to Rooms') }}
                    </a>
                    <button type="button" class="secondary-button" :disabled="loading" @click="fetchData(data.current_page)">{{ $t('Refresh') }}</button>
                    <template v-if="isDetails">
                        <audio v-if="conference_session.stream_url" controls preload="none" class="h-9 w-64"
                            :src="conference_session.stream_url" :aria-label="$t('Conference recording')" />
                        <a v-if="conference_session.download_url" :href="conference_session.download_url" class="secondary-button">{{ $t('Download') }}</a>
                    </template>
                </div>
            </template>
            <template #filters>
                <div class="mb-2 sm:mr-4">
                    <label for="conference-session-search" class="sr-only">{{ isDetails ? $t('Search participants') : $t('Search profiles') }}</label>
                    <input id="conference-session-search" v-model="search" type="search"
                        class="block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-500 sm:w-72"
                        :placeholder="isDetails ? $t('Search participants') : $t('Search profiles')" @keydown.enter="fetchData(1)" />
                </div>
            </template>
            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :current-page="data.current_page" :last-page="data.last_page" :links="data.links"
                    :page-size="perPage" :page-size-options="pagination.per_page_options" :show-page-size-selector="true"
                    :bulk-actions="bulkActions" :has-selected-items="selectedItems.length > 0"
                    @pagination-change-page="fetchData" @page-size-change="changePageSize" @bulk-action="confirmDelete" />
            </template>
            <template #table-header>
                <TableColumnHeader v-if="canDelete" :sortable="false" class="w-10 px-4 py-3">
                    <input v-model="selectPage" type="checkbox" :aria-label="$t('Select all on this page')"
                        :disabled="loading || !data.data.length" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                </TableColumnHeader>
                <TableColumnHeader v-for="column in columns" :key="column.field" :field="column.field"
                    :header="column.label" :sortable="column.sortable !== false" :sorted-field="sort.field" :sort-order="sort.order"
                    class="whitespace-nowrap px-3 py-3 text-left text-sm font-semibold text-gray-900" @sort="setSort" />
                <TableColumnHeader v-if="!isDetails" :header="$t('Recording')" :sortable="false"
                    class="px-3 py-3 text-left text-sm font-semibold text-gray-900" />
            </template>
            <template #table-body>
                <tr v-if="loading">
                    <td :colspan="columnCount" class="px-4 py-6" role="status">
                        <span class="sr-only">{{ $t('Loading...') }}</span>
                        <div class="space-y-4 animate-pulse motion-reduce:animate-none">
                            <div v-for="n in 3" :key="n" class="h-4 rounded bg-gray-100" />
                        </div>
                    </td>
                </tr>
                <tr v-else-if="loadError">
                    <td :colspan="columnCount" class="px-4 py-8 text-center text-sm text-red-700" role="alert">
                        {{ $t('Unable to load conference history.') }}
                        <button type="button" class="ml-3 underline" @click="fetchData(data.current_page)">{{ $t('Retry') }}</button>
                    </td>
                </tr>
                <tr v-else-if="!data.data.length">
                    <td :colspan="columnCount" class="px-4 py-8 text-center text-sm text-gray-500">
                        {{ search ? $t('No matching records found.') : isDetails ? $t('No participants recorded for this session.') : $t('No sessions recorded for this room.') }}
                    </td>
                </tr>
                <template v-else>
                    <tr v-for="row in data.data" :key="row.conference_session_detail_uuid || row.conference_session_uuid" class="hover:bg-gray-50">
                        <TableField v-if="canDelete" class="px-4 py-2">
                            <input v-model="selectedItems" type="checkbox" :value="row.conference_session_uuid"
                                :aria-label="$t('Select session from :date', { date: formatDate(row.start_epoch) })"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                        </TableField>
                        <TableField v-for="column in columns" :key="column.field" class="whitespace-nowrap px-3 py-2 text-sm text-gray-600">
                            <a v-if="!isDetails && column.field === 'start_epoch'" :href="row.details_url" class="text-indigo-600 hover:underline">
                                {{ formatDate(row.start_epoch) }}
                            </a>
                            <button v-else-if="isDetails && column.field === 'caller_id_name' && row.call_details_url" type="button"
                                class="max-w-xs truncate text-left text-indigo-600 hover:underline" :title="row.caller_id_name" @click="openCall(row)">
                                {{ row.caller_id_name || $t('Call details') }}
                            </button>
                            <span v-else-if="column.field === 'start_epoch'">{{ formatDate(row.start_epoch) }}</span>
                            <span v-else-if="column.field === 'end_epoch'">{{ Number(row.end_epoch) > 0 ? formatDate(row.end_epoch) : $t('In progress') }}</span>
                            <span v-else-if="column.field === 'duration'" class="tabular-nums">{{ duration(row) }}</span>
                            <span v-else-if="column.field === 'moderator'">{{ row.moderator ? $t('Yes') : $t('No') }}</span>
                            <span v-else class="block max-w-xs truncate" :title="row[column.field]">{{ row[column.field] || '—' }}</span>
                        </TableField>
                        <TableField v-if="!isDetails" class="px-3 py-2 text-sm text-gray-500">
                            <div v-if="row.stream_url || row.download_url" class="flex items-center gap-3">
                                <audio v-if="row.stream_url" controls preload="none" class="h-9 w-64" :src="row.stream_url" :aria-label="$t('Conference recording')" />
                                <a v-if="row.download_url" :href="row.download_url" class="text-indigo-600 hover:underline">{{ $t('Download') }}</a>
                            </div>
                            <span v-else>—</span>
                        </TableField>
                    </tr>
                </template>
            </template>
        </DataTable>
    </div>

    <ConfirmationModal :show="deleteConfirmation" :header="$t('Delete selected sessions?')"
        :text="$t('Session history and participant details will be deleted. Recording files will be kept.')"
        :confirm-button-label="$t('Delete')" :cancel-button-label="$t('Cancel')" color="red" :loading="deleting"
        @close="deleteConfirmation = false" @confirm="deleteSessions" />
    <CallDetailsModal :show="showCall" :item="callItem" :loading="loadingCall" custom-class="sm:max-w-5xl" @close="showCall = false" />
    <Notification :show="notification.show" :type="notification.type" :messages="notification.messages" @update:show="notification.show = $event" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { trans } from '@i18n';
import axios from 'axios';
import moment from 'moment-timezone';
import MainLayout from '../Layouts/MainLayout.vue';
import DataTable from './components/general/DataTable.vue';
import TableColumnHeader from './components/general/TableColumnHeader.vue';
import TableField from './components/general/TableField.vue';
import Paginator from './components/general/Paginator.vue';
import ConfirmationModal from './components/modal/ConfirmationModal.vue';
import CallDetailsModal from './components/modal/CallDetailsModal.vue';
import Notification from './components/notifications/Notification.vue';

const props = defineProps({ room: Object, conference_session: Object, timezone: String, routes: Object, permissions: Object, pagination: Object });
const isDetails = computed(() => Boolean(props.conference_session));
const canDelete = computed(() => !isDetails.value && props.permissions.destroy);
const data = ref({ data: [], current_page: 1, last_page: 1, links: [], total: 0, from: null, to: null });
const perPage = ref(props.pagination.per_page);
const search = ref('');
const sort = ref({ field: 'start_epoch', order: isDetails.value ? 'asc' : 'desc' });
const loading = ref(true);
const loadError = ref(false);
const selectedItems = ref([]);
const deleteConfirmation = ref(false);
const deleting = ref(false);
const showCall = ref(false);
const loadingCall = ref(false);
const callItem = ref(null);
const notification = ref({ show: false, type: 'success', messages: {} });
let requestNumber = 0;

const columns = computed(() => [
    ...(isDetails.value ? [
        { field: 'caller_id_name', label: trans('Caller ID Name') },
        { field: 'caller_id_number', label: trans('Caller ID Number') },
        { field: 'moderator', label: trans('Moderator') },
        { field: 'network_addr', label: trans('Network Address') },
    ] : []),
    { field: 'start_epoch', label: trans('Start') },
    { field: 'end_epoch', label: trans('End') },
    { field: 'duration', label: trans('Duration'), sortable: false },
    ...(!isDetails.value ? [{ field: 'profile', label: trans('Profile') }] : []),
]);
const columnCount = computed(() => columns.value.length + Number(canDelete.value) + Number(!isDetails.value));
const bulkActions = computed(() => canDelete.value ? [{ id: 'delete', label: trans('Delete'), icon: 'TrashIcon' }] : []);
const selectPage = computed({
    get: () => data.value.data.length > 0 && data.value.data.every(row => selectedItems.value.includes(row.conference_session_uuid)),
    set: checked => { selectedItems.value = checked ? data.value.data.map(row => row.conference_session_uuid) : []; },
});

function formatDate(epoch) {
    return Number(epoch) > 0 ? moment.unix(Number(epoch)).tz(props.timezone || 'UTC').format('YYYY-MM-DD HH:mm:ss z') : '—';
}

function duration(row) {
    if (!(Number(row.start_epoch) > 0 && Number(row.end_epoch) > 0)) return '—';
    const seconds = Math.max(0, Number(row.end_epoch) - Number(row.start_epoch));
    return [Math.floor(seconds / 3600), Math.floor(seconds / 60) % 60, Math.floor(seconds) % 60].map(value => String(value).padStart(2, '0')).join(':');
}

async function fetchData(page = 1) {
    if (typeof page === 'string') page = Number(new URL(page, window.location.origin).searchParams.get('page') || 1);
    const currentRequest = ++requestNumber;
    loading.value = true;
    loadError.value = false;
    selectedItems.value = [];
    try {
        const response = await axios.get(props.routes.data, { params: {
            page: page || 1, per_page: perPage.value,
            sort: `${sort.value.order === 'desc' ? '-' : ''}${sort.value.field}`,
            filter: search.value ? { [isDetails.value ? 'search' : 'profile']: search.value } : {},
        } });
        if (currentRequest !== requestNumber) return;
        data.value = response.data;
        if (data.value.current_page > data.value.last_page) return fetchData(data.value.last_page);
    } catch {
        if (currentRequest === requestNumber) loadError.value = true;
    } finally {
        if (currentRequest === requestNumber) loading.value = false;
    }
}

function setSort(value) { sort.value = value; fetchData(1); }
function resetFilters() { search.value = ''; fetchData(1); }
function changePageSize(value) { perPage.value = value; fetchData(1); }
function confirmDelete() { if (selectedItems.value.length) deleteConfirmation.value = true; }
function showError(error) {
    notification.value = { show: true, type: 'error', messages: error.response?.data?.errors || error.response?.data?.messages || { error: [trans('Unable to process request.')] } };
}

async function deleteSessions() {
    if (deleting.value) return;
    deleting.value = true;
    try {
        const response = await axios.post(props.routes.bulk_delete, { items: selectedItems.value });
        deleteConfirmation.value = false;
        notification.value = { show: true, type: 'success', messages: response.data.messages };
        await fetchData(data.value.current_page);
    } catch (error) { showError(error); }
    finally { deleting.value = false; }
}

async function openCall(row) {
    showCall.value = true;
    loadingCall.value = true;
    callItem.value = null;
    try {
        const response = await axios.get(row.call_details_url);
        callItem.value = response.data.item;
    } catch (error) { showCall.value = false; showError(error); }
    finally { loadingCall.value = false; }
}

onMounted(() => fetchData());
</script>

<style scoped>
.secondary-button {
    @apply rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600 disabled:opacity-50;
}
</style>
