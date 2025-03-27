<template>
    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
        <aside class="px-2 py-6 sm:px-6 lg:col-span-2 lg:px-0 lg:py-0">
            <nav class="space-y-1">
                <a v-for="item in options.cloud_providers" :key="item.name" href="#"
                   :class="[activeTab === item.slug ? 'bg-gray-200 text-indigo-700 hover:bg-gray-100 hover:text-indigo-700' : 'text-gray-900 hover:bg-gray-200 hover:text-gray-900', 'group flex items-center rounded-md px-3 py-2 text-sm font-medium']"
                   @click.prevent="setActiveTab(item.slug)" :aria-current="item.current ? 'page' : undefined">
                    <component :is="iconComponents[item.icon]"
                               :class="[item.current ? 'text-indigo-500 group-hover:text-indigo-500' : 'text-gray-400 group-hover:text-gray-500', '-ml-1 mr-3 h-6 w-6 flex-shrink-0']"
                               aria-hidden="true"/>
                    <span class="truncate">{{ item.name }}</span>
                </a>
            </nav>

        </aside>
        <div v-if="activeTab === 'polycom'" class="lg:col-span-10">
            <div class="shadow sm:rounded-md">
                <div class="bg-gray-50 pb-6">
                    <div class="flex justify-between items-center p-8 pb-0">
                        <h3 class="text-base font-semibold leading-6 text-gray-900">Tenants List</h3>
                        <button v-if="canEditPolycomToken" type="button" @click.prevent="handlePolycomApiTokenButtonClick()"
                                class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            API Token
                        </button>
                    </div>
                    <DataTable>
                        <template #table-header>
                            <TableColumnHeader header="Tenant"
                                               class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                            </TableColumnHeader>
                            <TableColumnHeader header="Tenant Domain"
                                               class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"/>
                            <TableColumnHeader header="Status"
                                               class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"/>
                            <TableColumnHeader header=""
                                               class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900"/>
                        </template>

                        <template v-if="availableTenants" #table-body>
                            <tr v-for="row in availableTenants.data" :key="row.domain_uuid">
                                <TableField class="whitespace-nowrap px-4 text-sm text-gray-500">
                                    <div class="flex items-center">
                                        <span v-if="row.domain_description" class="flex items-center">
                                            {{ row.domain_description }}
                                        </span>
                                        <span v-else class="flex items-center">
                                            {{ row.domain_name }}
                                        </span>
                                    </div>
                                </TableField>

                                <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                                            :text="row.domain_name"/>

                                <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                                            :text="row.ztp_status">
                                    <Badge v-if="row.ztp_status === 'true'" text="Activated"
                                           backgroundColor="bg-green-50"
                                           textColor="text-green-700" ringColor="ring-green-600/20"/>
                                    <Badge v-else text="Inactive" backgroundColor="bg-rose-50" textColor="text-rose-700"
                                           ringColor="ring-rose-600/20"/>

                                </TableField>


                                <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                                    <template #action-buttons>
                                        <div class="flex items-center whitespace-nowrap justify-end">
                                            <ejs-tooltip v-if="row.ztp_status === 'true'" :content="'Edit'"
                                                         position='TopCenter'
                                                         target="#destination_tooltip_target">
                                                <div id="destination_tooltip_target">
                                                    <PencilSquareIcon @click="handleEditButtonClick(row.domain_uuid)"
                                                                      class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"/>

                                                </div>
                                            </ejs-tooltip>

                                            <ejs-tooltip v-if="row.ztp_status === 'false'" :content="'Activate'"
                                                         position='TopCenter' target="#restart_tooltip_target">
                                                <div id="restart_tooltip_target">
                                                    <PowerIcon @click="handleActivateButtonClick(row.domain_uuid)"
                                                               class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"/>
                                                </div>
                                            </ejs-tooltip>

                                            <ejs-tooltip v-if="row.ztp_status === 'true'" :content="'Deactivate'"
                                                         position='TopCenter' target="#delete_tooltip_target">
                                                <div id="delete_tooltip_target">
                                                    <XCircleIcon @click="handleDeactivateButtonClick(row.domain_uuid)"
                                                                 class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"/>
                                                </div>
                                            </ejs-tooltip>
                                        </div>
                                    </template>
                                </TableField>
                            </tr>
                        </template>
                        <template v-if="availableTenants" #empty>
                            <!-- Conditional rendering for 'no records' message -->
                            <div v-if="availableTenants.data.length === 0"
                                 class="text-center my-5 ">
                                <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400"/>
                                <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Adjust your search and try again.
                                </p>
                            </div>
                        </template>

                        <!--
                        <template #loading>
                            <Loading :show="loading" />
                        </template>
                        -->
                        <template v-if="availableTenants" #footer>
                            <Paginator :previous="availableTenants.prev_page_url"
                                       :next="availableTenants.next_page_url"
                                       :from="availableTenants.from"
                                       :to="availableTenants.to"
                                       :total="availableTenants.total"
                                       :currentPage="availableTenants.current_page"
                                       :lastPage="availableTenants.last_page"
                                       :links="availableTenants.links"
                                       @pagination-change-page="renderRequestedPage" />
                        </template>

                    </DataTable>
                </div>
            </div>
        </div>
    </div>

    <AddEditItemModal :customClass="'sm:max-w-4xl'" :show="showActivateModal" :header="'Activate Polycom Organization'"
                      :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <CreatePolycomOrgForm :options="itemOptions" :errors="formErrors" :is-submitting="activateFormSubmitting"
                                  :activeTab="activationActiveTab" @submit="handleCreateRequest"
                                  @cancel="handleActivationFinish"
                                  @error="handleFormErrorResponse" @success="showNotification('success', $event)"
                                  @clear-errors="handleClearErrors"/>
        </template>
    </AddEditItemModal>

    <AddEditItemModal :customClass="'sm:max-w-4xl'" :show="showEditModal" :header="'Edit Polycom Organization'"
                      :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <UpdatePolycomOrgForm :options="itemOptions" :errors="formErrors" :is-submitting="updateFormSubmitting"
                                  @submit="handleUpdateRequest" @cancel="handleModalClose"
                                  @error="handleFormErrorResponse"
                                  @refresh-data="getItemOptions" @success="showNotification('success', $event)"
                                  @clear-errors="handleClearErrors"/>
        </template>
    </AddEditItemModal>

    <AddEditItemModal :customClass="'sm:max-w-xl'" :show="showPairModal"
                      :header="'Connect to existing ZTP Organization'"
                      :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <PairPolycomOrganizationForm :orgs="ztpOrganizations" :selected-account="selectedAccount"
                                         :errors="formErrors" :is-submitting="pairZtpOrgSubmitting"
                                         @submit="handlePairZtpOrgRequest" @cancel="handleModalClose"
                                         @error="handleFormErrorResponse"
                                         @success="showNotification('success', $event)"/>
        </template>
    </AddEditItemModal>

    <AddEditItemModal :customClass="'sm:max-w-xl'" :show="showApiTokenModal" :header="'Polycom Api Token'"
                      :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <UpdatePolycomApiTokenForm :token="apiToken" :errors="formErrors" :is-submitting="updateApiTokenFormSubmitting"
                                       @submit="handleUpdateApiTokenRequest" @cancel="handleModalClose" @error="handleFormErrorResponse"
                                       @refresh-data="getItemOptions" @success="showNotification('success', $event)"
                                       @clear-errors="handleClearErrors" />
        </template>
    </AddEditItemModal>

    <ConfirmationModal :show="showConfirmationModal" @close="showConfirmationModal = false" @confirm="confirmDeleteAction"
                       :header="'Confirm Action'"
                       :text="'Are you sure you want to deactivate apps for this account? This action may impact account functionality.'"
                       confirm-button-label="Deactivate" cancel-button-label="Cancel" :loading="showDeactivateSpinner" />

    <ConfirmationModal :show="showPolycomConfirmationModal" @close="cancelPolycomAction"
                       @confirm="confirmPolycomAction" :header="'Select a method to set up your Polycom organization.'"
                       :text="'Would you like to connect to an existing Polycom organization or create a new one?'"
                       confirm-button-label="Create New Organization" cancel-button-label="Connect to Existing"
                       :loading="showConnectSpinner || showCreateSpinner" :color="'blue'"/>
