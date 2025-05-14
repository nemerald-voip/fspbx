
<template>
    <MainLayout>

        <div class="m-3">
            <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
                <template #title>User Logs</template>

                <template #action>

                    <!-- <button v-if="page.props.auth.can.cdrs_export" type="button" @click.prevent="exportCsv"
                        :disabled="isExporting"
                        class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        <DocumentArrowDownIcon class="h-5 w-5" aria-hidden="true" />
                        Export CSV
                        <Spinner class="ml-1" :show="isExporting" />
                    </button> -->

                    <button v-if="!filterData.showGlobal" type="button" @click.prevent="handleShowGlobal()"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Show global
                    </button>

                    <button v-if="filterData.showGlobal" type="button" @click.prevent="handleShowLocal()"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Show local
                    </button>

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
                <!-- <template #table-header>
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
                    <TableColumnHeader v-if="filterData.showGlobal" header="Domain"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
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

                </template> -->


                <template #table-header>
                    <!-- <TableColumnHeader
                        class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                        <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                        <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest"
                            :has-selected-items="selectedItems.length > 0" />
                        <span class="pl-4">From</span>
                    </TableColumnHeader> -->

                    <TableColumnHeader
                        class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                        <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                        <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest"
                            :has-selected-items="selectedItems.length > 0" />
                        <span class="pl-4">Username</span>
                    </TableColumnHeader>

                    <!-- Email -->
                    <TableColumnHeader header="Email" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                    <!-- Domain (when global) -->
                    <TableColumnHeader v-if="filterData.showGlobal" header="Domain"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                    <!-- Type -->
                    <TableColumnHeader header="Type" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                    <!-- Result -->
                    <TableColumnHeader header="Result" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                    <!-- Timestamp -->
                    <TableColumnHeader header="Date" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                    <!-- IP Address -->
                    <TableColumnHeader header="IP Address"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                    <!-- User Agent -->
                    <TableColumnHeader header="User Agent"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />


                    <!-- Empty for any action buttons -->
                    <TableColumnHeader header="" class="px-2 py-3.5 text-center text-sm font-semibold text-gray-900" />
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
                    <tr v-for="row in data.data" :key="row.user_log_uuid">
                        <!-- Username + checkbox -->
                        <!-- <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                            <div class="flex items-center">
                                <input v-model="selectedItems" type="checkbox" name="action_box[]"
                                    :value="row.user_log_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                                <div class="ml-4">
                                    {{ row.user.name_formatted }}
                                </div>
                            </div>
                        </TableField> -->


                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500"
                            :text="row.fax_caller_id_number">
                            <div class="flex items-center">
                                <input v-if="row.user_log_uuid" v-model="selectedItems" type="checkbox" name="action_box[]"
                                    :value="row.user_log_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                                <div class="ml-9">
                                    <span class="flex items-center">
                                        {{ row.user.name_formatted }}
                                    </span>
                                </div>
                            </div>
                        </TableField>

                        <!-- Email -->
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.user.user_email" />

                        <!-- Domain (when global) -->
                        <!-- <TableField v-if="filterData.showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.domain?.domain_description" /> -->

                        <TableField v-if="filterData.showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.domain?.domain_description">
                            <ejs-tooltip :content="row.domain?.domain_name" position='TopLeft'
                                target="#domain_tooltip_target">
                                <div id="domain_tooltip_target">
                                    {{ row.domain?.domain_description }}
                                </div>
                            </ejs-tooltip>
                        </TableField>

                        <!-- Type -->
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.type" />

                        <!-- Result -->
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            <Badge :text="row.result" :backgroundColor="determineColor(row.result).backgroundColor"
                                :textColor="determineColor(row.result).textColor"
                                :ringColor="determineColor(row.result).ringColor" />
                        </TableField>

                        <!-- Timestamp (localized) -->
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="moment.tz(row.timestamp, filterData.timezone).format('YYYY-MM-DD HH:mm:ss')" />

                        <!-- IP Address -->
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.remote_address" />

                        <!-- User Agent -->
                        <TableField class=" px-2 py-2 text-sm text-gray-500" :text="row.user_agent" />

                        <!-- (Optional) action buttons -->
                        <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                            <!-- e.g. a “View Details” or nothing at all -->
                        </TableField>
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


    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

    <ConfirmationModal :show="showRetryConfirmationModal" @close="showRetryConfirmationModal = false"
        @confirm="confirmRetryAction" :header="'Are you sure?'"
        :text="'Are you sure you want to retry sending the selected faxes? This action will attempt to resend them immediately.'"
        :confirm-button-label="'Retry'" cancel-button-label="Cancel" />
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
import Notification from "./components/notifications/Notification.vue";
import {
    MagnifyingGlassIcon,
} from "@heroicons/vue/24/solid";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import RestartIcon from "./components/icons/RestartIcon.vue";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import Badge from "./components/general/Badge.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";



