<template>
    <div class="px-4 py-3">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-medium uppercase tracking-wide text-muted">{{ label }}</span>
            <slot name="suffix">
                <span v-if="dotClass" class="inline-block h-2 w-2 rounded-full" :class="dotClass"></span>
            </slot>
        </div>
        <div class="mt-1 text-xl font-semibold text-heading">{{ value }}</div>
        <div v-if="sub" class="text-xs text-muted">{{ sub }}</div>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: 0 },
    sub: { type: String, default: null },
    dotClass: { type: String, default: null },
});
</script>
