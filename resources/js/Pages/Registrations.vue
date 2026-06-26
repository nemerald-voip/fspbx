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

                <button v-if="!filterData.showGlobal && permissions.view_global" type="button"
                    @click.prevent="handleShowGlobal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show global
                </button>

                <button v-if="filterData.showGlobal && permissions.view_global" type="button"
                    @click.prevent="handleShowLocal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show local
                </button>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>
            <template #table-header>
                <TableColumnHeader header="User"
                    class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems" @click.stop
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <div class="pl-4 flex items-center cursor-pointer select-none" @click="handleSortRequest('sip_auth_user')">
                        <span class="mr-2">User</span>
                        <ChevronUpIcon v-if="sortData.name === 'sip_auth_user' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'sip_auth_user' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>

                <TableColumnHeader v-if="filterData.showGlobal" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('sip_auth_realm')">
                        <span class="mr-2">Domain</span>
                        <ChevronUpIcon v-if="sortData.name === 'sip_auth_realm' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'sip_auth_realm' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>


                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('agent')">
                        <span class="mr-2">Agent</span>
                        <ChevronUpIcon v-if="sortData.name === 'agent' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'agent' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <!-- <TableColumnHeader header="Contact" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" /> -->
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('lan_ip')">
                        <span class="mr-2">LAN IP</span>
                        <ChevronUpIcon v-if="sortData.name === 'lan_ip' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'lan_ip' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('wan_ip')">
                        <span class="mr-2">WAN IP</span>
                        <ChevronUpIcon v-if="sortData.name === 'wan_ip' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'wan_ip' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('port')">
                        <span class="mr-2">Port</span>
                        <ChevronUpIcon v-if="sortData.name === 'port' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'port' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('status')">
                        <span class="mr-2">Status</span>
                        <ChevronUpIcon v-if="sortData.name === 'status' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'status' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('expsecs')">
                        <span class="mr-2">Exp Sec</span>
                        <ChevronUpIcon v-if="sortData.name === 'expsecs' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'expsecs' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('ping_time')">
                        <span class="mr-2">Ping</span>
                        <ChevronUpIcon v-if="sortData.name === 'ping_time' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'ping_time' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('sip_profile_name')">
                        <span class="mr-2">Sip Profile</span>
                        <ChevronUpIcon v-if="sortData.name === 'sip_profile_name' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'sip_profile_name' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader header="Action" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td :colspan="filterData.showGlobal ? 11 : 10">
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
                <tr v-for="row in data.data" :key="row.call_id || row.contact">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 " :text="row.user">
                        <div class="flex items-center">
                            <input v-if="row.call_id" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.call_id" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-9">
                                {{ row.user }}
                            </div>

                        </div>
                    </TableField>

                    <TableField v-if="filterData.showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
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
                    :page-size="perPage" :page-size-options="props.pagination?.per_page_options ?? []"
                    :show-page-size-selector="true"
                    @pagination-change-page="renderRequestedPage" @page-size-change="handlePageSizeChange" />
            </template>
        </DataTable>
        <div class="px-4 sm:px-6 lg:px-8"></div>
    </div>

    <ConfirmationModal :show="confirmationActionTrigger" @close="handleModalClose" @confirm="confirmAction"
        :header="confirmationHeader" :text="confirmationText"
        :confirm-button-label="confirmationButtonLabel" cancel-button-label="Cancel" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from 'axios';
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import Badge from "./components/general/Badge.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { ChevronDownIcon, ChevronUpIcon, MagnifyingGlassIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import MainLayout from "../Layouts/MainLayout.vue";
import RestartIcon from "./components/icons/RestartIcon.vue";
import SyncIcon from "./components/icons/SyncIcon.vue";
import LinkOffIcon from "./components/icons/LinkOffIcon.vue";
import Notification from "./components/notifications/Notification.vue";

const loading = ref(false)
const currentPage = ref(1);
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const confirmAction = ref(null);
const confirmationHeader = ref('Are you sure?');
const confirmationText = ref('');
const confirmationButtonLabel = ref('Continue');
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);
const confirmationActionTrigger = ref(false);

const props = defineProps({
    pagination: Object,
    routes: Object,
    permissions: Object,
    showGlobal: Boolean,
});

