<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="getData(1)" @reset-filters="resetFilters">
            <template #title>{{ $t('Dynamic Routes') }}</template>
            <template #subtitle>
                {{ $t('Route calls to different destinations based on the number that was originally dialed.') }}
            </template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
                    </div>
                    <input v-model="filterData.search" type="text"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                        :placeholder="$t('Search')" @keydown.enter="getData(1)" />
                </div>
            </template>

            <template #action>
                <button v-if="permissions.create" type="button" @click="openCreate"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    {{ $t('Create') }}
                </button>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>

            <template #table-header>
                <TableColumnHeader class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex cursor-pointer items-center" @click="sortBy('name')">
                        <span class="mr-2">{{ $t('Name') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'name' && sortData.order === 'asc'" class="h-4 w-4" />
                        <ChevronDownIcon v-else-if="sortData.name === 'name'" class="h-4 w-4" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader :header="$t('Extension')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader :header="$t('Source')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader :header="$t('Matches')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader :header="$t('Enabled')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader :header="$t('Description')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.dynamic_route_uuid">
                    <TableField class="px-4 py-2 text-sm text-gray-500">
                        <button type="button" :disabled="!permissions.update"
                            :class="permissions.update ? 'rounded-sm hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2' : 'cursor-default'"
                            @click="permissions.update && openEdit(row.dynamic_route_uuid)">
                            {{ row.name }}
                        </button>
                    </TableField>
                    <TableField class="px-2 py-2 text-sm text-gray-500" :text="row.extension" />
                    <TableField class="px-2 py-2 text-sm text-gray-500" :text="$t('Original DID')" />
                    <TableField class="px-2 py-2 text-sm text-gray-500" :text="row.rules_count" />
                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        <button v-if="permissions.update" type="button" @click="toggle(row.dynamic_route_uuid)">
                            <Badge :text="row.enabled ? $t('True') : $t('False')" v-bind="enabledBadgeProps(row.enabled)" />
                        </button>
                        <Badge v-else :text="row.enabled ? $t('True') : $t('False')" v-bind="enabledBadgeProps(row.enabled)" />
                    </TableField>
                    <TableField class="px-2 py-2 text-sm text-gray-500" :text="row.description" />
                    <TableField class="px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center justify-end">
                                <button v-if="permissions.update" type="button" @click="openEdit(row.dynamic_route_uuid)"
                                    class="rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    <span class="sr-only">{{ $t('Edit') }}</span>
                                    <PencilSquareIcon class="h-9 w-9 py-2" aria-hidden="true" />
                                </button>
                                <button v-if="permissions.destroy" type="button" @click="requestDelete(row.dynamic_route_uuid)"
                                    class="rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    <span class="sr-only">{{ $t('Delete') }}</span>
                                    <TrashIcon class="h-9 w-9 py-2" aria-hidden="true" />
                                </button>
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="data.data.length === 0" class="my-5 text-center">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ $t('No results found') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $t('Create a route to map original phone numbers to call destinations.') }}</p>
                </div>
            </template>

            <template #loading><Loading :show="loading" /></template>

            <template #footer>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>
        </DataTable>
    </div>

    <ConfirmationModal :show="showDelete" @close="showDelete = false" @confirm="confirmDelete"
        :header="$t('Delete Dynamic Route')" :text="$t('This deletes its match rules and generated dialplan.')"
        :confirm-button-label="$t('Delete')" :cancel-button-label="$t('Cancel')" />

    <DynamicRouteForm :show="showForm" :options="itemOptions" :mode="formMode" :loading="loadingForm"
        :header="formHeader" @close="showForm = false" @error="handleError" @success="showNotification"
        @refresh-data="getData(currentPage)" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="notificationShow = $event" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import MainLayout from '../Layouts/MainLayout.vue';
