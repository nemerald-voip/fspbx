<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10">
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
                                    enabled: options.organization?.enabled,
                                    name: options.organization?.name,
                                    polling: options.organization?.template?.provisioning?.polling,
                                    quickSetup: options.organization?.template?.provisioning?.quickSetup,
                                    address: options.organization?.template?.provisioning?.server?.address,
                                    username: options.organization?.template?.provisioning?.server?.username,
                                    bootServerOption: options.organization?.template?.dhcp?.bootServerOption,
                                    option60Type: options.organization?.template?.dhcp?.option60Type,
                                    software: options.organization?.template?.software?.version,
                                    localization: options.organization?.template?.localization?.language,
                                }">

                                <template #empty>

                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical" @select="handleTabSelected">
                                                <FormTab name="page0" label="Settings" :elements="[
                                                    'general_title',
                                                    'enabled',
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
                                                    'option60Type',
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

                                                <StaticElement name="general_title" tag="h4" content="General"
                                                    description="Basic information about this profile." />

                                                <ToggleElement name="enabled" text="Status" />

                                                <TextElement name="name" label="Organization Name" />

                                                <StaticElement name="divider" tag="hr" />

                                                <StaticElement name="provisioning_title" tag="h4" content="Provisioning"
                                                    description="Specify provisioning parameters to be applied by this profile for your devices." />

                                                <TextElement name="address" label="Address" />

                                                <TextElement name="username" label="Username" />

                                                <TextElement name="password" label="Password" />

                                                <ToggleElement name="polling" text="Polling"
                                                    description="Enable provisioning server polling." />

                                                <ToggleElement name="quickSetup" text="Quick Setup"
                                                    description="Enable the quick setup option for phones." />

                                                <StaticElement name="divider_1" tag="hr" />

                                                <StaticElement name="dhcp_title" tag="h4" content="DHCP"
                                                    description="Configure DHCP options to determine boot behavior." />

                                                <SelectElement name="bootServerOption" :items="options.provider_settings?.dhcp_boot_server_option_list" :search="true" :native="false" label="Boot Server Option" input-type="search" autocomplete="off" />

                                                <SelectElement name="option60Type" :items="options.provider_settings?.dhcp_option_60_type_list" :search="true" :native="false" label="Option 60 Type" input-type="search" autocomplete="off" />

                                                <StaticElement name="divider_2" tag="hr" />

                                                <StaticElement name="software_title" tag="h4" content="Software"
                                                    description="Configure the software that will be loaded during provisioning." />

                                                <TextElement name="software" label="Software" />

                                                <StaticElement name="divider_3" tag="hr" />

                                                <StaticElement name="localization_title" tag="h4" content="Localization"
                                                    description="Specify the operating locale for this profile." />

                                                <SelectElement name="localization" :items="options.provider_settings?.locales" :search="true" :native="false" label="Localization" input-type="search" autocomplete="off" />

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

    <!-- <CreateApiTokenModal :show="showApiTokenModal" :options="options" @close="showApiTokenModal = false"
        @error="emitErrorToParentFromChild" @success="emitSuccessToParentFromChild" @refresh-data="getTokens" /> -->

    <!-- <ConfirmationModal :show="showResetConfirmationModal" @close="showResetConfirmationModal = false"
        @confirm="confirmResetPassword" header="Confirm Password Reset"
        text="Are you sure you want to reset the password for this user?" confirm-button-label="Reset"
        cancel-button-label="Cancel" />

    <ConfirmationModal :show="showDeleteConfirmationModal" @close="showDeleteConfirmationModal = false"
        @confirm="confirmDeleteAction" :header="'Confirm Deletion'" :loading="isDeleteTokenLoading"
        :text="'This action will permanently delete the selected API Key. Are you sure you want to proceed?'"
        confirm-button-label="Delete" cancel-button-label="Cancel" /> -->
</template>


<script setup>
import { reactive, ref, watch } from "vue";
import { usePage } from '@inertiajs/vue3';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/solid";

const loadingModal = ref(false);

const props = defineProps({
    show: Boolean,
    options: Object,
    header: String,
    loading: Boolean,

});


const page = usePage();


const emit = defineEmits(['cancel', 'error', 'success']);


const handleTabSelected = (activeTab, previousTab) => {
    // if (activeTab.name == 'cloud_provisioning') {
    //     getCloudProvisioningStatus();
    // }
}

const showPassword = ref(false);

const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
    const passwordInput = document.getElementById("provisioning_server_password");
    if (showPassword.value) {
        passwordInput.style.webkitTextSecurity = "none"; // Show text
    } else {
        passwordInput.style.webkitTextSecurity = "disc"; // Mask text
    }
};

const handleLoadDefaultValues = () => {
    form.provisioning_server_address = props.options.settings.polycom_provision_url;
    form.provisioning_server_username = props.options.settings.http_auth_username;
    form.provisioning_server_password = props.options.settings.http_auth_password;
}

const handleUpdateLocalizationLanguageField = (selected) => {
    form.localization_language = selected.value;
}

const handleUpdateOption60TypeField = (selected) => {
    form.dhcp_option_60_type = selected.value;
}

const handleUpdateBootServerOptionField = (selected) => {
    form.dhcp_bootServerOption = selected.value;
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


</script>
<style scoped>
/* This will mask the text input to behave like a password field */
.password-field {
    -webkit-text-security: disc;
    /* For Chrome and Safari */
    -moz-text-security: disc;
    /* For Firefox */
}
</style>
