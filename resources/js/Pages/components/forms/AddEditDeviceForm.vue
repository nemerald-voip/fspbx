<template>
    <form>
        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <div class="sm:col-span-12">
                <LabelInputRequired :target="'device_address'" :label="'MacAddress'" />
                <div class="mt-2">
                    <input v-model="device.device_address" :disabled="isEdit" :class="{ 'disabled:opacity-50': isEdit }" type="text" name="device_address" id="device_address" placeholder="Enter the MAC address"
                           class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"/>
                </div>
            </div>

            <div class="sm:col-span-12">
                <LabelInputRequired :target="'template'" :label="'Template'" />
                <div class="mt-2">
                    <SelectBox :options="device.device_options.templates"
                               :selectedItem="device.device_template"
                               :search="true"
                               :placeholder="'Choose template'"
                               @update:modal-value="handleUpdateTemplate"
                    />
                </div>
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'profile'" :label="'Profile'" />
                <div class="mt-2">
                    <SelectBox :options="device.device_options.profiles"
                               :selectedItem="device.device_profile_uuid"
                               :search="true"
                               :placeholder="'Choose profile'"
                               @update:modal-value="handleUpdateProfile"
                    />
                </div>
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'extension'" :label="'Extension'" />
                <div class="mt-2">
                    <SelectBox :options="device.device_options.extensions"
                               :selectedItem="device.extension_uuid"
                               :search="true"
                               :placeholder="'Choose extension'"
                               @update:modal-value="handleUpdateExtension"
                    />
                </div>
            </div>
        </div>
    </form>
</template>

<script setup>
import SelectBox from "../general/SelectBox.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";

const props = defineProps({
    device: Object,
    isEdit: {
        type: Boolean,
        default: false,
    },
});

const handleUpdateTemplate = (newSelectedItem) => {
    props.device.device_template = newSelectedItem.value
}

const handleUpdateProfile = (newSelectedItem) => {
    props.device.device_profile_uuid = newSelectedItem.value
}

const handleUpdateExtension = (newSelectedItem) => {
    props.device.extension_uuid = newSelectedItem.value
}
</script>
