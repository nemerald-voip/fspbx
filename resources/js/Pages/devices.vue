
<template>
    <Menu :menus="menus" :domain-select-permission="domainSelectPermission" :selected-domain="selectedDomain"
        :selected-domain-uuid="selectedDomainUuid" :domains="domains"></Menu>

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Devices</template>

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
                    <input type="text" name="add-new"
                           id="add-new"
                           class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                           placeholder="Search" />
                    <input type="text" name="add-new"
                           id="add-new"
                           class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                           placeholder="Search" />
                </div>

                <div class="relative min-w-36 mb-2 shrink-0 sm:mr-4">

                </div>

                <div class="relative min-w-64 mb-2 shrink-0 sm:mr-4">

                </div>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>
            <template #table-header>
                <TableColumnHeader v-if="deviceRestartPermission" header=" "
                    class="py-3.5 text-sm font-semibold text-gray-900 text-center">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="selectallCheckbox">
                        <label class="form-check-label" for="selectallCheckbox">&nbsp;</label>
                    </div>
                </TableColumnHeader>
                <TableColumnHeader  v-if="deviceGlobalView" header="Domain" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="MAC Address" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Name" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Template" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Profile" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Assigned extension" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Action" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.device_uuid">
                    <!-- <TableField class="whitespace-nowrap py-2 pl-4 pr-3 text-sm text-gray-500 sm:pl-6"
                        :text="row.direction" /> -->
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500 text-center" v-if="deviceRestartPermission">
                        <div v-if="row.extension" class="form-check">
                            <input type="checkbox" name="action_box[]" value=""
                                   data-extension-uuid=""
                                   class="form-check-input action_checkbox">
                            <label class="form-check-label" >&nbsp;</label>
                        </div>
                    </TableField>
                    <TableField v-if="deviceGlobalView" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.domain_name" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.device_address" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.device_label" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.device_template" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.profile_name" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <a v-if="row.extension_edit_path" :href="row.extension_edit_path">{{row.extension}}</a>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center space-x-2 whitespace-nowrap">
                                <DocumentTextIcon v-if="row.edit_path" @click="handleEdit(row.edit_path)" class="h-5 w-5 text-black-500 hover:text-black-500 active:h-5 active:w-5 cursor-pointer" />
                                <CogIcon v-if="row.send_notify_path" @click="handleRestart(row.send_notify_path)" class="h-5 w-5 text-black-500 hover:text-black-500 active:h-5 active:w-5 cursor-pointer" />
                                <TrashIcon v-if="row.destroy_path" @click="handleDestroy(row.destroy_path)" class="h-5 w-5 text-black-500 hover:text-black-500 active:h-5 active:w-5 cursor-pointer" />
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
import SelectBox from "./components/general/SelectBox.vue"
import moment from 'moment-timezone';
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import { registerLicense } from '@syncfusion/ej2-base';
import DatePicker from "./components/general/DatePicker.vue";
import {
    DocumentTextIcon,
    TrashIcon,
    CogIcon,
    MagnifyingGlassIcon,
} from "@heroicons/vue/24/solid";

import {
    TransitionRoot,
} from '@headlessui/vue'

import {
    startOfDay, endOfDay,
} from 'date-fns';

const today = new Date();

const loading = ref(false)


const props = defineProps({
    data: Object,
    menus: Array,
    domains: Array,
    domainSelectPermission: Boolean,
    deviceRestartPermission: Boolean,
    deviceGlobalView: Boolean,
    selectedDomain: String,
    selectedDomainUuid: String,
    search: String
});

// console.log(props.data);

const filterData = ref({
    search: props.search
});

const handleUpdateCallDirectionFilter = (newValue) => {
    console.log(newValue);
}

const handleEdit = (url) => {
    window.location = url;
}

const handleDestroy = (url) => {
    router.delete(url, {
        preserveScroll: true,
        preserveState: true,
        only: ["data"],
        onSuccess: (page) => {
            console.log(page)
        }
    });
}

const handleRestart = (url) => {
    router.post(url, {
        preserveScroll: true,
        preserveState: true,
        only: ["data"],
        onSuccess: (page) => {
            console.log(page)
        }
    });
}

const handleSearchButtonClick = () => {
    loading.value = true;

    router.visit("/devices", {
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

const handleUpdateDateRange = (newDateRange) => {
    filterData.value.dateRange = newDateRange;
}

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";</style>
