<template>
    <td :class="class" :style="style">{{ text }}</td>
  </template>
  
  <script setup>
  import { defineProps } from 'vue';
  
  const props = defineProps({
    text: {
      type: String,
      default: ''
    },
    style: {
      type: null,
      default: null
    },
    class: {
      type: String,
      default: ''
    }
  });
  </script>
  