<template>
    <div class="mt-4 flex flex-col">
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
            <div class="relative lg:col-span-3">
                <input
                    v-model="filterData.seed_uuid"
                    type="search"
                    class="block w-full rounded-md border-0 py-2 pl-3 pr-3 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                    placeholder="Call UUID or SIP Call-ID"
                    @keydown.enter="handleSearchButtonClick"
                />
            </div>

            <div class="relative lg:col-span-3">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                </div>
                <input
                    v-model="filterData.search"
                    type="search"
                    class="block w-full rounded-md border-0 py-2 pl-10 pr-3 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                    placeholder="Contains"
                    @keydown.enter="handleSearchButtonClick"
                />
            </div>

            <div class="relative lg:col-span-2">
                <select
                    v-model="filterData.log_file"
                    class="block w-full rounded-md border-0 py-2 pl-3 pr-10 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                >
                    <option v-for="file in fileOptions" :key="file.value" :value="file.value">
                        {{ file.label }}
                    </option>
                </select>
            </div>

            <div class="relative lg:col-span-2">
                <select
                    v-model="filterData.level"
                    class="block w-full rounded-md border-0 py-2 pl-3 pr-10 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                >
                    <option value="all">All levels</option>
                    <option value="debug">Debug</option>
                    <option value="info">Info</option>
                    <option value="notice">Notice</option>
                    <option value="warning">Warning</option>
                    <option value="err">Error</option>
                    <option value="crit">Critical</option>
                    <option value="alert">Alert</option>
                </select>
            </div>

            <div class="flex gap-2 lg:col-span-2">
                <button
                    type="button"
                    @click.prevent="handleSearchButtonClick"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                >
                    Search
                </button>

                <button
                    type="button"
                    @click.prevent="handleFiltersReset"
                    class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                >
                    Reset
                </button>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input
                    v-model="filterData.whole_call"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                />
                Whole call
            </label>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <span>Padding</span>
                <select
                    v-model.number="filterData.correlation_padding_minutes"
                    :disabled="!filterData.whole_call"
                    class="block rounded-md border-0 py-1.5 pl-3 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 disabled:bg-gray-100 disabled:text-gray-400"
                >
                    <option :value="1">1 min</option>
                    <option :value="5">5 min</option>
                    <option :value="15">15 min</option>
                    <option :value="30">30 min</option>
                    <option :value="60">60 min</option>
                </select>
            </label>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <span>Read</span>
                <input
                    v-model.number="filterData.size_kb"
                    type="number"
                    min="1"
                    max="10240"
                    class="block w-24 rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                />
                <span>KB</span>
            </label>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <span>Rows</span>
                <input
                    v-model.number="filterData.max_lines"
                    type="number"
                    min="1"
                    max="5000"
                    class="block w-24 rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                />
            </label>

            <select
                v-model="filterData.sort"
                class="block rounded-md border-0 py-1.5 pl-3 pr-10 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600"
            >
                <option value="asc">Oldest first</option>
                <option value="desc">Newest first</option>
            </select>
        </div>

        <div v-if="meta.errors?.length" class="mt-4 rounded-md bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-inset ring-rose-200">
            <div v-for="error in meta.errors" :key="error">{{ error }}</div>
        </div>

        <div v-if="hasCorrelation" class="mt-4 rounded-md bg-white p-4 ring-1 ring-gray-200">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-900">Included identifiers</p>
                    <p v-if="correlation.time_window" class="mt-1 text-sm text-gray-500">
                        {{ formatWindow(correlation.time_window) }}
                    </p>
                </div>
                <div class="text-sm text-gray-500">
                    {{ correlation.cdrs?.length || 0 }} related CDR{{ (correlation.cdrs?.length || 0) === 1 ? '' : 's' }}
                </div>
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="id in correlationIdentifiers"
                    :key="id"
                    type="button"
                    @click="copyToClipboard(id)"
                    class="max-w-full truncate rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-200 hover:bg-gray-200"
                >
                    {{ id }}
                </button>
            </div>
        </div>

        <div class="mt-4 overflow-hidden rounded-md bg-white ring-1 ring-gray-200">
            <div class="flex flex-col gap-2 border-b border-gray-200 px-4 py-3 text-sm text-gray-500 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <span class="font-medium text-gray-900">{{ meta.matched_lines || 0 }}</span>
                    matched line{{ (meta.matched_lines || 0) === 1 ? '' : 's' }}
                    <span v-if="meta.truncated_matches">, showing latest {{ filterData.max_lines }}</span>
                </div>
                <div>
                    {{ formatBytes(meta.bytes_read || 0) }} read
                    <span v-if="meta.log_dir"> from {{ meta.log_dir }}</span>
                </div>
            </div>

            <div v-if="isDataLoading" class="p-6">
                <div class="animate-pulse space-y-3">
                    <div class="h-2 rounded bg-slate-200"></div>
                    <div class="h-2 rounded bg-slate-200"></div>
                    <div class="h-2 rounded bg-slate-200"></div>
                    <div class="h-2 rounded bg-slate-200"></div>
                </div>
            </div>

            <div v-else-if="lines.length === 0" class="p-8 text-center">
                <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
            </div>

            <div v-else class="max-h-[68vh] overflow-auto bg-gray-950">
                <div
                    v-for="(line, index) in lines"
                    :key="`${line.file}-${line.line_number}-${index}`"
                    :class="lineTextClass(line.level)"
                    class="grid grid-cols-[10rem_5.5rem_minmax(40rem,1fr)] gap-3 border-b border-white/5 px-4 py-2 font-mono text-xs leading-5"
                >
                    <div class="truncate opacity-70">{{ line.file }}</div>
                    <div>
                        <span :class="levelBadgeClass(line.level)" class="rounded px-1.5 py-0.5 uppercase">
                            {{ line.level || 'log' }}
                        </span>
                    </div>
                    <pre class="whitespace-pre-wrap break-words text-inherit">{{ line.message }}</pre>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    routes: Object,
    trigger: Boolean,
})

