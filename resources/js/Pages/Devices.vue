<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Devices</template>

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
                <button v-if="!showGlobal" @click.prevent="handleBulkEdit()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Edit device
                </button>
                <button  type="button" @click.prevent="handleRestartSelected()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Restart selected devices
                </button>
                <button type="button" @click.prevent="handleRestartAll()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Restart all devices
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

                <TableColumnHeader header="MAC Address"
                    class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest"
                        :has-selected-items="selectedItems.length > 0" />
                    <span class="pl-4">MAC Address</span>
                </TableColumnHeader>
                <TableColumnHeader v-if="showGlobal" header="Domain"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <TableColumnHeader header="Template"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Profile" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Assigned extension"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Action" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.device_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 flex"
                        :text="row.device_address_formatted">
                        <input v-if="row.device_address" v-model="selectedItems" type="checkbox" name="action_box[]"
                            :value="row.device_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                        <div class="ml-9 cursor-pointer hover:text-gray-900"
                            @click="handleEditRequest(row.device_uuid)">
                            {{ row.device_address_formatted }}
                        </div>
                        <ejs-tooltip :content="tooltipCopyContent" position='TopLeft' class="ml-2"
                            @click="handleCopyToClipboard(row.device_address)" target="#destination_tooltip_target">
                            <div id="destination_tooltip_target">
                                <ClipboardDocumentIcon
                                    class="h-5 w-5 text-gray-500 hover:text-gray-900 pt-1 cursor-pointer" />
                            </div>
                        </ejs-tooltip>
                    </TableField>

                    <TableField v-if="showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.domain_description">
                        <ejs-tooltip :content="row.domain_name" position='TopLeft' target="#destination_tooltip_target">
                            <div id="destination_tooltip_target">
                                {{ row.domain_description }}
                            </div>
                        </ejs-tooltip>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.device_template" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.profile.device_profile_name" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                    :text="row.lines[0]?.extension?.name_formatted" />
                        
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center space-x-2 whitespace-nowrap">
                                <ejs-tooltip :content="'Edit device'" position='TopLeft'
                                    target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <DocumentTextIcon v-if="row.edit_path" @click="handleEdit(row.edit_path)"
                                            class="h-5 w-5 text-black-500 hover:text-black-900 active:h-5 active:w-5 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>
                                <ejs-tooltip :content="'Restart device'" position='TopLeft'
                                    target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <RestartIcon v-if="row.send_notify_path"
                                            @click="handleRestart(row.send_notify_path)"
                                            class="h-5 w-5 text-black-500 hover:text-black-900 active:h-5 active:w-5 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>
                                <ejs-tooltip :content="'Remove device'" position='TopLeft'
                                    target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <TrashIcon v-if="row.destroy_path"
                                            @click="handleDestroyConfirmation(row.destroy_path)"
                                            class="h-5 w-5 text-black-500 hover:text-black-900 active:h-5 active:w-5 cursor-pointer" />
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
    <NotificationSimple :show="restartRequestNotificationErrorTrigger" :isSuccess="false" :header="'Warning'"
        :text="'Please select at least one device'" @update:show="restartRequestNotificationErrorTrigger = false" />
    <NotificationSimple :show="restartRequestNotificationSuccessTrigger" :isSuccess="true" :header="'Success'"
        :text="'Restart request has been submitted'" @update:show="restartRequestNotificationSuccessTrigger = false" />
    <NotificationError :show="actionError" :errors="actionErrorsList" :header="actionErrorMessage"
        @update:show="handleErrorsReset" />
    <AddEditItemModal :show="createModalTrigger" :header="'Add New Device'" :loading="loadingModal"
        @close="handleClose">
        <template #modal-body>
            <AddEditDeviceForm :device="DeviceObject" />
        </template>
        <template #modal-action-buttons>
            <button type="button"
                class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                @click="handleSaveAdd" ref="saveButtonRef">Save
            </button>
            <button type="button"
                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                @click="handleClose" ref="cancelButtonRef">Cancel
            </button>
        </template>
    </AddEditItemModal>
    <AddEditItemModal :show="editModalTrigger" :header="'Edit Device'" :loading="loadingModal" @close="handleClose">
        <template #modal-body>
            <AddEditDeviceForm :device="DeviceObject" :isEdit="true" @update:show="editModalTrigger = false" />
        </template>
        <template #modal-action-buttons>
            <button type="button"
                class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                @click="handleSaveEdit" ref="saveButtonRef">Save
            </button>
            <button type="button"
                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                @click="handleClose" ref="cancelButtonRef">Cancel
            </button>
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
        @confirm="handleDestroy(confirmationModalDestroyPath)" />
    <NotificationError :show="actionError" :errors="actionErrorsList" :header="actionErrorMessage"
        @update:show="handleErrorsReset" />
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import axios from 'axios';
import { router } from "@inertiajs/vue3";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import NotificationSimple from "./components/notifications/Simple.vue";
import NotificationError from "./components/notifications/Error.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import DeleteConfirmationModal from "./components/modal/DeleteConfirmationModal.vue";
import AddEditDeviceForm from "./components/forms/AddEditDeviceForm.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { DocumentTextIcon } from "@heroicons/vue/24/solid";
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import BulkEditDeviceForm from "./components/forms/BulkEditDeviceForm.vue";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import RestartIcon from "./components/icons/RestartIcon.vue"

