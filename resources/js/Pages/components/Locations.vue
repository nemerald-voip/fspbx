<template>
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <div class="overflow-hidden border-t border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 mb-4">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Name</th>
                                <th class="hidden px-6 py-3 text-left text-sm font-semibold text-gray-900 sm:table-cell">
                                    Description
                                </th>
                                <th class="relative px-6 py-3 text-left text-sm font-medium text-gray-500">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody v-if="!loading && locations.length" class="divide-y divide-gray-200 bg-white">
                            <tr v-for="location in locations" :key="location.location_uuid">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ location.name }}

                                    <!-- Created at: show in mobile view -->
                                    <div class="px-6 py-2 text-sm text-gray-500 sm:hidden">
                                        {{ location.created_at }}
                                    </div>

                                   
                                </td>

                                <!-- Created at: show in desktop view -->
                                <td class="hidden px-6 py-2 text-sm text-gray-500 sm:table-cell">
                                    {{ location.created_at }}
                                </td>
                                

                                <!-- Actions -->
                                <td class="whitespace-nowrap px-6 py-2 text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <!-- Edit (if needed) -->
                                        <!-- <ejs-tooltip v-if="permissions.api_key_update" :content="'Edit'"
                                            position='TopCenter'>
                                            <PencilSquareIcon @click="handleEditButtonClick(token.id)"
                                                class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                        </ejs-tooltip> -->

                                        <!-- Revoke/Delete -->
                                        <ejs-tooltip v-if="permissions.api_key_delete" :content="'Revoke'"
                                            position='TopCenter'>
                                            <TrashIcon @click="handleSingleItemDeleteRequest(token.id)"
                                                class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />
                                        </ejs-tooltip>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                    </table>

                    <!-- Empty State -->
                    <div v-if="!loading && locations.length === 0" class="text-center my-5">
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
    locations: Object,
    permissions: Object,
    loading: Boolean,
})

const emits = defineEmits(['edit-item', 'delete-item']);

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