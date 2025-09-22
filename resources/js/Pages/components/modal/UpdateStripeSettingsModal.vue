<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="div" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                        <DialogPanel
                            class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">


                            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                                @response="handleResponse" :display-errors="false" :default="{
                                    uuid: uuid ?? null,
                                    status: isEnabled ?? false,
                                    sandbox: settings.sandbox ?? false,
                                    sandbox_secret_key: settings.sandbox_secret_key ?? null,
                                    sandbox_publishable_key: settings.sandbox_publishable_key ?? null,
                                    live_mode_secret_key: settings.live_mode_secret_key ?? null,
                                    live_mode_publishable_key: settings.live_mode_publishable_key ?? null,
                                    webhook_secret: settings.webhook_secret ?? null,
                                }">
                                <StaticElement name="title" tag="h3" content="Stripe Settings"
                                    description="Please fill out the following fields to configure your gateway settings." />

                                <HiddenElement name="uuid" :meta="true" />

                                <RadiogroupElement name="status" label="Enable/Disable Gateway" :rules="[
                                    'required',
                                ]" :items="{ true: 'On', false: 'Off', }" />

                                <ToggleElement name="sandbox" text="Turn on Sandbox mode"
                                    description="When in Sandbox mode, no credit cards will actually be processed"
                                    true-value="true" false-value="false" />

                                <TextElement name="sandbox_secret_key" label="Sandbox Secret Key"
                                    description="Provide the secret key for the sandbox environment."
                                    placeholder="Enter Sandbox Secret Key" :floating="false" />
                                <TextElement name="sandbox_publishable_key" label="Sandbox Publishable Key"
                                    description="Enter the publishable key for the sandbox environment."
                                    placeholder="Enter Sandbox Publishable Key" :floating="false" />
                                <TextElement name="live_mode_secret_key" label="Live Mode Secret Key"
                                    description="Insert the secret key for the live mode."
                                    placeholder="Enter Live Mode Secret Key" :floating="false" />
                                <TextElement name="live_mode_publishable_key" label="Live Mode Publishable Key"
                                    description="Provide the publishable key for the live mode."
                                    placeholder="Enter Live Mode Publishable Key" :floating="false" />

                                <TextElement name="webhook_secret" label="Webhook Secret"
                                    description="Provide the webhook secret."
                                    placeholder="Enter Webhook Secret" :floating="false" />
                                <ButtonElement @click="emit('close')" name="cancel" button-label="Cancel"
                                    :secondary="true" :columns="{
                                        container: 6,
                                    }" :full="true" />
                                <ButtonElement name="submit" button-label="Submit" :submits="true" :full="true"
                                    align="center" :columns="{
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
import { Dialog, DialogPanel, TransitionChild, TransitionRoot } from '@headlessui/vue'

const emit = defineEmits(['close', 'confirm', 'success', 'error', 'refresh-data'])

const form$ = ref(null)

const props = defineProps({
    show: Boolean,
    settings: Object,
    uuid: String,
    isEnabled: Boolean,
    route: String,
});

const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData

    return await form$.$vueform.services.axios.put(props.route, requestData)
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
    if (response?.data?.errors) {
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

    emit('success', response.data.messages);
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

</script>
