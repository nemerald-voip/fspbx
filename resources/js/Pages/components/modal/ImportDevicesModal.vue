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
                            <div class="mb-5 flex items-center justify-between">
                                <DialogTitle as="h3" class="text-base font-semibold leading-6 text-gray-900">
                                    {{ $t('Import Preview & Edit') }}
                                </DialogTitle>
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">{{ $t('Close') }}</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="flex items-center justify-center p-10">
                                <svg class="h-8 w-8 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <span class="ml-2 text-gray-600">{{ $t('Processing...') }}</span>
                            </div>

                            <div v-else class="w-full">
                                <!-- Bulk controls live in their own form so they survive page changes. -->
                                <Vueform ref="bulkForm$" :endpoint="false" :display-errors="false">
                                    <StaticElement name="bulk_header" tag="h4" :content="$t('Bulk Apply')"
                                        class="mb-2 mt-2 text-sm font-semibold text-gray-700" />

                                    <GroupElement name="bulk_group" :columns="{ lg: 12, md: 12, sm: 12 }"
                                        class="mb-6 flex items-start rounded-lg border border-gray-200 bg-gray-50 p-4">
                                        <SelectElement name="bulk_device_template" :label="$t('Template')" :floating="false"
                                            :items="options?.templates || []" label-prop="name" value-prop="value"
                                            :search="true" :native="false" input-type="search" autocomplete="off"
                                            :placeholder="$t('Select template...')" :columns="{ lg: 5, md: 5, sm: 12 }" />

                                        <SelectElement name="bulk_device_key_template_uuid" :label="$t('Key Template')"
                                            :floating="false" :items="options?.key_templates || []" label-prop="name"
                                            value-prop="value" :search="true" :native="false" input-type="search"
                                            autocomplete="off" :placeholder="$t('Select key template...')"
                                            :columns="{ lg: 5, md: 5, sm: 12 }" />

                                        <StaticElement name="apply_bulk_btn" :columns="{ lg: 2, md: 2, sm: 12 }">
                                            <template #default>
                                                <div class="pt-[26px]">
                                                    <button type="button" @click.prevent="handleBulkApply"
                                                        class="inline-flex w-full justify-center whitespace-nowrap rounded-md bg-indigo-600 px-3 py-[9px] text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                                        {{ $t('Apply to All') }}
                                                    </button>
                                                </div>
                                            </template>
                                        </StaticElement>
                                    </GroupElement>
                                </Vueform>

                                <div
                                    class="mb-2 hidden grid-cols-12 gap-4 border-b border-gray-200 bg-white px-2 py-2 text-xs font-bold uppercase tracking-wider text-gray-500 lg:grid">
                                    <div class="col-span-2 pl-2">{{ $t('MAC Address') }}</div>
                                    <div class="col-span-2 pl-2">{{ $t('Serial Number') }}</div>
                                    <div class="col-span-2 pl-2">{{ $t('Extension') }}</div>
                                    <div class="col-span-3 pl-2">{{ $t('Template') }}</div>
                                    <div class="col-span-3 pl-2">{{ $t('Key Template') }}</div>
                                </div>

                                <!-- Only the current page is mounted. Each row builds three searchable selects
                                     that render their whole option list into the DOM, so mounting all rows at
                                     once is what made large imports hang. -->
                                <Vueform :key="formKey" ref="form$" :endpoint="false" :display-errors="false"
                                    :default="{ items: pageItems }">
                                    <ListElement name="items" :sort="false" :controls="{ add: false, remove: true }"
                                        @remove="handleRowRemove">
                                        <template #default="{ index }">
                                            <ObjectElement :name="index" :columns="{ lg: 12, md: 12 }">
                                                <TextElement name="mac_address" :floating="false"
                                                    :columns="{ lg: 2, md: 3, sm: 12 }" />

                                                <TextElement name="serial_number" :floating="false"
                                                    :columns="{ lg: 2, md: 3, sm: 12 }" />

                                                <SelectElement name="associated_extension" :floating="false"
                                                    :items="options?.extensions || []" label-prop="name"
                                                    value-prop="value" :search="true" :native="false"
                                                    input-type="search" autocomplete="off" :limit="OPTION_RENDER_LIMIT"
                                                    :placeholder="$t('Select extension...')"
                                                    :columns="{ lg: 2, md: 3, sm: 12 }" />

                                                <SelectElement name="device_template" :floating="false"
                                                    :items="options?.templates || []" label-prop="name"
                                                    value-prop="value" :search="true" :native="false"
                                                    input-type="search" autocomplete="off" :limit="OPTION_RENDER_LIMIT"
                                                    :placeholder="$t('Select template...')"
                                                    :columns="{ lg: 3, md: 3, sm: 12 }" />

                                                <SelectElement name="device_key_template_uuid" :floating="false"
                                                    :items="options?.key_templates || []" label-prop="name"
                                                    value-prop="value" :search="true" :native="false"
                                                    input-type="search" autocomplete="off" :limit="OPTION_RENDER_LIMIT"
                                                    :placeholder="$t('Select key template...')"
                                                    :columns="{ lg: 3, md: 3, sm: 12 }" />
                                            </ObjectElement>
                                        </template>
                                    </ListElement>
                                </Vueform>

                                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm text-gray-600">{{ rangeLabel }}</span>
                                        <select v-model.number="pageSize"
                                            class="rounded-md border-0 py-1.5 pl-2 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600"
                                            @change="handlePageSizeChange">
                                            <option v-for="size in PAGE_SIZE_OPTIONS" :key="size" :value="size">
                                                {{ $t(':count per page', { count: size }) }}
                                            </option>
                                        </select>
                                    </div>

                                    <div v-if="totalPages > 1" class="flex items-center gap-2">
                                        <button type="button"
                                            class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="currentPage <= 1" @click="goToPage(currentPage - 1)">
                                            {{ $t('Previous') }}
                                        </button>
                                        <span class="text-sm text-gray-600">{{ pageLabel }}</span>
                                        <button type="button"
                                            class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="currentPage >= totalPages" @click="goToPage(currentPage + 1)">
                                            {{ $t('Next') }}
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-6 flex justify-end gap-3 border-t bg-white pt-4">
                                    <button type="button"
                                        class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                        @click="emit('close')">
                                        {{ $t('Cancel') }}
                                    </button>
                                    <button type="button"
                                        class="rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600"
                                        :disabled="loading || isSubmitting"
                                        @click.prevent="manualSubmit">
                                        {{ loading || isSubmitting ? $t('Importing...') : $t('Confirm Import') }}
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
import { computed, nextTick, ref, watch } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { XMarkIcon } from "@heroicons/vue/24/solid";
import axios from 'axios';
import { trans } from "@i18n";

