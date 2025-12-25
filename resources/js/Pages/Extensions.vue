<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Extensions</template>

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
                <button v-if="page.props.auth.can.extension_create" type="button" @click.prevent="handleCreateButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Create
                </button>

                <button v-if="page.props.auth.can.extension_upload" type="button" @click.prevent="handleImportButtonClick()"
                    class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    <DocumentArrowUpIcon class="h-5 w-5" aria-hidden="true" />
                    Import CSV
                </button>
                <button
                  type="button"
                  v-if="page.props.auth.can.extension_export"
                  @click.prevent="exportExtensionsCsv()"
                  class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                  <DocumentArrowDownIcon class="h-5 w-5" aria-hidden="true" />
                  Export CSV
                </button>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>


            <template #table-header>
                <!-- Checkbox + Extension column -->
                <TableColumnHeader
                    class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest"
                        :has-selected-items="selectedItems.length > 0" />
                    <span class="pl-10">Extension</span>
                </TableColumnHeader>

                <TableColumnHeader header="Email"
                    class="hidden px-2 py-3.5 text-left text-sm font-semibold text-gray-900 sm:table-cell" />
                <TableColumnHeader header="Outbound Caller ID"
                    class="whitespace-nowrap hidden px-2 py-3.5 text-left text-sm font-semibold text-gray-900 md:table-cell" />
                <TableColumnHeader header="Description"
                    class="hidden px-2 py-3.5 text-left text-sm font-semibold text-gray-900 lg:table-cell" />
                <TableColumnHeader header="Services"
                    class="hidden px-2 py-3.5 text-left text-sm font-semibold text-gray-900 lg:table-cell" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>


            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="9">
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
                <template v-for="row in data.data" :key="row.extension_uuid">

                    <tr>
                        <!-- Checkbox + Extension -->
                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                            <div class="flex items-center gap-5">
                                <input v-if="row.extension_uuid" v-model="selectedItems" type="checkbox" name="action_box[]"
                                    :value="row.extension_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                                <span
                                    v-if="!isRegsLoading && registrations && Array.isArray(registrations[String(row.extension)])"
                                    class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-green-500 text-white text-xs cursor-pointer focus:outline-none"
                                    :title="`${registrations[String(row.extension)].length} device(s) registered`"
                                    @click="toggleExpand(row.extension_uuid)">
                                    {{ registrations[String(row.extension)].length }}
                                </span>
                                <span v-else
                                    class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-300 text-gray-600 text-xs"
                                    title="Not registered" @click="toggleExpand(row.extension_uuid)">
                                </span>

                                <div :class="{ 'cursor-pointer hover:text-gray-900': page.props.auth.can.extension_update, }"
                                    @click="page.props.auth.can.extension_update && handleEditButtonClick(row.extension_uuid)">
                                    <span class="flex flex-col lg:flex-row items-start gap-2">
                                        {{ row.name_formatted }}
                                        <span class="italic text-xs sm:hidden"> {{ row.email || '' }}</span>
                                        <Badge v-if="row.suspended" :text="'Suspended'" :backgroundColor="'bg-rose-100'"
                                            :textColor="'text-rose-800'" ringColor="ring-rose-400/20"
                                            class="px-2 py-1 text-xs" />
                                        <Badge v-if="row.do_not_disturb == 'true' && !row.suspended" :text="'DND'"
                                            :backgroundColor="'bg-rose-100'" :textColor="'text-rose-800'"
                                            ringColor="ring-rose-400/20" class="px-2 py-1 text-xs" />
                                        <Badge v-if="row.forward_all_enabled == 'true'" :text="'FWD All'"
                                            :backgroundColor="'bg-blue-100'" :textColor="'text-blue-800'"
                                            ringColor="ring-blue-400/20" class="px-2 py-1 text-xs" />
                                        <Badge v-if="row.forward_busy_enabled == 'true'" :text="'FWD Busy'"
                                            :backgroundColor="'bg-blue-100'" :textColor="'text-blue-800'"
                                            ringColor="ring-blue-400/20" class="px-2 py-1 text-xs" />
                                        <Badge v-if="row.forward_no_answer_enabled == 'true'" :text="'FWD no Ans'"
                                            :backgroundColor="'bg-blue-100'" :textColor="'text-blue-800'"
                                            ringColor="ring-blue-400/20" class="px-2 py-1 text-xs" />
                                        <Badge v-if="row.forward_user_not_registered_enabled == 'true'" :text="'FWD no Reg'"
                                            :backgroundColor="'bg-blue-100'" :textColor="'text-blue-800'"
                                            ringColor="ring-blue-400/20" class="px-2 py-1 text-xs" />
                                        <Badge v-if="row.follow_me_enabled == 'true'" :text="'Sequence'"
                                            :backgroundColor="'bg-blue-100'" :textColor="'text-blue-800'"
                                            ringColor="ring-blue-400/20" class="px-2 py-1 text-xs" />
                                    </span>

                                </div>
                            </div>
                        </TableField>


                        <!-- Email -->
                        <TableField class="hidden px-2 py-2 text-sm text-gray-500 sm:table-cell">
                            {{ row.email || '' }}
                        </TableField>
                        <!-- Outbound Caller ID -->
                        <TableField class="whitespace-nowrap hidden spx-2 py-2 text-sm text-gray-500 md:table-cell">
                            {{ row.outbound_caller_id_number_formatted || row.outbound_caller_id_number || '' }}

                        </TableField>
                        <!-- Description -->
                        <TableField class="hidden px-2 py-2 text-sm text-gray-500 lg:table-cell">
                            {{ row.description }}
                        </TableField>

                        <TableField class="hidden whitespace-nowrap px-2 py-1 text-sm text-gray-500 lg:table-cell">

                            <template #action-buttons>
                                <div class="flex items-center whitespace-nowrap">
                                    <DevicePhoneMobileIcon v-if="!!row.mobile_app && row.mobile_app.status!='-1'"
                                        class="h-5 w-5  text-blue-400 hover:text-blue-600 active:bg-blue-300" />
                                    <MicrophoneIcon v-if="!!row.user_record"
                                        class="h-5 w-5 text-rose-400 hover:text-rose-600 active:bg-rose-300" />

                                </div>
                            </template>


                        </TableField>

                        <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">

                            <template #action-buttons>
                                <div class="flex items-center whitespace-nowrap justify-end">

                                    <ejs-tooltip v-if="page.props.auth.can.extension_update" :content="'Edit'"
                                        position='TopCenter' target="#destination_tooltip_target">
                                        <div id="destination_tooltip_target">
                                            <PencilSquareIcon @click="handleEditButtonClick(row.extension_uuid)"
                                                class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                        </div>
                                    </ejs-tooltip>

                                    <ejs-tooltip v-if="page.props.auth.can.extension_destroy" :content="'Delete'"
                                        position='TopCenter' target="#delete_tooltip_target">
                                        <div id="delete_tooltip_target">
                                            <TrashIcon @click="handleSingleItemDeleteRequest(row.extension_uuid)"
                                                class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                        </div>
                                    </ejs-tooltip>

                                    <AdvancedActionButton :actions="advancedActions" @advanced-action="(action) => handleAdvancedActionRequest(action, row.extension_uuid)"/>

                                </div>
                            </template>


                        </TableField>
                    </tr>

                    <!-- EXPANDABLE ROW -->
                    <tr v-if="expandedExtension === row.extension_uuid">
                        <td :colspan="5" class="bg-gray-50 px-6 py-4">
                            <div
                                v-if="registrations && Array.isArray(registrations[String(row.extension)]) && registrations[String(row.extension)].length">
                                <div class="ml-9 space-y-2 text-sm text-gray-500">
                                    <div v-for="(reg, idx) in registrations[String(row.extension)]" :key="idx"
                                        class="flex flex-col md:flex-row gap-4 border-b last:border-0 pb-2">
                                        <div><span class="font-semibold">Device:</span> {{ reg.agent }}</div>
                                        <div><span class="font-semibold">Remote IP Address:</span> {{ reg.wan_ip }}</div>
                                        <div><span class="font-semibold">Connection Type:</span> {{ reg.transport }}</div>
                                        <div><span class="font-semibold">Expires in:</span> {{ reg.expsecs }}s</div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-gray-400 text-sm ">No registered devices found.</div>
                        </td>
                    </tr>
                </template>
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

    <UpdateExtensionForm :show="showUpdateModal" :options="itemOptions" :loading="isModalLoading"
        :header="'Update Extension - ' + (itemOptions?.item?.name_formatted ?? 'loading')" @close="showUpdateModal = false"
        @error="handleErrorResponse" @success="showNotification" @refresh-data="handleSearchButtonClick" />

    <CreateExtensionForm :show="showCreateModal" :options="itemOptions" :loading="isModalLoading"
        :header="'Create Extension'" @close="showCreateModal = false" @error="handleErrorResponse"
        @success="showNotification" @open-edit-form="handleEditButtonClick" @refresh-data="handleSearchButtonClick"/>


    <ConfirmationModal :show="showDeleteConfirmationModal" @close="showDeleteConfirmationModal = false"
        @confirm="confirmDeleteAction" :header="'Confirm Deletion'" :loading="isModalLoading"
        :text="'This action will permanently delete the selected extension(s). Are you sure you want to proceed?'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

    <UploadModal :show="showUploadModal" @close="showUploadModal = false" :header="'Upload File'" @upload="uploadFile"
        @download-template="downloadTemplateFile" :is-submitting="isUploadingFile" :errors="uploadErrors" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { usePage } from '@inertiajs/vue3'
