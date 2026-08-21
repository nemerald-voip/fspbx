<template>
    <MainLayout />

    <div class="m-3 space-y-4">
        <!-- Dismissing only hides the notice for this page view; it is shown again on every load. -->
        <div
            v-if="noticeVisible"
            class="flex gap-3 rounded-md bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-inset ring-amber-600/20"
        >
            <ExclamationTriangleIcon class="h-5 w-5 shrink-0 text-amber-500" aria-hidden="true" />
            <div class="min-w-0 flex-1">
                <p class="font-medium">{{ $t("Device profiles are scheduled to be retired.") }}</p>
                <p class="mt-1">
                    {{ $t("They keep working for devices that still rely on them, but they will be removed in a future release. Use key templates for any new work.") }}
                    <a
                        v-if="permissions.view_key_templates"
                        :href="routes.key_templates"
                        class="font-medium text-amber-900 underline hover:text-amber-950"
                    >
                        {{ $t("Go to key templates") }}
                    </a>
                </p>
            </div>
            <button
                type="button"
                class="-m-1.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-amber-500 hover:bg-amber-100 hover:text-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-600"
                :aria-label="$t('Dismiss')"
                @click="noticeVisible = false"
            >
                <XMarkIcon class="h-4 w-4" />
            </button>
        </div>

        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>
                <span class="flex flex-wrap items-center gap-2">
                    {{ $t("Device Profiles") }}
                    <span class="rounded bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20">
                        {{ $t("Being retired") }}
                    </span>
                </span>
            </template>

            <template #subtitle>
                {{ $t("Legacy multi-vendor key and setting profiles assigned to devices. Build new key layouts with key templates instead.") }}
            </template>

            <template #filters>
                <div class="relative mb-2 min-w-64 focus-within:z-10 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input
                        v-model="filterData.search"
                        type="search"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                        :placeholder="$t('Search profiles')"
                        :aria-label="$t('Search device profiles')"
                        @keydown.enter="handleSearchButtonClick"
                    />
                </div>

                <select
                    v-model="filterData.fields"
                    class="mb-2 min-w-44 rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:mr-4"
                    :aria-label="$t('Search fields')"
                    @change="handleFilterChange"
                >
                    <option value="profile">{{ $t("Profile details") }}</option>
                    <option value="keys">{{ $t("Profile details and keys") }}</option>
                    <option value="settings">{{ $t("Profile details and settings") }}</option>
                    <option value="all">{{ $t("All profile content") }}</option>
                </select>

                <select
                    v-if="permissions.view_all"
                    v-model="filterData.scope"
                    class="mb-2 min-w-44 rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:mr-4"
                    :aria-label="$t('Account scope')"
                    @change="handleScopeChange"
                >
                    <option value="current">{{ $t("Current account and global") }}</option>
                    <option value="all">{{ $t("All accounts") }}</option>
                </select>
            </template>

            <template #action>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <a
                        :href="routes.devices"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                    >
                        {{ $t("Devices") }}
                    </a>
                    <button
                        v-if="permissions.create"
                        type="button"
                        class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                        @click="openProfileForm()"
                    >
                        <PlusIcon class="h-4 w-4" aria-hidden="true" />
                        {{ $t("Add") }}
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
                    :current-page="data.current_page"
                    :last-page="data.last_page"
                    :links="data.links"
                    :bulk-actions="bulkActions"
                    :has-selected-items="selectedItems.length > 0"
                    :page-size="perPage"
                    :page-size-options="pagination.per_page_options"
                    @pagination-change-page="renderRequestedPage"
                    @bulk-action="handleBulkActionRequest"
                    @page-size-change="handlePageSizeChange"
                />
            </template>

            <template #table-header>
                <TableColumnHeader class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center">
                        <input
                            v-model="selectPageItems"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                            :aria-label="$t('Select this page')"
                            @change="handleSelectPageItems"
                        />
                        <button
                            type="button"
                            class="ml-4 flex items-center"
                            @click="handleSortRequest('device_profile_name')"
                        >
                            <span class="mr-2">{{ $t("Name") }}</span>
                            <ChevronUpIcon
                                v-if="sortData.name === 'device_profile_name' && sortData.order === 'asc'"
                                class="h-4 w-4 text-gray-500"
                            />
                            <ChevronDownIcon
                                v-else-if="sortData.name === 'device_profile_name' && sortData.order === 'desc'"
                                class="h-4 w-4 text-gray-500"
                            />
                        </button>
                    </div>
                </TableColumnHeader>

                <TableColumnHeader class="w-28 px-2 py-3.5 text-center text-sm font-semibold text-gray-900">
                    <button
                        type="button"
                        class="flex w-full items-center justify-center"
                        @click="handleSortRequest('device_profile_enabled')"
                    >
                        <span class="mr-2">{{ $t("Status") }}</span>
                        <ChevronUpIcon
                            v-if="sortData.name === 'device_profile_enabled' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500"
                        />
                        <ChevronDownIcon
                            v-else-if="sortData.name === 'device_profile_enabled' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500"
                        />
                    </button>
                </TableColumnHeader>

                <TableColumnHeader
                    :header="$t('Configuration')"
                    class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900"
                />
                <TableColumnHeader
                    :header="$t('Assigned Devices')"
                    class="whitespace-nowrap px-2 py-3.5 text-center text-sm font-semibold text-gray-900"
                />
                <TableColumnHeader
                    v-if="showAccountColumn"
                    :header="$t('Account')"
                    class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900"
                />
                <TableColumnHeader
                    :header="$t('Description')"
                    class="min-w-64 px-2 py-3.5 text-left text-sm font-semibold text-gray-900"
                />
                <TableColumnHeader
                    v-if="hasRowActions"
                    header=""
                    class="w-28 px-2 py-3.5 text-right text-sm font-semibold text-gray-900"
                />
            </template>

            <template v-if="selectPageItems" #current-selection>
                <td :colspan="columnCount">
                    <div class="m-2 text-center text-sm text-gray-700">
                        <span class="font-semibold">{{ selectedItems.length }}</span>
                        {{ $t("device profiles selected.") }}
                        <button
                            v-if="!selectAll && selectedItems.length !== data.total"
                            type="button"
                            class="rounded px-2 py-2 text-blue-600 hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            @click="handleSelectAll"
                        >
                            {{ $t("Select all :count matching profiles", { count: data.total }) }}
                        </button>
                        <button
                            v-if="selectAll"
                            type="button"
                            class="rounded px-2 py-2 text-blue-600 hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            @click="handleClearSelection"
                        >
                            {{ $t("Clear selection") }}
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.device_profile_uuid" class="hover:bg-gray-50">
                    <TableField class="px-4 py-2 text-sm text-gray-600">
                        <div class="flex min-w-56 items-center">
                            <input
                                v-model="selectedItems"
                                type="checkbox"
                                :value="row.device_profile_uuid"
                                class="h-4 w-4 flex-none rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                :aria-label="$t('Select :name', { name: row.device_profile_name })"
                                @change="handleRowSelectionChange"
                            />
                            <button
                                v-if="permissions.update"
                                type="button"
                                class="ml-4 min-w-0 truncate text-left font-medium text-gray-900 hover:text-indigo-600"
                                :title="row.device_profile_name"
                                @click="openProfileForm(row)"
                            >
                                {{ row.device_profile_name }}
                            </button>
                            <span v-else class="ml-4 min-w-0 truncate font-medium text-gray-900" :title="row.device_profile_name">
                                {{ row.device_profile_name }}
                            </span>
                        </div>
                    </TableField>

                    <TableField class="w-28 whitespace-nowrap px-2 py-2 text-center text-sm text-gray-600">
                        <button
                            v-if="permissions.update"
                            type="button"
                            class="rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            :aria-label="$t('Toggle status for :name', { name: row.device_profile_name })"
                            @click="executeToggle([row.device_profile_uuid])"
                        >
                            <Badge :text="statusLabel(row)" v-bind="enabledBadgeProps(row.device_profile_enabled)" />
                        </button>
                        <Badge
                            v-else
                            :text="statusLabel(row)"
                            v-bind="enabledBadgeProps(row.device_profile_enabled)"
                        />
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-600">
                        <div class="flex flex-wrap gap-1.5">
                            <Badge
                                :text="keyCountLabel(row.keys_count)"
                                background-color="bg-blue-50"
                                text-color="text-blue-700"
                                ring-color="ring-blue-600/20"
                            />
                            <Badge
                                :text="settingCountLabel(row.settings_count)"
                                background-color="bg-gray-50"
                                text-color="text-gray-700"
                                ring-color="ring-gray-500/20"
                            />
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-center text-sm text-gray-600">
                        <span class="font-medium text-gray-900">{{ row.devices_count }}</span>
                    </TableField>

                    <TableField
                        v-if="showAccountColumn"
                        class="max-w-56 px-2 py-2 text-sm text-gray-600"
                    >
                        <Badge
                            :text="row.domain_label"
                            :background-color="row.domain_uuid ? 'bg-gray-50' : 'bg-indigo-50'"
                            :text-color="row.domain_uuid ? 'text-gray-700' : 'text-indigo-700'"
                            :ring-color="row.domain_uuid ? 'ring-gray-500/20' : 'ring-indigo-600/20'"
                        />
                    </TableField>

                    <TableField class="max-w-md px-2 py-2 text-sm text-gray-600">
                        <span class="line-clamp-2" :title="row.device_profile_description || ''">
                            {{ row.device_profile_description || $t("No description") }}
                        </span>
                    </TableField>

                    <TableField v-if="hasRowActions" class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    v-if="permissions.update"
                                    type="button"
                                    class="rounded-full p-2 text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                    :title="$t('Edit')"
                                    :aria-label="$t('Edit :name', { name: row.device_profile_name })"
                                    @click="openProfileForm(row)"
                                >
                                    <PencilSquareIcon class="h-5 w-5" />
                                </button>
                                <button
                                    v-if="permissions.create"
                                    type="button"
                                    class="rounded-full p-2 text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                    :title="$t('Copy')"
                                    :aria-label="$t('Copy :name', { name: row.device_profile_name })"
                                    @click="executeCopy([row.device_profile_uuid])"
                                >
                                    <DocumentDuplicateIcon class="h-5 w-5" />
                                </button>
                                <button
                                    v-if="permissions.destroy"
                                    type="button"
                                    class="rounded-full p-2 text-gray-400 transition-colors duration-150 hover:bg-red-50 hover:text-red-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600"
                                    :title="$t('Delete')"
                                    :aria-label="$t('Delete :name', { name: row.device_profile_name })"
                                    @click="openDeleteModal(row)"
                                >
                                    <TrashIcon class="h-5 w-5" />
                                </button>
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="!loading && data.data.length === 0" class="px-6 py-12 text-center">
                    <KeyIcon class="mx-auto h-10 w-10 text-gray-400" aria-hidden="true" />
                    <h3 class="mt-3 text-sm font-semibold text-gray-900">
                        {{ hasActiveSearch ? $t("No matching device profiles") : $t("No device profiles") }}
                    </h3>
                    <p class="mx-auto mt-1 max-w-lg text-sm text-gray-500">
                        {{
                            hasActiveSearch
                                ? $t("Adjust the search or scope and try again.")
                                : $t("Create a profile for legacy multi-vendor keys and device settings, or use Key Templates for new key layouts.")
                        }}
                    </p>
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
                    :current-page="data.current_page"
                    :last-page="data.last_page"
                    :links="data.links"
                    :page-size="perPage"
                    :page-size-options="pagination.per_page_options"
                    @pagination-change-page="renderRequestedPage"
                    @page-size-change="handlePageSizeChange"
                />
            </template>
        </DataTable>
    </div>

    <DeviceProfileForm
        :show="showProfileForm"
        :loading="profileFormLoading"
        :options="profileFormOptions"
        :mode="profileFormMode"
        :header="profileFormHeader"
        @close="closeProfileForm"
        @error="handleErrorResponse"
        @success="showNotification"
        @refresh-data="getData(currentPage)"
    />

    <ConfirmationModal
        :show="showDeleteConfirmation"
        :header="$t('Delete device profiles?')"
        :text="deleteConfirmationText"
        :confirm-button-label="$t('Delete')"
        :cancel-button-label="$t('Cancel')"
        :loading="deleteSubmitting"
        @close="closeDeleteModal"
        @confirm="executeDelete"
    />

    <Notification
        :show="notificationShow"
        :type="notificationType"
        :messages="notificationMessages"
        @update:show="hideNotification"
    />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import { trans } from "@i18n";
