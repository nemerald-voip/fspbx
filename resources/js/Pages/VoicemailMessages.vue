<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Voicemail messages</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-candidate"
                        id="mobile-search-candidate"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        placeholder="Search" />
                    <input type="text" v-model="filterData.search" name="desktop-search-candidate"
                        id="desktop-search-candidate"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        placeholder="Search" />
                </div>

                <div class="relative z-10 min-w-64 -mt-0.5 mb-2 scale-y-95 shrink-0 sm:mr-4">
                    <DatePicker :dateRange="filterData.dateRange" :timezone="props.timezone"
                        @update:date-range="handleUpdateDateRange" />
                </div>
            </template>

            <template #action>



            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>

            <template #table-header>
                <TableColumnHeader
                    class="hidden lg:table-cell whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center justify-start">
                        <input type="checkbox" v-model="selectPageItems"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                        <span class="pl-4">Caller ID</span>
                    </div>
                </TableColumnHeader>

                <TableColumnHeader header="Date"
                    class="hidden lg:table-cell px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Transcription"
                    class="hidden lg:table-cell px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="" class="hidden lg:table-cell px-2 py-3.5" />
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
                <!-- Mobile-only Selection & Bulk Action Bar -->
                <tr class="block lg:hidden bg-gray-50 border-b border-gray-200 mb-4 rounded-t-lg">
                    <td class="block p-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <input type="checkbox" v-model="selectPageItems"
                                    class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm font-semibold text-gray-900">
                                    {{ selectPageItems ? 'Deselect All' : 'Select All' }}
                                </span>
                            </div>

                            <!-- Bulk Actions (Delete button) for Mobile -->
                            <div v-if="selectedItems.length > 0" class="flex items-center">
                                <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest"
                                    :has-selected-items="true" />
                            </div>
                        </div>
                    </td>
                </tr>
                <tr v-for="row in data.data" :key="row.voicemail_message_uuid"
                    class="block lg:table-row border-b lg:border-none mb-4 lg:mb-0 bg-white shadow-sm lg:shadow-none rounded-lg lg:rounded-none p-4 lg:p-0">

                    <!-- TOP SECTION: Caller ID & Date (Side by side on tablets, stacked on phones) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:contents">
                        <!-- Caller ID -->
                        <TableField
                            class="block lg:table-cell whitespace-nowrap px-0 lg:px-4 py-2 text-sm text-gray-500">
                            <div class="flex items-start lg:items-center">
                                <input v-if="row.voicemail_message_uuid" v-model="selectedItems" type="checkbox"
                                    :value="row.voicemail_message_uuid"
                                    class="h-5 w-5 lg:h-4 lg:w-4 mt-1 lg:mt-0 rounded border-gray-300 text-indigo-600">
                                <div class="ml-4 text-sm"
                                    :class="[row.message_status !== 'saved' ? 'font-bold text-gray-900' : 'text-gray-700']">
                                    <div class="flex items-center gap-3">
                                        <div class="flex flex-col">
                                            <span>{{ row.caller_id_name }}</span>
                                            <span class="text-xs opacity-70">{{ row.caller_id_number }}</span>
                                        </div>
                                        <Badge v-if="row.message_status !== 'saved'" :text="'New'"
                                            :backgroundColor="'bg-blue-100'" :textColor="'text-blue-800'"
                                            class="px-2 py-0.5 text-[10px]" />
                                    </div>
                                </div>
                            </div>
                        </TableField>

                        <!-- Date -->
                        <TableField
                            class="flex lg:table-cell items-center gap-2 lg:gap-0 px-0 lg:px-2 py-2 text-sm lg:text-gray-900"
                            :class="row.message_status !== 'saved' ? 'font-bold text-gray-900' : 'text-gray-500'">
                            <span class="lg:hidden font-semibold text-gray-400 uppercase text-[10px]">Date:</span>
                            <span>{{ row.created_epoch_formatted }}</span>
                        </TableField>
                    </div>

                    <!-- TRANSCRIPTION SECTION: Always full width on mobile/tablet -->
                    <TableField
                        class="block lg:table-cell px-0 lg:px-4 py-2 text-sm border-t border-gray-100 lg:border-none mt-2 lg:mt-0 pt-2 lg:pt-0">
                        <span
                            class="lg:hidden font-semibold text-gray-400 uppercase text-[10px] block mb-1">Transcription</span>
                        <div
                            :class="['text-gray-600 leading-relaxed max-w-3xl', row.message_status !== 'saved' ? 'font-medium text-gray-800' : '']">
                            {{ row.message_transcription }}
                        </div>
                    </TableField>

                    <!-- ACTIONS SECTION -->
                    <TableField
                        class="block lg:table-cell px-0 lg:px-2 py-2 text-sm border-t border-gray-100 lg:border-none mt-2 lg:mt-0 pt-2 lg:pt-0">
                        <div class="flex items-center justify-between lg:justify-end">
                            <span class="lg:hidden font-semibold text-gray-400 uppercase text-[10px]">Actions</span>
                            <div class="flex items-center gap-1">
                                <PlayCircleIcon
                                    v-if="currentAudioUuid !== row.voicemail_message_uuid || !isAudioPlaying"
                                    @click="fetchAndPlayAudio(row.voicemail_message_uuid)"
                                    class="h-9 w-9 p-1.5 text-blue-500 cursor-pointer" />
                                <PauseCircleIcon
                                    v-if="currentAudioUuid === row.voicemail_message_uuid && isAudioPlaying"
                                    @click="pauseAudio" class="h-9 w-9 p-1.5 text-red-500 cursor-pointer" />
                                <CloudArrowDownIcon @click="downloadVoicemailMessage(row.voicemail_message_uuid)"
                                    class="h-9 w-9 p-1.5 text-gray-400 cursor-pointer" />

                                <!-- Status Toggle Button -->
                                <button v-if="props.permissions.voicemail_message_update"
                                    @click="executeStatusUpdate([row.voicemail_message_uuid], row.message_status === 'saved' ? null : 'saved')"
                                    class="h-9 w-9 p-1.5 rounded-full text-gray-400 hover:bg-gray-100 transition-colors duration-200"
                                    :title="row.message_status === 'saved' ? 'Mark as unread' : 'Mark as read'">

                                    <EnvelopeOpenIcon v-if="row.message_status === 'saved'" class="h-full w-full" />
                                    <EnvelopeIcon v-else class="h-full w-full text-blue-500" />
                                </button>
                                <TrashIcon v-if="props.permissions.voicemail_message_destroy"
                                    @click="handleSingleItemDeleteRequest(row.voicemail_message_uuid)"
                                    class="h-9 w-9 p-1.5 text-gray-400 cursor-pointer" />
                            </div>
                        </div>
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
        <div class="px-4 sm:px-6 lg:px-8"></div>
    </div>


    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

    <ConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmDeleteAction" :header="'Are you sure?'"
        :text="'Confirm deleting selected messages. This action can not be undone.'" :confirm-button-label="'Delete'"
        cancel-button-label="Cancel" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { usePage } from '@inertiajs/vue3'