const loading = ref(false)
const loadingModal = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const restartRequestNotificationSuccessTrigger = ref(false);
const restartRequestNotificationErrorTrigger = ref(false);
const createModalTrigger = ref(false);
const editModalTrigger = ref(false);
const bulkEditModalTrigger = ref(false);
const confirmationModalTrigger = ref(false);
const confirmationModalDestroyPath = ref(null);
const actionError = ref(false);
const actionErrorsList = ref({});
const actionErrorMessage = ref(null);
const formErrors = ref(null);
let tooltipCopyContent = ref('Copy to Clipboard');

const props = defineProps({
    data: Object,
    showGlobal: Boolean,
    routes: Object,
    // routeDevicesStore: String,
    // routeDevicesOptions: String,
    // routeDevicesBulkUpdate: String,
    // routeDevices: String,
    // routeSendEventNotifyAll: String
});


const filterData = ref({
    search: null,
    showGlobal: props.showGlobal,
});

const showGlobal = ref(props.showGlobal);

let DeviceObject = reactive({
    update_path: props.routeDevicesStore,
    domain_uuid: '',
    device_uuid: '',
    device_address: '',
    extension_uuid: '',
    device_profile_uuid: '',
    device_template: '',
    device_options: {
        templates: Array,
        profiles: Array,
        extensions: Array
    }
});

const bulkActions = ref([
    {
        id: 'bulk_restart',
        label: 'Restart',
        icon: RestartIcon
    },
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

onMounted(() => {
    console.log(props.data.data);
});

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        confirmationModalTrigger.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }
    if (action === 'bulk_update') {
        formErrors.value = [];
        getItemOptions();
        loadingModal.value = true
        bulkUpdateModalTrigger.value = true;
    }
}

const handleCreateButtonClick = () => {
    createModalTrigger.value = true
    formErrors.value = null;
    loadingModal.value = true
    getItemOptions();
}

const selectedItemsExtensions = computed(() => {
    return selectedItems.value.map(id => {
        const foundItem = props.data.data.find(item => item.device_uuid === id);
        return foundItem ? foundItem.extension_uuid : null;
    });
});