</template>

<script setup>
import {CloudIcon, PowerIcon, XCircleIcon} from "@heroicons/vue/24/outline/index.js";
import {onMounted, ref} from "vue";
import TableField from "../general/TableField.vue";
import {MagnifyingGlassIcon, PencilSquareIcon} from "@heroicons/vue/24/solid/index.js";
import DataTable from "../general/DataTable.vue";
import TableColumnHeader from "../general/TableColumnHeader.vue";
import Paginator from "../general/Paginator.vue";
//import Loading from "../general/Loading.vue";
import Badge from "@generalComponents/Badge.vue";
import {TooltipComponent as EjsTooltip} from "@syncfusion/ej2-vue-popups";
import axios from "axios";
import PairPolycomOrganizationForm from "../forms/PairPolycomOrganizationForm.vue";
import AddEditItemModal from "../modal/AddEditItemModal.vue";
import UpdatePolycomOrgForm from "../forms/UpdatePolycomOrgForm.vue";
import CreatePolycomOrgForm from "../forms/CreatePolycomOrgForm.vue";
import ConfirmationModal from "../modal/ConfirmationModal.vue";
import UpdatePolycomApiTokenForm from "../forms/UpdatePolycomApiTokenForm.vue";

const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    routes: Object,
    errors: Object,
    canEditPolycomToken: Boolean,
});

