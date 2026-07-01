<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="fetchData(1)" @reset-filters="resetFilters">
            <template #title>Modules</template>

            <template #subtitle>
                Manage FreeSWITCH modules and their autoload state.
            </template>

            <template #filters>
                <div class="relative mb-2 min-w-64 focus-within:z-10 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-subtle" aria-hidden="true" />
                    </div>
                    <input
                        v-model="filterData.search"
                        type="text"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus"
                        placeholder="Search"
                        @keydown.enter="fetchData(1)"
                    />
                </div>

                <div class="relative mb-2 min-w-44 sm:mr-4">
                    <select
                        v-model="filterData.module_enabled"
                        class="block w-full rounded-md border-0 py-1.5 pl-3 pr-8 text-sm leading-6 text-heading ring-1 ring-inset ring-strong focus:ring-2 focus:ring-inset focus:ring-focus"
                        @change="fetchData(1)"
                    >
                        <option value="">All autoload states</option>
                        <option :value="'true'">Enabled</option>
                        <option :value="'false'">Disabled</option>
                    </select>
                </div>
            </template>

            <template #action>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-60"
                        title="Refresh"
                        :disabled="loading"
                        @click="refreshData"
                    >
                        <ArrowPathIcon class="h-4 w-4 text-muted" :class="{ 'animate-spin': loading }" />
                        Refresh
                    </button>

                    <a
                        v-if="permissions.create"
                        :href="routes.legacy_add"
                        class="inline-flex items-center gap-1 rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover"
                    >
                        <PlusIcon class="h-4 w-4" />
                        Add
                    </a>
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
                <TableColumnHeader class="px-4 py-3.5 text-left text-sm font-semibold text-heading">
                    <div class="flex items-center">
                        <input
                            v-if="hasSelectableActions"
                            v-model="selectPageItems"
                            type="checkbox"
                            :disabled="pageItems.length === 0"
                            class="h-4 w-4 rounded border-strong text-accent-fg disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <button class="flex items-center" :class="{ 'ml-4': hasSelectableActions }" @click="setSort('module_label')">
                            <span class="mr-2">Module</span>
                            <ChevronUpIcon v-if="sortData.name === 'module_label' && sortData.order === 'asc'" class="h-4 w-4 text-muted" />
                            <ChevronDownIcon v-else-if="sortData.name === 'module_label' && sortData.order === 'desc'" class="h-4 w-4 text-muted" />
                        </button>
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-heading">
                    <button class="flex items-center" @click="setSort('module_category')">
                        <span class="mr-2">Category</span>
                        <ChevronUpIcon v-if="sortData.name === 'module_category' && sortData.order === 'asc'" class="h-4 w-4 text-muted" />
                        <ChevronDownIcon v-else-if="sortData.name === 'module_category' && sortData.order === 'desc'" class="h-4 w-4 text-muted" />
                    </button>
                </TableColumnHeader>
                <TableColumnHeader header="Runtime" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="Autoload" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="Description" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader v-if="hasRowActions" header="" class="px-2 py-3.5 text-right text-sm font-semibold text-heading" />
            </template>

            <template v-if="selectPageItems" #current-selection>
                <td :colspan="columnCount">
                    <div class="m-2 text-center text-sm">
                        <span class="font-semibold">{{ selectedItems.length }}</span> modules are selected.
                        <button
                            v-if="!selectAll && selectedItems.length !== data.total"
                            class="rounded px-2 py-2 text-info transition duration-500 ease-in-out hover:bg-info-subtle hover:text-info focus:bg-info-subtle focus:outline-none focus:ring-1 focus:ring-focus"
                            @click="selectAllMatching"
                        >
                            Select all {{ data.total }} modules
                        </button>
                        <button
                            v-if="selectAll"
                            class="rounded px-2 py-2 text-info transition duration-500 ease-in-out hover:bg-info-subtle hover:text-info focus:bg-info-subtle focus:outline-none focus:ring-1 focus:ring-focus"
                            @click="clearSelection"
                        >
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.module_uuid">
                    <TableField class="px-4 py-2 text-sm text-muted">
                        <div class="flex items-center">
                            <input
                                v-if="hasSelectableActions"
                                v-model="selectedItems"
                                type="checkbox"
                                :value="row.module_uuid"
                                class="h-4 w-4 rounded border-strong text-accent-fg"
                            />
                            <div class="min-w-0" :class="{ 'ml-4': hasSelectableActions }">
                                <a
                                    v-if="permissions.update"
                                    :href="row.edit_url"
                                    class="font-medium text-heading hover:text-accent-fg"
                                >
                                    {{ row.module_label }}
                                </a>
                                <div v-else class="font-medium text-heading">{{ row.module_label }}</div>
                                <div class="mt-1 truncate text-xs text-subtle">{{ row.module_name }}</div>
                            </div>
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted">
                        <Badge :text="row.module_category" v-bind="categoryBadge" />
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted">
                        <Badge :text="runtimeLabel(row.status)" v-bind="runtimeBadge(row.status)" />
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted">
                        <button v-if="permissions.update" type="button" @click="confirmAction('toggle', [row.module_uuid])">
                            <Badge :text="row.module_enabled === 'true' ? 'Enabled' : 'Disabled'" v-bind="enabledBadge(row.module_enabled)" />
                        </button>
                        <Badge v-else :text="row.module_enabled === 'true' ? 'Enabled' : 'Disabled'" v-bind="enabledBadge(row.module_enabled)" />
                    </TableField>

                    <TableField class="max-w-xl px-2 py-2 text-sm text-muted">
                        <span class="line-clamp-2">{{ row.module_description || "No description" }}</span>
                    </TableField>

                    <TableField v-if="hasRowActions" class="whitespace-nowrap px-2 py-1 text-sm text-muted">
                        <template #action-buttons>
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    v-if="permissions.update && row.status === 'stopped'"
                                    type="button"
                                    class="rounded-full p-2 text-subtle transition hover:bg-surface-3 hover:text-success disabled:cursor-not-allowed disabled:opacity-40"
                                    title="Start"
                                    :disabled="!row.can_control_runtime"
                                    @click="confirmAction('start', [row.module_uuid])"
                                >
                                    <PlayIcon class="h-5 w-5" />
                                </button>
                                <button
                                    v-if="permissions.update && row.status === 'running'"
                                    type="button"
                                    class="rounded-full p-2 text-subtle transition hover:bg-surface-3 hover:text-warning disabled:cursor-not-allowed disabled:opacity-40"
                                    title="Stop"
                                    :disabled="!row.can_control_runtime"
                                    @click="confirmAction('stop', [row.module_uuid])"
                                >
                                    <StopIcon class="h-5 w-5" />
                                </button>
                                <a
                                    v-if="permissions.update"
                                    :href="row.edit_url"
                                    class="rounded-full p-2 text-subtle transition hover:bg-surface-3 hover:text-body"
                                    title="Edit"
                                >
                                    <PencilSquareIcon class="h-5 w-5" />
                                </a>
                                <button
                                    v-if="permissions.destroy"
                                    type="button"
                                    class="rounded-full p-2 text-subtle transition hover:bg-surface-3 hover:text-danger"
                                    title="Delete"
                                    @click="confirmAction('delete', [row.module_uuid])"
                                >
                                    <TrashIcon class="h-5 w-5" />
                                </button>
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="!loading && data.data.length === 0" class="px-6 py-8 text-center text-sm text-muted">
                    No modules found.
                </div>
            </template>
        </DataTable>
    </div>

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
import MainLayout from "../Layouts/MainLayout.vue";
import DataTable from "./components/general/DataTable.vue";
import Paginator from "./components/general/Paginator.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Badge from "./components/general/Badge.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Notification from "./components/notifications/Notification.vue";
import {
    ArrowPathIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    MagnifyingGlassIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
} from "@heroicons/vue/24/outline";
import { PlayIcon, StopIcon } from "@heroicons/vue/20/solid";

