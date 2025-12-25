<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Phone Numbers</template>

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
                <button type="button" v-if="permissions.create"
                    @click.prevent="handleCreateButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Create
                </button>
                <button v-if="permissions.upload" type="button"
                    @click.prevent="handleImportButtonClick()"
                    class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    <DocumentArrowUpIcon class="h-5 w-5" aria-hidden="true" />
                    Import CSV
                </button>
                <button v-if="permissions.export"
                  type="button"
                  @click.prevent="exportPhoneNumbersCsv()"
                  class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                  <DocumentArrowDownIcon class="h-5 w-5" aria-hidden="true" />
                  Export CSV
                </button>
                <button v-if="permissions.view_global && !showGlobal" type="button"
                    @click.prevent="handleShowGlobal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show global
                </button>
                <button v-if="permissions.view_global && showGlobal" type="button"
                    @click.prevent="handleShowLocal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show local
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
                    class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">

                    <span class="pl-4">Phone Number</span>
                </TableColumnHeader>
                <TableColumnHeader v-if="showGlobal" header="Domain"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader v-if="!showGlobal" header="Call Routing"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Description"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Status" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Action" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="6">
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
                <tr v-for="row in data.data" :key="row.destination_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 flex"
                        :text="row.destination_number_formatted">
                        <div class="flex items-center">
                            <input v-if="row.destination_uuid" v-model="selectedItems" type="checkbox"
                                name="action_box[]" :value="row.destination_uuid"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-4"
                                :class="{ 'cursor-pointer hover:text-gray-900': permissions.update, }"
                                @click="permissions.update && handleEditRequest(row.destination_uuid)">
                                {{ row.destination_number_formatted }}
                            </div>

                            <ejs-tooltip :content="tooltipCopyContent" position='TopLeft' class="ml-2"
                                @click="handleCopyToClipboard(row.destination_number)" target="#copy_tooltip_target">
                                <div id="copy_tooltip_target">
                                    <ClipboardDocumentIcon
                                        class="h-5 w-5 text-gray-500 hover:text-gray-900 pt-1 cursor-pointer" />
                                </div>
                            </ejs-tooltip>
                        </div>
                    </TableField>

                    <TableField v-if="showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.domain?.domain_description || row.domain?.domain_name">
                        <ejs-tooltip :content="row.domain?.domain_name" position='TopLeft'
                            target="#domain_tooltip_target">
                            <div id="domain_tooltip_target">
                                {{ row.domain?.domain_description || row.domain?.domain_name }}
                            </div>
                        </ejs-tooltip>
                    </TableField>

                    <TableField v-if="!showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <ul v-if="row.routing_options">
                            <li v-for="(action, index) in row.routing_options" :key="index">
                                <span v-if="action && action.type && action.extension">
                                    Type: {{ action.type }}, Extension: {{ action.extension }}
                                </span>
                                <span v-else-if="action && action.type === 'hangup'">
                                    Type: {{ action.type }}
                                </span>
                                <span v-else>
                                    Invalid action data
                                </span>
                            </li>
                        </ul>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.destination_description" />

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">

                        <Badge v-if="row.destination_enabled == 'true'" :text="'Enabled'" backgroundColor="bg-green-100"
                            textColor="text-green-700" ringColor="ring-green-400/20" class="px-2 py-1 text-xs" />

                        <Badge v-if="row.destination_enabled == 'false'" :text="'Disabled'"
                            backgroundColor="bg-rose-100" textColor="text-rose-700" ringColor="ring-rose-400/20"
                            class="px-2 py-1 text-xs" />

                    </TableField>
                    <TableField class="w-4 whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center space-x-2 whitespace-nowrap">
                                <ejs-tooltip v-if="permissions.update"
                                    :content="'Edit phone number'" position='TopLeft' target="#edit_tooltip_target">
                                    <div id="edit_tooltip_target">
                                        <PencilSquareIcon @click="handleEditRequest(row.destination_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>
                                <ejs-tooltip v-if="permissions.destroy"
                                    :content="'Remove phone number'" position='TopLeft' target="#delete_tooltip_target">
                                    <div id="delete_tooltip_target">
                                        <TrashIcon @click="handleSingleItemDeleteRequest(row.destination_uuid)"
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


    <CreatePhoneNumberForm :show="showCreateModal" :header="'Add New Phone Number'" :loading="loadingModal"
        :options="itemOptions" @close="showCreateModal = false" @success="showNotification" @error="handleErrorResponse"
        @refresh-data="handleSearchButtonClick" />

    <UpdatePhoneNumberForm :show="showUpdateModal"
        :header="'Update Phone Number Settings - ' + itemOptions?.item?.destination_number_formatted ?? 'loading'"
        :loading="loadingModal" @close="showUpdateModal = false" :options="itemOptions" @success="showNotification"
        @error="handleErrorResponse" @refresh-data="handleSearchButtonClick" />

    <BulkUpdatePhoneNumberForm :show="showBulkUpdateModal" :header="'Bulk Update'" :loading="loadingModal"
        @close="showBulkUpdateModal = false" :items="selectedItems" :options="itemOptions" @success="showNotification"
        @error="handleErrorResponse" @refresh-data="handleSearchButtonClick" />

    <DeleteConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmDeleteAction" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

    <UploadModal :show="showUploadModal" @close="showUploadModal = false" :header="'Upload File'" @upload="uploadFile"
        @download-template="downloadTemplateFile" :is-submitting="isUploadingFile" :errors="uploadErrors" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from 'axios';