import {
    startOfDay, endOfDay,
} from 'date-fns';
import Loading from "./components/general/Loading.vue";

const page = usePage()
const today = new Date();
const loading = ref(false)
const loadingModal = ref(false)
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const selectAll = ref(false);
const showRetryConfirmationModal = ref(false);
const confirmRetryAction = ref(null);

const props = defineProps({
    data: Object,
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    statusOptions: Object,
});

// onMounted(() => {
//     //request list of entities
//     // getEntities();
//     if (props.data.data.length === 0) {
//         handleSearchButtonClick();
//     }
// })

console.log(props.data)


const filterData = ref({
    search: props.search,
    showGlobal: false,
    dateRange: [moment.tz(props.startPeriod, props.timezone).startOf('day').format(), moment.tz(props.endPeriod, props.timezone).endOf('day').format()],
    // dateRange: ['2024-07-01T00:00:00', '2024-07-01T23:59:59'],
    timezone: props.timezone,

});

const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    handleSearchButtonClick();
}

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    handleSearchButtonClick();
}

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
            icon: 'RestartIcon'
        }
    ];

    return actions;
});

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_retry') {
        showRetryConfirmationModal.value = true;
        confirmRetryAction.value = () => executeBulkRetry();
    }

}

const handleRetry = (uuid) => {
    axios.post(props.routes.retry,
        { 'items': [uuid] },
    )
        .then((response) => {
            showNotification('success', response.data.messages);

            handleClearSelection();
        }).catch((error) => {
            handleClearSelection();
            handleFormErrorResponse(error);
        }).finally(() => {
            handleSearchButtonClick();
        });
}

const executeBulkRetry = () => {
    axios.post(props.routes.retry,
        { 'items': selectedItems.value },
    )
        .then((response) => {
            showNotification('success', response.data.messages);
            handleModalClose();
            handleClearSelection();
        }).catch((error) => {
            handleClearSelection();
            handleModalClose();
            handleFormErrorResponse(error);
        }).finally(() => {
            handleSearchButtonClick();
        });
}


const handleUpdateDateRange = (newDateRange) => {
    filterData.value.dateRange = newDateRange;
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
        selectedItems.value = props.data.data.map(item => item.user_log_uuid);
    } else {
        selectedItems.value = [];
    }
};

const handleClearSelection = () => {
    selectedItems.value = [],
        selectPageItems.value = false;
    selectAll.value = false;
}


const handleModalClose = () => {
    showRetryConfirmationModal.value = false;
}

const determineColor = (status) => {
    switch (status) {
        case 'success':
            return {
                backgroundColor: 'bg-green-50',
                textColor: 'text-green-700',
                ringColor: 'ring-green-600/20'
            };
        case 'failed':
            return {
                backgroundColor: 'bg-rose-50',
                textColor: 'text-rose-700',
                ringColor: 'ring-rose-600/20'
            };
        default:
            return {
                backgroundColor: 'bg-blue-50',
                textColor: 'text-blue-700',
                ringColor: 'ring-blue-600/20'
            };
    }
};

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');


</script>


<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>