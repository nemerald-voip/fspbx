<template>
    <div class="mt-4 flex flex-col">
        <div class="flex flex-col sm:flex-row sm:flex-wrap">
            <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <MagnifyingGlassIcon class="h-5 w-5 text-subtle" aria-hidden="true" />
                </div>
                <input
                    type="search"
                    v-model="filterData.search"
                    class="block w-full rounded-md border-0 py-1.5 pl-10 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:hidden"
                    placeholder="Search"
                    @keydown.enter="handleSearchButtonClick"
                />
                <input
                    type="search"
                    v-model="filterData.search"
                    class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:block"
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

            <div v-if="showDomainFilter" class="relative z-[1] min-w-72 -mt-0.5 mb-2 shrink-0 sm:mr-4">
                <Vueform :key="domainFilterKey" :display-errors="false" size="sm">
                    <SelectElement
                        name="domain_uuid"
                        :default="filterData.domain_uuid"
                        :items="domainFilterOptions"
                        :native="false"
                        :search="true"
                        input-type="search"
                        autocomplete="off"
                        :strict="false"
                        :floating="false"
                        @change="handleUpdateDomainFilter"
                    />
                </Vueform>
            </div>

            <div class="relative min-w-40 mb-2 shrink-0 sm:mr-4">
                <select
                    v-model="filterData.status"
                    class="block w-full rounded-md border-0 py-2 pl-3 pr-10 text-sm text-heading ring-1 ring-inset ring-strong focus:ring-2 focus:ring-inset focus:ring-focus"
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
                        class="rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    >
                        Search
                    </button>

                    <button
                        type="button"
                        @click.prevent="handleFiltersReset"
                        class="rounded-md bg-surface px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <Paginator
                    class="border border-default"
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

                <div class="overflow-hidden-t border-l border-r border-default">
                    <table class="min-w-full divide-y divide-default mb-4">
                        <thead class="bg-surface-3">
                            <tr>
                                <th class="w-12 py-3 pl-6 pr-3 text-left text-sm font-semibold text-heading">
                                    <div class="flex items-center">
                                        <input
                                            v-if="canDelete"
                                            type="checkbox"
                                            v-model="selectPageItems"
                                            @change="handleSelectPageItems"
                                            class="h-4 w-4 rounded border-strong text-accent-fg"
                                        />
                                    </div>
                                </th>
                                <th class="w-12 py-3 pl-6 pr-3 text-left text-sm font-semibold text-heading">
                                    <span class="sr-only">Direction</span>
                                </th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Date</th>
                                <th v-if="showDomainColumn" class="px-6 py-3 text-left text-sm font-semibold text-heading">Domain</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">From</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">To</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Status</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Code</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Result</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">ECM</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Pages</th>
                                <th v-if="hasActions" class="px-6 py-3 text-right text-sm font-semibold text-heading">Action</th>
                            </tr>
                        </thead>

                        <tbody v-if="!isDataLoading && data.data?.length" class="divide-y divide-default bg-surface">
                            <tr v-if="canDelete && (selectPageItems || selectAll)">
                                <td :colspan="faxColumnCount">
                                    <div class="text-sm text-center m-2">
                                        <span class="font-semibold">{{ selectedItems.length }}</span> items are selected.
                                        <button
                                            v-if="!selectAll && selectedItems.length != data.total"
                                            class="text-info rounded py-2 px-2 hover:bg-info-subtle hover:text-info focus:outline-none focus:ring-1 focus:bg-info-subtle focus:ring-focus transition duration-500 ease-in-out"
                                            @click="handleSelectAll"
                                        >
                                            Select all {{ data.total }} items
                                        </button>
                                        <button
                                            v-if="selectAll"
                                            class="text-info rounded py-2 px-2 hover:bg-info-subtle hover:text-info focus:outline-none focus:ring-1 focus:bg-info-subtle focus:ring-focus transition duration-500 ease-in-out"
                                            @click="handleClearSelection"
                                        >
                                            Clear selection
                                        </button>
                                        <button
                                            class="text-danger rounded py-2 px-2 hover:bg-danger-subtle hover:text-danger focus:outline-none focus:ring-1 focus:bg-danger-subtle focus:ring-focus transition duration-500 ease-in-out"
                                            @click="handleBulkDeleteRequest"
                                        >
                                            Delete selected
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <template v-for="row in data.data" :key="row.fax_log_uuid">
                                <tr @click="toggleExpand(row.fax_log_uuid)" class="hover:bg-surface-2 cursor-pointer">
                                    <td class="w-12 whitespace-nowrap px-6 py-2 text-sm font-medium text-muted">
                                        <input
                                            v-if="canDelete"
                                            v-model="selectedItems"
                                            type="checkbox"
                                            :value="row.fax_log_uuid"
                                            class="h-4 w-4 rounded border-strong text-accent-fg"
                                            @click.stop
                                        />
                                    </td>
                                    <td class="w-12 whitespace-nowrap py-2 pl-6 pr-3 text-sm text-muted">
                                        <div class="relative group inline-flex items-center">
                                            <PhoneOutgoingIcon class="w-5 h-5 text-info" v-if="row.direction === 'outbound'" />
                                            <PhoneIncomingIcon class="w-5 h-5 text-success" v-if="row.direction === 'inbound'" />
                                            <span v-if="!row.direction">{{ directionText(row) }}</span>
                                            <div class="absolute bottom-full left-1/2 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded bg-gray-800 px-2 py-1 text-xs text-white shadow-lg group-hover:block">
                                                {{ directionTooltip(row) }}
                                                <div class="absolute -bottom-1 left-1/2 h-2 w-2 -translate-x-1/2 rotate-45 bg-gray-800"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm font-medium text-muted">
                                        {{ row.fax_date_formatted }}
                                    </td>
                                    <td v-if="showDomainColumn" class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        {{ domainLabel(row) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        {{ faxSource(row) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        {{ faxDestination(row) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        <Badge
                                            :text="statusBadge(row).text"
                                            :backgroundColor="statusBadge(row).backgroundColor"
                                            :textColor="statusBadge(row).textColor"
                                            :ringColor="statusBadge(row).ringColor"
                                        />
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        {{ row.fax_result_code ?? '' }}
                                    </td>
                                    <td class="px-6 py-2 text-sm text-muted" :title="row.fax_result_text ?? ''">
                                        <div class="max-w-md truncate">{{ row.fax_result_text ?? '' }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        {{ row.fax_ecm_used ?? '' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        {{ pagesText(row) }}
                                    </td>
                                    <td v-if="hasActions" class="whitespace-nowrap px-6 py-2 text-sm text-muted" @click.stop>
                                        <div class="flex items-center justify-end">
                                            <div v-if="canRetry(row)" class="relative group flex items-center justify-center cursor-pointer">
                                                <ArrowPathIcon
                                                    @click="handleRetryButtonClick(row)"
                                                    class="h-7 w-7 transition duration-300 ease-in-out p-1 text-subtle hover:bg-surface-3 hover:text-body rounded-full"
                                                />
                                                <div class="absolute bottom-full mb-1 hidden group-hover:block whitespace-nowrap bg-gray-800 text-white text-xs rounded py-1 px-2 z-10 shadow-lg">
                                                    Retry outbound fax
                                                    <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-gray-800 rotate-45"></div>
                                                </div>
                                            </div>
                                            <div v-if="canDelete" class="relative group flex items-center justify-center cursor-pointer">
                                                <TrashIcon
                                                    @click="handleDeleteButtonClick(row.fax_log_uuid)"
                                                    class="h-7 w-7 transition duration-300 ease-in-out p-1 text-subtle hover:bg-surface-3 hover:text-body rounded-full"
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
                                    <td :colspan="faxColumnCount" class="bg-surface-2 px-6 py-4 shadow-inner">
                                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                            <div class="rounded-md border border-default bg-surface p-4">
                                                <div class="text-sm font-semibold text-heading mb-2">Overview</div>
                                                <div class="space-y-2 text-sm text-body">
                                                    <div><span class="font-medium text-body">Log ID:</span> {{ displayValue(row.fax_log_uuid) }}</div>
                                                    <div><span class="font-medium text-body">Fax ID:</span> {{ displayValue(row.fax_uuid) }}</div>
                                                    <div><span class="font-medium text-body">Mode:</span> {{ displayValue(row.fax_file?.fax_mode) }}</div>
                                                    <div><span class="font-medium text-body">Direction:</span> {{ displayValue(directionText(row)) }}</div>
                                                    <div><span class="font-medium text-body">Date:</span> {{ displayValue(row.fax_date_formatted) }}</div>
                                                    <div><span class="font-medium text-body">Duration:</span> {{ displayValue(row.fax_duration) }}</div>
                                                </div>
                                            </div>

                                            <div class="rounded-md border border-default bg-surface p-4">
                                                <div class="text-sm font-semibold text-heading mb-2">Transmission</div>
                                                <div class="space-y-2 text-sm text-body">
                                                    <div><span class="font-medium text-body">From:</span> {{ displayValue(faxSource(row)) }}</div>
                                                    <div><span class="font-medium text-body">To:</span> {{ displayValue(faxDestination(row)) }}</div>
                                                    <div><span class="font-medium text-body">Local Station ID:</span> {{ displayValue(row.fax_local_station_id) }}</div>
                                                    <div><span class="font-medium text-body">Transfer Rate:</span> {{ displayValue(row.fax_transfer_rate) }}</div>
                                                    <div><span class="font-medium text-body">Bad Rows:</span> {{ displayValue(row.fax_bad_rows) }}</div>
                                                    <div><span class="font-medium text-body">Pages:</span> {{ displayValue(pagesText(row)) }}</div>
                                                </div>
                                            </div>

                                            <div class="rounded-md border border-default bg-surface p-4 lg:col-span-2">
                                                <div class="text-sm font-semibold text-heading mb-2">Result</div>
                                                <div class="space-y-2 text-sm text-body">
                                                    <div><span class="font-medium text-body">Code:</span> {{ displayValue(row.fax_result_code) }}</div>
                                                    <div><span class="font-medium text-body">Text:</span> {{ displayValue(row.fax_result_text) }}</div>
                                                    <div><span class="font-medium text-body">URI:</span> <span class="break-all">{{ displayValue(row.fax_uri) }}</span></div>
                                                    <div><span class="font-medium text-body">File:</span> <span class="break-all">{{ displayValue(row.fax_file_path) }}</span></div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <div v-if="!isDataLoading && data.data?.length === 0" class="text-center my-5">
                        <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-subtle" />
                        <h3 class="mt-2 text-sm font-semibold text-heading">No results found</h3>
                    </div>

                    <div v-if="isDataLoading" class="text-center my-5 text-sm text-muted">
                        <div class="animate-pulse flex space-x-4">
                            <div class="flex-1 space-y-6 py-1">
                                <div class="h-2 bg-surface-3 rounded"></div>
                                <div class="h-2 bg-surface-3 rounded"></div>
                                <div class="h-2 bg-surface-3 rounded"></div>
                                <div class="h-2 bg-surface-3 rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <Paginator
                    class="border border-default"
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
import PhoneOutgoingIcon from "./icons/PhoneOutgoingIcon.vue";
import PhoneIncomingIcon from "./icons/PhoneIncomingIcon.vue";
import { ArrowPathIcon, MagnifyingGlassIcon, TrashIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    permissions: Object,
    trigger: Boolean,
    domainOptions: {
        type: Array,
        default: () => [],
    },
    selectedDomainUuid: String,
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
const domainFilterKey = ref(0);

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
    domain_uuid: props.selectedDomainUuid,
    dateRange: [
        startLocal.clone().startOf("day").toISOString(),
        endLocal.clone().endOf("day").toISOString(),
    ],
});

const canDelete = computed(() => Boolean(props.permissions?.fax_log_delete));
const canRetryPermission = computed(() => Boolean(props.permissions?.fax_send));
const hasActions = computed(() => canDelete.value || canRetryPermission.value);
const showDomainFilter = computed(() => props.domainOptions.length > 1);
const showDomainColumn = computed(() => showDomainFilter.value);
const domainFilterOptions = computed(() => [
    { value: "all", label: "All domains" },
    ...props.domainOptions,
]);
const faxColumnCount = computed(() => 10 + (showDomainColumn.value ? 1 : 0) + (hasActions.value ? 1 : 0));

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

const handleUpdateDomainFilter = (newValue) => {
    filterData.value.domain_uuid = typeof newValue === "object"
        ? (newValue?.value ?? null)
        : newValue;
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    filterData.value.status = "all";
    filterData.value.domain_uuid = props.selectedDomainUuid;
    domainFilterKey.value += 1;
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

const handleRetryButtonClick = (row) => {
    if (!canRetry(row)) return;

    const url = props.routes.fax_logs_retry.replace(":faxLog", encodeURIComponent(row.fax_log_uuid));

    axios
        .post(url)
        .then((response) => {
            showNotification("success", response.data.messages);
            handleSearchButtonClick();
        })
        .catch((error) => {
            handleErrorResponse(error);
        });
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

const faxSource = (row) => {
    return row.source_formatted ?? row.source ?? row.fax_file?.fax_caller_id_number_formatted ?? "";
};

const directionText = (row) => {
    return row.direction_label ?? "";
};

const directionTooltip = (row) => {
    return row.direction ? `${row.direction} fax` : "Unknown direction";
};

const domainLabel = (row) => {
    return row.domain?.domain_description || row.domain?.domain_name || "";
};

const faxDestination = (row) => {
    if (row.destination_formatted || row.destination) {
        return row.destination_formatted ?? row.destination;
    }

    if (row.fax_file?.fax_mode === "rx") {
        return row.fax?.fax_caller_id_number_formatted ?? row.fax_file?.fax_caller_id_number_formatted ?? "";
    }

    if (row.fax_file?.fax_mode === "tx") {
        return row.fax_file?.fax_destination_formatted ?? "";
    }

    return "";
};

const isEmptyValue = (value) => value === null || value === undefined || value === "";

const pagesText = (row) => {
    const transferred = row?.fax_document_transferred_pages;
    const total = !isEmptyValue(row?.fax_document_total_pages)
        ? row.fax_document_total_pages
        : row?.outbound_fax?.total_pages;

    if (isEmptyValue(transferred) && isEmptyValue(total)) {
        return "";
    }

    if (isEmptyValue(transferred)) {
        return `0 / ${total}`;
    }

    if (isEmptyValue(total)) {
        return String(transferred);
    }

    return `${transferred} / ${total}`;
};

const canRetry = (row) => {
    return Boolean(
        canRetryPermission.value &&
        props.routes?.fax_logs_retry &&
        row?.outbound_fax_uuid &&
        String(row?.fax_success ?? "0") !== "1" &&
        row?.outbound_fax?.status === "failed"
    );
};

const isRetryRequestedFromRow = (row) => {
    return Boolean(
        row?.fax_log_uuid &&
        row?.outbound_fax?.response?.includes(`Manual retry requested from fax log ${row.fax_log_uuid}`)
    );
};

const statusBadge = (row) => {
    if (String(row?.fax_success ?? "0") === "1") {
        return {
            text: "Success",
            backgroundColor: "bg-success-subtle",
            textColor: "text-success",
            ringColor: "ring-success/20",
        };
    }

    if (isRetryRequestedFromRow(row)) {
        switch (row?.outbound_fax?.status) {
            case "waiting":
                return {
                    text: "Retry queued",
                    backgroundColor: "bg-info-subtle",
                    textColor: "text-info",
                    ringColor: "ring-info/20",
                };
            case "sending":
                return {
                    text: "Sending",
                    backgroundColor: "bg-accent-subtle",
                    textColor: "text-accent-fg",
                    ringColor: "ring-accent/20",
                };
            case "trying":
            case "busy":
                return {
                    text: "Retrying",
                    backgroundColor: "bg-warning-subtle",
                    textColor: "text-warning",
                    ringColor: "ring-warning/20",
                };
            case "sent":
                return {
                    text: "Retried",
                    backgroundColor: "bg-success-subtle",
                    textColor: "text-success",
                    ringColor: "ring-success/20",
                };
        }
    }

    return {
        text: "Failed",
        backgroundColor: "bg-danger-subtle",
        textColor: "text-danger",
        ringColor: "ring-danger/20",
    };
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
