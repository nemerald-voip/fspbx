<template>
    <input
        v-bind="{ id: id }"
        :name="name"
        :type="type"
        :placeholder="placeholder"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        :class="inputClass" 
        :disabled="disabled"/>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    id: {
        type: String,
        default: null,
    },
    name: String,
    type: String,
    placeholder: String,
    modelValue: String,
    error: Boolean,
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

// Compute the classes based on the state
const inputClass = computed(() => {
    let baseClasses = 'block w-full rounded-md border-0 py-1.5 shadow-sm ring-1 ring-inset placeholder:text-gray-400 focus:ring-2 focus:ring-inset sm:text-sm sm:leading-6';
    if (props.error) {
        return `${baseClasses} text-red-900 ring-red-600`; // Apply red text and ring if there's an error
    }
    if(props.disabled) {
        return `${baseClasses} text-gray-900 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 disabled:ring-gray-200`; // Apply disabled class
    }
    return `${baseClasses} text-gray-900 ring-gray-300`;  // Default text and ring color when no error
});

</script>
