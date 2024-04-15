<template>
    <form>
        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <div class="sm:col-span-12">
                <LabelInputOptional :target="'template'" :label="'Template'" />
                <div class="mt-2">
                    <SelectBox :options="device.device_options.templates"
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
                               :search="true"
                               :placeholder="'Choose profile'"
                               @update:modal-value="handleUpdateProfile"
                    />
                </div>
            </div>
        </div>
    </form>
</template>

<script setup>
import SelectBox from "../general/SelectBox.vue";
import LabelInputOptional from "../forms/LabelInputOptional.vue";

const props = defineProps({
    device: Object
});

const handleUpdateTemplate = (newSelectedItem) => {
    props.device.device_template = newSelectedItem.value
}

const handleUpdateProfile = (newSelectedItem) => {
    props.device.device_profile_uuid = newSelectedItem.value
}
</script>