import axios from 'axios';
import { router } from "@inertiajs/vue3";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import AdvancedActionButton from "./components/general/AdvancedActionButton.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import CreateExtensionForm from "./components/forms/CreateExtensionForm.vue";
import UpdateExtensionForm from "./components/forms/UpdateExtensionForm.vue";
import Notification from "./components/notifications/Notification.vue";
import Badge from "@generalComponents/Badge.vue";
import { MicrophoneIcon } from "@heroicons/vue/24/outline";
import UploadModal from "./components/modal/UploadModal.vue";
import { DocumentArrowUpIcon, DocumentArrowDownIcon, DevicePhoneMobileIcon } from "@heroicons/vue/24/outline";


const page = usePage()
const loading = ref(false)
const isModalLoading = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const showCreateModal = ref(false);
const showUpdateModal = ref(false);
const bulkUpdateModalTrigger = ref(false);
const confirmDeleteAction = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const showDeleteConfirmationModal = ref(false);
const isRegsLoading = ref(false)
const showUploadModal = ref(false);
const isUploadingFile = ref(null);
const uploadErrors = ref(null);

const props = defineProps({
    data: Object,
    routes: Object,
});

const filterData = ref({
    search: null,
});

const itemOptions = ref({})
const registrations = ref({})
const expandedExtension = ref(null)


