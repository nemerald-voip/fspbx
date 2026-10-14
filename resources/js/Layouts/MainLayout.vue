<template>
    <Menu :message-unread-count="messageUnreadCount" />

    <slot></slot>
</template>

<script setup>
import Menu from '../Pages/components/Menu.vue'
defineProps({ messageUnreadCount: { type: Number, default: 0 } });
</script>
