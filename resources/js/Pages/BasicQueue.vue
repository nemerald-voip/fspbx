<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Basic Queues</template>

            <template #subtitle>
                Manage queues, assigned agents, and the generated queue dialplans.
            </template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="basic-queues-search"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <div class="inline-flex rounded-md shadow-sm ring-1 ring-inset ring-gray-300">
                        <button type="button" @click="setActiveTab('queues')"
                            :class="tabButtonClass(activeTab === 'queues')">Queues</button>
                        <button v-if="permissions.agents.view" type="button" @click="setActiveTab('agents')"
                            :class="tabButtonClass(activeTab === 'agents')">Agents</button>
                    </div>

                    <a v-if="activeTab === 'queues' && permissions.queues.imports" :href="routes.queue_import"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Import
                    </a>

                    <a v-if="activeTab === 'queues' && permissions.queues.wallboard" :href="routes.wallboard"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Wallboard
                    </a>

                    <a v-if="activeTab === 'queues' && permissions.queues.active" :href="routes.queue_status"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Status
                    </a>

                    <a v-if="activeTab === 'agents'" :href="routes.agent_status"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Status
                    </a>

                    <a v-if="activeTab === 'agents' && permissions.agents.imports" :href="routes.agent_import"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Import
                    </a>

                    <button v-if="canCreateActiveTab" type="button" @click.prevent="handleCreateButtonClick"
                        class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Create
                    </button>

                    <button v-if="!filterData.showGlobal && canViewAllActiveTab" type="button"
                        @click.prevent="handleShowGlobal"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Show all
                    </button>

                    <button v-if="filterData.showGlobal && canViewAllActiveTab" type="button"
                        @click.prevent="handleShowLocal"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Show local
                    </button>
                </div>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>

            <template #table-header>
                <template v-if="activeTab === 'queues'">
                    <TableColumnHeader class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                        <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                        <div class="pl-4 flex items-center cursor-pointer select-none" @click="handleSortRequest('queue_name')">
                            <span class="mr-2">Name</span>
                            <ChevronUpIcon v-if="sortData.name === 'queue_name' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'queue_name' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                        </div>
                    </TableColumnHeader>
                    <TableColumnHeader v-if="filterData.showGlobal" header="Domain" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                        <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('queue_extension')">
                            <span class="mr-2">Extension</span>
                            <ChevronUpIcon v-if="sortData.name === 'queue_extension' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'queue_extension' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                        </div>
                    </TableColumnHeader>
                    <TableColumnHeader header="Strategy" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Agents" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Tier Rules" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Description" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
                </template>

                <template v-else>
                    <TableColumnHeader class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                        <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                        <div class="pl-4 flex items-center cursor-pointer select-none" @click="handleSortRequest('agent_name')">
                            <span class="mr-2">Agent</span>
                            <ChevronUpIcon v-if="sortData.name === 'agent_name' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'agent_name' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                        </div>
                    </TableColumnHeader>
                    <TableColumnHeader v-if="filterData.showGlobal" header="Domain" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Agent ID" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Type" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Timeout" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Contact" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Default Status" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Queues" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
                </template>
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td :colspan="selectionColspan">
                    <div class="text-sm text-center m-2">
                        <span class="font-semibold">{{ selectedItems.length }}</span> items are selected.
                        <button v-if="!selectAll && selectedItems.length !== data.total"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleSelectAll">
                            Select all {{ data.total }} items
                        </button>
                        <button v-if="selectAll"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleClearSelection">
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <template v-if="activeTab === 'queues'">
                    <tr v-for="row in data.data" :key="row.call_center_queue_uuid">
                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500" :text="row.queue_name">
                            <div class="flex items-center">
                                <input v-model="selectedItems" type="checkbox" name="queue_action_box[]"
                                    :value="row.call_center_queue_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                                <div class="ml-4" :class="{ 'cursor-pointer hover:text-gray-900': permissions.queues.update }"
                                    @click="permissions.queues.update && handleEditButtonClick(row.call_center_queue_uuid)">
                                    {{ row.queue_name }}
                                </div>
                            </div>
                        </TableField>
                        <TableField v-if="filterData.showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="domainLabel(row)" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.queue_extension" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="formatStrategy(row.queue_strategy)" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="String(row.agents_count ?? 0)" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            <Badge :text="row.queue_tier_rules_apply === 'true' ? 'On' : 'Off'"
                                v-bind="row.queue_tier_rules_apply === 'true' ? greenBadge : grayBadge" />
                        </TableField>
                        <TableField class="px-2 py-2 text-sm text-gray-500" :text="row.queue_description" />
                        <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                            <template #action-buttons>
                                <div class="flex items-center whitespace-nowrap justify-end">
                                    <PencilSquareIcon v-if="permissions.queues.update" @click="handleEditButtonClick(row.call_center_queue_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                        title="Edit" />
                                    <TrashIcon v-if="permissions.queues.destroy" @click="handleSingleItemDeleteRequest(row.call_center_queue_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                        title="Delete" />
                                </div>
                            </template>
                        </TableField>
                    </tr>
                </template>

                <template v-else>
                    <tr v-for="row in data.data" :key="row.call_center_agent_uuid">
                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500" :text="row.agent_name">
                            <div class="flex items-center">
                                <input v-model="selectedItems" type="checkbox" name="agent_action_box[]"
                                    :value="row.call_center_agent_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                                <div class="ml-4" :class="{ 'cursor-pointer hover:text-gray-900': permissions.agents.update }"
                                    @click="permissions.agents.update && handleEditButtonClick(row.call_center_agent_uuid)">
                                    {{ row.agent_name }}
                                </div>
                            </div>
                        </TableField>
                        <TableField v-if="filterData.showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="domainLabel(row)" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.agent_id" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.agent_type" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.agent_call_timeout" />
                        <TableField class="max-w-md truncate px-2 py-2 text-sm text-gray-500" :text="row.agent_contact" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            <Badge :text="row.agent_status || '-'" v-bind="statusBadge(row.agent_status)" />
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="String(row.queues_count ?? 0)" />
                        <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                            <template #action-buttons>
                                <div class="flex items-center whitespace-nowrap justify-end">
                                    <PencilSquareIcon v-if="permissions.agents.update" @click="handleEditButtonClick(row.call_center_agent_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                        title="Edit" />
                                    <TrashIcon v-if="permissions.agents.destroy" @click="handleSingleItemDeleteRequest(row.call_center_agent_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                        title="Delete" />
                                </div>
                            </template>
                        </TableField>
                    </tr>
                </template>
            </template>

            <template #empty>
                <div v-if="data.data.length === 0" class="text-center my-5">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                    <p class="mt-1 text-sm text-gray-500">Adjust your search and try again.</p>
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

    <ConfirmationModal :show="confirmationModalTrigger" @close="handleModalClose"
        @confirm="confirmAction" :header="confirmationHeader" :text="confirmationText"
        :confirm-button-label="confirmationButtonLabel" cancel-button-label="Cancel" />

    <BasicQueueForm :show="showQueueForm" :options="itemOptions" :mode="formMode" :loading="loadingForm"
        :header="queueFormHeader" @close="handleFormClose" @error="handleErrorResponse" @success="showNotification"
        @refresh-data="refreshCurrentPage" />

    <BasicQueueAgentForm :show="showAgentForm" :options="itemOptions" :mode="formMode" :loading="loadingForm"
        :header="agentFormHeader" @close="handleFormClose" @error="handleErrorResponse" @success="showNotification"
        @refresh-data="refreshCurrentPage" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import MainLayout from "../Layouts/MainLayout.vue";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import Notification from "./components/notifications/Notification.vue";
