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
                <button v-if="page.props.auth.can.device_create" type="button"
                    @click.prevent="handleCreateButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Create
                </button>

                <button v-if="page.props.auth.can.manage_cloud_provision_providers" type="button"
                    @click.prevent="handleCloudProvisioningButtonClick()"
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
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>
            <template #table-header>

                <TableColumnHeader header="MAC Address"
                    class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <!-- <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest"
                        :has-selected-items="selectedItems.length > 0" /> -->
                    <span class="pl-4">MAC Address</span>
                </TableColumnHeader>
                <TableColumnHeader v-if="showGlobal" header="Domain"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <TableColumnHeader header="Template"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Profile" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader v-if="!showGlobal" header="Assigned extension"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Description"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Last Contact"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Cloud" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
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
                <tr v-for="row in data.data" :key="row.device_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500"
                        :text="row.device_address_formatted">
                        <div class="flex items-center">
                            <input v-if="row.device_address" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.device_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-4"
                                :class="{ 'cursor-pointer hover:text-gray-900': page.props.auth.can.device_update, }"
                                @click="page.props.auth.can.device_update && handleEditButtonClick(row.device_uuid)">
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
                        <ejs-tooltip :content="row.domain?.domain_name" position='TopLeft'
                            target="#domain_tooltip_target">
                            <div id="domain_tooltip_target">
                                {{ row.domain?.domain_description }}
                            </div>
                        </ejs-tooltip>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.template?.name
                        ? (row.template.vendor ? `${row.template.vendor}/${row.template.name}` : row.template.name)
                        : (row.device_template || '—')" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.profile?.device_profile_name" />
                    <TableField v-if="!showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <template #default>
                            <div v-if="row.lines?.length === 0">—</div>
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
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.device_description" />

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.device_provisioned_date_formatted ?? row.device_provisioned_date" />

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <div class="flex items-center whitespace-nowrap">
                            <ejs-tooltip :content="!row.cloud_provisioning ? 'Not provisioned'
                                : (row.cloud_provisioning.status === 'success' && row.cloud_provisioning.last_action === 'register') ? 'Provisioned'
                                    : row.cloud_provisioning.status === 'pending' ? 'Pending'
                                        : row.cloud_provisioning.status === 'error' ? 'Error'
                                            : 'Not provisioned'" position='TopCenter'
                                target="#cloud_status_tooltip_target">
                                <div id="cloud_status_tooltip_target">
                                    <CloudIcon :class="[
                                        'h-9 w-9 py-2 rounded-full',
                                        !row.cloud_provisioning ? 'text-gray-300'
                                            : (row.cloud_provisioning.status === 'success' && row.cloud_provisioning.last_action === 'register') ? 'text-green-600'
                                                : row.cloud_provisioning.status === 'error' ? 'text-red-600'
                                                    : row.cloud_provisioning.status === 'pending' ? 'text-yellow-500'
                                                        : 'text-gray-300'
                                    ]" />
                                </div>
                            </ejs-tooltip>
                        </div>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap justify-end">
                                <ejs-tooltip v-if="page.props.auth.can.device_update" :content="'Edit'"
                                    position='TopCenter' target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <PencilSquareIcon @click="handleEditButtonClick(row.device_uuid)"
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
                                    <div class="relative z-20 ml-2">
                                        <AdvancedActionButton :actions="advancedActions"
                                    @advanced-action="(action) => handleAdvancedActionRequest(action, row.device_uuid)" />
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

    <NotificationSimple :show="restartRequestNotificationErrorTrigger" :isSuccess="false" :header="'Warning'"
        :text="'Please select at least one device'" @update:show="restartRequestNotificationErrorTrigger = false" />
    <NotificationSimple :show="restartRequestNotificationSuccessTrigger" :isSuccess="true" :header="'Success'"
        :text="'Restart request has been submitted'" @update:show="restartRequestNotificationSuccessTrigger = false" />

    <CreateDeviceForm :show="showCreateModal" :options="itemOptions" :loading="isModalLoading"
        :header="'Create New Device'" @close="showCreateModal = false" @error="handleErrorResponse"
        @success="showNotification" @refresh-data="handleSearchButtonClick" />

    <UpdateDeviceForm :show="showUpdateModal" :options="itemOptions" :loading="isModalLoading"
        :header="'Update Device - ' + (itemOptions?.item?.device_address_formatted ?? 'loading')"
        @close="showUpdateModal = false" @error="handleErrorResponse" @success="showNotification"
        @refresh-data="handleSearchButtonClick" />

    <BulkUpdateDeviceForm :items="selectedItems" :options="itemOptions" :show="showBulkUpdateModal"
        :header="'Bulk Update'" :loading="isModalLoading" @close="handleModalClose"
        @refresh-data="handleSearchButtonClick" />


    <CloudProvisioningSettings :show="showCloudProvisioningSettings" @close="showCloudProvisioningSettings = false"
        :header="'Cloud Provisioning Settings'" :loading="isModalLoading" :routes="routes" @error="handleErrorResponse"
        @success="showNotification" />


    <DeleteConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmDeleteAction" />

    <ConfirmationModal :show="confirmationRestartTrigger" @close="confirmationRestartTrigger = false"
        @confirm="confirmRestartAction" :header="'Are you sure?'" :text="'Confirm restart of selected devices.'"
        :confirm-button-label="'Restart'" cancel-button-label="Cancel" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

    <AddEditItemModal :show="showDuplicateModal" :header="'Duplicate Device'" :loading="isModalLoading"
        @close="showDuplicateModal = false">
        <template #modal-body>
            <div class="p-6">
                <div class="mb-4">
                    <label for="new_mac" class="block text-sm font-medium leading-6 text-gray-900">
                        New MAC Address
                    </label>
                    <div class="mt-2">
                        <input type="text" id="new_mac" v-model="newMacAddress"
                            placeholder="00:00:00:00:00:00"
                            class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                            @keydown.enter="submitDuplicateRequest" 
                        />
                    </div>
                    <div v-if="formErrors?.new_mac_address" class="mt-2 text-sm text-red-600">
                        {{ formErrors.new_mac_address[0] }}
                    </div>
                    <div v-if="formErrors?.server" class="mt-2 text-sm text-red-600">
                        {{ formErrors.server[0] }}
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-x-6">
                    <button type="button" @click="showDuplicateModal = false"
                        class="text-sm font-semibold leading-6 text-gray-900">Cancel</button>
                    <button type="button" @click="submitDuplicateRequest"
                        class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Duplicate
                    </button>
                </div>
            </div>
        </template>
    </AddEditItemModal>
    
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
import NotificationSimple from "./components/notifications/Simple.vue";
import DeleteConfirmationModal from "./components/modal/DeleteConfirmationModal.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon, CloudIcon } from "@heroicons/vue/24/solid";
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import BulkUpdateDeviceForm from "./components/forms/BulkUpdateDeviceForm.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import RestartIcon from "./components/icons/RestartIcon.vue";
import CreateDeviceForm from "./components/forms/CreateDeviceForm.vue";
import UpdateDeviceForm from "./components/forms/UpdateDeviceForm.vue";
import Notification from "./components/notifications/Notification.vue";
import CloudProvisioningSettings from "./components/forms/CloudProvisioningSettings.vue";
import AdvancedActionButton from "./components/general/AdvancedActionButton.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";

