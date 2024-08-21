<template>
    <SwitchGroup as="div" :class="['flex items-center justify-between gap-2', customClass]">
        <span class="flex flex-grow flex-col">
            <SwitchLabel as="span" class="text-sm font-medium leading-6 text-gray-900" passive>
                {{ label }}</SwitchLabel>
            <SwitchDescription as="span" class="text-sm text-gray-500">
                {{ description }}
            </SwitchDescription>
        </span>
        <Switch v-model="status"
            :class="[status ? 'bg-indigo-600' : 'bg-gray-200', 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2']">
            <span aria-hidden="true"
                :class="[status ? 'translate-x-5' : 'translate-x-0', 'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out']" />
        </Switch>
    </SwitchGroup>
</template>

<script setup>
import { defineEmits, defineProps, onMounted, ref, watch } from 'vue'
import { Switch, SwitchLabel, SwitchGroup, SwitchDescription } from '@headlessui/vue'

const props = defineProps({
    enabled: Boolean,
    label: String,
    description: {
        type: String,
        default: ''
    },
    customClass: {
        type: String,
        default: ''
    }
});

const status = ref(false)

const emit = defineEmits(["update:status"]);

watch(
    () => props.enabled,
    (newVal) => {
        status.value = newVal;
    },
    { immediate: true }
);

watch(status, (newValue) => {
    emit('update:status', newValue);
});

</script>
