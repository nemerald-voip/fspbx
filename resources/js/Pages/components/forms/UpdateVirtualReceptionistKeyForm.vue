<template>
    <VirtualReceptionistKeyForm
        mode="update"
        :options="options"
        :selected-key="selectedKey"
        @close="emit('close')"
        @error="emit('error', $event)"
        @success="emit('success', $event)"
        @saved="emit('saved')"
    />
</template>

<script setup>
import VirtualReceptionistKeyForm from "./VirtualReceptionistKeyForm.vue";

defineProps({
    options: {
        type: Object,
        required: true,
    },
    selectedKey: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["close", "error", "success", "saved"]);
</script>