const isDataLoading = ref(false)
const hasLoaded = ref(false)
const lines = ref([])
const fileOptions = ref([{ value: 'freeswitch.log', label: 'freeswitch.log' }])
const correlation = ref({
    seed: null,
    whole_call: true,
    uuids: [],
    sip_call_ids: [],
    cdrs: [],
    time_window: null,
})
const meta = ref({
    bytes_read: 0,
    matched_lines: 0,
    truncated_matches: false,
    errors: [],
})

const filterData = ref({
    seed_uuid: '',
    whole_call: true,
    search: '',
    log_file: 'freeswitch.log',
    level: 'all',
    size_kb: 512,
    max_lines: 1000,
    sort: 'asc',
    correlation_padding_minutes: 5,
})

const hasCorrelation = computed(() => Boolean(
    correlation.value.seed
    || correlation.value.uuids?.length
    || correlation.value.sip_call_ids?.length
))

const correlationIdentifiers = computed(() => [
    ...(correlation.value.uuids || []),
    ...(correlation.value.sip_call_ids || []),
])

watch(() => props.trigger, () => {
    fetchData()
})

const fetchData = async () => {
    isDataLoading.value = true

    try {
        const response = await axios.get(props.routes.freeswitch_logs, {
            params: filterData.value,
        })

        lines.value = response.data.lines || []
        fileOptions.value = response.data.files?.length ? response.data.files : fileOptions.value
        correlation.value = response.data.correlation || correlation.value
        meta.value = response.data.meta || meta.value

        if (!fileOptions.value.some((file) => file.value === filterData.value.log_file)) {
            filterData.value.log_file = fileOptions.value[0]?.value || 'freeswitch.log'
        }
    } catch (error) {
        meta.value = {
            ...meta.value,
            errors: Object.values(error.response?.data?.messages || error.response?.data?.errors || {})
                .flat()
                .filter(Boolean),
        }
    } finally {
        hasLoaded.value = true
        isDataLoading.value = false
    }
}

const handleSearchButtonClick = () => {
    fetchData()
}

const handleFiltersReset = () => {
    filterData.value = {
        seed_uuid: '',
        whole_call: true,
        search: '',
        log_file: 'freeswitch.log',
        level: 'all',
        size_kb: 512,
        max_lines: 1000,
        sort: 'asc',
        correlation_padding_minutes: 5,
    }
    fetchData()
}

const lineTextClass = (level) => {
    const classes = {
        debug: 'text-sky-400/75',
        info: 'text-emerald-300/80',
        notice: 'text-cyan-300/80',
        warning: 'text-amber-300',
        err: 'text-red-300',
        crit: 'text-red-200',
        alert: 'text-red-200',
    }

    return classes[level] || 'text-gray-300'
}

const levelBadgeClass = (level) => {
    const classes = {
        debug: 'bg-sky-900/30 text-sky-300/80',
        info: 'bg-emerald-900/30 text-emerald-300/80',
        notice: 'bg-cyan-900/30 text-cyan-300/80',
        warning: 'bg-amber-900/35 text-amber-200',
        err: 'bg-red-900/40 text-red-200',
        crit: 'bg-red-900/50 text-red-100',
        alert: 'bg-red-900/50 text-red-100',
    }

    return classes[level] || 'bg-gray-800 text-gray-300'
}

const formatBytes = (bytes) => {
    const value = Number(bytes || 0)
    if (value < 1024) return `${value} B`
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`
    return `${(value / 1024 / 1024).toFixed(1)} MB`
}

const formatWindow = (window) => {
    if (!window?.start || !window?.end) return ''
    return `${window.start} to ${window.end}`
}

const copyToClipboard = async (value) => {
    if (!value || !navigator?.clipboard) return
    await navigator.clipboard.writeText(value)
}
</script>
