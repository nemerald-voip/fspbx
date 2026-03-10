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

                    <div class="relative min-w-36 mb-2 shrink-0 sm:mr-4">
                        <multiselect v-model="filterData.direction" :options="callDirections" :searchable="false"
                            :close-on-select="true" track-by="value" label="name" :show-labels="false"
                            placeholder="Direction" aria-label="pick a value"></multiselect>
                    </div>

                    <div v-if="permissions.all_cdr_view" class="relative min-w-64 mb-2 shrink-0 sm:mr-4">
                        <multiselect v-model="filterData.entity" :options="entities" deselectLabel=""
                            selectGroupLabel="" deselectGroupLabel="" selectLabel="" group-values="groupOptions"
                            group-label="groupLabel" :group-select="false" placeholder="Select extension or group"
                            track-by="value" label="label"><template v-slot:noResult>No items found.</template>
                        </multiselect>
                    </div>

                    <div class="relative min-w-36 mb-2 shrink-0 sm:mr-4">
                        <multiselect v-model="filterData.status" :options="statusOptions" :searchable="false"
                            :close-on-select="true" track-by="value" label="name" :show-labels="false"
                            placeholder="Status" aria-label="pick a value">
                        </multiselect>
                    </div>

                    <div v-if="permissions.search_sentiment && permissions.transcription_summary" class="relative min-w-36 mb-2 shrink-0 sm:mr-4">
                        <multiselect v-model="filterData.sentiment" :options="sentimentOptions" :searchable="false"
                            :close-on-select="true" track-by="value" label="name" :show-labels="false"
                            placeholder="Sentiment" aria-label="pick a value">
                        </multiselect>
                    </div>


                </template>

                <template #navigation>
                    <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                        :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page"
                        :links="data.links" @pagination-change-page="renderRequestedPage" />
                </template>
                <template #table-header>
                    <TableColumnHeader header=" "
                        class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">
                    </TableColumnHeader>
                    <TableColumnHeader v-if="showGlobal" header="Domain"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Caller ID Name"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"></TableColumnHeader>
                    <TableColumnHeader header="Caller ID Number"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"></TableColumnHeader>
                    <TableColumnHeader header="Dialed Number"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Recipient"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Date" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Time" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Duration"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Status"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>

                    <TableColumnHeader v-if="permissions.cdr_mos_view" header="MOS"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

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
                                    <PhoneOutgoingIcon class="w-5 h-5 text-blue-600"
                                        v-if="row.direction === 'outbound'" />
                                    <PhoneIncomingIcon class="w-5 h-5 text-green-600"
                                        v-if="row.direction === 'inbound'" />
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

                        <!-- <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.caller_id_name" /> -->

                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            <div v-if="row.extension && row.direction == 'outbound'">{{ row.extension?.name_formatted }}
                            </div>
                            <div v-else>{{ row.caller_id_name_formatted }}</div>
                        </TableField>

                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">

                            <div v-if="row.extension && row.direction == 'outbound' && false">{{
                                row.extension?.extension
                            }}</div>
                            <div v-else>{{ row.caller_id_number_formatted }}</div>
                        </TableField>

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

                        <TableField v-if="permissions.cdr_mos_view"
                            class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.rtp_audio_in_mos" />

                        <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                            <template v-if="(row.record_name && row.record_path) || row.record_path === 'S3'
                            " #action-buttons>
                                <div class="flex items-center space-x-2 whitespace-nowrap">
                                    <PlayCircleIcon v-if="permissions.call_recording_play"
                                        @click="handleCallRecordingButtonClick(row.xml_cdr_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                    <!-- class="h-6 w-6 text-blue-500 hover:text-blue-700 active:h-5 active:w-5 cursor-pointer" /> -->
                                    <!-- <PauseCircleIcon v-if="currentAudioUuid === row.xml_cdr_uuid && isAudioPlaying"
                                        @click="pauseAudio"
                                        class="h-6 w-6 text-blue-500 hover:text-blue-700 active:h-5 active:w-5 cursor-pointer" /> -->

                                    <!-- <CloudArrowDownIcon v-if="page.props.auth.can.call_recording_download"
                                        @click="downloadAudio(row.xml_cdr_uuid)"
                                        class="h-6 w-6 text-gray-500 hover:text-gray-700 active:h-5 active:w-5 cursor-pointer" /> -->
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
                        :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page"
                        :links="data.links" @pagination-change-page="renderRequestedPage" />
                </template>


            </DataTable>
        </div>
    </MainLayout>


    <CallDetailsModal :show="showDetailsModal" :item="itemOptions?.item" :loading="loadingModal"
        :customClass="'sm:max-w-4xl'" @close="handleModalClose" 
        @success="showNotification" 
        @error="handleErrorResponse">
    </CallDetailsModal>

    <CallRecordingModal :show="showCallRecordingModal" :cdr_uuid="selectedUuid" :routes="routes"
        @close="showCallRecordingModal = false" @error="handleErrorResponse" @success="showNotification" />

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
import Badge from "@generalComponents/Badge.vue";
import moment from 'moment-timezone';
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import { registerLicense } from '@syncfusion/ej2-base';
import DatePicker from "./components/general/DatePicker.vue";
import CallDetailsModal from "./components/modal/CallDetailsModal.vue"
import CallRecordingModal from "./components/modal/CallRecordingModal.vue"
import Notification from "./components/notifications/Notification.vue";
import {
    PlayCircleIcon,
    PauseCircleIcon,
    CloudArrowDownIcon,
    MagnifyingGlassIcon,
} from "@heroicons/vue/24/solid";
import { DocumentArrowDownIcon } from "@heroicons/vue/24/outline";
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'

