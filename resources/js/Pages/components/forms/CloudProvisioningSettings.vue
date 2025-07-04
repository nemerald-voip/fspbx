<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" :inert="showPairModal">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                        <DialogPanel
                            class="relative transform  rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl sm:p-6">

                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full h-full">
                                <div class="flex justify-center items-center space-x-3">
                                    <div>
                                        <svg class="animate-spin  h-10 w-10 text-blue-600"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4">
                                            </circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                    </div>
                                    <div class="text-lg text-blue-600 m-auto">Loading...</div>
                                </div>
                            </div>


                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false" :default="{
                                    // enabled: options.organization?.enabled,
                                    // name: options.organization?.name,
                                    // polling: options.organization?.template?.provisioning?.polling,
                                    // quickSetup: options.organization?.template?.provisioning?.quickSetup,
                                    // address: options.organization?.template?.provisioning?.server?.address,
                                    // username: options.organization?.template?.provisioning?.server?.username,
                                    // boot_server_option: options.organization?.template?.provisioning?.dhcp?.boot_server_option,

                                }">

                                <template #empty>

                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical" @select="handleTabSelected">
                                                

                                                <FormTab name="polycom" label="Polycom" :elements="[
                                                    'polycom_title',
                                                    'polycom_loading',
                                                    'polycom_status',
                                                    'polycom_create_org',
                                                    'name',
                                                    'divider',
                                                    'provisioning_title',
                                                    'address',
                                                    'username',
                                                    'password',
                                                    'polling',
                                                    'quickSetup',
                                                    'divider_1',
                                                    'dhcp_title',
                                                    'bootServerOption',
                                                    'option_60_type',
                                                    'divider_2',
                                                    'software_title',
                                                    'software',
                                                    'divider_3',
                                                    'localization_title',
                                                    'localization',
                                                    'reset',
                                                    'submit',

                                                ]" />


                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>

                                                <StaticElement name="polycom_title" tag="h4" content="Polycom ZTP" />

                                                <StaticElement name="polycom_loading"
                                                    :conditions="[() => isFormLoading.loading]">
                                                    <div class="text-center my-5 text-sm text-gray-500">
                                                        <div class="animate-pulse flex space-x-4">
                                                            <div class="flex-1 space-y-6 py-1">
                                                                <div class="h-2 bg-slate-200 rounded"></div>
                                                                <div class="h-2 bg-slate-200 rounded"></div>
                                                                <div class="h-2 bg-slate-200 rounded"></div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </StaticElement>

                                                <StaticElement name="polycom_status"
                                                    :conditions="[() => !isFormLoading.loading]">
                                                    <div v-if="options && options.organization_id"
                                                        class="flex items-center gap-x-3">
                                                        <div
                                                            class="flex-none rounded-full bg-green-400/10 p-1 text-green-400">
                                                            <div class="size-3 rounded-full bg-current" />
                                                        </div>
                                                        <h1 class="flex gap-x-3 text-lg">
                                                            <span class="font-semibold ">Status:</span>
                                                            <Badge backgroundColor="bg-green-100"
                                                                textColor="text-green-700" :text="'Active'"
                                                                ringColor="ring-green-400/20"
                                                                class="px-2 py-1 text-xs font-semibold" />
                                                        </h1>
                                                    </div>
                                                    <!-- <div v-if="provisioning && provisioning.status == 'error'"
                                                        class="flex items-center gap-x-3">
                                                        <div
                                                            class="flex-none rounded-full bg-rose-400/10 p-1 text-rose-400">
                                                            <div class="size-3 rounded-full bg-current" />
                                                        </div>
                                                        <h1 class="flex gap-x-3 text-lg">
                                                            <span class="font-semibold ">Status:</span>
                                                            <Badge backgroundColor="bg-rose-100"
                                                                textColor="text-rose-700" :text="'Error'"
                                                                ringColor="ring-rose-400/20"
                                                                class="px-2 py-1 text-xs font-semibold" />
                                                        </h1>
                                                    </div> -->


                                                    

                                                    <!-- <div v-if="provisioning && provisioning.status == 'pending'"
                                                        class="flex items-center gap-x-3">
                                                        <div
                                                            class="flex-none rounded-full bg-amber-400/10 p-1 text-amber-400">
                                                            <div class="size-3 rounded-full bg-current" />
                                                        </div>
                                                        <h1 class="flex gap-x-3 text-lg">
                                                            <span class="font-semibold ">Status:</span>
                                                            <Badge backgroundColor="bg-amber-100"
                                                                textColor="text-amber-700" :text="'Pending'"
                                                                ringColor="ring-amber-400/20"
                                                                class="px-2 py-1 text-xs font-semibold" />
                                                        </h1>
                                                    </div> -->

                                                    <div v-if="!options || !options.organization_id"
                                                        class="flex items-center gap-x-3">
                                                        <div
                                                            class="flex-none rounded-full bg-gray-400/10 p-1 text-gray-400">
                                                            <div class="size-3 rounded-full bg-current" />
                                                        </div>
                                                        <h1 class="flex gap-x-3 text-lg">
                                                            <span class="font-semibold ">Status:</span>
                                                            <Badge backgroundColor="bg-gray-100"
                                                                textColor="text-gray-700" :text="'Not Registered'"
                                                                ringColor="ring-gray-400/20"
                                                                class="px-2 py-1 text-xs font-semibold" />
                                                        </h1>
                                                    </div>
                                                </StaticElement>

                                                <ButtonElement name="polycom_create_org" button-label="Create Organization"
                                                    :loading="isLoading.create"
                                                    @click="handleCreateButtonClick('polycom')"
                                                    description="Create organization or connect to the existing organization in Polycom ZTP."
                                                    :conditions="[() => true]" />

                                                

                                            </FormElements>
                                        </div>
                                    </div>
                                </template>
                            </Vueform>
                        </DialogPanel>


                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>


    <!-- <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
        <aside class="px-2 py-6 sm:px-6 lg:col-span-2 lg:px-0 lg:py-0">
            <nav class="space-y-1">
                <a v-for="item in options.cloud_providers" :key="item.name" href="#"
                    :class="[activeTab === item.slug ? 'bg-gray-200 text-indigo-700 hover:bg-gray-100 hover:text-indigo-700' : 'text-gray-900 hover:bg-gray-200 hover:text-gray-900', 'group flex items-center rounded-md px-3 py-2 text-sm font-medium']"
                    @click.prevent="setActiveTab(item.slug)" :aria-current="item.current ? 'page' : undefined">
                    <component :is="iconComponents[item.icon]"
                        :class="[item.current ? 'text-indigo-500 group-hover:text-indigo-500' : 'text-gray-400 group-hover:text-gray-500', '-ml-1 mr-3 h-6 w-6 flex-shrink-0']"
                        aria-hidden="true" />
                    <span class="truncate">{{ item.name }}</span>
                </a>
            </nav>

        </aside>
        <div v-if="activeTab === 'polycom'" class="lg:col-span-10">
            <div class="shadow sm:rounded-md">
                <div v-if="currentTenant" class="bg-gray-50 pb-6">
                    <div class="flex justify-between items-center p-8 pb-0">
                        <h3 class="text-base font-semibold leading-6 text-gray-900">Provisioning Status</h3>
                        <button v-if="canEditPolycomToken" type="button"
                            @click.prevent="handlePolycomApiTokenButtonClick()"
                            class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            API Token
                        </button>
                    </div>

                    <div class="space-y-6 p-8">
                        <div>Status:
                            <span class="text-rose-600" v-if="currentTenant.ztp_status !== 'true'">Organization is not
                                provisioned</span>
                            <span class="text-emerald-600" v-if="currentTenant.ztp_status === 'true'">Organization is
                                provisioned</span>
                        </div>
                        <div v-if="currentTenant.ztp_status === 'true'" class="flex gap-4 pt-4">
                            <button type="button" @click.prevent="handleEditButtonClick('polycom', currentTenant.domain_uuid)"
                                class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                Edit
                            </button>
                            <button type="button"
                                @click.prevent="handleDeactivateButtonClick(currentTenant.domain_uuid)"
                                class="rounded-md bg-red-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                                Deactivate
                            </button>
                        </div>
                        <div v-else class="flex gap-4 pt-4">
                            <button type="button" @click.prevent="handleActivateButtonClick(currentTenant.domain_uuid)"
                                class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                Activate
                            </button>
                        </div>
                    </div>

                    <div v-if="currentTenant.ztp_status === 'true'">
                        <div class="flex justify-between items-center p-8 pb-0">
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Devices</h3>
                        </div>

                        <div class="space-y-6 p-8">
                            <p class="text-sm text-gray-500">
                                Sync devices from Polycom to ensure your local system stays up-to-date with the latest
                                organizational data. Click the <strong>Sync Devices</strong> button to initiate the
                                process.
                            </p>
                            <div class="rounded-md bg-yellow-100 p-4 mt-4">
                                <div class="flex">
                                    <div class="shrink-0">
                                        <ExclamationTriangleIcon class="size-5 text-yellow-500" aria-hidden="true" />
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-yellow-800">Important Notice</h3>
                                        <div class="mt-2 text-sm text-yellow-700">
                                            <p>
                                                Syncing will replace all current device data in your system with the
                                                latest
                                                device data from the cloud.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="px-4 py-3 text-center sm:px-6">
                                <button @click.prevent="handleSyncButtonClick('polycom')"
                                    class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                                    :disabled="syncDevicesSubmitting">
                                    <Spinner :show="syncDevicesSubmitting" />
                                    Sync Devices
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div> -->

    <AddEditItemModal :customClass="'sm:max-w-4xl'" :show="showActivateModal" :header="'Activate Polycom Organization'"
        :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <CreatePolycomOrgForm :options="itemOptions" :errors="formErrors" :is-submitting="activateFormSubmitting"
                :activeTab="activationActiveTab" @submit="handleCreateRequest" @cancel="handleActivationFinish"
                @error="emitErrorToParentFromChild" @success="emitSuccessToParentFromChild"
                @clear-errors="handleClearErrors" />
        </template>
    </AddEditItemModal>

    <UpdatePolycomOrgForm :options="itemOptions" :show="showUpdateModal" :header="'Edit Polycom Organization'"
        :loading="loadingModal" @close="showUpdateModal = false" @error="emitErrorToParentFromChild"
        @success="emitSuccessToParentFromChild" @clear-errors="handleClearErrors" />


    <AddEditItemModal :customClass="'sm:max-w-xl'" :show="showPairModal"
        :header="'Connect to existing ZTP Organization'" :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <PairPolycomOrganizationForm :orgs="ztpOrganizations" :selected-account="selectedAccount"
                :errors="formErrors" :is-submitting="pairZtpOrgSubmitting" @submit="handlePairZtpOrgRequest"
                @cancel="handleModalClose" @error="emitErrorToParentFromChild"
                @success="emitSuccessToParentFromChild" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :customClass="'sm:max-w-xl'" :show="showApiTokenModal" :header="'Polycom Api Token'"
        :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <UpdatePolycomApiTokenForm :token="apiToken" :errors="formErrors"
                :is-submitting="updateApiTokenFormSubmitting" @submit="handleUpdateApiTokenRequest"
                @cancel="handleModalClose" @error="handleFormErrorResponse" @refresh-data="getItemOptions"
                @success="showNotification('success', $event)" @clear-errors="handleClearErrors" />
        </template>
    </AddEditItemModal>

    <ConfirmationModal :show="showConfirmationModal" @close="showConfirmationModal = false"
        @confirm="confirmDeleteAction" :header="'Confirm Action'"
        :text="'Are you sure you want to deactivate apps for this account? This action may impact account functionality.'"
        confirm-button-label="Deactivate" cancel-button-label="Cancel" :loading="showDeactivateSpinner" />

    <ConfirmationModal :show="showPolycomConfirmationModal" @close="cancelPolycomAction" @confirm="confirmPolycomAction"
        :header="'Select a method to set up your Polycom organization.'"
        :text="'Would you like to connect to an existing Polycom organization or create a new one?'"
        confirm-button-label="Create New Organization" cancel-button-label="Connect to Existing"
        :loading="showConnectSpinner || showCreateSpinner" :color="'blue'" />
