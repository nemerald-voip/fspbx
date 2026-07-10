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
    let baseClasses = 'block w-full rounded-md border-0 py-1.5 bg-surface shadow-sm ring-1 ring-inset placeholder:text-subtle focus:ring-2 focus:ring-inset sm:text-sm sm:leading-6';
    if (props.error) {
        return `${baseClasses} text-danger ring-danger`; // Apply danger text and ring if there's an error
    }
    if(props.disabled) {
        return `${baseClasses} text-heading disabled:cursor-not-allowed disabled:bg-surface-2 disabled:text-muted disabled:ring-strong`; // Apply disabled class
    }
    return `${baseClasses} text-heading ring-strong`;  // Default text and ring color when no error
});

</script>
