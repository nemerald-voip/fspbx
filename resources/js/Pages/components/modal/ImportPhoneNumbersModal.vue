<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-50" @close="emit('close')">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                        <DialogPanel
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-7xl sm:p-6">

                            <div class="flex justify-between items-center mb-5">
                                <DialogTitle as="h3" class="text-base font-semibold leading-6 text-gray-900">
                                    Import Preview & Edit
                                </DialogTitle>
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="flex justify-center items-center p-10">
                                <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span class="ml-2 text-gray-600">Processing...</span>
                            </div>

                            <div v-else class="w-full">
                                <Vueform ref="form$" :endpoint="false" :display-errors="false" :default="{ items: importData }">

                                    <StaticElement name="bulk_header" tag="h4" content="Bulk Apply" class="text-sm font-semibold text-gray-700 mb-2 mt-2" />
                                    
                                    <GroupElement name="bulk_group" :columns="{ lg: 12, md: 12, sm: 12 }" class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-6 flex items-start">
                                        
                                        <SelectElement name="bulk_type" label="Action" :floating="false"
                                            :items="options.routing_types || []" label-prop="name" value-prop="value"
                                            :search="true" :native="false" input-type="search" autocomplete="off"
                                            placeholder="Select Action..." :columns="{ lg: 3, md: 3, sm: 12 }"
                                            @change="(newValue, oldValue, el$) => {
                                                let target = el$.form$.el$('bulk_group.bulk_extension');
                                                if (target) {
                                                    target.clear();
                                                    target.updateItems();
                                                }
                                            }" />

                                        <SelectElement name="bulk_extension" label="Target" :floating="false"
                                            :search="true" :native="false" input-type="search" autocomplete="off"
                                            placeholder="Select Target..." label-prop="name" value-prop="extension"
                                            :columns="{ lg: 3, md: 3, sm: 12 }"
                                            :items="async (query, input) => {
                                                let formInst = input.form$ || input.$parent?.el$?.form$;
                                                if (!formInst) return [];
                                                
                                                let typeEl = formInst.el$('bulk_group.bulk_type');
                                                if (!typeEl || !typeEl.value) return [];
                                                
                                                try {
                                                    let response = await axios.post(options.routes.get_routing_options, { category: typeEl.value });
                                                    return response.data.options || [];
                                                } catch (error) { return []; }
                                            }" />

                                        <TextElement name="bulk_description" label="Description" :floating="false"
                                            placeholder="e.g. Main Office" 
                                            :columns="{ lg: 4, md: 4, sm: 12 }" />

                                        <StaticElement name="apply_bulk_btn" :columns="{ lg: 2, md: 2, sm: 12 }">
                                            <template #default>
                                                <div class="pt-[26px]">
                                                    <button type="button" @click.prevent="handleBulkApply"
                                                        class="w-full whitespace-nowrap inline-flex justify-center rounded-md bg-indigo-600 px-3 py-[9px] text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                                        Apply to All
                                                    </button>
                                                </div>
                                            </template>
                                        </StaticElement>

                                    </GroupElement>

                                    <StaticElement name="table_header_row">
                                        <template #default>
                                            <div class="hidden lg:grid grid-cols-12 gap-4 px-2 py-2 bg-white border-b border-gray-200 text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">
                                                <div class="col-span-3 pl-2">Phone Number</div>
                                                <div class="col-span-3 pl-2">Action</div>
                                                <div class="col-span-3 pl-2">Target</div>
                                                <div class="col-span-3 pl-2">Description</div>
                                            </div>
                                        </template>
                                    </StaticElement>

                                    <ListElement name="items" :sort="false" :controls="{ add: false, remove: true }">
                                        <template #default="{ index }">
                                            <ObjectElement :name="index" :columns="{ lg: 12, md: 12 }">
                                                
                                                <TextElement name="destination_number" :floating="false"
                                                    :columns="{ lg: 3, md: 3, sm: 12 }" />

                                                <SelectElement name="routing_type" :floating="false"
                                                    :items="options.routing_types || []" label-prop="name" value-prop="value"
                                                    :search="true" :native="false" input-type="search" autocomplete="off"
                                                    placeholder="Select Action..."
                                                    :columns="{ lg: 3, md: 3, sm: 12 }" 
                                                    @change="(newValue, oldValue, el$) => {
                                                        let extension = el$.form$.el$('items.' + index + '.routing_extension');
                                                        if (extension) {
                                                            extension.clear();
                                                            extension.updateItems();
                                                        }
                                                    }" />

                                                <SelectElement name="routing_extension" :floating="false"
                                                    :search="true" :native="false" input-type="search" autocomplete="off"
                                                    placeholder="Select Target..." label-prop="name" value-prop="extension"
                                                    :columns="{ lg: 3, md: 3, sm: 12 }"
                                                    :items="async (query, input) => {
                                                        let formInst = input.form$ || input.$parent?.el$?.form$;
                                                        if (!formInst) return [];
                                                        
                                                        let typeEl = formInst.el$('items.' + index + '.routing_type');
                                                        if (!typeEl || !typeEl.value) return [];
                                                        
                                                        try {
                                                            let response = await axios.post(options.routes.get_routing_options, { category: typeEl.value });
                                                            return response.data.options || [];
                                                        } catch (error) { return []; }
                                                    }" />

                                                <TextElement name="destination_description" :floating="false"
                                                    placeholder="Description" 
                                                    :columns="{ lg: 3, md: 3, sm: 12 }" />

                                            </ObjectElement>
                                        </template>
                                    </ListElement>

                                </Vueform>

                                <div class="mt-6 flex justify-end gap-3 border-t pt-4 bg-white">
                                    <button type="button" 
                                        class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                        @click="emit('close')">
                                        Cancel
                                    </button>
                                    <button type="button" 
                                        class="rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600"
                                        @click.prevent="manualSubmit">
                                        {{ loading ? 'Importing...' : 'Confirm Import' }}
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
    loading: Boolean
});

