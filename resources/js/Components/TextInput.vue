<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="border-strong dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-accent dark:focus:border-accent focus:ring-accent dark:focus:ring-accent rounded-md shadow-sm"
        v-model="model"
        ref="input"
    />
</template>