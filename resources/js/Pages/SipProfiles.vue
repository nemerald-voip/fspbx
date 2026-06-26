<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="fetchData(1)" @reset-filters="resetFilters">
            <template #title>SIP Profiles</template>

            <template #subtitle>
                Manage Sofia SIP profiles, and profile parameters.
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
                        v-model="filterData.sip_profile_enabled"
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
                        <button class="flex items-center" :class="{ 'ml-4': hasSelectableActions }" @click="setSort('sip_profile_name')">
                            <span class="mr-2">Profile</span>
                            <ChevronUpIcon v-if="sortData.name === 'sip_profile_name' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'sip_profile_name' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                        </button>
                    </div>
                </TableColumnHeader>
                <TableColumnHeader header="Bindings" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="State" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Config" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <button class="flex items-center" @click="setSort('sip_profile_hostname')">
                        <span class="mr-2">Hostname</span>
                        <ChevronUpIcon v-if="sortData.name === 'sip_profile_hostname' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'sip_profile_hostname' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </button>
                </TableColumnHeader>
                <TableColumnHeader header="Description" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader v-if="hasRowActions" header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" #current-selection>
                <td :colspan="columnCount">
                    <div class="m-2 text-center text-sm">
                        <span class="font-semibold">{{ selectedItems.length }}</span> SIP profiles are selected.
                        <button
                            v-if="!selectAll && selectedItems.length !== data.total"
                            class="rounded px-2 py-2 text-blue-500 transition duration-500 ease-in-out hover:bg-blue-200 hover:text-blue-500 focus:bg-blue-200 focus:outline-none focus:ring-1 focus:ring-blue-300"
                            @click="selectAllMatching"
                        >
                            Select all {{ data.total }} SIP profiles
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
                <tr v-for="row in data.data" :key="row.sip_profile_uuid">
                    <TableField class="px-4 py-2 text-sm text-gray-500">
                        <div class="flex items-center">
                            <input
                                v-if="hasSelectableActions"
                                v-model="selectedItems"
                                type="checkbox"
                                :value="row.sip_profile_uuid"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600"
                            />
                            <button
                                type="button"
                                class="min-w-0 text-left"
                                :class="{ 'ml-4': hasSelectableActions, 'cursor-pointer': permissions.update }"
                                @click="permissions.update && openEditModal(row)"
                            >
                                <span class="block font-medium text-gray-900" :class="{ 'hover:text-indigo-600': permissions.update }">
                                    {{ row.sip_profile_name }}
                                </span>
                                <span v-if="row.context" class="mt-1 block truncate text-xs text-gray-400">context: {{ row.context }}</span>
                            </button>
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <div class="space-y-1">
                            <div class="flex items-center gap-1.5 font-mono text-xs text-gray-700">
                                <span class="w-8 font-sans text-[10px] uppercase tracking-wide text-gray-400">SIP</span>
                                {{ row.sip_ip || "—" }}<span class="text-gray-400">:</span>{{ row.sip_port || "5060" }}
                            </div>
                            <div
                                v-if="row.tls_enabled"
                                class="flex items-center gap-1.5 font-mono text-xs text-emerald-700"
                                :title="row.tls_value && row.tls_value.startsWith('$') ? `TLS gated by ${row.tls_value}` : 'TLS enabled'"
                            >
                                <span class="w-8 font-sans text-[10px] uppercase tracking-wide text-emerald-500">TLS</span>
                                {{ row.sip_ip || "—" }}<span class="text-emerald-400">:</span>{{ row.tls_port || "5061" }}
                            </div>
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <button v-if="permissions.update" type="button" @click="confirmAction('toggle', [row.sip_profile_uuid])">
                            <Badge :text="row.sip_profile_enabled === 'true' ? 'Enabled' : 'Disabled'" v-bind="enabledBadge(row.sip_profile_enabled)" />
                        </button>
                        <Badge v-else :text="row.sip_profile_enabled === 'true' ? 'Enabled' : 'Disabled'" v-bind="enabledBadge(row.sip_profile_enabled)" />
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <div class="flex flex-wrap gap-1.5">
                            <Badge :text="`${row.settings_count} settings`" v-bind="countBadge" />
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        {{ row.sip_profile_hostname || "—" }}
                    </TableField>

                    <TableField class="max-w-xl px-2 py-2 text-sm text-gray-500">
                        <span class="line-clamp-2">{{ row.sip_profile_description || "No description" }}</span>
                    </TableField>

                    <TableField v-if="hasRowActions" class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    v-if="permissions.update"
                                    type="button"
                                    class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                                    title="Edit"
                                    @click="openEditModal(row)"
                                >
                                    <PencilSquareIcon class="h-5 w-5" />
                                </button>
                                <button
                                    v-if="permissions.create"
                                    type="button"
                                    class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                                    title="Clone"
                                    @click="cloneProfile(row)"
                                >
                                    <DocumentDuplicateIcon class="h-5 w-5" />
                                </button>
                                <button
                                    v-if="permissions.destroy"
                                    type="button"
                                    class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-red-600"
                                    title="Delete"
                                    @click="confirmAction('delete', [row.sip_profile_uuid])"
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
                    No SIP profiles found.
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

    <SipProfileForm
        :show="showForm"
        :header="formHeader"
        :mode="formMode"
        :loading="loadingForm"
        :options="itemOptions"
        @close="closeForm"
        @error="handleError"
        @success="showNotification"
        @refresh-data="refreshData"
        @reload-options="reloadItemOptions"
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
import Paginator from "./components/general/Paginator.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Loading from "./components/general/Loading.vue";
import Badge from "./components/general/Badge.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Notification from "./components/notifications/Notification.vue";
import SipProfileForm from "./components/forms/SipProfileForm.vue";
import {
    ChevronDownIcon,
    ChevronUpIcon,
    DocumentDuplicateIcon,
    MagnifyingGlassIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
} from "@heroicons/vue/24/outline";

