<template>
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <div class="overflow-hidden border-t border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 mb-4">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">API Tokens</th>
                                <th class="hidden px-6 py-3 text-left text-sm font-semibold text-gray-900 sm:table-cell">
                                    Date(s)
                                </th>
                                <th class="hidden px-6 py-3 text-left text-sm font-semibold text-gray-900 sm:table-cell">
                                    Route To</th>
                                <th class="relative px-6 py-3 text-left text-sm font-medium text-gray-500">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody v-if="!loading && tokens.length" class="divide-y divide-gray-200 bg-white">
                            <tr v-for="token in tokens" :key="token.uuid">
                                <td class=" px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ token.description }}

                                    <div class="px-6 py-2 text-sm text-gray-500 sm:hidden">
                                        {{ token.human_date }}
                                    </div>

                                    <div class="px-6 py-2 text-sm text-gray-500 sm:hidden">
                                        {{ token.target_label }}
                                    </div>
                                </td>
                                <!-- <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ call.members.length }}
                                </td> -->

                                <td class="hidden px-6 py-2 text-sm text-gray-500 sm:table-cell">
                                    {{ token.human_date }}
                                </td>
                                <td class="hidden px-6 py-2 text-sm text-gray-500 sm:table-cell">
                                    {{ token.target_label }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-2 text-right text-sm font-medium">
                                    <div class="flex items-center whitespace-nowrap justify-end">
                                        <ejs-tooltip v-if="permissions.tokens_update" :content="'Edit'" position='TopCenter'
                                            target="#destination_tooltip_target">
                                            <div id="destination_tooltip_target">
                                                <PencilSquareIcon @click="handleEditButtonClick(token.uuid)"
                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                            </div>
                                        </ejs-tooltip>

                                        <ejs-tooltip v-if="permissions.tokens_delete" :content="'Delete'" position='TopCenter'
                                            target="#delete_tooltip_target">
                                            <div id="delete_tooltip_target">
                                                <TrashIcon @click="handleSingleItemDeleteRequest(token.uuid)"
                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                            </div>
                                        </ejs-tooltip>
                                    </div>

                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="!loading && tokens.length === 0" class="text-center my-5">
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
    tokens: Object,
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
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";</style>