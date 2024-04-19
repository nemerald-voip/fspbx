<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Message Settings</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-candidate"
                        id="mobile-search-candidate"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        placeholder="Search" />
                    <input type="text" v-model="filterData.search" name="desktop-search-candidate"
                        id="desktop-search-candidate"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        placeholder="Search" />
                </div>
            </template>

            <template #action>
                <button type="button" @click.prevent="handleCreateButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Create
                </button>

                <button v-if="!showGlobal" type="button" @click.prevent="handleShowGlobal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show global
                </button>

                <button v-if="showGlobal" type="button" @click.prevent="handleShowLocal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show local
                </button>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>


            <template #table-header>
                <TableColumnHeader header="Phone Number"
                    class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest" />
                    <span class="pl-4">Phone Number</span>
                </TableColumnHeader>
                <TableColumnHeader v-if="showGlobal" header="Domain"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <TableColumnHeader header="Carrier" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Assigned Extension"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Assigned Email"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Description"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Action" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="6">
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
                <tr v-for="row in data.data" :key="row.sms_destination_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 flex"
                        :text="row.destination_formatted">
                        <input v-if="row.destination" v-model="selectedItems" type="checkbox" name="action_box[]"
                            :value="row.sms_destination_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                        <div class="ml-9 cursor-pointer hover:text-gray-900"
                            @click="handleEditRequest(row.sms_destination_uuid)">
                            {{ row.destination_formatted }}
                        </div>
                        <ejs-tooltip :content="tooltipCopyContent" position='TopLeft' class="ml-2"
                            @click="handleCopyToClipboard(row.destination)" target="#destination_tooltip_target">
                            <div id="destination_tooltip_target">
                                <ClipboardDocumentIcon
                                    class="h-5 w-5 text-gray-500 hover:text-gray-900 pt-1 cursor-pointer" />
                            </div>
                        </ejs-tooltip>
                    </TableField>

                    <TableField v-if="showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.domain?.domain_description">
                        <ejs-tooltip :content="row.domain?.domain_name" position='TopLeft'
                            target="#destination_tooltip_target">
                            <div id="destination_tooltip_target">
                                {{ row.domain?.domain_description }}
                            </div>
                        </ejs-tooltip>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.carrier" />
                    <TableField class="flex whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.extension ? row.extension.name_formatted : row.chatplan_detail_data">
                        <template #action-buttons>
                            <Warning v-if="!row.extension && !row.chatplan_detail_data == ''"
                                class="ml-2 h-5 w-5 text-amber-500" />
                        </template>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.email" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.description" />
                    <TableField class="whitespace-nowrap text-sm text-gray-500">

                        <template #action-buttons>

                            <div class="flex items-center whitespace-nowrap">
                                <ejs-tooltip :content="'Edit'" position='TopCenter'
                                    target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <PencilSquareIcon @click="handleEditRequest(row.sms_destination_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip :content="'Delete'" position='TopCenter'
                                    target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <TrashIcon @click="handleSingleItemDeleteRequest(row.destroy_route)"
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

    <AddEditItemModal :show="addModalTrigger" :header="'Add New'" :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <CreateMessageSettingsForm :options="itemOptions" :errors="formErrors" :is-submitting="createFormSubmiting"
                @submit="handleCreateRequest" @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :show="editModalTrigger" :header="'Edit Settings'" :loading="loadingModal"
        @close="handleModalClose">
        <template #modal-body>
            <UpdateMessageSettingsForm :item="itemData" :options="itemOptions" :errors="formErrors"
                :is-submitting="updateFormSubmiting" @submit="handleUpdateRequest" @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :show="bulkEditModalTrigger" :header="'Bulk Edit Device'" :loading="loadingModal"
        @close="handleBulkClose">
        <template #modal-body>
            <BulkEditDeviceForm :device="DeviceObject" @update:show="bulkEditModalTrigger = false" />
        </template>
        <template #modal-action-buttons>
            <button type="button"
                class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                @click="handleBulkSaveEdit" ref="saveButtonRef">Save
            </button>
            <button type="button"
                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                @click="handleBulkClose" ref="cancelButtonRef">Cancel
            </button>
        </template>
    </AddEditItemModal>

    <DeleteConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmDeleteAction" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

