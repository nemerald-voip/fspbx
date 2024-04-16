<template>
    <input  :name="name" 
        :type="type"
        :placeholder="placeholder"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        :class="inputClass" />
</template>
  
<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: String,
    type: String,
    placeholder: String,
    modelValue: String,
    error: Boolean,
});

const emit = defineEmits(['update:modelValue']);

// Compute the classes based on the error state
const inputClass = computed(() => {
    let baseClasses = 'block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset placeholder:text-gray-400 focus:ring-2 focus:ring-inset sm:text-sm sm:leading-6';
    if (props.error) {
        return `${baseClasses} ring-red-600`; // Apply red ring if there's an error
    }
    return `${baseClasses} ring-gray-300`;  // Default ring color when no error
});

</script>
  