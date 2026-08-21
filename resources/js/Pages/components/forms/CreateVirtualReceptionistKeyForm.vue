<template>
    <VirtualReceptionistKeyForm
        mode="create"
        :options="options"
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
});

const emit = defineEmits(["close", "error", "success", "saved"]);
</script>
