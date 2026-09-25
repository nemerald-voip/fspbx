<template>
    <MainLayout />

    <div class="m-3">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="mb-6 mt-2 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">{{ $t("Outbound Dialer") }}</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 sm:text-3xl">{{ $t("Basic Dialer") }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ $t("Lightweight outbound campaigns and reusable contact lists.") }}</p>
                </div>
            </div>
        </div>

        <div class="mb-6 border-b border-gray-200 px-4 sm:px-6 lg:px-8">
            <nav class="-mb-px flex gap-0.5 overflow-x-auto sm:gap-2" :aria-label="$t(&quot;Tabs&quot;)">
                <button v-for="tab in tabs" :key="tab.id" type="button"
                    :class="[
                        'group relative -mb-px inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-t-lg px-3 py-2.5 text-sm font-semibold tracking-tight transition-colors sm:gap-2.5 sm:px-6 sm:py-3.5 sm:text-base',
                        activeTab === tab.id
                            ? 'text-indigo-700'
                            : 'text-gray-500 hover:bg-gray-100 hover:text-gray-900'
                    ]"
                    @click="switchTab(tab.id)">
                    <component :is="tab.icon" class="h-4 w-4 sm:h-5 sm:w-5"
                        :class="activeTab === tab.id ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-600'" />
                    <span>{{ tab.label }}</span>
                    <span v-if="tab.count !== undefined && tab.count !== null"
                        :class="[
                            'ml-0.5 rounded-full px-2 py-0.5 text-xs font-semibold sm:ml-1 sm:px-2.5',
                            activeTab === tab.id
                                ? 'bg-indigo-100 text-indigo-700'
                                : 'bg-gray-100 text-gray-600 group-hover:bg-gray-200'
                        ]">
                        {{ tab.count }}
                    </span>
                    <span v-if="activeTab === tab.id"
                        class="absolute inset-x-2 -bottom-px h-[3px] rounded-full bg-indigo-600 sm:inset-x-3"></span>
                </button>
            </nav>
        </div>

        <div v-if="activeTab === 'overview'" class="px-4 sm:px-6 lg:px-8">
            <BasicDialerOverview :route="routes.overview"
                @open-campaign="handleStatusButtonClick" @error="handleErrorResponse" />
        </div>

        <DataTable v-else @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>{{ activeTab === "campaigns" ? $t("Campaigns") : $t("Contact Lists") }}</template>

            <template #subtitle>
                {{ activeTab === "campaigns" ? $t("Manage lightweight outbound dialer campaigns.") : $t("Manage reusable dialer contact lists.") }}
            </template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-basic-dialer"
                        id="mobile-search-basic-dialer"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        :placeholder="$t(&quot;Search&quot;)" @keydown.enter="handleSearchButtonClick" />
                    <input type="text" v-model="filterData.search" name="desktop-search-basic-dialer"
                        id="desktop-search-basic-dialer"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        :placeholder="$t(&quot;Search&quot;)" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <button v-if="permissions.create" type="button" @click.prevent="handleCreateButtonClick"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    {{ $t("Create") }}
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
                        class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                        <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                        <div class="pl-4 flex items-center cursor-pointer select-none" @click="handleSortRequest('name')">
                            <span class="mr-2">{{ $t("Name") }}</span>
                            <ChevronUpIcon v-if="sortData.name === 'name' && sortData.order === 'asc'"
                                class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'name' && sortData.order === 'desc'"
                                class="h-4 w-4 text-gray-500" />
                        </div>
                    </TableColumnHeader>
                    <TableColumnHeader :header="$t(&quot;Status&quot;)" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader :header="$t(&quot;List&quot;)" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader :header="$t(&quot;Recipients&quot;)" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader :header="$t(&quot;Destination&quot;)" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader :header="$t(&quot;Pacing&quot;)" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
                </template>

                <template v-else>
                    <TableColumnHeader
                        class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                        <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                        <div class="pl-4 flex items-center cursor-pointer select-none" @click="handleSortRequest('name')">
                            <span class="mr-2">{{ $t("Name") }}</span>
                            <ChevronUpIcon v-if="sortData.name === 'name' && sortData.order === 'asc'"
                                class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'name' && sortData.order === 'desc'"
                                class="h-4 w-4 text-gray-500" />
                        </div>
                    </TableColumnHeader>
                    <TableColumnHeader :header="$t(&quot;Enabled&quot;)" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader :header="$t(&quot;Contacts&quot;)" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader :header="$t(&quot;Campaigns&quot;)" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader :header="$t(&quot;Description&quot;)" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
                </template>
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td :colspan="activeTab === 'campaigns' ? 7 : 6">
                    <div class="text-sm text-center m-2">
                        {{ $t(":count selected", { count: selectedItems.length }) }}
                        <button v-if="!selectAll && selectedItems.length !== data.total"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleSelectAll">
                            {{ $t("Select all :count", { count: data.total }) }}
                        </button>
                        <button v-if="selectAll"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleClearSelection">
                            {{ $t("Clear selection") }}
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <template v-if="activeTab === 'campaigns'">
                    <tr v-for="row in data.data" :key="row.basic_dialer_campaign_uuid">
                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500" :text="row.name">
                            <div class="flex items-center">
                                <input v-model="selectedItems" type="checkbox" name="action_box[]"
                                    :value="row.basic_dialer_campaign_uuid"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                                <div class="ml-4" :class="{ 'cursor-pointer hover:text-gray-900': permissions.update }"
                                    @click="permissions.update && handleEditButtonClick(row.basic_dialer_campaign_uuid)">
                                    {{ row.name }}
                                </div>
                            </div>
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            <Badge :text="dialerLabel(row.status)" v-bind="statusBadgeProps(row.status)" />
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.contact_list?.name || '-'" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            {{ row.recipients_count ?? 0 }}
                            <span class="text-gray-400">/ {{ $t(":count pending", { count: row.pending_recipients_count ?? 0 }) }}</span>
                        </TableField>
                        <TableField class="px-2 py-2 text-sm text-gray-500"
                            :text="row.destination_label || row.destination_type || '-'" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="$t(':count at a time, :seconds s gap', { count: row.max_concurrent_calls || 1, seconds: row.seconds_between_calls || 0 })" />
                        <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                            <template #action-buttons>
                                <div class="flex items-center whitespace-nowrap justify-end">
                                    <EyeIcon v-if="activeTab === 'campaigns'"
                                        @click="handleStatusButtonClick(row.basic_dialer_campaign_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                        :title="$t(&quot;Status&quot;)" />
                                    <PlayIcon v-if="permissions.start && ['draft', 'paused', 'stopped'].includes(row.status)"
                                        @click="executeCampaignAction('start', row.basic_dialer_campaign_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                        :title="$t(&quot;Start&quot;)" />
                                    <PauseCircleIcon v-if="permissions.start && row.status === 'running'"
                                        @click="executeCampaignAction('pause', row.basic_dialer_campaign_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                        :title="$t(&quot;Pause&quot;)" />
                                    <StopIcon v-if="permissions.start && ['running', 'paused'].includes(row.status)"
                                        @click="executeCampaignAction('stop', row.basic_dialer_campaign_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                        :title="$t(&quot;Stop&quot;)" />
                                    <PencilSquareIcon v-if="permissions.update"
                                        @click="handleEditButtonClick(row.basic_dialer_campaign_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                        :title="$t(&quot;Edit&quot;)" />
                                    <TrashIcon v-if="permissions.destroy"
                                        @click="handleSingleItemDeleteRequest(row.basic_dialer_campaign_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                        :title="$t(&quot;Delete&quot;)" />
                                </div>
                            </template>
                        </TableField>
                    </tr>
                </template>

                <template v-else>
                    <tr v-for="row in data.data" :key="row.basic_dialer_contact_list_uuid">
                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500" :text="row.name">
                            <div class="flex items-center">
                                <input v-model="selectedItems" type="checkbox" name="action_box[]"
                                    :value="row.basic_dialer_contact_list_uuid"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                                <div class="ml-4" :class="{ 'cursor-pointer hover:text-gray-900': permissions.update }"
                                    @click="permissions.update && handleEditButtonClick(row.basic_dialer_contact_list_uuid)">
                                    {{ row.name }}
                                </div>
                            </div>
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            <Badge :text="row.enabled ? $t(&quot;True&quot;) : $t(&quot;False&quot;)" v-bind="enabledBadgeProps(row.enabled)" />
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.contacts_count ?? 0" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.campaigns_count ?? 0" />
                        <TableField class="px-2 py-2 text-sm text-gray-500" :text="row.description" />
                        <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                            <template #action-buttons>
                                <div class="flex items-center whitespace-nowrap justify-end">
                                    <PencilSquareIcon v-if="permissions.update"
                                        @click="handleEditButtonClick(row.basic_dialer_contact_list_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                        :title="$t(&quot;Edit&quot;)" />
                                    <TrashIcon v-if="permissions.destroy"
                                        @click="handleSingleItemDeleteRequest(row.basic_dialer_contact_list_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                        :title="$t(&quot;Delete&quot;)" />
                                </div>
                            </template>
                        </TableField>
                    </tr>
                </template>
            </template>

            <template #empty>
                <div v-if="data.data.length === 0" class="text-center my-5">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ $t("No results found") }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $t("Adjust your search and try again.") }}</p>
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
        :confirm-button-label="confirmationButtonLabel" :cancel-button-label="$t(&quot;Cancel&quot;)" />

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
import { dialerLabel } from "./data/basicDialerLabels";
import { trans } from "@i18n";
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
const confirmationHeader = ref(trans("Are you sure?"));
const confirmationText = ref("");
const confirmationButtonLabel = ref(trans("Continue"));
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
    { id: "overview", label: trans("Overview"), icon: ChartBarSquareIcon, count: null },
    {
        id: "campaigns",
        label: trans("Campaigns"),
        icon: MegaphoneIcon,
        count: activeTab.value === "campaigns" ? data.value.total : null,
    },
    {
        id: "contact_lists",
        label: trans("Contact Lists"),
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
        actions.push({ id: "bulk_delete", label: trans("Delete"), icon: "TrashIcon" });
    }

    return actions;
});

