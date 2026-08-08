<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10"
            :inert="showPairModal || showUpdateModal || showApiTokenModal || showCreateModal || showYealinkCreateModal || showYealinkUpdateModal">
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
                                    <span class="sr-only">{{ $t('Close') }}</span>
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
                                    <div class="text-lg text-blue-600 m-auto">{{ $t('Loading...') }}</div>
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


                                                <FormTab name="polycom" :label="$t('Polycom')" :elements="[
                                                    'polycom_title',
                                                    'polycom_loading',
                                                    'polycom_status',
                                                    'polycom_create_org',
                                                    'polycom_update_org',
                                                    'polycom_create_api_token',
                                                    'polycom_destroy_org',
                                                    'polycom_sync_devices',
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

                                                <FormTab name="yealink" :label="$t('Yealink')" :elements="[
                                                    'yealink_title',
                                                    'yealink_loading',
                                                    'yealink_status',
                                                    'yealink_provider_error',
                                                    'yealink_create_server',
                                                    'yealink_update_server',
                                                    'yealink_credentials',
                                                    'yealink_destroy_server',
                                                    'yealink_sync_devices',
                                                ]" />


                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>

                                                <StaticElement name="polycom_title" tag="h4" :content="$t('Polycom ZTP')" />

                                                <StaticElement name="polycom_loading"
                                                    :conditions="[() => isFormLoading]">
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
                                                    :conditions="[() => !isFormLoading]">
                                                    <div v-if="options && options?.organization_id"
                                                        class="flex items-center gap-x-3">
                                                        <div
                                                            class="flex-none rounded-full bg-green-400/10 p-1 text-green-400">
                                                            <div class="size-3 rounded-full bg-current" />
                                                        </div>
                                                        <h1 class="flex gap-x-3 text-lg">
                                                            <span class="font-semibold ">{{ $t('Status:') }}</span>
                                                            <Badge backgroundColor="bg-green-100"
                                                                textColor="text-green-700" :text="$t('Active')"
                                                                ringColor="ring-green-400/20"
                                                                class="px-2 py-1 text-xs font-semibold" />
                                                        </h1>
                                                    </div>


                                                    <div v-if="!options" class="flex items-center gap-x-3">
                                                        <div
                                                            class="flex-none rounded-full bg-amber-400/10 p-1 text-amber-400">
                                                            <div class="size-3 rounded-full bg-current" />
                                                        </div>
                                                        <h1 class="flex gap-x-3 text-lg">
                                                            <span class="font-semibold ">{{ $t('Status:') }}</span>
                                                            <Badge v-if="!isFormLoading" backgroundColor="bg-amber-100"
                                                                textColor="text-amber-700"
                                                                :text="$t('Polycom ZTP API Token Missing or Invalid')"
                                                                ringColor="ring-amber-400/20"
                                                                class="px-2 py-1 text-xs font-semibold" />
                                                        </h1>
                                                    </div>

                                                    <div v-if="!options?.organization_id && options?.provider_settings"
                                                        class="flex items-center gap-x-3">
                                                        <div
                                                            class="flex-none rounded-full bg-gray-400/10 p-1 text-gray-400">
                                                            <div class="size-3 rounded-full bg-current" />
                                                        </div>
                                                        <h1 class="flex gap-x-3 text-lg">
                                                            <span class="font-semibold ">{{ $t('Status:') }}</span>
                                                            <Badge backgroundColor="bg-gray-100"
                                                                textColor="text-gray-700" :text="$t('Not Registered')"
                                                                ringColor="ring-gray-400/20"
                                                                class="px-2 py-1 text-xs font-semibold" />
                                                        </h1>
                                                    </div>


                                                </StaticElement>


                                                <ButtonElement name="polycom_create_org"
                                                    :button-label="$t('Create Organization')" :loading="isLoading.create"
                                                    @click="handleCreateButtonClick('polycom')"
                                                    :description="$t('Create organization or connect to the existing organization in Polycom ZTP.')"
                                                    :conditions="[() => options && !options.organization_id]" />

                                                <ButtonElement name="polycom_update_org"
                                                    :button-label="$t('Organization Settings')" :loading="isLoading.update"
                                                    @click="handleUpdateButtonClick('polycom')"
                                                    :description="$t('Create organization or connect to the existing organization in Polycom ZTP.')"
                                                    :conditions="[() => options && options.organization_id]" />

                                                <ButtonElement name="polycom_destroy_org"
                                                    :button-label="$t('Delete Organization')" :loading="isLoading.destroy"
                                                    @click="handleDestroyButtonClick('polycom')"
                                                    :description="$t('Delete organization from Polycom ZTP.')" :danger="true"
                                                    :conditions="[() => options && options.organization_id]" />

                                                <ButtonElement name="polycom_sync_devices"
                                                    :button-label="$t('Sync Devices')" :loading="isLoading.sync"
                                                    @click="handleSyncButtonClick('polycom')"
                                                    :description="$t('Syncs all devices from Polycom ZTP to your local storage. This will erase and replace any devices currently stored locally.')"
                                                    :secondary="true"
                                                    :conditions="[() => options && options.organization_id]" />


                                                <ButtonElement name="polycom_create_api_token" :button-label="$t('API Token')"
                                                    :loading="isLoading.create"
                                                    @click="handleApiTokenButtonClick('polycom')"
                                                    :description="$t('Click to add or update your Polycom ZTP API Token.')"
                                                    :secondary="true" :conditions="[() => !isFormLoading]" />

                                                <StaticElement name="yealink_title" tag="h4" :content="$t('Yealink RPS')"
                                                    :description="$t('Manage the Yealink redirection server and device registrations for this account.')" />

                                                <StaticElement name="yealink_loading"
                                                    :conditions="[() => isFormLoading]">
                                                    <div class="my-5 space-y-4">
                                                        <div class="h-2 animate-pulse rounded bg-slate-200" />
                                                        <div class="h-2 animate-pulse rounded bg-slate-200" />
                                                        <div class="h-2 animate-pulse rounded bg-slate-200" />
                                                    </div>
                                                </StaticElement>

                                                <StaticElement name="yealink_status"
                                                    :conditions="[() => !isFormLoading]">
                                                    <div v-if="options?.organization_id && options?.credentials_configured"
                                                        class="flex items-center gap-x-3">
                                                        <div class="flex-none rounded-full bg-green-400/10 p-1 text-green-400">
                                                            <div class="size-3 rounded-full bg-current" />
                                                        </div>
                                                        <h1 class="flex gap-x-3 text-lg">
                                                            <span class="font-semibold">{{ $t('Status:') }}</span>
                                                            <Badge backgroundColor="bg-green-100"
                                                                textColor="text-green-700" :text="$t('Active')"
                                                                ringColor="ring-green-400/20"
                                                                class="px-2 py-1 text-xs font-semibold" />
                                                        </h1>
                                                    </div>

                                                    <div v-else-if="options && !options.credentials_configured"
                                                        class="flex items-center gap-x-3">
                                                        <div class="flex-none rounded-full bg-amber-400/10 p-1 text-amber-400">
                                                            <div class="size-3 rounded-full bg-current" />
                                                        </div>
                                                        <h1 class="flex gap-x-3 text-lg">
                                                            <span class="font-semibold">{{ $t('Status:') }}</span>
                                                            <Badge backgroundColor="bg-amber-100"
                                                                textColor="text-amber-700"
                                                                :text="$t('API Credentials Required')"
                                                                ringColor="ring-amber-400/20"
                                                                class="px-2 py-1 text-xs font-semibold" />
                                                        </h1>
                                                    </div>

                                                    <div v-else class="flex items-center gap-x-3">
                                                        <div class="flex-none rounded-full bg-gray-400/10 p-1 text-gray-400">
                                                            <div class="size-3 rounded-full bg-current" />
                                                        </div>
                                                        <h1 class="flex gap-x-3 text-lg">
                                                            <span class="font-semibold">{{ $t('Status:') }}</span>
                                                            <Badge backgroundColor="bg-gray-100"
                                                                textColor="text-gray-700" :text="$t('Not Registered')"
                                                                ringColor="ring-gray-400/20"
                                                                class="px-2 py-1 text-xs font-semibold" />
                                                        </h1>
                                                    </div>
                                                </StaticElement>

                                                <StaticElement name="yealink_provider_error"
                                                    :conditions="[() => !isFormLoading && options?.provider_error]">
                                                    <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                                                        {{ options?.provider_error }}
                                                    </div>
                                                </StaticElement>

                                                <ButtonElement name="yealink_create_server"
                                                    :button-label="$t('Create RPS Server')"
                                                    @click="handleCreateButtonClick('yealink')"
                                                    :description="$t('Create a Yealink RPS server or connect this account to an existing one.')"
                                                    :conditions="[() => options?.credentials_configured && !options?.organization_id]" />

                                                <ButtonElement name="yealink_update_server"
                                                    :button-label="$t('Server Settings')"
                                                    @click="handleUpdateButtonClick('yealink')"
                                                    :description="$t('Update the provisioning URL and HTTP credentials sent to Yealink phones.')"
                                                    :conditions="[() => options?.organization_id]" />

                                                <ButtonElement name="yealink_destroy_server"
                                                    :button-label="$t('Delete RPS Server')"
                                                    @click="handleDestroyButtonClick('yealink')"
                                                    :description="$t('Delete this server from Yealink RPS and disconnect it from the account.')"
                                                    :danger="true" :conditions="[() => options?.organization_id]" />

                                                <ButtonElement name="yealink_sync_devices"
                                                    :button-label="$t('Sync Devices')" :loading="isLoading.sync"
                                                    @click="handleSyncButtonClick('yealink')"
                                                    :description="$t('Replace local Yealink cloud status with the current device list from RPS.')"
                                                    :secondary="true" :conditions="[() => options?.organization_id]" />

                                                <ButtonElement name="yealink_credentials"
                                                    :button-label="$t('API Credentials')"
                                                    @click="handleApiTokenButtonClick('yealink')"
                                                    :description="$t('Add or update the Yealink RPS AccessKey credentials.')"
                                                    :secondary="true" :conditions="[() => !isFormLoading]" />




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


    <CreatePolycomOrgForm :options="itemOptions" :errors="formErrors" :show="showCreateModal"
        :header="$t('Activate Polycom Organization')" :loading="loadingModal" @close="handleModalClose"
        @error="emitErrorToParentFromChild" @success="emitSuccessToParentFromChild"
        @refresh-data="getCloudProvisioningItemOptions('polycom')" />


    <UpdatePolycomOrgForm :options="itemOptions" :show="showUpdateModal" :header="$t('Edit Polycom Organization')"
        :loading="loadingModal" @close="showUpdateModal = false" @error="emitErrorToParentFromChild"
        @success="emitSuccessToParentFromChild" @clear-errors="handleClearErrors" />

    <YealinkRpsServerForm :options="itemOptions" :show="showYealinkCreateModal"
        :header="$t('Create Yealink RPS Server')" :loading="loadingModal" mode="create"
        @close="handleModalClose" @error="emitErrorToParentFromChild"
        @success="emitSuccessToParentFromChild" @refresh-data="getCloudProvisioningItemOptions" />

    <YealinkRpsServerForm :options="itemOptions" :show="showYealinkUpdateModal"
        :header="$t('Edit Yealink RPS Server')" :loading="loadingModal" mode="update"
        @close="handleModalClose" @error="emitErrorToParentFromChild"
        @success="emitSuccessToParentFromChild" @refresh-data="getCloudProvisioningItemOptions" />


    <PairPolycomOrganizationForm :show="showPairModal" :loading="loadingModal" :orgs="ztpOrganizations"
        :selected-provider="selectedProvider" :route="options?.routes?.cloud_provisioning_pair_organization"
        :header="pairModalHeader" :description="pairModalDescription" :item-label="pairModalItemLabel"
        @close="handleModalClose" @error="emitErrorToParentFromChild" @success="emitSuccessToParentFromChild"
        @refresh-data="getCloudProvisioningItemOptions" />

    <AddEditItemModal :customClass="'sm:max-w-xl'" :show="showApiTokenModal" :header="credentialsModalHeader"
        :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <UpdatePolycomApiTokenForm v-if="selectedProvider === 'polycom'" :token="apiToken"
                :errors="formErrors" :selected-provider="selectedProvider"
                :is-submitting="updateApiTokenFormSubmitting" @submit="handleUpdateApiTokenRequest"
                @cancel="handleModalClose" @error="emitErrorToParentFromChild" @refresh-data="getItemOptions"
                @success="emitSuccessToParentFromChild" @clear-errors="handleClearErrors" />
            <UpdateYealinkRpsCredentialsForm v-else-if="selectedProvider === 'yealink'"
                :credentials="providerCredentials"
                :route="props.routes.cloud_provisioning_update_api_token"
                @cancel="handleModalClose" @error="emitErrorToParentFromChild"
                @success="handleCredentialsSuccess" />
        </template>
    </AddEditItemModal>

    <ConfirmationModal :show="showConfirmationModal" @close="showConfirmationModal = false"
        @confirm="confirmDeleteAction" :header="$t('Confirm Action')"
        :text="deleteConfirmationText"
        :confirm-button-label="$t('Delete')" :cancel-button-label="$t('Cancel')" :loading="showDeactivateSpinner" />

    <ConfirmationModal :show="showPolycomConfirmationModal" @close="dismissSetupConfirmation"
        @cancel="cancelPolycomAction" @confirm="confirmPolycomAction"
        :header="setupConfirmationHeader"
        :text="setupConfirmationText"
        :confirm-button-label="setupCreateLabel" :cancel-button-label="$t('Connect to Existing')"
        :loading="showConnectSpinner || showCreateSpinner" :color="'blue'" />