</template>

<script setup>
import { watch, ref } from "vue";
import axios from 'axios';
import { router } from "@inertiajs/vue3";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import Notification from "./components/notifications/Notification.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import DeleteConfirmationModal from "./components/modal/DeleteConfirmationModal.vue";
import UpdateMessageSettingsForm from "./components/forms/UpdateMessageSettingsForm.vue";
import CreateMessageSettingsForm from "./components/forms/CreateMessageSettingsForm.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import BulkEditDeviceForm from "./components/forms/BulkEditDeviceForm.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import Warning from "./components/icons/Warning.vue"

// import { usePage } from '@inertiajs/vue3'

// const page = usePage()

const loading = ref(false)
const loadingModal = ref(false)
const selectPageItems = ref(false);
const selectAll = ref(false);
const selectedItems = ref([]);
const restartRequestNotificationErrorTrigger = ref(false);
const addModalTrigger = ref(false);
const editModalTrigger = ref(false);
const bulkEditModalTrigger = ref(false);
const confirmationModalTrigger = ref(false);
const confirmationModalDestroyPath = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const notificationType = ref(null);
const updateFormSubmiting = ref(null);
const createFormSubmiting = ref(null);
const formErrors = ref(null);
const confirmDeleteAction = ref(null);
let tooltipCopyContent = ref('Copy to Clipboard');


const props = defineProps({
    data: Object,
    showGlobal: Boolean,
    routes: Object,
    itemData: Object,
    itemOptions: Object,
});

const filterData = ref({
    search: null,
    showGlobal: props.showGlobal,
});
const showGlobal = ref(props.showGlobal);

const bulkActions = ref([
    {
        id: 'bulk_update',
        label: 'Edit',
        icon: PencilSquareIcon
    },
    {
        id: 'bulk_delete',
        label: 'Delete',
        icon: TrashIcon
    },
]);

const handleSelectPageItems = () => {
    if (selectPageItems.value) {
        selectedItems.value = props.data.data.map(item => item.sms_destination_uuid);
    } else {
        selectedItems.value = [];
    }
};


const handleSelectAll = () => {

    axios.post(props.routes.select_all, filterData._rawValue)
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification('success', response.data.messages);

        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse();
        });

};

const handleClearSelection = () => {
    selectedItems.value = [],
        selectPageItems.value = false;
    selectAll.value = false;
}


const handleCopyToClipboard = (value) => {
    // Use regular expression to remove any non-digit characters
    value = value.replace(/\D/g, '');
    navigator.clipboard.writeText(value).then(() => {
        tooltipCopyContent.value = 'Copied'
        setTimeout(() => {
            tooltipCopyContent.value = 'Copy to Clipboard'
        }, 500);
    }).catch((error) => {
        // Handle the error case
        console.error('Failed to copy mac address:', error);
    });
}

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        confirmationModalTrigger.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    } else {
        executeBulkAction();
    }

    // axios.post(`${props.routes[action]}`, selectedItems._rawValue)
    //     .then((response) => {
    //         showNotification('success', response.data.messages);
    //         handleSearchButtonClick();
    //     }).catch((error) => {
    //         handleClearSelection();
    //         handleErrorResponse(error);

    //     });
}

const executeBulkDelete = () => {
    axios.post(`${props.routes.bulk_delete}`, { items: selectedItems.value })
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

const handleSingleItemDeleteRequest = (url) => {
    confirmationModalTrigger.value = true;
    confirmDeleteAction.value = () => executeSingleDelete(url);
}

const executeSingleDelete = (url) => {
    router.delete(url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
            if (page.props.flash.error) {
                showNotification('error', page.props.flash.error);
            }
            if (page.props.flash.message) {
                showNotification('success', page.props.flash.message);
            }
            confirmationModalTrigger.value = false;
            confirmationModalDestroyPath.value = null;
        },
        onFinish: () => {
            confirmationModalTrigger.value = false;
            confirmationModalDestroyPath.value = null;
        },
        onError: (errors) => {
            console.log(errors);
        },
    });
}

