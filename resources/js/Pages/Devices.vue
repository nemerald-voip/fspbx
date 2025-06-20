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
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                    <input type="text" v-model="filterData.search" name="desktop-search-candidate"
                        id="desktop-search-candidate"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <button v-if="page.props.auth.can.device_create" type="button" @click.prevent="handleCreateButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Create
                </button>

                <button v-if="page.props.auth.can.cloud_provisioning_list_view" type="button" @click.prevent="handleCloudProvisioningButtonClick()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Cloud
                </button>

                <a v-if="page.props.auth.can.device_profile_index" type="button" href="app/devices/device_profiles.php"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Profiles
                </a>

                <button v-if="!showGlobal && page.props.auth.can.device_view_global" type="button"
                    @click.prevent="handleShowGlobal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show global
                </button>

                <button v-if="showGlobal && page.props.auth.can.device_view_global" type="button"
                    @click.prevent="handleShowLocal()"
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

                <TableColumnHeader header="Template" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Profile" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Assigned extension"
                                   class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Cloud Provisioning"
                                   class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
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
                <tr v-for="row in data.data" :key="row.device_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500"
                        :text="row.device_address_formatted">
                        <div class="flex items-center">
                            <input v-if="row.device_address" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.device_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-9"
                                :class="{ 'cursor-pointer hover:text-gray-900': page.props.auth.can.device_update, }"
                                @click="page.props.auth.can.device_update && handleEditRequest(row.device_uuid)">
                                {{ row.device_address_formatted }}
                            </div>
                            <ejs-tooltip :content="tooltipCopyContent" position='TopLeft' class="ml-2"
                                @click="handleCopyToClipboard(row.device_address)" target="#copy_tooltip_target">
                                <div id="copy_tooltip_target">
                                    <ClipboardDocumentIcon
                                        class="h-5 w-5 text-gray-500 hover:text-gray-900 pt-1 cursor-pointer" />
                                </div>
                            </ejs-tooltip>
                        </div>
                    </TableField>

                    <TableField v-if="showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.domain?.domain_description">
                        <ejs-tooltip :content="row.domain?.domain_name" position='TopLeft' target="#domain_tooltip_target">
                            <div id="domain_tooltip_target">
                                {{ row.domain?.domain_description }}
                            </div>
                        </ejs-tooltip>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.device_template" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.profile?.device_profile_name" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <template #default>
                            <div v-if="row.lines.length === 0">—</div>
                            <div v-else>
                                <div v-for="line in [...row.lines].sort((a, b) => Number(a.line_number) - Number(b.line_number))"
                                    :key="line.device_line_uuid">
                                    <span v-if="row.lines.length > 1" class="font-semibold">
                                        Line {{ line.line_number }}:
                                    </span>
                                    <span>{{ line.extension?.name_formatted || line.auth_id }}</span>
                                </div>
                            </div>
                        </template>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <ejs-tooltip :content="deviceProvisionStatus[row.device_uuid] === 'provisioned' ? 'Provisioned' : deviceProvisionStatus[row.device_uuid] === 'pending' ? 'Pending': deviceProvisionStatus[row.device_uuid] === 'error' ? 'Error' : 'Not provisioned'" position='BottomLeft'
                                     target="#cloud_status_tooltip_target" >
                            <div id="cloud_status_tooltip_target">
                                <CloudIcon
                                    :class="[
                                        'h-9 w-9 py-2 rounded-full',
                                        deviceProvisionStatus[row.device_uuid] === 'provisioned' ? 'text-green-600' :
                                        deviceProvisionStatus[row.device_uuid] === 'error' ? 'text-red-600' :
                                        deviceProvisionStatus[row.device_uuid] === 'pending' ? 'text-yellow-600' : 'text-gray-300'
                                    ]"
                                />
                            </div>
                        </ejs-tooltip>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap">
                                <ejs-tooltip v-if="page.props.auth.can.device_update" :content="'Edit'" position='TopCenter'
                                    target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <PencilSquareIcon @click="handleEditRequest(row.device_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip :content="'Restart device'" position='TopCenter'
                                    target="#restart_tooltip_target">
                                    <div id="restart_tooltip_target">
                                        <RestartIcon @click="handleRestart(row.device_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip v-if="page.props.auth.can.device_destroy" :content="'Delete'"
                                    position='TopCenter' target="#delete_tooltip_target">
                                    <div id="delete_tooltip_target">
                                        <TrashIcon @click="handleSingleItemDeleteRequest(row.device_uuid)"
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

    <NotificationSimple :show="restartRequestNotificationErrorTrigger" :isSuccess="false" :header="'Warning'"
        :text="'Please select at least one device'" @update:show="restartRequestNotificationErrorTrigger = false" />
    <NotificationSimple :show="restartRequestNotificationSuccessTrigger" :isSuccess="true" :header="'Success'"
        :text="'Restart request has been submitted'" @update:show="restartRequestNotificationSuccessTrigger = false" />


    <AddEditItemModal :customClass="'sm:max-w-6xl'" :show="createModalTrigger" :header="'Add New'" :loading="loadingModal"
        @close="handleModalClose">
        <template #modal-body>
            <CreateDeviceForm
                :options="itemOptions"
                :errors="formErrors"
                :is-submitting="createFormSubmitting"
                @submit="handleCreateRequest"
                @cancel="handleModalClose"
            />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :customClass="'sm:max-w-6xl'" :show="editModalTrigger" :header="'Edit Device'" :loading="loadingModal"
        @close="handleModalClose">
        <template #modal-body>
            <UpdateDeviceForm
                :item="itemData"
                :options="itemOptions"
                :errors="formErrors"
                :is-submitting="updateFormSubmitting"
                @submit="handleUpdateRequest"
                @cancel="handleModalClose"
                @domain-selected="getItemOptions"
            />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :customClass="'sm:max-w-6xl'" :show="bulkUpdateModalTrigger" :header="'Bulk Edit'"
        :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <BulkUpdateDeviceForm :items="selectedItems" :options="itemOptions" :errors="formErrors"
                :is-submitting="bulkUpdateFormSubmitting" @submit="handleBulkUpdateRequest" @cancel="handleModalClose"
                @domain-selected="getItemOptions" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :customClass="'sm:max-w-6xl'" :show="cloudProvisioningModalTrigger" :header="'Cloud Provisioning'" :loading="loadingModal"
                      @close="handleModalClose">
        <template #modal-body>
            <CloudProvisioningListing
                :options="itemOptions"
                :routes="routes"
                :canEditPolycomToken="page.props.auth.can.polycom_api_token_edit"
                @notification:show="showNotification"
            />
        </template>
    </AddEditItemModal>

    <DeleteConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmDeleteAction" />

    <ConfirmationModal :show="confirmationRestartTrigger" @close="confirmationRestartTrigger = false"
        @confirm="confirmRestartAction" :header="'Are you sure?'" :text="'Confirm restart of selected devices.'"
        :confirm-button-label="'Restart'" cancel-button-label="Cancel" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import {computed, onMounted, onBeforeUnmount, ref, watch} from "vue";
