
<template>
    <Menu :menus="menus" :domain-select-permission="domainSelectPermission" :selected-domain="selectedDomain"
        :selected-domain-uuid="selectedDomainUuid" :domains="domains"></Menu>

    <div class="m-3">


        <DataTable :filterData="filterData" @search-action="handleSearchButtonClick">
            <template #title>Call History</template>
            <template #table-header>
                <TableColumnHeader header="Direction"
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
                <tr v-for="row in data" :key="row.xml_cdr_uuid">
                    <TableField class="whitespace-nowrap py-2 pl-4 pr-3 text-sm text-gray-500 sm:pl-6"
                        :text="row.direction" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.caller_id_name" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.caller_id_number" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.caller_destination" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.destination_number" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.start_date" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.start_time" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.duration" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.status" />
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template v-if="(row.record_name && row.record_path) || row.record_path==='S3'" #action-buttons>
                            <PlayCircleIcon v-if="currentAudioUuid !== row.xml_cdr_uuid || !isAudioPlaying"
                                @click="fetchAndPlayAudio(row.xml_cdr_uuid)"
                                class="h-6 w-6 text-blue-500 hover:text-blue-700 active:h-5 active:w-5 cursor-pointer" />
                            <PauseCircleIcon v-if="currentAudioUuid === row.xml_cdr_uuid && isAudioPlaying"
                                @click="pauseAudio"
                                class="h-6 w-6 text-blue-500 hover:text-blue-700 active:h-5 active:w-5 cursor-pointer" />
                        </template>

                    </TableField>
                </tr>
            </template>
        </DataTable>

        <div class="px-4 sm:px-6 lg:px-8">

        </div>

    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { router } from '@inertiajs/vue3'
import Menu from './components/Menu.vue';
import DataTable from './components/general/DataTable.vue';
import TableColumnHeader from './components/general/TableColumnHeader.vue';
import TableField from './components/general/TableField.vue';
import { PlayCircleIcon } from "@heroicons/vue/24/solid"
import { PauseCircleIcon } from "@heroicons/vue/24/solid"



const props = defineProps({
    data: Array,
    menus: Array,
    domainSelectPermission: Boolean,
    selectedDomain: String,
    selectedDomainUuid: String,
    domains: Array,
    startPeriod: Date,
    endPeriod: Date,
    search: String,
    timezone: String,
    recording: String,
});

const filterData = ref({
    search: props.search,
    dateRange: [props.startPeriod, props.endPeriod],
    timezone: props.timezone,
});

console.log(props.data);

const handleDateRangeUpdate = (dateRange) => {
    filterData.value.dateRange = dateRange;
    router.visit('/call-detail-records', {
        data: {
            filterData: filterData._rawValue,
        },
        preserveScroll: true,
        preserveState: true,
        only: [
            'data',
        ],
    })
};

const handleSearchButtonClick = (searchData) => {
    filterData.value.search = searchData.searchQuery;
    filterData.value.dateRange = searchData.dateRange;
    router.visit('/call-detail-records', {
        data: {
            filterData: filterData._rawValue,
        },
        preserveScroll: true,
        preserveState: true,
        only: [
            'data',
        ],
    })

}

const currentAudio = ref(null);
const currentAudioUuid = ref(null);
const isAudioPlaying = ref(false);

const fetchAndPlayAudio = (uuid) => {

    router.visit('/call-detail-records', {
        data: {
            callUuid: uuid,
        },
        preserveScroll: true,
        preserveState: true,
        only: [
            'recording',
        ],
        onSuccess: (page) => {
            console.log(props.recording);

            // Stop the currently playing audio (if any)
            if (currentAudio.value) {
                currentAudio.value.pause();
                currentAudio.value.currentTime = 0; // Reset the playback position
            }

            currentAudioUuid.value = uuid;
            isAudioPlaying.value = true;

            currentAudio.value = new Audio(props.recording);
            currentAudio.value.play();

            // Add an event listener for when the audio ends
            currentAudio.value.addEventListener('ended', () => {
                isAudioPlaying.value = false;
                currentAudioUuid.value = null;
            });
        },

    });


}

const pauseAudio = () => {
    // Check if currentAudio has an Audio object before calling pause
    if (currentAudio.value) {
        currentAudio.value.pause();
        isAudioPlaying.value = false;
    }
}

// const audioEnded = () => {
//     currentAudioUuid.value = null;
//     isAudioPlaying.value = false;
// }


</script>