</template>

<script setup>
import { onMounted, ref } from "vue";
import axios from "axios";
import PairPolycomOrganizationForm from "../forms/PairPolycomOrganizationForm.vue";
import AddEditItemModal from "../modal/AddEditItemModal.vue";
import UpdatePolycomOrgForm from "../forms/UpdatePolycomOrgForm.vue";
import CreatePolycomOrgForm from "../forms/CreatePolycomOrgForm.vue";
import ConfirmationModal from "../modal/ConfirmationModal.vue";
import UpdatePolycomApiTokenForm from "../forms/UpdatePolycomApiTokenForm.vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/solid";
import Badge from "@generalComponents/Badge.vue";
import { XCircleIcon } from '@heroicons/vue/20/solid'

const props = defineProps({
    show: Boolean,
    routes: Object,
    header: String,
    loading: Boolean,
});

// console.log(props.routes)


const loadingModal = ref(false)
const showActivateModal = ref(false);
const showUpdateModal = ref(false);
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
const currentTenant = ref(null);
const apiToken = ref(null)
const syncDevicesSubmitting = ref(null);
const options = ref({})
const isFormLoading = ref(false)
const isLoading = ref({
    create: false,
})

const emit = defineEmits(['close', 'cancel', 'error', 'success']);



// onMounted(() => {
//     handleRefreshTenantsClick();
// })