import BasicQueueForm from "./components/forms/BasicQueueForm.vue";
import BasicQueueAgentForm from "./components/forms/BasicQueueAgentForm.vue";
import Badge from "@generalComponents/Badge.vue";
import { ChevronDownIcon, ChevronUpIcon, MagnifyingGlassIcon, PencilSquareIcon, TrashIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    routes: Object,
    permissions: Object,
});

const routes = props.routes;
const permissions = props.permissions;
const activeTab = ref("queues");
const loading = ref(false);
const loadingForm = ref(false);
const currentPage = ref(1);
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const confirmationModalTrigger = ref(false);
const confirmAction = ref(null);
const confirmationHeader = ref("Are you sure?");
const confirmationText = ref("");
const confirmationButtonLabel = ref("Continue");
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);
const showQueueForm = ref(false);
const showAgentForm = ref(false);
const formMode = ref("create");
const itemOptions = ref({ item: {}, routes: {} });

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
    showGlobal: false,
});

const sortData = ref({
    name: "queue_name",
    order: "asc",
});

const activeRoutes = computed(() => activeTab.value === "queues"
    ? {
        data: routes.queue_data,
        itemOptions: routes.queue_item_options,
        selectAll: routes.queue_select_all,
        bulkDelete: routes.queue_bulk_delete,
    }
    : {
        data: routes.agent_data,
        itemOptions: routes.agent_item_options,
        selectAll: routes.agent_select_all,
        bulkDelete: routes.agent_bulk_delete,
    });

