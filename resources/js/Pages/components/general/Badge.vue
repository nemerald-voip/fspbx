<template>
    <span :class="badgeClasses">
        {{ text }}
    </span>

</template>
  
<script setup>
import { computed } from 'vue';

const props = defineProps({
    text: {
        type: [String, Number],
        default: 'Default Text'
    },
    backgroundColor: {
        type: String,
        default: 'bg-success-subtle'
    },
    textColor: {
        type: String,
        default: 'text-success'
    },
    ringColor: {
        type: String,
        default: 'ring-success/20'
    }
});

const badgeClasses = computed(() => {
    return `inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${props.backgroundColor} ${props.textColor} ring-1 ring-inset ${props.ringColor}`;
});
</script>
  
<style scoped>
/* Add any specific styles for your badge component here */
</style>
  