</template>

<script setup>
import { computed, ref } from "vue";
import axios from "axios";
import PairPolycomOrganizationForm from "../forms/PairPolycomOrganizationForm.vue";
import AddEditItemModal from "../modal/AddEditItemModal.vue";
import UpdatePolycomOrgForm from "../forms/UpdatePolycomOrgForm.vue";
import CreatePolycomOrgForm from "../forms/CreatePolycomOrgForm.vue";
import ConfirmationModal from "../modal/ConfirmationModal.vue";
import UpdatePolycomApiTokenForm from "../forms/UpdatePolycomApiTokenForm.vue";
import UpdateYealinkRpsCredentialsForm from "../forms/UpdateYealinkRpsCredentialsForm.vue";
import YealinkRpsServerForm from "../forms/YealinkRpsServerForm.vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/solid";
import Badge from "@generalComponents/Badge.vue";
import { trans } from '@i18n';

const props = defineProps({
    show: Boolean,
    routes: Object,
    header: String,
    loading: Boolean,
});

// console.log(props.routes)


const loadingModal = ref(false)
const showCreateModal = ref(false);
const showUpdateModal = ref(false);
const showYealinkCreateModal = ref(false);
const showYealinkUpdateModal = ref(false);
const showApiTokenModal = ref(false);
const showPairModal = ref(false);
const bulkUpdateModalTrigger = ref(false);
const showConfirmationModal = ref(false);
const showPolycomConfirmationModal = ref(false);
const updateApiTokenFormSubmitting = ref(null);
const confirmDeleteAction = ref(null);
const showDeactivateSpinner = ref(null);
const showConnectSpinner = ref(null);
const showCreateSpinner = ref(null);
const confirmPolycomAction = ref(null);
const cancelPolycomAction = ref(null);
const formErrors = ref(null);
const ztpOrganizations = ref({})
const selectedProvider = ref(null)
const itemOptions = ref({})
const apiToken = ref(null)
const providerCredentials = ref({})
const syncDevicesSubmitting = ref(null);
const options = ref(null)
const isFormLoading = ref(false)
const isLoading = ref({
    create: false,
    update: false,
    destroy: false,
    sync: false,
})