import axios from 'axios';
import DatePicker from "./components/general/DatePicker.vue";
import moment from 'moment-timezone';
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import Loading from "./components/general/Loading.vue";
import { TrashIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import Notification from "./components/notifications/Notification.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import Spinner from "@generalComponents/Spinner.vue";
import Badge from "@generalComponents/Badge.vue";
import {
    PlayCircleIcon,
    PauseCircleIcon,
    CloudArrowDownIcon,
    MagnifyingGlassIcon,
    EnvelopeIcon,
    EnvelopeOpenIcon,
} from "@heroicons/vue/24/solid";


const page = usePage()
const loading = ref(false)
const loadingModal = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const confirmationModalTrigger = ref(false);
const confirmationModalDestroyPath = ref(null);
const confirmDeleteAction = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const isDownloading = ref(false);

const props = defineProps({
    voicemail_uuid: String,
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    permissions: Object,
});

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

const selectPageItems = computed({
    get() {
        // Returns true if we have data and every item on the current page is in selectedItems
        return data.value.data.length > 0 &&
            data.value.data.every(item => selectedItems.value.includes(item.voicemail_message_uuid));
    },
    set(value) {
        if (value) {
            // Add all items on current page to selection (avoiding duplicates)
            const currentPageIds = data.value.data.map(item => item.voicemail_message_uuid);
            const newSelection = new Set([...selectedItems.value, ...currentPageIds]);
            selectedItems.value = Array.from(newSelection);
        } else {
            // Remove only the items on the current page from selection
            const currentPageIds = data.value.data.map(item => item.voicemail_message_uuid);
            selectedItems.value = selectedItems.value.filter(id => !currentPageIds.includes(id));
        }
    }
});

const startLocal = moment.utc(props.startPeriod).tz(props.timezone)
const endLocal = moment.utc(props.endPeriod).tz(props.timezone)

const dateRange = [
    startLocal.clone().startOf('day').toISOString(), // UTC instant for local start-of-day
    endLocal.clone().endOf('day').toISOString(),     // UTC instant for local end-of-day
]

const filterData = ref({
    voicemail_uuid: props.voicemail_uuid,
    search: null,
    dateRange: dateRange,

});

onMounted(() => {
    handleSearchButtonClick();
})


const currentAudio = ref(null);
const currentAudioUuid = ref(null);
const isAudioPlaying = ref(false);
const itemOptions = ref({})


const fetchAndPlayAudio = (uuid) => {
    // Check if there's already an audio object
    if (currentAudio.value) {
        // If the current audio is paused and corresponds to the clicked voicemail, play it
        if (currentAudio.value.paused && currentAudioUuid.value === uuid) {
            currentAudio.value.play();
            isAudioPlaying.value = true;
            return;
        }

        // If the current audio does not match the clicked voicemail or it's playing, stop it
        currentAudio.value.pause();
        currentAudio.value.currentTime = 0; // Reset the playback position
        isAudioPlaying.value = false;
    }

    // Fetch the new audio file
    axios.post(props.routes.get_message_url, { voicemail_message_uuid: uuid })
        .then((response) => {
            if (response.data.success) {
                isAudioPlaying.value = true;
                currentAudioUuid.value = uuid;

                currentAudio.value = new Audio(response.data.file_url);
                currentAudio.value.play();

                // Add an event listener for when the audio ends
                currentAudio.value.addEventListener("ended", () => {
                    isAudioPlaying.value = false;
                });
            }
        }).catch((error) => {
            handleErrorResponse(error);
        });
}



const pauseAudio = () => {
    // Check if currentAudio has an Audio object before calling pause
    if (currentAudio.value) {
        currentAudio.value.pause();
        isAudioPlaying.value = false;
    }
};

const downloadVoicemailMessage = (uuid) => {
    isDownloading.value = true; // Start the spinner

    axios.post(props.routes.get_message_url, { voicemail_message_uuid: uuid })
        .then((response) => {
            if (response.data.success) {
                // Create a URL with the download parameter set to true
                const downloadUrl = `${response.data.file_url}?download=true`;

                // Create an invisible link element
                const link = document.createElement('a');
                link.href = downloadUrl;

                // Use the filename or a default name
                const fileName = response.data.file_name || 'greeting.wav';
                link.download = fileName;

                // Append the link to the body
                document.body.appendChild(link);

                // Trigger the download by programmatically clicking the link
                link.click();

                // Remove the link after the download starts
                document.body.removeChild(link);
            }
        })
        .catch((error) => {
            emits('error', error);
        })
        .finally(() => {
            isDownloading.value = false; // Stop the spinner after download completes
        });
};

const handleUpdateDateRange = (newDateRange) => {
    filterData.value.dateRange = newDateRange;
}

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [];

    if (props.permissions.voicemail_message_update) {
        actions.push({
            id: 'bulk_read',
            label: 'Mark as read',
            icon: 'EnvelopeOpenIcon'
        });
        actions.push({
            id: 'bulk_unread',
            label: 'Mark as unread',
            icon: 'EnvelopeIcon'
        });
    }

    // Conditionally add the delete action if permission is granted
    if (props.permissions.voicemail_message_destroy) {
        actions.push({
            id: 'bulk_delete',
            label: 'Delete',
            icon: 'TrashIcon'
        });
    }

    return actions;
});