import MainLayout from "../Layouts/MainLayout.vue";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import Loading from "./components/general/Loading.vue";
import Badge from "@generalComponents/Badge.vue";
import DeviceProfileForm from "./components/forms/DeviceProfileForm.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Notification from "./components/notifications/Notification.vue";
import {
    ChevronDownIcon,
    ChevronUpIcon,
    DocumentDuplicateIcon,
    ExclamationTriangleIcon,
    KeyIcon,
    MagnifyingGlassIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
    XMarkIcon,
} from "@heroicons/vue/24/solid";

const props = defineProps({
    routes: {
        type: Object,
        required: true,
    },
    permissions: {
        type: Object,
        default: () => ({}),
    },
    pagination: {
        type: Object,
        default: () => ({
            per_page: 50,
            per_page_options: [25, 50, 100],
        }),
    },
});

const routes = props.routes;
const permissions = props.permissions;
const pagination = props.pagination;

const loading = ref(false);
const currentPage = ref(1);
const perPage = ref(Number(pagination.per_page) || 50);
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const showDeleteConfirmation = ref(false);
const deleteSubmitting = ref(false);
const deleteTarget = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);
const noticeVisible = ref(true);
const showProfileForm = ref(false);
const profileFormLoading = ref(false);
const profileFormOptions = ref({});
const profileFormMode = ref("create");
const profileFormTarget = ref(null);

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
    fields: "profile",
    scope: "current",
});

