<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>{{ $t('Activity Log') }}</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-candidate"
                        id="mobile-search-candidate"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        :placeholder="$t('Search')" @keydown.enter="handleSearchButtonClick" />
                    <input type="text" v-model="filterData.search" name="desktop-search-candidate"
                        id="desktop-search-candidate"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        :placeholder="$t('Search')" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <button v-if="!showGlobal && props.permissions.view_global" type="button"
                    @click.prevent="handleShowGlobal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    {{ $t('Show global') }}
                </button>

                <button v-if="showGlobal && props.permissions.view_global" type="button"
                    @click.prevent="handleShowLocal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    {{ $t('Show local') }}
                </button>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>
            <template #table-header>


                <TableColumnHeader :header="$t('Date of change')" class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <TableColumnHeader :header="$t('Log Name')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <TableColumnHeader v-if="showGlobal" :header="$t('Domain')"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <TableColumnHeader :header="$t('User')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <TableColumnHeader :header="$t('Event')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader :header="$t('Properties')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.id">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500" :text="row.created_at_formatted" />

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.log_name" />

                    <TableField v-if="showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.domain?.domain_description">
                        <ejs-tooltip :content="row.domain?.domain_name" position='TopLeft' target="#domain_tooltip_target">
                            <div id="domain_tooltip_target">
                                {{ row.domain?.domain_description }}
                            </div>
                        </ejs-tooltip>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            <div class="font-medium text-gray-900">{{ formatUserName(row.causer) }}</div>
                            <div class="mt-1 text-gray-500">{{ row.causer?.user_email }}</div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="eventLabel(row.description)" />
                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        <template v-if="row.subject_type && row.subject_id">
                            <div>{{ $t('Subject Type: :type', { type: row.subject_type }) }}</div>
                            <div>{{ $t('Subject ID: :id', { id: row.subject_id }) }}</div>
                        </template>
                        <div v-for="change in propertyChanges(row)" :key="change.field">
                            {{ $t('Changed :field from :old to :new.', change) }}
                        </div>
                    </TableField>
                </tr>
            </template>
            <template #empty>
                <!-- Conditional rendering for 'no records' message -->
                <div v-if="!loading && data.data.length === 0" class="text-center my-5 ">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ $t('No results found') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $t('Adjust your search and try again.') }}
                    </p>
                </div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>

            <template #footer>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :page-size="perPage"
                    :page-size-options="props.pagination?.per_page_options ?? []"
                    :show-page-size-selector="true" @page-size-change="handlePageSizeChange" />
            </template>
        </DataTable>
        <div class="px-4 sm:px-6 lg:px-8"></div>
    </div>


    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { trans } from "@i18n";
import { computed, onMounted, onUnmounted, ref } from "vue";
import axios from 'axios';
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import MainLayout from "../Layouts/MainLayout.vue";
import Notification from "./components/notifications/Notification.vue";

const props = defineProps({ routes: Object, pagination: Object, permissions: Object });
const loading = ref(false);
const perPage = ref(props.pagination?.per_page ?? 50);


const data = ref({
    data: [],
    prev_page_url: null,
    next_page_url: null,
    from: null,
    to: null,
    total: 0,
    current_page: 1,
    last_page: 1,
    links: [],
});
const currentPage = ref(1);
let activeRequest = null;
let requestSequence = 0;
let isUnmounted = false;

const filterData = ref({ search: null, showGlobal: false });
const showGlobal = computed(() => filterData.value.showGlobal);
const sortData = ref({ name: 'created_at', order: 'desc' });

const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);
const showNotification = (type, messages) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
};
const hideNotification = () => { notificationShow.value = false; };
const handleErrorResponse = (error) => {
    showNotification('error', error.response?.data?.errors || error.response?.data?.messages || {
        request: [error.response?.data?.message || error.message || trans('Request failed')],
    });
};
const handleSortRequest = (column) => {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === 'asc' ? 'desc' : 'asc';
    } else {
        sortData.value.name = column;
        sortData.value.order = 'asc';
    }

    handleSearchButtonClick();
};



const getData = async (page = currentPage.value, { background = false } = {}) => {
    if (isUnmounted) return;

    activeRequest?.abort();
    const controller = new AbortController();
    activeRequest = controller;
    const sequence = ++requestSequence;
    loading.value = !background;
    currentPage.value = Number(page) || 1;

    const sort = sortData.value.order === 'desc' ? `-${sortData.value.name}` : sortData.value.name;

    try {
        const response = await axios.get(props.routes.data_route, {
            params: {
                filter: { ...filterData.value },
                page: currentPage.value,
                per_page: perPage.value,
                sort,
            },
            signal: controller.signal,
        });

        if (isUnmounted || sequence !== requestSequence) return;

        // A deletion may have removed the last row on this page.
        if (response.data.last_page && currentPage.value > response.data.last_page) {
            return await getData(response.data.last_page, { background });
        }

        data.value = response.data;
        currentPage.value = response.data.current_page ?? currentPage.value;
    } catch (error) {
        if (!isUnmounted && sequence === requestSequence && !axios.isCancel(error)) {
            handleErrorResponse(error);
        }
    } finally {
        if (!isUnmounted && sequence === requestSequence) {
            activeRequest = null;
            loading.value = false;
        }
    }
};

const handleSearchButtonClick = () => {
    getData(1);
};

const refreshData = () => {
    getData(currentPage.value);
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    handleSearchButtonClick();
}


const handlePageSizeChange = (newPerPage) => {
    perPage.value = newPerPage;
    handleSearchButtonClick();
};

const renderRequestedPage = (url) => {
    if (!url) return;

    const urlObj = new URL(url, window.location.origin);
    getData(urlObj.searchParams.get('page') ?? 1);
};


const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    data.value.data = [];
    handleSearchButtonClick();
};
const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    data.value.data = [];
    handleSearchButtonClick();
};
const formatUserName = (causer) => causer?.name_formatted || causer?.username || '';
const eventLabel = (event) => ({
    created: trans('Created'), updated: trans('Updated'), deleted: trans('Deleted'), restored: trans('Restored'),
})[event] || event;
const propertyValue = (value) => {
    if (value === null || value === undefined || value === '') return trans('Empty');
    return typeof value === 'object' ? JSON.stringify(value) : String(value);
};
const propertyChanges = (item) => {
    if (item.description !== 'updated' || !item.properties?.attributes || !item.properties?.old) return [];
    return Object.entries(item.properties.attributes)
        .filter(([key, value]) => JSON.stringify(item.properties.old[key]) !== JSON.stringify(value))
        .map(([key, value]) => ({ field: key.replace(/_/g, ' '), old: propertyValue(item.properties.old[key]), new: propertyValue(value) }));
};

onMounted(() => getData());
onUnmounted(() => {
    isUnmounted = true;
    activeRequest?.abort();
});
registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>
