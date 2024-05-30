<template>
    <span :class="badgeClasses">
        {{ text }}
    </span>

</template>
  
<script setup>
import { computed } from 'vue';

const props = defineProps({
    text: {
        type: String,
        default: 'Default Text'
    },
    backgroundColor: {
        type: String,
        default: 'bg-green-50'
    },
    textColor: {
        type: String,
        default: 'text-green-700'
    },
    ringColor: {
        type: String,
        default: 'ring-green-600/20'
    }
});

const badgeClasses = computed(() => {
    return `inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${props.backgroundColor} ${props.textColor} ring-1 ring-inset ${props.ringColor}`;
});
</script>
  
<style scoped>
/* Add any specific styles for your badge component here */
</style>
  