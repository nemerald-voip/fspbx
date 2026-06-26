<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="fetchData(1)" @reset-filters="resetFilters">
            <template #title>PIN Numbers</template>

            <template #subtitle>
                Manage PIN numbers and account codes.
            </template>

            <template #filters>
                <div class="relative mb-2 min-w-64 focus-within:z-10 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input
                        v-model="filterData.search"
                        type="text"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                        placeholder="Search"
                        @keydown.enter="fetchData(1)"
                    />
                </div>

                <div class="relative mb-2 min-w-40 sm:mr-4">
                    <select
                        v-model="filterData.enabled"
                        class="block w-full rounded-md border-0 py-1.5 pl-3 pr-8 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                        @change="fetchData(1)"
                    >
                        <option value="">All states</option>
                        <option value="true">Enabled</option>
                        <option value="false">Disabled</option>
                    </select>
                </div>
            </template>

            <template #action>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <a
                        v-if="permissions.export"
                        :href="routes.export"
                        class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4" />
                        Export
                    </a>

                    <button
                        v-if="permissions.create"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                        @click="openCreateModal"
                    >
                        <PlusIcon class="h-4 w-4" />
                        Add
                    </button>
                </div>
            </template>

            <template #navigation>
                <Paginator
                    :previous="data.prev_page_url"
                    :next="data.next_page_url"
                    :from="data.from"
                    :to="data.to"
                    :total="data.total"
                    :currentPage="data.current_page"
                    :lastPage="data.last_page"
                    :links="data.links"
                    :bulk-actions="bulkActions"
                    :has-selected-items="selectedItems.length > 0"
                    @pagination-change-page="fetchData"
                    @bulk-action="handleBulkAction"
                />
            </template>

            <template #table-header>
                <TableColumnHeader class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center">
                        <input
                            v-if="hasSelectableActions"
                            v-model="selectPageItems"
                            type="checkbox"
                            :disabled="pageItems.length === 0"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <button class="flex items-center" :class="{ 'ml-4': hasSelectableActions }" @click="setSort('pin_number')">
                            <span class="mr-2">PIN Number</span>
                            <ChevronUpIcon v-if="sortData.name === 'pin_number' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'pin_number' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                        </button>
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <button class="flex items-center" @click="setSort('accountcode')">
                        <span class="mr-2">Account Code</span>
                        <ChevronUpIcon v-if="sortData.name === 'accountcode' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'accountcode' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </button>
                </TableColumnHeader>
                <TableColumnHeader header="State" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <button class="flex items-center" @click="setSort('description')">
                        <span class="mr-2">Description</span>
                        <ChevronUpIcon v-if="sortData.name === 'description' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'description' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </button>
                </TableColumnHeader>
                <TableColumnHeader v-if="hasRowActions" header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" #current-selection>
                <td :colspan="columnCount">
                    <div class="m-2 text-center text-sm">
                        <span class="font-semibold">{{ selectedItems.length }}</span> PIN numbers are selected.
                        <button
                            v-if="!selectAll && selectedItems.length !== data.total"
                            class="rounded px-2 py-2 text-blue-500 transition duration-500 ease-in-out hover:bg-blue-200 hover:text-blue-500 focus:bg-blue-200 focus:outline-none focus:ring-1 focus:ring-blue-300"
                            @click="selectAllMatching"
                        >
                            Select all {{ data.total }} PIN numbers
                        </button>
                        <button
                            v-if="selectAll"
                            class="rounded px-2 py-2 text-blue-500 transition duration-500 ease-in-out hover:bg-blue-200 hover:text-blue-500 focus:bg-blue-200 focus:outline-none focus:ring-1 focus:ring-blue-300"
                            @click="clearSelection"
                        >
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.pin_number_uuid">
                    <TableField class="px-4 py-2 text-sm text-gray-500">
                        <div class="flex items-center">
                            <input
                                v-if="hasSelectableActions"
                                v-model="selectedItems"
                                type="checkbox"
                                :value="row.pin_number_uuid"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600"
                            />
                            <button
                                type="button"
                                class="min-w-0 text-left"
                                :class="{ 'ml-4': hasSelectableActions, 'cursor-pointer': permissions.update }"
                                @click="permissions.update && openEditModal(row.pin_number_uuid)"
                            >
                                <span class="block font-medium text-gray-900" :class="{ 'hover:text-indigo-600': permissions.update }">
                                    {{ row.pin_number }}
                                </span>
                            </button>
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        {{ row.accountcode || "No account code" }}
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <button v-if="permissions.update" type="button" @click="confirmAction('toggle', [row.pin_number_uuid])">
                            <Badge :text="row.enabled === 'true' ? 'Enabled' : 'Disabled'" v-bind="enabledBadge(row.enabled)" />
                        </button>
                        <Badge v-else :text="row.enabled === 'true' ? 'Enabled' : 'Disabled'" v-bind="enabledBadge(row.enabled)" />
                    </TableField>

                    <TableField class="max-w-xl px-2 py-2 text-sm text-gray-500">
                        <span class="line-clamp-2">{{ row.description || "No description" }}</span>
                    </TableField>

                    <TableField v-if="hasRowActions" class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    v-if="permissions.update"
                                    type="button"
                                    class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                                    title="Edit"
                                    @click="openEditModal(row.pin_number_uuid)"
                                >
                                    <PencilSquareIcon class="h-5 w-5" />
                                </button>
                                <button
                                    v-if="permissions.copy"
                                    type="button"
                                    class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                                    title="Copy"
                                    @click="confirmAction('copy', [row.pin_number_uuid])"
                                >
                                    <DocumentDuplicateIcon class="h-5 w-5" />
                                </button>
                                <button
                                    v-if="permissions.destroy"
                                    type="button"
                                    class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-red-600"
                                    title="Delete"
                                    @click="confirmAction('delete', [row.pin_number_uuid])"
                                >
                                    <TrashIcon class="h-5 w-5" />
                                </button>
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="!loading && data.data.length === 0" class="px-6 py-8 text-center text-sm text-gray-500">
                    No PIN numbers found.
                </div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>

            <template #footer>
                <Paginator
                    :previous="data.prev_page_url"
                    :next="data.next_page_url"
                    :from="data.from"
                    :to="data.to"
                    :total="data.total"
                    :currentPage="data.current_page"
                    :lastPage="data.last_page"
                    :links="data.links"
                    @pagination-change-page="fetchData"
                />
            </template>
        </DataTable>
    </div>

    <PinNumberForm
        :show="showForm"
        :header="formHeader"
        :mode="formMode"
        :loading="loadingForm"
        :options="itemOptions"
        @close="closeForm"
        @error="handleError"
        @success="showNotification"
        @refresh-data="refreshData"
    />

    <ConfirmationModal
        :show="confirmation.show"
        :header="confirmation.header"
        :text="confirmation.text"
        :confirm-button-label="confirmation.button"
        cancel-button-label="Cancel"
        :loading="confirmation.loading"
        :color="confirmation.color"
        @close="closeConfirmation"
        @confirm="executeConfirmedAction"
    />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages" @update:show="notificationShow = $event" />
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import axios from "axios";
import MainLayout from "../Layouts/MainLayout.vue";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import Notification from "./components/notifications/Notification.vue";
import PinNumberForm from "./components/forms/PinNumberForm.vue";
import Badge from "@generalComponents/Badge.vue";
import {
    ArrowDownTrayIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    DocumentDuplicateIcon,
    MagnifyingGlassIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
} from "@heroicons/vue/24/solid";

