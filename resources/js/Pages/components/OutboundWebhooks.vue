<template>
    <div class="mt-4">
        <p class="mb-4 text-sm text-gray-500">{{ $t('Delivery results for notifications sent to webhook endpoints.') }}</p>
        <form class="flex flex-wrap items-start gap-3" @submit.prevent="fetchData()">
            <div>
                <label class="sr-only" for="outbound-webhook-search">{{ $t('Search') }}</label>
                <input id="outbound-webhook-search" v-model="filters.search" type="search" :placeholder="$t('Search event, ID, or URL')"
                    class="w-64 rounded-md border-0 py-1.5 text-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600" />
            </div>
            <div class="min-w-64">
                <DatePicker :date-range="filters.dateRange" :timezone="timezone" @update:date-range="updateDateRange" />
            </div>
            <div>
                <label class="sr-only" for="outbound-webhook-account">{{ $t('Account') }}</label>
                <select id="outbound-webhook-account" v-model="filters.domain_uuid"
                    class="max-w-64 rounded-md border-0 py-1.5 text-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600">
                    <option value="all">{{ $t('All accounts') }}</option>
                    <option v-for="account in domainOptions" :key="account.value" :value="account.value">{{ account.label }}</option>
                </select>
            </div>
            <div>
                <label class="sr-only" for="outbound-webhook-status">{{ $t('Status') }}</label>
                <select id="outbound-webhook-status" v-model="filters.status"
                    class="rounded-md border-0 py-1.5 text-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600">
                    <option value="">{{ $t('All statuses') }}</option>
                    <option v-for="(label, status) in statusLabels" :key="status" :value="status">{{ label }}</option>
                </select>
            </div>
            <button type="submit" :disabled="loading"
                class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">
                {{ $t('Search') }}
            </button>
            <button type="button" @click="resetFilters" :disabled="loading"
                class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-60">
                {{ $t('Reset') }}
            </button>
            <button type="button" @click="fetchData(data.current_page || 1)" :disabled="loading"
                class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-60">
                {{ $t('Refresh') }}
            </button>
        </form>

        <div v-if="error" role="alert" class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>
        <div class="mt-4 overflow-x-auto rounded-md border border-gray-200" :aria-busy="loading">
            <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to" :total="data.total"
                :current-page="data.current_page" :last-page="data.last_page" :links="data.links" @pagination-change-page="changePage" />
            <table class="w-full min-w-[68rem] table-fixed divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-gray-900">
                    <tr>
                        <th scope="col" class="w-48 px-4 py-3">{{ $t('Date') }}</th>
                        <th scope="col" class="w-52 px-4 py-3">{{ $t('Event') }}</th>
                        <th scope="col" class="px-4 py-3">{{ $t('Endpoint') }}</th>
                        <th scope="col" class="w-28 px-4 py-3">{{ $t('Status') }}</th>
                        <th scope="col" class="w-24 px-4 py-3">{{ $t('Attempts') }}</th>
                        <th scope="col" class="w-20 px-4 py-3">HTTP</th>
                        <th scope="col" class="w-24 px-4 py-3"><span class="sr-only">{{ $t('Details') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <template v-for="row in data.data" :key="row.outbound_webhook_log_uuid">
                        <tr class="text-gray-600 hover:bg-gray-50">
                            <td class="px-4 py-2">
                                <div class="whitespace-nowrap">{{ formatDate(row.created_at) }}</div>
                                <div class="truncate text-xs text-gray-500">{{ accountLabel(row) }}</div>
                            </td>
                            <td class="px-4 py-2"><div class="truncate" :title="row.event_type">{{ row.event_type }}</div></td>
                            <td class="px-4 py-2"><div class="truncate" :title="row.endpoint_url">{{ row.endpoint_url || '—' }}</div></td>
                            <td class="px-4 py-2">
                                <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium" :class="statusColor(row.status)">{{ statusLabels[row.status] || row.status }}</span>
                            </td>
                            <td class="px-4 py-2">{{ row.attempts }}</td>
                            <td class="px-4 py-2">{{ row.http_status ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <button type="button" class="text-indigo-600 underline hover:text-indigo-500"
                                    :aria-expanded="expandedRow === row.outbound_webhook_log_uuid"
                                    @click="expandedRow = expandedRow === row.outbound_webhook_log_uuid ? null : row.outbound_webhook_log_uuid">
                                    {{ expandedRow === row.outbound_webhook_log_uuid ? $t('Hide') : $t('Details') }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="expandedRow === row.outbound_webhook_log_uuid">
                            <td colspan="7" class="bg-gray-50 p-4">
                                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                                    <div><dt class="text-gray-500">{{ $t('Event ID') }}</dt><dd class="break-all text-gray-900">{{ row.event_id }}</dd></div>
                                    <div v-if="row.related_id"><dt class="text-gray-500">{{ $t('Related ID') }}</dt><dd class="break-all text-gray-900">{{ row.related_id }}</dd></div>
                                    <div class="sm:col-span-2"><dt class="text-gray-500">{{ $t('Endpoint') }}</dt><dd class="break-all text-gray-900">{{ row.http_method }} {{ row.endpoint_url || '—' }}</dd></div>
                                    <div v-if="row.attempted_at"><dt class="text-gray-500">{{ $t('Last attempt') }}</dt><dd>{{ formatDate(row.attempted_at) }}</dd></div>
                                    <div v-if="row.error"><dt class="text-gray-500">{{ $t('Error') }}</dt><dd>{{ row.error }}</dd></div>
                                </dl>
                                <table v-if="row.attempt_history?.length" class="mt-4 w-full text-left text-sm">
                                    <caption class="sr-only">{{ $t('Delivery attempts') }}</caption>
                                    <thead><tr class="text-gray-500"><th scope="col" class="py-2">{{ $t('Attempt') }}</th><th scope="col">{{ $t('Date') }}</th><th scope="col">{{ $t('Status') }}</th><th scope="col">HTTP</th><th scope="col">{{ $t('Error') }}</th></tr></thead>
                                    <tbody><tr v-for="attempt in row.attempt_history" :key="attempt.attempt" class="border-t border-gray-200">
                                        <td class="py-2">{{ attempt.attempt }}</td><td>{{ formatDate(attempt.attempted_at) }}</td>
                                        <td>{{ statusLabels[attempt.status] || attempt.status }}</td><td>{{ attempt.http_status ?? '—' }}</td><td>{{ attempt.error || '—' }}</td>
                                    </tr></tbody>
                                </table>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="loading && !data.data.length"><td colspan="7" class="p-4"><div class="h-4 animate-pulse rounded bg-gray-200"></div><div class="mt-3 h-4 animate-pulse rounded bg-gray-200"></div></td></tr>
                    <tr v-else-if="!loading && !error && !data.data.length"><td colspan="7" class="p-6 text-center text-gray-500">{{ $t('No outbound webhooks found for these filters.') }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import moment from 'moment-timezone';
import { trans } from '@i18n';
import Paginator from '@generalComponents/Paginator.vue';
import DatePicker from '@generalComponents/DatePicker.vue';

const props = defineProps({
    startPeriod: String, endPeriod: String, timezone: String, routes: Object, trigger: Boolean,
    domainOptions: { type: Array, default: () => [] }, selectedDomainUuid: String,
});
const defaults = () => ({ search: '', status: '', domain_uuid: props.selectedDomainUuid || 'all', dateRange: [props.startPeriod, props.endPeriod] });
const filters = ref(defaults());
const data = ref({ data: [], links: [], current_page: 1 });
const loading = ref(false);
const error = ref('');
const expandedRow = ref(null);
let activeRequest;
const statusLabels = computed(() => ({ pending: trans('Pending'), retrying: trans('Retrying'), delivered: trans('Delivered'), failed: trans('Failed') }));
const statusColor = (status) => ({ delivered: 'bg-green-50 text-green-700', failed: 'bg-red-50 text-red-700', retrying: 'bg-yellow-50 text-yellow-800' }[status] || 'bg-gray-100 text-gray-700');
const formatDate = (date) => date ? moment.utc(date).tz(props.timezone || 'UTC').format('MMM D, YYYY h:mm:ss A') : '—';
const accountLabel = (row) => row.domain?.domain_description || row.domain?.domain_name || (row.domain_uuid ? row.domain_uuid : trans('System'));

async function fetchData(page = 1) {
    activeRequest?.abort();
    const controller = new AbortController();
    activeRequest = controller;
    loading.value = true;
    error.value = '';
    try {
        const response = await axios.get(props.routes.outbound_webhooks, { params: { filter: filters.value, page }, signal: controller.signal });
        data.value = response.data;
        expandedRow.value = null;
    } catch (exception) {
        if (!axios.isCancel(exception)) {
            error.value = Object.values(exception.response?.data?.errors || {}).flat()[0]
                || exception.response?.data?.message || trans('Unable to load outbound webhooks.');
        }
    } finally {
        if (activeRequest === controller) loading.value = false;
    }
}
function resetFilters() { filters.value = defaults(); fetchData(); }
function updateDateRange(range) {
    filters.value.dateRange = range?.[0] && range?.[1] ? [
        moment(range[0]).tz(props.timezone || 'UTC').startOf('day').toISOString(),
        moment(range[1]).tz(props.timezone || 'UTC').endOf('day').toISOString(),
    ] : defaults().dateRange;
}
function changePage(url) { if (url) fetchData(new URL(url, window.location.origin).searchParams.get('page') || 1); }
watch(() => props.trigger, () => fetchData());
onUnmounted(() => activeRequest?.abort());
</script>