const canCreateActiveTab = computed(() => activeTab.value === "queues"
    ? permissions.queues.create
    : permissions.agents.create);

const canDestroyActiveTab = computed(() => activeTab.value === "queues"
    ? permissions.queues.destroy
    : permissions.agents.destroy);

const canViewAllActiveTab = computed(() => activeTab.value === "queues"
    ? permissions.queues.view_all
    : permissions.queues.view_all);

const bulkActions = computed(() => canDestroyActiveTab.value
    ? [{ id: "bulk_delete", label: "Delete", icon: "TrashIcon" }]
    : []);

const selectionColspan = computed(() => {
    if (activeTab.value === "queues") {
        return filterData.value.showGlobal ? 8 : 7;
    }

    return filterData.value.showGlobal ? 9 : 8;
});

const queueFormHeader = computed(() => formMode.value === "create"
    ? "Create Basic Queue"
    : `Update Basic Queue - ${itemOptions.value?.item?.queue_name || "Loading..."}`);

const agentFormHeader = computed(() => formMode.value === "create"
    ? "Create Agent"
    : `Update Agent - ${itemOptions.value?.item?.agent_name || "Loading..."}`);

onMounted(() => {
    getData();
});

const setActiveTab = (tab) => {
    activeTab.value = tab;
    sortData.value = tab === "queues"
        ? { name: "queue_name", order: "asc" }
        : { name: "agent_name", order: "asc" };
    handleClearSelection();
    getData(1);
};

const getData = (page = 1) => {
    loading.value = true;
    currentPage.value = Number(page) || 1;

    let sort = sortData.value.name;
    if (sortData.value.order === "desc") {
        sort = `-${sort}`;
    }

    axios.get(activeRoutes.value.data, {
        params: {
            filter: filterData.value,
            page: currentPage.value,
            sort,
        },
    })
        .then((response) => {
            data.value = response.data;
            currentPage.value = response.data.current_page ?? currentPage.value;
        })
        .catch(handleErrorResponse)
        .finally(() => {
            loading.value = false;
        });
};

const handleSortRequest = (column) => {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === "asc" ? "desc" : "asc";
    } else {
        sortData.value.name = column;
        sortData.value.order = "asc";
    }

    getData(currentPage.value);
};

const handleSearchButtonClick = () => getData(1);
const refreshCurrentPage = () => getData(currentPage.value);

const handleFiltersReset = () => {
    filterData.value.search = null;
    getData(1);
};

const renderRequestedPage = (url) => {
    if (!url) return;

    const urlObj = new URL(url, window.location.origin);
    getData(urlObj.searchParams.get("page") ?? 1);
};

const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    getData(1);
};

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    getData(1);
};

const handleCreateButtonClick = () => {
    formMode.value = "create";
    activeTab.value === "queues" ? showQueueForm.value = true : showAgentForm.value = true;
    getItemOptions();
};

const handleEditButtonClick = (uuid) => {
    formMode.value = "update";
    activeTab.value === "queues" ? showQueueForm.value = true : showAgentForm.value = true;
    getItemOptions(uuid);
};

