<template>
    <div class="rounded-lg bg-surface p-4 ring-1 ring-strong">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium uppercase tracking-wide text-muted">{{ label }}</span>
            <span v-if="accent" class="inline-flex h-2 w-2 rounded-full" :class="dotClass"></span>
        </div>
        <div class="mt-2 text-2xl font-semibold text-heading">{{ value }}</div>
        <div v-if="sub" class="mt-1 text-xs text-muted">{{ sub }}</div>
    </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: 0 },
    sub: { type: String, default: null },
    accent: { type: String, default: null },
});

const dotClass = computed(() => {
    if (!props.accent) return "bg-surface-3";
    const match = props.accent.match(/bg-([a-z]+)-(\d+)/);
    if (!match) return "bg-surface-3";
    return `bg-${match[1]}-500`;
});
</script>
