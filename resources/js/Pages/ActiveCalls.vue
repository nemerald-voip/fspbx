<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Active Calls</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-candidate"
                        id="mobile-search-candidate"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                    <input type="text" v-model="filterData.search" name="desktop-search-candidate"
                        id="desktop-search-candidate"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
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
                    Refresh
                </button>

                <button v-if="!showGlobal && page.props.auth.can.active_calls_view_global" type="button"
                    @click.prevent="handleShowGlobal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show global
                </button>

                <button v-if="showGlobal && page.props.auth.can.active_calls_view_global" type="button"
                    @click.prevent="handleShowLocal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show local
                </button>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>
            <template #table-header>
                <TableColumnHeader header="User"
                    class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems" @click.stop
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                </TableColumnHeader>

                <TableColumnHeader v-if="showGlobal" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('context')">
                        <span class="mr-2">Domain</span>
                        <ChevronUpIcon v-if="sortData.name === 'context' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'context' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>

                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('created_epoch')">
                        <span class="mr-2">Timestamp</span>
                        <ChevronUpIcon v-if="sortData.name === 'created_epoch' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'created_epoch' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>

                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('duration')">
                        <span class="mr-2">Duration</span>
                        <ChevronUpIcon v-if="sortData.name === 'duration' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'duration' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>

                <!-- <TableColumnHeader header="Contact" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" /> -->
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('cid_name')">
                        <span class="mr-2">Caller Name</span>
                        <ChevronUpIcon v-if="sortData.name === 'cid_name' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'cid_name' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('cid_num')">
                        <span class="mr-2">Caller Number</span>
                        <ChevronUpIcon v-if="sortData.name === 'cid_num' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'cid_num' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('dest')">
                        <span class="mr-2">Destination</span>
                        <ChevronUpIcon v-if="sortData.name === 'dest' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'dest' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('application')">
                        <span class="mr-2">App</span>
                        <ChevronUpIcon v-if="sortData.name === 'application' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'application' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('read_codec')">
                        <span class="mr-2">Codec</span>
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
                <TableColumnHeader header="Action" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="10">
                    <div class="text-sm text-center m-2">
                        <span class="font-semibold ">{{ selectedItems.length }} </span> items are selected.
                        <button v-if="!selectAll && selectedItems.length != data.total"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200  hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleSelectAll">
                            Select all {{ data.total }} items
                        </button>
                        <button v-if="selectAll"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200  hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleClearSelection">
                            Clear selection
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
                                <input v-if="row.uuid" v-model="selectedItems" type="checkbox" name="action_box[]"
                                    :value="row.uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                                <div class="ml-9">
                                    <ejs-tooltip :content="row.direction + ' call'" position='TopLeft'
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
                                @click="toggleExpand(row.uuid)" :title="'Click to view details'">
                                {{ row.app_preview || (row.application + (row.application_data ? ': ' +
                                row.application_data : '')) }}
                            </div>

                            <div class="text-xs text-gray-400 mt-1 cursor-pointer" @click="toggleExpand(row.uuid)">
                                {{ expandedCallUuid === row.uuid ? 'Hide details' : 'Show details' }}
                            </div>
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="`${row.read_codec}:${row.read_rate} / ${row.write_codec}:${row.write_rate}`" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.secure" />


                        <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                            <template #action-buttons>
                                <div class="flex items-center whitespace-nowrap">
                                    <ejs-tooltip :content="'End Call'" position='TopCenter'
                                        target="#restart_tooltip_target">
                                        <div id="restart_tooltip_target">
                                            <CallEndIcon @click="handleSingleItemActionRequest(row.uuid, 'end_call')"
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
                        <td :colspan="showGlobal ? 10 : 9" class="bg-gray-50 px-6 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-700 mb-2">Call Details</div>

                                    <div class="text-sm text-gray-600 space-y-1">
                                        <div><span class="font-semibold">Domain:</span> {{ row.context }}</div>
                                        <div><span class="font-semibold">Direction:</span> {{ row.direction }}</div>
                                        <div><span class="font-semibold">Caller:</span> {{ row.cid_name }} ({{
                                            row.cid_num }})</div>
                                        <div><span class="font-semibold">Destination:</span> {{ row.dest }}</div>
                                        <div><span class="font-semibold">Started:</span> {{ row.created_display }}</div>
                                    </div>

                                    <div class="mt-3">
                                        <div class="text-xs font-semibold text-gray-600 mb-1">App / Data</div>
                                        <pre
                                            class="text-xs bg-white border rounded p-3 overflow-auto max-h-48 whitespace-pre-wrap break-words">
                        {{ row.app_full || '' }}</pre>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 shrink-0">
                                    <button type="button"
                                        class="rounded-md bg-white px-3 py-2 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                        @click="copyToClipboard(row.app_full || '')">
                                        Copy
                                    </button>

                                    <button type="button"
                                        class="rounded-md bg-white px-3 py-2 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                        @click="toggleExpand(row.uuid)">
                                        Close
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
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Adjust your search and try again.
                    </p>
                </div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>

            <template #footer>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>
        </DataTable>
        <div class="px-4 sm:px-6 lg:px-8"></div>
    </div>

    <ConfirmationModal :show="isActionConfirmationModalVisible" @close="isActionConfirmationModalVisible = false"
        @confirm="confirmAction" :header="'Are you sure?'" :text="'Are you sure you want to proceed with this action?'"
        :confirm-button-label="actionLabel" cancel-button-label="Cancel" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, onMounted, ref, onUnmounted } from "vue";
import { usePage } from '@inertiajs/vue3'
import axios from 'axios';
import { router } from "@inertiajs/vue3";
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

