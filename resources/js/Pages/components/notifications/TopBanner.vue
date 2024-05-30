<template>
    <div v-if="show" class="grid gap-4 grid-cols-1 grid-rows-1 md:grid-cols-6 xl:grid-cols-10">
        <div
            :class="['pointer-events-auto flex items-center gap-x-6 m-2 px-6 py-2.5 md:col-start-2 md:col-span-4 xl:col-start-4 xl:col-span-4 sm:rounded-xl sm:py-3 sm:pl-4 sm:pr-3.5', color]">
            <p class="text-sm leading-6 text-white">
                {{ text }}
            </p>
            <div class="flex flex-1 justify-end">
                <button type="button" class="-m-3 p-3 focus-visible:outline-offset-[-4px]" @click="handleClose">
                    <span class="sr-only">Dismiss</span>
                    <XMarkIcon class="h-5 w-5 text-white" aria-hidden="true" />
                </button>
            </div>
        </div>
    </div>
</template>
  
<script setup>
import { defineProps, defineEmits } from 'vue';
import { XMarkIcon } from '@heroicons/vue/24/solid'; 

const props = defineProps({
    show: {
        type: Boolean,
        required: true,
    },
    color: {
        type: String,
        default: 'bg-green-600', // Default background color
    },
    text: {
        type: String,
        default: 'Label',
    },
});

const emit = defineEmits(['close']);

const handleClose = () => {
    emit('close');
};
</script>
  
<style scoped>
/* Add any scoped styles here if necessary */
</style>
  