// const handleRefreshTenantsClick = () => {
//     //loading.value = true;
//     axios.post(props.routes.cloud_provisioning_item_options, {})
//         .then((response) => {
//             currentTenant.value = response.data.tenant;
//             //loading.value = false;

//         }).catch((error) => {
//             handleModalClose();
//             handleErrorResponse(error);
//         });
// }

const getCloudProvisioningItemOptions = (provider) => {
    options.value = {}
    isFormLoading.value = true
    axios.post(props.routes.cloud_provisioning_item_options,
        {
            provider: provider
        }
    )
        .then((response) => {
            options.value = response.data;
            console.log(options.value);

        }).catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        }).finally(() => {
            isFormLoading.value = false
        })
}

const handleCreateButtonClick = (provider) => {
    showPolycomConfirmationModal.value = true;
    confirmPolycomAction.value = () => executeNewZtpOrgAction(provider);
    cancelPolycomAction.value = () => executeExistingZtpOrgAction(provider);
};

const executeNewZtpOrgAction = (provider) => {
    showPolycomConfirmationModal.value = false;
    showActivateModal.value = true
    loadingModal.value = true
    getItemOptions(provider);
}

const executeExistingZtpOrgAction = (provider) => {
    showPolycomConfirmationModal.value = false;
    showPairModal.value = true
    loadingModal.value = true
    getZtpOrganizations(provider);
}

