<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>{{ $t('Reports') }}</template>

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

            </template>

            <template #table-header>
                <TableColumnHeader :header="$t('Report Name')" class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader :header="$t('Action')"
                    class="flex justify-end px-4 py-3.5 text-left text-sm font-semibold text-gray-900" />
            </template>

            <template #table-body>
                <tr v-for="row in data" :key="row.id">

                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500" :text="row.reportName" />



                    <TableField class="whitespace-nowrap px-4 py-4 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex justify-end items-center whitespace-nowrap">
                                <div class="ml-4 flex-shrink-0">
                                    <button :disabled="generatingReport !== null" @click="handleReportRequest(row.id)"
                                        class="font-medium text-blue-600 hover:text-blue-500 disabled:opacity-50">{{ generatingReport === row.id ? $t('Generating...') : $t('Generate') }}</button>
                                </div>
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>
            <template #empty>
                <!-- Conditional rendering for 'no records' message -->
                <div v-if="!loading && data.length === 0" class="text-center my-5 ">
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

        </DataTable>
        <div class="px-4 sm:px-6 lg:px-8"></div>
    </div>



    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { trans } from "@i18n";
import { ref, onMounted, onUnmounted } from "vue";
import axios from 'axios';
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Loading from "./components/general/Loading.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import Notification from "./components/notifications/Notification.vue";
import { MagnifyingGlassIcon } from "@heroicons/vue/24/solid";

const props = defineProps({ routes: Object });
const data = ref([]);
const loading = ref(false);
const filterData = ref({ search: null });
const generatingReport = ref(null);
let activeRequest = null;
let requestSequence = 0;
let isUnmounted = false;

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
const getData = async () => {
    if (isUnmounted) return;
    activeRequest?.abort();
    const controller = new AbortController();
    activeRequest = controller;
    const sequence = ++requestSequence;
    loading.value = true;
    try {
        const response = await axios.get(props.routes.data_route, {
            params: { filter: { ...filterData.value } }, signal: controller.signal,
        });
        if (!isUnmounted && sequence === requestSequence) data.value = response.data;
    } catch (error) {
        if (!isUnmounted && sequence === requestSequence && !axios.isCancel(error)) handleErrorResponse(error);
    } finally {
        if (!isUnmounted && sequence === requestSequence) {
            loading.value = false;
            activeRequest = null;
        }
    }
};
const handleSearchButtonClick = () => getData();
const handleFiltersReset = () => {
    filterData.value.search = null;
    getData();
};
const handleReportRequest = async (reportId) => {
    if (generatingReport.value !== null) return;
    generatingReport.value = reportId;
    try {
        const response = await axios.post(props.routes.generate, { reportId });
        if (!isUnmounted) showNotification('success', response.data.messages);
    } catch (error) {
        if (!isUnmounted) handleErrorResponse(error);
    } finally {
        if (!isUnmounted) generatingReport.value = null;
    }
};

onMounted(() => getData());
onUnmounted(() => {
    isUnmounted = true;
    activeRequest?.abort();
});
</script>
