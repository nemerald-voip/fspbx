<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Registrations</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-candidate"
                        id="mobile-search-candidate"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick"/>
                    <input type="text" v-model="filterData.search" name="desktop-search-candidate"
                        id="desktop-search-candidate"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick"/>
                </div>
            </template>

            <template #action>
                <button type="button" @click.prevent="handleRefreshButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Refresh
                </button>

                <button v-if="!showGlobal && page.props.auth.can.registrations_view_global" type="button"
                    @click.prevent="handleShowGlobal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show global
                </button>

                <button v-if="showGlobal && page.props.auth.can.registrations_view_global" type="button"
                    @click.prevent="handleShowLocal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show local
                </button>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>
            <template #table-header>
                <TableColumnHeader header="User"
                    class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest"
                        :has-selected-items="selectedItems.length > 0" />
                    <span class="pl-4">User</span>
                </TableColumnHeader>

                <TableColumnHeader v-if="showGlobal" header="Domain"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />


                <TableColumnHeader header="Agent" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <!-- <TableColumnHeader header="Contact" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" /> -->
                <TableColumnHeader header="LAN IP" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="WAN IP" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Port" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Status" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Exp Sec" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Ping" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Sip Profile" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Action" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
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
                <tr v-for="row in data.data" :key="row.contact">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 " :text="row.user">
                        <div class="flex items-center">
                            <input v-if="row.call_id" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-9">
                                {{ row.user }}
                            </div>

                        </div>
                    </TableField>

                    <TableField v-if="showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.sip_auth_realm" />

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.agent" />

                    <!-- <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.source_formatted" /> -->

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.lan_ip" />

                    <TableField class=" px-2 py-2 text-sm text-gray-500" :text="row.wan_ip" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.port" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.status">
                        <Badge :text="row.status" :backgroundColor="determineColor(row.status).backgroundColor"
                            :textColor="determineColor(row.status).textColor"
                            :ringColor="determineColor(row.status).ringColor" />

                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.expsecs" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.ping_time" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.sip_profile_name" />

                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap">
                                <ejs-tooltip :content="'Restart'" position='TopCenter' target="#restart_tooltip_target">
                                    <div id="restart_tooltip_target">
                                        <RestartIcon @click="handleAction(row, 'reboot')"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip :content="'Sync'" position='TopCenter' target="#sync_tooltip_target">
                                    <div id="sync_tooltip_target">
                                        <SyncIcon @click="handleAction(row, 'provision')"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip :content="'Unregister'" position='TopCenter'
                                    target="#unregister_tooltip_target">
                                    <div id="unregister_tooltip_target">
                                        <LinkOffIcon @click="handleAction(row, 'unregister')"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>


                                <!-- <div id="tooltip-no-arrow-sync" role="tooltip" 
                                    class="inline-block absolute invisible text-xs z-10 py-1 px-2 font-medium text-white rounded-sm shadow-sm opacity-0 tooltip dark:bg-gray-600 delay-150" >
                                tooltip
                                </div> -->


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

    <ConfirmationModal :show="confirmationActionTrigger" @close="confirmationActionTrigger = false" @confirm="confirmAction"
        :header="'Are you sure?'" :text="'Are you sure you want to proceed with this bulk action?'"
        :confirm-button-label="bulkActionLabel" cancel-button-label="Cancel" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
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
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import Badge from "./components/general/Badge.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import RestartIcon from "./components/icons/RestartIcon.vue";
import SyncIcon from "./components/icons/SyncIcon.vue";
import LinkOffIcon from "./components/icons/LinkOffIcon.vue";
import Notification from "./components/notifications/Notification.vue";

const page = usePage()
const loading = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const confirmAction = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const confirmationActionTrigger = ref(false);
const bulkActionLabel = ref('');

const props = defineProps({
    data: Object,
    showGlobal: Boolean,
    routes: Object,
    // itemData: Object,
    // itemOptions: Object,
});


const filterData = ref({
    search: null,
    showGlobal: props.showGlobal,
});

const showGlobal = ref(props.showGlobal);

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [
        {
            id: 'bulk_restart',
            label: 'Restart',
            icon: 'RestartIcon'
        },
        {
            id: 'bulk_sync',
            label: 'Sync',
            icon: 'SyncIcon'
        },
        {
            id: 'bulk_unregister',
            label: 'Unregister',
            icon: 'LinkOffIcon'
        },

    ];

    return actions;
});

onMounted(() => {
    // console.log(props.data);
});


const handleBulkActionRequest = (action) => {
    if (action === 'bulk_restart') {
        confirmationActionTrigger.value = true;
        bulkActionLabel.value = 'Restart';
        confirmAction.value = () => executeBulkAction('reboot');
    }

    if (action === 'bulk_sync') {
        confirmationActionTrigger.value = true;
        bulkActionLabel.value = 'Sync';
        confirmAction.value = () => executeBulkAction('provision');
    }

    if (action === 'bulk_unregister') {
        confirmationActionTrigger.value = true;
        bulkActionLabel.value = 'Unregister';
        confirmAction.value = () => executeBulkAction('unregister');
    }

}

const executeBulkAction = (action) => {
    axios.post(props.routes.action,
        { 'regs': selectedItems.value, 'action': action },
    )
        .then((response) => {
            showNotification('success', response.data.messages);
            handleModalClose();
            handleClearSelection();
        }).catch((error) => {
            handleClearSelection();
            handleModalClose();
            handleFormErrorResponse(error);
        });
}




const handleSelectAll = () => {
    axios.post(props.routes.select_all, filterData._rawValue)
        .then((response) => {

            // Convert props.data.data to an array using Object.values()
            const currentPageItems = Object.values(props.data.data);

            // Set selected items to all the full row data returned by the server
            selectedItems.value = response.data.items.map(item => {
                // Find the row in the current data that matches the call_id
                const matchedRow = currentPageItems.find(row => row.call_id === item.call_id);
                // If found, return the row already present on the page; otherwise, return the received item
                return matchedRow || item;
            });

            selectAll.value = true;
            showNotification('success', response.data.messages);

        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });

};


const handleAction = (reg, action) => {
    axios.post(props.routes.action,
        { 'regs': [reg], 'action': action },
    )
        .then((response) => {
            showNotification('success', response.data.messages);

            handleClearSelection();
        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });
}

const handleRefreshButtonClick = () => {
    handleSearchButtonClick();
}


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
            'showGlobal',
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


const handleErrorResponse = (error) => {
    if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
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
        const currentPageItems = Object.values(props.data.data);
        selectedItems.value = currentPageItems.map(item => item);

    } else {
        selectedItems.value = [];
    }
};



const handleClearSelection = () => {
    selectedItems.value = [],
    selectPageItems.value = false;
    selectAll.value = false;
}

const handleModalClose = () => {
    confirmationActionTrigger.value = false;
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

const determineColor = (status) => {
    switch (status) {
        default:
            return {
                backgroundColor: 'bg-blue-50',
                textColor: 'text-blue-700',
                ringColor: 'ring-blue-600/20'
            };
    }
};

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>