const loading = ref(true)
const loadingModal = ref(false)
const showActivateModal = ref(false);
const showEditModal = ref(false);
const showApiTokenModal = ref(false);
const showPairModal = ref(false);
const bulkUpdateModalTrigger = ref(false);
const showConfirmationModal = ref(false);
const showPolycomConfirmationModal = ref(false);
const activateFormSubmitting = ref(null);
const activationActiveTab = ref('organization');
const updateFormSubmitting = ref(null);
const pairZtpOrgSubmitting = ref(null);
const updateApiTokenFormSubmitting = ref(null);
const confirmDeleteAction = ref(null);
const showDeactivateSpinner = ref(null);
const showConnectSpinner = ref(null);
const showCreateSpinner = ref(null);
const confirmPolycomAction = ref(null);
const cancelPolycomAction = ref(null);
const formErrors = ref(null);
const ztpOrganizations = ref({})
const selectedAccount = ref(null)
const itemOptions = ref({})
const availableTenants = ref(null);
const apiToken = ref(null)

const filterData = ref({
    search: null,
    showGlobal: props.showGlobal,
});

const emit = defineEmits(["notification:show"]);

const activeTab = ref(
    props?.options?.cloud_providers?.find(item => item?.slug)?.slug || ''
);
const iconComponents = {
    'CloudIcon': CloudIcon,
};

onMounted(() => {
    handleRefreshTenantsClick();
})

const setActiveTab = (tabSlug) => {
    activeTab.value = tabSlug;
};

const handleRefreshTenantsClick = () => {
    //loading.value = true;
    axios.post(props.routes.cloud_provisioning_item_options, {})
        .then((response) => {
            availableTenants.value = response.data.tenants;
            //loading.value = false;

        }).catch((error) => {
        handleModalClose();
        handleErrorResponse(error);
    });
}

const handleActivateButtonClick = (itemUuid) => {
    showPolycomConfirmationModal.value = true;
    confirmPolycomAction.value = () => executeNewZtpOrgAction(itemUuid);
    cancelPolycomAction.value = () => executeExistingZtpOrgAction(itemUuid);
};

const executeNewZtpOrgAction = (itemUuid) => {
    activationActiveTab.value = 'organization';
    showPolycomConfirmationModal.value = false;
    showActivateModal.value = true
    formErrors.value = null;
    loadingModal.value = true
    getItemOptions(itemUuid);
}

const executeExistingZtpOrgAction = (itemUuid) => {
    showPolycomConfirmationModal.value = false;
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

    axios.post(props.routes.cloud_provisioning_create_organization, form)
        .then((response) => {
            activateFormSubmitting.value = false;
            showNotification('success', response.data.messages);
            handleModalClose();
            handleRefreshTenantsClick();
        }).catch((error) => {
        activateFormSubmitting.value = false;
        handleRefreshTenantsClick();
        handleModalClose();
        handleFormErrorResponse(error);
    });

};

const handleUpdateRequest = (form) => {
    updateFormSubmitting.value = true;
    formErrors.value = null;

    axios.put(props.routes.cloud_provisioning_update_organization, form)
        .then((response) => {
            updateFormSubmitting.value = false;
            showNotification('success', response.data.messages);
            handleModalClose();
            handleRefreshTenantsClick();
        }).catch((error) => {
        updateFormSubmitting.value = false;
        handleRefreshTenantsClick();
        handleFormErrorResponse(error);
    });

};

const handleUpdateApiTokenRequest = (form) => {
    updateApiTokenFormSubmitting.value = true;
    formErrors.value = null;

    axios.post(props.routes.cloud_provisioning_update_api_token, form)
        .then((response) => {
            updateApiTokenFormSubmitting.value = false;
            showNotification('success', response.data.messages);
            handleModalClose();
            handleRefreshTenantsClick();
        }).catch((error) => {
        updateApiTokenFormSubmitting.value = false;
        handleRefreshTenantsClick();
        handleFormErrorResponse(error);
    });

};

