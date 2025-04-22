<template>
    <div>
        <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError" @response="handleResponse"
            :display-errors="false">
            <template #empty>

                <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                    <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                        <FormTabs view="vertical">
                            <FormTab name="page0" label="Settings" :elements="[
                                'ring_group_uuid',
                                'h4',
                                'ring_group_name',
                                'ring_group_extension',
                                'ring_group_description',
                                'settings_submit'
                            ]"/>

                        </FormTabs>
                    </div>

                    <div
                        class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                        <FormElements>

                            <StaticElement name="h4" tag="h4" content="Settings"
                                description="Provide basic information about the ring group" />
                            <TextElement name="ring_group_name" label="Name" :columns="{
                                sm: {
                                    container: 6,
                                },
                                lg: {
                                    container: 6,
                                },
                            }" placeholder="Enter Ring Group Name" :floating="false" />
                            <TextElement name="ring_group_extension" :columns="{
                                sm: {
                                    container: 6,
                                },
                                lg: {
                                    container: 6,
                                },
                            }" label="Extension" placeholder="Enter Extension" :floating="false" />

 
                            <TextareaElement name="ring_group_description" label="Description" :rows="2" />

                            <ButtonElement name="settings_submit" button-label="Save" :submits="true" align="right" />


                        </FormElements>
                    </div>
                </div>
            </template>
        </Vueform>
    </div>

</template>

<script setup>
import { onMounted, reactive, ref, watch, computed } from "vue";

import { Cog6ToothIcon, MusicalNoteIcon, AdjustmentsHorizontalIcon } from '@heroicons/vue/24/outline';


const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const form$ = ref(null)
// Initialize activeTab with the currently active tab from props
const showEditModal = ref(false);
const isDownloading = ref(false);
const isGreetingUpdating = ref(false);
const showNewGreetingModal = ref(false);
const showDeleteConfirmation = ref(false);
const greetingLabel = ref(null);

onMounted(() => {
    form$.value.update({ // updates form data
        ring_group_name: props.options.ring_group.ring_group_name ?? null,
        ring_group_extension: props.options.ring_group.ring_group_extension ?? null,
        
    })

    form$.value.clean()
    // console.log(form$.value.data);
})


// Make a local reactive copy of options to manipulate in this component
const localOptions = reactive({ ...props.options });

// Watch for changes in props.options and update localOptions accordingly
watch(() => props.options, (newOptions) => {
    Object.assign(localOptions, newOptions);
});



const emits = defineEmits(['close', 'error', 'success', 'refresh-data', 'open-edit-form']);

const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData

    // console.log(requestData);
    return await form$.$vueform.services.axios.post(localOptions.routes.store_route, requestData)
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

    emits('success', 'success', response.data.messages);
    emits('close');
    emits('refresh-data');
    emits('open-edit-form', response.data.ring_group_uuid);
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
            emits('error', error);
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
}</style>