const sortData = ref({
    name: "device_profile_name",
    order: "asc",
});

const hasRowActions = computed(() => permissions.update || permissions.create || permissions.destroy);
const showAccountColumn = computed(() => permissions.view_all && filterData.value.scope === "all");
const columnCount = computed(() => 5 + (showAccountColumn.value ? 1 : 0) + (hasRowActions.value ? 1 : 0));
const hasActiveSearch = computed(() => Boolean(filterData.value.search) || filterData.value.scope === "all");
const bulkActions = computed(() => {
    const actions = [];

    if (permissions.create) {
        actions.push({ id: "bulk_copy", label: trans("Copy"), icon: "DocumentDuplicateIcon" });
    }

    if (permissions.update) {
        actions.push({ id: "bulk_toggle", label: trans("Toggle Enabled"), icon: "PencilSquareIcon" });
    }

    if (permissions.destroy) {
        actions.push({ id: "bulk_delete", label: trans("Delete"), icon: "TrashIcon" });
    }

    return actions;
});
const deleteConfirmationText = computed(() => {
    const count = deleteTarget.value === "bulk" ? selectedItems.value.length : 1;
    const name = deleteTarget.value?.device_profile_name;

    if (name) {
        return trans("Delete :name? Its keys and settings will also be deleted.", { name });
    }

    return trans("Delete :count selected device profile(s)? Their keys and settings will also be deleted.", { count });
});
const profileFormHeader = computed(() => {
    if (profileFormMode.value === "create") {
        return trans("Add device profile");
    }

    return trans("Edit :name", {
        name: profileFormTarget.value?.device_profile_name || trans("device profile"),
    });
});