const props = defineProps({
    routes: Object,
    permissions: Object,
});

const data = ref({ data: [], current_page: 1, last_page: 1, links: [], from: 0, to: 0, total: 0 });
const filterData = ref({ search: "", sip_profile_enabled: "" });
const sortData = ref({ name: "sip_profile_name", order: "asc" });
const selectedItems = ref([]);
const selectAll = ref(false);
const loading = ref(false);
const notificationShow = ref(false);
const notificationType = ref("success");
const notificationMessages = ref(null);
const showForm = ref(false);
const formMode = ref("create");
const loadingForm = ref(false);
const editingItemUuid = ref(null);
const itemOptions = ref({ item: {}, domains: [], settings: [], permissions: {}, routes: {} });
const confirmation = ref({ show: false, action: null, items: [], header: "", text: "", button: "Continue", color: "indigo", loading: false });

const routes = computed(() => props.routes || {});
const permissions = computed(() => props.permissions || {});
const hasSelectableActions = computed(() => permissions.value.update || permissions.value.destroy);
const hasRowActions = computed(() => permissions.value.update || permissions.value.create || permissions.value.destroy);
const pageItems = computed(() => data.value.data.map((row) => row.sip_profile_uuid));
const columnCount = computed(() => 6 + (hasRowActions.value ? 1 : 0));
const formHeader = computed(() => (formMode.value === "create" ? "Create SIP Profile" : "Edit SIP Profile"));
const selectPageItems = computed({
    get() {
        return pageItems.value.length > 0 && pageItems.value.every((uuid) => selectedItems.value.includes(uuid));
    },
    set(checked) {
        selectAll.value = false;
        selectedItems.value = checked
            ? Array.from(new Set([...selectedItems.value, ...pageItems.value]))
            : selectedItems.value.filter((uuid) => !pageItems.value.includes(uuid));
    },
});

