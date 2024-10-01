<template>
    <td :class="class" :style="style">
      <!-- Default slot next to the text -->
      <slot>{{ text }}</slot>
  
      <!-- Named slot for action-buttons -->
      <slot name="action-buttons"></slot>
    </td>
  </template>
  
  <script setup>
  
  const props = defineProps({
    text: {
      type: [String, Number],
      default: ''
    },
    style: {
      type: Object,  
      default: () => ({})
    },
    class: {
      type: String,
      default: ''
    }
  });
  </script>
  