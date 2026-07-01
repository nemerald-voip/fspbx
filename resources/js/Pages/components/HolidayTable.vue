<template>
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <div class="overflow-hidden border-t border-default">
                    <table class="min-w-full divide-y divide-default mb-4">
                        <thead class="bg-surface-3">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Holiday</th>
                                <th class="hidden px-6 py-3 text-left text-sm font-semibold text-heading sm:table-cell">
                                    Date(s)
                                </th>
                                <th class="hidden px-6 py-3 text-left text-sm font-semibold text-heading sm:table-cell">
                                    Route To</th>
                                <th class="relative px-6 py-3 text-left text-sm font-medium text-muted">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody v-if="!loading && holidays.length" class="divide-y divide-default bg-surface">
                            <tr v-for="holiday in holidays" :key="holiday.uuid">
                                <td class=" px-6 py-4 text-sm font-medium text-heading">
                                    {{ holiday.description }}

                                    <div class="px-6 py-2 text-sm text-muted sm:hidden">
                                        {{ holiday.human_date }}
                                    </div>

                                    <div class="px-6 py-2 text-sm text-muted sm:hidden">
                                        {{ holiday.target_label }}
                                    </div>
                                </td>
                                <!-- <td class="whitespace-nowrap px-6 py-4 text-sm text-muted">
                                    {{ call.members.length }}
                                </td> -->

                                <td class="hidden px-6 py-2 text-sm text-muted sm:table-cell">
                                    {{ holiday.human_date }}
                                </td>
                                <td class="hidden px-6 py-2 text-sm text-muted sm:table-cell">
                                    {{ holiday.target_label }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-2 text-right text-sm font-medium">
                                    <div class="flex items-center whitespace-nowrap justify-end">
                                        <ejs-tooltip v-if="permissions.holidays_update" :content="'Edit'" position='TopCenter'
                                            target="#destination_tooltip_target">
                                            <div id="destination_tooltip_target">
                                                <PencilSquareIcon @click="handleEditButtonClick(holiday.uuid)"
                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer" />

                                            </div>
                                        </ejs-tooltip>

                                        <ejs-tooltip v-if="permissions.holidays_delete" :content="'Delete'" position='TopCenter'
                                            target="#delete_tooltip_target">
                                            <div id="delete_tooltip_target">
                                                <TrashIcon @click="handleSingleItemDeleteRequest(holiday.uuid)"
                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer" />
                                            </div>
                                        </ejs-tooltip>
                                    </div>

                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="!loading && holidays.length === 0" class="text-center my-5">
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
    holidays: Object,
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