const getItemOptions = (itemUuid = null) => {
    loadingForm.value = true;

    axios.post(activeRoutes.value.itemOptions, itemUuid ? { itemUuid } : {})
        .then((response) => {
            itemOptions.value = response.data;
        })
        .catch((error) => {
            handleFormClose();
            handleErrorResponse(error);
        })
        .finally(() => {
            loadingForm.value = false;
        });
};

const handleFormClose = () => {
    showQueueForm.value = false;
    showAgentForm.value = false;
    formMode.value = "create";
    itemOptions.value = { item: {}, routes: {} };
};

const handleSelectPageItems = () => {
    const key = activeTab.value === "queues" ? "call_center_queue_uuid" : "call_center_agent_uuid";
    selectedItems.value = selectPageItems.value ? data.value.data.map((item) => item[key]) : [];
};

const handleSelectAll = () => {
    axios.post(activeRoutes.value.selectAll, { filter: filterData.value })
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification("success", response.data.messages);
        })
        .catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });
};

const handleClearSelection = () => {
    selectedItems.value = [];
    selectPageItems.value = false;
    selectAll.value = false;
};

const handleSingleItemDeleteRequest = (uuid) => {
    showConfirmation({
        header: "Confirm Deletion",
        text: activeTab.value === "queues"
            ? "This action will permanently delete the selected queue, agent assignments, and generated dialplan."
            : "This action will permanently delete the selected agent and remove queue assignments.",
        button: "Delete",
        action: () => executeBulkDelete([uuid]),
    });
};

const handleBulkActionRequest = (action) => {
    if (action === "bulk_delete") {
        showConfirmation({
            header: "Confirm Deletion",
            text: activeTab.value === "queues"
                ? "This action will permanently delete the selected queues, agent assignments, and generated dialplans."
                : "This action will permanently delete the selected agents and remove queue assignments.",
            button: "Delete",
            action: () => executeBulkDelete(),
        });
    }
};

const showConfirmation = ({ header, text, button, action }) => {
    confirmationHeader.value = header;
    confirmationText.value = text;
    confirmationButtonLabel.value = button;
    confirmAction.value = action;
    confirmationModalTrigger.value = true;
};

const executeBulkDelete = (items = selectedItems.value) => {
    axios.post(activeRoutes.value.bulkDelete, { items })
        .then((response) => {
            handleModalClose();
            handleClearSelection();
            showNotification("success", response.data.messages);
            refreshCurrentPage();
        })
        .catch((error) => {
            handleModalClose();
            handleClearSelection();
            handleErrorResponse(error);
        });
};

const handleModalClose = () => {
    confirmationModalTrigger.value = false;
    confirmAction.value = null;
};

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
};

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
};

const handleErrorResponse = (error) => {
    if (error.response) {
        showNotification("error", error.response.data.errors || error.response.data.messages || { request: [error.message] });
    } else if (error.request) {
        showNotification("error", { request: [error.request] });
    } else {
        showNotification("error", { request: [error.message] });
    }
};

const tabButtonClass = (active) => [
    "px-3 py-1.5 text-sm font-semibold first:rounded-l-md last:rounded-r-md",
    active ? "bg-indigo-600 text-white" : "bg-white text-gray-700 hover:bg-gray-50",
];

const domainLabel = (row) => row.domain?.domain_description || row.domain?.domain_name || "Global";

const formatStrategy = (strategy) => String(strategy || "-")
    .replaceAll("-", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());

const greenBadge = {
    backgroundColor: "bg-green-50",
    textColor: "text-green-700",
    ringColor: "ring-green-600/20",
};

const grayBadge = {
    backgroundColor: "bg-gray-50",
    textColor: "text-gray-700",
    ringColor: "ring-gray-600/20",
};

const blueBadge = {
    backgroundColor: "bg-blue-50",
    textColor: "text-blue-700",
    ringColor: "ring-blue-600/20",
};

const amberBadge = {
    backgroundColor: "bg-amber-50",
    textColor: "text-amber-700",
    ringColor: "ring-amber-600/20",
};

const statusBadge = (status) => {
    if (status === "Available") return greenBadge;
    if (status === "On Break") return amberBadge;
    return grayBadge;
};
</script>