const page = usePage()
const loading = ref(false)
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
const expandedCallUuid = ref(null);

const props = defineProps({
    data: Object,
    showGlobal: Boolean,
    routes: Object,
    // itemData: Object,
    // itemOptions: Object,
});


const filterData = ref({
    search: null,
    showGlobal: props.showGlobal,
});

const sortData = ref({
    name: 'created_epoch',
    order: 'desc',
});

const showGlobal = ref(props.showGlobal);

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [
        {
            id: 'bulk_end_call',
            label: 'End Calls',
            icon: 'CallEndIcon'
        },

    ];

    return actions;
});

const currentTime = ref(Date.now());
const dataFetchedTime = ref(Date.now());
const durationIntervalId = ref(null);

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
    // Tick every second to update durations
    durationIntervalId.value = setInterval(() => {
        currentTime.value = Date.now();
    }, 1000);
});

const handleSingleItemActionRequest = (uuid, action) => {
    isActionConfirmationModalVisible.value = true;
    actionLabel.value = 'End Call';
    confirmAction.value = () => executeSingleAction(uuid, action);
}

const executeSingleAction = (uuid, action) => {
    axios.post(props.routes.action,
        { 'ids': [uuid], 'action': action },
    )
        .then((response) => {
            showNotification('success', response.data.messages);
            handleModalClose();
            // Delay the search button click by 2 seconds (2000 milliseconds)
            setTimeout(() => {
                handleRefresh();
            }, 2000);
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
        actionLabel.value = 'End Calls';
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
            // Delay the search button click by 2 seconds (2000 milliseconds)
            setTimeout(() => {
                handleRefresh();
            }, 2000);
            handleClearSelection();
        }).catch((error) => {
            handleClearSelection();
            handleModalClose();
            handleErrorResponse(error);
        });
}




const handleSelectAll = () => {
    axios.post(props.routes.select_all, filterData._rawValue)
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
        showNotification('success', { success: ['Copied to clipboard'] });
    } catch (e) {
        showNotification('error', { error: ['Copy failed'] });
    }
};

const handleAction = (id, action) => {
    axios.post(props.routes.action,
        { 'ids': [id], 'action': action },
    )
        .then((response) => {
            showNotification('success', response.data.messages);
            // Delay the search button click by 2 seconds (2000 milliseconds)
            setTimeout(() => {
                handleSearchButtonClick();
            }, 2000);
            handleClearSelection();
        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });
}

const handleRefreshButtonClick = () => {
    handleSearchButtonClick();
}


const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    showGlobal.value = true;
    handleSearchButtonClick();
}

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    showGlobal.value = false;
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

const handleSearchButtonClick = () => {
    loading.value = true;
    router.visit(props.routes.current_page, {
        data: {
            filterData: filterData._rawValue,
            sortField: sortData.value.name,
            sortOrder: sortData.value.order,
        },
        preserveScroll: true,
        preserveState: true,
        only: [
            "data",
            'showGlobal',
        ],
        onSuccess: (page) => {
            loading.value = false;
            handleClearSelection();
            dataFetchedTime.value = Date.now();
        }
    });
};

const handleRefresh = () => {
    // Safety check: if they toggled it off while a request was pending, abort.
    if (!isRefreshing.value) return;

    router.visit(props.routes.current_page, {
        data: {
            filterData: filterData._rawValue,
            sortField: sortData.value.name,
            sortOrder: sortData.value.order,
        },
        preserveScroll: true,
        preserveState: true,
        only: [
            "data",
            'showGlobal',
        ],
        onSuccess: (page) => {
            handleClearSelection();
            dataFetchedTime.value = Date.now();

            // Queue up the next refresh ONLY after this one succeeds
            if (isRefreshing.value) {
                refreshTimeoutId.value = setTimeout(() => {
                    handleRefresh();
                }, 5000);
            }
        },
        onError: () => {
            if (isRefreshing.value) {
                refreshTimeoutId.value = setTimeout(() => {
                    handleRefresh();
                }, 5000);
            }
        }
    });
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    handleSearchButtonClick();
}


const renderRequestedPage = (url) => {
    loading.value = true;
    router.visit(url, {
        data: {
            filterData: filterData._rawValue,
            sortField: sortData.value.name,
            sortOrder: sortData.value.order,
        },
        preserveScroll: true,
        preserveState: true,
        only: ["data"],
        onSuccess: (page) => {
            loading.value = false;
            dataFetchedTime.value = Date.now();
        }
    });
};


const handleErrorResponse = (error) => {
    if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || { request: [error.message] });
    } else if (error.request) {
        // The request was made but no response was received
        // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
        // http.ClientRequest in node.js
        showNotification('error', { request: [error.request] });
        console.log(error.request);
    } else {
        // Something happened in setting up the request that triggered an Error
        showNotification('error', { request: [error.message] });
        console.log(error.message);
    }
}

const handleSelectPageItems = () => {
    if (selectPageItems.value) {
        selectedItems.value = props.data.data.map(item => item.uuid);
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

    if (isRefreshing.value) {
        // Start the loop by firing the first manual refresh
        handleRefresh();
    } else {
        // Stop the loop by clearing the pending timeout
        if (refreshTimeoutId.value) {
            clearTimeout(refreshTimeoutId.value);
            refreshTimeoutId.value = null;
        }
    }
};

// Make sure to clear the interval when the component is destroyed
onUnmounted(() => {
    if (refreshTimeoutId.value) {
        clearTimeout(refreshTimeoutId.value);
    }
    if (durationIntervalId.value) {
        clearInterval(durationIntervalId.value);
    }
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
