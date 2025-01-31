
<template>
    <MainLayout>

        <div class="m-3">
            <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
                <template #title>Fax Queue</template>

                <template #action>

                    <!-- <button v-if="page.props.auth.can.cdrs_export" type="button" @click.prevent="exportCsv"
                        :disabled="isExporting"
                        class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        <DocumentArrowDownIcon class="h-5 w-5" aria-hidden="true" />
                        Export CSV
                        <Spinner class="ml-1" :show="isExporting" />
                    </button> -->

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
                        <input type="search" v-model="filterData.search" name="mobile-search-candidate"
                            id="mobile-search-candidate"
                            class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                            placeholder="Search" />
                        <input type="search" v-model="filterData.search" name="desktop-search-candidate"
                            id="desktop-search-candidate"
                            class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                            placeholder="Search" />
                    </div>


                    <div class="relative z-10 min-w-64 -mt-0.5 mb-2 scale-y-95 shrink-0 sm:mr-4">
                        <DatePicker :dateRange="filterData.dateRange" :timezone="filterData.timezone"
                            @update:date-range="handleUpdateDateRange" />
                    </div>

                    <!-- <div class="relative min-w-36 mb-2 shrink-0 sm:mr-4">
                        <SelectBox :options="callDirections" :selectedItem="filterData.direction"
                            :placeholder="'Call Direction'" @update:model-value="handleUpdateCallDirectionFilter" />
                    </div> -->


                </template>

                <template #navigation>
                    <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                        :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                        @pagination-change-page="renderRequestedPage" />
                </template>
                <template #table-header>
                    <TableColumnHeader
                        class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                        <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                        <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest"
                            :has-selected-items="selectedItems.length > 0" />
                        <span class="pl-4">From</span>
                    </TableColumnHeader>

                    <TableColumnHeader header="To" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Email" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Date" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Status" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Last Attempt"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Retry Count"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Notify Date"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>

                    <TableColumnHeader header="" class="px-2 py-3.5 text-sm font-semibold text-center text-gray-900" />

                </template>

                <template v-if="selectPageItems" v-slot:current-selection>
                    <td colspan="6">
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
                    <tr v-for="row in data.data" :key="row.fax_queue_uuid">

                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500" :text="row.fax_caller_id_number">
                        <div class="flex items-center">
                            <input v-if="row.fax_queue_uuid" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.fax_queue_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-9"
                                :class="{ 'cursor-pointer hover:text-gray-900': page.props.auth.can.voicemail_update, }"
                                @click="page.props.auth.can.voicemail_update && handleEditRequest(row.fax_queue_uuid)">
                                <span class="flex items-center">
                                    <UserIcon class="mr-2 h-5 w-5 text-indigo-500" />
                                    {{ row.fax_caller_id_number }}
                                </span>
                            </div>
                        </div>
                    </TableField>

                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.fax_number" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.fax_email_address" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.fax_date" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.fax_status" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.fax_retry_date" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.fax_retry_count" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.fax_notify_date" />
                    </tr>
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
        </div>
    </MainLayout>


    <CallDetailsModal :show="viewModalTrigger" :item="itemData" :loading="loadingModal" :customClass="'sm:max-w-4xl'"
        @close="handleModalClose">
    </CallDetailsModal>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { usePage } from '@inertiajs/vue3'
import { router } from "@inertiajs/vue3";
import MainLayout from '../Layouts/MainLayout.vue'
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import moment from 'moment-timezone';
import { registerLicense } from '@syncfusion/ej2-base';
import DatePicker from "./components/general/DatePicker.vue";
import CallDetailsModal from "./components/modal/CallDetailsModal.vue"
import Notification from "./components/notifications/Notification.vue";
import {
    MagnifyingGlassIcon,
} from "@heroicons/vue/24/solid";
import { DocumentArrowDownIcon } from "@heroicons/vue/24/outline";

import {
    startOfDay, endOfDay,
} from 'date-fns';
import Loading from "./components/general/Loading.vue";

const page = usePage()
const today = new Date();
const loading = ref(false)
const viewModalTrigger = ref(false);
const loadingModal = ref(false)
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const isExporting = ref(null);
const selectedItems = ref([]);
const selectPageItems = ref(false);


const props = defineProps({
    data: Object,
    showGlobal: Boolean,
    startPeriod: String,
    endPeriod: String,
    search: String,
    timezone: String,
    csvUrl: Object,
    routes: Object,
    itemData: Object,
    statusOptions: Object,
});

onMounted(() => {
    //request list of entities
    // getEntities();
    if (props.data.data.length === 0) {
        handleSearchButtonClick();
    }
})


const filterData = ref({
    search: props.search,
    showGlobal: props.showGlobal,
    dateRange: [moment.tz(props.startPeriod, props.timezone).startOf('day').format(), moment.tz(props.endPeriod, props.timezone).endOf('day').format()],
    // dateRange: ['2024-07-01T00:00:00', '2024-07-01T23:59:59'],
    timezone: props.timezone,

});

const handleSearchButtonClick = () => {
    loading.value = true;

    router.visit(props.routes.current_page, {
        data: {
            filterData: filterData._rawValue,
        },
        preserveScroll: true,
        preserveState: true,
        only: [
            "data",
        ],
        onSuccess: (page) => {
            loading.value = false;
        },
        onError: (error) => {
            loading.value = false;
            handleErrorResponse(error);
        }

    });
};

const handleFiltersReset = () => {
    filterData.value.dateRange = [startOfDay(today), endOfDay(today)];

    filterData.value.search = null;
    filterData.value.direction = null;
    filterData.value.entity = null;
    filterData.value.entityType = null;
    filterData.value.statuses = [];

    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    handleSearchButtonClick();
}

const renderRequestedPage = (url) => {
    loading.value = true;
    router.visit(url, {
        data: {
            filterData: filterData._rawValue,
        },
        preserveScroll: true,
        preserveState: true,
        only: ["data"],
        onSuccess: (page) => {
            loading.value = false;
        }

    });
};

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [
        {
            id: 'bulk_retry',
            label: 'Retry',
            icon: 'PencilSquareIcon'
        }
    ];

    return actions;
});

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        confirmationModalTrigger.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }
    if (action === 'bulk_update') {
        formErrors.value = [];
        getItemOptions();
        loadingModal.value = true
        bulkUpdateModalTrigger.value = true;
    }

}


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

const handleSelectPageItems = () => {
    if (selectPageItems.value) {
        selectedItems.value = props.data.data.map(item => item.voicemail_uuid);
    } else {
        selectedItems.value = [];
    }
};



</script>