const handleEditButtonClick = (provider, itemUuid) => {
    showUpdateModal.value = true
    formErrors.value = null;
    loadingModal.value = true
    getItemOptions(provider, itemUuid);
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

const handleSyncButtonClick = (provider) => {
    syncDevicesSubmitting.value = true;
    axios.post(props.routes.cloud_provisioning_sync_devices,
        {
            provider: provider
        }
    )
        .then((response) => {
            syncDevicesSubmitting.value = false;
            showNotification('success', response.data.messages);
        }).catch((error) => {
            syncDevicesSubmitting.value = false;
            handleFormErrorResponse(error); // Emit the event with error
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

    axios.post(props.routes.cloud_provisioning_destroy_organization, { domain_uuid: uuid })
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

const getItemOptions = (provider, itemUuid) => {
    const payload = {
        provider: provider,
    };
    axios.post(props.routes.cloud_provisioning_item_options, payload)
        .then((response) => {
            itemOptions.value = response.data;
            console.log(response.data);
        }).catch((error) => {
            emit('error', error)
            handleModalClose();
        }).finally(() => {
            loadingModal.value = false;
        })
}

const getZtpOrganizations = (provider) => {
    axios.post(options.value.routes.cloud_provisioning_get_all_orgs, {
        provider:provider
    })
        .then((response) => {
            loadingModal.value = false;
            ztpOrganizations.value = response.data;
        }).catch((error) => {
            handleModalClose();
            emit('error', error)
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
            emit('error', error)
        });
}

const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    // const requestData = form$.requestData
    // console.log(requestData);

    // Using form$.data will INCLUDE conditional elements and it
    // will submit the form as "Content-Type: application/json".
    const data = form$.data

    return await form$.$vueform.services.axios.put(props.options.routes.update_route, data)
};


function clearErrorsRecursive(el$) {
    // clear this element’s errors
    el$.messageBag?.clear()

    // if it has child elements, recurse into each
    if (el$.children$) {
        Object.values(el$.children$).forEach(childEl$ => {
            clearErrorsRecursive(childEl$)
        })
    }
}

const handleResponse = (response, form$) => {
    // Clear form including nested elements 
    Object.values(form$.elements$).forEach(el$ => {
        clearErrorsRecursive(el$)
    })

    // Display custom errors for elements
    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0])
            }
        })
    }
}

const handleSuccess = (response, form$) => {
    // console.log(response) // axios response
    // console.log(response.status) // HTTP status code
    // console.log(response.data) // response data

    emit('success', 'success', response.data.messages);
    emit('close');
    emit('refresh-data');
}

const handleError = (error, details, form$) => {
    form$.messageBag.clear() // clear message bag

    switch (details.type) {
        // Error occured while preparing elements (no submit happened)
        case 'prepare':
            console.log(error) // Error object

            form$.messageBag.append('Could not prepare form')
            break

        // Error occured because response status is outside of 2xx
        case 'submit':
            emit('error', error);
            console.log(error) // AxiosError object
            // console.log(error.response) // axios response
            // console.log(error.response.status) // HTTP status code
            // console.log(error.response.data) // response data

            // console.log(error.response.data.errors)


            break

        // Request cancelled (no response object)
        case 'cancel':
            console.log(error) // Error object

            form$.messageBag.append('Request cancelled')
            break

        // Some other errors happened (no response object)
        case 'other':
            console.log(error) // Error object

            form$.messageBag.append('Couldn\'t submit form')
            break
    }
}

const handleTabSelected = (activeTab, previousTab) => {
    if (activeTab.name == 'polycom') {
        getCloudProvisioningItemOptions(activeTab.name)
    }

}

const handleModalClose = () => {
    showActivateModal.value = false;
    showUpdateModal.value = false;
    showApiTokenModal.value = false;
    showPolycomConfirmationModal.value = false;
    showConfirmationModal.value = false;
    bulkUpdateModalTrigger.value = false;
    showPairModal.value = false;
}

const emitErrorToParentFromChild = (error) => {
    emit('error', error);
}

const emitSuccessToParentFromChild = (message) => {
    emit('success', 'success', message);
}


</script>
