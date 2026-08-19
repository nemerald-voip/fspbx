<template>
    <div class="mt-4 flex flex-col">
        <div class="flex flex-col sm:flex-row sm:flex-wrap">
            <div class="relative mb-2 min-w-64 focus-within:z-10 sm:mr-4">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                </div>
                <input v-model="filterData.search" type="search"
                    class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                    :placeholder="$t('Search')" @keydown.enter="handleSearchButtonClick" />
            </div>

            <div class="relative z-10 mb-2 min-w-64 -mt-0.5 scale-y-95 shrink-0 sm:mr-4">
                <DatePicker :dateRange="filterData.dateRange" :timezone="timezone"
                    @update:date-range="handleUpdateDateRange" />
            </div>

            <div v-if="showDomainFilter" class="relative z-[1] mb-2 min-w-72 -mt-0.5 shrink-0 sm:mr-4">
                <Vueform :key="domainFilterKey" :display-errors="false" size="sm">
                    <SelectElement name="domain_uuid" :default="filterData.domain_uuid"
                        :items="domainFilterOptions" :native="false" :search="true" input-type="search"
                        autocomplete="off" :strict="false" :floating="false"
                        @change="handleUpdateDomainFilter" />
                </Vueform>
            </div>

            <div class="relative mb-2 flex items-start gap-2">
                <button type="button" @click="handleSearchButtonClick"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    {{ $t('Search') }}
                </button>
                <button type="button" @click="handleFiltersReset"
                    class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    {{ $t('Reset') }}
                </button>
            </div>
        </div>

        <div class="mt-2 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <Paginator class="border border-gray-200" :previous="data.prev_page_url" :next="data.next_page_url"
                    :from="data.from" :to="data.to" :total="data.total" :currentPage="data.current_page"
                    :lastPage="data.last_page" :links="data.links" @pagination-change-page="renderRequestedPage" />

                <div class="border-l border-r border-gray-200">
                    <table class="mb-4 min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Date') }}</th>
                                <th v-if="showDomainColumn" class="px-4 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Account') }}</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Agent') }}</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Tool') }}</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Call ID') }}</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Status') }}</th>
                            </tr>
                        </thead>

                        <tbody v-if="!isDataLoading && data.data?.length" class="divide-y divide-gray-200 bg-white">
                            <template v-for="row in data.data" :key="row.ai_tool_invocation_uuid">
                                <tr class="cursor-pointer hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-indigo-600"
                                    role="button" tabindex="0"
                                    :aria-expanded="expandedRow === row.ai_tool_invocation_uuid"
                                    :aria-controls="`ai-agent-log-details-${row.ai_tool_invocation_uuid}`"
                                    @click="toggleExpand(row.ai_tool_invocation_uuid)"
                                    @keydown.enter.prevent="toggleExpand(row.ai_tool_invocation_uuid)"
                                    @keydown.space.prevent="toggleExpand(row.ai_tool_invocation_uuid)">
                                    <td class="whitespace-nowrap px-4 py-2 text-sm font-medium text-gray-500">
                                        {{ formatDate(row.created_at) }}
                                    </td>
                                    <td v-if="showDomainColumn" class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                                        {{ domainLabel(row) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-700">
                                        <div class="max-w-48 truncate font-medium text-gray-900" :title="row.agent?.name || row.ai_agent_uuid">
                                            {{ row.agent?.name || $t('Deleted AI Agent') }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                                        {{ toolLabel(row.tool_name) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500">
                                        <div class="flex max-w-72 items-center gap-1.5">
                                            <span class="truncate font-mono text-xs" :title="row.provider_call_id">
                                                {{ row.provider_call_id }}
                                            </span>
                                            <div class="group relative inline-flex shrink-0">
                                                <button type="button"
                                                    class="rounded-full p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
                                                    :aria-label="$t('Copy call ID')"
                                                    @click.stop="copyCallId(row.provider_call_id)"
                                                    @keydown.stop>
                                                    <ClipboardDocumentIcon class="h-4 w-4" aria-hidden="true" />
                                                </button>
                                                <div role="tooltip"
                                                    class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded bg-gray-800 px-2 py-1 text-xs text-white shadow-lg group-hover:block group-focus-within:block">
                                                    {{ $t('Copy call ID') }}
                                                    <span class="absolute left-1/2 top-full h-2 w-2 -translate-x-1/2 -translate-y-1 rotate-45 bg-gray-800"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-sm">
                                        <Badge :text="statusLabel(row.status)" v-bind="statusBadge(row.status)"
                                            class="px-2 py-1 text-xs" />
                                    </td>
                                </tr>

                                <tr v-if="expandedRow === row.ai_tool_invocation_uuid">
                                    <td :id="`ai-agent-log-details-${row.ai_tool_invocation_uuid}`"
                                        :colspan="columnCount" class="bg-gray-50 px-6 py-4 shadow-inner">
                                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                            <section class="rounded-md border border-gray-200 bg-white p-4">
                                                <h4 class="mb-2 text-sm font-semibold text-gray-900">{{ $t('Overview') }}</h4>
                                                <dl class="space-y-2 text-sm text-gray-600">
                                                    <div>
                                                        <dt class="inline font-medium text-gray-700">{{ $t('Log ID') }}:</dt>
                                                        <dd class="inline break-all font-mono text-xs"> {{ displayValue(row.ai_tool_invocation_uuid) }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="inline font-medium text-gray-700">{{ $t('Agent') }}:</dt>
                                                        <dd class="inline"> {{ displayValue(row.agent?.name || $t('Deleted AI Agent')) }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="inline font-medium text-gray-700">{{ $t('Agent UUID') }}:</dt>
                                                        <dd class="inline break-all font-mono text-xs"> {{ displayValue(row.ai_agent_uuid) }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="inline font-medium text-gray-700">{{ $t('Account') }}:</dt>
                                                        <dd class="inline"> {{ displayValue(domainLabel(row)) }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="inline font-medium text-gray-700">{{ $t('Date') }}:</dt>
                                                        <dd class="inline"> {{ displayValue(formatDate(row.created_at)) }}</dd>
                                                    </div>
                                                </dl>
                                            </section>

                                            <section class="rounded-md border border-gray-200 bg-white p-4">
                                                <h4 class="mb-2 text-sm font-semibold text-gray-900">{{ $t('Invocation') }}</h4>
                                                <dl class="space-y-2 text-sm text-gray-600">
                                                    <div>
                                                        <dt class="inline font-medium text-gray-700">{{ $t('Tool') }}:</dt>
                                                        <dd class="inline"> {{ displayValue(toolLabel(row.tool_name)) }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="inline font-medium text-gray-700">{{ $t('Tool name') }}:</dt>
                                                        <dd class="inline break-all font-mono text-xs"> {{ displayValue(row.tool_name) }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="inline font-medium text-gray-700">{{ $t('Provider call ID') }}:</dt>
                                                        <dd class="inline break-all font-mono text-xs"> {{ displayValue(row.provider_call_id) }}</dd>
                                                    </div>
                                                </dl>
                                            </section>

                                            <section class="rounded-md border border-gray-200 bg-white p-4 lg:col-span-2">
                                                <h4 class="mb-2 text-sm font-semibold text-gray-900">{{ $t('Payload') }}</h4>

                                                <template v-if="hasPayload(row)">
                                                    <dl v-if="row.tool_name === 'fspbx_send_email'" class="space-y-2 text-sm text-gray-600">
                                                        <div>
                                                            <dt class="inline font-medium text-gray-700">{{ $t('Recipient') }}:</dt>
                                                            <dd class="inline break-all"> {{ displayValue(row.request_payload.recipient) }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="inline font-medium text-gray-700">{{ $t('Subject') }}:</dt>
                                                            <dd class="inline"> {{ displayValue(row.request_payload.subject) }}</dd>
                                                        </div>
                                                        <div v-if="emailFields(row).length">
                                                            <dt class="font-medium text-gray-700">{{ $t('Collected information') }}:</dt>
                                                            <dd class="mt-1">
                                                                <dl class="divide-y divide-gray-100 rounded-md border border-gray-200">
                                                                    <div v-for="(field, index) in emailFields(row)" :key="`${field.label}-${index}`"
                                                                        class="grid gap-1 px-3 py-2 sm:grid-cols-[minmax(10rem,0.35fr)_1fr]">
                                                                        <dt class="font-medium text-gray-700">{{ displayValue(field.label) }}</dt>
                                                                        <dd class="whitespace-pre-wrap break-words">{{ displayValue(field.value) }}</dd>
                                                                    </div>
                                                                </dl>
                                                            </dd>
                                                        </div>
                                                        <div v-if="row.request_payload.notes">
                                                            <dt class="font-medium text-gray-700">{{ $t('Notes') }}:</dt>
                                                            <dd class="mt-1 whitespace-pre-wrap break-words">{{ row.request_payload.notes }}</dd>
                                                        </div>
                                                    </dl>

                                                    <details class="mt-4">
                                                        <summary class="cursor-pointer select-none text-sm font-medium text-gray-700 hover:text-gray-900">
                                                            {{ $t('Raw payload') }}
                                                        </summary>
                                                        <pre class="mt-2 max-h-96 overflow-auto rounded-md bg-gray-900 p-3 text-xs leading-5 text-gray-100">{{ prettyJson(row.request_payload) }}</pre>
                                                    </details>
                                                </template>

                                                <p v-else class="text-sm text-gray-500">
                                                    {{ $t('Payload was not recorded for this invocation.') }}
                                                </p>
                                            </section>

                                            <section class="rounded-md border border-gray-200 bg-white p-4 lg:col-span-2">
                                                <h4 class="mb-2 text-sm font-semibold text-gray-900">{{ $t('Result') }}</h4>
                                                <dl class="space-y-2 text-sm text-gray-600">
                                                    <div class="flex items-center gap-2">
                                                        <dt class="font-medium text-gray-700">{{ $t('Status') }}:</dt>
                                                        <dd>
                                                            <Badge :text="statusLabel(row.status)" v-bind="statusBadge(row.status)"
                                                                class="px-2 py-1 text-xs" />
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt class="inline font-medium text-gray-700">{{ $t('Completed') }}:</dt>
                                                        <dd class="inline"> {{ displayValue(row.sent_at ? formatDate(row.sent_at) : $t('Not completed')) }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="inline font-medium text-gray-700">{{ $t('Elapsed') }}:</dt>
                                                        <dd class="inline"> {{ displayValue(elapsedTime(row)) }}</dd>
                                                    </div>
                                                    <div v-if="row.last_error">
                                                        <dt class="font-medium text-red-700">{{ $t('Error') }}:</dt>
                                                        <dd class="mt-1 whitespace-pre-wrap break-words text-red-700">{{ row.last_error }}</dd>
                                                    </div>
                                                </dl>
                                            </section>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <div v-if="!isDataLoading && data.data?.length === 0" class="my-8 text-center">
                        <MagnifyingGlassIcon class="mx-auto h-10 w-10 text-gray-400" aria-hidden="true" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ $t('No AI Agent activity found') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ $t('Tool activity will appear here after an AI Agent uses an FS PBX tool.') }}</p>
                    </div>

                    <div v-if="isDataLoading" class="my-5 px-4" aria-live="polite">
                        <span class="sr-only">{{ $t('Loading') }}</span>
                        <div class="animate-pulse space-y-4">
                            <div v-for="index in 4" :key="index" class="h-3 rounded bg-slate-200"></div>
                        </div>
                    </div>
                </div>

                <Paginator class="border border-gray-200" :previous="data.prev_page_url" :next="data.next_page_url"
                    :from="data.from" :to="data.to" :total="data.total" :currentPage="data.current_page"
                    :lastPage="data.last_page" :links="data.links" @pagination-change-page="renderRequestedPage" />
            </div>
        </div>

        <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
            @update:show="hideNotification" />
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import moment from 'moment-timezone';
import { trans } from '@i18n';
import Paginator from '@generalComponents/Paginator.vue';
import DatePicker from '@generalComponents/DatePicker.vue';
import Badge from '@generalComponents/Badge.vue';
import Notification from './notifications/Notification.vue';
import { ClipboardDocumentIcon } from '@heroicons/vue/24/outline';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    trigger: Boolean,
    domainOptions: {
        type: Array,
        default: () => [],
    },
    selectedDomainUuid: String,
});

const isDataLoading = ref(false);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);
const expandedRow = ref(null);
const domainFilterKey = ref(0);
const data = ref({
    data: [],
    prev_page_url: null,
    next_page_url: null,
    from: 0,
    to: 0,
    total: 0,
    current_page: 1,
    last_page: 1,
    links: [],
});

const startLocal = moment.utc(props.startPeriod).tz(props.timezone);
const endLocal = moment.utc(props.endPeriod).tz(props.timezone);
const initialDateRange = () => [
    startLocal.clone().startOf('day').toISOString(),
    endLocal.clone().endOf('day').toISOString(),
];

const filterData = ref({
    search: null,
    domain_uuid: props.selectedDomainUuid,
    dateRange: initialDateRange(),
});

const showDomainFilter = computed(() => props.domainOptions.length > 1);
const showDomainColumn = computed(() => showDomainFilter.value);
const columnCount = computed(() => showDomainColumn.value ? 6 : 5);
const domainFilterOptions = computed(() => [
    { value: 'all', label: trans('All accounts') },
    ...props.domainOptions,
]);

const fetchData = async (page = 1) => {
    if (!props.routes.ai_agent_logs) return;

    isDataLoading.value = true;
    axios.get(props.routes.ai_agent_logs, {
        params: {
            filter: filterData.value,
            page,
        },
    }).then((response) => {
        data.value = response.data;
    }).catch((error) => {
        showNotification('error', error.response?.data?.errors ?? { request: [trans('Unable to load AI Agent activity.')] });
    }).finally(() => {
        isDataLoading.value = false;
    });
};

watch(() => props.trigger, () => fetchData(1));

const handleSearchButtonClick = () => fetchData(1);
const handleUpdateDateRange = (value) => { filterData.value.dateRange = value; };
const handleUpdateDomainFilter = (value) => {
    filterData.value.domain_uuid = typeof value === 'object' ? value?.value : value;
};
const handleFiltersReset = () => {
    filterData.value = {
        search: null,
        domain_uuid: props.selectedDomainUuid,
        dateRange: initialDateRange(),
    };
    domainFilterKey.value += 1;
    fetchData(1);
};
const renderRequestedPage = (url) => {
    const page = new URL(url, window.location.origin).searchParams.get('page') ?? 1;
    fetchData(page);
};
const toggleExpand = (uuid) => {
    expandedRow.value = expandedRow.value === uuid ? null : uuid;
};

const formatDate = (value) => value
    ? moment.utc(value).tz(props.timezone).format('h:mm:ss A MMM D, YYYY')
    : '';
const displayValue = (value) => value === null || value === undefined || value === '' ? trans('N/A') : value;
const hasPayload = (row) => row.request_payload && typeof row.request_payload === 'object';
const emailFields = (row) => Array.isArray(row.request_payload?.fields) ? row.request_payload.fields : [];
const prettyJson = (payload) => JSON.stringify(payload, null, 2);
const domainLabel = (row) => props.domainOptions.find((domain) => domain.value === row.domain_uuid)?.label || row.domain_uuid;
const toolLabel = (name) => name === 'fspbx_send_email' ? trans('Send email') : String(name || '').replaceAll('_', ' ');
const statusLabel = (status) => String(status || trans('Unknown')).replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());
const elapsedTime = (row) => {
    if (!row.created_at || !row.sent_at) return trans('Not completed');

    const milliseconds = Math.max(0, moment.utc(row.sent_at).diff(moment.utc(row.created_at)));
    if (milliseconds < 1000) return trans('Less than one second');

    const seconds = Math.round(milliseconds / 1000);
    return seconds === 1 ? trans('1 second') : trans(':count seconds', { count: seconds });
};
const statusBadge = (status) => {
    if (status === 'sent') {
        return { backgroundColor: 'bg-green-100', textColor: 'text-green-800', ringColor: 'ring-green-400/20' };
    }
    if (status === 'failed') {
        return { backgroundColor: 'bg-rose-100', textColor: 'text-rose-800', ringColor: 'ring-rose-400/20' };
    }
    if (status === 'queued' || status === 'sending') {
        return { backgroundColor: 'bg-blue-100', textColor: 'text-blue-800', ringColor: 'ring-blue-400/20' };
    }
    return { backgroundColor: 'bg-gray-100', textColor: 'text-gray-700', ringColor: 'ring-gray-400/20' };
};

const copyCallId = async (callId) => {
    try {
        await navigator.clipboard.writeText(callId);
        showNotification('success', { message: [trans('Call ID copied to clipboard.')] });
    } catch {
        showNotification('error', { request: [trans('Failed to copy call ID.')] });
    }
};
const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
};
const showNotification = (type, messages) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
};
</script>
