<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Cloud Provisioning</template>

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

            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                           :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                           @pagination-change-page="renderRequestedPage" />
            </template>
            <template #table-header>

                <TableColumnHeader
                    class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                           class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest"
                                      :has-selected-items="selectedItems.length > 0" />
                    <span class="pl-4">Tenant</span>
                </TableColumnHeader>

                <TableColumnHeader header="Tenant Domain"
                                   class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Status" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
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
                <tr v-for="row in data.data" :key="row.domain_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                        <div class="flex items-center">
                            <input v-if="row.domain_uuid" v-model="selectedItems" type="checkbox" name="action_box[]"
                                   :value="row.domain_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-9">
                                <span v-if="row.domain_description" class="flex items-center">
                                    {{ row.domain_description }}
                                </span>
                                <span v-else class="flex items-center">
                                    {{ row.domain_name }}
                                </span>

                            </div>
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.domain_name" />

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.ztp_status">
                        <Badge v-if="row.ztp_status === 'true'" text="Activated" backgroundColor="bg-green-50"
                               textColor="text-green-700" ringColor="ring-green-600/20" />
                        <Badge v-else text="Inactive" backgroundColor="bg-rose-50" textColor="text-rose-700"
                               ringColor="ring-rose-600/20" />

                    </TableField>


                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap justify-end">
                                <ejs-tooltip v-if="row.ztp_status === 'true'" :content="'Edit'" position='TopCenter'
                                             target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <PencilSquareIcon @click="handleEditButtonClick(row.domain_uuid)"
                                                          class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip v-if="row.ztp_status === 'false'" :content="'Activate'"
                                             position='TopCenter' target="#restart_tooltip_target">
                                    <div id="restart_tooltip_target">
                                        <PowerIcon @click="handleActivateButtonClick(row.domain_uuid)"
                                                   class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip v-if="row.ztp_status === 'true'" :content="'Deactivate'"
                                             position='TopCenter' target="#delete_tooltip_target">
                                    <div id="delete_tooltip_target">
                                        <XCircleIcon @click="handleDeactivateButtonClick(row.domain_uuid)"
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

    <AddEditItemModal :customClass="'sm:max-w-4xl'" :show="showActivateModal" :header="'Activate ZTP Organization'"
                      :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <CreateZtpOrgForm :options="itemOptions" :errors="formErrors" :is-submitting="activateFormSubmitting"
                                   :activeTab="activationActiveTab" @submit="handleCreateRequest" @cancel="handleActivationFinish"
                                   @error="handleFormErrorResponse" @success="showNotification('success', $event)"
                                   @clear-errors="handleClearErrors" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :customClass="'sm:max-w-4xl'" :show="showEditModal" :header="'Edit ZTP Organization'"
                      :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <UpdateZtpOrgForm :options="itemOptions" :errors="formErrors" :is-submitting="updateFormSubmitting"
                                   @submit="handleUpdateRequest" @cancel="handleModalClose" @error="handleFormErrorResponse"
                                   @refresh-data="getItemOptions" @success="showNotification('success', $event)"
                                   @clear-errors="handleClearErrors" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :customClass="'sm:max-w-xl'" :show="showPairModal" :header="'Connect to existing ZTP Organization'"
                      :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <PairZtpOrganizationForm :orgs="ztpOrganizations" :selected-account="selectedAccount" :errors="formErrors" :is-submitting="pairZtpOrgSubmitting"
                                          @submit="handlePairZtpOrgRequest" @cancel="handleModalClose" @error="handleFormErrorResponse"
                                          @success="showNotification('success', $event)"/>
        </template>
    </AddEditItemModal>

    <ConfirmationModal :show="showConfirmationModal" @close="showConfirmationModal = false" @confirm="confirmDeleteAction"
                       :header="'Confirm Action'"
                       :text="'Are you sure you want to deactivate apps for this account? This action may impact account functionality.'"
                       confirm-button-label="Deactivate" cancel-button-label="Cancel" :loading="showDeactivateSpinner" />

    <ConfirmationModal :show="showZtpConfirmationModal" @close="cancelZtpAction"
                       @confirm="confirmZtpAction" :header="'Select a method to set up your Ztp organization.'"
                       :text="'Would you like to connect to an existing Ztp organization or create a new one?'"
                       confirm-button-label="Create New Organization" cancel-button-label="Connect to Existing"
                       :loading="showConnectSpinner || showCreateSpinner" :color="'blue'"/>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
                  @update:show="hideNotification" />
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
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import CreateZtpOrgForm from "./components/forms/CreateZtpOrgForm.vue";
import UpdateZtpOrgForm from "./components/forms/UpdateZtpOrgForm.vue";
import PairZtpOrganizationForm from "./components/forms/PairZtpOrganizationForm.vue";
import Notification from "./components/notifications/Notification.vue";
import Badge from "@generalComponents/Badge.vue";
import { PowerIcon } from "@heroicons/vue/24/outline";
import { XCircleIcon } from "@heroicons/vue/24/outline";

const page = usePage()
const loading = ref(false)
const loadingModal = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const showActivateModal = ref(false);
const showEditModal = ref(false);
const showApiTokenModal = ref(false);
const showPairModal = ref(false);
const bulkUpdateModalTrigger = ref(false);
const showConfirmationModal = ref(false);
const showZtpConfirmationModal = ref(false);
const activateFormSubmitting = ref(null);
const activationActiveTab = ref('organization');
const updateFormSubmitting = ref(null);
const pairZtpOrgSubmitting = ref(null);
const confirmDeleteAction = ref(null);
const showDeactivateSpinner = ref(null);
const showConnectSpinner = ref(null);
const showCreateSpinner = ref(null);
const confirmZtpAction = ref(null);
const cancelZtpAction = ref(null);
const formErrors = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);

