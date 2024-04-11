<template>
    <form>
        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <div class="sm:col-span-12">
                <LabelInputOptional :target="'Fax'" :label="'Fax'" />
                <div class="mt-2">
                    <SelectBox :options="phoneNumber.phonenumber_options.templates"
                               :selectedItem="phoneNumber.fax_uuid"
                               :search="true"
                               :placeholder="'Choose fax'"
                               @update:modal-value="handleUpdateFax"
                    />
                </div>
            </div>

            <div class="sm:col-span-12">
                <Toggle
                    :target="'destination_record'"
                    :label="'Record'"
                    :enabled="destinationRecordTrigger"
                    @update:status="destinationRecordTrigger = false"
                />
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'destination_cid_name_prefix'" :label="'Caller ID name prefix'" />
                <div class="mt-2">
                    <input v-model="phoneNumber.destination_cid_name_prefix" type="text" name="destination_cid_name_prefix" id="destination_cid_name_prefix" placeholder="Enter caller prefix"
                           class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"/>
                </div>
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'destination_accountcode'" :label="'Account code'" />
                <div class="mt-2">
                    <input v-model="phoneNumber.destination_accountcode" type="text" name="destination_accountcode" id="destination_accountcode" placeholder="Enter account code"
                           class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"/>
                </div>
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'destination_caller_id_number'" :label="'Caller ID Name'" />
                <div class="mt-2">
                    <input v-model="phoneNumber.destination_caller_id_number" type="text" name="destination_caller_id_number" id="destination_caller_id_number" placeholder="Enter caller id number"
                           class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"/>
                </div>
            </div>
        </div>
    </form>
</template>

<script setup>
import {defineProps, ref} from 'vue'
import LabelInputRequired from "../forms/LabelInputRequired.vue";
import LabelInputOptional from "../forms/LabelInputOptional.vue";
import SelectBox from "../general/SelectBox.vue";
import Toggle from "./Toggle.vue";

const props = defineProps({
    phoneNumber: Object,
    isEdit: {
        type: Boolean,
        default: false,
    },
});

const destinationRecordTrigger = ref(false);

const handleUpdateFax = (newSelectedItem) => {
    props.phoneNumber.fax_uuid = newSelectedItem.value
}

const handleUpdateTemplate = (newSelectedItem) => {
    props.device.device_template = newSelectedItem.value
}
</script>