const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    showGlobal.value = true;
    handleSearchButtonClick();
    handleClearSelection();
}

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    showGlobal.value = false;
    handleSearchButtonClick();
    handleClearSelection();
}


const handleCreateButtonClick = () => {
    addModalTrigger.value = true
    formErrors.value = null;
    loadingModal.value = true

    router.get(props.routes.current_page,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: [
                'itemOptions',
            ],
            onSuccess: (page) => {
                // console.log(props.itemOptions);
                loadingModal.value = false;
            },
            onFinish: () => {
                loadingModal.value = false;
            },
            onError: (errors) => {
                console.log(errors);
            },

        });
}

const handleEditRequest = (itemUuid) => {
    editModalTrigger.value = true
    formErrors.value = null;
    loadingModal.value = true

    router.get(props.routes.current_page,
        {
            itemUuid: itemUuid,
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: [
                'itemData',
                'itemOptions',
            ],
            onSuccess: (page) => {
                // console.log(props.itemOptions);
                loadingModal.value = false;
            },
            onFinish: () => {
                loadingModal.value = false;
            },
            onError: (errors) => {
                console.log(errors);
            },

        });
}

const handleCreateRequest = (form) => {
    createFormSubmiting.value = true;
    formErrors.value = null;

    axios.post(props.routes.store, form)
        .then((response) => {
            createFormSubmiting.value = false;
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
            handleModalClose();
            handleClearSelection();
        }).catch((error) => {
            createFormSubmiting.value = false;
            handleClearSelection();
            handleFormErrorResponse();
        });

};

const handleUpdateRequest = (form) => {
    updateFormSubmiting.value = true;
    formErrors.value = null;

    axios.put(props.itemData.update_url, form)
        .then((response) => {
            updateFormSubmiting.value = false;
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
            handleModalClose();
            handleClearSelection();
        }).catch((error) => {
            updateFormSubmiting.value = false;
            handleClearSelection();
            handleFormErrorResponse(error);
        });

};

const handleFormErrorResponse = (error) => {
    if (error.response) {
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


const handleBulkEdit = () => {
    if (selectedItems.value.length > 0) {
        bulkEditModalTrigger.value = true;
        loadingModal.value = true;
        axios.get(props.routeDevicesOptions).then((response) => {
            DeviceObject.device_options.templates = response.data.templates
            DeviceObject.device_options.profiles = response.data.profiles
            DeviceObject.device_options.extensions = response.data.extensions
            loadingModal.value = false
        }).catch((error) => {
            console.error('Failed to get device data:', error);
        });
    } else {
        restartRequestNotificationErrorTrigger.value = true
    }
}

const handleSearchButtonClick = () => {
    loading.value = true;
    router.visit(props.routes.current_page, {
        data: {
            filterData: filterData._rawValue,
        },
        preserveScroll: true,
        preserveState: true,
        only: [
            "data",
            'showGlobal',
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
    handleClearSelection();
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


const renderRequestedPage = (url) => {
    loading.value = true;
    router.visit(url, {
        data: {
            filterData: filterData._rawValue,
        },
        preserveScroll: true,
        preserveState: true,
        only: ["data"],
        onSuccess: (page) => {
            loading.value = false;
        }
    });
};



const handleBulkSaveEdit = () => {
    axios.put(props.routeDevicesBulkUpdate, {
        devices: selectedItems.value,
        device_template: DeviceObject.device_template,
        device_profile_uuid: DeviceObject.device_profile_uuid
    }).then((response) => {
        handleSearchButtonClick()
        handleBulkClose()
    }).catch((error) => {
        console.error('Failed to save device data:', error);
        if (error.response.data.message) {
            handleErrorsPush(error.response.data.message)
        }
    });
}

const handleModalClose = () => {
    addModalTrigger.value = false;
    editModalTrigger.value = false;
    confirmationModalTrigger.value = false;
}

const handleBulkClose = () => {
    bulkEditModalTrigger.value = false
}

const handleDestroyConfirmation = (url) => {
    confirmationModalTrigger.value = true
    confirmationModalDestroyPath.value = url;
}

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>
