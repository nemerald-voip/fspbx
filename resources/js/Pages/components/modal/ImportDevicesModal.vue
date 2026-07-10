<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-50" @close="emit('close')">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative transform rounded-lg bg-surface px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-7xl sm:p-6">
                            <div class="mb-5 flex items-center justify-between">
                                <DialogTitle as="h3" class="text-base font-semibold leading-6 text-heading">
                                    Import Preview & Edit
                                </DialogTitle>
                                <button type="button"
                                    class="rounded-md bg-surface text-subtle hover:text-muted focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="flex items-center justify-center p-10">
                                <svg class="h-8 w-8 animate-spin text-info" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <span class="ml-2 text-body">Processing...</span>
                            </div>

                            <div v-else class="w-full">
                                <Vueform ref="form$" :endpoint="false" :display-errors="false"
                                    :default="{ items: importData }">
                                    <StaticElement name="bulk_header" tag="h4" content="Bulk Apply"
                                        class="mb-2 mt-2 text-sm font-semibold text-body" />

                                    <GroupElement name="bulk_group" :columns="{ lg: 12, md: 12, sm: 12 }"
                                        class="mb-6 flex items-start rounded-lg border border-default bg-surface-2 p-4">
                                        <SelectElement name="bulk_device_template" label="Template" :floating="false"
                                            :items="options?.templates || []" label-prop="name" value-prop="value"
                                            :search="true" :native="false" input-type="search" autocomplete="off"
                                            placeholder="Select template..." :columns="{ lg: 5, md: 5, sm: 12 }" />

                                        <SelectElement name="bulk_device_key_template_uuid" label="Key Template"
                                            :floating="false" :items="options?.key_templates || []" label-prop="name"
                                            value-prop="value" :search="true" :native="false" input-type="search"
                                            autocomplete="off" placeholder="Select key template..."
                                            :columns="{ lg: 5, md: 5, sm: 12 }" />

                                        <StaticElement name="apply_bulk_btn" :columns="{ lg: 2, md: 2, sm: 12 }">
                                            <template #default>
                                                <div class="pt-[26px]">
                                                    <button type="button" @click.prevent="handleBulkApply"
                                                        class="inline-flex w-full justify-center whitespace-nowrap rounded-md bg-accent px-3 py-[9px] text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                                                        Apply to All
                                                    </button>
                                                </div>
                                            </template>
                                        </StaticElement>
                                    </GroupElement>

                                    <StaticElement name="table_header_row">
                                        <template #default>
                                            <div
                                                class="mb-2 hidden grid-cols-12 gap-4 border-b border-default bg-surface px-2 py-2 text-xs font-bold uppercase tracking-wider text-muted lg:grid">
                                                <div class="col-span-2 pl-2">MAC Address</div>
                                                <div class="col-span-2 pl-2">Serial Number</div>
                                                <div class="col-span-2 pl-2">Extension</div>
                                                <div class="col-span-3 pl-2">Template</div>
                                                <div class="col-span-3 pl-2">Key Template</div>
                                            </div>
                                        </template>
                                    </StaticElement>

                                    <ListElement name="items" :sort="false" :controls="{ add: false, remove: true }">
                                        <template #default="{ index }">
                                            <ObjectElement :name="index" :columns="{ lg: 12, md: 12 }">
                                                <TextElement name="mac_address" :floating="false"
                                                    :columns="{ lg: 2, md: 3, sm: 12 }" />

                                                <TextElement name="serial_number" :floating="false"
                                                    :columns="{ lg: 2, md: 3, sm: 12 }" />

                                                <SelectElement name="associated_extension" :floating="false"
                                                    :items="options?.extensions || []" label-prop="name"
                                                    value-prop="value" :search="true" :native="false"
                                                    input-type="search" autocomplete="off"
                                                    placeholder="Select extension..."
                                                    :columns="{ lg: 2, md: 3, sm: 12 }" />

                                                <SelectElement name="device_template" :floating="false"
                                                    :items="options?.templates || []" label-prop="name"
                                                    value-prop="value" :search="true" :native="false"
                                                    input-type="search" autocomplete="off"
                                                    placeholder="Select template..."
                                                    :columns="{ lg: 3, md: 3, sm: 12 }" />

                                                <SelectElement name="device_key_template_uuid" :floating="false"
                                                    :items="options?.key_templates || []" label-prop="name"
                                                    value-prop="value" :search="true" :native="false"
                                                    input-type="search" autocomplete="off"
                                                    placeholder="Select key template..."
                                                    :columns="{ lg: 3, md: 3, sm: 12 }" />
                                            </ObjectElement>
                                        </template>
                                    </ListElement>
                                </Vueform>

                                <div class="mt-6 flex justify-end gap-3 border-t bg-surface pt-4">
                                    <button type="button"
                                        class="rounded-md bg-surface px-3 py-2 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2"
                                        @click="emit('close')">
                                        Cancel
                                    </button>
                                    <button type="button"
                                        class="rounded-md bg-success-solid px-3 py-2 text-sm font-semibold text-on-accent shadow-sm hover:bg-success-solid-hover disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-success"
                                        :disabled="loading || isSubmitting"
                                        @click.prevent="manualSubmit">
                                        {{ loading || isSubmitting ? 'Importing...' : 'Confirm Import' }}
                                    </button>
                                </div>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { XMarkIcon } from "@heroicons/vue/24/solid";
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    importData: Array,
    options: Object,
    loading: Boolean,
});

const emit = defineEmits(['close', 'success', 'error']);
const form$ = ref(null);
const isSubmitting = ref(false);

const handleBulkApply = () => {
    if (!form$.value) return;

    const f = form$.value;
    const bulkTemplate = f.el$('bulk_group.bulk_device_template')?.value;
    const bulkKeyTemplate = f.el$('bulk_group.bulk_device_key_template_uuid')?.value;
    const items = f.data.items || [];

    if (items.length === 0) return;

    const newItems = items.map((item) => {
        const updatedItem = { ...item };

        if (bulkTemplate !== null && bulkTemplate !== '') {
            updatedItem.device_template = bulkTemplate;
        }

        if (bulkKeyTemplate !== null && bulkKeyTemplate !== '') {
            updatedItem.device_key_template_uuid = bulkKeyTemplate;
        }

        return updatedItem;
    });

    f.el$('items').update(newItems);
};

const manualSubmit = async () => {
    if (!form$.value) return;
    if (isSubmitting.value) return;

    const data = form$.value.data;
    if (!data.items || data.items.length === 0) return;

    try {
        isSubmitting.value = true;
        const response = await axios.post(props.options?.routes?.import_commit || '/devices/import/commit', {
            items: data.items,
        });

        if (response.data?.success) {
            emit('success', response.data.messages);
        }
    } catch (error) {
        emit('error', error);
    } finally {
        isSubmitting.value = false;
    }
};
</script>
