<template>
    <MainLayout />

    <div class="m-3">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="mb-6 mt-2 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-accent-fg">Outbound Dialer</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-heading sm:text-3xl">Basic Dialer</h1>
                    <p class="mt-1 text-sm text-muted">Lightweight outbound campaigns and reusable contact lists.</p>
                </div>
            </div>
        </div>

        <div class="mb-6 border-b border-default px-4 sm:px-6 lg:px-8">
            <nav class="-mb-px flex gap-0.5 overflow-x-auto sm:gap-2" aria-label="Tabs">
                <button v-for="tab in tabs" :key="tab.id" type="button"
                    :class="[
                        'group relative -mb-px inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-t-lg px-3 py-2.5 text-sm font-semibold tracking-tight transition-colors sm:gap-2.5 sm:px-6 sm:py-3.5 sm:text-base',
                        activeTab === tab.id
                            ? 'text-accent-fg'
                            : 'text-muted hover:bg-surface-3 hover:text-heading'
                    ]"
                    @click="switchTab(tab.id)">
                    <component :is="tab.icon" class="h-4 w-4 sm:h-5 sm:w-5"
                        :class="activeTab === tab.id ? 'text-accent-fg' : 'text-subtle group-hover:text-body'" />
                    <span>{{ tab.label }}</span>
                    <span v-if="tab.count !== undefined && tab.count !== null"
                        :class="[
                            'ml-0.5 rounded-full px-2 py-0.5 text-xs font-semibold sm:ml-1 sm:px-2.5',
                            activeTab === tab.id
                                ? 'bg-accent-subtle text-accent-fg'
                                : 'bg-surface-3 text-body group-hover:bg-surface-3'
                        ]">
                        {{ tab.count }}
                    </span>
                    <span v-if="activeTab === tab.id"
                        class="absolute inset-x-2 -bottom-px h-[3px] rounded-full bg-accent sm:inset-x-3"></span>
                </button>
            </nav>
        </div>

        <div v-if="activeTab === 'overview'" class="px-4 sm:px-6 lg:px-8">
            <BasicDialerOverview :route="routes.overview"
                @open-campaign="handleStatusButtonClick" @error="handleErrorResponse" />
        </div>

        <DataTable v-else @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>{{ activeTab === "campaigns" ? "Campaigns" : "Contact Lists" }}</template>

            <template #subtitle>
                {{ activeTab === "campaigns" ? "Manage lightweight outbound dialer campaigns." : "Manage reusable dialer contact lists." }}
            </template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-subtle" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-basic-dialer"
                        id="mobile-search-basic-dialer"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:hidden"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                    <input type="text" v-model="filterData.search" name="desktop-search-basic-dialer"
                        id="desktop-search-basic-dialer"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:block"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <button v-if="permissions.create" type="button" @click.prevent="handleCreateButtonClick"
                    class="rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                    Create
                </button>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>

            <template #table-header>
                <template v-if="activeTab === 'campaigns'">
                    <TableColumnHeader
                        class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-heading items-center justify-start">
                        <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                            class="h-4 w-4 rounded border-strong text-accent-fg">
                        <div class="pl-4 flex items-center cursor-pointer select-none" @click="handleSortRequest('name')">
                            <span class="mr-2">Name</span>
                            <ChevronUpIcon v-if="sortData.name === 'name' && sortData.order === 'asc'"
                                class="h-4 w-4 text-muted" />
                            <ChevronDownIcon v-else-if="sortData.name === 'name' && sortData.order === 'desc'"
                                class="h-4 w-4 text-muted" />
                        </div>
                    </TableColumnHeader>
                    <TableColumnHeader header="Status" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                    <TableColumnHeader header="List" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                    <TableColumnHeader header="Recipients" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                    <TableColumnHeader header="Destination" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                    <TableColumnHeader header="Pacing" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                    <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-heading" />
                </template>

                <template v-else>
                    <TableColumnHeader
                        class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-heading items-center justify-start">
                        <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                            class="h-4 w-4 rounded border-strong text-accent-fg">
                        <div class="pl-4 flex items-center cursor-pointer select-none" @click="handleSortRequest('name')">
                            <span class="mr-2">Name</span>
                            <ChevronUpIcon v-if="sortData.name === 'name' && sortData.order === 'asc'"
                                class="h-4 w-4 text-muted" />
                            <ChevronDownIcon v-else-if="sortData.name === 'name' && sortData.order === 'desc'"
                                class="h-4 w-4 text-muted" />
                        </div>
                    </TableColumnHeader>
                    <TableColumnHeader header="Enabled" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                    <TableColumnHeader header="Contacts" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                    <TableColumnHeader header="Campaigns" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                    <TableColumnHeader header="Description" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                    <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-heading" />
                </template>
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td :colspan="activeTab === 'campaigns' ? 7 : 6">
                    <div class="text-sm text-center m-2">
                        <span class="font-semibold">{{ selectedItems.length }}</span> items are selected.
                        <button v-if="!selectAll && selectedItems.length !== data.total"
                            class="text-info rounded py-2 px-2 hover:bg-info-subtle hover:text-info focus:outline-none focus:ring-1 focus:bg-info-subtle focus:ring-focus transition duration-500 ease-in-out"
                            @click="handleSelectAll">
                            Select all {{ data.total }} items
                        </button>
                        <button v-if="selectAll"
                            class="text-info rounded py-2 px-2 hover:bg-info-subtle hover:text-info focus:outline-none focus:ring-1 focus:bg-info-subtle focus:ring-focus transition duration-500 ease-in-out"
                            @click="handleClearSelection">
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <template v-if="activeTab === 'campaigns'">
                    <tr v-for="row in data.data" :key="row.basic_dialer_campaign_uuid">
                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-muted" :text="row.name">
                            <div class="flex items-center">
                                <input v-model="selectedItems" type="checkbox" name="action_box[]"
                                    :value="row.basic_dialer_campaign_uuid"
                                    class="h-4 w-4 rounded border-strong text-accent-fg">
                                <div class="ml-4" :class="{ 'cursor-pointer hover:text-heading': permissions.update }"
                                    @click="permissions.update && handleEditButtonClick(row.basic_dialer_campaign_uuid)">
                                    {{ row.name }}
                                </div>
                            </div>
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted">
                            <Badge :text="row.status" v-bind="statusBadgeProps(row.status)" />
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted"
                            :text="row.contact_list?.name || '-'" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted">
                            {{ row.recipients_count ?? 0 }}
                            <span class="text-subtle">/ {{ row.pending_recipients_count ?? 0 }} pending</span>
                        </TableField>
                        <TableField class="px-2 py-2 text-sm text-muted"
                            :text="row.destination_label || row.destination_type || '-'" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted"
                            :text="`${row.max_concurrent_calls || 1} at a time, ${row.seconds_between_calls || 0}s gap`" />
                        <TableField class="whitespace-nowrap px-2 py-1 text-sm text-muted">
                            <template #action-buttons>
                                <div class="flex items-center whitespace-nowrap justify-end">
                                    <EyeIcon v-if="activeTab === 'campaigns'"
                                        @click="handleStatusButtonClick(row.basic_dialer_campaign_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer"
                                        title="Status" />
                                    <PlayIcon v-if="permissions.start && ['draft', 'paused', 'stopped'].includes(row.status)"
                                        @click="executeCampaignAction('start', row.basic_dialer_campaign_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer"
                                        title="Start" />
                                    <PauseCircleIcon v-if="permissions.start && row.status === 'running'"
                                        @click="executeCampaignAction('pause', row.basic_dialer_campaign_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer"
                                        title="Pause" />
                                    <StopIcon v-if="permissions.start && ['running', 'paused'].includes(row.status)"
                                        @click="executeCampaignAction('stop', row.basic_dialer_campaign_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer"
                                        title="Stop" />
                                    <PencilSquareIcon v-if="permissions.update"
                                        @click="handleEditButtonClick(row.basic_dialer_campaign_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer"
                                        title="Edit" />
                                    <TrashIcon v-if="permissions.destroy"
                                        @click="handleSingleItemDeleteRequest(row.basic_dialer_campaign_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer"
                                        title="Delete" />
                                </div>
                            </template>
                        </TableField>
                    </tr>
                </template>

                <template v-else>
                    <tr v-for="row in data.data" :key="row.basic_dialer_contact_list_uuid">
                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-muted" :text="row.name">
                            <div class="flex items-center">
                                <input v-model="selectedItems" type="checkbox" name="action_box[]"
                                    :value="row.basic_dialer_contact_list_uuid"
                                    class="h-4 w-4 rounded border-strong text-accent-fg">
                                <div class="ml-4" :class="{ 'cursor-pointer hover:text-heading': permissions.update }"
                                    @click="permissions.update && handleEditButtonClick(row.basic_dialer_contact_list_uuid)">
                                    {{ row.name }}
                                </div>
                            </div>
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted">
                            <Badge :text="row.enabled ? 'True' : 'False'" v-bind="enabledBadgeProps(row.enabled)" />
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted"
                            :text="row.contacts_count ?? 0" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted"
                            :text="row.campaigns_count ?? 0" />
                        <TableField class="px-2 py-2 text-sm text-muted" :text="row.description" />
                        <TableField class="whitespace-nowrap px-2 py-1 text-sm text-muted">
                            <template #action-buttons>
                                <div class="flex items-center whitespace-nowrap justify-end">
                                    <PencilSquareIcon v-if="permissions.update"
                                        @click="handleEditButtonClick(row.basic_dialer_contact_list_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer"
                                        title="Edit" />
                                    <TrashIcon v-if="permissions.destroy"
                                        @click="handleSingleItemDeleteRequest(row.basic_dialer_contact_list_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer"
                                        title="Delete" />
                                </div>
                            </template>
                        </TableField>
                    </tr>
                </template>
            </template>

            <template #empty>
                <div v-if="data.data.length === 0" class="text-center my-5">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-subtle" />
                    <h3 class="mt-2 text-sm font-semibold text-heading">No results found</h3>
                    <p class="mt-1 text-sm text-muted">Adjust your search and try again.</p>
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

    <ConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmAction" :header="confirmationHeader" :text="confirmationText"
        :confirm-button-label="confirmationButtonLabel" cancel-button-label="Cancel" />

    <BasicDialerCampaignForm v-if="activeForm === 'campaigns'" :show="showForm" :options="itemOptions"
        :mode="formMode" :loading="loadingForm" :header="formHeader" @close="handleFormClose"
        @error="handleErrorResponse" @success="showNotification" @refresh-data="refreshCurrentPage" />

    <BasicDialerContactListForm v-if="activeForm === 'contact_lists'" :show="showForm" :options="itemOptions"
        :mode="formMode" :loading="loadingForm" :header="formHeader" @close="handleFormClose"
        @error="handleErrorResponse" @success="showNotification" @refresh-data="refreshCurrentPage" />

    <BasicDialerCampaignStatusModal :show="showStatusModal" :route="statusRoute" @close="handleStatusModalClose"
        @error="handleErrorResponse" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import Notification from "./components/notifications/Notification.vue";
