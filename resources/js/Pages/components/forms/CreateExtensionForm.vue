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
                                    extension_uuid: options.item?.extension_uuid ?? '',
                                    directory_first_name: options.item?.directory_first_name ?? '',
                                    directory_last_name: options.item?.directory_last_name ?? '',
                                    extension: options.item?.extension ?? '',
                                    last_name: options.item?.last_name ?? '',
                                    voicemail_mail_to: options.item?.email ?? '',
                                    description: options.item?.description ?? '',
                                    suspended: options.item?.suspended ?? false,
                                    

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
                                                    
                                                    'container_basic',
                                                    'submit_basic',

                                                ]" />
                                                
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
                                                    }" :disabled="!options.permissions?.extension_extension"/>
                                                    
                                                <TextElement name="voicemail_mail_to" label="Email"
                                                    placeholder="Enter Email" :floating="false" :columns="{
                                                        container: 6,
                                                    }" />
                                                <TextElement name="description" label="Description"
                                                    placeholder="Enter Description" :floating="false" />

                                                
                                                <GroupElement name="container_basic" />

                                                <ButtonElement name="submit_basic" button-label="Save" :submits="true"
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

</template>

<script setup>
import { ref } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/solid";



const emit = defineEmits(['close', 'error', 'success', 'refresh-data', 'open-edit-form'])

const props = defineProps({
    show: Boolean,
    options: Object,
    header: String,
    loading: Boolean,
});

const form$ = ref(null)


const handleTabSelected = (activeTab, previousTab) => {

}

const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData
    // console.log(requestData);

    return await form$.$vueform.services.axios.post(props.options.routes.store_route, requestData)
};

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

const handleSuccess = (response, form$) => {
    // console.log(response) // axios response
    // console.log(response.status) // HTTP status code
    // console.log(response.data) // response data

    emit('success', 'success', response.data.messages);
    emit('close');
    emit('refresh-data');
    emit('open-edit-form', response.data.extension_uuid);
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