const countBadge = { backgroundColor: "bg-slate-50", textColor: "text-slate-700", ringColor: "ring-slate-600/20" };
const bulkActions = computed(() => {
    const actions = [];

    if (permissions.value.update) {
        actions.push({ id: "toggle", label: "Toggle", icon: "SyncIcon" });
    }

    if (permissions.value.destroy) {
        actions.push({ id: "delete", label: "Delete", icon: "TrashIcon" });
    }

    return actions;
});

watch(() => data.value.current_page, () => { selectAll.value = false; });
onMounted(() => fetchData());

function queryParams(page = 1) {
    const params = { page };

    if (filterData.value.search) params["filter[search]"] = filterData.value.search;
    if (filterData.value.sip_profile_enabled) params["filter[sip_profile_enabled]"] = filterData.value.sip_profile_enabled;
    if (sortData.value.name) params.sort = `${sortData.value.order === "desc" ? "-" : ""}${sortData.value.name}`;

    return params;
}

function resolvePage(page = 1) {
    if (typeof page === "number") return page;
    if (!page) return 1;

    try {
        return Number(new URL(page, window.location.origin).searchParams.get("page") || 1);
    } catch {
        return 1;
    }
}

function fetchData(page = 1) {
    loading.value = true;

    axios
        .get(routes.value.data_route, { params: queryParams(resolvePage(page)) })
        .then((response) => { data.value = response.data; })
        .catch(handleError)
        .finally(() => { loading.value = false; });
}

function refreshData() {
    fetchData(data.value.current_page || 1);
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
    filterData.value = { search: "", sip_profile_enabled: "" };
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

function openCreateModal() {
    formMode.value = "create";
    editingItemUuid.value = null;
    loadItemOptions();
}

function openEditModal(row) {
    formMode.value = "edit";
    editingItemUuid.value = row.sip_profile_uuid;
    loadItemOptions(row.sip_profile_uuid);
}

function cloneProfile(row) {
    axios
        .post(row.duplicate_route)
        .then((response) => {
            showNotification("success", response.data.messages);
            refreshData();
        })
        .catch(handleError);
}

function loadItemOptions(itemUuid = null) {
    loadingForm.value = true;
    showForm.value = true;

    axios
        .post(routes.value.item_options, { itemUuid })
        .then((response) => { itemOptions.value = response.data; })
        .catch((error) => {
            handleError(error);
            closeForm();
        })
        .finally(() => { loadingForm.value = false; });
}

function reloadItemOptions() {
    if (editingItemUuid.value) {
        loadItemOptions(editingItemUuid.value);
    }
}

function closeForm() {
    showForm.value = false;
    editingItemUuid.value = null;
    itemOptions.value = { item: {}, domains: [], settings: [], permissions: {}, routes: {} };
}

function handleBulkAction(action) {
    confirmAction(action, selectedItems.value);
}

function confirmAction(action, items) {
    if (!items.length) {
        showNotification("error", { request: ["No SIP profiles selected."] });
        return;
    }

    const count = items.length;
    const copy = {
        toggle: {
            header: "Toggle SIP profiles?",
            text: `Toggle enabled state for ${count} selected SIP profile${count === 1 ? "" : "s"}.`,
            button: "Toggle",
            color: "indigo",
        },
        delete: {
            header: "Delete SIP profiles?",
            text: `Delete ${count} selected SIP profile${count === 1 ? "" : "s"} and its domains/settings.`,
            button: "Delete",
            color: "red",
        },
    }[action];

    confirmation.value = { show: true, action, items: [...items], loading: false, ...copy };
}

function executeConfirmedAction() {
    const actionRoutes = {
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
        .finally(() => { confirmation.value.loading = false; });
}

function closeConfirmation() {
    confirmation.value.show = false;
}

function enabledBadge(enabled) {
    return enabled === "true"
        ? { backgroundColor: "bg-green-50", textColor: "text-green-700", ringColor: "ring-green-600/20" }
        : { backgroundColor: "bg-gray-50", textColor: "text-gray-600", ringColor: "ring-gray-500/20" };
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
