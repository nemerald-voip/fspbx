<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-danger dark:text-danger">
            {{ message }}
        </p>
    </div>
</template>