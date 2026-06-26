<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="fetchData(1)" @reset-filters="resetFilters">
            <template #title>Database Transactions</template>

            <template #subtitle>
                Track database changes by user, source address, transaction type, and time.
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

                <div v-if="users.length" class="relative mb-2 min-w-48 sm:mr-4">
                    <select
                        v-model="filterData.user_uuid"
                        class="block w-full rounded-md border-0 py-1.5 pl-3 pr-8 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                        @change="fetchData(1)"
                    >
                        <option value="">All users</option>
                        <option v-for="user in users" :key="user.value" :value="user.value">
                            {{ user.label }}
                        </option>
                    </select>
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
                    @pagination-change-page="fetchData"
                />
            </template>

            <template #table-header>
                <TableColumnHeader class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <button class="flex items-center" @click="setSort('domain_name')">
                        <span class="mr-2">Domain</span>
                        <ChevronUpIcon v-if="sortData.name === 'domain_name' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'domain_name' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </button>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <button class="flex items-center" @click="setSort('username')">
                        <span class="mr-2">User</span>
                        <ChevronUpIcon v-if="sortData.name === 'username' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'username' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </button>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <button class="flex items-center" @click="setSort('app_name')">
                        <span class="mr-2">App</span>
                        <ChevronUpIcon v-if="sortData.name === 'app_name' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'app_name' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </button>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <button class="flex items-center" @click="setSort('transaction_code')">
                        <span class="mr-2">Code</span>
                        <ChevronUpIcon v-if="sortData.name === 'transaction_code' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'transaction_code' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </button>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <button class="flex items-center" @click="setSort('transaction_address')">
                        <span class="mr-2">Address</span>
                        <ChevronUpIcon v-if="sortData.name === 'transaction_address' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'transaction_address' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </button>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <button class="flex items-center" @click="setSort('transaction_type')">
                        <span class="mr-2">Type</span>
                        <ChevronUpIcon v-if="sortData.name === 'transaction_type' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'transaction_type' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </button>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <button class="flex items-center" @click="setSort('transaction_date')">
                        <span class="mr-2">Date</span>
                        <ChevronUpIcon v-if="sortData.name === 'transaction_date' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'transaction_date' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </button>
                </TableColumnHeader>
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.database_transaction_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                        {{ row.domain_name || "No domain" }}
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        {{ row.username || "No user" }}
                    </TableField>
                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        <button
                            type="button"
                            class="max-w-56 truncate text-left font-medium text-gray-900 hover:text-indigo-600"
                            @click="openDetails(row.database_transaction_uuid)"
                        >
                            {{ row.app_name || "No app" }}
                        </button>
                    </TableField>
                    <TableField class="max-w-52 px-2 py-2 text-sm text-gray-500">
                        <span class="line-clamp-2">{{ row.transaction_code || "No code" }}</span>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 font-mono text-xs text-gray-500">
                        {{ row.transaction_address || "No address" }}
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <Badge :text="row.transaction_type || 'unknown'" v-bind="typeBadge(row.transaction_type)" />
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        {{ formatDate(row.transaction_date) }}
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center justify-end">
                                <button
                                    type="button"
                                    class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                                    title="View"
                                    @click="openDetails(row.database_transaction_uuid)"
                                >
                                    <EyeIcon class="h-5 w-5" />
                                </button>
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="!loading && data.data.length === 0" class="px-6 py-8 text-center text-sm text-gray-500">
                    No database transactions found.
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

    <AddEditItemModal
        :show="details.show"
        :header="detailsHeader"
        :loading="details.loading"
        custom-class="sm:max-w-6xl"
        body-class="max-h-[72vh] overflow-y-auto"
        @close="closeDetails"
    >
        <template #modal-body>
            <div v-if="details.item" class="space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge :text="details.item.transaction_type" v-bind="typeBadge(details.item.transaction_type)" />
                        <span class="text-sm text-gray-500">{{ formatDate(details.item.transaction_date) }}</span>
                    </div>

                    <button
                        v-if="permissions.update && details.item.can_undo"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        @click="confirmUndo"
                    >
                        <ArrowUturnLeftIcon class="h-4 w-4" />
                        Undo
                    </button>
                </div>

                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="font-medium text-gray-900">Domain</dt>
                        <dd class="mt-1 text-gray-600">{{ details.item.domain_description || details.item.domain_name || "No domain" }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-900">User</dt>
                        <dd class="mt-1 text-gray-600">{{ details.item.username || "No user" }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-900">Address</dt>
                        <dd class="mt-1 font-mono text-xs text-gray-600">{{ details.item.transaction_address || "No address" }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-900">App UUID</dt>
                        <dd class="mt-1 break-all font-mono text-xs text-gray-600">{{ details.item.app_uuid || "No app UUID" }}</dd>
                    </div>
                </dl>

                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Transaction Code</h4>
                    <p class="mt-2 whitespace-pre-wrap rounded-md bg-gray-50 p-3 font-mono text-xs text-gray-700 ring-1 ring-inset ring-gray-200">
                        {{ details.item.transaction_code || "No code" }}
                    </p>
                </div>

                <div v-if="details.item.diff.length" class="space-y-5">
                    <div v-for="section in details.item.diff" :key="section.title" class="overflow-hidden rounded-md ring-1 ring-gray-200">
                        <div class="bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-900">
                            {{ section.title }}
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-white">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Field</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Old</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">New</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="row in section.rows" :key="`${section.title}-${row.name}`">
                                        <td class="max-w-xs px-4 py-2 font-mono text-xs text-gray-700">{{ row.name }}</td>
                                        <td class="max-w-md px-4 py-2 font-mono text-xs" :class="row.changed ? 'text-red-700' : 'text-gray-600'">
                                            <span class="whitespace-pre-wrap break-words">{{ row.old }}</span>
                                        </td>
                                        <td class="max-w-md px-4 py-2 font-mono text-xs" :class="row.changed ? 'text-red-700' : 'text-gray-600'">
                                            <span class="whitespace-pre-wrap break-words">{{ row.new }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div v-else class="rounded-md bg-gray-50 px-4 py-6 text-center text-sm text-gray-500 ring-1 ring-inset ring-gray-200">
                    No transaction details found.
                </div>

                <details class="rounded-md bg-gray-50 p-4 ring-1 ring-inset ring-gray-200">
                    <summary class="cursor-pointer text-sm font-semibold text-gray-900">Raw payloads</summary>
                    <div class="mt-4 grid gap-4 lg:grid-cols-3">
                        <div>
                            <h5 class="text-xs font-semibold uppercase text-gray-500">Old</h5>
                            <pre class="mt-2 max-h-72 overflow-auto whitespace-pre-wrap rounded bg-white p-3 text-xs text-gray-700 ring-1 ring-inset ring-gray-200">{{ details.item.raw.old || "" }}</pre>
                        </div>
                        <div>
                            <h5 class="text-xs font-semibold uppercase text-gray-500">New</h5>
                            <pre class="mt-2 max-h-72 overflow-auto whitespace-pre-wrap rounded bg-white p-3 text-xs text-gray-700 ring-1 ring-inset ring-gray-200">{{ details.item.raw.new || "" }}</pre>
                        </div>
                        <div>
                            <h5 class="text-xs font-semibold uppercase text-gray-500">Result</h5>
                            <pre class="mt-2 max-h-72 overflow-auto whitespace-pre-wrap rounded bg-white p-3 text-xs text-gray-700 ring-1 ring-inset ring-gray-200">{{ details.item.raw.result || "" }}</pre>
                        </div>
                    </div>
                </details>
            </div>
        </template>
    </AddEditItemModal>

    <ConfirmationModal
        :show="undoConfirmation.show"
        header="Undo Transaction"
        text="Restore the old values saved with this transaction?"
        confirm-button-label="Undo"
        cancel-button-label="Cancel"
        :loading="undoConfirmation.loading"
        color="indigo"
        @close="closeUndoConfirmation"
        @confirm="executeUndo"
    />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages" @update:show="notificationShow = $event" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import moment from "moment-timezone";
import MainLayout from "../Layouts/MainLayout.vue";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import Loading from "./components/general/Loading.vue";
import Badge from "./components/general/Badge.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Notification from "./components/notifications/Notification.vue";
import {
    ArrowUturnLeftIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    EyeIcon,
    MagnifyingGlassIcon,
} from "@heroicons/vue/24/solid";

const props = defineProps({
    routes: Object,
    permissions: Object,
    users: {
        type: Array,
        default: () => [],
    },
    timezone: String,
});

const routes = props.routes;
const permissions = props.permissions || {};
const users = props.users || [];

const loading = ref(false);
const currentPage = ref(1);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);

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
    user_uuid: "",
});

const sortData = ref({
    name: "transaction_date",
    order: "desc",
});

const details = ref({
    show: false,
    loading: false,
    item: null,
});

const undoConfirmation = ref({
    show: false,
    loading: false,
});

const detailsHeader = computed(() => {
    if (!details.value.item) {
        return "Database Transaction";
    }

    return `Database Transaction - ${details.value.item.app_name || "Unknown App"}`;
});

onMounted(() => {
    fetchData();
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

    if (filterData.value.user_uuid) {
        filters.user_uuid = filterData.value.user_uuid;
    }

    return filters;
};

const resetFilters = () => {
    filterData.value.search = null;
    filterData.value.user_uuid = "";
    fetchData(1);
};

const openDetails = (uuid) => {
    details.value.show = true;
    details.value.loading = true;
    details.value.item = null;

    axios.get(transactionRoute(routes.show, uuid))
        .then((response) => {
            details.value.item = response.data.item;
        })
        .catch((error) => {
            closeDetails();
            handleError(error);
        })
        .finally(() => {
            details.value.loading = false;
        });
};

const closeDetails = () => {
    details.value.show = false;
    details.value.loading = false;
    details.value.item = null;
};

const confirmUndo = () => {
    undoConfirmation.value.show = true;
};

const closeUndoConfirmation = () => {
    undoConfirmation.value.show = false;
    undoConfirmation.value.loading = false;
};

const executeUndo = () => {
    if (!details.value.item) {
        closeUndoConfirmation();
        return;
    }

    undoConfirmation.value.loading = true;

    axios.post(transactionRoute(routes.undo, details.value.item.database_transaction_uuid))
        .then((response) => {
            closeUndoConfirmation();
            showNotification("success", response.data.messages);
            fetchData(currentPage.value);
            openDetails(details.value.item.database_transaction_uuid);
        })
        .catch((error) => {
            closeUndoConfirmation();
            handleError(error);
        });
};

const transactionRoute = (template, uuid) => template.replace("__TRANSACTION__", uuid);

const formatDate = (value) => {
    if (!value) {
        return "No date";
    }

    return moment.utc(value).tz(props.timezone || moment.tz.guess()).format("YYYY-MM-DD HH:mm:ss");
};

const typeBadge = (type) => {
    const normalized = String(type || "unknown").toLowerCase();
    const colors = {
        add: {
            backgroundColor: "bg-green-50",
            textColor: "text-green-700",
            ringColor: "ring-green-600/20",
        },
        update: {
            backgroundColor: "bg-blue-50",
            textColor: "text-blue-700",
            ringColor: "ring-blue-600/20",
        },
        delete: {
            backgroundColor: "bg-red-50",
            textColor: "text-red-700",
            ringColor: "ring-red-600/20",
        },
        select: {
            backgroundColor: "bg-gray-50",
            textColor: "text-gray-700",
            ringColor: "ring-gray-600/20",
        },
    };

    return colors[normalized] || {
        backgroundColor: "bg-gray-50",
        textColor: "text-gray-700",
        ringColor: "ring-gray-600/20",
    };
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
</script>