import BasicDialerCampaignForm from "./components/forms/BasicDialerCampaignForm.vue";
import BasicDialerContactListForm from "./components/forms/BasicDialerContactListForm.vue";
import BasicDialerCampaignStatusModal from "./components/modal/BasicDialerCampaignStatusModal.vue";
import BasicDialerOverview from "./components/dashboards/BasicDialerOverview.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import Badge from "@generalComponents/Badge.vue";
import { ChevronDownIcon, ChevronUpIcon, EyeIcon, MagnifyingGlassIcon, PauseCircleIcon, PencilSquareIcon, PlayIcon, StopIcon, TrashIcon } from "@heroicons/vue/24/solid";
import { ChartBarSquareIcon, MegaphoneIcon, UserGroupIcon } from "@heroicons/vue/24/outline";

const props = defineProps({
    routes: Object,
    permissions: Object,
});

const routes = props.routes;
const permissions = props.permissions;

const activeTab = ref("overview");
const activeForm = ref("campaigns");
const loading = ref(false);
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
const showForm = ref(false);
const formMode = ref("create");
const loadingForm = ref(false);
const itemOptions = ref({ item: {}, routes: {} });
const showStatusModal = ref(false);
const statusCampaignUuid = ref(null);

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
});

const sortData = ref({
    name: "name",
    order: "asc",
});

