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
            </template>

            <template #action>



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
                    <span class="pl-4">Caller ID</span>
                </TableColumnHeader>

                <TableColumnHeader header="Date" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Transcription" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
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
                <tr v-for="row in data.data" :key="row.voicemail_message_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500" :text="row.voicemail_id">
                        <div class="flex items-center">
                            <input v-if="row.voicemail_message_uuid" v-model="selectedItems" type="checkbox"
                                name="action_box[]" :value="row.voicemail_message_uuid"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-9 text-sm font-semibold text-gray-700"
                                :class="{ 'cursor-pointer hover:text-gray-900': page.props.auth.can.voicemail_update, }"
                                @click="page.props.auth.can.voicemail_update && handleEditRequest(row.voicemail_message_uuid)">
                                <div class="flex flex-col">
                                    <span>
                                        {{ row.caller_id_name }}
                                    </span>
                                    <span>
                                        {{ row.caller_id_number }}
                                    </span>

                                </div>
                            </div>
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.created_epoch_formatted" />


                    <TableField class="px-4 py-2 text-sm text-gray-500">
                        <div>{{ row.message_transcription }}</div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap justify-end">

                                <PlayCircleIcon v-if="currentAudioUuid !== row.voicemail_message_uuid || !isAudioPlaying
                                    " @click="fetchAndPlayAudio(row.voicemail_message_uuid)"
                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-blue-500 hover:bg-blue-200 hover:text-blue-700 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                <PauseCircleIcon v-if="currentAudioUuid === row.voicemail_message_uuid && isAudioPlaying"
                                    @click="pauseAudio"
                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />

                                <CloudArrowDownIcon v-if="!isDownloading"
                                    @click="downloadVoicemailMessage(row.voicemail_message_uuid)"
                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                <!-- Spinner -->
                                <Spinner :show="isDownloading"
                                    class="ml-0 mr-0 h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />


                                <ejs-tooltip v-if="page.props.auth.can.voicemail_destroy" :content="'Delete'"
                                    position='TopCenter' target="#delete_tooltip_target">
                                    <div id="delete_tooltip_target">
                                        <TrashIcon @click="handleSingleItemDeleteRequest(row.destroy_route)"
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
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>
        </DataTable>
        <div class="px-4 sm:px-6 lg:px-8"></div>
    </div>


    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

    <ConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmDeleteAction" :header="'Are you sure?'" :text="'Confirm deleting selected messages. This action can not be undone.'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { usePage } from '@inertiajs/vue3'
import axios from 'axios';
import { router } from "@inertiajs/vue3";
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
import {
    PlayCircleIcon,
    PauseCircleIcon,
    CloudArrowDownIcon,
    MagnifyingGlassIcon,
} from "@heroicons/vue/24/solid";
import { DocumentArrowDownIcon } from "@heroicons/vue/24/outline";



const page = usePage()
const loading = ref(false)
const loadingModal = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const confirmationModalTrigger = ref(false);
const confirmationModalDestroyPath = ref(null);
const confirmDeleteAction = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const isDownloading = ref(false);

const props = defineProps({
    voicemail_uuid: String,
    data: Object,
    routes: Object,
    itemData: Object,
    permissions: Object,
});


const filterData = ref({
    voicemail_uuid: props.voicemail_uuid,
    search: null,
});

const currentAudio = ref(null);
const currentAudioUuid = ref(null);
const isAudioPlaying = ref(false);
const itemOptions = ref({})

// const fetchAndPlayAudio = (uuid) => {
//     // Check if there's already an audio object and it is paused
//     if (currentAudio.value && currentAudio.value.paused) {
//         currentAudio.value.play();
//         isAudioPlaying.value = true;
//         return;
//     }

//     axios.post(props.routes.get_message_url, { voicemail_message_uuid: uuid })
//         .then((response) => {
//             // Stop the currently playing audio (if any)
//             if (currentAudio.value) {
//                 currentAudio.value.pause();
//                 currentAudio.value.currentTime = 0; // Reset the playback position
//             }
//             if (response.data.success) {
//                 isAudioPlaying.value = true;
//                 currentAudioUuid.value = uuid;

//                 console.log(response.data.file_url);
//                 currentAudio.value = new Audio(response.data.file_url);
//                 currentAudio.value.play();

//                 // Add an event listener for when the audio ends
//                 currentAudio.value.addEventListener("ended", () => {
//                     isAudioPlaying.value = false;
//                 });
//             }

//         }).catch((error) => {
//             handleErrorResponse(error);
//         });
// }

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
    if (props.permissions.voicemail_message_destroy) {
        actions.push({
            id: 'bulk_delete',
            label: 'Delete',
            icon: 'TrashIcon'
        });
    }

    return actions;
});

onMounted(() => {
});



const handleSingleItemDeleteRequest = (url) => {
    confirmationModalTrigger.value = true;
    confirmDeleteAction.value = () => executeSingleDelete(url);
}

const executeSingleDelete = (url) => {
    router.delete(url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
            if (page.props.flash.error) {
                showNotification('error', page.props.flash.error);
            }
            if (page.props.flash.message) {
                showNotification('success', page.props.flash.message);
            }
            confirmationModalTrigger.value = false;
            confirmationModalDestroyPath.value = null;
        },
        onFinish: () => {
            confirmationModalTrigger.value = false;
            confirmationModalDestroyPath.value = null;
        },
        onError: (errors) => {
            console.log(errors);
        },
    });
}

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        confirmationModalTrigger.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }


}



const executeBulkDelete = () => {
    axios.post(props.routes.bulk_delete, { items: selectedItems.value })
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
            handleClearSelection();
        }
    });
};

const handleFiltersReset = () => {
    filterData.value.search = null;
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

const handleSelectPageItems = () => {
    if (selectPageItems.value) {
        selectedItems.value = props.data.data.map(item => item.voicemail_message_uuid);
    } else {
        selectedItems.value = [];
    }
};



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
