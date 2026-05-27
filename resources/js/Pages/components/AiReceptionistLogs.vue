<template>
    <div class="mt-4 flex flex-col">
        <div class="flex flex-col sm:flex-row sm:flex-wrap">
            <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                </div>
                <input
                    type="search"
                    v-model="filterData.search"
                    class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                    placeholder="Search"
                    @keydown.enter="handleSearchButtonClick"
                />
                <input
                    type="search"
                    v-model="filterData.search"
                    class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                    placeholder="Search"
                    @keydown.enter="handleSearchButtonClick"
                />
            </div>

            <div class="relative z-10 min-w-64 -mt-0.5 mb-2 scale-y-95 shrink-0 sm:mr-4">
                <DatePicker
                    :dateRange="filterData.dateRange"
                    :timezone="timezone"
                    @update:date-range="handleUpdateDateRange"
                />
            </div>

            <div v-if="showDomainFilter" class="relative min-w-56 mb-2 shrink-0 sm:mr-4">
                <select
                    v-model="filterData.domain_uuid"
                    class="block w-full rounded-md border-0 py-2 pl-3 pr-10 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                >
                    <option v-for="domain in domainFilterOptions" :key="domain.value" :value="domain.value">
                        {{ domain.label }}
                    </option>
                </select>
            </div>

            <div class="relative min-w-44 mb-2 shrink-0 sm:mr-4">
                <select
                    v-model="filterData.status"
                    class="block w-full rounded-md border-0 py-2 pl-3 pr-10 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                >
                    <option value="all">All statuses</option>
                    <option value="started">Started</option>
                    <option value="warm_transfer_dialing">Warm Transfer Dialing</option>
                    <option value="warm_transfer_consulting">Warm Transfer Consulting</option>
                    <option value="transferred">Transferred</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                </select>
            </div>

            <div class="relative">
                <div class="flex justify-between">
                    <button
                        type="button"
                        @click.prevent="handleSearchButtonClick"
                        class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                    >
                        Search
                    </button>

                    <button
                        type="button"
                        @click.prevent="handleFiltersReset"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <Paginator
                    class="border border-gray-200"
                    :previous="data.prev_page_url"
                    :next="data.next_page_url"
                    :from="data.from"
                    :to="data.to"
                    :total="data.total"
                    :currentPage="data.current_page"
                    :lastPage="data.last_page"
                    :links="data.links"
                    @pagination-change-page="renderRequestedPage"
                />

                <div class="overflow-hidden-t border-l border-r border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 mb-4">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Date</th>
                                <th v-if="showDomainColumn" class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Domain</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Receptionist</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Caller</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Destination</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Status</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Activity</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Tools</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Duration</th>
                            </tr>
                        </thead>

                        <tbody v-if="!isDataLoading && data.data?.length" class="divide-y divide-gray-200 bg-white">
                            <template v-for="row in data.data" :key="row.session_uuid">
                                <tr @click="toggleExpand(row.session_uuid)" class="hover:bg-gray-50 cursor-pointer">
                                    <td class="whitespace-nowrap px-6 py-2 text-sm font-medium text-gray-500">
                                        {{ row.started_at_formatted || '' }}
                                    </td>
                                    <td v-if="showDomainColumn" class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        {{ domainLabel(row) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        {{ receptionistLabel(row) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        <div>{{ row.caller_id_name || '-' }}</div>
                                        <div class="text-xs text-gray-400">{{ row.caller_id_number || '' }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        {{ row.destination_number || '-' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        <Badge
                                            :text="statusText(row.status)"
                                            :backgroundColor="statusBadge(row.status).backgroundColor"
                                            :textColor="statusBadge(row.status).textColor"
                                            :ringColor="statusBadge(row.status).ringColor"
                                        />
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        <div>{{ row.activity_summary?.label || row.transfer_label || row.transfer_target || '-' }}</div>
                                        <div v-if="row.activity_summary?.detail || row.latest_warm_transfer_status" class="text-xs text-gray-400">
                                            {{ row.activity_summary?.detail || `warm: ${statusText(row.latest_warm_transfer_status)}` }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        <span>{{ row.tool_runs_count }}</span>
                                        <span v-if="row.failed_tool_runs_count" class="ml-1 text-rose-600">
                                            ({{ row.failed_tool_runs_count }} failed)
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                        {{ row.duration || '-' }}
                                    </td>
                                </tr>

                                <tr v-if="expandedRow === row.session_uuid">
                                    <td :colspan="columnCount" class="bg-gray-50 px-6 py-4">
                                        <div class="grid gap-4 lg:grid-cols-2">
                                            <div>
                                                <h5 class="text-sm font-semibold text-gray-900">Session</h5>
                                                <dl class="mt-2 space-y-1 text-sm">
                                                    <div class="flex gap-2">
                                                        <dt class="w-36 shrink-0 text-gray-500">Session UUID</dt>
                                                        <dd class="break-all text-gray-700">{{ row.session_uuid }}</dd>
                                                    </div>
                                                    <div class="flex gap-2">
                                                        <dt class="w-36 shrink-0 text-gray-500">FreeSWITCH UUID</dt>
                                                        <dd class="break-all text-gray-700">{{ row.freeswitch_uuid || '-' }}</dd>
                                                    </div>
                                                    <div class="flex gap-2">
                                                        <dt class="w-36 shrink-0 text-gray-500">OpenAI Call ID</dt>
                                                        <dd class="break-all text-gray-700">{{ row.openai_call_id || '-' }}</dd>
                                                    </div>
                                                    <div class="flex gap-2">
                                                        <dt class="w-36 shrink-0 text-gray-500">SIP Call ID</dt>
                                                        <dd class="break-all text-gray-700">{{ row.sip_call_id || '-' }}</dd>
                                                    </div>
                                                    <div v-if="row.error_message" class="flex gap-2">
                                                        <dt class="w-36 shrink-0 text-gray-500">Error</dt>
                                                        <dd class="break-all text-rose-700">{{ row.error_message }}</dd>
                                                    </div>
                                                </dl>
                                            </div>

                                            <div>
                                                <h5 class="text-sm font-semibold text-gray-900">Activity</h5>
                                                <div v-if="row.activities?.length" class="mt-2 space-y-3">
                                                    <div v-for="activity in row.activities" :key="activity.tool_run_uuid" class="border-t border-gray-200 pt-2 first:border-t-0 first:pt-0">
                                                        <div class="flex flex-wrap items-center gap-2 text-sm">
                                                            <Badge
                                                                :text="statusText(activity.status)"
                                                                :backgroundColor="statusBadge(activity.status).backgroundColor"
                                                                :textColor="statusBadge(activity.status).textColor"
                                                                :ringColor="statusBadge(activity.status).ringColor"
                                                            />
                                                            <span class="font-medium text-gray-700">{{ activity.label }}</span>
                                                            <span v-if="activity.detail" class="text-gray-600">{{ activity.detail }}</span>
                                                            <span class="text-gray-400">{{ activity.started_at_formatted }}</span>
                                                        </div>
                                                        <div v-if="activity.error_message" class="mt-1 text-sm text-rose-700">{{ activity.error_message }}</div>
                                                    </div>
                                                </div>
                                                <p v-else class="mt-2 text-sm text-gray-500">No activity recorded.</p>
                                            </div>
                                        </div>

                                        <div v-if="row.warm_transfers?.length" class="mt-4">
                                            <div>
                                                <h5 class="text-sm font-semibold text-gray-900">Warm Transfers</h5>
                                                <div class="mt-2 space-y-3">
                                                    <div v-for="transfer in row.warm_transfers" :key="transfer.warm_transfer_uuid" class="border-t border-gray-200 pt-2 first:border-t-0 first:pt-0">
                                                        <div class="flex flex-wrap items-center gap-2 text-sm">
                                                            <Badge
                                                                :text="statusText(transfer.status)"
                                                                :backgroundColor="statusBadge(transfer.status).backgroundColor"
                                                                :textColor="statusBadge(transfer.status).textColor"
                                                                :ringColor="statusBadge(transfer.status).ringColor"
                                                            />
                                                            <span class="text-gray-700">{{ transfer.destination_label || transfer.destination_target }}</span>
                                                            <span class="text-gray-400">{{ transfer.started_at_formatted }}</span>
                                                        </div>
                                                        <div class="mt-1 text-sm text-gray-600">{{ transfer.handoff_summary }}</div>
                                                        <dl class="mt-2 space-y-1 text-xs text-gray-500">
                                                            <div class="flex gap-2">
                                                                <dt class="w-28 shrink-0">Caller UUID</dt>
                                                                <dd class="break-all">{{ transfer.caller_uuid || '-' }}</dd>
                                                            </div>
                                                            <div class="flex gap-2">
                                                                <dt class="w-28 shrink-0">OpenAI UUID</dt>
                                                                <dd class="break-all">{{ transfer.openai_uuid || '-' }}</dd>
                                                            </div>
                                                            <div class="flex gap-2">
                                                                <dt class="w-28 shrink-0">Recipient UUID</dt>
                                                                <dd class="break-all">{{ transfer.recipient_uuid || '-' }}</dd>
                                                            </div>
                                                        </dl>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4">
                                            <h5 class="text-sm font-semibold text-gray-900">Tool Runs</h5>
                                            <div v-if="row.tool_runs?.length" class="mt-2 overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200 border border-gray-200 bg-white">
                                                    <thead class="bg-gray-100">
                                                        <tr>
                                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Time</th>
                                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Tool</th>
                                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Status</th>
                                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Duration</th>
                                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Request</th>
                                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Response</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-200">
                                                        <tr v-for="toolRun in row.tool_runs" :key="toolRun.tool_run_uuid">
                                                            <td class="whitespace-nowrap px-3 py-2 text-xs text-gray-500">{{ toolRun.started_at_formatted || '' }}</td>
                                                            <td class="whitespace-nowrap px-3 py-2 text-xs text-gray-700">{{ toolRun.tool_name }}</td>
                                                            <td class="whitespace-nowrap px-3 py-2 text-xs text-gray-500">
                                                                <Badge
                                                                    :text="statusText(toolRun.status)"
                                                                    :backgroundColor="statusBadge(toolRun.status).backgroundColor"
                                                                    :textColor="statusBadge(toolRun.status).textColor"
                                                                    :ringColor="statusBadge(toolRun.status).ringColor"
                                                                />
                                                            </td>
                                                            <td class="whitespace-nowrap px-3 py-2 text-xs text-gray-500">{{ toolRun.duration || '-' }}</td>
                                                            <td class="px-3 py-2 text-xs text-gray-500">
                                                                <pre class="max-w-sm whitespace-pre-wrap break-words">{{ formatJson(toolRun.request_payload) }}</pre>
                                                            </td>
                                                            <td class="px-3 py-2 text-xs text-gray-500">
                                                                <pre class="max-w-sm whitespace-pre-wrap break-words">{{ toolRun.error_message || formatJson(toolRun.response_payload) }}</pre>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <p v-else class="mt-2 text-sm text-gray-500">No tool runs.</p>
                                        </div>

                                        <div v-if="row.transcript" class="mt-4">
                                            <h5 class="text-sm font-semibold text-gray-900">Transcript</h5>
                                            <pre class="mt-2 whitespace-pre-wrap break-words text-sm text-gray-600">{{ row.transcript }}</pre>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <div v-if="!isDataLoading && data.data?.length === 0" class="text-center my-5">
                        <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                    </div>

                    <div v-if="isDataLoading" class="text-center my-5 text-sm text-gray-500">
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

        <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
            @update:show="hideNotification" />
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import moment from 'moment-timezone';
import DatePicker from "@generalComponents/DatePicker.vue";
import Paginator from "@generalComponents/Paginator.vue";
import Badge from "@generalComponents/Badge.vue";
import Notification from "./notifications/Notification.vue";
import { MagnifyingGlassIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    permissions: Object,
    trigger: Boolean,
    domainOptions: {
        type: Array,
        default: () => [],
    },
    selectedDomainUuid: String,
});

const startLocal = moment.utc(props.startPeriod).tz(props.timezone);
const endLocal = moment.utc(props.endPeriod).tz(props.timezone);

const filterData = ref({
    search: null,
    status: 'all',
    domain_uuid: props.selectedDomainUuid,
    dateRange: [
        startLocal.clone().startOf('day').toISOString(),
        endLocal.clone().endOf('day').toISOString(),
    ],
});

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
const isDataLoading = ref(false);
const expandedRow = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);

const showDomainFilter = computed(() => props.domainOptions.length > 1);
const showDomainColumn = computed(() => showDomainFilter.value);
const domainFilterOptions = computed(() => [
    { value: 'all', label: 'All domains' },
    ...props.domainOptions,
]);
const columnCount = computed(() => showDomainColumn.value ? 9 : 8);

const fetchData = async (page = 1) => {
    isDataLoading.value = true;
    axios.get(props.routes.ai_receptionist_logs, {
        params: {
            filter: filterData.value,
            page,
        },
    })
        .then((response) => {
            data.value = response.data;
        })
        .catch((error) => {
            handleErrorResponse(error);
        })
        .finally(() => {
            isDataLoading.value = false;
        });
};

watch(() => props.trigger, () => {
    fetchData(1);
});

const toggleExpand = (uuid) => {
    expandedRow.value = expandedRow.value === uuid ? null : uuid;
};

const handleUpdateDateRange = (newDateRange) => {
    filterData.value.dateRange = newDateRange;
};

const renderRequestedPage = (url) => {
    const urlObj = new URL(url, window.location.origin);
    fetchData(urlObj.searchParams.get("page") ?? 1);
};

const handleSearchButtonClick = () => {
    fetchData(1);
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    filterData.value.status = 'all';
    filterData.value.domain_uuid = props.selectedDomainUuid;
    handleSearchButtonClick();
};

const domainLabel = (row) => {
    return row.domain?.domain_description || row.domain?.domain_name || '';
};

const receptionistLabel = (row) => {
    if (!row.receptionist) return '-';
    return row.receptionist.extension
        ? `${row.receptionist.name} (${row.receptionist.extension})`
        : row.receptionist.name;
};

const statusText = (status) => {
    return String(status || '-').replaceAll('_', ' ');
};

const statusBadge = (status) => {
    const normalized = String(status || '').toLowerCase();
    if (['completed', 'sent', 'transferred'].includes(normalized)) {
        return { backgroundColor: 'bg-green-100', textColor: 'text-green-800', ringColor: 'ring-green-400/20' };
    }
    if (['failed', 'no_answer', 'unavailable', 'declined', 'cancelled'].includes(normalized)) {
        return { backgroundColor: 'bg-rose-100', textColor: 'text-rose-800', ringColor: 'ring-rose-400/20' };
    }
    if (normalized.includes('warm_transfer')) {
        return { backgroundColor: 'bg-blue-100', textColor: 'text-blue-800', ringColor: 'ring-blue-400/20' };
    }
    return { backgroundColor: 'bg-gray-100', textColor: 'text-gray-800', ringColor: 'ring-gray-400/20' };
};

const formatJson = (value) => {
    if (value === null || value === undefined || value === '') return '';
    return JSON.stringify(value, null, 2);
};

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
};

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
};

const handleErrorResponse = (error) => {
    if (error.response) {
        showNotification('error', error.response.data.errors || { request: [error.message] });
    } else if (error.request) {
        showNotification('error', { request: [error.request] });
    } else {
        showNotification('error', { request: [error.message] });
    }
};
</script>
