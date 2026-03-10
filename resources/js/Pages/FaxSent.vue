<template>
    <MainLayout>

        <div class="m-3">
            <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
                <template #title>Sent Faxes</template>

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
                        :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page"
                        :links="data.links" @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                        @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
                </template>


                <template #table-header>
                    <TableColumnHeader
                        class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                        <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600" />

                        <span class="pl-4">From</span>
                    </TableColumnHeader>

                    <!-- Email -->
                    <TableColumnHeader header="To" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                    <!-- Timestamp -->
                    <TableColumnHeader header="Date"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />


                    <TableColumnHeader header="" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                    <!-- Empty for any action buttons -->
                    <TableColumnHeader header="" class="px-2 py-3.5 text-center text-sm font-semibold text-gray-900" />
                </template>




                <template v-if="selectPageItems || selectAll" v-slot:current-selection>
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
                    <tr v-for="row in data.data" :key="row.fax_file_uuid">
                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                            <div class="flex items-center">
                                <input v-if="row.fax_file_uuid" v-model="selectedItems" type="checkbox"
                                    name="action_box[]" :value="row.fax_file_uuid"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                                <div class="ml-4">
                                    <span class="flex flex-col items-center">
                                        <span v-if="row.fax_caller_id_name != row.fax_caller_id_number">{{
                                            row.fax_caller_id_name ?? '' }}</span>
                                        <span>{{ row.fax_caller_id_number_formatted ?? '' }}</span>
                                    </span>
                                </div>
                            </div>
                        </TableField>

                        <!-- Email -->
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.fax?.fax_caller_id_number_formatted ?? ''" />



                        <!-- Timestamp (localized) -->
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.fax_date_formatted" />

                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="''" />


                        <!-- Action buttons -->
                        <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">

                            <template #action-buttons>
                                <div class="flex items-center whitespace-nowrap justify-end">
                                    <ejs-tooltip v-if="permissions.delete" :content="'Download'" position='TopCenter'
                                        target="#destination_tooltip_target">
                                        <div id="destination_tooltip_target">
                                            <CloudArrowDownIcon @click="handleDownloadButtonClick(row.fax_file_uuid)"
                                                class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                        </div>
                                    </ejs-tooltip>

                                    <ejs-tooltip v-if="permissions.delete" :content="'Delete'" position='TopCenter'
                                        target="#delete_tooltip_target">
                                        <div id="delete_tooltip_target">
                                            <TrashIcon @click="handleDeleteButtonClick(row.fax_file_uuid)"
                                                class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                        </div>
                                    </ejs-tooltip>
                                </div>
                            </template>


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
                        :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page"
                        :links="data.links" @pagination-change-page="renderRequestedPage" />
                </template>


            </DataTable>
        </div>
    </MainLayout>


    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

    <ConfirmationModal :show="showDeleteConfirmationModal" @close="showDeleteConfirmationModal = false"
        @confirm="confirmDeleteAction" :header="'Are you sure?'"
        :text="'Are you sure you want to permanently delete selected faxes? This action can not be undone.'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" />
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import MainLayout from '../Layouts/MainLayout.vue'
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import moment from 'moment-timezone';
import { registerLicense } from '@syncfusion/ej2-base';
import DatePicker from "./components/general/DatePicker.vue";
import Notification from "./components/notifications/Notification.vue";
import { MagnifyingGlassIcon, CloudArrowDownIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";

import Loading from "./components/general/Loading.vue";

const loading = ref(false)
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const selectAll = ref(false);
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

const props = defineProps({
    fax_uuid: String,
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    permissions: Object,
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
    fax_uuid: props.fax_uuid,
    search: props.search,
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

const handleDeleteButtonClick = (uuid) => {
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
}

const executeBulkDelete = (items = selectedItems.value) => {
    axios.post(props.routes.bulk_delete, { items })
        .then((response) => {
            handleModalClose();
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
        })
        .catch((error) => {
            handleClearSelection();
            handleModalClose();
            handleErrorResponse(error);
        });
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

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [
        {
            id: 'bulk_delete',
            label: 'Delete',
            icon: 'TrashIcon'
        }
    ];

    return actions;
});

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        showDeleteConfirmationModal.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }

}

const handleUpdateDateRange = (newDateRange) => {
    filterData.value.dateRange = newDateRange;
}

const handleDownloadButtonClick = async (uuid) => {
    const url = props.routes.download.replace(':file', encodeURIComponent(uuid));

    try {
        const res = await axios.get(url, {
            responseType: 'blob',
            // optional: track progress
            // onDownloadProgress: (e) => console.log(`Downloaded ${e.loaded} bytes`)
        });

        // Try to get a filename from headers
        const dispo = res.headers['content-disposition'] || '';
        const matches = /filename\*=UTF-8''([^;]+)|filename="?([^"]+)"?/i.exec(dispo);
        const headerName = decodeURIComponent(matches?.[1] || matches?.[2] || '');

        // Fallback filename if header missing
        const fallbackName = `${uuid}.tif`;
        const filename = headerName || fallbackName;

        // Create a temporary download link
        const blob = new Blob([res.data], { type: res.headers['content-type'] || 'image/tiff' });
        const link = document.createElement('a');
        const urlObj = URL.createObjectURL(blob);
        link.href = urlObj;
        link.download = filename;

        // iOS/Safari sometimes ignores link.click(); open in new tab if download attr unsupported
        if (typeof link.download === 'string') {
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } else {
            window.open(urlObj, '_blank');
        }

        URL.revokeObjectURL(urlObj);
    } catch (error) {
        handleErrorResponse(error);
    } finally {

    }
};



const handleSelectAll = () => {
    axios.post(props.routes.select_all, { filter: filterData.value })
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            selectPageItems.value = true;

            showNotification('success', response.data.messages);
        })
        .catch((error) => {
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
        selectedItems.value = data.value.data.map(item => item.fax_file_uuid);
    } else {
        selectedItems.value = [];
    }
};

const handleClearSelection = () => {
    selectedItems.value = []
    selectPageItems.value = false;
    selectAll.value = false;
}


const handleModalClose = () => {
    showDeleteConfirmationModal.value = false;
}

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');


</script>


<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>