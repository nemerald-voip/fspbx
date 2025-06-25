
<template>
    <MainLayout>

        <div class="m-3">
            <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
                <template #title>Call History</template>

                <template #action>

                    <button v-if="page.props.auth.can.cdrs_export" type="button" @click.prevent="exportCsv"
                        :disabled="isExporting"
                        class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        <DocumentArrowDownIcon class="h-5 w-5" aria-hidden="true" />
                        Export CSV
                        <Spinner class="ml-1" :show="isExporting" />
                    </button>

                    <button v-if="!showGlobal && page.props.auth.can.cdrs_view_global" type="button"
                        @click.prevent="handleShowGlobal()"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Show global
                    </button>

                    <button v-if="showGlobal && page.props.auth.can.cdrs_view_global" type="button"
                        @click.prevent="handleShowLocal()"
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

                    <div class="relative min-w-36 mb-2 shrink-0 sm:mr-4">
                        <SelectBox :options="callDirections" :selectedItem="filterData.direction"
                            :placeholder="'Call Direction'" @update:model-value="handleUpdateCallDirectionFilter" />
                    </div>

                    <div class="relative min-w-64 mb-2 shrink-0 sm:mr-4">
                        <ComboBox :options="entities" :selectedItem="filterData.entity" :search="true"
                            :placeholder="'Users or Groups'" @update:model-value="handleUpdateUserOrGroupFilter" />
                    </div>

                    <div class="relative min-w-64 mb-2 shrink-0 sm:mr-4">
                        <ComboBox :options="statusOptions" :selectedItem="selectedStatuses" :multiple="true"
                            :placeholder="'Status'" @apply-selection="handleSelectedStatusUpdate"
                            @update:model-value="handleSelectedStatusUpdate" :error="null" />
                    </div>


                </template>

                <template #navigation>
                    <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                        :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                        @pagination-change-page="renderRequestedPage" />
                </template>
                <template #table-header>
                    <TableColumnHeader header=" "
                        class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6"></TableColumnHeader>
                    <TableColumnHeader v-if="showGlobal" header="Domain"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Caller ID Name"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"></TableColumnHeader>
                    <TableColumnHeader header="Caller ID Number"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"></TableColumnHeader>
                    <TableColumnHeader header="Dialed Number"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Recipient" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Date" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Time" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Duration" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Status" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Rec" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                    <TableColumnHeader header="Actions"
                        class="px-2 py-3.5 text-sm font-semibold text-center text-gray-900" />

                </template>

                <template #table-body>
                    <tr v-for="row in data.data" :key="row.xml_cdr_uuid">
                        <!-- <TableField class="whitespace-nowrap py-2 pl-4 pr-3 text-sm text-gray-500 sm:pl-6"
                        :text="row.direction" /> -->
                        <TableField :text="row.direction"
                            class="whitespace-nowrap py-2 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">
                            <ejs-tooltip :content="row.direction + ' call'" position='TopLeft'
                                target="#destination_tooltip_target">
                                <div id="destination_tooltip_target">
                                    <PhoneOutgoingIcon class="w-5 h-5 text-blue-600" v-if="row.direction === 'outbound'" />
                                    <PhoneIncomingIcon class="w-5 h-5 text-green-600" v-if="row.direction === 'inbound'" />
                                    <PhoneLocalIcon class="w-5 h-5 text-fuchsia-600" v-if="row.direction === 'local'" />
                                </div>
                            </ejs-tooltip>

                        </TableField>

                        <TableField v-if="showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.domain?.domain_description">
                            <ejs-tooltip :content="row.domain?.domain_name" position='TopLeft'
                                target="#domain_tooltip_target">
                                <div id="domain_tooltip_target">
                                    {{ row.domain?.domain_description }}
                                </div>
                            </ejs-tooltip>
                        </TableField>

                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.caller_id_name" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.caller_id_number_formatted" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.caller_destination_formatted" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.destination_number_formatted" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.start_date" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.start_time" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.duration_formatted" />

                        <!-- <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.status" /> -->
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            <Badge :text="row.status"
                                :backgroundColor="statusBadgeConfig[row.status]?.backgroundColor || 'bg-blue-50'"
                                :textColor="statusBadgeConfig[row.status]?.textColor || 'text-blue-700'"
                                :ringColor="statusBadgeConfig[row.status]?.ringColor || 'ring-blue-600/20'" />
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                            <template v-if="(row.record_name && row.record_path) || row.record_path === 'S3'
                                " #action-buttons>
                                <div class="flex items-center space-x-2 whitespace-nowrap">
                                    <PlayCircleIcon v-if="page.props.auth.can.call_recording_play && (currentAudioUuid !== row.xml_cdr_uuid || !isAudioPlaying)
                                        " @click="fetchAndPlayAudio(row.xml_cdr_uuid)"
                                        class="h-6 w-6 text-blue-500 hover:text-blue-700 active:h-5 active:w-5 cursor-pointer" />
                                    <PauseCircleIcon v-if="currentAudioUuid === row.xml_cdr_uuid && isAudioPlaying"
                                        @click="pauseAudio"
                                        class="h-6 w-6 text-blue-500 hover:text-blue-700 active:h-5 active:w-5 cursor-pointer" />

                                    <CloudArrowDownIcon v-if="page.props.auth.can.call_recording_download" @click="downloadAudio(row.xml_cdr_uuid)"
                                        class="h-6 w-6 text-gray-500 hover:text-gray-700 active:h-5 active:w-5 cursor-pointer" />
                                </div>
                            </template>
                        </TableField>

                        <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                            <template #action-buttons>
                                <div class="flex items-center whitespace-nowrap justify-center">
                                    <ejs-tooltip v-if="page.props.auth.can.cdr_view_details" :content="'View details'"
                                        position='TopCenter' target="#view_tooltip_target">
                                        <div id="view_tooltip_target">
                                            <MagnifyingGlassIcon @click="handleViewRequest(row.xml_cdr_uuid)"
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
        </div>
    </MainLayout>


    <CallDetailsModal :show="viewModalTrigger" :item="itemOptions?.item" :loading="loadingModal"
        :customClass="'sm:max-w-4xl'" @close="handleModalClose">
    </CallDetailsModal>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { ref, onMounted } from "vue";