const props = defineProps({
    routes: Object,
    permissions: Object,
});

const routes = props.routes;
const permissions = props.permissions;

const loading = ref(false);
const loadingForm = ref(false);
const currentPage = ref(1);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const selectAll = ref(false);
const syncingPageSelection = ref(false);
const showForm = ref(false);
const formMode = ref("create");
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);
const itemOptions = ref({
    item: {},
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
    enabled: "",
});

const sortData = ref({
    name: "pin_number",
    order: "asc",
});

const confirmation = ref({
    show: false,
    loading: false,
    action: null,
    items: [],
    header: "Confirm Action",
    text: "",
    button: "Continue",
    color: "indigo",
});

const pageItems = computed(() => data.value.data.map((item) => item.pin_number_uuid));
const hasSelectableActions = computed(() => permissions.copy || permissions.update || permissions.destroy);
const hasRowActions = computed(() => permissions.copy || permissions.update || permissions.destroy);
const columnCount = computed(() => hasRowActions.value ? 5 : 4);

const bulkActions = computed(() => {
    const actions = [];

    if (permissions.copy) {
        actions.push({ id: "copy", label: "Copy", icon: "DocumentDuplicateIcon" });
    }

    if (permissions.update) {
        actions.push({ id: "toggle", label: "Toggle Enabled", icon: "PencilSquareIcon" });
    }

    if (permissions.destroy) {
        actions.push({ id: "delete", label: "Delete", icon: "TrashIcon" });
    }

    return actions;
});

const formHeader = computed(() => {
    if (formMode.value === "create") {
        return "Create PIN Number";
    }

    return `Update PIN Number - ${itemOptions.value?.item?.pin_number || "Loading..."}`;
});

onMounted(() => {
    fetchData();
});

watch(selectPageItems, (checked) => {
    if (syncingPageSelection.value) {
        return;
    }

    if (checked) {
        selectedItems.value = pageItems.value;
        return;
    }

    if (!selectAll.value) {
        selectedItems.value = [];
    }
});

watch(selectedItems, () => {
    if (selectedItems.value.length === 0) {
        selectPageItems.value = false;
        selectAll.value = false;
    }
});

