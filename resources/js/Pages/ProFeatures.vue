<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>{{ $t('Pro Features') }}</template>

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


                <button type="button" @click.prevent="handleRefreshButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 ml-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    {{ $t('Refresh') }}
                </button>


            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>
            <template #table-header>
                <TableColumnHeader :header="$t('User')"
                    class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <BulkActionButton :actions="bulkActions"
                        :has-selected-items="selectedItems.length > 0" />
                    <span class="pl-4">{{ $t('Feature') }}</span>
                </TableColumnHeader>

                <TableColumnHeader :header="$t('License Status')"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <!-- <TableColumnHeader header="Contact" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" /> -->
                <!-- <TableColumnHeader header="Module Status" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" /> -->

                <TableColumnHeader :header="$t('Action')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="10">
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
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 ">
                        <div class="flex items-center">
                            <input v-if="row.uuid" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">

                            <div class="ml-9 cursor-pointer hover:text-gray-900 " @click="handleEditRequest(row.uuid)">
                                {{ row.name }}
                            </div>

                        </div>
                    </TableField>


                    <TableField class=" px-2 py-2 text-sm text-gray-500">
                        <Badge v-if="row.license && !row.license_details?.meta?.valid" :text="licenseStatus(row)" backgroundColor="bg-rose-50"
                            textColor="text-rose-700"
                            ringColor="ring-rose-600/20" />

                        <Badge v-if="row.license && row.license_details?.meta?.valid" :text="licenseStatus(row)" backgroundColor="bg-blue-50"
                            textColor="text-blue-700"
                            ringColor="ring-blue-600/20" />

                    </TableField>
                    <!-- <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.status" /> -->


                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap">
                                <ejs-tooltip v-if="permissions.device_update" :content="$t('Edit')" position='TopCenter'
                                    target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <PencilSquareIcon @click="handleEditRequest(row.uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                    </div>
                                </ejs-tooltip>



                                <!-- <div id="tooltip-no-arrow-sync" role="tooltip" 
                                    class="inline-block absolute invisible text-xs z-10 py-1 px-2 font-medium text-white rounded-sm shadow-sm opacity-0 tooltip dark:bg-gray-600 delay-150" >
                                tooltip
                                </div> -->


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
                    @pagination-change-page="renderRequestedPage"
                    :page-size="perPage" :page-size-options="props.pagination?.per_page_options ?? []"
                    :show-page-size-selector="true" @page-size-change="handlePageSizeChange" />
            </template>
        </DataTable>
        <div class="px-4 sm:px-6 lg:px-8"></div>
    </div>

    <ConfirmationModal :show="showConfirmationModal" @close="showConfirmationModal = false" @confirm="confirmAction"
        :header="$t('Are you sure?')" :text="$t('Are you sure you want to proceed with this action?')"
        :confirm-button-label="actionLabel" :cancel-button-label="$t('Cancel')" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

    <AddEditItemModal :customClass="'sm:max-w-4xl'" :show="showEditModal" :header="$t('Edit Pro Feature Settings')"
        :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <UpdateProFeatureForm :options="itemOptions" :errors="formErrors" :is-submitting="updateFormSubmiting" :is-installing="isInstalling" :is-uninstalling="isUninstalling"
                @submit="handleUpdateRequest" @cancel="handleModalClose" @deactivate="handleDeactivateRequest" @install="handleInstallRequest" @uninstall="handleUninstallRequest" />
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { trans } from "@i18n";
import { computed, onMounted, onUnmounted, ref } from "vue";
import axios from 'axios';
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import NotificationSimple from "./components/notifications/Simple.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import DeleteConfirmationModal from "./components/modal/DeleteConfirmationModal.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import Badge from "./components/general/Badge.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import Notification from "./components/notifications/Notification.vue";
import { PencilSquareIcon } from "@heroicons/vue/24/solid";
import UpdateProFeatureForm from "./components/forms/UpdateProFeatureForm.vue";

