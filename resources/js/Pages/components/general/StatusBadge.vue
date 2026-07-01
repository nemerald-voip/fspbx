<template>
    <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium"
          :class="enabled ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">{{ enabled ? enabledLabel : disabledLabel }}</span>
</template>

<script setup>
import {defineProps} from 'vue'

const props = defineProps({
    enabled: [Boolean, String],
    enabledLabel: {
        type: String,
        default: 'Enabled'
    },
    disabledLabel: {
        type: String,
        default: 'Disabled'
    }
});

</script>
