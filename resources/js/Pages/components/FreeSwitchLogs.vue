<template>
    <div class="mt-4 flex flex-col">
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
            <div class="relative lg:col-span-3">
                <input
                    v-model="filterData.seed_uuid"
                    type="search"
                    class="block w-full rounded-md border-0 py-2 pl-3 pr-3 text-sm text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus"
                    placeholder="Call UUID or SIP Call-ID"
                    @keydown.enter="handleSearchButtonClick"
                />
            </div>

            <div class="relative lg:col-span-3">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <MagnifyingGlassIcon class="h-5 w-5 text-subtle" aria-hidden="true" />
                </div>
                <input
                    v-model="filterData.search"
                    type="search"
                    class="block w-full rounded-md border-0 py-2 pl-10 pr-3 text-sm text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus"
                    placeholder="Contains"
                    @keydown.enter="handleSearchButtonClick"
                />
            </div>

            <div class="relative lg:col-span-2">
                <select
                    v-model="filterData.log_file"
                    class="block w-full rounded-md border-0 py-2 pl-3 pr-10 text-sm text-heading ring-1 ring-inset ring-strong focus:ring-2 focus:ring-inset focus:ring-focus"
                >
                    <option v-for="file in fileOptions" :key="file.value" :value="file.value">
                        {{ file.label }}
                    </option>
                </select>
            </div>

            <div class="relative lg:col-span-2">
                <select
                    v-model="filterData.level"
                    class="block w-full rounded-md border-0 py-2 pl-3 pr-10 text-sm text-heading ring-1 ring-inset ring-strong focus:ring-2 focus:ring-inset focus:ring-focus"
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
                    class="inline-flex items-center rounded-md bg-accent px-3 py-2 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                >
                    Search
                </button>

                <button
                    type="button"
                    @click.prevent="handleFiltersReset"
                    class="inline-flex items-center rounded-md bg-surface px-3 py-2 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2"
                >
                    Reset
                </button>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center gap-2 text-sm text-body">
                <input
                    v-model="filterData.whole_call"
                    type="checkbox"
                    class="h-4 w-4 rounded border-strong text-accent-fg focus:ring-focus"
                />
                Whole call
            </label>

            <label class="inline-flex items-center gap-2 text-sm text-body">
                <span>Padding</span>
                <select
                    v-model.number="filterData.correlation_padding_minutes"
                    :disabled="!filterData.whole_call"
                    class="block rounded-md border-0 py-1.5 pl-3 pr-8 text-sm text-heading ring-1 ring-inset ring-strong focus:ring-2 focus:ring-inset focus:ring-focus disabled:bg-surface-3 disabled:text-subtle"
                >
                    <option :value="1">1 min</option>
                    <option :value="5">5 min</option>
                    <option :value="15">15 min</option>
                    <option :value="30">30 min</option>
                    <option :value="60">60 min</option>
                </select>
            </label>

            <label class="inline-flex items-center gap-2 text-sm text-body">
                <span>Read</span>
                <input
                    v-model.number="filterData.size_kb"
                    type="number"
                    min="1"
                    max="10240"
                    class="block w-24 rounded-md border-0 py-1.5 text-sm text-heading ring-1 ring-inset ring-strong focus:ring-2 focus:ring-inset focus:ring-focus"
                />
                <span>KB</span>
            </label>

            <label class="inline-flex items-center gap-2 text-sm text-body">
                <span>Rows</span>
                <input
                    v-model.number="filterData.max_lines"
                    type="number"
                    min="1"
                    max="5000"
                    class="block w-24 rounded-md border-0 py-1.5 text-sm text-heading ring-1 ring-inset ring-strong focus:ring-2 focus:ring-inset focus:ring-focus"
                />
            </label>

            <select
                v-model="filterData.sort"
                class="block rounded-md border-0 py-1.5 pl-3 pr-10 text-sm text-heading ring-1 ring-inset ring-strong focus:ring-2 focus:ring-inset focus:ring-focus"
            >
                <option value="asc">Oldest first</option>
                <option value="desc">Newest first</option>
            </select>

            <div v-if="permissions?.log_view && routes?.freeswitch_sip_trace" class="ml-auto flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-body">SIP packets</span>
                <button
                    type="button"
                    :disabled="isSipTraceLoading"
                    @click="setSipTrace(true)"
                    class="inline-flex items-center gap-1.5 rounded-md bg-surface px-2.5 py-1.5 text-xs font-semibold text-body shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-50"
                    :class="{ 'bg-accent-subtle text-accent-fg ring-accent': sipTraceEnabled === true }"
                >
                    <SignalIcon class="h-4 w-4" aria-hidden="true" />
                    Enable
                </button>
                <button
                    type="button"
                    :disabled="isSipTraceLoading"
                    @click="setSipTrace(false)"
                    class="inline-flex items-center gap-1.5 rounded-md bg-surface px-2.5 py-1.5 text-xs font-semibold text-body shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-50"
                    :class="{ 'bg-surface-3 text-heading ring-strong': sipTraceEnabled === false }"
                >
                    <NoSymbolIcon class="h-4 w-4" aria-hidden="true" />
                    Disable
                </button>
            </div>
        </div>

        <div v-if="meta.errors?.length" class="mt-4 rounded-md bg-danger-subtle p-4 text-sm text-danger ring-1 ring-inset ring-danger">
            <div v-for="error in meta.errors" :key="error">{{ error }}</div>
        </div>

        <div v-if="hasCorrelation" class="mt-4 rounded-md bg-surface p-4 ring-1 ring-strong">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-semibold text-heading">Included identifiers</p>
                    <p v-if="correlation.time_window" class="mt-1 text-sm text-muted">
                        {{ formatWindow(correlation.time_window) }}
                    </p>
                </div>
                <div class="text-sm text-muted">
                    {{ correlation.cdrs?.length || 0 }} related CDR{{ (correlation.cdrs?.length || 0) === 1 ? '' : 's' }}
                </div>
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="id in correlationIdentifiers"
                    :key="id"
                    type="button"
                    @click="copyToClipboard(id)"
                    class="max-w-full truncate rounded-md bg-surface-3 px-2.5 py-1 text-xs font-medium text-body ring-1 ring-inset ring-strong hover:bg-surface-3"
                >
                    {{ id }}
                </button>
            </div>
        </div>

        <div class="mt-4 overflow-hidden rounded-md bg-surface ring-1 ring-strong">
            <div class="flex flex-col gap-3 border-b border-default px-4 py-3 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <span class="font-medium text-heading">{{ meta.matched_lines || 0 }}</span>
                    matched line{{ (meta.matched_lines || 0) === 1 ? '' : 's' }}
                    <span v-if="meta.truncated_matches">, showing latest {{ filterData.max_lines }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div>
                        {{ formatBytes(meta.bytes_read || 0) }} read
                        <span v-if="meta.log_dir"> from {{ meta.log_dir }}</span>
                    </div>
                    <button
                        type="button"
                        :disabled="isDataLoading || lines.length === 0"
                        title="Copy shown log"
                        @click="copyVisibleLog"
                        class="inline-flex items-center gap-1.5 rounded-md bg-surface px-2.5 py-1.5 text-xs font-semibold text-body shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <ClipboardDocumentIcon class="h-4 w-4" aria-hidden="true" />
                        {{ copiedLog ? 'Copied' : 'Copy shown log' }}
                    </button>
                </div>
            </div>

            <div v-if="isDataLoading" class="p-6">
                <div class="animate-pulse space-y-3">
                    <div class="h-2 rounded bg-surface-3"></div>
                    <div class="h-2 rounded bg-surface-3"></div>
                    <div class="h-2 rounded bg-surface-3"></div>
                    <div class="h-2 rounded bg-surface-3"></div>
                </div>
            </div>

            <div v-else-if="lines.length === 0" class="p-8 text-center">
                <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-subtle" />
                <h3 class="mt-2 text-sm font-semibold text-heading">No results found</h3>
            </div>

            <div v-else class="max-h-[68vh] overflow-auto bg-gray-950">
                <div
                    v-for="(line, index) in lines"
                    :key="`${line.file}-${line.line_number}-${index}`"
                    :class="lineTextClass(line.level)"
                    class="grid grid-cols-[5.5rem_minmax(40rem,1fr)] gap-3 border-b border-white/5 px-4 py-2 font-mono text-xs leading-5"
                >
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
import { ClipboardDocumentIcon, NoSymbolIcon, SignalIcon } from '@heroicons/vue/24/outline';

const emit = defineEmits(['success', 'error'])

const props = defineProps({
    routes: Object,
    trigger: Boolean,
    permissions: Object,
})

const isDataLoading = ref(false)
const isSipTraceLoading = ref(false)
const hasLoaded = ref(false)
const lines = ref([])
const copiedLog = ref(false)
const sipTraceEnabled = ref(null)
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
    size_kb: 5120,
    max_lines: 3000,
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
    copiedLog.value = false

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
        size_kb: 5120,
        max_lines: 3000,
        sort: 'asc',
        correlation_padding_minutes: 5,
    }
    fetchData()
}