const isYealinkSelected = computed(() => selectedProvider.value === 'yealink')
const credentialsModalHeader = computed(() => isYealinkSelected.value
    ? trans('Yealink RPS API Credentials')
    : trans('Polycom API Token'))
const pairModalHeader = computed(() => isYealinkSelected.value
    ? trans('Connect to Existing Yealink RPS Server')
    : trans('Connect to Existing ZTP Organization'))
const pairModalDescription = computed(() => isYealinkSelected.value
    ? trans('Select the Yealink RPS server to use for this account.')
    : trans('Select the organization you want to connect to.'))
const pairModalItemLabel = computed(() => isYealinkSelected.value ? trans('RPS Server') : trans('Organization'))
const deleteConfirmationText = computed(() => isYealinkSelected.value
    ? trans('Delete this Yealink RPS server? Devices assigned to it may stop redirecting to FS PBX.')
    : trans('Delete this Polycom organization? This action may impact account functionality.'))
const setupConfirmationHeader = computed(() => isYealinkSelected.value
    ? trans('Set up Yealink RPS')
    : trans('Set up Polycom ZTP'))
const setupConfirmationText = computed(() => isYealinkSelected.value
    ? trans('Create a new RPS server or connect this account to an existing server.')
    : trans('Create a new Polycom organization or connect this account to an existing organization.'))
