<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Firewall</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-candidate"
                        id="mobile-search-candidate"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick"/>
                    <input type="text" v-model="filterData.search" name="desktop-search-candidate"
                        id="desktop-search-candidate"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick"/>
                </div>
            </template>

            <template #action>
                <button type="button" @click.prevent="handleCreateButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Block IP
                </button>

            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>
            <template #table-header>
                <TableColumnHeader header=""
                    class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <div class="pl-4 flex items-center cursor-pointer select-none" @click="handleSortRequest('hostname')">
                        <span class="mr-2">Hostname</span>
                        <ChevronUpIcon v-if="sortData.name === 'hostname' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'hostname' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>


                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('ip')">
                        <span class="mr-2">IP Address</span>
                        <ChevronUpIcon v-if="sortData.name === 'ip' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'ip' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('filter')">
                        <span class="mr-2">Filter</span>
                        <ChevronUpIcon v-if="sortData.name === 'filter' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'filter' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('extension')">
                        <span class="mr-2">Extension</span>
                        <ChevronUpIcon v-if="sortData.name === 'extension' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'extension' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('user_agent')">
                        <span class="mr-2">User Agent</span>
                        <ChevronUpIcon v-if="sortData.name === 'user_agent' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'user_agent' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('date')">
                        <span class="mr-2">Date</span>
                        <ChevronUpIcon v-if="sortData.name === 'date' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'date' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('status')">
                        <span class="mr-2">Status</span>
                        <ChevronUpIcon v-if="sortData.name === 'status' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'status' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader header="Action" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="8">
                    <div class="text-sm text-center m-2">
                        <span class="font-semibold ">{{ selectedItems.length }} </span> items are selected.
                        <button v-if="!selectAll && selectedItems.length != data.total"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200  hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleSelectAll">
                            Select all {{ data.total }} items
                        </button>
                        <button v-if="selectAll"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200  hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleClearSelection">
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 " :text="row.hostname">
                        <div class="flex items-center">
                            <input v-if="row.hostname" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.ip" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-9">
                                {{ row.hostname }}
                            </div>

                        </div>
                    </TableField>


                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.ip" />

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.filter" />

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.extension" />

                    <TableField class="truncate max-w-64 px-2 py-2 text-sm text-gray-500" :text="row.user_agent" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.date" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.status">
                        <Badge :text="row.status" :backgroundColor="determineColor(row.status).backgroundColor"
                            :textColor="determineColor(row.status).textColor"
                            :ringColor="determineColor(row.status).ringColor" />

                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap">

                                <!-- <RestartIcon @click="handleRetry(row.message_uuid)"
                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" /> -->


                                    <div class="flex items-center  font-medium text-blue-600 hover:text-blue-500 hover:cursor-pointer"
                                        @click="handleSingleItemDeleteRequest(row.ip)">
                                        Unblock
                                    </div>


                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>
            <template #empty>
                <!-- Conditional rendering for 'no records' message -->
                <div v-if="data.data.length === 0" class="text-center my-5 ">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Adjust your search and try again.
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
        <div class="px-4 sm:px-6 lg:px-8"></div>
    </div>


    <AddEditItemModal :show="createModalTrigger" :header="'Block new IP address'" :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <CreateNewIpBlockForm :errors="formErrors" :is-submitting="createFormSubmiting"
                @submit="handleCreateRequest" @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>

    <ConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmDeleteAction" :header="'Are you sure?'" :text="'Confirm unblocking selected IP addreses.'"
        :confirm-button-label="'Unblock'" cancel-button-label="Cancel" :loading="confirmationModalLoading"/>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from 'axios';
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import CreateNewIpBlockForm from "./components/forms/CreateNewIpBlockForm.vue";
import Loading from "./components/general/Loading.vue";
import Badge from "./components/general/Badge.vue";
import { ChevronDownIcon, ChevronUpIcon, MagnifyingGlassIcon, } from "@heroicons/vue/24/solid";
import MainLayout from "../Layouts/MainLayout.vue";
import Notification from "./components/notifications/Notification.vue";