const PAGE_SIZE_OPTIONS = [25, 50, 100];
// Each searchable select renders its full option list into the DOM up front, and the
// template list runs to a few hundred entries. Cap what is rendered per select - typing
// still searches the complete list, and an already selected value keeps its label.
const OPTION_RENDER_LIMIT = 20;

const props = defineProps({
    show: Boolean,
    importData: Array,
    options: Object,
    loading: Boolean,
});

const emit = defineEmits(['close', 'success', 'error']);
const form$ = ref(null);
const bulkForm$ = ref(null);
const isSubmitting = ref(false);

// Master list of every imported row. Only `pageItems` is handed to Vueform.
const allItems = ref([]);
const pageItems = ref([]);
// Offset and length of the slice currently mounted in the items form. Kept separate from
// `pageItems` so syncing edits back never touches the `:default` binding mid-edit.
const renderedOffset = ref(0);
const renderedCount = ref(0);
const currentPage = ref(1);
const pageSize = ref(PAGE_SIZE_OPTIONS[0]);
// Bumped to force a clean remount of the items form whenever the visible slice changes.
const formKey = ref(0);

const totalPages = computed(() => Math.max(1, Math.ceil(allItems.value.length / pageSize.value)));

const pageLabel = computed(() => trans('Page :current of :total', {
    current: currentPage.value,
    total: totalPages.value,
}));

const rangeLabel = computed(() => {
    const total = allItems.value.length;

    if (total === 0) {
        return trans('No rows to import');
    }

    return trans('Showing :from-:to of :total', {
        from: renderedOffset.value + 1,
        to: Math.min(renderedOffset.value + renderedCount.value, total),
        total,
    });
});

const renderPage = () => {
    currentPage.value = Math.min(Math.max(currentPage.value, 1), totalPages.value);
    renderedOffset.value = (currentPage.value - 1) * pageSize.value;
    pageItems.value = allItems.value.slice(renderedOffset.value, renderedOffset.value + pageSize.value);
    renderedCount.value = pageItems.value.length;
    formKey.value++;
};

// Write whatever the user edited on the visible page back into the master list.
// Splicing by the rendered length (not the new length) keeps row removals correct.
const syncCurrentPage = () => {
    const items = form$.value?.data?.items;

    if (!Array.isArray(items)) return;

    allItems.value.splice(renderedOffset.value, renderedCount.value, ...items);
    renderedCount.value = items.length;
};

watch(() => props.importData, (rows) => {
    // `id` is a preview-only marker the commit endpoint ignores, and the row form does not
    // carry it. Dropping it here keeps every row the same shape once a page is synced back.
    allItems.value = Array.isArray(rows)
        ? rows.map(({ id, ...row }) => ({ ...row }))
        : [];
    currentPage.value = 1;
    renderPage();
}, { immediate: true, deep: false });

const goToPage = (page) => {
    if (page < 1 || page > totalPages.value || page === currentPage.value) return;

    syncCurrentPage();
    currentPage.value = page;
    renderPage();
};

const handlePageSizeChange = () => {
    syncCurrentPage();
    currentPage.value = 1;
    renderPage();
};

const handleRowRemove = () => {
    // Vueform has already dropped the row from the page form by the time this fires.
    nextTick(() => {
        syncCurrentPage();
        renderPage();
    });
};

const handleBulkApply = () => {
    if (!bulkForm$.value) return;

    const bulkTemplate = bulkForm$.value.el$('bulk_group.bulk_device_template')?.value;
    const bulkKeyTemplate = bulkForm$.value.el$('bulk_group.bulk_device_key_template_uuid')?.value;

    syncCurrentPage();

    if (allItems.value.length === 0) return;

    allItems.value = allItems.value.map((item) => {
        const updatedItem = { ...item };

        if (bulkTemplate !== null && bulkTemplate !== undefined && bulkTemplate !== '') {
            updatedItem.device_template = bulkTemplate;
        }

        if (bulkKeyTemplate !== null && bulkKeyTemplate !== undefined && bulkKeyTemplate !== '') {
            updatedItem.device_key_template_uuid = bulkKeyTemplate;
        }

        return updatedItem;
    });

    renderPage();
};

const manualSubmit = async () => {
    if (isSubmitting.value) return;

    syncCurrentPage();

    if (allItems.value.length === 0) return;

    try {
        isSubmitting.value = true;
        const response = await axios.post(props.options?.routes?.import_commit || '/devices/import/commit', {
            items: allItems.value,
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
