<template>
    <MainLayout />

    <div class="m-3">
        <div v-if="data.runtime_available === false"
            class="mb-3 rounded-md bg-amber-50 p-3 text-sm font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20">
            FreeSWITCH event socket is not available. Queue calls cannot be loaded right now.
        </div>

        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Active Basic Queues</template>

            <template #subtitle>
                View live caller activity across basic queues.
            </template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="active-basic-queues-search"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <button type="button" :class="[
                    isRefreshing
                        ? 'rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
                        : 'rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50'
                ]" title="Auto refresh" @click="toggleRefreshing">
                    <Refresh class="h-5 w-5" :class="{ 'animate-spin': isRefreshing }" />
                </button>

                <button type="button" @click.prevent="handleRefreshButtonClick"
                    class="ml-2 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Refresh
                </button>

                <a :href="routes.basic_queues"
                    class="ml-2 sm:ml-4 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Basic Queues
                </a>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>

            <template #table-header>
                <TableColumnHeader class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('queue_name')">
                        <span class="mr-2">Name</span>
                        <ChevronUpIcon v-if="sortData.name === 'queue_name' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'queue_name' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>

                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('queue_extension')">
                        <span class="mr-2">Extension</span>
                        <ChevronUpIcon v-if="sortData.name === 'queue_extension' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'queue_extension' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>

                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('queue_strategy')">
                        <span class="mr-2">Strategy</span>
                        <ChevronUpIcon v-if="sortData.name === 'queue_strategy' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'queue_strategy' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>

                <TableColumnHeader header="Agents" class="w-24 px-2 py-3.5 text-center text-sm font-semibold text-gray-900 [&>div]:justify-center" />
                <TableColumnHeader header="Waiting" class="w-24 px-2 py-3.5 text-center text-sm font-semibold text-gray-900 [&>div]:justify-center" />
                <TableColumnHeader header="Trying" class="w-24 px-2 py-3.5 text-center text-sm font-semibold text-gray-900 [&>div]:justify-center" />
                <TableColumnHeader header="Answered" class="w-24 px-2 py-3.5 text-center text-sm font-semibold text-gray-900 [&>div]:justify-center" />
                <TableColumnHeader header="Active Calls" class="w-28 px-2 py-3.5 text-center text-sm font-semibold text-gray-900 [&>div]:justify-center" />
                <TableColumnHeader header="Description" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
            </template>

            <template #table-body>
                <template v-for="row in data.data" :key="row.call_center_queue_uuid">
                <tr>
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm font-medium text-gray-900">
                        {{ row.queue_name }}
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.queue_extension || '-'" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="formatStrategy(row.queue_strategy)" />
                    <TableField class="w-24 whitespace-nowrap px-2 py-2 text-center text-sm text-gray-500" :text="String(row.agents_count ?? 0)" />
                    <TableField class="w-24 whitespace-nowrap px-2 py-2 text-center text-sm text-gray-500" :text="String(row.waiting_count ?? 0)" />
                    <TableField class="w-24 whitespace-nowrap px-2 py-2 text-center text-sm text-gray-500" :text="String(row.trying_count ?? 0)" />
                    <TableField class="w-24 whitespace-nowrap px-2 py-2 text-center text-sm text-gray-500" :text="String(row.answered_count ?? 0)" />
                    <TableField class="w-28 whitespace-nowrap px-2 py-2 text-center text-sm text-gray-500">
                        <span :class="[
                            (row.calls?.length ?? 0) > 0
                                ? 'inline-flex rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20'
                                : 'inline-flex rounded-full bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-600/20'
                        ]">
                            {{ row.calls?.length ?? 0 }}
                        </span>
                    </TableField>
                    <TableField class="px-2 py-2 text-sm text-gray-500" :text="row.queue_description || '-'" />
                </tr>
                <tr v-if="row.calls?.length">
                    <td colspan="9" class="bg-gray-50 px-6 py-4">
                        <div class="overflow-hidden rounded-md ring-1 ring-gray-200">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-white">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Time</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Caller</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Number</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Agent</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="call in row.calls" :key="call.session_uuid || call.uuid">
                                        <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-500">{{ call.wait_time || '-' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-sm font-medium text-gray-900">{{ call.caller_name || '-' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-500">{{ call.caller_number || '-' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-500">{{ call.state || '-' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-500">{{ call.serving_agent_name || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
                </template>
            </template>

            <template #empty>
                <div v-if="!loading && data.data.length === 0" class="text-center my-5">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">No basic queues found</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Refresh the list or adjust your search.
                    </p>
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

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { onMounted, onUnmounted, ref } from "vue";
import axios from "axios";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import Loading from "./components/general/Loading.vue";
import Notification from "./components/notifications/Notification.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import Refresh from "./components/icons/Refresh.vue";
import { ChevronDownIcon, ChevronUpIcon, MagnifyingGlassIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    routes: Object,
});

const routes = props.routes;
const loading = ref(false);
const currentPage = ref(1);
const isRefreshing = ref(false);
const refreshTimeoutId = ref(null);
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
    runtime_available: null,
});

const filterData = ref({
    search: null,
});

const sortData = ref({
    name: "queue_name",
    order: "asc",
});

onMounted(() => {
    getData();
});

onUnmounted(() => {
    stopRefreshing();
});

const getData = (page = 1, showLoading = true) => {
    if (showLoading) {
        loading.value = true;
    }

    currentPage.value = Number(page) || 1;

    let sort = sortData.value.name;
    if (sortData.value.order === "desc") {
        sort = `-${sort}`;
    }

    return axios.get(routes.data, {
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
            if (showLoading) {
                loading.value = false;
            }
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

const handleRefreshButtonClick = () => {
    getData(currentPage.value, false);
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    getData(1);
};

const renderRequestedPage = (url) => {
    if (!url) return;

    const urlObj = new URL(url, window.location.origin);
    getData(urlObj.searchParams.get("page") ?? 1);
};

const toggleRefreshing = () => {
    isRefreshing.value = !isRefreshing.value;

    if (isRefreshing.value) {
        handleAutoRefresh();
        return;
    }

    stopRefreshing();
};

const handleAutoRefresh = () => {
    if (!isRefreshing.value) return;

    getData(currentPage.value, false)
        .finally(() => {
            if (isRefreshing.value) {
                refreshTimeoutId.value = setTimeout(handleAutoRefresh, 5000);
            }
        });
};

const stopRefreshing = () => {
    isRefreshing.value = false;

    if (refreshTimeoutId.value) {
        clearTimeout(refreshTimeoutId.value);
        refreshTimeoutId.value = null;
    }
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

const formatStrategy = (strategy) => String(strategy || "-")
    .replaceAll("-", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
</script>