const setupCreateLabel = computed(() => isYealinkSelected.value
    ? trans('Create New Server')
    : trans('Create New Organization'))

const emit = defineEmits(['close', 'cancel', 'error', 'success']);


const getCloudProvisioningItemOptions = (provider) => {
    options.value = null
    isFormLoading.value = true
    axios.post(props.routes.cloud_provisioning_item_options,
        {
            provider: provider
        }
    )
        .then((response) => {
            options.value = response.data;
            // console.log(options.value);

        }).catch((error) => {
            handleModalClose();
            emit('error', error);
        }).finally(() => {
            isFormLoading.value = false
        })
}

const handleCreateButtonClick = (provider) => {
    selectedProvider.value = provider;
    showPolycomConfirmationModal.value = true;
    confirmPolycomAction.value = () => executeNewOrgAction(provider);
    cancelPolycomAction.value = () => executeExistingOrgAction(provider);
};

const dismissSetupConfirmation = () => {
    showPolycomConfirmationModal.value = false;
}

const executeNewOrgAction = (provider) => {
    showPolycomConfirmationModal.value = false;
    selectedProvider.value = provider;
    if (provider === 'yealink') {
        showYealinkCreateModal.value = true
    } else {
        showCreateModal.value = true
    }
    loadingModal.value = true
    getItemOptions(provider);
}