const handleSingleItemDeleteRequest = (url) => {
    confirmationModalTrigger.value = true;
    confirmDeleteAction.value = () => executeSingleDelete(url);
}


const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        confirmationModalTrigger.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    } else if (action === 'bulk_read') {
        executeStatusUpdate(selectedItems.value, 'saved');
    } else if (action === 'bulk_unread') {
        executeStatusUpdate(selectedItems.value, null);
    }
}

const executeStatusUpdate = (items, status) => {
    loading.value = true;
    axios.post(props.routes.update_status, {
        items: items,
        status: status
    })
        .then((response) => {
            showNotification('success', response.data.messages);

            // Optimistically update the local data so the badges/bold text change immediately
            data.value.data.forEach(row => {
                if (items.includes(row.voicemail_message_uuid)) {
                    row.message_status = status;
                }
            });

            if (items.length > 1) handleClearSelection();
        })
        .catch((error) => {
            handleErrorResponse(error);
        })
        .finally(() => {
            loading.value = false;
        });
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


const handleSelectAll = () => {
    axios.post(props.routes.select_all, {
        filter: filterData.value,

    })
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification('success', response.data.messages);

        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });

};


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
            // console.log(data.value);

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


const getItemOptions = (itemUuid = null) => {
    const payload = itemUuid ? { item_uuid: itemUuid } : {}; // Conditionally add itemUuid to payload

    axios.post(props.routes.item_options, payload)
        .then((response) => {
            loadingModal.value = false;
            itemOptions.value = response.data;
            // console.log(itemOptions.value);

        }).catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        });
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

const handleClearSelection = () => {
    selectedItems.value = [],
        selectPageItems.value = false;
    selectAll.value = false;
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

const handleModalClose = () => {
    confirmationModalTrigger.value = false;
}

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>