const setSipTrace = async (enabled) => {
    if (isSipTraceLoading.value || !props.routes?.freeswitch_sip_trace) return

    isSipTraceLoading.value = true

    try {
        const response = await axios.post(props.routes.freeswitch_sip_trace, { enabled })
        sipTraceEnabled.value = response.data.enabled ?? enabled
        emit('success', response.data.messages || { success: [enabled ? 'SIP packet logging enabled.' : 'SIP packet logging disabled.'] })
    } catch (error) {
        emit('error', error.response?.data?.messages || error.response?.data?.errors || { error: ['Unable to update SIP packet logging.'] })
    } finally {
        isSipTraceLoading.value = false
    }
}

const lineTextClass = (level) => {
    const classes = {
        debug: 'text-info/75',
        info: 'text-success/80',
        notice: 'text-info/80',
        warning: 'text-warning',
        err: 'text-danger',
        crit: 'text-danger',
        alert: 'text-danger',
    }

    return classes[level] || 'text-subtle'
}

const levelBadgeClass = (level) => {
    const classes = {
        debug: 'bg-info/30 text-info/80',
        info: 'bg-success/30 text-success/80',
        notice: 'bg-info/30 text-info/80',
        warning: 'bg-warning/35 text-warning',
        err: 'bg-danger/40 text-danger',
        crit: 'bg-danger/50 text-danger',
        alert: 'bg-danger/50 text-danger',
    }

    return classes[level] || 'bg-gray-800 text-subtle'
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
    if (!value) return
    await writeClipboardText(value)
}

const copyVisibleLog = async () => {
    if (!lines.value.length) return

    try {
        await writeClipboardText(lines.value.map((line) => line.message || '').join('\n'))
        copiedLog.value = true
        window.setTimeout(() => {
            copiedLog.value = false
        }, 1500)
    } catch (error) {
        copiedLog.value = false
        console.error('Failed to copy FreeSWITCH log:', error)
    }
}

const writeClipboardText = async (text) => {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text)
        return
    }

    const textarea = document.createElement('textarea')
    textarea.value = text
    textarea.setAttribute('readonly', '')
    textarea.style.position = 'fixed'
    textarea.style.left = '-9999px'
    document.body.appendChild(textarea)
    textarea.select()

    const copied = document.execCommand('copy')
    document.body.removeChild(textarea)

    if (!copied) throw new Error('Copy failed')
}
</script>