const toggleExpand = (extension_uuid) => {
    expandedExtension.value = expandedExtension.value === extension_uuid ? null : extension_uuid
}

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
    if (page.props.auth.can.extension_destroy) {
        actions.push({
            id: 'bulk_delete',
            label: 'Delete',
            icon: 'TrashIcon'
        });
    }

    return actions;
});


const advancedActions = computed(() => [
    {
        category: "Users",
        actions: [
            { id: 'make_user', label: 'Make User', icon: 'UserPlusIcon' },
            { id: 'make_admin', label: 'Make Admin', icon: 'KeyIcon' },
        ],
    },
    {
        category: "Contact Center",
        actions: [
            { id: 'make_cc_agent', label: 'Make Agent', icon: 'SupportAgent' },
            { id: 'make_cc_admin', label: 'Make Admin', icon: 'KeyIcon' },
        ],
    },
]);


onMounted(async () => {
    isRegsLoading.value = true
    try {
        // Make your additional API call (example URL)
        const response = await axios.get(props.routes.registrations)
        registrations.value = response.data.registrations || {}
        // console.log(response.data.registrations)
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        isRegsLoading.value = false
    }
})


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
            link.setAttribute('download', 'extensions_template.csv') // The filename you want
            document.body.appendChild(link)
            link.click()
            link.remove()
        })
        .catch((error) => {
            console.error('Error downloading template:', error)
        })
}

