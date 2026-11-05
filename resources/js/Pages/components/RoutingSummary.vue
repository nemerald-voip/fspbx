<template>
    <div>
        <div class="flex items-center gap-2">
            <Badge v-if="!actions?.length" :text="$t('Not routed')"
                backgroundColor="bg-amber-50" textColor="text-amber-700"
                ringColor="ring-amber-600/20" class="px-2 py-1 text-xs" />
            <Badge v-else-if="first.invalid" :text="$t('Invalid destination')"
                backgroundColor="bg-rose-50" textColor="text-rose-700"
                ringColor="ring-rose-600/20" class="px-2 py-1 text-xs" />
            <span v-else class="text-gray-900" :class="{ 'hover:text-indigo-600': editable }">
                {{ first.name || first.type }}
            </span>
            <span v-if="actions?.length > 1"
                class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600" :title="summaryTitle">
                {{ $t('+:count more', { count: actions.length - 1 }) }}
            </span>
        </div>
        <div v-if="details" class="text-xs text-gray-500">{{ details }}</div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import Badge from '@generalComponents/Badge.vue';

const props = defineProps({
    actions: Array,
    extras: Array,
    editable: Boolean,
    invalidHint: String,
});

const first = computed(() => props.actions?.[0]);
const details = computed(() => {
    const detail = first.value?.invalid ? props.invalidHint : (first.value?.name ? first.value.type : null);
    return [detail, ...(props.extras ?? [])].filter(Boolean).join(' · ');
});
const summaryTitle = computed(() => (props.actions ?? []).map((action) => {
    if (action.invalid) return trans('Invalid destination');
    return action.name && action.type ? `${action.name} (${action.type})` : (action.name || action.type);
}).join('\n'));
</script>