const emit = defineEmits(['close', 'success']);
const form$ = ref(null);

// Bulk Apply Logic
const handleBulkApply = () => {
    if (!form$.value) return;
    const f = form$.value;
    
    // 1. Safely retrieve the Bulk Values
    // Checking both potential element paths to guarantee we grab the value
    const bulkTypeEl = f.el$('bulk_type') || f.el$('bulk_group.bulk_type');
    const bulkType = bulkTypeEl ? bulkTypeEl.value : null;

    const bulkExtEl = f.el$('bulk_extension') || f.el$('bulk_group.bulk_extension');
    const bulkExt = bulkExtEl ? bulkExtEl.value : null;

    const bulkDescEl = f.el$('bulk_description') || f.el$('bulk_group.bulk_description');
    const bulkDesc = bulkDescEl ? bulkDescEl.value : null;

    // Determine how many items are in the list
    const items = f.data.items || [];
    const numItems = items.length;

    if (numItems === 0) return;

    // Push out the Actions (Type) and Description first
    for (let i = 0; i < numItems; i++) {
        if (bulkDesc !== null && bulkDesc !== undefined && bulkDesc !== '') {
            let descEl = f.el$('items.' + i + '.destination_description');
            if (descEl) descEl.update(bulkDesc);
        }
        
        if (bulkType !== null && bulkType !== undefined && bulkType !== '') {
            let typeEl = f.el$('items.' + i + '.routing_type');
            if (typeEl) typeEl.update(bulkType);
        }
    }

    // Wait for Targets to load, then push the Target Value
    if (bulkType !== null && bulkType !== undefined && bulkType !== '') {
        
        // Give Vueform a moment to register the new Action values internally
        setTimeout(() => {
            for (let i = 0; i < numItems; i++) {
                let targetEl = f.el$('items.' + i + '.routing_extension');
                if (targetEl) {
                    
                    targetEl.updateItems().then(() => {
                        
                        if (bulkExt !== null && bulkExt !== undefined && bulkExt !== '') {
                            targetEl.update(bulkExt);
                        }
                        
                    }).catch(err => console.error("Error updating items:", err));
                }
            }
        }, 100);
    }
};

// -- Manual Submit Trigger --
const manualSubmit = async () => {
    if (!form$.value) return;
    
    const data = form$.value.data; 
    
    if (!data.items || data.items.length === 0) return;

    try {
        const response = await axios.post('/phone-numbers/import/commit', { 
            items: data.items 
        });
        
        if (response.data && response.data.success) {
            emit('success', response.data.messages);
        }
    } catch (error) {
        console.error("Submission failed:", error);
        if(error.response && error.response.data && error.response.data.errors) {
             const errorMsg = Object.values(error.response.data.errors).flat().join('\n');
             alert("Import Failed:\n" + errorMsg);
        }
    }
};
</script>