import { usePage } from '@inertiajs/vue3'
import { router } from "@inertiajs/vue3";
import MainLayout from '../Layouts/MainLayout.vue'
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import PhoneOutgoingIcon from "./components/icons/PhoneOutgoingIcon.vue"
import PhoneIncomingIcon from "./components/icons/PhoneIncomingIcon.vue"
import PhoneLocalIcon from "./components/icons/PhoneLocalIcon.vue"
import SelectBox from "./components/general/SelectBox.vue"
import ComboBox from "./components/general/ComboBox.vue"
import Badge from "@generalComponents/Badge.vue";
import moment from 'moment-timezone';
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import { registerLicense } from '@syncfusion/ej2-base';
import DatePicker from "./components/general/DatePicker.vue";
import CallDetailsModal from "./components/modal/CallDetailsModal.vue"
import Notification from "./components/notifications/Notification.vue";
import {
    PlayCircleIcon,
    PauseCircleIcon,
    CloudArrowDownIcon,
    MagnifyingGlassIcon,
} from "@heroicons/vue/24/solid";
import { DocumentArrowDownIcon } from "@heroicons/vue/24/outline";

import {
    startOfDay, endOfDay,
} from 'date-fns';
import Loading from "./components/general/Loading.vue";
import Spinner from "./components/general/Spinner.vue";

const page = usePage()
const today = new Date();
const loading = ref(false)
const viewModalTrigger = ref(false);
const loadingModal = ref(false)
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const isExporting = ref(null);
const selectedStatuses = ref([]);


const props = defineProps({
    data: Object,
    showGlobal: Boolean,
    startPeriod: String,
    endPeriod: String,
    search: String,
    timezone: String,
    direction: String,
    recordingUrl: String,
    entities: Array,
    selectedEntity: String,
    selectedEntityType: String,
    csvUrl: Object,
    routes: Object,
    // itemData: Object,
    statusOptions: Object,
});

onMounted(() => {
    //request list of entities
    getEntities();
    console.log(page)
})

const filterData = ref({
    search: props.search,
    showGlobal: props.showGlobal,
    dateRange: [moment.tz(props.startPeriod, props.timezone).startOf('day').format(), moment.tz(props.endPeriod, props.timezone).endOf('day').format()],
    // dateRange: ['2024-07-01T00:00:00', '2024-07-01T23:59:59'],
    timezone: props.timezone,
    direction: props.direction,
    entity: props.selectedEntity,
    entityType: props.selectedEntityType,
});

const showGlobal = ref(props.showGlobal);
const itemOptions = ref({});

const callDirections = [
    { value: 'outbound', name: 'Outbound' },
    { value: 'inbound', name: 'Inbound' },
    { value: 'local', name: 'Local' },
]

const handleSelectedStatusUpdate = (updatedStatuses) => {
    filterData.value.statuses = updatedStatuses;
};

const getEntities = () => {
    filterData.value.entity = null;
    router.visit("/call-detail-records", {
        preserveScroll: true,
        preserveState: true,
        data: {
            filterData: filterData._rawValue,
        },
        only: ["entities"],
        onSuccess: (page) => {
            filterData.value.entity = props.selectedEntity;
        }

    });

}

const handleViewRequest = (itemUuid) => {
    viewModalTrigger.value = true
    loadingModal.value = true
    getItemOptions(itemUuid);

    // router.get(props.routes.current_page,
    //     {
    //         itemUuid: itemUuid,
    //     },
    //     {
    //         preserveScroll: true,
    //         preserveState: true,
    //         only: [
    //             'itemData',
    //         ],
    //         onSuccess: (page) => {
    //             // console.log(props.itemData);
    //             if (!props.itemData) {
    //                 viewModalTrigger.value = false;
    //                 showNotification('error', { error: ['Unable to retrieve this item'] });
    //             } else {
    //                 loadingModal.value = false;
    //                 viewModalTrigger.value = true;
    //             }

    //         },
    //         onFinish: () => {
    //             // loadingModal.value = false;
    //         },
    //         onError: (errors) => {
    //             console.log(errors);
    //         },

    //     });
}

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

