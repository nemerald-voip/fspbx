<template>
    <div class="mt-4 flex flex-col">
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
            <div class="relative lg:col-span-4">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                </div>
                <input
                    v-model="filterData.search"
                    type="search"
                    class="block w-full rounded-md border-0 py-2 pl-10 pr-3 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                    placeholder="Search Laravel logs"
                    @keydown.enter="handleSearchButtonClick"
                />
            </div>

            <div class="relative lg:col-span-3">
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

            <div class="flex flex-wrap gap-2 lg:col-span-3">
                <button
                    type="button"
                    @click.prevent="handleSearchButtonClick"
                    class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isDataLoading"
                >
                    <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true" />
                    Search
                </button>

                <button
                    type="button"
                    @click.prevent="handleRefreshButtonClick"
                    class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isDataLoading"
                >
                    <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': isDataLoading }" aria-hidden="true" />
                    Refresh
                </button>

                <button
                    type="button"
                    @click.prevent="handleFiltersReset"
                    class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isDataLoading"
                >
                    Reset
                </button>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input
                    v-model="isLiveTailEnabled"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                />
                <span>Live tail</span>
                <span v-if="isLiveTailEnabled" class="text-xs text-gray-500">3s</span>
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

        <div class="mt-4 overflow-hidden rounded-md bg-white ring-1 ring-gray-200">
            <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-3 text-sm text-gray-500 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <template v-if="isLiveTailEnabled">
                        <span class="font-medium text-gray-900">{{ lines.length }}</span>
                        shown line{{ lines.length === 1 ? '' : 's' }}
                        <span v-if="meta.matched_lines">, latest read matched {{ meta.matched_lines }}</span>
                    </template>
                    <template v-else>
                        <span class="font-medium text-gray-900">{{ meta.matched_lines || 0 }}</span>
                        matched line{{ (meta.matched_lines || 0) === 1 ? '' : 's' }}
                        <span v-if="meta.truncated_matches">, showing latest {{ filterData.max_lines }}</span>
                    </template>
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
                        class="inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <ClipboardDocumentIcon class="h-4 w-4" aria-hidden="true" />
                        {{ copiedLog ? 'Copied' : 'Copy shown log' }}
                    </button>
                </div>
            </div>

            <div v-if="isDataLoading && lines.length === 0" class="p-6">
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

            <div v-else ref="logContainer" class="h-[68vh] overflow-auto bg-gray-950">
                <div
                    v-for="(line, index) in lines"
                    :key="`${line.file}-${line.byte_offset ?? line.line_number}-${index}`"
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
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/solid';
import { ArrowPathIcon, ClipboardDocumentIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    routes: Object,
    trigger: Boolean,
    permissions: Object,
})

const isDataLoading = ref(false)
const isLiveTailRefreshing = ref(false)
const isLiveTailEnabled = ref(false)
const lines = ref([])
const copiedLog = ref(false)
const liveTailTimer = ref(null)
const logContainer = ref(null)
const fileOptions = ref([{ value: 'laravel.log', label: 'laravel.log' }])
const meta = ref({
    bytes_read: 0,
    matched_lines: 0,
    truncated_matches: false,
    errors: [],
})

const defaultFilters = () => ({
    search: '',
    log_file: 'laravel.log',
    level: 'all',
    size_kb: 5120,
    max_lines: 200,
    sort: 'asc',
})

const LIVE_TAIL_INTERVAL_MS = 3000
const MAX_LIVE_TAIL_LINES = 200
const filterData = ref(defaultFilters())
const isRequestPending = computed(() => isDataLoading.value || isLiveTailRefreshing.value)

watch(() => props.trigger, () => {
    fetchData({ stickToBottom: isLiveTailEnabled.value })
})

watch(isLiveTailEnabled, (enabled) => {
    if (enabled) {
        startLiveTail()
        return
    }

    stopLiveTail()
})

onUnmounted(() => {
    stopLiveTail()
})

const fetchData = async ({
    stickToBottom = false,
    preserveCopiedState = false,
    appendLines = false,
    showLoading = true,
} = {}) => {
    if (!props.routes?.laravel_logs) return
    if (isRequestPending.value) return

    if (showLoading) {
        isDataLoading.value = true
    } else {
        isLiveTailRefreshing.value = true
    }

    if (!preserveCopiedState) {
        copiedLog.value = false
    }

    try {
        const response = await axios.get(props.routes.laravel_logs, {
            params: filterData.value,
        })

        const responseLines = response.data.lines || []
        lines.value = appendLines
            ? mergeLines(lines.value, responseLines)
            : responseLines
        fileOptions.value = response.data.files?.length ? response.data.files : fileOptions.value
        meta.value = response.data.meta || meta.value

        if (!fileOptions.value.some((file) => file.value === filterData.value.log_file)) {
            filterData.value.log_file = fileOptions.value[0]?.value || 'laravel.log'
        }

        if (stickToBottom) {
            await nextTick()
            scrollToBottom()
        }
    } catch (error) {
        meta.value = {
            ...meta.value,
            errors: Object.values(error.response?.data?.messages || error.response?.data?.errors || {})
                .flat()
                .filter(Boolean),
        }
    } finally {
        isDataLoading.value = false
        isLiveTailRefreshing.value = false
    }
}

const handleSearchButtonClick = () => {
    fetchData({ stickToBottom: isLiveTailEnabled.value })
}

const handleRefreshButtonClick = () => {
    fetchData({ stickToBottom: isLiveTailEnabled.value })
}

const handleFiltersReset = () => {
    filterData.value = defaultFilters()
    fetchData({ stickToBottom: isLiveTailEnabled.value })
}

const startLiveTail = () => {
    if (liveTailTimer.value) return

    filterData.value.sort = 'asc'
    fetchData({ stickToBottom: true, preserveCopiedState: true, appendLines: true, showLoading: false })

    liveTailTimer.value = window.setInterval(() => {
        fetchData({ stickToBottom: true, preserveCopiedState: true, appendLines: true, showLoading: false })
    }, LIVE_TAIL_INTERVAL_MS)
}

const stopLiveTail = () => {
    if (!liveTailTimer.value) return

    window.clearInterval(liveTailTimer.value)
    liveTailTimer.value = null
}

const scrollToBottom = () => {
    if (!logContainer.value) return

    logContainer.value.scrollTop = logContainer.value.scrollHeight
}

const mergeLines = (currentLines, nextLines) => {
    const seen = new Set(currentLines.map((line) => logLineKey(line)))
    const merged = [...currentLines]

    nextLines.forEach((line) => {
        const key = logLineKey(line)

        if (seen.has(key)) return

        seen.add(key)
        merged.push(line)
    })

    return merged.slice(-MAX_LIVE_TAIL_LINES)
}

const logLineKey = (line) => [
    line.file || '',
    line.byte_offset ?? line.line_number ?? '',
    line.message || '',
].join(':')

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
        console.error('Failed to copy Laravel log:', error)
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
