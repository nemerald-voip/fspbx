<template>
    <textarea
        v-bind="{ id: id }"
        :name="name"
        :rows="rows"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
</template>

<script setup>
const props = defineProps({
    id: {
        type: String,
        default: null,
    },
    name: String,
    rows: String,
    modelValue: String,
});

const emit = defineEmits(['update:modelValue']);
</script>
