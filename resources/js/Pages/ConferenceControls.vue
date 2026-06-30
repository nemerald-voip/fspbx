<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Conference Controls</template>

            <template #subtitle>
                Manage conference call control sets used to assign digits to in-call actions.
            </template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-subtle" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="desktop-search-conference-controls"
                        id="desktop-search-conference-controls"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button v-if="permissions.create" type="button" @click.prevent="openCreateModal"
                        class="inline-flex items-center gap-x-1.5 rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                        <PlusIcon aria-hidden="true" class="h-5 w-5" />
                        Add
                    </button>

                    <a :href="routes.conference_centers"
                        class="rounded-md bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                        Conference Centers
                    </a>
                </div>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>

            <template #table-header>
                <TableColumnHeader
                    class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-heading items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-strong text-accent-fg">
                    <div class="pl-4 flex items-center cursor-pointer select-none" @click="handleSortRequest('control_name')">
                        <span class="mr-2">Name</span>
                        <ChevronUpIcon v-if="sortData.name === 'control_name' && sortData.order === 'asc'"
                            class="h-4 w-4 text-muted" />
                        <ChevronDownIcon v-else-if="sortData.name === 'control_name' && sortData.order === 'desc'"
                            class="h-4 w-4 text-muted" />
                    </div>
                </TableColumnHeader>

                <TableColumnHeader class="w-32 px-2 py-3.5 text-center text-sm font-semibold text-heading [&>div]:justify-center">
                    <div class="flex items-center justify-center cursor-pointer select-none"
                        @click="handleSortRequest('control_enabled')">
                        <span class="mr-2">Enabled</span>
                        <ChevronUpIcon v-if="sortData.name === 'control_enabled' && sortData.order === 'asc'"
                            class="h-4 w-4 text-muted" />
                        <ChevronDownIcon v-else-if="sortData.name === 'control_enabled' && sortData.order === 'desc'"
                            class="h-4 w-4 text-muted" />
                    </div>
                </TableColumnHeader>

                <TableColumnHeader header="Description" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />

                <TableColumnHeader v-if="hasRowActions" header=""
                    class="w-24 px-2 py-3.5 text-right text-sm font-semibold text-heading" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td :colspan="hasRowActions ? 4 : 3">
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
                <tr v-for="row in data.data" :key="row.conference_control_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm font-medium text-heading"
                        :text="row.control_name">
                        <div class="flex items-center">
                            <input v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.conference_control_uuid" class="h-4 w-4 rounded border-strong text-accent-fg">
                            <div class="ml-4" :class="{ 'cursor-pointer hover:text-body': permissions.update }"
                                @click="permissions.update && openEditModal(row)">
                                {{ row.control_name }}
                            </div>
                        </div>
                    </TableField>

                    <TableField class="w-32 whitespace-nowrap px-2 py-2 text-center text-sm text-muted">
                        <button v-if="permissions.update" type="button" @click="executeBulkToggle([row.conference_control_uuid])">
                            <Badge :text="row.control_enabled === 'true' ? 'True' : 'False'"
                                v-bind="enabledBadgeProps(row.control_enabled)" />
                        </button>
                        <Badge v-else :text="row.control_enabled === 'true' ? 'True' : 'False'"
                            v-bind="enabledBadgeProps(row.control_enabled)" />
                    </TableField>

                    <TableField class="px-2 py-2 text-sm text-muted" :text="row.control_description" />

                    <TableField v-if="hasRowActions" class="whitespace-nowrap px-2 py-1 text-sm text-muted">
                        <template #action-buttons>
                            <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                                <button v-if="permissions.update" type="button" @click="openEditModal(row)"
                                    class="rounded-full p-2 text-subtle transition duration-150 hover:bg-surface-3 hover:text-body"
                                    title="Edit">
                                    <PencilSquareIcon class="h-5 w-5" />
                                </button>

                                <button v-if="permissions.destroy" type="button" @click="openDeleteModal(row)"
                                    class="rounded-full p-2 text-subtle transition duration-150 hover:bg-surface-3 hover:text-danger"
                                    title="Delete">
                                    <TrashIcon class="h-5 w-5" />
                                </button>
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="!loading && data.data.length === 0" class="text-center my-5">
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

    <ConfirmationModal :show="showDeleteConfirmationModal" @close="showDeleteConfirmationModal = false"
        @confirm="deleteSelectedControl" :header="'Are you sure?'" :text="deleteConfirmationText"
        :confirm-button-label="'Delete'" :cancel-button-label="'Cancel'" :loading="deleteSubmitting" />

    <ConferenceControlForm :show="showForm" :options="itemOptions" :mode="formMode" :loading="loadingForm"
        :header="formHeader" @close="handleFormClose" @error="handleErrorResponse" @success="showNotification"
        @refresh-data="refreshCurrentPage" @reload-options="reloadItemOptions" />

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
import Loading from "./components/general/Loading.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Notification from "./components/notifications/Notification.vue";
import ConferenceControlForm from "./components/forms/ConferenceControlForm.vue";
import Badge from "@generalComponents/Badge.vue";
import {
    ChevronDownIcon,
    ChevronUpIcon,
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
const permissions = props.permissions ?? {};

const loading = ref(false);
const currentPage = ref(1);
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const showDeleteConfirmationModal = ref(false);
const deleteSubmitting = ref(false);
const deleteControl = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);
const showForm = ref(false);
const formMode = ref("create");
const loadingForm = ref(false);
const editingItemUuid = ref(null);
const itemOptions = ref({
    item: {},
    details: [],
    permissions: {},
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
});