import Loading from "./components/general/Loading.vue";
import Spinner from "./components/general/Spinner.vue";

const page = usePage()
const loading = ref(false)
const showDetailsModal = ref(false);
const showCallRecordingModal = ref(false);
const loadingModal = ref(false)
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const isExporting = ref(null);
const recordingOptions = ref(null)

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
const entities = ref([])


const props = defineProps({
    showGlobal: Boolean,
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    recordingUrl: String,
    csvUrl: Object,
    routes: Object,
    permissions: Object,
});


onMounted(() => {
    getData();
    getEntities();
})

const startLocal = moment.utc(props.startPeriod).tz(props.timezone)
const endLocal = moment.utc(props.endPeriod).tz(props.timezone)

const dateRange = [
    startLocal.clone().startOf('day').toISOString(), // UTC instant for local start-of-day
    endLocal.clone().endOf('day').toISOString(),     // UTC instant for local end-of-day
]

const filterData = ref({
    search: null,
    showGlobal: props.showGlobal,
    dateRange: dateRange,
    direction: null,
    entity: null,
    status: null,
});


const showGlobal = ref(props.showGlobal);
const itemOptions = ref({});
const selectedUuid = ref(null)

const callDirections = [
    { value: 'outbound', name: 'Outbound' },
    { value: 'inbound', name: 'Inbound' },
    { value: 'local', name: 'Local' },
]

const statusOptions = [
    { name: 'Answered', value: 'answered' },
    { name: 'No Answer', value: 'no_answer' },
    { name: 'Cancelled', value: 'cancelled' },
    { name: 'Voicemail', value: 'voicemail' },
    { name: 'Missed Call', value: 'missed call' },
    { name: 'Abandoned', value: 'abandoned' },
];

const sentimentOptions = [
    { name: 'Neutral', value: 'neutral' },
    { name: 'Positive', value: 'positive' },
    { name: 'Negative', value: 'negative' },
];

const handleCallRecordingButtonClick = (uuid) => {
    showCallRecordingModal.value = true
    // getCallRecordingOptions(uuid);
    selectedUuid.value = uuid
};

// const getCallRecordingOptions = (uuid) => {
//     loadingModal.value = true
//     axios.get(props.routes.call_recording_route, {
//         params: {
//             item_uuid: uuid
//         }
//     })
//         .then((response) => {
//             recordingOptions.value = response.data;
//             console.log(recordingOptions.value);

//         }).catch((error) => {

//             handleErrorResponse(error);
//         }).finally(() => {
//             loadingModal.value = false
//         })

// }

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
            // console.log(data.value);

        }).catch((error) => {

            handleErrorResponse(error);
        }).finally(() => {
            loading.value = false
        })
}

const getEntities = () => {
    axios.get(props.routes.entities_route, {
        params: {
            filter: filterData.value
        }
    })
        .then((response) => {
            entities.value = response.data;
            // console.log(entities.value);

        }).catch((error) => {

            handleErrorResponse(error);
        }).finally(() => {
        })

}

const handleViewRequest = (itemUuid) => {
    showDetailsModal.value = true
    loadingModal.value = true
    getItemOptions(itemUuid);

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


const handleSearchButtonClick = () => {
    getData();
};

const handleFiltersReset = () => {
    filterData.value.dateRange = [
        startLocal.clone().startOf('day').toISOString(), // UTC instant for local start-of-day
        endLocal.clone().endOf('day').toISOString(),     // UTC instant for local end-of-day
    ]

    filterData.value.search = null;
    filterData.value.direction = null;
    filterData.value.entity = null;
    filterData.value.entityType = null;
    filterData.value.status = null;
    filterData.value.sentiment = null;

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
        filter: filterData._rawValue,
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
    showDetailsModal.value = false;
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
