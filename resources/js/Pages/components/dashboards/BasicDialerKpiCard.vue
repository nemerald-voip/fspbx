<template>
    <div class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ label }}</span>
            <span v-if="accent" class="inline-flex h-2 w-2 rounded-full" :class="dotClass"></span>
        </div>
        <div class="mt-2 text-2xl font-semibold text-gray-900">{{ value }}</div>
        <div v-if="sub" class="mt-1 text-xs text-gray-500">{{ sub }}</div>
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
    if (!props.accent) return "bg-gray-300";
    const match = props.accent.match(/bg-([a-z]+)-(\d+)/);
    if (!match) return "bg-gray-300";
    return `bg-${match[1]}-500`;
});
</script>
