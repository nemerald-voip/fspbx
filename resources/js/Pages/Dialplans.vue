<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>{{ pageTitle }}</template>

            <template #subtitle>
                {{ pageSubtitle }}
            </template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-dialplans"
                        id="mobile-search-dialplans"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                    <input type="text" v-model="filterData.search" name="desktop-search-dialplans"
                        id="desktop-search-dialplans"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <button v-if="createButtonVisible" type="button" @click.prevent="handleCreateButtonClick"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Create
                </button>

                <button v-if="!isCategoryView && !filterData.showGlobal && permissions.view_global" type="button"
                    @click.prevent="handleShowGlobal"
                    class="ml-2 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:ml-4">
                    Show global
                </button>

                <button v-if="!isCategoryView && filterData.showGlobal && permissions.view_global" type="button"
                    @click.prevent="handleShowLocal"
                    class="ml-2 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:ml-4">
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
                <TableColumnHeader
                    class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <div class="pl-4 flex items-center cursor-pointer select-none" @click="handleSortRequest('dialplan_name')">
                        <span class="mr-2">Name</span>
                        <ChevronUpIcon v-if="sortData.name === 'dialplan_name' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'dialplan_name' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>

                <TableColumnHeader v-if="filterData.showGlobal" header="Domain"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Number" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader v-if="permissions.context" header="Context"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Order" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Enabled" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Description"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
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
                <tr v-for="row in data.data" :key="row.dialplan_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500" :text="row.dialplan_name">
                        <div class="flex items-center">
                            <input v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.dialplan_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-4" :class="{ 'cursor-pointer hover:text-gray-900': permissions.update }"
                                @click="permissions.update && handleEditButtonClick(row.dialplan_uuid)">
                                {{ row.dialplan_name }}
                            </div>
                        </div>
                    </TableField>

                    <TableField v-if="filterData.showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="domainLabel(row)" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.dialplan_number" />
                    <TableField v-if="permissions.context" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.dialplan_context" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="String(row.dialplan_order ?? '')" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <button v-if="permissions.update" type="button" class="cursor-pointer"
                            @click="executeToggle([row.dialplan_uuid])">
                            <Badge :text="row.enabled_label" v-bind="enabledBadgeProps(row.dialplan_enabled_raw)" />
                        </button>
                        <Badge v-else :text="row.enabled_label" v-bind="enabledBadgeProps(row.dialplan_enabled_raw)" />
                    </TableField>
                    <TableField class="px-2 py-2 text-sm text-gray-500" :text="row.dialplan_description" />

                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap justify-end">
                                <button v-if="permissions.update" type="button" title="Edit" aria-label="Edit dialplan"
                                    class="rounded-full" @click="handleEditButtonClick(row.dialplan_uuid)">
                                    <PencilSquareIcon
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                </button>
                                <button v-if="permissions.destroy" type="button" title="Delete" aria-label="Delete dialplan"
                                    class="rounded-full" @click="handleSingleItemDeleteRequest(row.dialplan_uuid)">
                                    <TrashIcon
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                </button>
                            </div>
                        </template>
                    </TableField>
                </tr>
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

    <ConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmAction" :header="confirmationHeader" :text="confirmationText"
        :confirm-button-label="confirmationButtonLabel" cancel-button-label="Cancel" />

    <DialplanForm :show="showForm" :options="itemOptions" :mode="formMode" :loading="loadingForm"
        :header="formHeader" @close="handleFormClose" @error="handleErrorResponse" @success="showNotification"
        @refresh-data="refreshCurrentPage" @saved="handleFormSaved" />

    <OutboundRouteForm :show="showOutboundRouteForm" :options="outboundRouteOptions"
        :loading="loadingOutboundRouteForm" @close="handleOutboundRouteFormClose" @error="handleErrorResponse"
        @success="showNotification" @refresh-data="refreshCurrentPage" />

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
import DialplanForm from "./components/forms/DialplanForm.vue";
import OutboundRouteForm from "./components/forms/OutboundRouteForm.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import Badge from "@generalComponents/Badge.vue";
import {
    ChevronDownIcon,
    ChevronUpIcon,
    MagnifyingGlassIcon,
    PencilSquareIcon,
    TrashIcon,
} from "@heroicons/vue/24/solid";

const props = defineProps({
    routes: Object,
    permissions: Object,
});

const routes = props.routes;
const permissions = props.permissions;

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
const showOutboundRouteForm = ref(false);
const loadingOutboundRouteForm = ref(false);
const itemOptions = ref({
    item: {},
    domain_options: [],
    context_options: [],
    application_options: [],
    condition_options: [],
    routes: {},
});
const outboundRouteOptions = ref({
    gateway_options: [],
    pattern_options: [],
    domain_options: [],
    context_options: [],
    permissions: {},
    defaults: {},
    routes: {},
});

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
    category: new URLSearchParams(window.location.search).get("category"),
});

const sortData = ref({
    name: "dialplan_order",
    order: "asc",
});

const bulkActions = computed(() => {
    const actions = [];

    if (permissions.update) {
        actions.push({ id: "bulk_toggle_enabled", label: "Toggle Enabled", icon: "PencilSquareIcon" });
    }

    if (permissions.create) {
        actions.push({ id: "bulk_copy", label: "Copy", icon: "PencilSquareIcon" });
    }

    if (permissions.destroy) {
        actions.push({ id: "bulk_delete", label: "Delete", icon: "TrashIcon" });
    }

    return actions;
});

