<template>
    <div class="mt-4 flex flex-col">

        <!-- SEARCH & ACTIONS ROW -->
        <div class="flex flex-col sm:flex-row sm:flex-wrap">
            <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <MagnifyingGlassIcon class="h-5 w-5 text-subtle" aria-hidden="true" />
                </div>
                <!-- mobile -->
                <input type="search" v-model="filterData.search" id="mobile-search-inbound-webhooks"
                    class="block w-full rounded-md border-0 py-1.5 pl-10 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:hidden"
                    placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                <!-- desktop -->
                <input type="search" v-model="filterData.search" id="desktop-search-inbound-webhooks"
                    class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:block"
                    placeholder="Search" @keydown.enter="handleSearchButtonClick" />
            </div>

            <!-- DATE RANGE -->
            <div class="relative z-10 min-w-64 -mt-0.5 mb-2 scale-y-95 shrink-0 sm:mr-4">
                <DatePicker :dateRange="filterData.dateRange" :timezone="timezone"
                    @update:date-range="handleUpdateDateRange" />
            </div>

            <div class="relative">
                <div class="flex justify-between">
                    <button type="button" @click.prevent="handleSearchButtonClick"
                        class="rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover
                     focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                        Search
                    </button>

                    <button type="button" @click.prevent="handleFiltersReset"
                        class="rounded-md bg-surface px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- TABLE + PAGINATION -->
        <div class="mt-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <Paginator class="border border-default" :previous="data.prev_page_url" :next="data.next_page_url"
                    :from="data.from" :to="data.to" :total="data.total" :currentPage="data.current_page"
                    :lastPage="data.last_page" :links="data.links" @pagination-change-page="renderRequestedPage" />

                <div class="overflow-hidden-t border-l border-r border-default">
                    <table class="min-w-full divide-y divide-default mb-4">
                        <thead class="bg-surface-3">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Date</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">In/Out</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Source</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Destination</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Message</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Type</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Status</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Action</th>

                            </tr>
                        </thead>

                        <tbody v-if="!isDataLoading && data.data?.length" class="divide-y divide-default bg-surface">
                            <template v-for="row in data.data" :key="row.message_uuid">
                                <tr @click="toggleExpand(row.message_uuid)" class="hover:bg-surface-2 cursor-pointer">
                                    <td class="whitespace-nowrap px-6 py-2 text-sm font-medium text-muted">
                                        {{ row.created_at_formatted ?? row.created_at }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        {{ row.direction }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        {{ row.source_formatted }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        {{ row.destination_formatted }}
                                    </td>
                                    <td class="px-6 py-2 text-sm text-muted" :title="row.message_preview">
                                        <div class="max-w-sm">
                                            <div class="truncate">
                                                {{ row.message_preview }}
                                            </div>
                                            <div class="mt-1 text-xs text-subtle">
                                                <span v-if="row.provider_name">{{ row.provider_name }}</span>
                                                <span v-if="row.provider_name && row.has_media"> • </span>
                                                <span v-if="row.has_media">📎 {{ row.media_count }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        {{ row.type }}
                                    </td>
                                    <td class="px-6 py-2 text-sm text-muted"
                                        :title="row.status_summary || row.status">
                                        <Badge :text="truncateText(row.status_summary || row.status, 60)"
                                            :backgroundColor="determineColor(row.status_summary || row.status).backgroundColor"
                                            :textColor="determineColor(row.status_summary || row.status).textColor"
                                            :ringColor="determineColor(row.status_summary || row.status).ringColor" />
                                    </td>

                                    <!-- ACTION COLUMN WITH RETRY ICON -->
                                    <td class="whitespace-nowrap px-6 py-2 text-sm font-medium select-none" @click.stop>
                                        <div class="flex items-center gap-4">
                                            <!-- RETRY ICON -->
                                            <div class="relative group flex items-center justify-center cursor-pointer">
                                                <RestartIcon @click="handleRetry(row.message_uuid)"
                                                    class="h-7 w-7 transition duration-300 ease-in-out p-1 text-subtle hover:bg-surface-3 hover:text-body rounded-full" />

                                                <!-- TOOLTIP -->
                                                <div
                                                    class="absolute bottom-full mb-1 hidden group-hover:block whitespace-nowrap bg-gray-800 text-white text-xs rounded py-1 px-2 z-10 shadow-lg">
                                                    Retry Message
                                                    <!-- Little down arrow -->
                                                    <div
                                                        class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-gray-800 rotate-45">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>


                                </tr>

                                <!-- EXPANDED DETAILS -->
                                <tr v-if="expandedRow === row.message_uuid">
                                    <td :colspan="8" class="bg-surface-2 px-6 py-4 shadow-inner">
                                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                            <div class="rounded-md border border-default bg-surface p-4">
                                                <div class="text-heading font-semibold text-sm mb-2">Overview</div>

                                                <div class="space-y-2 text-sm">
                                                    <div><span class="font-medium text-body">Message ID:</span> {{
                                                        row.message_uuid }}</div>
                                                    <div><span class="font-medium text-body">Provider:</span> {{
                                                        row.provider_name || '—' }}</div>
                                                    <div><span class="font-medium text-body">Reference ID:</span> {{
                                                        row.reference_id || '—' }}</div>
                                                    <div><span class="font-medium text-body">Direction:</span> {{
                                                        row.direction }}</div>
                                                    <div><span class="font-medium text-body">Type:</span> {{
                                                        row.type }}</div>
                                                    <div><span class="font-medium text-body">Status Summary:</span>
                                                        {{ row.status_summary || row.status }}</div>
                                                    <div><span class="font-medium text-body">Read At:</span> {{
                                                        row.read_at || '—' }}</div>
                                                </div>
                                            </div>

                                            <div class="rounded-md border border-default bg-surface p-4">
                                                <div class="text-heading font-semibold text-sm mb-2">Message</div>
                                                <pre
                                                    class="text-body text-sm whitespace-pre-wrap break-words">{{ row.message || '—' }}</pre>
                                            </div>

                                            <div class="rounded-md border border-default bg-surface p-4 lg:col-span-2"
                                                v-if="hasMedia(row)">
                                                <div class="text-heading font-semibold text-sm mb-2">Attachments</div>

                                                <div class="space-y-2">
                                                    <div v-for="(item, index) in row.media"
                                                        :key="`${row.message_uuid}-${index}`"
                                                        class="flex items-center justify-between rounded border border-default px-3 py-2 text-sm">
                                                        <div class="min-w-0">
                                                            <div class="truncate font-medium text-heading">
                                                                {{ item.original_name || item.stored_name || `Attachment
                                                                ${index + 1}` }}
                                                            </div>
                                                            <div class="text-xs text-muted">
                                                                {{ item.mime_type || 'unknown' }}
                                                                <span v-if="item.size"> • {{ item.size }} bytes</span>
                                                            </div>
                                                        </div>

                                                        <a v-if="item.access_path" :href="item.access_path"
                                                            target="_blank"
                                                            class="ml-4 text-sm font-medium text-accent-fg hover:text-accent-fg"
                                                            @click.stop>
                                                            Open
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="rounded-md border border-default bg-surface p-4 lg:col-span-2">
                                                <div class="text-heading font-semibold text-sm mb-2">Delivery Meta
                                                </div>
                                                <pre
                                                    class="text-body text-sm whitespace-pre-wrap break-words">{{ prettyJson(row.delivery_meta) }}</pre>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <!-- EMPTY STATE -->
                    <div v-if="!isDataLoading && data.data?.length === 0" class="text-center my-5">
                        <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-subtle" />
                        <h3 class="mt-2 text-sm font-semibold text-heading">No results found</h3>
                    </div>

                    <!-- LOADING -->
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

                <!-- BOTTOM PAGER -->
                <Paginator class="border border-default" :previous="data.prev_page_url" :next="data.next_page_url"
                    :from="data.from" :to="data.to" :total="data.total" :currentPage="data.current_page"
                    :lastPage="data.last_page" :links="data.links" @pagination-change-page="renderRequestedPage" />
            </div>
        </div>
    </div>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

    <!-- CONFIRMATION MODAL -->
    <ConfirmationModal :show="showRetryConfirmationModal" @close="handleModalClose" @confirm="confirmRetryAction"
        :header="'Are you sure?'"
        :text="'Are you sure you want to retry sending the selected messages? This action will attempt to resend them immediately.'"
        :confirm-button-label="'Retry'" cancel-button-label="Cancel" />
</template>


<script setup>
import { ref, watch } from "vue";
import Paginator from "@generalComponents/Paginator.vue";
import moment from 'moment-timezone';
import DatePicker from "@generalComponents/DatePicker.vue";
import Notification from "./notifications/Notification.vue";
import {
    MagnifyingGlassIcon,
} from "@heroicons/vue/24/solid";
import Badge from "@generalComponents/Badge.vue";
import RestartIcon from "./icons/RestartIcon.vue";
import ConfirmationModal from "./modal/ConfirmationModal.vue";



const isDataLoading = ref(false)
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
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
const selectedItems = ref([]);
const selectPageItems = ref(false);
const selectAll = ref(false);
const expandedRow = ref(null)
const showRetryConfirmationModal = ref(false);
const confirmRetryAction = ref(null);

const props = defineProps({
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    permissions: Object,
    trigger: Boolean
});

const startLocal = moment.utc(props.startPeriod).tz(props.timezone)
const endLocal = moment.utc(props.endPeriod).tz(props.timezone)

const dateRange = [
    startLocal.clone().startOf('day').toISOString(), // UTC instant for local start-of-day
    endLocal.clone().endOf('day').toISOString(),     // UTC instant for local end-of-day
]

const filterData = ref({
    search: props.search,
    showGlobal: props.showGlobal,
    dateRange: dateRange,
    // dateRange: ['2024-07-01T00:00:00', '2024-07-01T23:59:59'],

});

const fetchData = async (page = 1) => {
    isDataLoading.value = true
    axios.get(props.routes.message_logs, {
        params: {
            filter: filterData.value,
            page,
        }
    })
        .then((response) => {
            data.value = response.data;
            // console.log(data.value);

        }).catch((error) => {
            handleErrorResponse(error)
        }).finally(() => {
            isDataLoading.value = false
        });
}


watch(() => props.trigger, (newVal) => {
    fetchData(1)
})


const handleSearchButtonClick = () => {
    fetchData(1)
};

const toggleExpand = (id) => {
    expandedRow.value = expandedRow.value === id ? null : id
}

const truncateText = (value, length = 50) => {
    if (!value) return '—'
    return value.length > length ? value.substring(0, length) + '...' : value
}

const prettyJson = (value) => {
    if (!value) return '—'
    try {
        return JSON.stringify(value, null, 2)
    } catch {
        return String(value)
    }
}

const hasMedia = (row) => Array.isArray(row.media) && row.media.length > 0

const handleFiltersReset = () => {
    filterData.value.dateRange = [
        startLocal.clone().startOf('day').toISOString(), // UTC instant for local start-of-day
        endLocal.clone().endOf('day').toISOString(),     // UTC instant for local end-of-day
    ]
    filterData.value.search = null;

    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    handleSearchButtonClick();
}

const determineColor = (status) => {
    const value = (status || '').toLowerCase()

    if (value.includes('failed') || value.includes('error') || value.includes('undelivered')) {
        return {
            backgroundColor: 'bg-danger-subtle',
            textColor: 'text-danger',
            ringColor: 'ring-danger/20'
        }
    }

    if (value.includes('queued') || value.includes('received')) {
        return {
            backgroundColor: 'bg-info-subtle',
            textColor: 'text-info',
            ringColor: 'ring-info/20'
        }
    }

    if (value.includes('success') || value.includes('delivered') || value.includes('emailed')) {
        return {
            backgroundColor: 'bg-success-subtle',
            textColor: 'text-success',
            ringColor: 'ring-success/20'
        }
    }

    return {
        backgroundColor: 'bg-warning-subtle',
        textColor: 'text-warning',
        ringColor: 'ring-warning/20'
    }
}

const renderRequestedPage = (url) => {
    isDataLoading.value = true;
    // Extract the page number from the url, e.g. "?page=3"
    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get("page") ?? 1;

    // Now call getData with the page number
    fetchData(pageParam);
};


const handleUpdateDateRange = (newDateRange) => {
    filterData.value.dateRange = newDateRange;
}

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_retry') {
        showRetryConfirmationModal.value = true;
        confirmRetryAction.value = () => executeBulkRetry();
    }
}

const handleClearSelection = () => {
    selectedItems.value = [];
    selectPageItems.value = false;
    selectAll.value = false;
}

const handleRetry = (uuid) => {
    // Single message sent in array format to bulk endpoint
    axios.post(props.routes.message_retry, { 'items': [uuid] })
        .then((response) => {
            showNotification('success', response.data.messages);
            handleClearSelection();
        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        }).finally(() => {
            fetchData(data.value.current_page || 1);
        });
}

const executeBulkRetry = () => {
    axios.post(props.routes.retry, { 'items': selectedItems.value })
        .then((response) => {
            showNotification('success', response.data.messages);
            handleModalClose();
            handleClearSelection();
        }).catch((error) => {
            handleClearSelection();
            handleModalClose();
            handleErrorResponse(error);
        }).finally(() => {
            fetchData(data.value.current_page || 1);
        });
}

const handleModalClose = () => {
    showRetryConfirmationModal.value = false;
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

</script>