import DataTable from './components/general/DataTable.vue';
import TableColumnHeader from './components/general/TableColumnHeader.vue';
import TableField from './components/general/TableField.vue';
import Paginator from './components/general/Paginator.vue';
import Loading from './components/general/Loading.vue';
import Badge from '@generalComponents/Badge.vue';
import ConfirmationModal from './components/modal/ConfirmationModal.vue';
import Notification from './components/notifications/Notification.vue';
import DynamicRouteForm from './components/forms/DynamicRouteForm.vue';
import { ChevronDownIcon, ChevronUpIcon, MagnifyingGlassIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { trans } from '@i18n';

const props = defineProps({ routes: Object, permissions: Object, pagination: Object });
const loading = ref(false);
const loadingForm = ref(false);
const showForm = ref(false);
const showDelete = ref(false);
const deleteUuid = ref(null);
const formMode = ref('create');
const currentPage = ref(1);
const itemOptions = ref({ item: {}, routes: {} });
const filterData = ref({ search: null });
const sortData = ref({ name: 'name', order: 'asc' });
const notificationShow = ref(false);
const notificationType = ref(null);
const notificationMessages = ref(null);
const data = ref({ data: [], links: [], current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });

const formHeader = computed(() => formMode.value === 'create'
    ? trans('Create Dynamic Route')
    : trans('Update Dynamic Route - :name', { name: itemOptions.value?.item?.name || trans('Loading...') }));

onMounted(() => getData());

const getData = async (page = 1) => {
    loading.value = true;
    currentPage.value = Number(page) || 1;
    try {
        const sort = sortData.value.order === 'desc' ? `-${sortData.value.name}` : sortData.value.name;
        const response = await axios.get(props.routes.data_route, {
            params: { filter: filterData.value, page: currentPage.value, sort },
        });
        data.value = response.data;
        currentPage.value = response.data.current_page;
    } catch (error) {
        handleError(error);
    } finally {
        loading.value = false;
    }
};

const sortBy = (column) => {
    sortData.value.order = sortData.value.name === column && sortData.value.order === 'asc' ? 'desc' : 'asc';
    sortData.value.name = column;
    getData(currentPage.value);
};

const resetFilters = () => {
    filterData.value.search = null;
    getData(1);
};

const renderRequestedPage = (url) => {
    if (!url) return;
    getData(new URL(url, window.location.origin).searchParams.get('page') || 1);
};

const loadForm = async (uuid = null) => {
    showForm.value = true;
    loadingForm.value = true;
    try {
        const response = await axios.post(props.routes.item_options, uuid ? { itemUuid: uuid } : {});
        itemOptions.value = response.data;
    } catch (error) {
        showForm.value = false;
        handleError(error);
    } finally {
        loadingForm.value = false;
    }
};

const openCreate = () => { formMode.value = 'create'; loadForm(); };
const openEdit = (uuid) => { formMode.value = 'update'; loadForm(uuid); };
const requestDelete = (uuid) => { deleteUuid.value = uuid; showDelete.value = true; };

const confirmDelete = async () => {
    showDelete.value = false;
    try {
        const response = await axios.post(props.routes.bulk_delete, { items: [deleteUuid.value] });
        showNotification('success', response.data.messages);
        getData(currentPage.value);
    } catch (error) { handleError(error); }
};

const toggle = async (uuid) => {
    try {
        const response = await axios.post(props.routes.bulk_toggle, { items: [uuid] });
        showNotification('success', response.data.messages);
        getData(currentPage.value);
    } catch (error) { handleError(error); }
};

const showNotification = (type, messages) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
};

const handleError = (error) => showNotification('error', error?.response?.data?.messages
    || error?.response?.data?.errors || { error: [trans('An error occurred.')] });

const enabledBadgeProps = (enabled) => enabled
    ? { backgroundColor: 'bg-green-50', textColor: 'text-green-700', ringColor: 'ring-green-600/20' }
    : { backgroundColor: 'bg-gray-50', textColor: 'text-gray-600', ringColor: 'ring-gray-500/20' };
</script>
