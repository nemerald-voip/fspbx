<template>
    <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
    @response="handleResponse" :display-errors="false">

    </Vueform>
  </template>
  

<script setup>
import { ref } from 'vue'

const form$ = ref(null)
</script>
