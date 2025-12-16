<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Domains</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-candidate"
                        id="mobile-search-candidate"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                    <input type="text" v-model="filterData.search" name="desktop-search-candidate"
                        id="desktop-search-candidate"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <button v-if="permissions.domain_create" type="button"
                    @click.prevent="handleCreateButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Create
                </button>


            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>

            <template #table-header>
                <!-- First column: checkbox + Domain (description) -->
                <TableColumnHeader header="Domain" field="domain_description" :sortable="true" :sortedField="sortData.name" 
                    :sortOrder="sortData.order" @sort="handleSortRequest"
                    class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                    <span class="pl-4">Domain</span>
                </TableColumnHeader>

                <!-- Domain Name -->
                <TableColumnHeader header="Host" field="domain_name" :sortable="true" :sortedField="sortData.name"
                    :sortOrder="sortData.order" @sort="handleSortRequest"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <TableColumnHeader />
                <!-- Enabled -->
                <TableColumnHeader header="Status" field="domain_enabled" :sortable="true" :sortedField="sortData.name"
                    :sortOrder="sortData.order" @sort="handleSortRequest"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <!-- Actions -->
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>


            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="10">
                    <div class="text-sm text-center m-2">
                        <span class="font-semibold ">{{ selectedItems.length }} </span> items are selected.
                        <button v-if="!selectAll && selectedItems.length !== data.total"
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
                <tr v-for="row in data.data" :key="row.domain_uuid">
                    <!-- Checkbox + Domain description (clickable for edit) -->
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                        <div class="flex items-center">
                            <input v-model="selectedItems" type="checkbox" :value="row.domain_uuid"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                            <div class="ml-4 cursor-pointer hover:text-gray-900"
                                @click="permissions.domain_update && handleEditButtonClick(row.domain_uuid)">
                                {{ row.domain_description || row.domain_name }}
                            </div>
                        </div>
                    </TableField>

                    <!-- Domain Name (fqdn) -->
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.domain_name" />

                    <TableField class="px-2 py-2 text-sm flex-col sm:flex-row gap-2">

                        <template v-if="page.props.auth.can.fax_sent_view">
                            <a :href="`/core/domain_settings/domain_settings.php?id=${row.domain_uuid}`"
                                class="inline-flex items-center px-2 py-1 rounded text-gray-700 hover:bg-gray-100 transition text-xs font-medium"
                                title="Settings">
                                <SettingsApplications class="w-4 h-4 mr-1" />
                                Settings
                            </a>
                        </template>

                    </TableField>

                    <!-- Enabled flag -->
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">

                        <Badge v-if="row.domain_enabled" text="Enabled" backgroundColor="bg-green-50"
                            textColor="text-green-700" ringColor="ring-green-600/20" />
                        <Badge v-else text="Disabled" backgroundColor="bg-rose-50" textColor="text-rose-700"
                            ringColor="ring-rose-600/20" />
                    </TableField>

                    <!-- Action buttons -->
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap justify-end">
                                <!-- Edit -->
                                <ejs-tooltip v-if="permissions.domain_update" :content="'Edit'"
                                    position="TopCenter" target="#edit_domain_tooltip_target">
                                    <div id="edit_domain_tooltip_target">
                                        <PencilSquareIcon @click="handleEditButtonClick(row.domain_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>

                                <!-- Delete -->
                                <ejs-tooltip v-if="permissions.domain_destroy" :content="'Delete'"
                                    position="TopCenter" target="#delete_domain_tooltip_target">
                                    <div id="delete_domain_tooltip_target">
                                        <TrashIcon @click="handleSingleItemDeleteRequest(row.domain_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>
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
    <CreateDomainForm :show="showCreateModal" :options="itemOptions" :loading="isModalLoading"
        :header="'Create New Domain'" @close="showCreateModal = false" @error="handleErrorResponse"
        @success="showNotification" @refresh-data="handleSearchButtonClick" />

    <UpdateDomainForm :show="showUpdateModal" :options="itemOptions" :loading="isModalLoading"
        :header="'Update Domain - ' + (itemOptions?.item?.domain_name ?? 'loading')"
        @close="showUpdateModal = false" @error="handleErrorResponse" @success="showNotification"
        @refresh-data="handleSearchButtonClick" />

    <!-- <BulkUpdateDomainForm :items="selectedItems" :options="itemOptions" :show="showBulkUpdateModal"
        :header="'Bulk Update'" :loading="isModalLoading" @close="handleModalClose"
        @refresh-data="handleSearchButtonClick" /> -->

    <ConfirmationModal :show="showConfirmationModal" @close="showConfirmationModal = false"
        @confirm="confirmDeleteAction" :header="'Confirm Deletion'"
        :text="'This action will permanently delete the selected domain(s). Are you sure you want to proceed?'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { usePage } from '@inertiajs/vue3'
