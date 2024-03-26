<template>
    <Menu :menus="menuOptions" :domain-select-permission="domainSelectPermission" :selected-domain="selectedDomain"
        :selected-domain-uuid="selectedDomainUuid" :domains="domains">
    </Menu>

    <slot></slot>
</template>
  
<script setup>
import Menu from '../Pages/components/Menu.vue'
import { defineProps } from 'vue'

const props = defineProps({
    menuOptions: Array,
    domainSelectPermission: Boolean,
    selectedDomain: String,
    selectedDomainUuid: String,
    domains: Array
})
</script>
  