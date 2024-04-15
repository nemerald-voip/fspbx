<template>
    <form class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2">
        <div class="px-4 py-6 sm:p-8">
        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <div class="sm:col-span-12">
                <Toggle
                    :target="'destination_record'"
                    :label="'Check to save recordings'"
                    :enabled="destinationRecordTrigger"
                    @update:status="destinationRecordTrigger = false"
                />
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'fax_uuid'" :label="'Fax detection'"/>
                <div class="mt-2">
                    <SelectBox :options="phoneNumber.phonenumber_options.faxes"
                               :search="true"
                               :allowEmpty="true"
                               :placeholder="'Choose fax'"
                               @update:modal-value="handleUpdateFax"
                    />
                </div>
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
                <LabelInputOptional :target="'destination_distinctive_ring'" :label="'Distinctive ring'" />
                <div class="mt-2">
                    <input v-model="phoneNumber.destination_distinctive_ring" type="text" name="destination_distinctive_ring" id="destination_distinctive_ring" placeholder="Enter distinctive ring"
                           class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"/>
                </div>
            </div>

            <div class="sm:col-span-12">
                <LabelInputRequired :target="'domain_uuid'" :label="'Domain'"/>
                <div class="mt-2">
                    <SelectBox :options="phoneNumber.phonenumber_options.domains"
                               :selectedItem="phoneNumber.domain_uuid"
                                    :search="true"
                                    :placeholder="'Choose domain'"
                                    @update:modal-value="handleUpdateDomain"
                    />
                </div>
            </div>
        </div>
        </div>
    </form>
</template>

<script setup>
import {defineProps, ref} from 'vue'
import LabelInputRequired from "../general/LabelInputRequired.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import SelectBox from "../general/SelectBox.vue";
import Toggle from "../general/Toggle.vue";
import SelectBoxGroup from "../general/SelectBoxGroup.vue";

const props = defineProps({
    phoneNumber: Object,
    isEdit: {
        type: Boolean,
        default: false,
    },
});

const destinationRecordTrigger = ref(false);

const handleUpdateFax = (newSelectedItem) => {
    if (newSelectedItem !== null && newSelectedItem !== undefined) {
        props.phoneNumber.fax_uuid = newSelectedItem.value
    } else {
        props.phoneNumber.fax_uuid = '';
    }
}

const handleUpdateDomain = (newSelectedItem) => {
    if (newSelectedItem !== null && newSelectedItem !== undefined) {
        props.phoneNumber.domain_uuid = newSelectedItem.value
    } else {
        props.phoneNumber.domain_uuid = '';
    }
}
</script>
