<template>
    <div class="overflow-hidden rounded-lg bg-surface ring-1 ring-strong">
        <div class="border-b border-default px-5 py-4">
            <h3 class="text-base font-semibold leading-6 text-heading">
                Global Info
                <span class="ml-1 text-sm font-normal italic text-muted">(Superadmin only)</span>
            </h3>
            <p v-if="data.version" class="mt-1 text-sm text-muted">Version: {{ data.version }}</p>
        </div>

        <dl class="grid grid-cols-1 gap-px bg-surface-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="bg-surface p-5">
                <dt class="truncate text-sm font-medium text-muted">Total Domains</dt>
                <dd class="mt-2 text-3xl font-semibold tracking-tight text-accent-fg">{{ data.domain_count }}</dd>
            </div>

            <div class="bg-surface p-5">
                <dt class="truncate text-sm font-medium text-muted">Total extensions</dt>
                <dd class="mt-1 flex flex-wrap items-baseline justify-between gap-2">
                    <div class="flex items-baseline font-semibold text-muted">
                        {{ counts.global_reg_count }}
                        <span class="ml-2 text-sm font-medium text-muted">online</span>
                    </div>
                    <div class="inline-flex items-baseline whitespace-nowrap rounded-full bg-info-subtle px-2.5 py-0.5 text-sm font-medium text-info">
                        Total {{ data.extension_count }}
                    </div>
                </dd>
                <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-surface-3">
                    <div class="h-full rounded-full bg-success" :style="{ width: extensionPercent + '%' }"></div>
                </div>
            </div>

            <div class="space-y-3 bg-surface p-5">
                <div>
                    <dd class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="text-sm font-medium text-muted">
                            Disk: <span class="whitespace-nowrap">{{ round(data.diskused) }}/{{ round(data.disktotal) }} GB</span>
                        </span>
                        <span :class="[usageBadge(data.diskusagecolor), 'inline-flex items-baseline whitespace-nowrap rounded-full px-2.5 py-0.5 text-sm font-medium']">
                            {{ Math.round(data.diskusage) }}%
                        </span>
                    </dd>
                    <div class="mt-1 h-2.5 w-full overflow-hidden rounded-full bg-surface-3">
                        <div :class="usageBar(data.diskusagecolor)" class="h-full rounded-full" :style="{ width: data.diskusage + '%' }"></div>
                    </div>
                </div>

                <div>
                    <dd class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="text-sm font-medium text-muted">
                            Memory: <span class="whitespace-nowrap">{{ round(data.ramused) }}/{{ round(data.ramtotal) }} GB</span>
                        </span>
                        <span :class="[usageBadge(data.ramusagecolor), 'inline-flex items-baseline whitespace-nowrap rounded-full px-2.5 py-0.5 text-sm font-medium']">
                            {{ Math.round(data.ramusage) }}%
                        </span>
                    </dd>
                    <div class="mt-1 h-2.5 w-full overflow-hidden rounded-full bg-surface-3">
                        <div :class="usageBar(data.ramusagecolor)" class="h-full rounded-full" :style="{ width: data.ramusage + '%' }"></div>
                    </div>
                </div>
            </div>

            <div class="space-y-2 bg-surface p-5">
                <dd class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="text-sm font-medium text-muted">Hostname</span>
                    <span class="max-w-full truncate rounded-full bg-surface-3 px-2.5 py-0.5 text-sm font-medium text-body">{{ data.hostname }}</span>
                </dd>
                <dd class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="text-sm font-medium text-muted">Uptime</span>
                    <span class="max-w-full truncate rounded-full bg-surface-3 px-2.5 py-0.5 text-sm font-medium text-body">{{ data.uptime }}</span>
                </dd>
                <dd class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="text-sm font-medium text-muted">CPU cores</span>
                    <span class="whitespace-nowrap rounded-full bg-info-subtle px-2.5 py-0.5 text-sm font-medium text-info">{{ data.core_count }}</span>
                </dd>
                <dd class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="text-sm font-medium text-muted">Horizon Status</span>
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
    if (color === 'bg-success') return 'bg-success-subtle text-success';
    if (color === 'bg-warning') return 'bg-warning-subtle text-warning';
    if (color === 'bg-danger') return 'bg-danger-subtle text-danger';
    return 'bg-info-subtle text-info';
};

const usageBar = (color) => {
    if (color === 'bg-success') return 'bg-success';
    if (color === 'bg-warning') return 'bg-warning';
    if (color === 'bg-danger') return 'bg-danger';
    return 'bg-info';
};

const horizonBadge = (status) => {
    if (status === 'running') return 'bg-success-subtle text-success';
    if (status === 'paused') return 'bg-warning-subtle text-warning';
    return 'bg-surface-3 text-body';
};
</script>
