<template>
    <div class="mt-4 flex flex-col">

        <div class="flex flex-col sm:flex-row sm:flex-wrap">
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
            <div class="relative">
                <div class="flex justify-between">

                    <button type="button" @click.prevent="handleSearchButtonClick"
                        class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500
                                focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Search
                    </button>

                    <button type="button" @click.prevent="handleFiltersReset"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2  sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <button type="button" @click.prevent="handleManageCodesButtonClick()"
                class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Housekeeping Codes
            </button>
        </div>

        <div class="mt-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <Paginator class="border border-gray-200" :previous="data.prev_page_url" :next="data.next_page_url"
                    :from="data.from" :to="data.to" :total="data.total" :currentPage="data.current_page"
                    :lastPage="data.last_page" :links="data.links" @pagination-change-page="renderRequestedPage" />
                <div class="overflow-hidden-t border-l border-r border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 mb-4">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Room</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Occupancy</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Housekeeping</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Guest</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Arrival</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Departure</th>
                                <th class="relative px-6 py-3 text-left text-sm font-medium text-gray-500">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody v-if="!isRoomsLoading && data.data?.length" class="divide-y divide-gray-200 bg-white">
                            <tr v-for="row in data.data" :key="row.uuid">
                                <td class="whitespace-nowrap px-6 py-2 text-sm font-medium text-gray-900 capitalize">
                                    {{ row.room_name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-2 text-sm">
                                    
                                        {{ row.occupancy_status || 'Not checked in' }}
                                    
                                </td>
                                <td class="whitespace-nowrap px-6 py-2 text-sm">
                                    
                                        {{ row.housekeeping_status || 'Not checked in' }}
                                    
                                </td>
                                <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-800">
                                    <span v-if="row.guest_last_name || row.guest_first_name">
                                        {{ [row.guest_last_name, row.guest_first_name].filter(Boolean).join(', ') }}
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                    {{ row.arrival_date ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                    {{ row.departure_date ?? '—' }}
                                </td>

                                <td class="whitespace-nowrap px-6 py-2 text-right text-sm font-medium">
                                    <div class="flex items-center whitespace-nowrap justify-end gap-1">
                                        <ejs-tooltip :content="'Check In'" position="TopCenter" target="#rs_edit_tt">
                                            <div id="rs_edit_tt">
                                                <ClipboardDocumentCheckIcon @click="handleCheckInButtonClick(row.uuid)"
                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                            </div>
                                        </ejs-tooltip>

                                    
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="!isRoomsLoading && data.data?.length === 0" class="text-center my-5">
                        <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                        <!-- <p class="mt-1 text-sm text-gray-500">
                Adjust your search and try again.
              </p> -->
                    </div>

                    <!-- Loading -->
                    <div v-if="isRoomsLoading" class="text-center my-5 text-sm text-gray-500">
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
            </div>
        </div>
    </div>

    <ManageHousekeepingCodesModal :options="housekeepingItemOptions" :show="showManageCodesModal" :loading="loadingModal"
        @close="showManageCodesModal = false" @error="handleFormErrorResponse" 
        @success="showNotification('success', $event)" />

    <HotelRoomCheckInModal :options="itemOptions" :show="showCheckInModal" :loading="loadingModal"
        @close="showCheckInModal = false" @error="handleFormErrorResponse" @refresh-data="fetchRoomStatuses"
        @success="showNotification('success', $event)" /> 

    <ConfirmationModal :show="showDeleteConfirmationModal" @close="showDeleteConfirmationModal = false"
        @confirm="confirmDeleteAction" :header="'Confirm Deletion'"
        :text="'This will permanently delete the selected room status record(s). Proceed?'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import ManageHousekeepingCodesModal from "./modal/ManageHousekeepingCodesModal.vue";
import HotelRoomCheckInModal from "./modal/HotelRoomCheckInModal.vue";
import Notification from "./notifications/Notification.vue";
import ConfirmationModal from "./modal/ConfirmationModal.vue";
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import {ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline'
import { registerLicense } from '@syncfusion/ej2-base';
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import Paginator from "@generalComponents/Paginator.vue";


const selectedItems = ref([]);

const props = defineProps({
    domain_uuid: String,
    routes: Object,
    permissions: Object,
    trigger: Boolean
})

const showManageCodesModal = ref(false);
const showCheckInModal = ref(false);
const loadingModal = ref(false)
const formErrors = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const showDeleteConfirmationModal = ref(false);
const confirmDeleteAction = ref(null);
const itemOptions = ref([])
const housekeepingItemOptions = ref([])
const isRoomsLoading = ref(false)
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


const filterData = ref({
    search: null,
    domain_uuid: props.domain_uuid,
});

// const emits = defineEmits(['edit-item', 'delete-item']);

const fetchRoomStatuses = async (page = 1) => {
    isRoomsLoading.value = true
    axios.get(props.routes.hotel_room_status, {
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
            isRoomsLoading.value = false
        });
}


watch(() => props.trigger, (newVal) => {
    fetchRoomStatuses(1)
})


const renderRequestedPage = (url) => {
    isRoomsLoading.value = true;
    // Extract the page number from the url, e.g. "?page=3"
    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get("page") ?? 1;

    // Now call getData with the page number
    fetchRoomStatuses(pageParam);
};

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [
        // {
        //     id: 'bulk_update',
        //     label: 'Edit',
        //     icon: 'PencilSquareIcon'
        // }
    ];

    // Conditionally add the delete action if permission is granted
    if (props.permissions.user_destroy) {
        actions.push({
            id: 'bulk_delete',
            label: 'Delete',
            icon: 'TrashIcon'
        });
    }

    return actions;
});

const handleManageCodesButtonClick = () => {
    showManageCodesModal.value = true
    loadingModal.value = true
    getCodeItemOptions();
}

const handleSearchButtonClick = () => {
    fetchRoomStatuses(1)
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    handleSearchButtonClick();
}

const handleCheckInButtonClick = (uuid) => {
    showCheckInModal.value = true
    loadingModal.value = true
    getItemOptions(uuid);
}

const getItemOptions = (itemUuid = null) => {
    loadingModal.value = true;

    axios.post(props.routes.hotel_room_status_item_options, {
        item_uuid: itemUuid,
    })
        .then((response) => {
            itemOptions.value = response.data;
            console.log(itemOptions.value);

        }).catch((error) => {
            handleErrorResponse(error)
        }).finally(() => {
            loadingModal.value = false
        });
}

const handleSingleItemDeleteRequest = (uuid) => {
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
};

const executeBulkDelete = (items = selectedItems.value) => {
    axios.post(props.routes.hotel_rooms_bulk_delete, { items })
        .then((response) => {
            handleModalClose();
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
        })
        .catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        });
}


const handleModalClose = () => {
    showCreateModal.value = false;
    showCheckInModal.value = false;
    showDeleteConfirmationModal.value = false;
    // bulkUpdateModalTrigger.value = false;
}

const getCodeItemOptions = (itemUuid = null) => {
    loadingModal.value = true;

    axios.post(props.routes.housekeeping_item_options, {
        item_uuid: itemUuid,
    })
        .then((response) => {
            housekeepingItemOptions.value = response.data;
            // console.log(housekeepingItemOptions.value);

        }).catch((error) => {
            handleErrorResponse(error)
        }).finally(() => {
            loadingModal.value = false
        });
}


const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        showDeleteConfirmationModal.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }
    if (action === 'bulk_update') {
        formErrors.value = [];
        getItemOptions();
        loadingModal.value = true
        bulkUpdateModalTrigger.value = true;
    }

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

const handleClearErrors = () => {
    formErrors.value = null;
}

const handleFormErrorResponse = (error) => {
    if (error.request?.status == 419) {
        showNotification('error', { request: ["Session expired. Reload the page"] });
    } else if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || { request: [error.message] });
        formErrors.value = error.response.data.errors;
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

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');


</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>