import { router, usePage } from "@inertiajs/vue3";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import DeleteConfirmationModal from "./components/modal/DeleteConfirmationModal.vue";
import CreatePhoneNumberForm from "./components/forms/CreatePhoneNumberForm.vue";
import UpdatePhoneNumberForm from "./components/forms/UpdatePhoneNumberForm.vue";
import Loading from "./components/general/Loading.vue";
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, TrashIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import MainLayout from "../Layouts/MainLayout.vue";
import Notification from "./components/notifications/Notification.vue";
import { PencilSquareIcon } from "@heroicons/vue/24/solid/index.js";
import BulkUpdatePhoneNumberForm from "./components/forms/BulkUpdatePhoneNumberForm.vue";
import Badge from "@generalComponents/Badge.vue";
import { DocumentArrowUpIcon, DocumentArrowDownIcon } from "@heroicons/vue/24/outline";
import UploadModal from "./components/modal/UploadModal.vue";

const page = usePage()
const loading = ref(false)
const loadingModal = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const showCreateModal = ref(false);
const showUpdateModal = ref(false);
const showBulkUpdateModal = ref(false);
const confirmationModalTrigger = ref(false);
const confirmDeleteAction = ref(null);
const formErrors = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const uploadErrors = ref(null);
const showUploadModal = ref(false);
const isUploadingFile = ref(null);
let tooltipCopyContent = ref('Copy to Clipboard');

const props = defineProps({
    data: Object,
    showGlobal: Boolean,
    routes: Object,
    permissions: Object,
});

// console.log(props.data)

const filterData = ref({
    search: null,
    showGlobal: props.showGlobal,
});

const itemOptions = ref({})

const showGlobal = ref(props.showGlobal);

// --- Export CSV (respects current filters/sort on server if your Export uses request()) ---
// --- Export CSV (GET to match Route::get('/phone-numbers-export', ...)) ---
const exportPhoneNumbersCsv = () => {
  axios.get(props.routes.export, {
    params: {
      // only needed if your export() reads filters from the request
      filter: filterData.value,
      // items: selectedItems.value, // enable later if you support "export selected"
    },
    responseType: 'blob',
  })
  .then((response) => {
    const blob = new Blob([response.data], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'phone_numbers.csv';
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
  })
  .catch(handleErrorResponse);
};

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [];
    if (props.permissions.update) {
        actions.push({
            id: 'bulk_update',
            label: 'Edit',
            icon: 'PencilSquareIcon'
        });
    }
    if (props.permissions.destroy) {
        actions.push({
            id: 'bulk_delete',
            label: 'Delete',
            icon: 'TrashIcon'
        });
    }

    return actions;
});

onMounted(() => {
});

const handleEditRequest = (itemUuid) => {
    showUpdateModal.value = true
    formErrors.value = null;
    loadingModal.value = true;
    getItemOptions(itemUuid);
}


const handleImportButtonClick = () => {
    uploadErrors.value = null;
    showUploadModal.value = true;
};

const uploadFile = (file) => {
    isUploadingFile.value = true;
    uploadErrors.value = null;
    const formData = new FormData();
    formData.append('file', file);

    axios.post(props.routes.import, formData)
        .then((response) => {
            showNotification('success', response.data.messages);
            handleModalClose();
            handleSearchButtonClick();
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

function normalizeSearchQuery(query) {
    if (!query) return query;

    const hasLetters = /[a-zA-Z]/.test(query);
    if (hasLetters) {
        // If letters exist, do not reformat
        return query;
    }

    // Strip everything except digits
    let digits = query.replace(/\D+/g, '');

    // Strip leading 1 ONLY if NANPA 11-digit format
    if (digits.length === 11 && digits.startsWith('1')) {
        digits = digits.substring(1);
    }

    return digits;
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


const handleSingleItemDeleteRequest = (uuid) => {
    confirmationModalTrigger.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
}

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_update') {
        getItemOptions();
        loadingModal.value = true
        showBulkUpdateModal.value = true;
    }
    if (action === 'bulk_delete') {
        confirmationModalTrigger.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
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

const handleCreateButtonClick = () => {
    showCreateModal.value = true
    formErrors.value = null;
    loadingModal.value = true
    getItemOptions();
}

const handleSelectAll = () => {
    axios.post(props.routes.select_all, {
        filter: filterData.value,
    })
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification('success', response.data.messages);

        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });

};

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        tooltipCopyContent.value = 'Copied'
        setTimeout(() => {
            tooltipCopyContent.value = 'Copy to Clipboard'
        }, 500);
    }).catch((error) => {
        // Handle the error case
        console.error('Failed to copy to clipboard:', error);
    });
}

const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    showGlobal.value = true;
    handleSearchButtonClick();
}

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    showGlobal.value = false;
    handleSearchButtonClick();
}

const handleSearchButtonClick = () => {
    // Normalize phone-number-style searches only if no letters present
    filterData.value.search = normalizeSearchQuery(filterData.value.search);

    loading.value = true;

    router.visit(props.routes.current_page, {
        data: {
            filter: filterData._rawValue,
        },
        preserveScroll: true,
        preserveState: true,
        only: [
            "data",
            "showGlobal",
        ],
        onSuccess: () => {
            loading.value = false;
            handleClearSelection();
        }
    });
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    handleSearchButtonClick();
}


const renderRequestedPage = (url) => {
    loading.value = true;
    router.visit(url, {
        data: {
            filter: filterData._rawValue,
        },
        preserveScroll: true,
        preserveState: true,
        only: ["data"],
        onSuccess: (page) => {
            loading.value = false;
        }
    });
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
        selectedItems.value = props.data.data.map(item => item.destination_uuid);
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
    showCreateModal.value = false;
    showUpdateModal.value = false;
    confirmationModalTrigger.value = false;
    showBulkUpdateModal.value = false;
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

/*
const getDestinationActionName = ((action) => {
    return action;
})*/

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>
