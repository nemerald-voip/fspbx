<template>
    <form class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2">
        <div class="px-4 py-6 sm:p-8">

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
