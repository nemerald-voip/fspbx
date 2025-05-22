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
                            class="relative transform  rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md sm:p-6">

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                                @response="handleResponse" :display-errors="false" :default="{
                                    user_uuid: options.item.user_uuid,
                                }">
                                <HiddenElement name="user_uuid" :meta="true" />
                                <StaticElement name="h4" tag="h4" content="Create API Key" />

                                <TextElement name="name" label="Name"
                                    description="Enter a clear, descriptive name for this API Key." />

                                <StaticElement name="html">
                                    <div v-if="token" class="rounded-md bg-green-50 p-4">
                                        <div class="flex">
                                            <div class="shrink-0">
                                                <CheckCircleIcon class="size-5 text-green-400" aria-hidden="true" />
                                            </div>
                                            <div class="ml-3 truncate">
                                                <p class=" text-sm font-medium text-green-800">{{ token }}</p>
                                            </div>
                                            <div class="ml-auto pl-3">
                                                <div class="-mx-1.5 -my-1.5">
                                                    <button type="button" @click="copyTokenToClipboard"
                                                        class="inline-flex rounded-md bg-green-50 p-1.5 text-green-500 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-600 focus:ring-offset-2 focus:ring-offset-green-50">
                                                        <ClipboardDocumentIcon class="size-5" aria-hidden="true" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </StaticElement>
                                <GroupElement name="container_3" />
                                <ButtonElement name="reset" button-label="Cancel" :secondary="true" :resets="true"
                                    @click="emit('close')" :columns="{
                                        container: 6,
                                    }" />
                                <ButtonElement name="submit" button-label="Create" :submits="true" align="right" :columns="{
                                    container: 6,
                                }" />
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
import {CheckCircleIcon, XMarkIcon } from "@heroicons/vue/24/solid";
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";


const emit = defineEmits(['close', 'confirm', 'success', 'error', 'refresh-data'])

const props = defineProps({
    show: Boolean,
    options: Object,
});

const form$ = ref(null)
const token = ref(null)

const copyTokenToClipboard = async () => {
    if (token.value) {
        try {
            await navigator.clipboard.writeText(token.value);
            // Optional: Provide feedback (toast, alert, UI state)
            emit('success',{messages: ['API Key is copied to clipboard']});

        } catch (e) {
            // Optional: Handle clipboard error (permissions, etc)
            emit('success',{messages: ['Failed to copy token.']});
        }
    }
}

const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.data

    // console.log(requestData);
    return await form$.$vueform.services.axios.post(props.options.routes.create_token, requestData)
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

    token.value = response.data.token
    emit('success', response.data.messages);
    // emit('close');
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
