<template>
    basic
    <!--form>
        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <div class="sm:col-span-12">
                <LabelInputRequired :target="'destination_prefix'" :label="'Country Code'" />
                <div class="mt-2">
                    <input v-model="phoneNumber.destination_prefix" :disabled="isEdit" :class="{ 'disabled:opacity-50': isEdit }" type="text" name="destination_prefix" id="destination_prefix" placeholder="Enter country code"
                           class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"/>
                </div>
            </div>

            <div class="sm:col-span-12">
                <LabelInputRequired :target="'destination_number'" :label="'Phone Number'" />
                <div class="mt-2">
                    <input v-model="phoneNumber.destination_number" :disabled="isEdit" :class="{ 'disabled:opacity-50': isEdit }" type="text" name="destination_number" id="destination_number" placeholder="Enter phone number"
                           class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"/>
                </div>
            </div>
            <div class="sm:col-span-12">
                <LabelInputOptional :target="'destination_actions'" :label="'Actions'" />
                <div class="mt-2">
                    <input v-model="phoneNumber.destination_actions" type="text" name="destination_actions" id="destination_actions" placeholder="Enter actions"
                           class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"/>
                </div>
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'destination_hold_music'" :label="'Hold Music'" />
                <div class="mt-2">
                    <SelectBox :options="phoneNumber.phonenumber_options.holdmusic"
                               :search="true"
                               :placeholder="'Choose music'"
                               @update:modal-value="handleUpdateTemplate"
                    />
                </div>
            </div>

            <div class="sm:col-span-12">
                <Toggle
                    :target="'destination_enabled'"
                    :label="'Enabled'"
                    :enabled="destinationEnabledTrigger"
                    @update:status="destinationEnabledTrigger = false"
                />
            </div>
        </div>
    </form-->
</template>

<script setup>
import {defineProps, ref} from 'vue'
import LabelInputRequired from "../forms/LabelInputRequired.vue";
import LabelInputOptional from "../forms/LabelInputOptional.vue";
import Toggle from "../forms/Toggle.vue";
import SelectBox from "../general/SelectBox.vue";

const destinationEnabledTrigger = ref(false);

const props = defineProps({
    phoneNumber: Object,
    isEdit: {
        type: Boolean,
        default: false,
    },
});

</script>