const sortData = ref({
    name: "control_name",
    order: "asc",
});

const hasRowActions = computed(() => permissions.update || permissions.destroy);
const bulkActions = computed(() => {
    const actions = [];

    if (permissions.create) {
        actions.push({ id: "bulk_copy", label: "Copy", icon: "DocumentDuplicateIcon" });
    }

    if (permissions.update) {
        actions.push({ id: "bulk_toggle", label: "Toggle Enabled", icon: "PencilSquareIcon" });
    }

    if (permissions.destroy) {
        actions.push({ id: "bulk_delete", label: "Delete", icon: "TrashIcon" });
    }

    return actions;
});
const deleteConfirmationText = computed(() => {
    if (deleteControl.value === "bulk") {
        return `Delete ${selectedItems.value.length} selected conference control(s)? Any control details assigned to them will also be deleted.`;
    }

    const name = deleteControl.value?.control_name ?? "this conference control";
    return `Delete ${name}? Any control details assigned to it will also be deleted.`;
});
const formHeader = computed(() => {
    if (formMode.value === "create") {
        return "Create Conference Control";
    }

    return `Update Conference Control - ${itemOptions.value?.item?.control_name || "Loading..."}`;
});

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

const handleSelectPageItems = () => {
    if (selectPageItems.value) {
        selectedItems.value = data.value.data.map((item) => item.conference_control_uuid);
    } else {
        selectedItems.value = [];
    }

    selectAll.value = false;
};

const handleSelectAll = () => {
    axios.post(routes.select_all, { filter: filterData.value })
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            selectPageItems.value = true;
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

const handleBulkActionRequest = (action) => {
    if (selectedItems.value.length === 0) return;

    if (action === "bulk_delete") {
        deleteControl.value = "bulk";
        showDeleteConfirmationModal.value = true;
        return;
    }

    if (action === "bulk_toggle") {
        executeBulkToggle(selectedItems.value);
        return;
    }

    if (action === "bulk_copy") {
        executeBulkCopy();
    }
};

const executeBulkToggle = (items = selectedItems.value) => {
    axios.post(routes.bulk_toggle, { items })
        .then((response) => {
            showNotification("success", response.data.messages);
            getData(currentPage.value);
            handleClearSelection();
        })
        .catch(handleErrorResponse);
};

const executeBulkCopy = () => {
    axios.post(routes.bulk_copy, { items: selectedItems.value })
        .then((response) => {
            showNotification("success", response.data.messages);
            getData(1);
            handleClearSelection();
        })
        .catch(handleErrorResponse);
};

const openCreateModal = () => {
    formMode.value = "create";
    editingItemUuid.value = null;
    showForm.value = true;
    getItemOptions();
};

const openEditModal = (row) => {
    formMode.value = "edit";
    editingItemUuid.value = row.conference_control_uuid;
    showForm.value = true;
    getItemOptions(row.conference_control_uuid);
};

const getItemOptions = (itemUuid = editingItemUuid.value) => {
    loadingForm.value = true;

    axios.post(routes.item_options, { itemUuid })
        .then((response) => {
            itemOptions.value = response.data;
        })
        .catch(handleErrorResponse)
        .finally(() => {
            loadingForm.value = false;
        });
};

const reloadItemOptions = () => {
    if (editingItemUuid.value) {
        getItemOptions(editingItemUuid.value);
    }
};

const handleFormClose = () => {
    showForm.value = false;
    editingItemUuid.value = null;
    itemOptions.value = {
        item: {},
        details: [],
        permissions: {},
        routes: {},
    };
};

const refreshCurrentPage = () => {
    getData(currentPage.value);
};

const openDeleteModal = (row) => {
    deleteControl.value = row;
    showDeleteConfirmationModal.value = true;
};

const deleteSelectedControl = () => {
    if (!deleteControl.value) return;

    deleteSubmitting.value = true;

    const request = deleteControl.value === "bulk"
        ? axios.post(routes.bulk_delete, { items: selectedItems.value })
        : axios.delete(deleteControl.value.destroy_route);

    request
        .then((response) => {
            showNotification("success", response.data.messages);
            showDeleteConfirmationModal.value = false;
            deleteControl.value = null;
            getData(currentPage.value);
            handleClearSelection();
        })
        .catch(handleErrorResponse)
        .finally(() => {
            deleteSubmitting.value = false;
        });
};

const handleSearchButtonClick = () => {
    handleClearSelection();
    getData(1);
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    handleClearSelection();
    getData(1);
};

const renderRequestedPage = (url) => {
    if (!url) return;

    const urlObj = new URL(url, window.location.origin);
    handleClearSelection();
    getData(urlObj.searchParams.get("page") ?? 1);
};

function enabledBadgeProps(value) {
    return value === "true"
        ? { backgroundColor: "bg-success-subtle", textColor: "text-success", ringColor: "ring-success/20" }
        : { backgroundColor: "bg-surface-2", textColor: "text-body", ringColor: "ring-strong/20" };
}

function handleErrorResponse(error) {
    if (error.request?.status === 419) {
        showNotification("error", { request: ["Session expired. Reload the page."] });
        return;
    }

    if (error.response) {
        showNotification("error", error.response.data.messages || error.response.data.errors || { request: [error.message] });
        return;
    }

    showNotification("error", { request: [error.message] });
}

function hideNotification() {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
}

function showNotification(type, messages = null) {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
}
</script>