const executeExistingOrgAction = (provider) => {
    showPolycomConfirmationModal.value = false;
    selectedProvider.value = provider;
    showPairModal.value = true
    loadingModal.value = true
    getZtpOrganizations(provider);
}

const handleUpdateButtonClick = (provider) => {
    selectedProvider.value = provider;
    if (provider === 'yealink') {
        showYealinkUpdateModal.value = true
    } else {
        showUpdateModal.value = true
    }
    loadingModal.value = true
    getItemOptions(provider);
}

const handleSyncButtonClick = (provider) => {
    isLoading.value.sync = true;
    axios.post(options.value.routes.cloud_provisioning_sync_devices,
        {
            provider: provider
        }
    )
        .then((response) => {
            emit('success', 'success', response.data.messages);
        }).catch((error) => {
            emit('error', error); // Emit the event with error
        }).finally(() => {
            isLoading.value.sync = false;
        })
};



const handleDestroyButtonClick = (provider) => {
    selectedProvider.value = provider;
    showConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeSingleDelete(provider);
}

const executeSingleDelete = (provider) => {
    showDeactivateSpinner.value = true;

    axios.post(options.value.routes.cloud_provisioning_destroy_organization, { provider: provider })
        .then((response) => {
            emit('success', 'success', response.data.messages);
        }).catch((error) => {
            emit('error', error);
        }).finally(() => {
            showDeactivateSpinner.value = false;
            handleModalClose();
            getCloudProvisioningItemOptions(provider)
        })
}


const getItemOptions = (provider) => {
    const payload = {
        provider: provider,
    };
    axios.post(props.routes.cloud_provisioning_item_options, payload)
        .then((response) => {
            itemOptions.value = response.data;
            // console.log(response.data);
        }).catch((error) => {
            emit('error', error)
            handleModalClose();
        }).finally(() => {
            loadingModal.value = false;
        })
}

const getZtpOrganizations = (provider) => {
    axios.post(options.value.routes.cloud_provisioning_get_all_orgs, {
        provider: provider
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

const handleApiTokenButtonClick = (provider) => {
    showApiTokenModal.value = true
    loadingModal.value = true
    selectedProvider.value = provider;
    getApiToken(provider);
}

const getApiToken = (provider) => {
    axios.post(props.routes.cloud_provisioning_get_token, {
        provider: provider
    })
        .then((response) => {
            apiToken.value = response.data.token;
            providerCredentials.value = response.data.credentials ?? {};
            // console.log(apiToken.value)

        }).catch((error) => {
            handleModalClose();
            emit('error', error)
        }).finally(() => {
            loadingModal.value = false;
        })
}

const handleUpdateApiTokenRequest = (form) => {
    updateApiTokenFormSubmitting.value = true;
    formErrors.value = null;

    axios.post(props.routes.cloud_provisioning_update_api_token, form)
        .then((response) => {
            emit('success', 'success', response.data.messages);
            handleModalClose();
        }).catch((error) => {
            emit('error', error);
        }).finally(() => {
            getCloudProvisioningItemOptions(form.provider);
            updateApiTokenFormSubmitting.value = false;
            loadingModal.value = false;
        })

};

const handleCredentialsSuccess = (messages) => {
    emit('success', 'success', messages);
    handleModalClose();
    getCloudProvisioningItemOptions('yealink');
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

            form$.messageBag.append(trans('Could not prepare form'))
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

            form$.messageBag.append(trans('Request cancelled'))
            break

        // Some other errors happened (no response object)
        case 'other':
            console.log(error) // Error object

            form$.messageBag.append(trans('Couldn\'t submit form'))
            break
    }
}

const handleTabSelected = (activeTab, previousTab) => {
    if (activeTab.name == 'polycom' || activeTab.name == 'yealink') {
        selectedProvider.value = activeTab.name
        getCloudProvisioningItemOptions(activeTab.name)
    }

}

const handleModalClose = () => {
    showCreateModal.value = false;
    showUpdateModal.value = false;
    showYealinkCreateModal.value = false;
    showYealinkUpdateModal.value = false;
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
<style>
div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}
</style>