const exportExtensionsCsv = () => {
  axios.get(props.routes.export, {
    params: {
      // mirrors your search filter (and leaves room for future filters)
      filter: { search: filterData.value.search },
    },
    responseType: 'blob',
  })
  .then((response) => {
    const blob = new Blob([response.data], { type: 'text/csv' });
    const url  = window.URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = 'extensions.csv';
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
  })
  .catch(handleErrorResponse);
};

const handleEditButtonClick = (itemUuid) => {
    showUpdateModal.value = true
    getItemOptions(itemUuid);
}

const handleSingleItemDeleteRequest = (uuid) => {
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
};

const executeBulkDelete = (items = selectedItems.value) => {
    isModalLoading.value = true
    axios.post(props.routes.bulk_delete, { items })
        .then((response) => {
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
        })
        .catch((error) => {
            handleErrorResponse(error);
        })
        .finally(() => {
            handleModalClose();
            isModalLoading.value = false
        })
}

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        showDeleteConfirmationModal.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }
    if (action === 'bulk_update') {
        formErrors.value = [];
        getItemOptions();
        loadingModal.value = true
        bulkUpdateModalTrigger.value = true;
    }

}

const handleAdvancedActionRequest = (action, extension_uuid) => {
    let role = null;
    let url = null;

    if (action === 'make_cc_agent') {
        url = props.routes.create_contact_center_user
        role = 'agent';
    } else if (action === 'make_cc_admin') {
        url = props.routes.create_contact_center_user
        role = 'admin';
    } else if (action === 'make_admin') {
        url = props.routes.create_user
        role = 'admin';
    } else if (action === 'make_user') {
        url = props.routes.create_user
        role = 'user';
    } else {
        return; // ignore other actions
    }

    const payload = {
        extension_uuid,
        role,
    };

    axios.post(url, payload)
        .then((response) => {
            showNotification('success', response.data.messages);
        })
        .catch((error) => {
            handleErrorResponse(error);
        })
        .finally(() => {
            // reset loading state, close modal, etc.
        });
};




const handleCreateButtonClick = () => {
    showCreateModal.value = true
    getItemOptions();
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



const handleSearchButtonClick = () => {
    loading.value = true;
    router.visit(props.routes.current_page, {
        data: {
            filter: {
                search: filterData.value.search,
            },
        },
        preserveScroll: true,
        preserveState: true,
        only: [
            "data",
        ],
        onSuccess: (page) => {
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
            filter: {
                search: filterData.value.search,
            },
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
    itemOptions.value = {}
    const payload = itemUuid ? { item_uuid: itemUuid } : {}; // Conditionally add itemUuid to payload
    isModalLoading.value = true
    axios.post(props.routes.item_options, payload)
        .then((response) => {
            itemOptions.value = response.data;
            // console.log(itemOptions.value);

        }).catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        }).finally(() => {
            isModalLoading.value = false
        })
}


const handleErrorResponse = (error) => {
    if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        console.log(error.response.data);
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
        selectedItems.value = props.data.data.map(item => item.extension_uuid);
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
    showUpdateModal.value = false;
    showDeleteConfirmationModal.value = false;
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


registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>
