<template>
    <label :for="target" :class="style">{{ label }}<span class="ml-1 ordinal text-red-600">*</span></label>
</template>
<script setup>
const props = defineProps({
    target: String,
    style: {
        type: String,
        default: 'block text-sm font-medium leading-6 text-gray-900'
    },
    label: String
});
</script>
