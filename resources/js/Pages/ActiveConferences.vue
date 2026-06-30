<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Active Conferences</template>

            <template #subtitle>
                View conference rooms with active participants.
            </template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-subtle" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-active-conferences"
                        id="mobile-search-active-conferences"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:hidden"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                    <input type="text" v-model="filterData.search" name="desktop-search-active-conferences"
                        id="desktop-search-active-conferences"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:block"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <button :class="[
                    isRefreshing
                        ? 'rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent'
                        : 'rounded-md bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2'
                ]" title="Auto refresh" @click="toggleRefreshing">
                    <Refresh class="h-5 w-5" :class="{ 'animate-spin': isRefreshing }" />
                </button>

                <button type="button" @click.prevent="handleRefreshButtonClick"
                    class="ml-2 rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                    Refresh
                </button>

                <a :href="routes.conference_centers"
                    class="ml-2 sm:ml-4 rounded-md bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    Conference Centers
                </a>

                <a :href="routes.conference_rooms"
                    class="ml-2 sm:ml-4 rounded-md bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    Conference Rooms
                </a>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>

            <template #table-header>
                <TableColumnHeader class="px-4 py-3.5 text-left text-sm font-semibold text-heading">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('name')">
                        <span class="mr-2">Name</span>
                        <ChevronUpIcon v-if="sortData.name === 'name' && sortData.order === 'asc'" class="h-4 w-4 text-muted" />
                        <ChevronDownIcon v-else-if="sortData.name === 'name' && sortData.order === 'desc'" class="h-4 w-4 text-muted" />
                    </div>
                </TableColumnHeader>

                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-heading">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('extension')">
                        <span class="mr-2">Extension</span>
                        <ChevronUpIcon v-if="sortData.name === 'extension' && sortData.order === 'asc'" class="h-4 w-4 text-muted" />
                        <ChevronDownIcon v-else-if="sortData.name === 'extension' && sortData.order === 'desc'" class="h-4 w-4 text-muted" />
                    </div>
                </TableColumnHeader>

                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-heading">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('participant_pin')">
                        <span class="mr-2">Participant PIN</span>
                        <ChevronUpIcon v-if="sortData.name === 'participant_pin' && sortData.order === 'asc'" class="h-4 w-4 text-muted" />
                        <ChevronDownIcon v-else-if="sortData.name === 'participant_pin' && sortData.order === 'desc'" class="h-4 w-4 text-muted" />
                    </div>
                </TableColumnHeader>

                <TableColumnHeader class="w-32 px-2 py-3.5 text-center text-sm font-semibold text-heading [&>div]:justify-center">
                    <div class="flex items-center justify-center cursor-pointer select-none" @click="handleSortRequest('member_count')">
                        <span class="mr-2">Members</span>
                        <ChevronUpIcon v-if="sortData.name === 'member_count' && sortData.order === 'asc'" class="h-4 w-4 text-muted" />
                        <ChevronDownIcon v-else-if="sortData.name === 'member_count' && sortData.order === 'desc'" class="h-4 w-4 text-muted" />
                    </div>
                </TableColumnHeader>

                <TableColumnHeader header="Action" class="w-20 px-2 py-3.5 text-right text-sm font-semibold text-heading [&>div]:justify-end" />
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.full_name">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm font-medium text-heading">
                        <a v-if="permissions.interactive_view" :href="toolUrl(routes.interactive, row.identifier)"
                            class="text-info hover:text-info">
                            {{ row.name || row.identifier }}
                        </a>
                        <span v-else>{{ row.name || row.identifier }}</span>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted" :text="row.extension || '-'" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted" :text="formatPin(row.participant_pin)" />
                    <TableField class="w-32 whitespace-nowrap px-2 py-2 text-center text-sm text-muted" :text="row.member_count" />
                    <TableField class="w-20 whitespace-nowrap px-2 py-1 text-sm text-muted">
                        <template #action-buttons>
                            <div class="flex items-center justify-end whitespace-nowrap">
                                <EyeIcon v-if="permissions.interactive_view" @click="goToInteractive(row.identifier)"
                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer" />
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="!loading && data.data.length === 0" class="text-center my-5">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-subtle" />
                    <h3 class="mt-2 text-sm font-semibold text-heading">No active conferences found</h3>
                    <p class="mt-1 text-sm text-muted">
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
import { ChevronDownIcon, ChevronUpIcon, EyeIcon, MagnifyingGlassIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    routes: Object,
    permissions: Object,
});

const routes = props.routes;
const permissions = props.permissions;

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
});

const filterData = ref({
    search: null,
});

const sortData = ref({
    name: "name",
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

    return axios.get(routes.data_route, {
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

const handleSearchButtonClick = () => {
    getData(1);
};

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
    const pageParam = urlObj.searchParams.get("page") ?? 1;
    getData(pageParam);
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

const formatPin = (pin) => {
    const value = String(pin || "");
    if (value.length === 9) {
        return `${value.slice(0, 3)}-${value.slice(3, 6)}-${value.slice(6)}`;
    }

    return value || "-";
};

const toolUrl = (template, uuid) => template.replace(":uuid", encodeURIComponent(uuid));

const goToInteractive = (uuid) => {
    window.location.href = toolUrl(routes.interactive, uuid);
};
</script>