const handleSelectAll = () => {
    if (selectAll.value) {
        selectedItems.value = props.data.data.map(item => item.device_uuid);
        selectedItemsExtensions.value = props.data.data.map(item => item.extension_uuid);
    } else {
        selectedItems.value = [];
        selectedItemsExtensions.value = [];
    }
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

const handleDestroy = (url) => {
    router.delete(url, {
        preserveScroll: true,
        preserveState: true,
        only: ["data"],
        onSuccess: (page) => {
            confirmationModalTrigger.value = false;
            confirmationModalDestroyPath.value = null;
        }
    });
}

const handleRestart = (url) => {
    axios.post(url).then((response) => {
        loading.value = false;
        restartRequestNotificationSuccessTrigger.value = true;
    }).catch((error) => {
        console.error('Failed to restart selected:', error);
    });
}

const handleRestartSelected = () => {
    if (selectedItemsExtensions.value.length > 0) {
        let scope = showGlobal.value ? 'global' : 'local';
        axios.post(`${props.routeSendEventNotifyAll}?scope=${scope}`, {
            extensionIds: selectedItemsExtensions.value,
        }).then((response) => {
            loading.value = false;
            restartRequestNotificationSuccessTrigger.value = true;
        }).catch((error) => {
            console.error('Failed to restart selected:', error);
        });
    } else {
        restartRequestNotificationErrorTrigger.value = true
    }
}

const handleRestartAll = () => {
    let scope = showGlobal.value ? 'global' : 'local';
    axios.post(`${props.routeSendEventNotifyAll}?scope=${scope}`).then((response) => {
        loading.value = false;
        restartRequestNotificationSuccessTrigger.value = true;
    }).catch((error) => {
        console.error('Failed to restart selected:', error);
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

const handleAdd = () => {
    DeviceObject.update_path = props.routeDevicesStore;
    axios.get(props.routeDevicesOptions).then((response) => {
        DeviceObject.device_options.templates = response.data.templates
        DeviceObject.device_options.profiles = response.data.profiles
        DeviceObject.device_options.extensions = response.data.extensions
        loadingModal.value = false
        createModalTrigger.value = true;
    }).catch((error) => {
        console.error('Failed to get device data:', error);
    });
}

const handleEdit = (url) => {
    editModalTrigger.value = true
    loadingModal.value = true
    axios.get(url).then((response) => {
        DeviceObject.domain_uuid = response.data.device.domain_uuid
        DeviceObject.update_path = response.data.device.update_path
        DeviceObject.device_uuid = response.data.device.device_uuid
        DeviceObject.device_address = response.data.device.device_address
        DeviceObject.device_profile_uuid = response.data.device.device_profile_uuid
        DeviceObject.device_template = response.data.device.device_template
        DeviceObject.extension_uuid = response.data.device.extension_uuid
        DeviceObject.device_options.templates = response.data.device.options.templates
        DeviceObject.device_options.profiles = response.data.device.options.profiles
        DeviceObject.device_options.extensions = response.data.device.options.extensions
        loadingModal.value = false
    }).catch((error) => {
        console.error('Failed to get device data:', error);
    });
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
        only: ["data"],
        onSuccess: (page) => {
            loading.value = false;
        }
    });
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    handleSearchButtonClick();
}

const handleErrorsReset = () => {
    actionError.value = false;
    actionErrorsList.value = {};
    actionErrorMessage.value = null;
}

const handleErrorsPush = (message, errors = null) => {
    actionError.value = true;
    if (errors !== null) {
        actionErrorsList.value = errors;
    } else {
        actionErrorsList.value = {};
    }
    actionErrorMessage.value = message;
}

const handleDeviceObjectReset = () => {
    DeviceObject = reactive({
        update_path: props.routeDevicesStore,
        domain_uuid: '',
        device_uuid: '',
        device_address: '',
        extension_uuid: '',
        device_profile_uuid: '',
        device_template: '',
        device_options: {
            templates: Array,
            profiles: Array,
            extensions: Array
        }
    });
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

const handleSaveAdd = () => {
    axios.post(props.routeDevicesStore, {
        device_address: DeviceObject.device_address,
        device_template: DeviceObject.device_template,
        device_profile_uuid: DeviceObject.device_profile_uuid,
        extension_uuid: DeviceObject.extension_uuid
    }).then((response) => {
        handleSearchButtonClick()
        handleClose()
    }).catch((error) => {
        console.error('Failed to add device data:', error);
        if (error.response.data.errors) {
            handleErrorsPush(error.response.data.message, error.response.data.errors)
        }
    });
}

const handleSaveEdit = () => {
    axios.put(DeviceObject.update_path, {
        domain_uuid: DeviceObject.domain_uuid,
        device_address: DeviceObject.device_address,
        device_template: DeviceObject.device_template,
        device_profile_uuid: DeviceObject.device_profile_uuid,
        extension_uuid: DeviceObject.extension_uuid
    }).then((response) => {
        handleSearchButtonClick()
        handleClose()
    }).catch((error) => {
        console.error('Failed to save device data:', error);
        console.log(error.response.data.errors)
        if (error.response.data.errors.length > 0) {
            handleErrorsPush(error.response.data.message, error.response.data.errors)
        }
    });
}

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

const getItemOptions = () => {
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

const handleClose = () => {
    createModalTrigger.value = false
    editModalTrigger.value = false
    setTimeout(handleDeviceObjectReset, 1000)
}

const handleBulkClose = () => {
    bulkEditModalTrigger.value = false
    setTimeout(handleDeviceObjectReset, 1000)
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
