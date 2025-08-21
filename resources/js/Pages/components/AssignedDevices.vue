<template>
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <div class="overflow-hidden border-t border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 mb-4">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 sm:px-6">
                                    MAC Address
                                </th>
                                <th
                                    class="hidden px-6 py-3 text-left text-sm font-semibold text-gray-900 sm:table-cell">
                                    Device Template
                                </th>
                                <th
                                    class="hidden px-6 py-3 text-left text-sm font-semibold text-gray-900 sm:table-cell">
                                    Profile Name
                                </th>
                                <th class="relative px-4 py-3 text-left text-sm font-medium text-gray-500 sm:px-6">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>

                        <tbody v-if="!loading && devices.length" class="divide-y divide-gray-200 bg-white">
                            <tr v-for="device in devices" :key="device.device_uuid">
                                <!-- Device MAC Address -->
                                <td class="px-4 py-4 text-sm font-medium text-gray-900 sm:px-6">
                                    {{ device.device_address_formatted }}

                                    <!-- Device Template: show in mobile view -->
                                    <div class="px-6 py-2 text-sm text-gray-500 sm:hidden">
                                        <span v-if="device.template && device.template.name">
                                            {{ (device.template.vendor ? device.template.vendor + '/' : '') +
                                                device.template.name }}
                                        </span>
                                        <span v-else>
                                            {{ device.device_template || '—' }}
                                        </span>
                                    </div>

                                    <!-- Device Profile Name: show in mobile view -->
                                    <div class="px-6 py-2 text-sm text-gray-500 sm:hidden">
                                        <span v-if="device.profile && device.profile.device_profile_name">
                                            {{ device.profile.device_profile_name }}
                                        </span>

                                    </div>
                                </td>

                                <!-- Device Template: show in desktop view -->
                                <td class="hidden px-6 py-2 text-sm text-gray-500 sm:table-cell">
                                    <span v-if="device.template && device.template.name">
                                        {{ (device.template.vendor ? device.template.vendor + '/' : '') +
                                        device.template.name }}
                                    </span>
                                    <span v-else>
                                        {{ device.device_template || '—' }}
                                    </span>
                                </td>
                                <!-- Device Profile Name: show in desktop view -->
                                <td class="hidden px-6 py-2 text-sm text-gray-500 sm:table-cell">
                                    <span v-if="device.profile && device.profile.device_profile_name">
                                        {{ device.profile.device_profile_name }}
                                    </span>

                                </td>

                                <!-- Actions -->
                                <td class="whitespace-nowrap px-4 py-2 text-right text-sm font-medium sm:px-6">
                                    <div class="flex items-center justify-end space-x-2">
                                        <ejs-tooltip v-if="permissions.extension_device_update" :content="'Edit'"
                                            position='TopCenter' target="#destination_tooltip_target">
                                            <div id="destination_tooltip_target">
                                                <PencilSquareIcon @click="handleEditButtonClick(device.device_uuid)"
                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                            </div>
                                        </ejs-tooltip>

                                        <ejs-tooltip v-if="permissions.extension_device_unassign" :content="'Unassign'"
                                            position='TopCenter'>
                                            <TrashIcon @click="handleSingleItemDeleteRequest(device.device_uuid)"
                                                class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />
                                        </ejs-tooltip>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="!loading && devices.length === 0" class="text-center my-5">
                        <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                        <!-- <p class="mt-1 text-sm text-gray-500">
                Adjust your search and try again.
              </p> -->
                    </div>

                    <!-- Loading -->
                    <div v-if="loading" class="text-center my-5 text-sm text-gray-500">
                        <div class="animate-pulse flex space-x-4">
                            <div class="flex-1 space-y-6 py-1">
                                <div class="h-2 bg-slate-200 rounded"></div>
                                <div class="h-2 bg-slate-200 rounded"></div>
                                <div class="h-2 bg-slate-200 rounded"></div>
                                <div class="h-2 bg-slate-200 rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import { registerLicense } from '@syncfusion/ej2-base';
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";

const props = defineProps({
    devices: Object,
    permissions: Object,
    loading: Boolean,
})

const emits = defineEmits(['edit-item', 'delete-item', 'edit-item']);

const handleEditButtonClick = (uuid) => {
    emits('edit-item', uuid)
}

const handleSingleItemDeleteRequest = (uuid) => {
    emits('delete-item', uuid);
};


registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');


</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>