const page = usePage()
const itemOptions = ref({})
const loading = ref(false)
const isModalLoading = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const restartRequestNotificationSuccessTrigger = ref(false);
const restartRequestNotificationErrorTrigger = ref(false);
const createModalTrigger = ref(false);
const showUpdateModal = ref(false);
const showCreateModal = ref(false);
const showBulkUpdateModal = ref(false);
const confirmationModalTrigger = ref(false);
const confirmationRestartTrigger = ref(false);
const confirmDeleteAction = ref(null);
const confirmRestartAction = ref(null);
const showCloudProvisioningSettings = ref(false);
const formErrors = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const showDuplicateModal = ref(false);
const itemToDuplicate = ref(null);
const newMacAddress = ref('');
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
});


onMounted(() => {
    handleSearchButtonClick();
})

const filterData = ref({
    search: null,
    showGlobal: false,
});

const showGlobal = ref(props.showGlobal);
const advancedActions = computed(() => [
    {
        category: "Advanced",
        actions: [
            { id: 'duplicate', label: 'Duplicate', icon: 'DocumentDuplicateIcon' },
        ],
    },
]);

const handleAdvancedActionRequest = (action, uuid) => {
    if (action === 'duplicate') {
        itemToDuplicate.value = uuid;
        newMacAddress.value = ''; 
        formErrors.value = null;
        showDuplicateModal.value = true; 
    }
};

const submitDuplicateRequest = () => {
    if (!newMacAddress.value) {
        formErrors.value = { new_mac_address: ['MAC Address is required'] };
        return;
    }

    const url = props.routes.duplicate || '/devices/duplicate';
    isModalLoading.value = true;

    axios.post(url, { 
        uuid: itemToDuplicate.value,
        new_mac_address: newMacAddress.value 
    })
    .then((response) => {
        showDuplicateModal.value = false;
        showNotification('success', response.data.messages);
        handleSearchButtonClick(); 
    })
    .catch((error) => {
        handleFormErrorResponse(error); 
    })
    .finally(() => {
        isModalLoading.value = false;
    });
};

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
            label: 'Update',
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

const handleEditButtonClick = (itemUuid) => {
 //Removed to make way for checking limits:
 //    showUpdateModal.value = true
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
    confirmationModalTrigger.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
}


const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        confirmationModalTrigger.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }
    if (action === 'bulk_update') {
        getItemOptions();
        isModalLoading.value = true
        showBulkUpdateModal.value = true;
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
    showCloudProvisioningSettings.value = true
    isModalLoading.value = false
    // getCloudProvisioningItemOptions()
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
}

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    showGlobal.value = false;
    handleSearchButtonClick();
}

const getData = (page = 1) => {
    loading.value = true;

    axios.get(props.routes.data_route, {
        params: {
            filter: filterData.value,
            page,
        }
    })
        .then((response) => {
            data.value = response.data;
            // console.log(data.value);

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
        selectedItems.value = data.value.data.map(item => item.device_uuid);
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
    confirmationModalTrigger.value = false;
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