import axios from 'axios';
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon, ChevronUpIcon, ChevronDownIcon } from "@heroicons/vue/24/solid";
import Badge from "@generalComponents/Badge.vue";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
// import BulkUpdateDomainForm from "./components/forms/BulkUpdateDomainForm.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import CreateDomainForm from "./components/forms/CreateDomainForm.vue";
import UpdateDomainForm from "./components/forms/UpdateDomainForm.vue";
import Notification from "./components/notifications/Notification.vue";
import SettingsApplications from "@icons/SettingsApplications.vue"

const page = usePage()
const itemOptions = ref({})
const loading = ref(false)
const isModalLoading = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const createModalTrigger = ref(false);
const showUpdateModal = ref(false);
const showCreateModal = ref(false);
const showBulkUpdateModal = ref(false);
const showConfirmationModal = ref(false);
const confirmationRestartTrigger = ref(false);
const confirmDeleteAction = ref(null);
const showCloudProvisioningSettings = ref(false);
const formErrors = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
let tooltipCopyContent = ref('Copy to Clipboard');

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

const props = defineProps({
    routes: Object,
    permissions: Object,
});


onMounted(() => {
    handleSearchButtonClick();
})

const filterData = ref({
    search: null,
});

const sortData = ref({
    name: 'domain_description',
    order: 'asc'
});

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [
        // {
        //     id: 'bulk_restart',
        //     label: 'Restart',
        //     icon: 'RestartIcon'
        // },
    ];

    // Conditionally add the delete action if permission is granted
    if (props.permissions.domain_destroy) {
        actions.push({
            id: 'bulk_delete',
            label: 'Delete',
            icon: 'TrashIcon'
        });
    }

    return actions;
});

const handleEditButtonClick = (itemUuid) => {
    getItemOptions(itemUuid);
}

const getItemOptions = async (itemUuid = null) => {
    itemOptions.value = {};
    isModalLoading.value = true;

    try {
        const payload = itemUuid ? { itemUuid } : {};
        const response = await axios.post(props.routes.item_options, payload);
        itemOptions.value = response.data;

        if (itemUuid) {
            showUpdateModal.value = true;
        }

    } catch (error) {
        handleModalClose();
        handleErrorResponse(error);
    } finally {
        isModalLoading.value = false;
    }
}


const handleCreateButtonClick = async () => {
    isModalLoading.value = true;

    try {
        const response = await axios.post(props.routes.item_options, {
            itemUuid: null,
        });

        // Only open modal if no limit error
        itemOptions.value = response.data;
        showCreateModal.value = true;

    } catch (error) {
        // Limit reached → show toast, do NOT open modal
        handleErrorResponse(error);
        return;

    } finally {
        isModalLoading.value = false;
    }
}


const handleSingleItemDeleteRequest = (uuid) => {
    showConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
}


const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        showConfirmationModal.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }
    if (action === 'bulk_update') {
        getItemOptions();
        isModalLoading.value = true
        showBulkUpdateModal.value = true;
    }
}


const executeBulkDelete = (items = selectedItems.value) => {
    axios.post(props.routes.bulk_delete, { items })
        .then((response) => {
            handleModalClose();
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
        })
        .catch((error) => {
            handleClearSelection();
            handleModalClose();
            handleErrorResponse(error);
        });
}

const handleSelectAll = () => {
    axios.post(props.routes.select_all, filterData._rawValue)
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification('success', response.data.messages);

        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });

};


const handleCopyToClipboard = (macAddress) => {
    navigator.clipboard.writeText(macAddress).then(() => {
        tooltipCopyContent.value = 'Copied'
        setTimeout(() => {
            tooltipCopyContent.value = 'Copy to Clipboard'
        }, 500);
    }).catch((error) => {
        // Handle the error case
        console.error('Failed to copy to clipboard:', error);
    });
}

const handleSortRequest = (sort) => {
    sortData.value.name = sort.field
    sortData.value.order = sort.order

    getData();
};

const getData = (page = 1) => {
    loading.value = true;

    let sort = sortData.value.name;
    if (sortData.value.order === 'desc') {
        sort = `-${sort}`;
    }

    axios.get(props.routes.data_route, {
        params: {
            filter: filterData.value,
            page,
            sort: sort, 
        }
    })
        .then((response) => {
            data.value = response.data;
        }).catch((error) => {
            handleErrorResponse(error);
        }).finally(() => {
            loading.value = false
        })
}

const handleSearchButtonClick = () => {
    getData()
};


const handleFiltersReset = () => {
    filterData.value.search = null;
    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    handleSearchButtonClick();
}


const renderRequestedPage = (url) => {
    loading.value = true;
    // Extract the page number from the url, e.g. "?page=3"
    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get("page") ?? 1;

    // Now call getData with the page number
    getData(pageParam);
};


const handleFormErrorResponse = (error) => {
    if (error.request?.status === 419) {
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
        selectedItems.value = data.value.data.map(item => item.domain_uuid);
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
    showUpdateModal.value = false;
    showConfirmationModal.value = false;
    confirmationRestartTrigger.value = false;
    showBulkUpdateModal.value = false;
    showCloudProvisioningSettings.value = false;
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

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>