import { usePage } from '@inertiajs/vue3'
import axios from 'axios';
import { router } from "@inertiajs/vue3";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import NotificationSimple from "./components/notifications/Simple.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import DeleteConfirmationModal from "./components/modal/DeleteConfirmationModal.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon, CloudIcon } from "@heroicons/vue/24/solid";
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import BulkUpdateDeviceForm from "./components/forms/BulkUpdateDeviceForm.vue";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import RestartIcon from "./components/icons/RestartIcon.vue";
import CreateDeviceForm from "./components/forms/CreateDeviceForm.vue";
import UpdateDeviceForm from "./components/forms/UpdateDeviceForm.vue";
import Notification from "./components/notifications/Notification.vue";
import CloudProvisioningListing from "./components/listings/CloudProvisioningListing.vue";

const page = usePage()
const loading = ref(false)
const loadingModal = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const restartRequestNotificationSuccessTrigger = ref(false);
const restartRequestNotificationErrorTrigger = ref(false);
const createModalTrigger = ref(false);
const editModalTrigger = ref(false);
const bulkUpdateModalTrigger = ref(false);
const confirmationModalTrigger = ref(false);
const confirmationRestartTrigger = ref(false);
const confirmationModalDestroyPath = ref(null);
const createFormSubmitting = ref(null);
const updateFormSubmitting = ref(null);
const confirmDeleteAction = ref(null);
const confirmRestartAction = ref(null);
const bulkUpdateFormSubmitting = ref(null);
const cloudProvisioningModalTrigger = ref(false);
const formErrors = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const deviceProvisionStatus = ref({});
const deviceProvisionStatusCheckInterval = ref(null);
const availableDomains = ref([]);
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

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [
        {
            id: 'bulk_restart',
            label: 'Restart',
            icon: 'RestartIcon'
        },
        {
            id: 'bulk_update',
            label: 'Edit',
            icon: 'PencilSquareIcon'
        }
    ];

    // Conditionally add the delete action if permission is granted
    if (page.props.auth.can.device_destroy) {
        actions.push({
            id: 'bulk_delete',
            label: 'Delete',
            icon: 'TrashIcon'
        });
    }

    return actions;
});

