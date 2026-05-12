<template>
    <div class="overflow-hidden rounded-lg bg-white ring-1 ring-gray-200">
        <div class="border-b border-gray-100 px-5 py-4">
            <h3 class="text-base font-semibold leading-6 text-gray-950">
                Global Info
                <span class="ml-1 text-sm font-normal italic text-gray-500">(Superadmin only)</span>
            </h3>
            <p v-if="data.version" class="mt-1 text-sm text-gray-500">Version: {{ data.version }}</p>
        </div>

        <dl class="grid grid-cols-1 gap-px bg-gray-100 sm:grid-cols-2 xl:grid-cols-4">
            <div class="bg-white p-5">
                <dt class="truncate text-sm font-medium text-gray-500">Total Domains</dt>
                <dd class="mt-2 text-3xl font-semibold tracking-tight text-indigo-600">{{ data.domain_count }}</dd>
            </div>

            <div class="bg-white p-5">
                <dt class="truncate text-sm font-medium text-gray-500">Total extensions</dt>
                <dd class="mt-1 flex flex-wrap items-baseline justify-between gap-2">
                    <div class="flex items-baseline font-semibold text-gray-500">
                        {{ counts.global_reg_count }}
                        <span class="ml-2 text-sm font-medium text-gray-500">online</span>
                    </div>
                    <div class="inline-flex items-baseline whitespace-nowrap rounded-full bg-sky-100 px-2.5 py-0.5 text-sm font-medium text-sky-800">
                        Total {{ data.extension_count }}
                    </div>
                </dd>
                <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-gray-200">
                    <div class="h-full rounded-full bg-green-500" :style="{ width: extensionPercent + '%' }"></div>
                </div>
            </div>

            <div class="space-y-3 bg-white p-5">
                <div>
                    <dd class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="text-sm font-medium text-gray-500">
                            Disk: <span class="whitespace-nowrap">{{ round(data.diskused) }}/{{ round(data.disktotal) }} GB</span>
                        </span>
                        <span :class="[usageBadge(data.diskusagecolor), 'inline-flex items-baseline whitespace-nowrap rounded-full px-2.5 py-0.5 text-sm font-medium']">
                            {{ Math.round(data.diskusage) }}%
                        </span>
                    </dd>
                    <div class="mt-1 h-2.5 w-full overflow-hidden rounded-full bg-gray-200">
                        <div :class="usageBar(data.diskusagecolor)" class="h-full rounded-full" :style="{ width: data.diskusage + '%' }"></div>
                    </div>
                </div>

                <div>
                    <dd class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="text-sm font-medium text-gray-500">
                            Memory: <span class="whitespace-nowrap">{{ round(data.ramused) }}/{{ round(data.ramtotal) }} GB</span>
                        </span>
                        <span :class="[usageBadge(data.ramusagecolor), 'inline-flex items-baseline whitespace-nowrap rounded-full px-2.5 py-0.5 text-sm font-medium']">
                            {{ Math.round(data.ramusage) }}%
                        </span>
                    </dd>
                    <div class="mt-1 h-2.5 w-full overflow-hidden rounded-full bg-gray-200">
                        <div :class="usageBar(data.ramusagecolor)" class="h-full rounded-full" :style="{ width: data.ramusage + '%' }"></div>
                    </div>
                </div>
            </div>

            <div class="space-y-2 bg-white p-5">
                <dd class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="text-sm font-medium text-gray-500">Hostname</span>
                    <span class="max-w-full truncate rounded-full bg-slate-200 px-2.5 py-0.5 text-sm font-medium text-slate-700">{{ data.hostname }}</span>
                </dd>
                <dd class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="text-sm font-medium text-gray-500">Uptime</span>
                    <span class="max-w-full truncate rounded-full bg-slate-200 px-2.5 py-0.5 text-sm font-medium text-slate-700">{{ data.uptime }}</span>
                </dd>
                <dd class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="text-sm font-medium text-gray-500">CPU cores</span>
                    <span class="whitespace-nowrap rounded-full bg-sky-100 px-2.5 py-0.5 text-sm font-medium text-sky-700">{{ data.core_count }}</span>
                </dd>
                <dd class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="text-sm font-medium text-gray-500">Horizon Status</span>
                    <span :class="[horizonBadge(data.horizonStatus), 'whitespace-nowrap rounded-full px-2.5 py-0.5 text-sm font-medium']">
                        {{ data.horizonStatus }}
                    </span>
                </dd>
            </div>
        </dl>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    data: { type: Object, default: () => ({}) },
    counts: { type: Object, default: () => ({}) },
});

const round = (value) => Math.round((Number(value) || 0) * 10) / 10;

const extensionPercent = computed(() => {
    const total = Number(props.data.extension_count) || 0;
    const online = Number(props.counts.global_reg_count) || 0;
    if (!total) return 0;
    return Math.min(Math.round((online / total) * 100), 100);
});

const usageBadge = (color) => {
    if (color === 'bg-success') return 'bg-green-100 text-green-600';
    if (color === 'bg-warning') return 'bg-yellow-100 text-yellow-600';
    if (color === 'bg-danger') return 'bg-rose-100 text-rose-600';
    return 'bg-sky-100 text-sky-800';
};

const usageBar = (color) => {
    if (color === 'bg-success') return 'bg-green-500';
    if (color === 'bg-warning') return 'bg-yellow-500';
    if (color === 'bg-danger') return 'bg-rose-500';
    return 'bg-sky-500';
};

const horizonBadge = (status) => {
    if (status === 'running') return 'bg-green-100 text-green-600';
    if (status === 'paused') return 'bg-yellow-100 text-yellow-600';
    return 'bg-gray-100 text-gray-600';
};
</script>