const loading = ref(false)
const currentPage = ref(1)
const loadingModal = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const createModalTrigger = ref(false);
const confirmationModalTrigger = ref(false);
const confirmationModalLoading = ref(false);
const createFormSubmiting = ref(null);
const confirmDeleteAction = ref(null);
const formErrors = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);

const props = defineProps({
    routes: Object,
});

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
    name: 'ip',
    order: 'asc',
});


// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [
        {
            id: 'bulk_unblock',
            label: 'Unblock',
            icon: 'LinkOffIcon'
        },

    ];

    return actions;
});

onMounted(() => {
    getData();
});

const handleSortRequest = (column) => {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === 'asc' ? 'desc' : 'asc';
    } else {
        sortData.value.name = column;
        sortData.value.order = 'asc';
    }

    getData(currentPage.value);
};

const getData = (page = 1) => {
    loading.value = true;
    currentPage.value = Number(page) || 1;

    let sort = sortData.value.name;
    if (sortData.value.order === 'desc') {
        sort = `-${sort}`;
    }

    axios.get(props.routes.data_route, {
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
            loading.value = false;
        });
};

const handleCreateRequest = (form) => {
    createFormSubmiting.value = true;
    formErrors.value = null;

    axios.post(props.routes.block, form)
        .then((response) => {
            createFormSubmiting.value = false;
            showNotification('success', response.data.messages);
            handleModalClose();
            handleClearSelection();
            getData(1);
        }).catch((error) => {
            createFormSubmiting.value = false;
            handleClearSelection();
            handleFormErrorResponse(error);
        });

};


const handleSingleItemDeleteRequest = (ip) => {
    confirmationModalTrigger.value = true;
    confirmDeleteAction.value = () => executeSingleDelete(ip);
}

const executeSingleDelete = (ip) => {
    confirmationModalLoading.value = true;

    axios.post(props.routes.unblock,
        { 'items': [ip] },
    )
        .then((response) => {
            showNotification('success', response.data.messages);
            handleModalClose();
            handleClearSelection();
            getData(currentPage.value);
            confirmationModalLoading.value = false;
        }).catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
            confirmationModalLoading.value = false;
        });


}

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_unblock') {
        confirmationModalTrigger.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }
}


const executeBulkDelete = () => {
    confirmationModalLoading.value = true;

    axios.post(props.routes.unblock, { items: selectedItems.value })
        .then((response) => {
            handleModalClose();
            handleClearSelection();
            showNotification('success', response.data.messages);
            getData(currentPage.value);
        })
        .catch((error) => {
            handleClearSelection();
            handleModalClose();
            handleErrorResponse(error);
        })
        .finally(() => {
            confirmationModalLoading.value = false;
        });
}


const handleCreateButtonClick = () => {
    createModalTrigger.value = true
    formErrors.value = null;
    loadingModal.value = false
}

const handleSelectAll = () => {
    axios.post(props.routes.select_all, { filter: filterData.value })
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification('success', response.data.messages);

        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });

};


const handleSearchButtonClick = () => {
    getData(1);
    handleClearSelection();
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    handleSearchButtonClick();
}


const renderRequestedPage = (url) => {
    if (!url) return;

    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get('page') ?? 1;
    getData(pageParam);
};


const handleFormErrorResponse = (error) => {
    if (error.request?.status == 419) {
        showNotification('error', { request: ["Session expired. Reload the page"] });
    } else if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || { request: [error.message] });
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
        showNotification('error', error.response.data.errors || { request: [error.message] });
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
        selectedItems.value = data.value.data.map(item => item.ip);
    } else {
        selectedItems.value = [];
    }
};



const handleClearSelection = () => {
    selectedItems.value = [];
    selectPageItems.value = false;
    selectAll.value = false;
}

const handleModalClose = () => {
    createModalTrigger.value = false;
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

const determineColor = (status) => {
    switch (status) {
        case 'blocked':
            return {
                backgroundColor: 'bg-rose-50',
                textColor: 'text-rose-700',
                ringColor: 'ring-rose-600/20'
            };
        default:
            return {
                backgroundColor: 'bg-yellow-50',
                textColor: 'text-yellow-700',
                ringColor: 'ring-yellow-600/20'
            };
    }
};


</script>
