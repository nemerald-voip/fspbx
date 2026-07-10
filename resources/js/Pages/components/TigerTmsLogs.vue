<template>
    <div class="mt-4 flex flex-col">
        <div class="flex flex-col sm:flex-row sm:flex-wrap">
            <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <MagnifyingGlassIcon class="h-5 w-5 text-subtle" aria-hidden="true" />
                </div>
                <input
                    type="search"
                    v-model="filterData.search"
                    class="block w-full rounded-md border-0 py-1.5 pl-10 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:hidden"
                    placeholder="Search"
                    @keydown.enter="handleSearchButtonClick"
                />
                <input
                    type="search"
                    v-model="filterData.search"
                    class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:block"
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

            <div v-if="showDomainFilter" class="relative z-[1] min-w-72 -mt-0.5 mb-2 shrink-0 sm:mr-4">
                <Vueform :key="domainFilterKey" :display-errors="false" size="sm">
                    <SelectElement
                        name="domain_uuid"
                        :default="filterData.domain_uuid"
                        :items="domainFilterOptions"
                        :native="false"
                        :search="true"
                        input-type="search"
                        autocomplete="off"
                        :strict="false"
                        :floating="false"
                        @change="handleUpdateDomainFilter"
                    />
                </Vueform>
            </div>

            <div class="relative">
                <div class="flex justify-between">
                    <button
                        type="button"
                        @click.prevent="handleSearchButtonClick"
                        class="rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    >
                        Search
                    </button>

                    <button
                        type="button"
                        @click.prevent="handleFiltersReset"
                        class="rounded-md bg-surface px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <Paginator class="border border-default" :previous="data.prev_page_url" :next="data.next_page_url"
                    :from="data.from" :to="data.to" :total="data.total" :currentPage="data.current_page"
                    :lastPage="data.last_page" :links="data.links" @pagination-change-page="renderRequestedPage" />

                <div class="overflow-hidden-t border-l border-r border-default">
                    <table class="min-w-full divide-y divide-default mb-4">
                        <thead class="bg-surface-3">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Date</th>
                                <th v-if="showDomainColumn" class="px-6 py-3 text-left text-sm font-semibold text-heading">Domain</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Method</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Endpoint</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Result</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Details</th>
                            </tr>
                        </thead>

                        <tbody v-if="!isDataLoading && data.data?.length" class="divide-y divide-default bg-surface">
                            <template v-for="row in data.data" :key="row.uuid">
                                <tr @click="toggleExpand(row.uuid)" class="hover:bg-surface-2 cursor-pointer">
                                    <td class="whitespace-nowrap px-6 py-2 text-sm font-medium text-muted">
                                        {{ row.created_at_formatted ?? row.created_at }}
                                    </td>
                                    <td v-if="showDomainColumn" class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        {{ domainLabel(row) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        {{ row.method }}
                                    </td>
                                    <td class="max-w-sm truncate whitespace-nowrap px-6 py-2 text-sm text-muted" :title="row.endpoint">
                                        {{ row.endpoint }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        <Badge
                                            :text="resultLabel(row)"
                                            :backgroundColor="resultBadge(row).background"
                                            :textColor="resultBadge(row).text"
                                            :ringColor="resultBadge(row).ring"
                                            class="px-2 py-1 text-xs"
                                        />
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-info">
                                        <span class="underline">Click for details…</span>
                                    </td>
                                </tr>

                                <tr v-if="expandedRow === row.uuid">
                                    <td :colspan="columnCount" class="bg-surface-2 px-6 py-4">
                                        <div class="grid gap-3 text-sm sm:grid-cols-2">
                                            <div>
                                                <div class="text-xs font-medium uppercase tracking-wide text-subtle">ID</div>
                                                <div class="mt-1 font-mono text-xs text-body">{{ row.uuid }}</div>
                                            </div>
                                            <div>
                                                <div class="text-xs font-medium uppercase tracking-wide text-subtle">URL</div>
                                                <div class="mt-1 break-all text-body">{{ row.url }}</div>
                                            </div>
                                            <div>
                                                <div class="text-xs font-medium uppercase tracking-wide text-subtle">HTTP Status</div>
                                                <div class="mt-1 text-body">{{ row.response_status || 'No response' }}</div>
                                            </div>
                                            <div>
                                                <div class="text-xs font-medium uppercase tracking-wide text-subtle">Duration</div>
                                                <div class="mt-1 text-body">{{ row.duration_ms !== null ? `${row.duration_ms} ms` : '' }}</div>
                                            </div>
                                            <div v-if="row.error" class="sm:col-span-2">
                                                <div class="text-xs font-medium uppercase tracking-wide text-subtle">Error</div>
                                                <div class="mt-1 whitespace-pre-wrap break-words text-danger">{{ row.error }}</div>
                                            </div>
                                        </div>

                                        <details open class="mt-4">
                                            <summary class="cursor-pointer text-sm font-medium text-body">Context</summary>
                                            <pre class="mt-2 max-h-60 overflow-auto rounded-md bg-gray-900 p-3 text-xs text-gray-100">{{ prettyJson(row.request_context) }}</pre>
                                        </details>

                                        <details class="mt-3">
                                            <summary class="cursor-pointer text-sm font-medium text-body">Request</summary>
                                            <pre class="mt-2 max-h-96 overflow-auto rounded-md bg-gray-900 p-3 text-xs text-gray-100">{{ prettyJson(row.request_payload) }}</pre>
                                        </details>

                                        <details class="mt-3">
                                            <summary class="cursor-pointer text-sm font-medium text-body">Response</summary>
                                            <pre class="mt-2 max-h-80 overflow-auto rounded-md bg-gray-900 p-3 text-xs text-gray-100">{{ prettyJson(row.response_body) }}</pre>
                                        </details>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <div v-if="!isDataLoading && data.data?.length === 0" class="text-center my-5">
                        <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-subtle" />
                        <h3 class="mt-2 text-sm font-semibold text-heading">No results found</h3>
                    </div>

                    <div v-if="isDataLoading" class="text-center my-5 text-sm text-muted">
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

                <Paginator class="border border-default" :previous="data.prev_page_url" :next="data.next_page_url"
                    :from="data.from" :to="data.to" :total="data.total" :currentPage="data.current_page"
                    :lastPage="data.last_page" :links="data.links" @pagination-change-page="renderRequestedPage" />
            </div>
        </div>
    </div>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import moment from 'moment-timezone';
import Paginator from '@generalComponents/Paginator.vue';
import DatePicker from '@generalComponents/DatePicker.vue';
import Badge from '@generalComponents/Badge.vue';
import Notification from './notifications/Notification.vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/solid';

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

const isDataLoading = ref(false);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
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
    { value: 'all', label: 'All domains' },
    ...props.domainOptions,
]);

const fetchData = async (page = 1) => {
    if (!props.routes.tigertms_logs) return;

    isDataLoading.value = true;
    axios.get(props.routes.tigertms_logs, {
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

const handleSearchButtonClick = () => {
    fetchData(1);
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    filterData.value.domain_uuid = props.selectedDomainUuid;
    filterData.value.dateRange = initialDateRange();
    domainFilterKey.value += 1;
    handleSearchButtonClick();
};

const handleUpdateDateRange = (newDateRange) => {
    filterData.value.dateRange = newDateRange;
};

const handleUpdateDomainFilter = (newValue) => {
    filterData.value.domain_uuid = typeof newValue === 'object'
        ? (newValue?.value ?? null)
        : newValue;
};

const renderRequestedPage = (url) => {
    isDataLoading.value = true;
    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get('page') ?? 1;
    fetchData(pageParam);
};

const toggleExpand = (uuid) => {
    expandedRow.value = expandedRow.value === uuid ? null : uuid;
};

const resultLabel = (row) => {
    if (row.error) return 'Error';
    if (row.response_status >= 200 && row.response_status < 300) return String(row.response_status);
    if (row.response_status) return String(row.response_status);

    return 'No response';
};

const resultBadge = (row) => {
    if (row.error || !row.response_status || row.response_status >= 400) {
        return { background: 'bg-danger-subtle', text: 'text-danger', ring: 'ring-danger/20' };
    }

    if (row.response_status >= 300) {
        return { background: 'bg-warning-subtle', text: 'text-warning', ring: 'ring-warning/20' };
    }

    return { background: 'bg-success-subtle', text: 'text-success', ring: 'ring-success/20' };
};

const domainLabel = (row) => {
    if (!row.domain_uuid) return '';

    const option = props.domainOptions.find((domain) => domain.value === row.domain_uuid);
    return option?.label || row.domain_uuid;
};

const prettyJson = (value) => {
    if (!value) return '';
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
