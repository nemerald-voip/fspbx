<template>
    <MainLayout>

        <div class="m-3">
            <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
                <template #title>Statistics by Extension</template>

                <template #action>
                    <button
                        v-if="page.props.auth?.can?.cdrs_export || page.props.auth?.can?.xml_cdr_export"
                        type="button"
                        @click.prevent="exportCsv"
                        :disabled="isExporting"
                        class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <DocumentArrowDownIcon class="h-5 w-5" aria-hidden="true" />
                        Export CSV
                        <Spinner class="ml-1" :show="isExporting" />
                    </button>

                    <!-- <button v-if="!showGlobal && page.props.auth.can.cdrs_view_global" type="button"
                        @click.prevent="handleShowGlobal()"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Show global
                    </button>

                    <button v-if="showGlobal && page.props.auth.can.cdrs_view_global" type="button"
                        @click.prevent="handleShowLocal()"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Show local
                    </button> -->
                </template>

                <template #filters>
                    <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                        </div>
                        <input
                            type="search"
                            v-model="filterData.search"
                            name="mobile-search-candidate"
                            id="mobile-search-candidate"
                            class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                            placeholder="Search"
                            @keydown.enter="handleSearchButtonClick"
                        />
                        <input
                            type="search"
                            v-model="filterData.search"
                            name="desktop-search-candidate"
                            id="desktop-search-candidate"
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
                </template>

                <template #navigation>
                    <Paginator
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
                </template>

                <template #table-header>
                    <TableColumnHeader
                        header="Extension"
                        class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6"
                    />
                    <TableColumnHeader
                        header="Total Calls"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"
                    />
                    <TableColumnHeader
                        header="Inbound"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"
                    />
                    <TableColumnHeader
                        header="Outbound"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"
                    />
                    <TableColumnHeader
                        header="Missed"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"
                    />
                    <TableColumnHeader
                        header="Total Talk"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"
                    />
                    <TableColumnHeader
                        header="Avg Call Duration"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"
                    />
                </template>

                <template #table-body>
                    <tr v-for="row in data.data" :key="row.extension_uuid">
                        <TableField
                            class="whitespace-nowrap py-3.5 pl-4 pr-3 text-sm text-gray-500"
                            :text="row.extension_label"
                        />
                        <TableField
                            class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.call_count"
                        />
                        <TableField
                            class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.inbound"
                        />
                        <TableField
                            class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.outbound"
                        />
                        <TableField
                            class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.missed"
                        />
                        <TableField
                            class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.total_talk_time_formatted"
                        />
                        <TableField
                            class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.average_duration_formatted"
                        />
                    </tr>
                </template>

                <template #empty>
                    <div v-if="data.data.length === 0" class="text-center my-5">
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
                    <Paginator
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
                </template>
            </DataTable>
        </div>
    </MainLayout>

    <Notification
        :show="notificationShow"
        :type="notificationType"
        :messages="notificationMessages"
        @update:show="hideNotification"
    />
</template>

<script setup>
import axios from 'axios';
import { ref, onMounted } from "vue";
import { usePage } from '@inertiajs/vue3'
import MainLayout from '../Layouts/MainLayout.vue'
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import moment from 'moment-timezone';
import DatePicker from "./components/general/DatePicker.vue";
import Notification from "./components/notifications/Notification.vue";
import Spinner from "./components/general/Spinner.vue";
import { DocumentArrowDownIcon } from "@heroicons/vue/24/outline";
import {
    MagnifyingGlassIcon,
} from "@heroicons/vue/24/solid";
import Loading from "./components/general/Loading.vue";

const page = usePage();

const loading = ref(false)
const viewModalTrigger = ref(false);
const loadingModal = ref(false)
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const isExporting = ref(false);

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

const props = defineProps({
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
});

onMounted(() => {
    handleSearchButtonClick();
})

const startLocal = moment.utc(props.startPeriod).tz(props.timezone)
const endLocal = moment.utc(props.endPeriod).tz(props.timezone)

const dateRange = [
    startLocal.clone().startOf('day').toISOString(),
    endLocal.clone().endOf('day').toISOString(),
]

const filterData = ref({
    search: null,
    showGlobal: false,
    dateRange: dateRange,
});

const getData = (page = 1) => {
    loading.value = true;

    axios.get(props.routes.data_route, {
        params: {
            filter: filterData.value,
            page,
        }
    })
        .then((response) => {
            data.value = response.data;
        }).catch((error) => {
            handleErrorResponse(error);
        }).finally(() => {
            loading.value = false
        })
}

const handleSearchButtonClick = () => {
    getData()
};

const handleFiltersReset = () => {
    filterData.value.dateRange = [
        startLocal.clone().startOf('day').toISOString(),
        endLocal.clone().endOf('day').toISOString(),
    ];
    filterData.value.search = null;

    handleSearchButtonClick();
}

const renderRequestedPage = (url) => {
    loading.value = true;
    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get("page") ?? 1;
    getData(pageParam);
};

const handleUpdateDateRange = (newDateRange) => {
    filterData.value.dateRange = newDateRange;
}

const exportCsv = async () => {
    isExporting.value = true;

    try {
        const response = await axios.get(props.routes.export, {
            params: {
                filter: filterData.value,
            },
            responseType: 'blob',
        });

        const contentType = response.headers['content-type'] || '';

        // If backend returned JSON/error payload as blob instead of CSV
        if (contentType.includes('application/json')) {
            const text = await response.data.text();
            const json = JSON.parse(text);

            showNotification('error', json.errors || json.messages || { request: ['Export failed'] });
            return;
        }

        const blob = new Blob([response.data], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'extension_statistics.csv';
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        // Handle blob error responses cleanly
        if (error.response?.data instanceof Blob) {
            try {
                const text = await error.response.data.text();
                const json = JSON.parse(text);
                showNotification('error', json.errors || json.messages || { request: ['Export failed'] });
            } catch {
                showNotification('error', { request: ['Export failed. Server returned a non-JSON error response.'] });
            }
        } else {
            handleErrorResponse(error);
        }
    } finally {
        isExporting.value = false;
    }
};

const handleModalClose = () => {
    viewModalTrigger.value = false;
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
        if (error.response.data instanceof Blob) {
            showNotification('error', { request: ['The server returned a file/blob error response instead of JSON.'] });
            return;
        }

        showNotification('error', error.response.data.errors || { request: [error.message] });
    } else if (error.request) {
        showNotification('error', { request: ['No response received from server.'] });
        console.log(error.request);
    } else {
        showNotification('error', { request: [error.message] });
        console.log(error.message);
    }
}
</script>
