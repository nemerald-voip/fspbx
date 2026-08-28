<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ label }}</div>
        <div class="mt-2 flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full" :class="color"></span><span class="text-sm font-medium capitalize text-gray-900">{{ String(status).replaceAll('_', ' ') }}</span></div>
        <div class="mt-1 truncate text-xs text-gray-500" :title="detail">{{ detail || '—' }}</div>
    </div>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({ label: String, status: String, detail: String });
const color = computed(() => ['connected','completed','scheduled'].includes(props.status) ? 'bg-green-500' : ['failed'].includes(props.status) ? 'bg-red-500' : 'bg-gray-400');
</script>
