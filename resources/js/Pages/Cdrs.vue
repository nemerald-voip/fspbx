
<template>
    <Menu :menus="menus" :domain-select-permission="domainSelectPermission" :selected-domain="selectedDomain"
        :selected-domain-uuid="selectedDomainUuid" :domains="domains"></Menu>

    <div class="m-3">
        <DataTable :filterData="filterData" @search-action="handleSearchButtonClick">
            <template #title>Call History</template>
            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>
            <template #table-header>
                <TableColumnHeader header=" "
                    class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6"></TableColumnHeader>
                <TableColumnHeader header="Caller ID Name"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"></TableColumnHeader>
                <TableColumnHeader header="Caller ID Number"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"></TableColumnHeader>
                <TableColumnHeader header="Destination" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                </TableColumnHeader>
                <TableColumnHeader header="Destination Number"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"></TableColumnHeader>
                <TableColumnHeader header="Date" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                </TableColumnHeader>
                <TableColumnHeader header="Time" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                </TableColumnHeader>
                <TableColumnHeader header="Duration" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                </TableColumnHeader>
                <TableColumnHeader header="Status" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                </TableColumnHeader>
                <TableColumnHeader header="Rec" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                </TableColumnHeader>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.xml_cdr_uuid">
                    <!-- <TableField class="whitespace-nowrap py-2 pl-4 pr-3 text-sm text-gray-500 sm:pl-6"
                        :text="row.direction" /> -->
                    <TableField :text="row.direction"
                        class="whitespace-nowrap py-2 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">
                        <ejs-tooltip :content="row.direction + ' call'" position='TopLeft' target="#destination_tooltip_target">
                            <div id="destination_tooltip_target">
                                <PhoneOutgoingIcon class="w-5 h-5 text-blue-600" v-if="row.direction === 'outbound'" />
                                <PhoneIncomingIcon class="w-5 h-5 text-green-600" v-if="row.direction === 'inbound'" />
                                <PhoneLocalIcon class="w-5 h-5 text-fuchsia-600" v-if="row.direction === 'local'" />
                            </div>
                        </ejs-tooltip>

                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.caller_id_name" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.caller_id_number" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.caller_destination" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.destination_number" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.start_date" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.start_time" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.duration" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.status" />
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template v-if="(row.record_name && row.record_path) || row.record_path === 'S3'
                            " #action-buttons>
                            <div class="flex items-center space-x-2 whitespace-nowrap">
                                <PlayCircleIcon v-if="currentAudioUuid !== row.xml_cdr_uuid || !isAudioPlaying
                                    " @click="fetchAndPlayAudio(row.xml_cdr_uuid)"
                                    class="h-6 w-6 text-blue-500 hover:text-blue-700 active:h-5 active:w-5 cursor-pointer" />
                                <PauseCircleIcon v-if="currentAudioUuid === row.xml_cdr_uuid && isAudioPlaying"
                                    @click="pauseAudio"
                                    class="h-6 w-6 text-blue-500 hover:text-blue-700 active:h-5 active:w-5 cursor-pointer" />

                                <CloudArrowDownIcon @click="downloadAudio(row.xml_cdr_uuid)"
                                    class="h-6 w-6 text-gray-500 hover:text-gray-700 active:h-5 active:w-5 cursor-pointer" />
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
                <TransitionRoot as="template" :show="loading" enter="transition-opacity duration-500 ease-out"
                    enter-from="opacity-0" enter-to="opacity-100" leave="transition-opacity duration-300 ease-in"
                    leave-from="opacity-100" leave-to="opacity-0">
                    <!-- Backdrop -->
                    <div class="absolute w-full h-full bg-gray-400 bg-opacity-30">
                        <div class="flex justify-center items-center space-x-3 mt-20">
                            <div>
                                <svg class="animate-spin  h-10 w-10 text-blue-600" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4">
                                    </circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                            </div>

                            <div class="text-lg text-blue-600 m-auto">Loading...</div>
                        </div>
                    </div>
                    <!-- End Backdrop -->

                </TransitionRoot>
            </template>

            <template #footer>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>


        </DataTable>



        <div class="px-4 sm:px-6 lg:px-8"></div>
    </div>
</template>

<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import Menu from "./components/Menu.vue";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import PhoneOutgoingIcon from "./components/icons/PhoneOutgoingIcon.vue"
import PhoneIncomingIcon from "./components/icons/PhoneIncomingIcon.vue"
import PhoneLocalIcon from "./components/icons/PhoneLocalIcon.vue"
import moment from 'moment-timezone';
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import { registerLicense } from '@syncfusion/ej2-base';


import {
    PlayCircleIcon,
    PauseCircleIcon,
    CloudArrowDownIcon,
    MagnifyingGlassIcon,
} from "@heroicons/vue/24/solid";

import {
    TransitionRoot,
} from '@headlessui/vue'

const loading = ref(false)


const props = defineProps({
    data: Object,
    menus: Array,
    domainSelectPermission: Boolean,
    selectedDomain: String,
    selectedDomainUuid: String,
    domains: Array,
    startPeriod: String,
    endPeriod: String,
    search: String,
    timezone: String,
    recordingUrl: String,
});

// console.log(props.data);

const filterData = ref({
    search: props.search,
    dateRange: [moment(props.startPeriod).startOf('day').format(), moment(props.endPeriod).endOf('day').format()],
    timezone: props.timezone,
});

const handleSearchButtonClick = (searchData) => {
    loading.value = true;
    filterData.value.search = searchData.searchQuery;
    filterData.value.dateRange = searchData.dateRange;
    router.visit("/call-detail-records", {
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
            // filterData: filterData._rawValue,
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
            // console.log(props.recordingUrl);

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


registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');



</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>