<template>
    <label
        for="device_address"
           class="">
        MacAddress
        <span class="text-base/loose text-red-600">*</span>
    </label>
</template>
<script setup>
const props = defineProps({
    for: String,
    class: {
        type: String,
        default: 'block text-sm font-medium leading-6 text-gray-900'
    },
    label: String
});
</script>