const props = defineProps({
    routes: Object,
    permissions: Object,
});

const data = ref({
    data: [],
    current_page: 1,
    last_page: 1,
    links: [],
    from: 0,
    to: 0,
    total: 0,
});
const filterData = ref({ search: "", module_enabled: "" });
const sortData = ref({ name: "module_category", order: "asc" });
const selectedItems = ref([]);
const selectAll = ref(false);
const loading = ref(false);
const notificationShow = ref(false);
const notificationType = ref("success");
const notificationMessages = ref(null);
const confirmation = ref({
    show: false,
    action: null,
    items: [],
    header: "",
    text: "",
    button: "Continue",
    color: "indigo",
    loading: false,
});

const routes = computed(() => props.routes || {});
const permissions = computed(() => props.permissions || {});
const hasSelectableActions = computed(() => permissions.value.update || permissions.value.destroy);
const hasRowActions = computed(() => permissions.value.update || permissions.value.destroy);
const pageItems = computed(() => data.value.data.map((row) => row.module_uuid));
const columnCount = computed(() => 5 + (hasRowActions.value ? 1 : 0));
const selectPageItems = computed({
    get() {
        return pageItems.value.length > 0 && pageItems.value.every((uuid) => selectedItems.value.includes(uuid));
    },
    set(checked) {
        selectAll.value = false;
        if (checked) {
            selectedItems.value = Array.from(new Set([...selectedItems.value, ...pageItems.value]));
            return;
        }
        selectedItems.value = selectedItems.value.filter((uuid) => !pageItems.value.includes(uuid));
    },
});

const categoryBadge = {
    backgroundColor: "bg-surface-2",
    textColor: "text-body",
    ringColor: "ring-strong/20",
};

const bulkActions = computed(() => {
    const actions = [];

    if (permissions.value.update) {
        actions.push({ id: "start", label: "Start", icon: "PlayIcon" });
        actions.push({ id: "stop", label: "Stop", icon: "StopIcon" });
        actions.push({ id: "toggle", label: "Toggle", icon: "SyncIcon" });
    }

    if (permissions.value.destroy) {
        actions.push({ id: "delete", label: "Delete", icon: "TrashIcon" });
    }

    return actions;
});

