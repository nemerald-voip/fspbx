<template>
    <div class="mt-4 flex flex-col">

        <!-- SEARCH & ACTIONS ROW -->
        <div class="flex flex-col sm:flex-row sm:flex-wrap">
            <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                </div>
                <!-- mobile -->
                <input type="search" v-model="filterData.search" id="mobile-search-inbound-webhooks"
                    class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                    placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                <!-- desktop -->
                <input type="search" v-model="filterData.search" id="desktop-search-inbound-webhooks"
                    class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
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
                        class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500
                     focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Search
                    </button>

                    <button type="button" @click.prevent="handleFiltersReset"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- TABLE + PAGINATION -->
        <div class="mt-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <Paginator class="border border-gray-200" :previous="data.prev_page_url" :next="data.next_page_url"
                    :from="data.from" :to="data.to" :total="data.total" :currentPage="data.current_page"
                    :lastPage="data.last_page" :links="data.links" @pagination-change-page="renderRequestedPage" />

                <div class="overflow-hidden-t border-l border-r border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 mb-4">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Date</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Name</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">URL</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Payload</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Exception</th>
                            </tr>
                        </thead>

                        <tbody v-if="!isDataLoading && data.data?.length" class="divide-y divide-gray-200 bg-white">
                            <template v-for="row in data.data" :key="row.id">
                                <tr @click="toggleExpand(row.id)" class="hover:bg-gray-50 cursor-pointer">
                                    <td class="whitespace-nowrap px-6 py-2 text-sm font-medium text-gray-500">
                                        {{ row.created_at_formatted ?? row.created_at }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        {{ row.name }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        {{ row.url }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-blue-600">
                                        <span class="underline">Click for details…</span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        {{ row.exception }}
                                    </td>
                                </tr>

                                <!-- EXPANDED DETAILS -->
                                <tr v-if="expandedRow === row.id">
                                    <td :colspan="5" class="bg-gray-50 px-6 py-4">
                                        <div class="text-gray-500 text-sm mb-1">Payload</div>
                                        <pre
                                            class="text-gray-700 text-sm whitespace-pre-wrap break-words">{{ row.payload }}</pre>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <!-- EMPTY STATE -->
                    <div v-if="!isDataLoading && data.data?.length === 0" class="text-center my-5">
                        <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                    </div>

                    <!-- LOADING -->
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

                <!-- BOTTOM PAGER -->
                <Paginator class="border border-gray-200" :previous="data.prev_page_url" :next="data.next_page_url"
                    :from="data.from" :to="data.to" :total="data.total" :currentPage="data.current_page"
                    :lastPage="data.last_page" :links="data.links" @pagination-change-page="renderRequestedPage" />
            </div>
        </div>
    </div>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
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


const isDataLoading = ref(false)
const viewModalTrigger = ref(false);
const loadingModal = ref(false)
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const isExporting = ref(null);
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
const expandedRow = ref(null)


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
    axios.get(props.routes.inbound_webhooks, {
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

const handleFiltersReset = () => {
    filterData.value.dateRange = [
        startLocal.clone().startOf('day').toISOString(), // UTC instant for local start-of-day
        endLocal.clone().endOf('day').toISOString(),     // UTC instant for local end-of-day
    ]
    filterData.value.search = null;

    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    handleSearchButtonClick();
}

const renderRequestedPage = (url) => {
    loading.value = true;
    // Extract the page number from the url, e.g. "?page=3"
    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get("page") ?? 1;

    // Now call getData with the page number
    getData(pageParam);
};


const handleUpdateDateRange = (newDateRange) => {
    filterData.value.dateRange = newDateRange;
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