const handlePairZtpOrgRequest = (form) => {
    pairZtpOrgSubmitting.value = true;
    formErrors.value = null;

    axios.post(props.routes.cloud_provisioning_pair_organization, form)
        .then((response) => {
            pairZtpOrgSubmitting.value = false;
            showNotification('success', response.data.messages);
            //   handleSearchButtonClick();
            handleModalClose();
            handleRefreshTenantsClick();
        }).catch((error) => {
        pairZtpOrgSubmitting.value = false;
        handleRefreshTenantsClick();
        handleFormErrorResponse(error);
    });

};

const handleDeactivateButtonClick = (uuid) => {
    showConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeSingleDelete(uuid);
}

const executeSingleDelete = (uuid) => {
    showDeactivateSpinner.value = true;

    axios.post(props.routes.cloud_provisioning_destroy_organization, {domain_uuid: uuid})
        .then((response) => {
            showDeactivateSpinner.value = false;
            showNotification('success', response.data.messages);
            handleModalClose();
            handleRefreshTenantsClick();
        }).catch((error) => {
        showDeactivateSpinner.value = false;
        handleRefreshTenantsClick();
        handleModalClose();
        handleFormErrorResponse(error);
    });
}

const handlePolycomApiTokenButtonClick = () => {
    showApiTokenModal.value = true
    loadingModal.value = true
    getApiToken();
}

const handleActivationFinish = () => {
    handleModalClose();
}


const renderRequestedPage = (url) => {
    loading.value = true;
    axios.post(url, {
        filterData: filterData._rawValue,
    })
        .then((response) => {
            availableTenants.value = response.data.tenants;
        }).catch((error) => {
        handleModalClose();
        handleErrorResponse(error);
    });

};

const getItemOptions = (itemUuid = null) => {
    const payload = itemUuid ? {item_uuid: itemUuid} : {}; // Conditionally add itemUuid to payload
    axios.post(props.routes.cloud_provisioning_item_options, payload)
        .then((response) => {
            loadingModal.value = false;
            itemOptions.value = response.data;
        }).catch((error) => {
        handleModalClose();
        handleErrorResponse(error);
    });
}

const getZtpOrganizations = (itemUuid = null) => {
    const payload = itemUuid ? {item_uuid: itemUuid} : {};

    axios.post(props.routes.cloud_provisioning_get_all_orgs, payload)
        .then((response) => {
            loadingModal.value = false;
            ztpOrganizations.value = response.data;
        }).catch((error) => {
        handleModalClose();
        handleErrorResponse(error);
    });
}

const handleClearErrors = () => {
    formErrors.value = null;
}

const getApiToken = () => {
    axios.post(props.routes.cloud_provisioning_get_api_token)
        .then((response) => {
            loadingModal.value = false;
            apiToken.value = response.data.token;
        }).catch((error) => {
        handleModalClose();
        handleErrorResponse(error);
    });
}

const handleFormErrorResponse = (error) => {
    if (error.request?.status === 419) {
        showNotification('error', {request: ["Session expired. Reload the page"]});
    } else if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || {request: [error.message]});
        formErrors.value = error.response.data.errors;
    } else if (error.request) {
        // The request was made but no response was received
        // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
        // http.ClientRequest in node.js
        showNotification('error', {request: [error.request]});
        console.log(error.request);
    } else {
        // Something happened in setting up the request that triggered an Error
        showNotification('error', {request: [error.message]});
        console.log(error.message);
    }

}

const handleErrorResponse = (error) => {
    if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || {request: [error.message]});
    } else if (error.request) {
        // The request was made but no response was received
        // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
        // http.ClientRequest in node.js
        showNotification('error', {request: [error.request]});
        console.log(error.request);
    } else {
        // Something happened in setting up the request that triggered an Error
        showNotification('error', {request: [error.message]});
        console.log(error.message);
    }
}

const handleModalClose = () => {
    showActivateModal.value = false;
    showEditModal.value = false;
    showApiTokenModal.value = false;
    showPolycomConfirmationModal.value = false;
    showConfirmationModal.value = false;
    bulkUpdateModalTrigger.value = false;
    showPairModal.value = false;
}

const showNotification = (type, messages = null) => {
    emit("notification:show", type, messages);
}

</script>
