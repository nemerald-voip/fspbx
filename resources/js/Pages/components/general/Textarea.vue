<template>
    <textarea
        v-bind="{ id: id }"
        :name="name"
        :rows="rows"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        :placeholder="placeholder"
        :class="textareaClass">
    </textarea>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    id: {
        type: String,
        default: null,
    },
    name: String,
    rows: String,
    modelValue: String,
    placeholder: {
        type: String,
        default: null,
    },
    error: Boolean,  

});

const emit = defineEmits(['update:modelValue']);

// Compute the classes based on the error state
const textareaClass = computed(() => {
    let baseClasses = 'block w-full rounded-md border-0 bg-surface py-1.5 shadow-sm ring-1 ring-inset placeholder:text-subtle focus:ring-2 focus:ring-inset sm:text-sm sm:leading-6';
    if (props.error) {
        return `${baseClasses} text-danger ring-danger`; // Apply red text and ring if there's an error
    }
    return `${baseClasses} text-heading ring-strong`; // Default text and ring color when no error
});
</script>
