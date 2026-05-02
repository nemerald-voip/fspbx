<template>
    <div class="mt-4 flex flex-col">
        <div class="flex flex-col sm:flex-row sm:flex-wrap">
            <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                </div>
                <input
                    type="search"
                    v-model="filterData.search"
                    class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                    placeholder="Search"
                    @keydown.enter="handleSearchButtonClick"
                />
                <input
                    type="search"
                    v-model="filterData.search"
                    class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                    placeholder="Search"
                    @keydown.enter="handleSearchButtonClick"
                />
            </div>

            <div class="relative z-10 min-w-64 -mt-0.5 mb-2 scale-y-95 shrink-0 sm:mr-4">
                <DatePicker
                    :dateRange="filterData.dateRange"
                    :timezone="timezone"
                    @update:date-range="handleUpdateDateRange"
                />
            </div>

            <div class="relative min-w-40 mb-2 shrink-0 sm:mr-4">
                <select
                    v-model="filterData.status"
                    class="block w-full rounded-md border-0 py-2 pl-3 pr-10 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                >
                    <option value="all">All</option>
                    <option value="success">Success</option>
                    <option value="failed">Failed</option>
                </select>
            </div>

            <div class="relative">
                <div class="flex justify-between">
                    <button
                        type="button"
                        @click.prevent="handleSearchButtonClick"
                        class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                    >
                        Search
                    </button>

                    <button
                        type="button"
                        @click.prevent="handleFiltersReset"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <Paginator
                    class="border border-gray-200"
                    :previous="data.prev_page_url"
                    :next="data.next_page_url"
                    :from="data.from"
                    :to="data.to"
                    :total="data.total"
                    :currentPage="data.current_page"
                    :lastPage="data.last_page"
                    :links="data.links"
                    @pagination-change-page="renderRequestedPage"
                />

                <div class="overflow-hidden-t border-l border-r border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 mb-4">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                    <div class="flex items-center">
                                        <input
                                            v-if="canDelete"
                                            type="checkbox"
                                            v-model="selectPageItems"
                                            @change="handleSelectPageItems"
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600"
                                        />
                                        <span :class="canDelete ? 'pl-4' : ''">Date</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">From</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">To</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Status</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Code</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Result</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">ECM</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Pages</th>
                                <th v-if="canDelete" class="px-6 py-3 text-right text-sm font-semibold text-gray-900">Action</th>
                            </tr>
                        </thead>

                        <tbody v-if="!isDataLoading && data.data?.length" class="divide-y divide-gray-200 bg-white">
                            <tr v-if="canDelete && (selectPageItems || selectAll)">
                                <td colspan="9">
                                    <div class="text-sm text-center m-2">
                                        <span class="font-semibold">{{ selectedItems.length }}</span> items are selected.
                                        <button
                                            v-if="!selectAll && selectedItems.length != data.total"
                                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                                            @click="handleSelectAll"
                                        >
                                            Select all {{ data.total }} items
                                        </button>
                                        <button
                                            v-if="selectAll"
                                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                                            @click="handleClearSelection"
                                        >
                                            Clear selection
                                        </button>
                                        <button
                                            class="text-red-500 rounded py-2 px-2 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-1 focus:bg-red-50 focus:ring-red-300 transition duration-500 ease-in-out"
                                            @click="handleBulkDeleteRequest"
                                        >
                                            Delete selected
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <template v-for="row in data.data" :key="row.fax_log_uuid">
                                <tr @click="toggleExpand(row.fax_log_uuid)" class="hover:bg-gray-50 cursor-pointer">
                                    <td class="whitespace-nowrap px-6 py-2 text-sm font-medium text-gray-500">
                                        <div class="flex items-center">
                                            <input
                                                v-if="canDelete"
                                                v-model="selectedItems"
                                                type="checkbox"
                                                :value="row.fax_log_uuid"
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600"
                                                @click.stop
                                            />
                                            <span :class="canDelete ? 'ml-4' : ''">{{ row.fax_date_formatted }}</span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        {{ row.fax_file?.fax_caller_id_number_formatted ?? '' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        {{ faxDestination(row) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        <Badge
                                            :text="row.fax_success == '1' ? 'Success' : 'Failed'"
                                            :backgroundColor="row.fax_success == '1' ? 'bg-green-50' : 'bg-red-50'"
                                            :textColor="row.fax_success == '1' ? 'text-green-700' : 'text-red-700'"
                                            :ringColor="row.fax_success == '1' ? 'ring-green-600/20' : 'ring-red-600/20'"
                                        />
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        {{ row.fax_result_code ?? '' }}
                                    </td>
                                    <td class="px-6 py-2 text-sm text-gray-500" :title="row.fax_result_text ?? ''">
                                        <div class="max-w-md truncate">{{ row.fax_result_text ?? '' }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        {{ row.fax_ecm_used ?? '' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        {{ row.fax_document_transferred_pages ?? '' }} / {{ row.fax_document_total_pages ?? '' }}
                                    </td>
                                    <td v-if="canDelete" class="whitespace-nowrap px-6 py-2 text-sm text-gray-500" @click.stop>
                                        <div class="flex items-center justify-end">
                                            <div class="relative group flex items-center justify-center cursor-pointer">
                                                <TrashIcon
                                                    @click="handleDeleteButtonClick(row.fax_log_uuid)"
                                                    class="h-7 w-7 transition duration-300 ease-in-out p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-600 rounded-full"
                                                />
                                                <div class="absolute bottom-full mb-1 hidden group-hover:block whitespace-nowrap bg-gray-800 text-white text-xs rounded py-1 px-2 z-10 shadow-lg">
                                                    Delete
                                                    <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-gray-800 rotate-45"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="expandedRow === row.fax_log_uuid">
                                    <td :colspan="canDelete ? 9 : 8" class="bg-gray-50 px-6 py-4 shadow-inner">
                                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                            <div class="rounded-md border border-gray-200 bg-white p-4">
                                                <div class="text-sm font-semibold text-gray-900 mb-2">Overview</div>
                                                <div class="space-y-2 text-sm text-gray-600">
                                                    <div><span class="font-medium text-gray-700">Log ID:</span> {{ displayValue(row.fax_log_uuid) }}</div>
                                                    <div><span class="font-medium text-gray-700">Fax ID:</span> {{ displayValue(row.fax_uuid) }}</div>
                                                    <div><span class="font-medium text-gray-700">Mode:</span> {{ displayValue(row.fax_file?.fax_mode) }}</div>
                                                    <div><span class="font-medium text-gray-700">Date:</span> {{ displayValue(row.fax_date_formatted) }}</div>
                                                    <div><span class="font-medium text-gray-700">Duration:</span> {{ displayValue(row.fax_duration) }}</div>
                                                </div>
                                            </div>

                                            <div class="rounded-md border border-gray-200 bg-white p-4">
                                                <div class="text-sm font-semibold text-gray-900 mb-2">Transmission</div>
                                                <div class="space-y-2 text-sm text-gray-600">
                                                    <div><span class="font-medium text-gray-700">From:</span> {{ displayValue(row.fax_file?.fax_caller_id_number_formatted) }}</div>
                                                    <div><span class="font-medium text-gray-700">To:</span> {{ displayValue(faxDestination(row)) }}</div>
                                                    <div><span class="font-medium text-gray-700">Local Station ID:</span> {{ displayValue(row.fax_local_station_id) }}</div>
                                                    <div><span class="font-medium text-gray-700">Transfer Rate:</span> {{ displayValue(row.fax_transfer_rate) }}</div>
                                                    <div><span class="font-medium text-gray-700">Bad Rows:</span> {{ displayValue(row.fax_bad_rows) }}</div>
                                                    <div><span class="font-medium text-gray-700">Pages:</span> {{ displayValue(row.fax_document_transferred_pages) }} / {{ displayValue(row.fax_document_total_pages) }}</div>
                                                </div>
                                            </div>

                                            <div class="rounded-md border border-gray-200 bg-white p-4 lg:col-span-2">
                                                <div class="text-sm font-semibold text-gray-900 mb-2">Result</div>
                                                <div class="space-y-2 text-sm text-gray-600">
                                                    <div><span class="font-medium text-gray-700">Code:</span> {{ displayValue(row.fax_result_code) }}</div>
                                                    <div><span class="font-medium text-gray-700">Text:</span> {{ displayValue(row.fax_result_text) }}</div>
                                                    <div><span class="font-medium text-gray-700">URI:</span> <span class="break-all">{{ displayValue(row.fax_uri) }}</span></div>
                                                    <div><span class="font-medium text-gray-700">File:</span> <span class="break-all">{{ displayValue(row.fax_file_path) }}</span></div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <div v-if="!isDataLoading && data.data?.length === 0" class="text-center my-5">
                        <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                    </div>

                    <div v-if="isDataLoading" class="text-center my-5 text-sm text-gray-500">
                        <div class="animate-pulse flex space-x-4">
                            <div class="flex-1 space-y-6 py-1">
                                <div class="h-2 bg-slate-200 rounded"></div>
                                <div class="h-2 bg-slate-200 rounded"></div>
                                <div class="h-2 bg-slate-200 rounded"></div>
                                <div class="h-2 bg-slate-200 rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <Paginator
                    class="border border-gray-200"
                    :previous="data.prev_page_url"
                    :next="data.next_page_url"
                    :from="data.from"
                    :to="data.to"
                    :total="data.total"
                    :currentPage="data.current_page"
                    :lastPage="data.last_page"
                    :links="data.links"
                    @pagination-change-page="renderRequestedPage"
                />
            </div>
        </div>
    </div>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages" @update:show="hideNotification" />

    <ConfirmationModal
        :show="showDeleteConfirmationModal"
        @close="showDeleteConfirmationModal = false"
        @confirm="confirmDeleteAction"
        :header="'Are you sure?'"
        :text="'Are you sure you want to permanently delete selected fax logs? This action can not be undone.'"
        :confirm-button-label="'Delete'"
        cancel-button-label="Cancel"
    />
</template>

<script setup>
import { computed, ref, watch } from "vue";
import axios from "axios";
import moment from "moment-timezone";
import DatePicker from "@generalComponents/DatePicker.vue";
import Paginator from "@generalComponents/Paginator.vue";
import Badge from "@generalComponents/Badge.vue";
import Notification from "./notifications/Notification.vue";
import ConfirmationModal from "./modal/ConfirmationModal.vue";
import { MagnifyingGlassIcon, TrashIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    permissions: Object,
    trigger: Boolean,
});

const isDataLoading = ref(false);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const selectAll = ref(false);
const expandedRow = ref(null);
const showDeleteConfirmationModal = ref(false);
const confirmDeleteAction = ref(null);

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

const startLocal = moment.utc(props.startPeriod).tz(props.timezone);
const endLocal = moment.utc(props.endPeriod).tz(props.timezone);

const filterData = ref({
    search: null,
    status: "all",
    dateRange: [
        startLocal.clone().startOf("day").toISOString(),
        endLocal.clone().endOf("day").toISOString(),
    ],
});

const canDelete = computed(() => Boolean(props.permissions?.fax_log_delete));

const fetchData = (page = 1) => {
    isDataLoading.value = true;

    axios
        .get(props.routes.fax_logs, {
            params: {
                filter: filterData.value,
                page,
            },
        })
        .then((response) => {
            data.value = response.data;
            expandedRow.value = null;
        })
        .catch((error) => {
            handleErrorResponse(error);
        })
        .finally(() => {
            isDataLoading.value = false;
        });
};

watch(() => props.trigger, () => {
    fetchData(1);
});

const handleSearchButtonClick = () => fetchData(1);

const toggleExpand = (uuid) => {
    expandedRow.value = expandedRow.value === uuid ? null : uuid;
};

const renderRequestedPage = (url) => {
    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get("page") ?? 1;
    fetchData(pageParam);
};

const handleUpdateDateRange = (newDateRange) => {
    filterData.value.dateRange = newDateRange;
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    filterData.value.status = "all";
    filterData.value.dateRange = [
        startLocal.clone().startOf("day").toISOString(),
        endLocal.clone().endOf("day").toISOString(),
    ];
    handleSearchButtonClick();
};

const handleSelectAll = () => {
    axios
        .post(props.routes.fax_logs_select_all, { filter: filterData.value })
        .then((response) => {
            selectedItems.value = (response.data.items || []).map(String);
            selectAll.value = true;
            selectPageItems.value = true;
            showNotification("success", response.data.messages);
        })
        .catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });
};

const handleSelectPageItems = () => {
    if (selectPageItems.value) {
        selectedItems.value = (data.value.data || []).map((item) => String(item.fax_log_uuid));
    } else {
        selectedItems.value = [];
        selectAll.value = false;
    }
};

const handleClearSelection = () => {
    selectedItems.value = [];
    selectPageItems.value = false;
    selectAll.value = false;
};

const handleBulkDeleteRequest = () => {
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeBulkDelete();
};

const handleDeleteButtonClick = (uuid) => {
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
};

const executeBulkDelete = (items = selectedItems.value) => {
    axios
        .post(props.routes.fax_logs_bulk_delete, { items })
        .then((response) => {
            showDeleteConfirmationModal.value = false;
            showNotification("success", response.data.messages);
            handleClearSelection();
            handleSearchButtonClick();
        })
        .catch((error) => {
            showDeleteConfirmationModal.value = false;
            handleClearSelection();
            handleErrorResponse(error);
        });
};

const faxDestination = (row) => {
    if (row.fax_file?.fax_mode === "rx") {
        return row.fax?.fax_caller_id_number_formatted ?? row.fax_file?.fax_caller_id_number_formatted ?? "";
    }

    if (row.fax_file?.fax_mode === "tx") {
        return row.fax_file?.fax_destination_formatted ?? "";
    }

    return "";
};

const displayValue = (value) => {
    if (value === null || value === undefined || value === "") {
        return "N/A";
    }

    return value;
};

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
};

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
};

const handleErrorResponse = (error) => {
    if (error.response) {
        showNotification("error", error.response.data.errors || error.response.data.messages || { request: [error.message] });
    } else if (error.request) {
        showNotification("error", { request: [String(error.request)] });
    } else {
        showNotification("error", { request: [error.message] });
    }
};
</script>
