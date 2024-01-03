
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
                    <TableColumnHeader header="Destination"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"></TableColumnHeader>
                    <TableColumnHeader header="Destination Number"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"></TableColumnHeader>
                    <TableColumnHeader header="Date"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"></TableColumnHeader>
                    <TableColumnHeader header="Time"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"></TableColumnHeader>
                    <TableColumnHeader header="Duration"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"></TableColumnHeader>
                    <TableColumnHeader header="Status"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"></TableColumnHeader>
                    <TableColumnHeader header=""
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"></TableColumnHeader>
            </template>

            <template #table-body>
                <tr v-for="row in data" :key="row.xml_cdr_uuid">
                    <TableField class="whitespace-nowrap py-2 pl-4 pr-3 text-sm text-gray-500 sm:pl-6" :text="row.direction" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.caller_id_name" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.caller_id_number" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.caller_destination" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.destination_number" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.start_date" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.start_time" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.duration" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.status" />
                </tr>
            </template>
        </DataTable>


        <div class="px-4 sm:px-6 lg:px-8">

        </div>

    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3'
import Menu from './components/Menu.vue';
import DataTable from './components/general/DataTable.vue';
import TableColumnHeader from './components/general/TableColumnHeader.vue';
import TableField from './components/general/TableField.vue';

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
});

const filterData = ref({
    search: props.search,
    dateRange: [props.startPeriod, props.endPeriod],
    timezone: props.timezone,
});

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


</script>