const routes = props.routes;
const permissions = props.permissions;
const perPage = ref(props.pagination?.per_page);

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
    showGlobal: props.showGlobal,
});

const sortData = ref({
    name: 'sip_auth_user',
    order: 'asc',
});

const bulkActions = computed(() => [
    { id: 'bulk_restart', label: 'Restart', icon: 'RestartIcon' },
    { id: 'bulk_sync', label: 'Sync', icon: 'SyncIcon' },
    { id: 'bulk_unregister', label: 'Unregister', icon: 'LinkOffIcon' },
]);

onMounted(() => getData());


const handleBulkActionRequest = (action) => {
    if (action === 'bulk_restart') {
        showConfirmation({
            text: 'Restart the selected registration(s)?',
            button: 'Restart',
            action: () => executeAction('reboot'),
        });
    }

    if (action === 'bulk_sync') {
        showConfirmation({
            text: 'Sync the selected registration(s)?',
            button: 'Sync',
            action: () => executeAction('provision'),
        });
    }

    if (action === 'bulk_unregister') {
        showConfirmation({
            text: 'Unregister the selected registration(s)?',
            button: 'Unregister',
            action: () => executeAction('unregister'),
        });
    }
}

const showConfirmation = ({ text, button, action }) => {
    confirmationHeader.value = 'Are you sure?';
    confirmationText.value = text;
    confirmationButtonLabel.value = button;
    confirmAction.value = action;
    confirmationActionTrigger.value = true;
};

const executeAction = (action, items = selectedItems.value) => {
    axios.post(routes.action, { items, action })
        .then((response) => {
            showNotification('success', response.data.messages);
            handleModalClose();
            handleClearSelection();
            refreshCurrentPage();
        }).catch((error) => {
            handleClearSelection();
            handleModalClose();
            handleErrorResponse(error);
        });
}

const handleSelectAll = () => {
    axios.post(routes.select_all, { filter: filterData.value })
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification('success', response.data.messages);
        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });
};


const handleAction = (reg, action) => {
    executeAction(action, [reg.call_id]);
}

const handleRefreshButtonClick = () => {
    refreshCurrentPage();
}


const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    getData(1);
}

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    getData(1);
}

const handleSortRequest = (column) => {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === 'asc' ? 'desc' : 'asc';
    } else {
        sortData.value.name = column;
        sortData.value.order = 'asc';
    }

    getData(currentPage.value);
};

const getData = (page = 1) => {
    loading.value = true;
    currentPage.value = Number(page) || 1;

    const sort = sortData.value.order === 'desc' ? `-${sortData.value.name}` : sortData.value.name;

    axios.get(routes.data_route, {
        params: {
            filter: filterData.value,
            page: currentPage.value,
            per_page: perPage.value,
            sort,
        }
    })
        .then((response) => {
            data.value = response.data;
            currentPage.value = response.data.current_page ?? currentPage.value;
            handleClearSelection();
        })
        .catch(handleErrorResponse)
        .finally(() => {
            loading.value = false;
        });
};

const handleSearchButtonClick = () => {
    getData(1);
};

const refreshCurrentPage = () => {
    getData(currentPage.value);
};

const handlePageSizeChange = (newPerPage) => {
    perPage.value = newPerPage;
    getData(1);
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    getData(1);
}


const renderRequestedPage = (url) => {
    if (!url) return;
    const urlObj = new URL(url, window.location.origin);
    getData(urlObj.searchParams.get("page") ?? 1);
};

const handleErrorResponse = (error) => {
    if (error.response) {
        showNotification('error', error.response.data.errors || error.response.data.messages || { request: [error.message] });
    } else if (error.request) {
        showNotification('error', { request: [error.request] });
        console.log(error.request);
    } else {
        showNotification('error', { request: [error.message] });
        console.log(error.message);
    }
}

const handleSelectPageItems = () => {
    selectedItems.value = selectPageItems.value
        ? data.value.data.map((item) => item.call_id).filter(Boolean)
        : [];
};



const handleClearSelection = () => {
    selectedItems.value = [];
    selectPageItems.value = false;
    selectAll.value = false;
}

const handleModalClose = () => {
    confirmationActionTrigger.value = false;
    confirmAction.value = null;
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
