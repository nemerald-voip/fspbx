<template>
    <form class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2">
        <div class="px-4 py-6 sm:p-8">
            <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                <div class="sm:col-span-12">
                    <LabelInputRequired :target="'destination_prefix'" :label="'Country Code'"/>
                    <div class="mt-2">
                        <InputField
                            v-model="phoneNumber.destination_prefix"
                            type="text"
                            :disabled="isEdit"
                            id="destination_prefix"
                            name="destination_prefix"
                            placeholder="Enter country code"
                            :error="onSubmitErrors?.destination_prefix && onSubmitErrors.destination_prefix.length > 0"/>
                    </div>
                </div>


                <div class="sm:col-span-12">
                    <LabelInputRequired :target="'destination_number'" :label="'Phone Number'"/>
                    <div class="mt-2">
                        <InputField
                            v-model="phoneNumber.destination_number"
                            type="text"
                            :disabled="isEdit"
                            id="destination_number"
                            name="destination_number"
                            placeholder="Enter phone number"
                            :error="onSubmitErrors?.destination_number && onSubmitErrors.destination_number.length > 0"/>
                    </div>
                </div>

                <div class="sm:col-span-12">
                    <LabelInputOptional :target="'destination_actions'" :label="'If not answered, calls will be sent'"/>
                    <TimeoutDestinations
                        :categories="phoneNumber.phonenumber_options.timeout_destinations_categories"
                        :targets="phoneNumber.phonenumber_options.timeout_destinations_targets"
                        :phoneNumber="phoneNumber"
                    />
                </div>

                <div class="sm:col-span-12">
                    <LabelInputOptional :target="'destination_hold_music'" :label="'Music on Hold'"/>
                    <div class="mt-2">
                        <SelectBoxGroup :options="phoneNumber.phonenumber_options.music_on_hold"
                                        :search="true"
                                        :allowEmpty="true"
                                        :placeholder="'Choose music on hold'"
                                        @update:modal-value="handleUpdateMusicOnHold"
                        />
                    </div>
                </div>

                <div class="sm:col-span-12">
                    <LabelInputOptional :target="'destination_description'" :label="'Description'"/>
                    <div class="mt-2">
                        <Textarea v-model="phoneNumber.destination_description" name="destination_description" rows="2" />
                    </div>
                </div>

                <div class="sm:col-span-12">
                    <Toggle
                        :target="'destination_enabled'"
                        :label="'Enable'"
                        :enabled="destinationEnabledTrigger"
                        @update:status="handleDestinationEnabled"
                    />
                </div>

            </div>
        </div>
    </form>
</template>

<script setup>
import {defineProps, onMounted, ref} from 'vue'
import LabelInputRequired from "../general/LabelInputRequired.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import Toggle from "../general/Toggle.vue";
import SelectBoxGroup from "../general/SelectBoxGroup.vue";
import TimeoutDestinations from "../general/TimeoutDestinations.vue";
import InputField from "../general/InputField.vue";
import Textarea from "../general/Textarea.vue";

const destinationEnabledTrigger = ref(false);

const props = defineProps({
    phoneNumber: Object,
    isEdit: {
        type: Boolean,
        default: false,
    },
});

onMounted(() => {
    if(!Array.isArray(props.phoneNumber.destination_actions)) {
        props.phoneNumber.destination_actions = [];
    }
    destinationEnabledTrigger.value = props.phoneNumber.destination_enabled;
});

const handleUpdateMusicOnHold = (newSelectedItem) => {
    if (newSelectedItem !== null && newSelectedItem !== undefined) {
        props.phoneNumber.destination_hold_music = newSelectedItem.value;
    } else {
        props.phoneNumber.destination_hold_music = '';
    }
}

const handleDestinationEnabled = (newSelectedItem) => {
    props.phoneNumber.destination_enabled = newSelectedItem;
    destinationEnabledTrigger.value = newSelectedItem
}

</script>