const tabs = computed(() => [
    { id: "overview", label: "Overview", icon: ChartBarSquareIcon, count: null },
    {
        id: "campaigns",
        label: "Campaigns",
        icon: MegaphoneIcon,
        count: activeTab.value === "campaigns" ? data.value.total : null,
    },
    {
        id: "contact_lists",
        label: "Contact Lists",
        icon: UserGroupIcon,
        count: activeTab.value === "contact_lists" ? data.value.total : null,
    },
]);

const activeRoutes = computed(() => activeTab.value === "campaigns"
    ? {
        data: routes.campaign_data,
        itemOptions: routes.campaign_item_options,
        selectAll: routes.campaign_select_all,
        bulkDelete: routes.campaign_bulk_delete,
    }
    : {
        data: routes.contact_list_data,
        itemOptions: routes.contact_list_item_options,
        selectAll: routes.contact_list_select_all,
        bulkDelete: routes.contact_list_bulk_delete,
    });

const bulkActions = computed(() => {
    const actions = [];

    if (permissions.destroy) {
        actions.push({ id: "bulk_delete", label: "Delete", icon: "TrashIcon" });
    }

    return actions;
});

const formHeader = computed(() => {
    if (formMode.value === "create") {
        return activeForm.value === "campaigns" ? "Create Campaign" : "Create Contact List";
    }

    const name = itemOptions.value?.item?.name || "Loading...";
    return activeForm.value === "campaigns" ? `Update Campaign - ${name}` : `Update Contact List - ${name}`;
});

