<template>
    <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium"
          :class="enabled ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">{{ enabled ? enabledLabel : disabledLabel }}</span>
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