onMounted(() => {
    getData();
});

function getData(page = 1) {
    loading.value = true;
    currentPage.value = Number(page) || 1;

    const sort = sortData.value.order === "desc"
        ? `-${sortData.value.name}`
        : sortData.value.name;

    axios.get(routes.data_route, {
        params: {
            filter: filterData.value,
            page: currentPage.value,
            per_page: perPage.value,
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
}

function handleSortRequest(column) {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === "asc" ? "desc" : "asc";
    } else {
        sortData.value.name = column;
        sortData.value.order = "asc";
    }

    getData(currentPage.value);
}

function handleSelectPageItems() {
    selectedItems.value = selectPageItems.value
        ? data.value.data.map((item) => item.device_profile_uuid)
        : [];
    selectAll.value = false;
}

function handleRowSelectionChange() {
    selectPageItems.value = data.value.data.length > 0
        && data.value.data.every((item) => selectedItems.value.includes(item.device_profile_uuid));
    selectAll.value = false;
}

function handleSelectAll() {
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
}

function handleClearSelection() {
    selectedItems.value = [];
    selectPageItems.value = false;
    selectAll.value = false;
}

function handleBulkActionRequest(action) {
    if (selectedItems.value.length === 0) {
        return;
    }

    if (action === "bulk_copy") {
        executeCopy();
    } else if (action === "bulk_toggle") {
        executeToggle();
    } else if (action === "bulk_delete") {
        deleteTarget.value = "bulk";
        showDeleteConfirmation.value = true;
    }
}

function executeCopy(items = selectedItems.value) {
    executeBulkRequest(routes.bulk_copy, items, currentPage.value);
}

function openProfileForm(row = null) {
    profileFormTarget.value = row;
    profileFormMode.value = row ? "update" : "create";
    profileFormOptions.value = {};
    profileFormLoading.value = true;
    showProfileForm.value = true;

    axios.post(routes.item_options, {
        item_uuid: row?.device_profile_uuid,
        scope: filterData.value.scope,
    })
        .then((response) => {
            profileFormOptions.value = {
                ...response.data,
                scope: filterData.value.scope,
            };
        })
        .catch((error) => {
            closeProfileForm();
            handleErrorResponse(error);
        })
        .finally(() => {
            profileFormLoading.value = false;
        });
}

function closeProfileForm() {
    showProfileForm.value = false;
    profileFormLoading.value = false;
    profileFormOptions.value = {};
    profileFormTarget.value = null;
}

function executeToggle(items = selectedItems.value) {
    executeBulkRequest(routes.bulk_toggle, items, currentPage.value);
}

function executeBulkRequest(route, items, page) {
    axios.post(route, {
        items,
        scope: filterData.value.scope,
    })
        .then((response) => {
            showNotification("success", response.data.messages);
            handleClearSelection();
            getData(page);
        })
        .catch(handleErrorResponse);
}

function openDeleteModal(row) {
    deleteTarget.value = row;
    showDeleteConfirmation.value = true;
}

function closeDeleteModal() {
    showDeleteConfirmation.value = false;
    deleteTarget.value = null;
}

function executeDelete() {
    const items = deleteTarget.value === "bulk"
        ? selectedItems.value
        : [deleteTarget.value.device_profile_uuid];

    deleteSubmitting.value = true;

    axios.post(routes.bulk_delete, {
        items,
        scope: filterData.value.scope,
    })
        .then((response) => {
            showNotification("success", response.data.messages);
            closeDeleteModal();
            handleClearSelection();

            const nextPage = data.value.data.length === items.length && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;
            getData(nextPage);
        })
        .catch(handleErrorResponse)
        .finally(() => {
            deleteSubmitting.value = false;
        });
}

function handleSearchButtonClick() {
    handleClearSelection();
    getData(1);
}

function handleFilterChange() {
    if (filterData.value.search) {
        handleSearchButtonClick();
    }
}

function handleScopeChange() {
    handleClearSelection();
    getData(1);
}

function handleFiltersReset() {
    filterData.value = {
        search: null,
        fields: "profile",
        scope: "current",
    };
    handleClearSelection();
    getData(1);
}

function handlePageSizeChange(value) {
    perPage.value = Number(value) || perPage.value;
    handleClearSelection();
    getData(1);
}

function renderRequestedPage(url) {
    if (!url) {
        return;
    }

    const urlObject = new URL(url, window.location.origin);
    handleClearSelection();
    getData(urlObject.searchParams.get("page") ?? 1);
}

function keyCountLabel(count) {
    return `${count} ${Number(count) === 1 ? trans("Key") : trans("Keys")}`;
}

function settingCountLabel(count) {
    return `${count} ${Number(count) === 1 ? trans("Setting") : trans("Settings")}`;
}

function statusLabel(row) {
    return row.device_profile_enabled === "true" ? trans("Enabled") : trans("Disabled");
}

function enabledBadgeProps(value) {
    return value === "true"
        ? {
            backgroundColor: "bg-green-50",
            textColor: "text-green-700",
            ringColor: "ring-green-600/20",
        }
        : {
            backgroundColor: "bg-gray-50",
            textColor: "text-gray-600",
            ringColor: "ring-gray-500/20",
        };
}

function handleErrorResponse(error) {
    if (error.request?.status === 419) {
        showNotification("error", { request: [trans("Session expired. Reload the page.")] });
        return;
    }

    if (error.response) {
        showNotification(
            "error",
            error.response.data.messages
                || error.response.data.errors
                || { request: [error.message] },
        );
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