const isCategoryView = computed(() => ["inbound", "outbound"].includes(filterData.value.category));

const createButtonVisible = computed(() => {
    if (filterData.value.category === "inbound") {
        return false;
    }

    if (filterData.value.category === "outbound") {
        return permissions.create_outbound_route;
    }

    return permissions.create;
});

const pageTitle = computed(() => {
    if (filterData.value.category === "inbound") {
        return "Inbound Routes";
    }

    if (filterData.value.category === "outbound") {
        return "Outbound Routes";
    }

    return "Dialplans";
});

const pageSubtitle = computed(() => {
    if (filterData.value.category === "inbound") {
        return "Manage public-context routes that receive calls from carriers and external sources.";
    }

    if (filterData.value.category === "outbound") {
        return "Manage outbound route dialplans used to send calls to gateways.";
    }

    return "Manage custom FreeSWITCH dialplans and advanced routing rules.";
});

const selectionColspan = computed(() => {
    let count = 7;
    if (filterData.value.showGlobal) count += 1;
    if (!permissions.context) count -= 1;
    return count;
});

const formHeader = computed(() => {
    if (formMode.value === "create") {
        return "Create Dialplan";
    }

    return `Update Dialplan - ${itemOptions.value?.item?.dialplan_name || "Loading..."}`;
});

const domainLabel = (row) => row.domain?.domain_description || row.domain?.domain_name || "Global";

onMounted(() => {
    getData();
});

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

    axios.get(routes.data_route, {
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

const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    getData(1);
};

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    getData(1);
};


const handleCreateButtonClick = () => {
    if (filterData.value.category === "outbound") {
        showOutboundRouteForm.value = true;
        getOutboundRouteOptions();
        return;
    }

    showForm.value = true;
    formMode.value = "create";
    getItemOptions();
};

const handleEditButtonClick = (uuid) => {
    showForm.value = true;
    formMode.value = "update";
    getItemOptions(uuid);
};

const getItemOptions = (itemUuid = null) => {
    loadingForm.value = true;

    axios.post(routes.item_options, itemUuid ? { itemUuid } : {})
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

const getOutboundRouteOptions = () => {
    loadingOutboundRouteForm.value = true;

    axios.post(routes.outbound_route_options)
        .then((response) => {
            outboundRouteOptions.value = response.data;
        })
        .catch((error) => {
            handleOutboundRouteFormClose();
            handleErrorResponse(error);
        })
        .finally(() => {
            loadingOutboundRouteForm.value = false;
        });
};

const handleFormSaved = (payload = {}) => {
    const uuid = payload.dialplan_uuid || itemOptions.value?.item?.dialplan_uuid;

    if (!uuid) {
        return;
    }

    formMode.value = "update";
    getItemOptions(uuid);
};

const handleFormClose = () => {
    showForm.value = false;
    formMode.value = "create";
    itemOptions.value = {
        item: {},
        domain_options: [],
        context_options: [],
        application_options: [],
        condition_options: [],
        routes: {},
    };
};

const handleOutboundRouteFormClose = () => {
    showOutboundRouteForm.value = false;
    outboundRouteOptions.value = {
        gateway_options: [],
        pattern_options: [],
        domain_options: [],
        context_options: [],
        permissions: {},
        defaults: {},
        routes: {},
    };
};

const handleSelectPageItems = () => {
    if (selectPageItems.value) {
        selectedItems.value = data.value.data.map((item) => item.dialplan_uuid);
    } else {
        selectedItems.value = [];
    }
};

const handleSelectAll = () => {
    axios.post(routes.select_all, { filter: filterData.value })
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
        text: "This action will permanently delete the selected dialplan.",
        button: "Delete",
        action: () => executeBulkDelete([uuid]),
    });
};

const handleBulkActionRequest = (action) => {
    if (action === "bulk_delete") {
        showConfirmation({
            header: "Confirm Deletion",
            text: "This action will permanently delete the selected dialplan(s).",
            button: "Delete",
            action: () => executeBulkDelete(),
        });
    }

    if (action === "bulk_copy") {
        showConfirmation({
            header: "Confirm Copy",
            text: "Copy the selected dialplan(s)?",
            button: "Copy",
            action: () => executeBulkCopy(),
        });
    }

    if (action === "bulk_toggle_enabled") {
        showConfirmation({
            header: "Confirm Toggle",
            text: "Toggle enabled for the selected dialplan(s)?",
            button: "Toggle",
            action: () => executeToggle(selectedItems.value),
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
    axios.post(routes.bulk_delete, { items })
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

const executeBulkCopy = (items = selectedItems.value) => {
    axios.post(routes.bulk_copy, { items })
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

const executeToggle = (items) => {
    axios.post(routes.bulk_toggle, { items })
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
    notificationType.value = "error";
    notificationMessages.value = error?.response?.data?.messages
        || error?.response?.data?.errors
        || { error: ["Something went wrong."] };
    notificationShow.value = true;
};

const enabledBadgeProps = (enabled) => {
    if (enabled === "true" || enabled === true) {
        return {
            backgroundColor: "bg-green-50",
            textColor: "text-green-700",
            ringColor: "ring-green-600/20",
        };
    }

    return {
        backgroundColor: "bg-gray-50",
        textColor: "text-gray-600",
        ringColor: "ring-gray-500/20",
    };
};
</script>
