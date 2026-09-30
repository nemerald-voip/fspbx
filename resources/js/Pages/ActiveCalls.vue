<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>{{ $t('Active Calls') }}</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-candidate"
                        id="mobile-search-candidate"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        :placeholder="$t('Search')" @keydown.enter="handleSearchButtonClick" />
                    <input type="text" v-model="filterData.search" name="desktop-search-candidate"
                        id="desktop-search-candidate"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        :placeholder="$t('Search')" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <button :class="[
                    isRefreshing
                        ? 'rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
                        : 'rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50'
                ]" @click="toggleRefreshing">
                    <Refresh :class="{ 'animate-spin': isRefreshing }" />
                </button>

                <button type="button" @click.prevent="handleRefreshButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 ml-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    {{ $t('Refresh') }}
                </button>

                <button v-if="!showGlobal && permissions.view_global" type="button"
                    @click.prevent="handleShowGlobal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    {{ $t('Show global') }}
                </button>

                <button v-if="showGlobal && permissions.view_global" type="button"
                    @click.prevent="handleShowLocal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    {{ $t('Show local') }}
                </button>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>
            <template #table-header>
                <TableColumnHeader :header="$t('User')"
                    class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input v-if="permissions.hangup" type="checkbox" v-model="selectPageItems"
                        @change="handleSelectPageItems" @click.stop
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                </TableColumnHeader>

                <TableColumnHeader v-if="showGlobal" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('context')">
                        <span class="mr-2">{{ $t('Domain') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'context' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'context' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>

                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('created_epoch')">
                        <span class="mr-2">{{ $t('Timestamp') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'created_epoch' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'created_epoch' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>

                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('duration')">
                        <span class="mr-2">{{ $t('Duration') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'duration' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'duration' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>

                <!-- <TableColumnHeader header="Contact" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" /> -->
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('cid_name')">
                        <span class="mr-2">{{ $t('Caller Name') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'cid_name' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'cid_name' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('cid_num')">
                        <span class="mr-2">{{ $t('Caller Number') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'cid_num' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'cid_num' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('dest')">
                        <span class="mr-2">{{ $t('Destination') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'dest' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'dest' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('application')">
                        <span class="mr-2">{{ $t('App') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'application' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'application' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('read_codec')">
                        <span class="mr-2">{{ $t('Codec') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'read_codec' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'read_codec' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('secure')">
                        <span class="mr-2">SRTP</span>
                        <ChevronUpIcon v-if="sortData.name === 'secure' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'secure' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader v-if="permissions.hangup" :header="$t('Action')"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems && permissions.hangup" v-slot:current-selection>
                <td :colspan="detailColspan">
                    <div class="text-sm text-center m-2">
                        <span class="font-semibold">{{ $t(':count items are selected.', { count: selectedItems.length }) }}</span>
                        <button v-if="!selectAll && selectedItems.length != data.total"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200  hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleSelectAll">
                            {{ $t('Select all :count items', { count: data.total }) }}
                        </button>
                        <button v-if="selectAll"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200  hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleClearSelection">
                            {{ $t('Clear selection') }}
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <template v-for="row in data.data" :key="row.uuid">
                    <!-- MAIN ROW -->
                    <tr>
                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 ">
                            <div class="flex items-center">
                                <input v-if="row.uuid && permissions.hangup" v-model="selectedItems" type="checkbox"
                                    name="action_box[]" :value="row.uuid"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                                <div class="ml-9">
                                    <ejs-tooltip :content="callDirectionLabel(row.direction)" position='TopLeft'
                                        target="#destination_tooltip_target">
                                        <div id="destination_tooltip_target">
                                            <PhoneOutgoingIcon class="w-5 h-5 text-blue-600"
                                                v-if="row.direction === 'outbound'" />
                                            <PhoneIncomingIcon class="w-5 h-5 text-green-600"
                                                v-if="row.direction === 'inbound'" />
                                            <PhoneLocalIcon class="w-5 h-5 text-fuchsia-600"
                                                v-if="row.direction === 'local'" />
                                        </div>
                                    </ejs-tooltip>
                                </div>

                            </div>
                        </TableField>

                        <TableField v-if="showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.context" />

                        <TableField class=" px-2 py-2 text-sm text-gray-500" :text="row.created_display" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500 font-mono"
                            :text="formatDuration(row.start_epoch)" />

                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.cid_name" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.cid_num" />

                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.dest" />
                        <TableField class="px-2 py-2 text-sm text-gray-500">
                            <div class="max-w-[22rem] truncate font-mono cursor-pointer hover:text-gray-900"
                                @click="toggleExpand(row.uuid)" :title="$t('Click to view details')">
                                {{ row.app_preview || (row.application + (row.application_data ? ': ' +
                                row.application_data : '')) }}
                            </div>

                            <div class="text-xs text-gray-400 mt-1 cursor-pointer" @click="toggleExpand(row.uuid)">
                                {{ expandedCallUuid === row.uuid ? $t('Hide details') : $t('Show details') }}
                            </div>
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="`${row.read_codec}:${row.read_rate} / ${row.write_codec}:${row.write_rate}`" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.secure" />


                        <TableField v-if="permissions.hangup" class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                            <template #action-buttons>
                                <div class="flex items-center whitespace-nowrap">
                                    <ejs-tooltip :content="$t('End Call')" position='TopCenter'
                                        target="#restart_tooltip_target">
                                        <div id="restart_tooltip_target">
                                            <CallEndIcon v-if="permissions.hangup"
                                                @click="handleSingleItemActionRequest(row.uuid, 'end_call')"
                                                class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                        </div>
                                    </ejs-tooltip>



                                    <!-- <div id="tooltip-no-arrow-sync" role="tooltip" 
                                    class="inline-block absolute invisible text-xs z-10 py-1 px-2 font-medium text-white rounded-sm shadow-sm opacity-0 tooltip dark:bg-gray-600 delay-150" >
                                tooltip
                                </div> -->


                                </div>
                            </template>
                        </TableField>
                    </tr>

                    <!-- EXPANDED DETAILS ROW -->
                    <tr v-if="expandedCallUuid === row.uuid">
                        <td :colspan="detailColspan" class="bg-gray-50 px-6 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-700 mb-2">{{ $t('Call Details') }}</div>

                                    <div class="text-sm text-gray-600 space-y-1">
                                        <div><span class="font-semibold">{{ $t('Domain') }}:</span> {{ row.context }}</div>
                                        <div><span class="font-semibold">{{ $t('Direction') }}:</span> {{ callDirectionLabel(row.direction) }}</div>
                                        <div><span class="font-semibold">{{ $t('Caller') }}:</span> {{ row.cid_name }} ({{
                                            row.cid_num }})</div>
                                        <div><span class="font-semibold">{{ $t('Destination') }}:</span> {{ row.dest }}</div>
                                        <div><span class="font-semibold">{{ $t('Started') }}:</span> {{ row.created_display }}</div>
                                    </div>

                                    <div class="mt-3">
                                        <div class="text-xs font-semibold text-gray-600 mb-1">{{ $t('App / Data') }}</div>
                                        <pre
                                            class="text-xs bg-white border rounded p-3 overflow-auto max-h-48 whitespace-pre-wrap break-words">
                        {{ row.app_full || '' }}</pre>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 shrink-0">
                                    <button type="button"
                                        class="rounded-md bg-white px-3 py-2 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                        @click="copyToClipboard(row.app_full || '')">
                                        {{ $t('Copy') }}
                                    </button>

                                    <button type="button"
                                        class="rounded-md bg-white px-3 py-2 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                        @click="toggleExpand(row.uuid)">
                                        {{ $t('Close') }}
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                </template>
            </template>
            <template #empty>
                <!-- Conditional rendering for 'no records' message -->
                <div v-if="data.data.length === 0" class="text-center my-5 ">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ $t('No results found') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $t('Adjust your search and try again.') }}
                    </p>
                </div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>

            <template #footer>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    :page-size="perPage" :page-size-options="props.pagination?.per_page_options ?? []"
                    :show-page-size-selector="true"
                    @pagination-change-page="renderRequestedPage" @page-size-change="handlePageSizeChange" />
            </template>
        </DataTable>
        <div class="px-4 sm:px-6 lg:px-8"></div>
    </div>

    <ConfirmationModal :show="isActionConfirmationModalVisible" @close="isActionConfirmationModalVisible = false"
        @confirm="confirmAction" :header="$t('Are you sure?')" :text="$t('Are you sure you want to proceed with this action?')"
        :confirm-button-label="actionLabel" :cancel-button-label="$t('Cancel')" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, onMounted, ref, onUnmounted } from "vue";
import { trans } from '@i18n';
import axios from 'axios';
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { ChevronDownIcon, ChevronUpIcon, MagnifyingGlassIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import MainLayout from "../Layouts/MainLayout.vue";
import Notification from "./components/notifications/Notification.vue";
import PhoneOutgoingIcon from "./components/icons/PhoneOutgoingIcon.vue"
import PhoneIncomingIcon from "./components/icons/PhoneIncomingIcon.vue"
import PhoneLocalIcon from "./components/icons/PhoneLocalIcon.vue"
import CallEndIcon from "./components/icons/CallEndIcon.vue"
import Refresh from "./components/icons/Refresh.vue"

const loading = ref(true)
const currentPage = ref(1);
const isRefreshing = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const confirmAction = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const isActionConfirmationModalVisible = ref(false);
const actionLabel = ref('');
const refreshTimeoutId = ref(null);
const actionRefreshTimeoutId = ref(null);
const expandedCallUuid = ref(null);
let activeRequest = null;
let requestSequence = 0;
let isUnmounted = false;

const props = defineProps({
    showGlobal: Boolean,
    permissions: Object,
    routes: Object,
    pagination: Object,
});

const perPage = ref(props.pagination?.per_page);

const data = ref({
    data: [],
    prev_page_url: null,
    next_page_url: null,
    from: 0,
    to: 0,
    total: 0,
    current_page: 1,
    last_page: 1,
    links: [],
});

const filterData = ref({
    search: null,
    showGlobal: props.showGlobal,
});

const sortData = ref({
    name: 'created_epoch',
    order: 'desc',
});

const permissions = computed(() => props.permissions ?? {});
const showGlobal = computed(() => filterData.value.showGlobal && permissions.value.view_global);
const detailColspan = computed(() => (showGlobal.value ? 10 : 9) - (permissions.value.hangup ? 0 : 1));

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    if (!permissions.value.hangup) {
        return [];
    }

    const actions = [
        {
            id: 'bulk_end_call',
            label: trans('End Calls'),
            icon: 'CallEndIcon'
        },

    ];

    return actions;
});

const currentTime = ref(Date.now());
const dataFetchedTime = ref(Date.now());
const durationIntervalId = ref(null);

const callDirectionLabel = (direction) => {
    switch (direction) {
        case 'inbound': return trans('Inbound call');
        case 'outbound': return trans('Outbound call');
        case 'local': return trans('Local call');
        default: return direction ?? '';
    }
};

const formatDuration = (startEpoch) => {
    if (!startEpoch) return '00:00:00';

    // Choose the reference time based on the auto-refresh state
    const referenceTime = isRefreshing.value ? currentTime.value : dataFetchedTime.value;

    let diffInSeconds = Math.floor((referenceTime - startEpoch) / 1000);

    // Fallback just in case
    if (diffInSeconds < 0) diffInSeconds = 0;

    const hours = Math.floor(diffInSeconds / 3600);
    const minutes = Math.floor((diffInSeconds % 3600) / 60);
    const seconds = diffInSeconds % 60;

    const pad = (num) => num.toString().padStart(2, '0');
    return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
};

onMounted(() => {
    getData();

    // Tick every second to update durations
    durationIntervalId.value = setInterval(() => {
        currentTime.value = Date.now();
    }, 1000);
});

const handleSingleItemActionRequest = (uuid, action) => {
    isActionConfirmationModalVisible.value = true;
    actionLabel.value = trans('End Call');
    confirmAction.value = () => executeSingleAction(uuid, action);
}

const executeSingleAction = (uuid, action) => {
    axios.post(props.routes.action,
        { 'ids': [uuid], 'action': action },
    )
        .then((response) => {
            showNotification('success', response.data.messages);
            handleModalClose();
            scheduleActionRefresh();
            handleClearSelection();
        }).catch((error) => {
            handleModalClose();
            handleClearSelection();
            handleErrorResponse(error);
        });
}


const handleBulkActionRequest = (action) => {
    if (action === 'bulk_end_call') {
        isActionConfirmationModalVisible.value = true;
        actionLabel.value = trans('End Calls');
        confirmAction.value = () => executeBulkAction('end_call');
    }

}

const executeBulkAction = (action) => {
    axios.post(props.routes.action,
        { 'ids': selectedItems.value, 'action': action },
    )
        .then((response) => {
            showNotification('success', response.data.messages);
            handleModalClose();
            scheduleActionRefresh();
            handleClearSelection();
        }).catch((error) => {
            handleClearSelection();
            handleModalClose();
            handleErrorResponse(error);
        });
}




const handleSelectAll = () => {
    axios.post(props.routes.select_all, { filter: filterData.value })
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification('success', response.data.messages);

        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });

};

const toggleExpand = (uuid) => {
    expandedCallUuid.value = expandedCallUuid.value === uuid ? null : uuid;
};

const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
        showNotification('success', { success: [trans('Copied to clipboard')] });
    } catch (e) {
        showNotification('error', { error: [trans('Copy failed')] });
    }
};

const scheduleActionRefresh = () => {
    if (isUnmounted) return;

    clearTimeout(actionRefreshTimeoutId.value);
    actionRefreshTimeoutId.value = setTimeout(() => {
        actionRefreshTimeoutId.value = null;
        getData(currentPage.value, { background: true });
    }, 2000);
};

const handleRefreshButtonClick = () => {
    getData(currentPage.value);
}


const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    handleSearchButtonClick();
}

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    handleSearchButtonClick();
}

const handleSortRequest = (column) => {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === 'asc' ? 'desc' : 'asc';
    } else {
        sortData.value.name = column;
        sortData.value.order = column === 'created_epoch' ? 'desc' : 'asc';
    }

    handleSearchButtonClick();
};

const getData = async (page = currentPage.value, { background = false } = {}) => {
    if (isUnmounted) return;

    clearTimeout(refreshTimeoutId.value);
    refreshTimeoutId.value = null;
    activeRequest?.abort();
    const controller = new AbortController();
    activeRequest = controller;
    const sequence = ++requestSequence;
    loading.value = !background;
    currentPage.value = Number(page) || 1;

    const sort = sortData.value.order === 'desc' ? `-${sortData.value.name}` : sortData.value.name;

    try {
        const response = await axios.get(props.routes.data_route, {
            params: {
                filter: { ...filterData.value },
                page: currentPage.value,
                per_page: perPage.value,
                sort,
            },
            signal: controller.signal,
        });

        if (isUnmounted || sequence !== requestSequence) return;

        data.value = response.data;
        currentPage.value = response.data.current_page ?? currentPage.value;
        dataFetchedTime.value = Date.now();
        handleClearSelection();
    } catch (error) {
        if (!isUnmounted && sequence === requestSequence && !axios.isCancel(error)) {
            handleErrorResponse(error);
        }
    } finally {
        if (!isUnmounted && sequence === requestSequence) {
            activeRequest = null;
            loading.value = false;
            scheduleRefresh();
        }
    }
};

const scheduleRefresh = () => {
    clearTimeout(refreshTimeoutId.value);
    refreshTimeoutId.value = null;

    if (isRefreshing.value && !isUnmounted) {
        refreshTimeoutId.value = setTimeout(() => {
            getData(currentPage.value, { background: true });
        }, 5000);
    }
};

const handleSearchButtonClick = () => {
    getData(1);
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    handleSearchButtonClick();
}


const handlePageSizeChange = (newPerPage) => {
    perPage.value = newPerPage;
    handleSearchButtonClick();
};

const renderRequestedPage = (url) => {
    if (!url) return;

    const urlObj = new URL(url, window.location.origin);
    getData(urlObj.searchParams.get('page') ?? 1);
};


const handleErrorResponse = (error) => {
    if (error.response) {
        showNotification('error', error.response.data.errors || error.response.data.messages || { request: [error.message] });
    } else {
        showNotification('error', { request: [error.message] });
    }
}

const handleSelectPageItems = () => {
    if (selectPageItems.value) {
        selectedItems.value = data.value.data.map(item => item.uuid);
    } else {
        selectedItems.value = [];
    }
};



const handleClearSelection = () => {
    selectedItems.value = [];
    selectPageItems.value = false;
    selectAll.value = false;
}

const toggleRefreshing = () => {
    isRefreshing.value = !isRefreshing.value;

    if (isRefreshing.value && !activeRequest) {
        getData(currentPage.value, { background: true });
    } else {
        clearTimeout(refreshTimeoutId.value);
        refreshTimeoutId.value = null;
    }
};

onUnmounted(() => {
    isUnmounted = true;
    activeRequest?.abort();
    clearTimeout(refreshTimeoutId.value);
    clearTimeout(actionRefreshTimeoutId.value);
    clearInterval(durationIntervalId.value);
});

const handleModalClose = () => {
    isActionConfirmationModalVisible.value = false;
}

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
}

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
}


registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>
