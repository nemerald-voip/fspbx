<template>
    <form @submit.prevent="submitForm">
        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <div class="sm:col-span-12">
                <LabelInputRequired :target="'destination'" :label="'Phone Number'" />
                <div class="mt-2">
                    <InputField v-model="form.destination" type="text" name="destination"
                        placeholder="Enter Phone Number"
                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                </div>
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'extension'" :label="'Extension'" />
                <div class="mt-2">
                    <SelectBox :options="options.chatplan_detail_data" :selectedItem="item.chatplan_detail_data"
                        :search="true" :placeholder="'Choose extension'" @update:modal-value="handleUpdateExtension" />
                </div>
                <p class="mt-3 text-sm leading-6 text-gray-600">Assign the extension to which the messages should be
                    forwarded.</p>
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'email'" :label="'Email'" />
                <div class="mt-2">
                    <InputField v-model="form.email" type="text" name="email" placeholder="Optional"
                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                </div>
                <p class="mt-3 text-sm leading-6 text-gray-600">You can choose to use email instead of the extension if
                    you'd like.</p>
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'description'" :label="'Description'" />
                <div class="mt-2">
                    <Textarea v-model="form.description" name="description" rows="2" />
                </div>
            </div>
        </div>
        <div class="border-t mt-4 sm:mt-4 ">
            <div class="mt-4 sm:mt-4 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                <button type="submit"
                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                    ref="saveButtonRef">Save
                </button>
                <button type="button"
                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                    @click="emits('close')" ref="cancelButtonRef">Cancel
                </button>
            </div>
        </div>


    </form>
</template>

<script setup>
import { reactive, ref } from "vue";
import { usePage } from '@inertiajs/vue3';
// import { useForm } from '@inertiajs/vue3'
// import { useForm } from 'laravel-precognition-vue-inertia';

import SelectBox from "../general/SelectBox.vue";
import Textarea from "../general/Textarea.vue";
import InputField from "../general/InputField.vue";
import LabelInputOptional from "../forms/LabelInputOptional.vue";
import LabelInputRequired from "../forms/LabelInputRequired.vue";

const props = defineProps({
    item: Object,
    options: Object,
});

const page = usePage();

const form = reactive({
    destination: props.item.destination,
    chatplan_detail_data: props.item.chatplan_detail_data,
    email: props.item.email,
    description: props.item.description,
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel']);

const submitForm = () => {
    console.log(form);

    // emits('submit', form); // Emit the event with the form data

    axios.put('/message-settings/' + props.item.sms_destination_uuid, form)
        .then((response) => {
            console.log(response);
            // handleSearchButtonClick();
            // handleClose();
        }).catch((error) => {
            if (error.response) {
                // The request was made and the server responded with a status code
                // that falls out of the range of 2xx
                console.log(error.response.data);
                handleErrorsPush(error.response.data.message, error.response.data.errors);
            } else if (error.request) {
                // The request was made but no response was received
                // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
                // http.ClientRequest in node.js
                console.log(error.request);
            } else {
                // Something happened in setting up the request that triggered an Error
                console.log('Error', error.message);
            }
            console.log(error.config);
        });
}

const handleUpdateExtension = (newSelectedItem) => {
    form.extension = newSelectedItem.value
}

</script>
