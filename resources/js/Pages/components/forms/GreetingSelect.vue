<template>
    <SelectElement :name="name" :label="label ?? $t('Greeting')" :items="items" :search="true" :native="false"
        :placeholder="placeholder ?? $t('Select greeting')" :floating="false" :strict="false" :disabled="disabled"
        :description="description" :columns="{ sm: { container: 12 }, lg: { container: 6 } }">
        <template #after>
            <p v-if="transcription" class="mt-1 max-w-prose text-xs italic text-gray-500">
                &ldquo;{{ transcription }}&rdquo;
            </p>
        </template>
    </SelectElement>

    <StaticElement :name="`${name}_action_buttons`" label="&nbsp;"
        :columns="{ sm: { container: 12 }, lg: { container: 6 } }">
        <div class="flex flex-wrap items-center gap-1.5">
            <button v-for="action in actions" :key="action.name" type="button" :disabled="disabled"
                :class="buttonClass" :title="action.label" @click="emit('action', action.name)">
                <span class="sr-only">{{ action.label }}</span>
                <component :is="action.icon" :class="action.red ? redIconClass : blueIconClass" aria-hidden="true" />
            </button>
        </div>
    </StaticElement>
</template>

<script setup>
import { computed } from 'vue';
import { trans } from '@i18n';
import { PlayCircleIcon, CloudArrowDownIcon, PauseCircleIcon } from '@heroicons/vue/24/solid';
import { PencilSquareIcon } from '@heroicons/vue/20/solid';
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    name: { type: String, required: true },
    label: { type: String, default: null },
    placeholder: { type: String, default: null },
    description: { type: String, default: '' },
    items: { type: [Array, Function], required: true },
    value: { type: [String, Number], default: null },
    transcription: { type: String, default: '' },
    playing: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['action']);
const hasSelection = computed(() => ![null, undefined, '', 'disabled', 'null', '0', '-1'].includes(props.value));
const actions = computed(() => [
    ...(hasSelection.value ? [
        { name: props.playing ? 'Pause' : 'Play', label: props.playing ? trans('Pause') : trans('Play'), icon: props.playing ? PauseCircleIcon : PlayCircleIcon, red: props.playing },
        { name: 'Download', label: trans('Download'), icon: CloudArrowDownIcon },
        { name: 'Rename', label: trans('Rename'), icon: PencilSquareIcon },
        { name: 'Delete', label: trans('Delete'), icon: TrashIcon, red: true },
    ] : []),
    { name: 'New', label: trans('New Greeting'), icon: PlusIcon },
]);
const buttonClass = 'rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';
const blueIconClass = 'h-8 w-8 shrink-0 rounded-full py-1 text-blue-400 ring-1 transition duration-500 ease-in-out hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer';
const redIconClass = 'h-8 w-8 shrink-0 rounded-full py-1 text-red-400 ring-1 transition duration-500 ease-in-out hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer';
</script>
