<template>
    <div class="flex flex-col xl:flex-row">
        <div class="basis-3/4">
            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                @response="handleResponse" :display-errors="false">

                <template #empty>
                    <div class="space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                        <FormElements>

                            <!-- Header -->
                            <StaticElement name="header" tag="h4" :content="'Call Transcription Options'" />

                            <GroupElement name="container2" />

                            <!-- Enabled -->
                            <ToggleElement name="enabled" text="Enable call transcriptions" :true-value="true"
                                :false-value="false" />

                            <!-- Provider -->
                            <SelectElement name="provider_uuid" label="Provider" :search="true" :items="providers" 
                                                   :floating="false" placeholder="Select Provider"
                                :loading="isProvidersLoading" :native="false" input-type="search" autocomplete="off" :clearable="true"
                                :columns="{ lg: { wrapper: 5 } }"
                                description="Choose the default call transcription provider." />

                            <GroupElement name="container" />

                            <!-- Submit -->
                            <ButtonElement name="save" button-label="Save" :submits="true" />

                        </FormElements>
                    </div>
                </template>
            </Vueform>
        </div>

        <!-- Right rail for help, previews, or saved presets -->
        <div class="basis-1/4 xl:pl-6 mt-8 xl:mt-0">
            <!-- (Optional) You can add contextual help or a live JSON preview here -->
        </div>
    </div>
</template>



<script setup>
import { ref, onMounted } from 'vue'

const props = defineProps({
    routes: Object,
})

const emit = defineEmits(['error']);

const form$ = ref(null)
const providers = ref([])
const isProvidersLoading = ref(null)

onMounted(() => {

    getTranscriptionProviders()

    // console.log('general')
})

const getTranscriptionProviders = async () => {
    isProvidersLoading.value = true
    try {
        const { data } = await axios.get(props.routes.transcription_providers_route)
        providers.value = data
        return data
    } catch (err) {
        emit('error', err);
        providers.value = []
        return []
    } finally {
        isProvidersLoading.value = false
    }
}


const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData

    // console.log(requestData);
    return await form$.$vueform.services.axios.post(props.options.routes.store_route, requestData)
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
    emit('open-edit-form', response.data.business_hours_uuid);
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