const loading = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const confirmAction = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const showEditModal = ref(false);
const showConfirmationModal = ref(false);
const actionLabel = ref('');
const formErrors = ref(null);
const loadingModal = ref(false)
const itemOptions = ref({})
const updateFormSubmiting = ref(null);
const isInstalling = ref(null);
const isUninstalling = ref(null);


const props = defineProps({
    pagination: Object,
    routes: Object,
    permissions: {
        type: Object,
        default: () => ({}),
    },
    // itemData: Object,
    // itemOptions: Object,
});

const permissions = props.permissions;

const licenseStatus = (row) => ({
    VALID: trans('Valid'),
    EXPIRED: trans('Expired'),
    SUSPENDED: trans('Suspended'),
    NO_MACHINE: trans('Not activated'),
    NO_MACHINES: trans('Not activated'),
    FINGERPRINT_SCOPE_MISMATCH: trans('Not activated'),
    NOT_FOUND: trans('Invalid license'),
})[row.license_valid] || row.license_valid || trans('Unknown');

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

const sortData = ref({ name: 'created_at', order: 'asc' });

const filterData = ref({
    search: null,
});


// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [
        // {
        //     id: 'bulk_end_call',
        //     label: 'End Calls',
        //     icon: 'CallEndIcon'
        // },

    ];

    return actions;
});


const handleEditRequest = (itemUuid) => {
    showEditModal.value = true
    formErrors.value = null;
    loadingModal.value = true
    getItemOptions(itemUuid);
}

const getItemOptions = (itemUuid = null) => {
    const payload = itemUuid ? { item_uuid: itemUuid } : {}; // Conditionally add itemUuid to payload


    axios.post(props.routes.item_options, payload)
        .then((response) => {
            loadingModal.value = false;
            itemOptions.value = response.data;


        }).catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        });
}

const handleUpdateRequest = (form) => {
    updateFormSubmiting.value = true;
    loadingModal.value = true
    formErrors.value = null;

    axios.put(form.update_route, form)
        .then((response) => {
            updateFormSubmiting.value = false;
            showNotification('success', response.data.messages);
            refreshData();
            // handleModalClose();
            getItemOptions(itemOptions.value.item.uuid);
            handleClearSelection();
        }).catch((error) => {
            updateFormSubmiting.value = false;
            handleClearSelection();
            handleFormErrorResponse(error);
        });

};

const handleDeactivateRequest = (form) => {
    updateFormSubmiting.value = true;
    loadingModal.value = true
    formErrors.value = null;

    axios.delete(form.deactivate_route)
        .then((response) => {
            updateFormSubmiting.value = false;
            showNotification('success', response.data.messages);
            refreshData();
            // handleModalClose();
            getItemOptions(itemOptions.value.item.uuid);
            handleClearSelection();
        })
        .catch((error) => {
            updateFormSubmiting.value = false;
            handleClearSelection();
            handleFormErrorResponse(error);
        });
};

const handleInstallRequest = (form) => {
    isInstalling.value = true;
    formErrors.value = null;

    axios.post(form.install_route, form)
        .then((response) => {
            isInstalling.value = false;
            showNotification('success', response.data.messages);
            refreshData();
            // handleModalClose();
            getItemOptions(itemOptions.value.item.uuid);
            handleClearSelection();
        })
        .catch((error) => {
            isInstalling.value = false;
            handleClearSelection();
            handleFormErrorResponse(error);
        });
};

const handleUninstallRequest = (form) => {
    isUninstalling.value = true;
    formErrors.value = null;

    axios.post(form.uninstall_route, form)
        .then((response) => {
            isUninstalling.value = false;
            showNotification('success', response.data.messages);
            refreshData();
            // handleModalClose();
            getItemOptions(itemOptions.value.item.uuid);
            handleClearSelection();
        })
        .catch((error) => {
            isUninstalling.value = false;
            handleClearSelection();
            handleFormErrorResponse(error);
        });
};




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


const handleRefreshButtonClick = () => refreshData();

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
    selectedItems.value = [];
    selectPageItems.value = false;
    selectAll.value = false;
}



const handleModalClose = () => {
    showEditModal.value = false;
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

const handleFormErrorResponse = (error) => {
    loadingModal.value = false;
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