watch(
    () => data.value.current_page,
    () => {
        selectAll.value = false;
    }
);

onMounted(() => fetchData());

function resolvePage(page = 1) {
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
}

function queryParams(page = 1, force = false) {
    const params = { page };

    if (filterData.value.search) {
        params["filter[search]"] = filterData.value.search;
    }

    if (filterData.value.module_enabled) {
        params["filter[module_enabled]"] = filterData.value.module_enabled;
    }

    if (sortData.value.name) {
        params.sort = `${sortData.value.order === "desc" ? "-" : ""}${sortData.value.name}`;
    }

    if (force) {
        params._ = Date.now();
    }

    return params;
}

function fetchData(page = 1, force = false) {
    loading.value = true;
    const requestedPage = resolvePage(page);

    axios
        .get(routes.value.data_route, { params: queryParams(requestedPage, force) })
        .then((response) => {
            data.value = response.data;
        })
        .catch(handleError)
        .finally(() => {
            loading.value = false;
        });
}

function refreshData() {
    fetchData(data.value.current_page || 1, true);
}

function setSort(column) {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === "asc" ? "desc" : "asc";
    } else {
        sortData.value.name = column;
        sortData.value.order = "asc";
    }

    fetchData(1);
}

function resetFilters() {
    filterData.value = { search: "", module_enabled: "" };
    fetchData(1);
}

function selectAllMatching() {
    axios
        .post(routes.value.select_all, { filter: filterData.value })
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification("success", response.data.messages);
        })
        .catch(handleError);
}

function clearSelection() {
    selectedItems.value = [];
    selectAll.value = false;
}

function handleBulkAction(action) {
    confirmAction(action, selectedItems.value);
}

function confirmAction(action, items) {
    if (!items.length) {
        showNotification("error", { request: ["No modules selected."] });
        return;
    }

    const count = items.length;
    const copy = {
        start: {
            header: "Start modules?",
            text: `Start ${count} selected module${count === 1 ? "" : "s"} in FreeSWITCH.`,
            button: "Start",
            color: "green",
        },
        stop: {
            header: "Stop modules?",
            text: `Stop ${count} selected module${count === 1 ? "" : "s"} in FreeSWITCH.`,
            button: "Stop",
            color: "red",
        },
        toggle: {
            header: "Toggle autoload?",
            text: `Toggle autoload for ${count} selected module${count === 1 ? "" : "s"}.`,
            button: "Toggle",
            color: "indigo",
        },
        delete: {
            header: "Delete modules?",
            text: `Delete ${count} selected module${count === 1 ? "" : "s"}.`,
            button: "Delete",
            color: "red",
        },
    }[action];

    confirmation.value = {
        show: true,
        action,
        items: [...items],
        loading: false,
        ...copy,
    };
}

function executeConfirmedAction() {
    const actionRoutes = {
        start: routes.value.bulk_start,
        stop: routes.value.bulk_stop,
        toggle: routes.value.bulk_toggle,
        delete: routes.value.bulk_delete,
    };

    confirmation.value.loading = true;

    axios
        .post(actionRoutes[confirmation.value.action], { items: confirmation.value.items })
        .then((response) => {
            showNotification("success", response.data.messages);
            closeConfirmation();
            clearSelection();
            refreshData();
        })
        .catch((error) => {
            handleError(error);
            closeConfirmation();
            refreshData();
        })
        .finally(() => {
            confirmation.value.loading = false;
        });
}

function closeConfirmation() {
    confirmation.value.show = false;
}

function runtimeLabel(status) {
    return {
        running: "Running",
        stopped: "Stopped",
        unknown: "Unknown",
    }[status] || "Unknown";
}

function runtimeBadge(status) {
    return {
        running: { backgroundColor: "bg-success-subtle", textColor: "text-success", ringColor: "ring-success/20" },
        stopped: { backgroundColor: "bg-danger-subtle", textColor: "text-danger", ringColor: "ring-danger/20" },
        unknown: { backgroundColor: "bg-surface-2", textColor: "text-body", ringColor: "ring-strong/20" },
    }[status] || { backgroundColor: "bg-surface-2", textColor: "text-body", ringColor: "ring-strong/20" };
}

function enabledBadge(enabled) {
    return enabled === "true"
        ? { backgroundColor: "bg-info-subtle", textColor: "text-info", ringColor: "ring-info/20" }
        : { backgroundColor: "bg-surface-2", textColor: "text-body", ringColor: "ring-strong/20" };
}

function showNotification(type, messages = null) {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
}

function handleError(error) {
    if (error?.response?.status === 419) {
        showNotification("error", { request: ["Session expired. Reload the page."] });
        return;
    }

    if (error?.response?.data) {
        showNotification("error", error.response.data.messages || error.response.data.errors || { request: [error.message] });
        return;
    }

    showNotification("error", { request: [error?.message || "Request failed."] });
}
</script>
