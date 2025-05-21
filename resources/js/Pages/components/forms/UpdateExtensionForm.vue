<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" :inert="showApiTokenModal">
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
                                    extension_uuid: options.item.extension_uuid,
                                    directory_first_name: options.item.directory_first_name,
                                    directory_last_name: options.item.directory_last_name,
                                    extension: options.item.extension,
                                    last_name: options.item.last_name,
                                    voicemail_mail_to: options.item.email,
                                    description: options.item.description,
                                    suspended: options.item.suspended,
                                    enabled: options.item.enabled,
                                    directory_visible: options.item.directory_visible,
                                    directory_exten_visible: options.item.directory_exten_visible,
                                    outbound_caller_id_number: options.item.outbound_caller_id_number_e164 ?? '',
                                    emergency_caller_id_number: options.item.emergency_caller_id_number_e164 ?? '',

                                    // groups: options.item.user_groups
                                    //     ? options.item.user_groups.map(ug => ug.group_uuid)
                                    //     : []

                                }">

                                <template #empty>

                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical" @select="handleTabSelected">
                                                <FormTab name="page0" label="Basic Info" :elements="[
                                                    'basic_info_title',
                                                    'directory_first_name',
                                                    'directory_last_name',
                                                    'extension',
                                                    'voicemail_mail_to',
                                                    'description',
                                                    'user_enabled',
                                                    'enabled',
                                                    'suspended',
                                                    'directory_visible',
                                                    'directory_exten_visible',
                                                    'divider',
                                                    'divider2',
                                                    'divider3',
                                                    'container_2',
                                                    'container_3',
                                                    'submit',

                                                ]" />
                                                <FormTab name="caller_id" label="Caller ID" :elements="[
                                                    'external_caller_id_title',
                                                    'emergency_caller_id_title',
                                                    'outbound_caller_id_number',
                                                    'emergency_caller_id_number',
                                                    'container_3',
                                                    'submit',

                                                ]" />
                                                <FormTab name="api_tokens" label="API Keys" :elements="[
                                                    'html',
                                                    'add_token',
                                                    'token_title',

                                                ]" :conditions="[() => options.permissions.api_key]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>

                                                <HiddenElement name="extension_uuid" :meta="true" />

                                                <StaticElement name="basic_info_title" tag="h4" content="Basic Info"
                                                    description="Fill in basic details to identify and describe this extension." />
                                                <TextElement name="directory_first_name" label="First Name"
                                                    placeholder="Enter First Name" :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" />
                                                <TextElement name="directory_last_name" label="Last Name"
                                                    placeholder="Enter Last Name" :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" />
                                                <TextElement name="extension" label="Extension"
                                                    placeholder="Enter Extension" :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        }
                                                    }" />
                                                <TextElement name="voicemail_mail_to" label="Email"
                                                    placeholder="Enter Email" :floating="false" :columns="{
                                                        container: 6,
                                                    }" />
                                                <TextElement name="description" label="Description"
                                                    placeholder="Enter Description" :floating="false" />

                                                <GroupElement name="container_2" />

                                                <ToggleElement name="suspended" text="Suspended"
                                                    description="Prevents users from making or receiving calls, except for emergency calls. Typically used for billing or policy-related suspensions."
                                                    :replace-class="{
                                                        'toggle.toggleOn': {
                                                            'form-bg-primary': 'bg-red-500',
                                                            'form-border-color-primary': 'border-red-500',
                                                            'form-color-on-primary': 'form-color-on-danger'

                                                        }
                                                    }" />

                                                <StaticElement name="divider" tag="hr" />

                                                <ToggleElement name="enabled" text="Status" true-value="true"
                                                    false-value="false"
                                                    description="Activate or deactivate the extension. When deactivated, devices cannot connect and calls cannot be placed or received." />

                                                <StaticElement name="divider2" tag="hr" />

                                                <ToggleElement name="directory_visible"
                                                    text="Show in company dial-by-name directory"
                                                    description="Controls whether this extension appears in the company’s dial-by-name directory. Hide extensions for devices (door phones, intercoms) or private users (e.g., executives)."
                                                    true-value="true" false-value="false" />

                                                <StaticElement name="divide3" tag="hr" />

                                                <ToggleElement name="directory_exten_visible"
                                                    text="Announce extension after name in directory"
                                                    description="Controls whether the extension number is played after the user’s name in the directory. Useful for making it easier for callers to reach the extension directly. Disable for privacy or security reasons."
                                                    true-value="true" false-value="false" />


                                                <!-- Caller ID Tab -->
                                                <StaticElement name="external_caller_id_title" tag="h4"
                                                    content="External Caller ID"
                                                    description="Define the External Caller ID that will be displayed on the recipient's device when dialing outside the company." />

                                                <SelectElement name="outbound_caller_id_number"
                                                    :items="options.phone_numbers" :search="true" :native="false"
                                                    input-type="search" autocomplete="off" />
                                                <StaticElement name="emergency_caller_id_title" tag="h4"
                                                    content="Emergency Caller ID"
                                                    description="Define the Emergency Caller ID that will be displayed when dialing emergency services." />

                                                <SelectElement name="emergency_caller_id_number"
                                                    :items="options.phone_numbers" :search="true" :native="false"
                                                    input-type="search" autocomplete="off" />

                                                <GroupElement name="container_3" />

                                                <ButtonElement name="submit" button-label="Save" :submits="true"
                                                    align="right" />



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

    <CreateApiTokenModal :show="showApiTokenModal" :options="options" @close="showApiTokenModal = false"
        @error="emitErrorToParentFromChild" @success="emitSuccessToParentFromChild" @refresh-data="getTokens" />


    <ConfirmationModal :show="showDeleteConfirmationModal" @close="showDeleteConfirmationModal = false"
        @confirm="confirmDeleteAction" :header="'Confirm Deletion'" :loading="isDeleteTokenLoading"
        :text="'This action will permanently delete the selected API Key. Are you sure you want to proceed?'"
        confirm-button-label="Delete" cancel-button-label="Cancel" />