onBeforeUnmount(() => {
    stopStatusWatching();
});

onMounted(() => {
    handleUpdateCloudProvisioningStatuses();
});

watch(
    () => deviceProvisionStatus.value,
    (newStatuses) => {
        // If any device status is still "pending", start watching
        if (Object.values(newStatuses).includes("pending")) {
            startStatusWatching(); // Start the polling process
        } else {
            stopStatusWatching(); // Stop polling if no device is "pending"
        }
    },
    { deep: true } // Watch deeply to react to changes inside the object
);

const startStatusWatching = () => {
    if (!deviceProvisionStatusCheckInterval.value) {
        // Avoid starting multiple intervals
        deviceProvisionStatusCheckInterval.value = setInterval(() => {
            handleUpdateCloudProvisioningStatuses();
        }, 5000);
    }
};

// Function to stop watching when no statuses are pending
const stopStatusWatching = () => {
    if (deviceProvisionStatusCheckInterval.value) {
        clearInterval(deviceProvisionStatusCheckInterval.value);
        deviceProvisionStatusCheckInterval.value = null;
    }
};

const handleUpdateCloudProvisioningStatuses = () => {
    const deviceUuids = props.data.data.map(device => device.device_uuid);
    axios.post(props.routes.cloud_provisioning_status, {items: deviceUuids})
        .then(response => {
            if (response.data.status) {
                deviceProvisionStatus.value = response.data.devicesData.reduce(
                    (acc, device) => {
                        acc[device.device_uuid] =
                            device.status || (device.error ? "error" : "not_provisioned");
                        return acc;
                    },
                    {}
                );
            }
        })
        .catch(error => {
            console.warn('Failed to fetch cloud provisioning statuses:', error);
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
    createFormSubmitting.value = true;
    formErrors.value = null;

    axios.post(props.routes.store, form)
        .then((response) => {
            createFormSubmitting.value = false;
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
            handleModalClose();
            handleClearSelection();
        }).catch((error) => {
            createFormSubmitting.value = false;
            handleClearSelection();
            handleFormErrorResponse(error);
        });

};

const handleUpdateRequest = (form) => {
    updateFormSubmitting.value = true;
    formErrors.value = null;

    axios.put(props.itemData.update_url, form)
        .then((response) => {
            updateFormSubmitting.value = false;
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
            handleModalClose();
            handleClearSelection();
        }).catch((error) => {
            updateFormSubmitting.value = false;
            handleClearSelection();
            handleFormErrorResponse(error);
        });

};

const handleSingleItemDeleteRequest = (uuid) => {
    confirmationModalTrigger.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
}

// const executeSingleDelete = (url) => {
//     router.delete(url, {
//         preserveScroll: true,
//         preserveState: true,
//         onSuccess: (page) => {
//             if (page.props.flash.error) {
//                 showNotification('error', page.props.flash.error);
//             }
//             if (page.props.flash.message) {
//                 showNotification('success', page.props.flash.message);
//             }
//             confirmationModalTrigger.value = false;
//             confirmationModalDestroyPath.value = null;
//         },
//         onFinish: () => {
//             confirmationModalTrigger.value = false;
//             confirmationModalDestroyPath.value = null;
//         },
//         onError: (errors) => {
//             console.log(errors);
//         },
//     });
// }

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
    if (action === 'bulk_restart') {
        confirmationRestartTrigger.value = true;
        confirmRestartAction.value = () => executeBulkRestart();
    }
}

const executeBulkRestart = () => {
    axios.post(props.routes.restart,
        { 'devices': selectedItems.value },
    )
        .then((response) => {
            showNotification('success', response.data.messages);
            handleModalClose();
            handleClearSelection();
        }).catch((error) => {
            handleClearSelection();
            handleModalClose();
            handleFormErrorResponse(error);
        });
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

const handleBulkUpdateRequest = (form) => {
    bulkUpdateFormSubmitting.value = true
    axios.post(`${props.routes.bulk_update}`, form)
        .then((response) => {
            bulkUpdateFormSubmitting.value = false;
            handleModalClose();
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
        })
        .catch((error) => {
            bulkUpdateFormSubmitting.value = false;
            handleFormErrorResponse(error);
        });
}

const handleCreateButtonClick = () => {
    createModalTrigger.value = true
    formErrors.value = null;
    loadingModal.value = true
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

const handleCloudProvisioningButtonClick = () => {
    cloudProvisioningModalTrigger.value = true
    loadingModal.value = true
    formErrors.value = null;
    getItemOptions();
        /*axios.post(props.routes.cloud_provisioning_item_options, {}).then(response => {

            availableDomains.value = response.data.tenants || [];
            console.log(availableDomains.value)
            loadingModal.value = false
            //
            //formErrors.value = null;
            //
            //getItemOptions();
        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });*/
    //getAvailableDomains();
}


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


const handleRestart = (device_uuid) => {
    axios.post(props.routes.restart,
        { 'devices': [device_uuid] },
    )
        .then((response) => {
            showNotification('success', response.data.messages);

            handleClearSelection();
        }).catch((error) => {
            handleClearSelection();
            handleFormErrorResponse(error);
        });
}


const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    showGlobal.value = true;
    handleSearchButtonClick();
    handleUpdateCloudProvisioningStatuses();
}

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    showGlobal.value = false;
    handleSearchButtonClick();
    handleUpdateCloudProvisioningStatuses();
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
            console.log('Search clicked')
            handleUpdateCloudProvisioningStatuses();
        }
    });
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    handleSearchButtonClick();
    handleUpdateCloudProvisioningStatuses();
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


const getItemOptions = (domain_uuid) => {
    router.get(props.routes.current_page,
        {
            'domain_uuid': domain_uuid,
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: [
                'itemOptions',
            ],
            onSuccess: (page) => {
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

const getAvailableDomains = () => {
    router.get(props.routes.cloud_provisioning_domains,
        {

        },
        {
            preserveScroll: true,
            preserveState: true,
            only: [
                'availableDomains',
            ],
            onSuccess: (page) => {
                loadingModal.value = false;
            },
            onFinish: () => {
                loadingModal.value = false;
            },
            onError: (errors) => {
                console.log(errors);
            },

        });

   /* try {
        const response = await axios.post(props.routes.cloud_provisioning_domains, {});
        availableDomains.value = response.data.data || [];
    } catch (error) {
        console.error(error);
    }*/
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
        selectedItems.value = props.data.data.map(item => item.device_uuid);
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
    editModalTrigger.value = false;
    confirmationModalTrigger.value = false;
    confirmationRestartTrigger.value = false;
    bulkUpdateModalTrigger.value = false;
    cloudProvisioningModalTrigger.value = false;
    handleUpdateCloudProvisioningStatuses();
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
