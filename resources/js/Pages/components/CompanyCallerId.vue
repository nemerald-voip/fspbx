<template>
    <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm">
        <ExclamationTriangleIcon v-if="state.warning" class="h-4 w-4 flex-none text-amber-500" aria-hidden="true" />
        <span :class="state.warning ? 'text-amber-700' : 'text-gray-600'">{{ state.text }}</span>
        <span v-if="state.name" class="text-gray-500">· {{ state.name }}</span>
        <span v-if="state.managedElsewhere" class="text-gray-500">· {{ $t('Managed in Dialplan Manager') }}</span>
        <button v-if="state.action" type="button" @click="emit('edit')"
            class="rounded font-semibold text-indigo-600 hover:text-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
            {{ state.action }}
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { ExclamationTriangleIcon } from '@heroicons/vue/20/solid'
import { trans } from '@i18n'

const props = defineProps({
    defaults: { type: Object, required: true },
    type: { type: String, required: true },
    extensionName: String,
    phoneNumbers: { type: Array, default: () => [] },
})
const emit = defineEmits(['edit'])

const state = computed(() => {
    const { status, enabled, values, can_manage: canManage } = props.defaults
    const shared = status === 'shared'
    const number = values[`${props.type}_caller_id_number`]

    if (status === 'custom') {
        return { text: trans('Company caller ID uses custom rules.'), managedElsewhere: true }
    }
    if (status === 'disabled' || (shared && !enabled)) {
        return {
            warning: true,
            text: trans('Company caller ID is turned off.'),
            managedElsewhere: shared,
            action: canManage ? trans('Turn on') : null,
        }
    }
    if (!number) {
        return {
            warning: true,
            text: props.type === 'emergency' ? trans('No company emergency number set yet.') : trans('No company number set yet.'),
            managedElsewhere: shared,
            action: canManage ? trans('Set up') : null,
        }
    }

    return {
        text: trans('Uses :number', { number: props.phoneNumbers.find(item => item.value === number)?.label ?? number }),
        // The company name only applies while this extension has no name of its own.
        name: props.extensionName ? null : values[`${props.type}_caller_id_name`],
        managedElsewhere: shared,
        action: canManage ? trans('Edit') : null,
    }
})
</script>