</template>

<script setup>
import { ref } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/solid";
import ConfirmationModal from "./../modal/ConfirmationModal.vue";
import CreateApiTokenModal from "./../modal/CreateApiTokenModal.vue"


const emit = defineEmits(['close', 'error', 'success', 'refresh-data'])

const props = defineProps({
    show: Boolean,
    options: Object,
    header: String,
    loading: Boolean,
});

const form$ = ref(null)
const showResetConfirmationModal = ref(false);
const isTokensLoading = ref(false)
const isDeleteTokenLoading = ref(false)
const showDeleteConfirmationModal = ref(false)
const tokens = ref([])
const addTokenButtonLoading = ref(false)
const showApiTokenModal = ref(false)
const confirmDeleteAction = ref(null);


const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData
    console.log(requestData);

    return await form$.$vueform.services.axios.put(props.options.routes.update_route, requestData)
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


const requestResetPassword = () => {
    showResetConfirmationModal.value = true;
};

// const confirmResetPassword = async () => {
//     showResetConfirmationModal.value = false;

//     try {
//         await form$.value.$vueform.services.axios.post(
//             props.options.routes.password_reset,
//             {
//                 email: props.options.item.voicemail_mail_to,
//             }
//         );

//         emit("success", "success", { success: ["Password reset email sent successfully."] });
//     } catch (error) {
//         emit("error", error);
//     }
// };

const handleAddTokenButtonClick = () => {
    showApiTokenModal.value = true
}

const handleTabSelected = (activeTab, previousTab) => {
    if (activeTab.name == 'api_tokens') {
        getTokens()
    }
}

const getTokens = async () => {
    isTokensLoading.value = true
    axios.get(props.options.routes.tokens, {
        params: {
            uuid: props.options.item.user_uuid
        }
    })
        .then((response) => {
            tokens.value = response.data.data;
            // console.log(tokens.value);

        }).catch((error) => {
            emit('error', error)
        }).finally(() => {
            isTokensLoading.value = false
        });
}

// const handleUpdateTokenButtonClick = async uuid => {
//     updateTokenButtonLoading.value = true;
//     try {
//         await getHolidayItemOptions(uuid);
//         showUpdateTokenModal.value = true;
//     } catch (err) {
//         handleModalClose();
//         emit('error', err);
//     } finally {
//         updateTokenButtonLoading.value = false;
//     }
// };


const handleDeleteTokenButtonClick = (uuid) => {
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
};


const emitErrorToParentFromChild = (error) => {
    emit('error', error);
}

const emitSuccessToParentFromChild = (message) => {
    emit('success', 'success', message);
}


const executeBulkDelete = async (items) => {
    isDeleteTokenLoading.value = true;

    try {
        const response = await axios.post(
            props.options.routes.token_bulk_delete,
            { items }
        );
        emit('success', 'success', response.data.messages);
        getTokens();
    } catch (error) {
        emit('error', error);
    } finally {
        // hide both the delete and the confirmation modals
        handleModalClose();
        isDeleteTokenLoading.value = false;
    }
};

const handleModalClose = () => {
    showResetConfirmationModal.value = false;
    showApiTokenModal.value = false
    showDeleteConfirmationModal.value = false;
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

<style>
div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}</style>