const formHeader = computed(() => {
    if (formMode.value === "create") {
        return activeForm.value === "campaigns" ? trans("Create Campaign") : trans("Create Contact List");
    }

    const name = itemOptions.value?.item?.name || trans("Loading...");
    return activeForm.value === "campaigns" ? trans("Update Campaign - :name", { name }) : trans("Update Contact List - :name", { name });
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
    showConfirmation({
        header: trans("Confirm Deletion"),
        text: activeTab.value === "campaigns"
            ? trans("This action will permanently delete the selected campaigns.")
            : trans("This action will permanently delete the selected contact lists."),
        button: trans("Delete"),
        action: () => executeBulkDelete([uuid]),
    });
};

const handleBulkActionRequest = (action) => {
    if (action === "bulk_delete") {
        showConfirmation({
            header: trans("Confirm Deletion"),
            text: activeTab.value === "campaigns"
                ? trans("This action will permanently delete the selected campaigns.")
                : trans("This action will permanently delete the selected contact lists."),
            button: trans("Delete"),
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
        backgroundColor: "bg-green-50",
        textColor: "text-green-700",
        ringColor: "ring-green-600/20",
    }
    : {
        backgroundColor: "bg-gray-50",
        textColor: "text-gray-700",
        ringColor: "ring-gray-600/20",
    };

const statusBadgeProps = (status) => {
    if (status === "running") {
        return {
            backgroundColor: "bg-blue-50",
            textColor: "text-blue-700",
            ringColor: "ring-blue-600/20",
        };
    }

    if (status === "completed") {
        return enabledBadgeProps(true);
    }

    if (status === "paused") {
        return {
            backgroundColor: "bg-yellow-50",
            textColor: "text-yellow-700",
            ringColor: "ring-yellow-600/20",
        };
    }

    if (status === "stopped") {
        return {
            backgroundColor: "bg-red-50",
            textColor: "text-red-700",
            ringColor: "ring-red-600/20",
        };
    }

    return {
        backgroundColor: "bg-gray-50",
        textColor: "text-gray-700",
        ringColor: "ring-gray-600/20",
    };
};
</script>
