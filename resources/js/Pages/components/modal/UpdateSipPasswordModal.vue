<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 transition-opacity" />
            <div class="relative z-10 flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div
                    class="relative transform rounded-lg bg-surface px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md sm:p-6">

                    <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                        <button type="button"
                            class="rounded-md bg-surface text-subtle hover:text-muted focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2"
                            @click="emit('close')">
                            <span class="sr-only">Close</span>
                            <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                        </button>
                    </div>

                    <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                        @response="handleResponse" :display-errors="false" :default="{
                            extension_uuid: extension_uuid,
                            password: sip_credentials?.password,
                        }">
                        <HiddenElement name="extension_uuid" :meta="true" />
                        <StaticElement name="h4" tag="h4" content="Update SIP Password" />

                        <TextElement name="password" label="Password" description="" />

                        <GroupElement name="container_3" />
                        <ButtonElement name="reset" button-label="Cancel" :secondary="true" :resets="true"
                            @click="emit('close')" :columns="{
                                container: 6,
                            }" />
                        <ButtonElement name="submit" button-label="Update" :submits="true" align="right" :columns="{
                            container: 6,
                        }" />
                    </Vueform>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref } from "vue";
import { XMarkIcon } from "@heroicons/vue/24/solid";


const emit = defineEmits(['close', 'confirm', 'success', 'error', 'refresh-data'])

const props = defineProps({
    show: Boolean,
    sip_credentials: Object,
    extension_uuid: String,
    route: String,
});

const form$ = ref(null)

const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.data

    // console.log(requestData);
    return await form$.$vueform.services.axios.post(props.route, requestData)
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



</script>
