<template>
    <form>
        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <div class="sm:col-span-12">
                <label for="first-name" class="block text-sm font-medium leading-6 text-gray-900">MacAddress</label>
                <div class="mt-2">
                    <input type="text" name="device_address" id="device_address" placeholder="Enter the MAC address"
                           :value="macaddress"
                           class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"/>
                </div>
            </div>

            <div class="sm:col-span-12">
                <label for="template" class="block text-sm font-medium leading-6 text-gray-900">Template</label>
                <div class="mt-2">
                    <SelectBox :options="templates"
                               :selectedItem="selectedTemplate"
                               :search="true"
                               :placeholder="'Choose template'"/>
                </div>
            </div>

            <div class="sm:col-span-12">
                <label for="email" class="block text-sm font-medium leading-6 text-gray-900">Profile</label>
                <div class="mt-2">
                    <SelectBox :options="profiles"
                               :selectedItem="selectedProfile"
                               :search="true"
                               :placeholder="'Choose profile'"/>
                </div>
            </div>

            <div class="sm:col-span-12">
                <label for="country"
                       class="block text-sm font-medium leading-6 text-gray-900">Extension</label>
                <div class="mt-2">
                    <SelectBox :options="extensions"
                               :selectedItem="selectedExtension"
                               :search="true"
                               :placeholder="'Choose extension'"/>
                </div>
            </div>
        </div>
    </form>
</template>

<script setup>
import {defineProps} from 'vue'
import SelectBox from "../general/SelectBox.vue";

const props = defineProps({
    templates: Array,
    profiles: Array,
    extensions: Array,
    macaddress: String,
    selectedTemplate: String,
    selectedProfile: String,
    selectedExtension: String
});
</script>