const handleUpdateCallDirectionFilter = (newSelectedItem) => {
    if (newSelectedItem.value == "NULL") {
        filterData.value.direction = null;
    } else {
        filterData.value.direction = newSelectedItem.value;
    }
}

const handleUpdateUserOrGroupFilter = (newSelectedItem) => {
    filterData.value.entity = newSelectedItem.value;
    filterData.value.entityType = newSelectedItem.type;
}

const handleSearchButtonClick = () => {
    loading.value = true;

    router.visit("/call-detail-records", {
        data: {
            filterData: filterData._rawValue,
        },
        preserveScroll: true,
        preserveState: true,
        only: [
            "data",
            'showGlobal',
        ],
        onSuccess: (page) => {
            loading.value = false;
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

const currentAudio = ref(null);
const currentAudioUuid = ref(null);
const isAudioPlaying = ref(false);

const fetchAndPlayAudio = (uuid) => {
    router.visit("/call-detail-records", {
        data: {
            callUuid: uuid,
        },
        preserveScroll: true,
        preserveState: true,
        only: ["recordingUrl"],
        onSuccess: (page) => {
            // Stop the currently playing audio (if any)
            if (currentAudio.value) {
                currentAudio.value.pause();
                currentAudio.value.currentTime = 0; // Reset the playback position
            }

            currentAudioUuid.value = uuid;
            isAudioPlaying.value = true;

            currentAudio.value = new Audio(props.recordingUrl);
            currentAudio.value.play();

            // Add an event listener for when the audio ends
            currentAudio.value.addEventListener("ended", () => {
                isAudioPlaying.value = false;
                currentAudioUuid.value = null;
            });
        },
    });
};

const downloadAudio = (uuid) => {
    router.visit("/call-detail-records", {
        data: {
            filterData: filterData._rawValue,
            callUuid: uuid,
        },
        preserveScroll: true,
        preserveState: true,
        only: ["recordingUrl"],
        onSuccess: (page) => {
            let fileName;

            if (props.recordingUrl.includes("call-detail-records/file")) {
                // Shorten the name
                fileName = uuid;
            } else {
                // If the substring is not present, use the original URL
                fileName = props.recordingUrl;
            }

            // Create an anchor element and set the attributes for downloading
            const anchor = document.createElement("a");
            anchor.href = props.recordingUrl;
            anchor.download = fileName; // You can set a specific filename here if desired
            document.body.appendChild(anchor);

            // Trigger the download
            anchor.click();

            // Clean up by removing the anchor element
            document.body.removeChild(anchor);
        },
    });
};

const pauseAudio = () => {
    // Check if currentAudio has an Audio object before calling pause
    if (currentAudio.value) {
        currentAudio.value.pause();
        isAudioPlaying.value = false;
    }
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

// const exportCsv = () => {
//     isExporting.value = true;

//     axios.post(props.routes.export, {
//         filterData: filterData._rawValue,
//     }, {
//         responseType: 'blob'
//     })
//         .then(response => {
//             // Create a blob link to download
//             const url = window.URL.createObjectURL(new Blob([response.data]));
//             const link = document.createElement('a');
//             link.href = url;
//             link.setAttribute('download', 'call-detail-records.csv'); // Set the file name for the download
//             document.body.appendChild(link);
//             link.click();
//             document.body.removeChild(link); // Clean up
//             window.URL.revokeObjectURL(url); // Free up memory

//             filterData.value.download = 'false'; // Reset download flag on success
//             showNotification('success', response.data.messages);
//             isExporting.value = false;
//         })
//         .catch(error => {
//             console.error('There was an error with the request:', error);
//             filterData.value.download = 'false'; // Reset download flag on error
//             isExporting.value = false;
//             handleErrorResponse(error);
//         });


// };

const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    showGlobal.value = true;
    handleSearchButtonClick();
}

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    showGlobal.value = false;
    handleSearchButtonClick();
}

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

const statusBadgeConfig = {
    answered: {
        backgroundColor: "bg-green-50",
        textColor: "text-green-700",
        ringColor: "ring-green-600/20",
    },
    cancelled: {
        backgroundColor: "bg-gray-50",
        textColor: "text-gray-700",
        ringColor: "ring-gray-600/20",
    },
    no_answer: {
        backgroundColor: "bg-amber-50",
        textColor: "text-amber-700",
        ringColor: "ring-amber-600/20",
    },
    failed: {
        backgroundColor: "bg-red-50",
        textColor: "text-red-700",
        ringColor: "ring-red-600/20",
    },
    "missed call": {
        backgroundColor: "bg-orange-50",
        textColor: "text-orange-700",
        ringColor: "ring-orange-600/20",
    },
    abandoned: {
        backgroundColor: "bg-purple-50",
        textColor: "text-purple-700",
        ringColor: "ring-purple-600/20",
    }
};

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');



</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>
