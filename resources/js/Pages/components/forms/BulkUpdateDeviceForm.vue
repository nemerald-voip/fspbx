<template>
    <form @submit.prevent="submitForm">
        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <!-- <div class="sm:col-span-12">
                <LabelInputRequired :target="'device_address'" :label="'MAC Address'" />
                <div class="mt-2">
                    <InputField v-model="form.device_address" type="text" name="device_address"
                        placeholder="Enter MAC Address"
                        :error="errors?.device_address && errors.device_address.length > 0" />
                </div>
                <div v-if="errors?.device_address" class="mt-2 text-sm text-red-600">
                    {{ errors.device_address[0] }}
                </div>
            </div> -->

            <div class="sm:col-span-12">
            <div class="rounded-md bg-blue-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <InformationCircleIcon class="h-5 w-5 text-blue-400" aria-hidden="true" />
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">Select a new option to apply to all items, use the clear button to unset current settings, or leave unchanged to keep existing values </p>
                        
                    </div>
                </div>
            </div>
        </div>


        <div class="sm:col-span-12">
            <LabelInputRequired :target="'template'" :label="'Device Template'" />
            <div class="mt-2">
                <ComboBox :options="options.templates" :selectedItem="form.device_template" :search="true" :showUndo="form.device_template === 'NULL'"
                    :placeholder="placeholderText('device_template')" @update:modal-value="handleTemplateUpdate"
                    :error="errors?.device_template && errors.device_template.length > 0" />
            </div>
            <!-- <p class="mt-3 text-sm leading-6 text-gray-600">Select a new common template for all selected devices</p> -->
            <div v-if="errors?.device_template" class="mt-2 text-sm text-red-600">
                {{ errors.device_template[0] }}
            </div>
        </div>

        <div class="sm:col-span-12">
            <LabelInputOptional :target="'profile'" :label="'Device Profile'" />
            <div class="mt-2">
                <ComboBox :options="options.profiles" :selectedItem="form.device_profile_uuid" :search="true" :showClear="true" :showUndo="form.device_profile_uuid === 'NULL'"
                    :placeholder="placeholderText('device_profile_uuid')" @update:modal-value="handleProfileUpdate" />
            </div>
            <!-- <p class="mt-3 text-sm leading-6 text-gray-600">Assign the extension to which the messages should be
                    forwarded.</p> -->
        </div>


        <div v-if="page.props.auth.can.device_edit_line" class="sm:col-span-12">
            <LabelInputOptional :target="'extension'" :label="'Assigned Extension'" />
            <div class="mt-2">
                <ComboBox :options="options.extensions" :selectedItem="form.extension" :search="true" :showClear="true" :showUndo="form.extension === 'NULL'"
                    :placeholder="placeholderText('extension')" @update:modal-value="handleExtensionUpdate" />
            </div>
            <!-- <p class="mt-3 text-sm leading-6 text-gray-600">Assign the extension to which the messages should be
                    forwarded.</p> -->
        </div>

        <div v-if="page.props.auth.can.domain_select && page.props.auth.can.device_edit_domain" class="sm:col-span-12">
            <LabelInputRequired :target="'domain'" :label="'Owned By (Company Name)'" />
            <div class="mt-2">
                <ComboBox :options="options.domains" :selectedItem="form.domain_uuid" :search="true" :showUndo="form.domain_uuid === 'NULL'"
                    :placeholder="placeholderText('domain_uuid')" @update:modal-value="handleDomainUpdate"
                    :error="errors?.domain_uuid && errors.domain_uuid.length > 0" />
            </div>
            <div v-if="errors?.domain_uuid" class="mt-2 text-sm text-red-600">
                {{ errors.domain_uuid[0] }}
            </div>
        </div>
        </div>

        <div class="border-t mt-4 sm:mt-4 ">
            <div class="mt-4 sm:mt-4 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                <button type="submit"
                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                    ref="saveButtonRef" :disabled="isSubmitting">
                    <Spinner :show="isSubmitting" />
                    Save
                </button>
                <button type="button"
                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                    @click="emits('cancel')" ref="cancelButtonRef">Cancel
                </button>
            </div>
        </div>
    </form>
</template>

<script setup>
import { reactive } from "vue";
import { usePage } from '@inertiajs/vue3';


import SelectBox from "../general/SelectBox.vue";
import ComboBox from "../general/ComboBox.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";
import Spinner from "../general/Spinner.vue";
import { InformationCircleIcon } from '@heroicons/vue/20/solid'

const props = defineProps({
    items: Object,
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();

const form = reactive({
    items: props.items,
    device_template: null,
    device_profile_uuid: null,
    extension: null,
    domain_uuid: null,
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel', 'domain-selected']);

const submitForm = () => {
    // console.log(form);
    emits('submit', form); // Emit the event with the form data
}

const handleTemplateUpdate = (newSelectedItem) => {
    form.device_template = newSelectedItem.value;
}

const handleProfileUpdate = (newSelectedItem) => {
    form.device_profile_uuid = newSelectedItem.value
}

const handleExtensionUpdate = (newSelectedItem) => {
    form.extension = newSelectedItem.value
}

const handleDomainUpdate = (newSelectedItem) => {
    form.domain_uuid = newSelectedItem.value;
    form.device_profile_uuid = "NULL";
    form.extension = "NULL";
    if (newSelectedItem.value !== "NULL") {
        emits('domain-selected', newSelectedItem.value); // Emit 'domain-selected' event when the domain is updated
    }}

// Function to determine placeholder based on the current value
function placeholderText(fieldName) {
    const fieldValue = form[fieldName];
    if (fieldValue === null) {
        return "Keep existing setting";
    } else if (fieldValue === "NULL") {
        return "Current settings will be cleared";
    } else {
        return fieldValue; // Use the actual value as the placeholder
    }
}


</script>