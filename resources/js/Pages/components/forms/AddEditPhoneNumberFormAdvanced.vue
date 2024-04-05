<template>
    <form>
        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <div class="sm:col-span-12">
                <LabelInputRequired :target="'destination_condition_field'" :label="'Conditions'" />
                <div class="mt-2">
                    <input v-model="phoneNumber.destination_condition_field" :disabled="isEdit" :class="{ 'disabled:opacity-50': isEdit }" type="text" name="destination_condition_field" id="destination_condition_field" placeholder="Conditions"
                           class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"/>
                </div>
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'destination_caller_id_name'" :label="'Caller ID Name'" />
                <div class="mt-2">
                    <input v-model="phoneNumber.destination_caller_id_name" type="text" name="destination_caller_id_name" id="destination_caller_id_name" placeholder="Enter caller id name"
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
import {defineProps} from 'vue'
import LabelInputRequired from "../forms/LabelInputRequired.vue";
import LabelInputOptional from "../forms/LabelInputOptional.vue";
import SelectBox from "../general/SelectBox.vue";

const props = defineProps({
    phoneNumber: Object,
    isEdit: {
        type: Boolean,
        default: false,
    },
});

const handleUpdateType = (newSelectedItem) => {
    props.phoneNumber.destination_type = newSelectedItem.value
}
</script>