const setSort = (column) => {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === "asc" ? "desc" : "asc";
    } else {
        sortData.value.name = column;
        sortData.value.order = "asc";
    }

    fetchData(currentPage.value);
};

const resolvePage = (page = 1) => {
    if (typeof page === "number") {
        return page;
    }

    if (!page) {
        return 1;
    }

    try {
        return Number(new URL(page, window.location.origin).searchParams.get("page") || 1);
    } catch {
        return 1;
    }
};

const fetchData = (page = 1) => {
    loading.value = true;
    currentPage.value = resolvePage(page);

    let sort = sortData.value.name;
    if (sortData.value.order === "desc") {
        sort = `-${sort}`;
    }

    axios.get(routes.data_route, {
        params: {
            filter: activeFilters(),
            page: currentPage.value,
            sort,
        },
    })
        .then((response) => {
            data.value = response.data;
            currentPage.value = response.data.current_page ?? currentPage.value;
            syncPageSelection();
        })
        .catch(handleError)
        .finally(() => {
            loading.value = false;
        });
};

const activeFilters = () => {
    const filters = {};

    if (filterData.value.search) {
        filters.search = filterData.value.search;
    }

    if (filterData.value.enabled) {
        filters.enabled = filterData.value.enabled;
    }

    return filters;
};

const resetFilters = () => {
    filterData.value.search = null;
    filterData.value.enabled = "";
    fetchData(1);
};

const refreshData = () => {
    fetchData(currentPage.value);
};

const openCreateModal = () => {
    formMode.value = "create";
    showForm.value = true;
    getItemOptions();
};

const openEditModal = (uuid) => {
    formMode.value = "update";
    showForm.value = true;
    getItemOptions(uuid);
};

const closeForm = () => {
    showForm.value = false;
    formMode.value = "create";
    itemOptions.value = {
        item: {},
        routes: {},
    };
};

const getItemOptions = (itemUuid = null) => {
    loadingForm.value = true;

    axios.post(routes.item_options, itemUuid ? { itemUuid } : {})
        .then((response) => {
            itemOptions.value = response.data;
        })
        .catch((error) => {
            closeForm();
            handleError(error);
        })
        .finally(() => {
            loadingForm.value = false;
        });
};

const selectAllMatching = () => {
    axios.post(routes.select_all, { filter: activeFilters() })
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification("success", response.data.messages);
        })
        .catch((error) => {
            clearSelection();
            handleError(error);
        });
};

const clearSelection = () => {
    selectedItems.value = [];
    selectPageItems.value = false;
    selectAll.value = false;
};

const syncPageSelection = () => {
    syncingPageSelection.value = true;
    selectPageItems.value = pageItems.value.length > 0 && pageItems.value.every((uuid) => selectedItems.value.includes(uuid));
    syncingPageSelection.value = false;
};

const handleBulkAction = (action) => {
    confirmAction(action, selectedItems.value);
};

const confirmAction = (action, items) => {
    const copy = {
        header: "Confirm Copy",
        text: "Copy the selected PIN number(s)?",
        button: "Copy",
        color: "indigo",
    };

    const toggle = {
        header: "Confirm Toggle",
        text: "Toggle enabled for the selected PIN number(s)?",
        button: "Toggle",
        color: "indigo",
    };

    const del = {
        header: "Confirm Deletion",
        text: "This action will permanently delete the selected PIN number(s).",
        button: "Delete",
        color: "red",
    };

    const config = { copy, toggle, delete: del }[action];
    if (!config) {
        return;
    }

    confirmation.value = {
        ...confirmation.value,
        ...config,
        show: true,
        loading: false,
        action,
        items,
    };
};

const closeConfirmation = () => {
    confirmation.value.show = false;
    confirmation.value.loading = false;
    confirmation.value.action = null;
    confirmation.value.items = [];
};

const executeConfirmedAction = () => {
    const endpoint = {
        copy: routes.bulk_copy,
        toggle: routes.bulk_toggle,
        delete: routes.bulk_delete,
    }[confirmation.value.action];

    if (!endpoint) {
        closeConfirmation();
        return;
    }

    confirmation.value.loading = true;

    axios.post(endpoint, { items: confirmation.value.items })
        .then((response) => {
            closeConfirmation();
            clearSelection();
            showNotification("success", response.data.messages);
            refreshData();
        })
        .catch((error) => {
            closeConfirmation();
            clearSelection();
            handleError(error);
        });
};

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
};

const handleError = (error) => {
    if (error.response) {
        showNotification("error", error.response.data.errors || error.response.data.messages || { request: [error.message] });
    } else if (error.request) {
        showNotification("error", { request: [error.request] });
    } else {
        showNotification("error", { request: [error.message] });
    }
};

const enabledBadge = (enabled) => enabled === "true"
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
</script>
