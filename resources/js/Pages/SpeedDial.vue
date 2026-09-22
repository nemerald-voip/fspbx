<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>{{ $t('Speed Dial') }}</template>

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
                <button v-if="props.permissions.create" type="button" @click.prevent="handleCreateButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    {{ $t('Create') }}
                </button>

                <button v-if="props.permissions.upload" type="button" @click.prevent="handleImportButtonClick()"
                    class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    <DocumentArrowUpIcon class="h-5 w-5" aria-hidden="true" />
                    {{ $t('Upload CSV') }}
                </button>

                <button type="button" @click.prevent="handleExportButtonClick()"
                    class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    <DocumentArrowDownIcon class="h-5 w-5" aria-hidden="true" />
                    {{ $t('Export') }}
                </button>


            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>
            <template #table-header>

                <TableColumnHeader
                    class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <span class="pl-4">{{ $t('Speed Dial Name') }}</span>
                </TableColumnHeader>

                <TableColumnHeader :header="$t('Destination Number')"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader :header="$t('Speed Dial Code')"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader :header="$t('Assigned User')"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="6">
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
                <tr v-for="row in data.data" :key="row.contact_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                        <div class="flex items-center">
                            <input v-if="row.contact_uuid" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.contact_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-4"
                                :class="{ 'cursor-pointer hover:text-gray-900': props.permissions.update, }"
                                @click="props.permissions.update && handleEditRequest(row.contact_uuid)">
                                {{ row.contact_organization }}
                            </div>
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <span v-if="row.primary_phone" class="flex items-center">
                            {{ row.primary_phone.phone_number_formatted }}
                        </span>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <span v-if="row.primary_phone" class="flex items-center">
                            {{ row.primary_phone.phone_speed_dial }}
                        </span>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <div v-if="row.speed_dial_user.length" class="flex flex-wrap gap-1">
                            <Badge v-for="user in row.speed_dial_user" :key="user.user_uuid" :text="user.username"
                                backgroundColor="bg-gray-100" textColor="text-gray-700" ringColor="ring-gray-400/20"
                                class="px-2 py-1 text-xs font-semibold" />
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap justify-end">
                                <ejs-tooltip v-if="props.permissions.update" :content="$t('Edit')"
                                    position='TopCenter' target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <PencilSquareIcon @click="handleEditRequest(row.contact_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip v-if="props.permissions.destroy" :content="$t('Delete')"
                                    position='TopCenter' target="#delete_tooltip_target">
                                    <div id="delete_tooltip_target">
                                        <TrashIcon @click="handleSingleItemDeleteRequest(row.contact_uuid)"
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

    <AddEditItemModal :customClass="'sm:max-w-4xl'" :show="createModalTrigger" :header="$t('Create New Speed Dial')"
        :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <CreateSpeedDialForm :options="itemOptions" :errors="formErrors" :is-submitting="createFormSubmiting"
                @submit="handleCreateRequest" @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :customClass="'sm:max-w-4xl'" :show="editModalTrigger" :header="$t('Edit Speed Dial Details')"
        :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <UpdateSpeedDialForm :options="itemOptions" :errors="formErrors" :is-submitting="updateFormSubmiting"
                @submit="handleUpdateRequest" @cancel="handleModalClose" @error="handleErrorResponse"
                @success="showNotification('success', { request: [$event] })" />
        </template>
    </AddEditItemModal>

    <DeleteConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmDeleteAction" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

    <UploadModal :show="showUploadModal" @close="showUploadModal = false" :header="$t('Upload File')" @upload="uploadFile"
        @download-template="downloadTemplateFile" :is-submitting="isUploadingFile" :errors="uploadErrors" />
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
import DeleteConfirmationModal from "./components/modal/DeleteConfirmationModal.vue";
import UploadModal from "./components/modal/UploadModal.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import CreateSpeedDialForm from "./components/forms/CreateSpeedDialForm.vue";
import UpdateSpeedDialForm from "./components/forms/UpdateSpeedDialForm.vue";
import Notification from "./components/notifications/Notification.vue";
import Badge from "@generalComponents/Badge.vue";
import { DocumentArrowUpIcon, DocumentArrowDownIcon } from "@heroicons/vue/24/outline";



