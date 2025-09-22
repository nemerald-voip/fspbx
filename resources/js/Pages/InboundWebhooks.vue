<template>
    <MainLayout>

        <div class="m-3">
            <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
                <template #title>Inbound Webhooks</template>

                <template #action>

                </template>

                <template #filters>
                    <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                        </div>
                        <input type="search" v-model="filterData.search" name="mobile-search-candidate"
                            id="mobile-search-candidate"
                            class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                            placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                        <input type="search" v-model="filterData.search" name="desktop-search-candidate"
                            id="desktop-search-candidate"
                            class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                            placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                    </div>


                    <div class="relative z-10 min-w-64 -mt-0.5 mb-2 scale-y-95 shrink-0 sm:mr-4">
                        <DatePicker :dateRange="filterData.dateRange" :timezone="timezone"
                            @update:date-range="handleUpdateDateRange" />
                    </div>

                    <!-- <div class="relative min-w-36 mb-2 shrink-0 sm:mr-4">
                        <SelectBox :options="callDirections" :selectedItem="filterData.direction"
                            :placeholder="'Call Direction'" @update:model-value="handleUpdateCallDirectionFilter" />
                    </div> -->


                </template>

                <template #navigation>
                    <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                        :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page"
                        :links="data.links" @pagination-change-page="renderRequestedPage" />
                </template>
                <template #table-header>
                    <TableColumnHeader header="Date"
                        class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">
                    </TableColumnHeader>

                    <TableColumnHeader header="Name"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                    <TableColumnHeader header="URL" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>

                    <TableColumnHeader header="Payload"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                    <TableColumnHeader header="Exception"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>

                    <!-- <TableColumnHeader header="Actions"
                        class="px-2 py-3.5 text-sm font-semibold text-center text-gray-900" /> -->

                </template>

                <template #table-body>
                    <template v-for="row in data.data" :key="row.extension_uuid">
                        <tr @click="toggleExpand(row.id)">

                            <TableField class="whitespace-nowrap py-3.5 pl-4 pr-3 text-sm text-gray-500"
                                :text="row.created_at_formatted ?? row.created_at" />
                            <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.name" />
                            <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.url" />
                            <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" >
                                <span class="text-sm text-blue-500 cursor-pointer">Click for details...</span>
                                </TableField>
                            <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                                :text="row.exception" />
                        </tr>
                        <!-- EXPANDABLE ROW -->
                        <tr v-if="expandedRow === row.id">
                            <td :colspan="5" class="bg-gray-50 px-6 py-4">

                                <div class="text-gray-400 text-sm ">Payload</div>
                                <div class="text-gray-400 text-sm "> {{ row.payload }}</div>

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
                        :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page"
                        :links="data.links" @pagination-change-page="renderRequestedPage" />
                </template>


            </DataTable>
        </div>
    </MainLayout>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
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
import {
    MagnifyingGlassIcon,
} from "@heroicons/vue/24/solid";

import Loading from "./components/general/Loading.vue";

const loading = ref(false)
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
});

onMounted(() => {
    handleSearchButtonClick();
})

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

const getData = (page = 1) => {
    loading.value = true;

    // console.log(filterData.value);

    axios.get(props.routes.data_route, {
        params: {
            filter: filterData.value,
            page,
        }
    })
        .then((response) => {
            data.value = response.data;
            //console.log(data.value);

        }).catch((error) => {

            handleErrorResponse(error);
        }).finally(() => {
            loading.value = false
        })
}

const handleSearchButtonClick = () => {
    getData()
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

const exportCsv = () => {
    isExporting.value = true;

    axios.post(props.routes.export, {
        filterData: filterData._rawValue,
    })
        .then(response => {
            showNotification('success', response.data.messages);
            isExporting.value = false;
        })
        .catch(error => {
            console.error('There was an error with the request:', error);
            isExporting.value = false;
            handleErrorResponse(error);
        });


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
