<template>
    <form class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2">
        <div class="px-4 py-6 sm:p-8">
        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <div class="sm:col-span-12">
                <Toggle
                    :target="'destination_record'"
                    :label="'Check to save recordings'"
                    :enabled="destinationRecordTrigger"
                    @update:status="handleDestinationRecord"
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
                    <InputField
                        v-model="phoneNumber.destination_cid_name_prefix"
                        type="text"
                        id="destination_cid_name_prefix"
                        name="destination_cid_name_prefix"
                        placeholder="Enter caller prefix"
                        :error="onSubmitErrors?.destination_cid_name_prefix && onSubmitErrors.destination_cid_name_prefix.length > 0"/>
                </div>
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'destination_accountcode'" :label="'Account code'" />
                <div class="mt-2">
                    <InputField
                        v-model="phoneNumber.destination_accountcode"
                        type="text"
                        id="destination_accountcode"
                        name="destination_accountcode"
                        placeholder="Enter account code"
                        :error="onSubmitErrors?.destination_accountcode && onSubmitErrors.destination_accountcode.length > 0"/>
                </div>
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'destination_distinctive_ring'" :label="'Distinctive ring'" />
                <div class="mt-2">
                    <InputField
                        v-model="phoneNumber.destination_distinctive_ring"
                        type="text"
                        id="destination_distinctive_ring"
                        name="destination_distinctive_ring"
                        placeholder="Enter distinctive ring"
                        :error="onSubmitErrors?.destination_accountcode && onSubmitErrors.destination_accountcode.length > 0"/>
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
import {defineProps, onMounted, ref, watch} from 'vue'
import LabelInputRequired from "../general/LabelInputRequired.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import SelectBox from "../general/SelectBox.vue";
import Toggle from "../general/Toggle.vue";
import SelectBoxGroup from "../general/SelectBoxGroup.vue";
import InputField from "../general/InputField.vue";

const props = defineProps({
    phoneNumber: Object,
    onSubmitErrors: Object,
    isEdit: {
        type: Boolean,
        default: false,
    },
});

const destinationRecordTrigger = ref(false);

onMounted(() => {
    destinationRecordTrigger.value = props.phoneNumber.destination_record;
});

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

const handleDestinationRecord = (newSelectedItem) => {
    props.phoneNumber.destination_record = newSelectedItem;
    destinationRecordTrigger.value = newSelectedItem
}
</script>