const loading = ref(false)
const loadingModal = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const createModalTrigger = ref(false);
const editModalTrigger = ref(false);
const bulkUpdateModalTrigger = ref(false);
const confirmationModalTrigger = ref(false);
const showUploadModal = ref(false);
const createFormSubmiting = ref(null);
const updateFormSubmiting = ref(null);
const isUploadingFile = ref(null);
const confirmDeleteAction = ref(null);
const bulkUpdateFormSubmiting = ref(null);
const formErrors = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const uploadErrors = ref(null);

const props = defineProps({
    pagination: Object,
    routes: Object,
    permissions: Object,
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

const sortData = ref({ name: 'contact_organization', order: 'asc' });

const filterData = ref({
    search: null,
});

const itemOptions = ref({})

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [
        // {
        //     id: 'bulk_update',
        //     label: 'Edit',
        //     icon: 'PencilSquareIcon'
        // }
    ];

    // Conditionally add the delete action if permission is granted
    if (props.permissions.destroy) {
        actions.push({
            id: 'bulk_delete',
            label: trans('Delete'),
            icon: 'TrashIcon'
        });
    }

    return actions;
});


const handleEditRequest = (itemUuid) => {
    editModalTrigger.value = true
    formErrors.value = null;
    loadingModal.value = true
    getItemOptions(itemUuid);
}

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

const handleUpdateRequest = (form) => {
    updateFormSubmiting.value = true;
    formErrors.value = null;

    axios.put(itemOptions.value.routes.update_route, form)
        .then((response) => {
            updateFormSubmiting.value = false;
            showNotification('success', response.data.messages);
            refreshData();
            handleModalClose();
            handleClearSelection();
        }).catch((error) => {
            updateFormSubmiting.value = false;
            handleClearSelection();
            handleFormErrorResponse(error);
        });

};

const handleSingleItemDeleteRequest = (uuid) => {
    confirmationModalTrigger.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
};

const handleImportButtonClick = () => {
    uploadErrors.value = null;
    showUploadModal.value = true;
};

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        confirmationModalTrigger.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }

}

const executeBulkDelete = (items = selectedItems.value) => {
    axios.post(`${props.routes.bulk_delete}`, { items })
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


const uploadFile = (file) => {
    isUploadingFile.value = true;
    uploadErrors.value = null;
    const formData = new FormData();
    formData.append('file', file);

    axios.post(props.routes.import, formData)
        .then((response) => {
            showNotification('success', response.data.messages);
            handleModalClose();
            refreshData();
        })
        .catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
            if (error.response) {
                uploadErrors.value = error.response.data.errors;
            }
        })
        .finally(() => {
            isUploadingFile.value = false;
        });
}

const handleCreateButtonClick = () => {
    createModalTrigger.value = true
    formErrors.value = null;
    loadingModal.value = true
    getItemOptions();
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


const getItemOptions = (itemUuid = null) => {
    const payload = itemUuid ? { item_uuid: itemUuid } : {}; // Conditionally add itemUuid to payload

    axios.post(props.routes.item_options, payload)
        .then((response) => {
            loadingModal.value = false;
            itemOptions.value = response.data;
            // console.log(itemOptions.value);

        }).catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        });
}

function downloadTemplateFile() {
    // Make a GET request to your Laravel route
    axios.get(props.routes.download_template, {
        responseType: 'blob' // Important: so we get back a Blob object
    })
        .then((response) => {
            // Create a Blob from the response data
            const fileBlob = new Blob([response.data], { type: 'text/csv' })
            // Create a URL for the blob
            const fileURL = window.URL.createObjectURL(fileBlob)

            // Create a hidden link element, set it to the blob URL, and trigger a download
            const link = document.createElement('a')
            link.href = fileURL
            link.setAttribute('download', 'template.csv') // The filename you want
            document.body.appendChild(link)
            link.click()
            link.remove()
        })
        .catch((error) => {
            console.error('Error downloading template:', error)
        })
}

const handleExportButtonClick = () => {
    axios.get(props.routes.export, {
        params: {
            filterData: filterData.value
        },
        responseType: 'blob'
    })
    .then((response) => {
        const blob = new Blob(
            [response.data],
            { type: 'text/csv' }
        );
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', 'contacts.csv');
        document.body.appendChild(link);
        link.click();
        link.remove();
    })
    .catch((error) => {
        console.error('Error exporting contacts:', error);
    });
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
        selectedItems.value = data.value.data.map(item => item.contact_uuid);
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
    createModalTrigger.value = false;
    editModalTrigger.value = false;
    confirmationModalTrigger.value = false;
    bulkUpdateModalTrigger.value = false;
    showUploadModal.value = false;
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

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>
