<template>
    <div class="mt-4 space-y-5">
        <p class="text-sm text-gray-600">{{ scope === 'account' ? $t('Account-wide opt-outs') : $t('Opt-outs for :number', { number: destination }) }}</p>
        <p class="text-sm text-gray-600">{{ $t('Each entry records its receiving number. Account-wide mode checks all numbers in this account; per-number mode checks only the sending number. New entries are added under :number.', { number: destination }) }}</p>
        <Vueform ref="form$" :endpoint="false" :display-errors="false" :default="{ action: 'add' }" @submit="save"
            @mounted="form => form.disableValidation()">
            <SelectElement name="action" :label="$t('Action')" :floating="false" :native="false" :can-clear="false"
                :items="[{ value: 'add', label: $t('Add numbers') }, { value: 'remove', label: $t('Remove numbers') }]" />
            <TextareaElement name="numbers" :label="$t('Phone numbers')" :floating="false" :rows="4"
                :description="$t('Enter one number per line or separate numbers with commas. Include the country code for international numbers. Duplicates are combined.')" />
            <StaticElement name="search_control" :columns="{ container: 8 }">
                <div class="relative w-full max-w-64">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input v-model="search" type="search" :placeholder="$t('Search numbers')" :aria-label="$t('Search numbers')"
                        class="block w-full rounded-md border-0 py-2 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                        :disabled="saving || removing" @keydown.enter.stop.prevent="searchNow" />
                </div>
            </StaticElement>
            <ButtonElement name="save" :columns="{ container: 4 }" :button-label="$t('Save')" :submits="true" :loading="saving" :disabled="saving || loading || removing" align="right" />
        </Vueform>
        <p v-if="loading" class="text-sm text-gray-500">{{ $t('Loading...') }}</p>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                <thead><tr><th class="py-2">{{ $t('Phone Number') }}</th><th>{{ $t('Receiving Number') }}</th><th class="text-right">{{ $t('Action') }}</th></tr></thead>
                <tbody class="divide-y divide-gray-100"><tr v-for="row in records.data" :key="row.message_opt_out_uuid">
                    <td class="py-2">{{ row.phone_number }}</td><td>{{ row.local_number }}</td>
                    <td class="text-right">
                        <button type="button" class="inline-flex p-1 text-gray-400 hover:text-gray-600 disabled:opacity-50"
                            :title="$t('Remove opt-out')" :aria-label="$t('Remove opt-out for :number', { number: row.phone_number })"
                            :disabled="saving || loading || removing" @click="pendingRemoval = row">
                            <MinusCircleIcon class="h-5 w-5" aria-hidden="true" />
                        </button>
                    </td>
                </tr><tr v-if="!records.data?.length && !loading"><td colspan="3" class="py-3 text-gray-500">{{ $t('No opted-out recipients.') }}</td></tr></tbody>
            </table>
        </div>
        <div class="flex justify-between items-center text-sm">
            <button type="button" class="text-indigo-600 disabled:text-gray-400" :disabled="loading || saving || removing || !records.prev_page_url" @click="load(records.current_page - 1)">{{ $t('Previous') }}</button>
            <span>{{ records.total || 0 }} {{ $t('entries') }}</span>
            <button type="button" class="text-indigo-600 disabled:text-gray-400" :disabled="loading || saving || removing || !records.next_page_url" @click="load(records.current_page + 1)">{{ $t('Next') }}</button>
        </div>
        <ConfirmationModal :show="!!pendingRemoval" :header="$t('Remove opt-out')"
            :text="$t('Remove :number from the opt-out list for :local?', { number: pendingRemoval?.phone_number, local: pendingRemoval?.local_number })"
            :confirm-button-label="$t('Remove')" :cancel-button-label="$t('Cancel')" :loading="removing"
            @close="closeRemoval" @confirm="remove" />
    </div>
</template>
<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import axios from 'axios';
import { MagnifyingGlassIcon, MinusCircleIcon } from '@heroicons/vue/24/outline';
import ConfirmationModal from '../modal/ConfirmationModal.vue';
import { clearServerFormErrors, showServerFormErrors } from '../../../composables/serverFormErrors.js';
const props = defineProps({ route: String, destination: String });
const emit = defineEmits(['success', 'error']);
const form$ = ref(null), saving = ref(false), loading = ref(false), scope = ref('number'), records = ref({ data: [] });
const pendingRemoval = ref(null), removing = ref(false);
const search = ref(''), appliedSearch = ref('');
let latestRequest = 0;
const showError = error => {
    showServerFormErrors(error.response, form$.value);
    emit('error', error.response?.data?.errors || { request: [error.response?.data?.message || error.message] });
};
const load = async (page = 1) => {
    const request = ++latestRequest;
    loading.value = true;
    try {
        const { data } = await axios.get(props.route, { params: { page, search: appliedSearch.value } });
        if (request !== latestRequest) return;
        scope.value = data.scope;
        records.value = data.data;
    } catch (error) { if (request === latestRequest) showError(error); }
    finally { if (request === latestRequest) loading.value = false; }
};
const searchNow = () => {
    if (loading.value || saving.value || removing.value) return;
    appliedSearch.value = search.value;
    return load(1);
};
const save = async form => {
    if (saving.value || loading.value || removing.value) return;
    clearServerFormErrors(form);
    saving.value = true;
    try {
        const { data } = await axios.post(props.route, form.requestData);
        form.el$('numbers').clear();
        emit('success', data.messages);
        await load();
    } catch (error) { showError(error); }
    finally { saving.value = false; }
};
const closeRemoval = () => { if (!removing.value) pendingRemoval.value = null; };
const remove = async () => {
    if (!pendingRemoval.value || removing.value || saving.value || loading.value) return;
    removing.value = true;
    clearServerFormErrors(form$.value);
    try {
        const { data } = await axios.delete(`${props.route}/${pendingRemoval.value.message_opt_out_uuid}`);
        pendingRemoval.value = null;
        emit('success', data.messages);
        const lastPage = Math.max(1, Math.ceil((records.value.total - 1) / records.value.per_page));
        await load(Math.min(records.value.current_page, lastPage));
    } catch (error) { showError(error); }
    finally { removing.value = false; }
};
onMounted(() => load());
onBeforeUnmount(() => { ++latestRequest; });
</script>
