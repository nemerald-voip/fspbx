<template>
    <div v-if="show" :class="{'absolute bg-gray-400 bg-opacity-30': absolute}" class="w-full h-full">
        <div :class="{'mt-20': absolute}" class="flex justify-center items-center space-x-3">
            <div>
                <svg class="animate-spin  h-10 w-10 text-blue-600" xmlns="http://www.w3.org/2000/svg"
                     fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4">
                    </circle>
                    <path class="opacity-75" fill="currentColor"
                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
            </div>
            <div class="text-lg text-blue-600 m-auto">Loading...</div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    absolute: {
        type: Boolean,
        default: true,
    },
});
</script>
