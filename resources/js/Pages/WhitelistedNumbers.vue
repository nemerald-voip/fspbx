<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>{{ $t('Whitelisted Numbers') }}</template>

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
                <button type="button" @click.prevent="handleCreateButtonClick()"
                    class="inline-flex items-center gap-x-1.5  rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    <PlusIcon aria-hidden="true" class="h-5 w-5" className="-ml-0.5 size-5" />
                    {{ $t('Add') }}
                </button>

            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>
            <template #table-header>
                <TableColumnHeader header=""
                    class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest"
                        :has-selected-items="selectedItems.length > 0" />
                    <span class="pl-4">{{ $t('Number') }}</span>
                </TableColumnHeader>

                <TableColumnHeader :header="$t('Description')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <TableColumnHeader :header="$t('Created')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <TableColumnHeader header="" class="px-2 py-3.5 text-left  text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="8">
                    <div class="text-sm text-center m-2">
                        {{ $t(':count items are selected.', { count: selectedItems.length }) }}
                        <button v-if="!selectAll && selectedItems.length != data.total"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200  hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleSelectAll">
                            {{ $t('Select all :total items', { total: data.total }) }}
                        </button>
                        <button v-if="selectAll"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200  hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleClearSelection">
                            {{ $t('Clear selection') }}
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 " :text="row.number">
                        <div class="flex items-center">
                            <input v-if="row.uuid" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-9">
                                {{ row.number }}
                            </div>

                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.description" />

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.created_at_formatted" />

                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center justify-end whitespace-nowrap">

                                <TrashIcon @click="handleSingleItemDeleteRequest(row.destroy_route)"
                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                            </div>
                        </template>
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


    <AddEditItemModal :show="showCreateModal" :header="$t('Add new number to whitelist')" :loading="loadingModal"
        @close="handleModalClose">
        <template #modal-body>
            <CreateNewWhitelistNumberForm :errors="formErrors" :is-submitting="createFormSubmiting"
                @submit="handleCreateRequest" @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>

    <!-- <ConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmDeleteAction" :header="'Are you sure?'" :text="'Confirm unblocking selected IP addreses.'"
        :confirm-button-label="'Unblock'" cancel-button-label="Cancel" :loading="confirmationModalLoading" /> -->

    <DeleteConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmDeleteAction" />

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
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import { PlusIcon } from "@heroicons/vue/24/outline";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import DeleteConfirmationModal from "./components/modal/DeleteConfirmationModal.vue";
import CreateNewWhitelistNumberForm from "./components/forms/CreateNewWhitelistNumberForm.vue";
import Loading from "./components/general/Loading.vue";
import { TrashIcon } from "@heroicons/vue/24/solid";
import { MagnifyingGlassIcon, } from "@heroicons/vue/24/solid";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import Notification from "./components/notifications/Notification.vue";

const loading = ref(false)
const loadingModal = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const showCreateModal = ref(false);
const confirmationModalTrigger = ref(false);
const confirmationModalLoading = ref(false);
const createFormSubmiting = ref(null);
const confirmDeleteAction = ref(null);
const formErrors = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);

const props = defineProps({
    pagination: Object,
    routes: Object,
    // itemData: Object,
    // itemOptions: Object,
});


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

const sortData = ref({ name: 'number', order: 'asc' });

const filterData = ref({
    search: null,
});


// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [
        {
            id: 'bulk_delete',
            label: trans('Delete'),
            icon: 'TrashIcon'
        },

    ];

    return actions;
});



const handleCreateRequest = (form) => {
    createFormSubmiting.value = true;
    formErrors.value = null;

    axios.post(props.routes.store, form)
        .then((response) => {
            createFormSubmiting.value = false;
            showNotification('success', response.data.messages);
            refreshData();
            handleModalClose();
            handleClearSelection();
        }).catch((error) => {
            createFormSubmiting.value = false;
            handleClearSelection();
            handleFormErrorResponse(error);
        });

};


const handleSingleItemDeleteRequest = (url) => {
    confirmationModalTrigger.value = true;
    confirmDeleteAction.value = () => executeSingleDelete(url);
}

const executeSingleDelete = (url) => {
    axios.delete(url)
        .then((response) => {
            handleModalClose();
            showNotification('success', response.data.messages);
            refreshData();
        }).catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        });
};

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        confirmationModalTrigger.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }
}


const executeBulkDelete = () => {
    axios.post(props.routes.bulk_delete, { items: selectedItems.value })
        .then((response) => {
            handleModalClose();
            showNotification('success', response.data.messages);
            refreshData();
        })
        .catch((error) => {
            handleClearSelection();
            handleModalClose();
            handleErrorResponse(error);
        });
}


const handleCreateButtonClick = () => {
    showCreateModal.value = true
    formErrors.value = null;
}

const handleSelectAll = () => {
    const sequence = requestSequence;
    axios.post(props.routes.select_all, { filter: { ...filterData.value } })
        .then((response) => {
            if (isUnmounted || sequence !== requestSequence) return;
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification('success', response.data.messages);

        }).catch((error) => {
            if (isUnmounted || sequence !== requestSequence) return;
            handleClearSelection();
            handleErrorResponse(error);
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
    handleClearSelection();
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
        handleClearSelection();
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


const handleFormErrorResponse = (error) => {
    if (error.request?.status == 419) {
        showNotification('error', { request: [trans('Session expired. Reload the page')] });
    } else if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || error.response.data.messages || { request: [error.response.data.message || error.message] });
        formErrors.value = error.response.data.errors;
    } else if (error.request) {
        // The request was made but no response was received
        // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
        // http.ClientRequest in node.js
        showNotification('error', { request: [error.request] });
        console.log(error.request);
    } else {
        // Something happened in setting up the request that triggered an Error
        showNotification('error', { request: [error.message] });
        console.log(error.message);
    }

}

const handleErrorResponse = (error) => {
    if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || error.response.data.messages || { request: [error.response.data.message || error.message] });
    } else if (error.request) {
        // The request was made but no response was received
        // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
        // http.ClientRequest in node.js
        showNotification('error', { request: [error.request] });
        console.log(error.request);
    } else {
        // Something happened in setting up the request that triggered an Error
        showNotification('error', { request: [error.message] });
        console.log(error.message);
    }
}

const handleSelectPageItems = () => {
    if (selectPageItems.value) {
        selectedItems.value = data.value.data.map(item => item.uuid);
    } else {
        selectedItems.value = [];
    }
};



const handleClearSelection = () => {
    selectedItems.value = [],
        selectPageItems.value = false;
    selectAll.value = false;
}

const handleModalClose = () => {
    showCreateModal.value = false;
    confirmationModalTrigger.value = false;
}

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
}

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
}

onMounted(() => getData());
onUnmounted(() => {
    isUnmounted = true;
    activeRequest?.abort();
});
</script>
