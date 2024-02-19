<template>
    <th :class="class" :style="style">
        <slot>{{ header }}</slot>
    </th>
</template>


<script setup>

// Define the props for the Column component
const props = defineProps({
    header: {
        type: null,
        default: null
    },
    style: {
        type: null,
        default: null
    },
    class: {
        type: String,
        default: null
    },
    hidden: {
        type: Boolean,
        default: false
    }
});
</script>