const props = defineProps({
    data: Object,
    routes: Object,
    itemData: Object,
});


const filterData = ref({
    search: null,
    showGlobal: props.showGlobal,
});

const itemOptions = ref({})
const ztpOrganizations = ref({})
const selectedAccount =  ref(null)
const apiToken = ref(null)

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    return [
        // {
        //     id: 'bulk_update',
        //     label: 'Edit',
        //     icon: 'PencilSquareIcon'
        // }
    ];
});

onMounted(() => {
});

const handleActivateButtonClick = (itemUuid) => {
    showZtpConfirmationModal.value = true;
    confirmZtpAction.value = () => executeNewZtpOrgAction(itemUuid);
    cancelZtpAction.value = () => executeExistingZtpOrgAction(itemUuid);
};

const executeNewZtpOrgAction = (itemUuid) => {
    activationActiveTab.value = 'organization';
    showZtpConfirmationModal.value = false;
    showActivateModal.value = true
    formErrors.value = null;
    loadingModal.value = true
    getItemOptions(itemUuid);
}

const executeExistingZtpOrgAction = (itemUuid) => {
    showZtpConfirmationModal.value = false;
    showPairModal.value = true
    loadingModal.value = true
    selectedAccount.value = itemUuid;
    getZtpOrganizations(itemUuid);
}

const handleEditButtonClick = (itemUuid) => {
    showEditModal.value = true
    formErrors.value = null;
    loadingModal.value = true
    getItemOptions(itemUuid);
}

const handleCreateRequest = (form) => {
    activateFormSubmitting.value = true;
    formErrors.value = null;

    axios.post(props.routes.create_organization, form)
        .then((response) => {
            activateFormSubmitting.value = false;
            showNotification('success', response.data.messages);
            itemOptions.value.orgId = response.data.org_id;
            activationActiveTab.value = 'connections';

        }).catch((error) => {
        activateFormSubmitting.value = false;
        handleClearSelection();
        handleFormErrorResponse(error);
    });

};

const handleUpdateRequest = (form) => {
    updateFormSubmitting.value = true;
    formErrors.value = null;

    axios.put(props.routes.update_organization, form)
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

const handlePairZtpOrgRequest = (form) => {
    pairZtpOrgSubmitting.value = true;
    formErrors.value = null;

    axios.post(props.routes.pair_organization, form)
        .then((response) => {
            pairZtpOrgSubmitting.value = false;
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
            handleModalClose();
            handleClearSelection();
        }).catch((error) => {
        pairZtpOrgSubmitting.value = false;
        handleClearSelection();
        handleFormErrorResponse(error);
    });

};

const handleDeactivateButtonClick = (uuid) => {
    showConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeSingleDelete(uuid);
}

const executeSingleDelete = (uuid) => {
    showDeactivateSpinner.value = true;

    axios.post(props.routes.destroy_organization, { domain_uuid: uuid })
        .then((response) => {
            showDeactivateSpinner.value = false;
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
            handleModalClose();
            handleClearSelection();
        }).catch((error) => {
        showDeactivateSpinner.value = false;
        handleClearSelection();
        handleModalClose();
        handleSearchButtonClick();
        handleFormErrorResponse(error);
    });
}

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        showConfirmationModal.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }
    if (action === 'bulk_update') {
        formErrors.value = [];
        getItemOptions();
        loadingModal.value = true
        bulkUpdateModalTrigger.value = true;
    }

}

const handleApiTokenButtonClick = () => {
    showApiTokenModal.value = true
    loadingModal.value = true
    getApiToken();
}

const handleActivationFinish = () => {
    handleModalClose();
    handleSearchButtonClick();
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
            filterData: filterData._rawValue,
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


const getItemOptions = (itemUuid = null) => {
    const payload = itemUuid ? { item_uuid: itemUuid } : {}; // Conditionally add itemUuid to payload
console.log('asdasdasd');
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

const getZtpOrganizations = (itemUuid = null) => {
    const payload = itemUuid ? { item_uuid: itemUuid } : {};

    axios.post(props.routes.get_all_orgs, payload)
        .then((response) => {
            loadingModal.value = false;
            ztpOrganizations.value = response.data;
            // console.log(itemOptions.value);

        }).catch((error) => {
        handleModalClose();
        handleErrorResponse(error);
    });
}

const handleClearErrors = () => {
    formErrors.value = null;
}

const getApiToken = () => {
    axios.post(props.routes.get_api_token)
        .then((response) => {
            loadingModal.value = false;
            apiToken.value = response.data.token;
            // console.log(apiToken.value);

        }).catch((error) => {
        handleModalClose();
        handleErrorResponse(error);
    });
}


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
        selectedItems.value = props.data.data.map(item => item.voicemail_uuid);
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
    showActivateModal.value = false;
    showEditModal.value = false,
        showApiTokenModal.value = false;
    showZtpConfirmationModal.value = false;
    showConfirmationModal.value = false;
    bulkUpdateModalTrigger.value = false;
    showPairModal.value = false;
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
