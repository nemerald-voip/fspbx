<template>
    <label :for="target">
        <Switch v-model="status"
                :class="[status ? 'bg-indigo-600' : 'bg-gray-200', 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out']">
            <span class="sr-only">Use setting</span>
            <span aria-hidden="true"
                  :class="[status ? 'translate-x-5' : 'translate-x-0', 'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out']"/>
        </Switch>
        {{ label }}
    </label>
</template>

<script setup>
import {defineEmits, defineProps, ref, watch} from 'vue'
import {Switch} from '@headlessui/vue'

const props = defineProps({
    enabled: Boolean,
    target: String,
    label: String
});

const status = ref(false)

const emit = defineEmits(["update:status"]);

watch(status, (newValue) => {
    emit('update:status', newValue);
});

</script>