const statusRoute = computed(() => statusCampaignUuid.value
    ? routes.campaign_status?.replace(":campaign", statusCampaignUuid.value)
    : null);

onMounted(() => {
    if (activeTab.value !== "overview") {
        getData();
    }
});

const switchTab = (tab) => {
    if (activeTab.value === tab) return;

    handleStatusModalClose();
    activeTab.value = tab;
    sortData.value = { name: "name", order: "asc" };
    filterData.value.search = null;
    handleClearSelection();
    if (tab !== "overview") {
        getData(1);
    }
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

const handleSearchButtonClick = () => {
    getData(1);
};

const refreshCurrentPage = () => {
    getData(currentPage.value);
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    getData(1);
};

const renderRequestedPage = (url) => {
    if (!url) return;

    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get("page") ?? 1;
    getData(pageParam);
};

const handleCreateButtonClick = () => {
    activeForm.value = activeTab.value;
    showForm.value = true;
    formMode.value = "create";
    getItemOptions();
};

const handleEditButtonClick = (uuid) => {
    activeForm.value = activeTab.value;
    showForm.value = true;
    formMode.value = "update";
    getItemOptions(uuid);
};

const handleStatusButtonClick = (uuid) => {
    statusCampaignUuid.value = uuid;
    showStatusModal.value = true;
};

const handleStatusModalClose = () => {
    showStatusModal.value = false;
    statusCampaignUuid.value = null;
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
    showForm.value = false;
    formMode.value = "create";
    itemOptions.value = { item: {}, routes: {} };
};

const itemKey = computed(() => activeTab.value === "campaigns"
    ? "basic_dialer_campaign_uuid"
    : "basic_dialer_contact_list_uuid");

const handleSelectPageItems = () => {
    selectedItems.value = selectPageItems.value
        ? data.value.data.map((item) => item[itemKey.value])
        : [];
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
    const label = activeTab.value === "campaigns" ? "campaign" : "contact list";

    showConfirmation({
        header: "Confirm Deletion",
        text: `This action will permanently delete the selected ${label}.`,
        button: "Delete",
        action: () => executeBulkDelete([uuid]),
    });
};

const handleBulkActionRequest = (action) => {
    if (action === "bulk_delete") {
        const label = activeTab.value === "campaigns" ? "campaign(s)" : "contact list(s)";

        showConfirmation({
            header: "Confirm Deletion",
            text: `This action will permanently delete the selected ${label}.`,
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

const executeCampaignAction = (action, campaignUuid) => {
    const route = routes[`campaign_${action}`]?.replace(":campaign", campaignUuid);

    if (!route) return;

    axios.post(route)
        .then((response) => {
            showNotification("success", response.data.messages);
            refreshCurrentPage();
        })
        .catch(handleErrorResponse);
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

const enabledBadgeProps = (enabled) => enabled
    ? {
        backgroundColor: "bg-success-subtle",
        textColor: "text-success",
        ringColor: "ring-success/20",
    }
    : {
        backgroundColor: "bg-surface-2",
        textColor: "text-body",
        ringColor: "ring-strong/20",
    };

const statusBadgeProps = (status) => {
    if (status === "running") {
        return {
            backgroundColor: "bg-info-subtle",
            textColor: "text-info",
            ringColor: "ring-info/20",
        };
    }

    if (status === "completed") {
        return enabledBadgeProps(true);
    }

    if (status === "paused") {
        return {
            backgroundColor: "bg-warning-subtle",
            textColor: "text-warning",
            ringColor: "ring-warning/20",
        };
    }

    if (status === "stopped") {
        return {
            backgroundColor: "bg-danger-subtle",
            textColor: "text-danger",
            ringColor: "ring-danger/20",
        };
    }

    return {
        backgroundColor: "bg-surface-2",
        textColor: "text-body",
        ringColor: "ring-strong/20",
    };
};
</script>
