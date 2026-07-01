<template>
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <div class="overflow-hidden border-t border-default">
                    <table class="min-w-full divide-y divide-default mb-4">
                        <thead class="bg-surface-3">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-heading sm:px-6">
                                    MAC Address
                                </th>
                                <th
                                    class="hidden px-6 py-3 text-left text-sm font-semibold text-heading sm:table-cell">
                                    Device Template
                                </th>
                                <th
                                    class="hidden px-6 py-3 text-left text-sm font-semibold text-heading sm:table-cell">
                                    Profile / Key Template
                                </th>
                                <th class="relative px-4 py-3 text-left text-sm font-medium text-muted sm:px-6">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>

                        <tbody v-if="!loading && devices.length" class="divide-y divide-default bg-surface">
                            <tr v-for="device in devices" :key="device.device_uuid">
                                <!-- Device MAC Address -->
                                <td class="px-4 py-4 text-sm font-medium text-heading sm:px-6">
                                    {{ device.device_address_formatted }}

                                    <!-- Device Template: show in mobile view -->
                                    <div class="px-6 py-2 text-sm text-muted sm:hidden">
                                        <span v-if="device.template && device.template.name">
                                            {{ (device.template.vendor ? device.template.vendor + '/' : '') +
                                                device.template.name }}
                                        </span>
                                        <span v-else>
                                            {{ device.device_template || '—' }}
                                        </span>
                                    </div>

                                    <!-- Device Profile / Key Template: show in mobile view -->
                                    <div class="px-6 py-2 text-sm text-muted sm:hidden">
                                        <template v-if="device.profile?.device_profile_name || device.key_template?.name">
                                            <div v-if="device.profile?.device_profile_name">
                                                <span class="font-semibold">Profile:</span>
                                                <span> {{ device.profile.device_profile_name }}</span>
                                            </div>
                                            <div v-if="device.key_template?.name">
                                                <span class="font-semibold">Key Template:</span>
                                                <span> {{ device.key_template.name }}</span>
                                            </div>
                                        </template>
                                        <span v-else>—</span>
                                    </div>
                                </td>

                                <!-- Device Template: show in desktop view -->
                                <td class="hidden px-6 py-2 text-sm text-muted sm:table-cell">
                                    <span v-if="device.template && device.template.name">
                                        {{ (device.template.vendor ? device.template.vendor + '/' : '') +
                                        device.template.name }}
                                    </span>
                                    <span v-else>
                                        {{ device.device_template || '—' }}
                                    </span>
                                </td>
                                <!-- Device Profile / Key Template: show in desktop view -->
                                <td class="hidden px-6 py-2 text-sm text-muted sm:table-cell">
                                    <template v-if="device.profile?.device_profile_name || device.key_template?.name">
                                        <div v-if="device.profile?.device_profile_name">
                                            <span class="font-semibold">Profile:</span>
                                            <span> {{ device.profile.device_profile_name }}</span>
                                        </div>
                                        <div v-if="device.key_template?.name">
                                            <span class="font-semibold">Key Template:</span>
                                            <span> {{ device.key_template.name }}</span>
                                        </div>
                                    </template>
                                    <span v-else>—</span>
                                </td>

                                <!-- Actions -->
                                <td class="whitespace-nowrap px-4 py-2 text-right text-sm font-medium sm:px-6">
                                    <div class="flex items-center justify-end space-x-2">
                                        <ejs-tooltip v-if="permissions.extension_device_update" :content="'Edit'"
                                            position='TopCenter' target="#destination_tooltip_target">
                                            <div id="destination_tooltip_target">
                                                <PencilSquareIcon @click="handleEditButtonClick(device.device_uuid)"
                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer" />

                                            </div>
                                        </ejs-tooltip>

                                        <ejs-tooltip v-if="permissions.extension_device_unassign" :content="'Unassign'"
                                            position='TopCenter'>
                                            <TrashIcon @click="handleSingleItemDeleteRequest(device.device_uuid)"
                                                class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-danger hover:bg-danger-subtle hover:text-danger active:bg-danger-subtle active:duration-150 cursor-pointer" />
                                        </ejs-tooltip>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="!loading && devices.length === 0" class="text-center my-5">
                        <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-subtle" />
                        <h3 class="mt-2 text-sm font-semibold text-heading">No results found</h3>
                        <!-- <p class="mt-1 text-sm text-muted">
                Adjust your search and try again.
              </p> -->
                    </div>

                    <!-- Loading -->
                    <div v-if="loading" class="text-center my-5 text-sm text-muted">
                        <div class="animate-pulse flex space-x-4">
                            <div class="flex-1 space-y-6 py-1">
                                <div class="h-2 bg-surface-3 rounded"></div>
                                <div class="h-2 bg-surface-3 rounded"></div>
                                <div class="h-2 bg-surface-3 rounded"></div>
                                <div class="h-2 bg-surface-3 rounded"></div>
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
