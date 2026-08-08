<template>
    <MainLayout />

    <main class="m-3 space-y-4">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold text-gray-900">
                        {{ $t('Legacy Provision Templates') }}
                    </h1>
                    <span class="rounded bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                        {{ $t('File based') }}
                    </span>
                    <span class="rounded bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20">
                        {{ $t('Being retired') }}
                    </span>
                </div>
                <p class="mt-1 max-w-3xl text-sm text-gray-500">
                    {{ $t('Edit the legacy provisioning templates stored on this server. These files are separate from database-backed provisioning templates.') }}
                </p>
            </div>

            <div v-if="template_root" class="max-w-full text-right">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ $t('Template directory') }}</p>
                <p class="max-w-xl truncate font-mono text-xs text-gray-600" :title="template_root">
                    {{ template_root }}
                </p>
            </div>
        </header>

        <!-- Dismissing only hides the notice for this page view; it is shown again on every load. -->
        <div
            v-if="noticeVisible"
            class="flex gap-3 rounded-md bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-inset ring-amber-600/20"
        >
            <ExclamationTriangleIcon class="h-5 w-5 shrink-0 text-amber-500" aria-hidden="true" />
            <div class="min-w-0 flex-1">
                <p class="font-medium">{{ $t('These templates are scheduled to be retired.') }}</p>
                <p class="mt-1">
                    {{ $t('They remain editable for devices that still rely on them, but they will be removed in a future release. Use the database-backed provisioning templates for any new work.') }}
                </p>
            </div>
            <button
                type="button"
                class="-m-1.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-amber-500 hover:bg-amber-100 hover:text-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-600"
                :aria-label="$t('Dismiss')"
                @click="noticeVisible = false"
            >
                <XMarkIcon class="h-4 w-4" />
            </button>
        </div>

        <div
            v-if="load_error"
            class="rounded-md bg-red-50 p-4 text-sm text-red-700 ring-1 ring-inset ring-red-600/20"
            role="alert"
        >
            {{ load_error }}
        </div>

        <div
            v-else
            class="grid gap-4 lg:min-h-[34rem] lg:grid-cols-[20rem_minmax(0,1fr)]"
            :class="noticeVisible ? 'lg:h-[calc(100vh-19rem)]' : 'lg:h-[calc(100vh-13rem)]'"
        >
            <aside class="flex max-h-[60vh] min-h-0 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200 lg:max-h-none">
                <div class="space-y-2 border-b border-gray-200 p-3">
                    <div class="relative">
                        <MagnifyingGlassIcon class="pointer-events-none absolute inset-y-0 left-3 my-auto h-4 w-4 text-gray-400" />
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="$t('Search by vendor, model, or file name')"
                            class="block w-full rounded-md border-0 py-1.5 pl-9 pr-9 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                        />
                        <button
                            v-if="search"
                            type="button"
                            class="absolute inset-y-0 right-2 my-auto flex h-5 w-5 items-center justify-center rounded text-gray-400 hover:text-gray-600"
                            :aria-label="$t('Clear search')"
                            @click="search = ''"
                        >
                            <XMarkIcon class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="flex items-center justify-between text-xs text-gray-500">
                        <span>{{ $t(':count files', { count: filteredFiles.length }) }}</span>
                        <button
                            v-if="filteredFiles.length"
                            type="button"
                            class="font-medium text-indigo-600 hover:text-indigo-500"
                            @click="toggleAllGroups"
                        >
                            {{ allGroupsExpanded ? $t('Collapse all') : $t('Expand all') }}
                        </button>
                    </div>
                </div>

                <nav ref="fileNav" class="min-h-0 flex-1 overflow-y-auto p-2" :aria-label="$t('Provision template files')">
                    <div v-for="vendorNode in tree" :key="vendorNode.key" class="pb-1">
                        <button
                            type="button"
                            class="sticky top-0 z-10 flex w-full items-center gap-1.5 rounded-md bg-white px-2 py-1.5 text-left hover:bg-gray-50"
                            :aria-expanded="isExpanded(vendorNode.key)"
                            @click="toggleGroup(vendorNode.key)"
                        >
                            <ChevronRightIcon
                                class="h-4 w-4 shrink-0 text-gray-400 transition-transform"
                                :class="isExpanded(vendorNode.key) ? 'rotate-90' : ''"
                            />
                            <span
                                class="min-w-0 flex-1 truncate text-sm font-semibold"
                                :class="selectedKeys.vendor === vendorNode.key ? 'text-indigo-700' : 'text-gray-900'"
                            >
                                {{ vendorNode.label }}
                            </span>
                            <span class="shrink-0 rounded-full bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-500">
                                {{ vendorNode.count }}
                            </span>
                        </button>

                        <div v-show="isExpanded(vendorNode.key)" class="mt-0.5 space-y-0.5 pl-3">
                            <div v-for="group in vendorNode.groups" :key="group.key">
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-1.5 rounded-md px-2 py-1.5 text-left hover:bg-gray-50"
                                    :aria-expanded="isExpanded(group.key)"
                                    @click="toggleGroup(group.key)"
                                >
                                    <ChevronRightIcon
                                        class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform"
                                        :class="isExpanded(group.key) ? 'rotate-90' : ''"
                                    />
                                    <FolderIcon
                                        class="h-4 w-4 shrink-0"
                                        :class="selectedKeys.group === group.key ? 'text-indigo-500' : 'text-gray-400'"
                                    />
                                    <span
                                        class="min-w-0 flex-1 truncate text-sm"
                                        :class="selectedKeys.group === group.key ? 'font-medium text-indigo-700' : 'text-gray-700'"
                                    >
                                        {{ group.label }}
                                    </span>
                                    <span class="shrink-0 text-xs text-gray-400">{{ group.files.length }}</span>
                                </button>

                                <ul v-show="isExpanded(group.key)" class="mt-0.5 space-y-0.5 pl-5">
                                    <li v-for="file in group.files" :key="file.path">
                                        <button
                                            type="button"
                                            :data-file-path="file.path"
                                            :class="fileButtonClass(file)"
                                            :aria-current="isSelected(file) ? 'true' : undefined"
                                            @click="selectFile(file)"
                                        >
                                            <DocumentTextIcon
                                                class="h-4 w-4 shrink-0"
                                                :class="isSelected(file) ? 'text-indigo-500' : 'text-gray-400'"
                                            />
                                            <span class="min-w-0 flex-1 truncate text-left text-sm">{{ file.name }}</span>
                                            <span
                                                v-if="isSelected(file) && dirty"
                                                class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"
                                                :title="$t('Unsaved changes')"
                                            />
                                            <LockClosedIcon
                                                v-else-if="!file.writable"
                                                class="h-3.5 w-3.5 shrink-0 text-gray-300"
                                                :title="$t('Read only')"
                                            />
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div v-if="!filteredFiles.length" class="px-4 py-10 text-center">
                        <FolderOpenIcon class="mx-auto h-8 w-8 text-gray-300" />
                        <p class="mt-2 text-sm font-medium text-gray-900">{{ $t('No template files found') }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $t('Try a different search.') }}</p>
                    </div>
                </nav>
            </aside>

            <section class="flex min-h-0 min-w-0 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                <template v-if="selectedFile">
                    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 px-4 py-3">
                        <div class="min-w-0">
                            <nav class="flex items-center gap-1 text-xs text-gray-500" :aria-label="$t('Breadcrumb')">
                                <template v-for="(crumb, index) in breadcrumb" :key="crumb.key">
                                    <ChevronRightIcon v-if="index > 0" class="h-3 w-3 shrink-0 text-gray-300" />
                                    <button
                                        type="button"
                                        class="max-w-[12rem] truncate hover:text-indigo-600"
                                        @click="revealGroup(crumb.keys)"
                                    >
                                        {{ crumb.label }}
                                    </button>
                                </template>
                            </nav>

                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-base font-semibold text-gray-900">{{ selectedFile.name }}</h2>
                                <span
                                    v-if="dirty"
                                    class="rounded bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20"
                                >
                                    {{ $t('Unsaved changes') }}
                                </span>
                                <span
                                    v-if="!selectedFile.writable"
                                    class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600"
                                >
                                    {{ $t('Read only') }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <select
                                v-model="editorTheme"
                                class="rounded-md border-0 py-1.5 text-sm text-gray-700 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                                :aria-label="$t('Editor theme')"
                            >
                                <option value="chrome">{{ $t('Light') }}</option>
                                <option value="one_dark">{{ $t('Dark') }}</option>
                            </select>
                            <button
                                v-if="permissions.save"
                                type="button"
                                :disabled="!dirty || saving || loadingFile || !selectedFile.writable"
                                class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="saveFile"
                            >
                                <ArrowPathIcon v-if="saving" class="h-4 w-4 animate-spin" />
                                <CheckIcon v-else class="h-4 w-4" />
                                {{ saving ? $t('Saving') : $t('Save Changes') }}
                            </button>
                        </div>
                    </header>

                    <div class="flex min-h-0 flex-1 flex-col p-3">
                        <div v-if="loadingFile" class="flex min-h-[520px] items-center justify-center text-sm text-gray-500">
                            <ArrowPathIcon class="mr-2 h-5 w-5 animate-spin" />
                            {{ $t('Loading template') }}
                        </div>
                        <AceEditor
                            v-else
                            :key="selectedFile.path"
                            v-model="content"
                            :lang="editorMode"
                            :theme="editorTheme"
                            :options="editorOptions"
                            height="100%"
                            class="min-h-[520px] flex-1 overflow-hidden rounded-md border border-gray-200"
                        />
                    </div>

                    <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 px-4 py-2 text-xs text-gray-500">
                        <span class="min-w-0 truncate font-mono" :title="selectedFile.path">{{ selectedFile.path }}</span>
                        <span class="flex shrink-0 items-center gap-3">
                            <span>{{ formatBytes(selectedFile.size) }}</span>
                            <span v-if="selectedFile.modified_at">
                                {{ $t('Last modified: :date', { date: formatDate(selectedFile.modified_at) }) }}
                            </span>
                        </span>
                    </footer>
                </template>

                <div v-else class="flex min-h-[640px] flex-1 items-center justify-center p-8 text-center">
                    <div>
                        <DocumentTextIcon class="mx-auto h-10 w-10 text-gray-300" />
                        <h2 class="mt-3 text-sm font-semibold text-gray-900">{{ $t('Select a template file') }}</h2>
                        <p class="mt-1 max-w-sm text-sm text-gray-500">
                            {{ $t('Choose a file from the list to inspect or edit its contents.') }}
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <Notification
            :show="notificationShow"
            :type="notificationType"
            :messages="notificationMessages"
            @update:show="hideNotification"
        />
    </main>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { trans } from '@i18n'
import {
    ArrowPathIcon,
    CheckIcon,
    ChevronRightIcon,
    DocumentTextIcon,
    ExclamationTriangleIcon,
    FolderIcon,
    FolderOpenIcon,
    LockClosedIcon,
    MagnifyingGlassIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline'
import MainLayout from '../Layouts/MainLayout.vue'
import AceEditor from './components/general/AceEditor.vue'
import Notification from './components/notifications/Notification.vue'

const props = defineProps({
    files: {
        type: Array,
        default: () => [],
    },
    template_root: {
        type: String,
        default: null,
    },
    load_error: {
        type: String,
        default: null,
    },
    routes: {
        type: Object,
        required: true,
    },
    permissions: {
        type: Object,
        required: true,
    },
})

const search = ref('')
const selectedFile = ref(null)
const content = ref('')
const originalContent = ref('')
const loadingFile = ref(false)
const saving = ref(false)
const editorTheme = ref('one_dark')
const noticeVisible = ref(true)
const expandedGroups = ref(new Set())
const fileNav = ref(null)
const notificationShow = ref(false)
const notificationType = ref(null)
const notificationMessages = ref(null)

let expandedBeforeSearch = null
let stopInertiaGuard = null

const filteredFiles = computed(() => {
    const needle = search.value.trim().toLocaleLowerCase()

    if (!needle) return props.files

    return props.files.filter((file) => file.path.toLocaleLowerCase().includes(needle))
})

// Files live in a vendor/model/file.ext tree, so the sidebar is grouped the same way
// instead of listing every template file at one flat level.
const tree = computed(() => {
    const vendors = new Map()

    for (const file of filteredFiles.value) {
        const segments = segmentsOf(file)
        const vendorKey = vendorKeyOf(segments)
        const groupKey = groupKeyOf(segments)

        if (!vendors.has(vendorKey)) {
            vendors.set(vendorKey, {
                key: vendorKey,
                label: segments.length ? formatVendor(segments[0]) : trans('Root'),
                count: 0,
                groups: new Map(),
            })
        }

        const vendorNode = vendors.get(vendorKey)
        vendorNode.count += 1

        if (!vendorNode.groups.has(groupKey)) {
            vendorNode.groups.set(groupKey, {
                key: groupKey,
                label: segments.slice(1).join('/') || trans('Root'),
                files: [],
            })
        }

        vendorNode.groups.get(groupKey).files.push(file)
    }

    return [...vendors.values()]
        .map((vendorNode) => ({
            ...vendorNode,
            groups: [...vendorNode.groups.values()].sort(compareByLabel),
        }))
        .sort(compareByLabel)
})

const groupKeys = computed(() => tree.value.flatMap((vendorNode) => [
    vendorNode.key,
    ...vendorNode.groups.map((group) => group.key),
]))

const allGroupsExpanded = computed(() => groupKeys.value.length > 0
    && groupKeys.value.every((key) => expandedGroups.value.has(key)))

const breadcrumb = computed(() => {
    if (!selectedFile.value) return []

    const segments = segmentsOf(selectedFile.value)

    if (!segments.length) return [{ key: 'root', label: trans('Root'), keys: [vendorKeyOf(segments)] }]

    return segments.map((segment, index) => ({
        key: segments.slice(0, index + 1).join('/'),
        label: index === 0 ? formatVendor(segment) : segment,
        keys: index === 0
            ? [vendorKeyOf(segments)]
            : [vendorKeyOf(segments), groupKeyOf(segments.slice(0, index + 1))],
    }))
})

const dirty = computed(() => selectedFile.value !== null && content.value !== originalContent.value)

const editorMode = computed(() => {
    const extension = selectedFile.value?.extension

    if (extension === 'xml') return 'xml'
    if (extension === 'yaml' || extension === 'yml') return 'yaml'
    if (extension === 'lua') return 'lua'
    if (extension === 'php') return 'php'
    if (extension === 'html' || extension === 'htm') return 'html'

    return 'text'
})

const editorOptions = computed(() => ({
    fontSize: 14,
    tabSize: 2,
    readOnly: !props.permissions.save || !selectedFile.value?.writable,
}))

const segmentsOf = (file) => (file.directory || '').split('/').filter(Boolean)

const vendorKeyOf = (segments) => `v:${segments[0] ?? ''}`

const groupKeyOf = (segments) => `g:${segments.join('/')}`

const compareByLabel = (left, right) => left.label
    .localeCompare(right.label, undefined, { numeric: true, sensitivity: 'base' })

const isSelected = (file) => selectedFile.value?.path === file.path

// Used to keep the open file visible in the tree even when its folders are collapsed.
const selectedKeys = computed(() => {
    if (!selectedFile.value) return {}

    const segments = segmentsOf(selectedFile.value)

    return { vendor: vendorKeyOf(segments), group: groupKeyOf(segments) }
})

const isExpanded = (key) => expandedGroups.value.has(key)

const toggleGroup = (key) => {
    const next = new Set(expandedGroups.value)

    if (next.has(key)) {
        next.delete(key)
    } else {
        next.add(key)
    }

    expandedGroups.value = next
}

const toggleAllGroups = () => {
    expandedGroups.value = allGroupsExpanded.value ? new Set() : new Set(groupKeys.value)
}

const expandForFile = (file) => {
    if (!file) return

    const segments = segmentsOf(file)
    const next = new Set(expandedGroups.value)

    next.add(vendorKeyOf(segments))
    next.add(groupKeyOf(segments))
    expandedGroups.value = next
}

const revealSelectedFile = async () => {
    if (!selectedFile.value) return

    await nextTick()

    const buttons = fileNav.value?.querySelectorAll('[data-file-path]') ?? []

    for (const button of buttons) {
        if (button.dataset.filePath === selectedFile.value.path) {
            button.scrollIntoView({ block: 'nearest' })

            return
        }
    }
}

const revealGroup = async (keys) => {
    if (search.value) {
        // Let the search watcher restore the manual expand state before adding to it.
        search.value = ''
        await nextTick()
    }

    const next = new Set(expandedGroups.value)

    keys.forEach((key) => next.add(key))
    expandedGroups.value = next
    revealSelectedFile()
}

const selectFile = async (file) => {
    if (selectedFile.value?.path === file.path) return

    if (dirty.value && !window.confirm(trans('Discard your unsaved changes?'))) {
        return
    }

    loadingFile.value = true
    selectedFile.value = file
    content.value = ''
    originalContent.value = ''
    expandForFile(file)
    syncUrl(file.path)
    revealSelectedFile()

    try {
        const response = await axios.get(props.routes.show, {
            params: { path: file.path },
        })
        selectedFile.value = response.data.file
        content.value = response.data.file.content
        originalContent.value = response.data.file.content
    } catch (error) {
        selectedFile.value = null
        syncUrl(null)
        showError(error)
    } finally {
        loadingFile.value = false
    }
}

const saveFile = async () => {
    if (!dirty.value || !selectedFile.value || saving.value) return

    saving.value = true

    try {
        const response = await axios.put(props.routes.update, {
            path: selectedFile.value.path,
            content: content.value,
        })
        selectedFile.value = {
            ...selectedFile.value,
            ...response.data.file,
        }
        originalContent.value = content.value
        showNotification('success', response.data.messages)
    } catch (error) {
        showError(error)
    } finally {
        saving.value = false
    }
}

// Keeps the open file in the address bar so it can be bookmarked and shared,
// without pushing a history entry for every file that gets opened.
const syncUrl = (path) => {
    const url = new URL(window.location.href)

    if (path) {
        url.searchParams.set('path', path)
    } else {
        url.searchParams.delete('path')
    }

    window.history.replaceState(window.history.state, '', url)
}

const fileButtonClass = (file) => [
    isSelected(file)
        ? 'bg-indigo-50 text-indigo-700'
        : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900',
    'flex w-full items-center gap-2 rounded-md px-2 py-1.5',
]

const formatVendor = (value) => String(value)
    .replaceAll('_', ' ')
    .replaceAll('-', ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase())

const formatBytes = (bytes) => {
    if (bytes < 1024) return trans(':count bytes', { count: bytes })
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`

    return `${(bytes / 1048576).toFixed(1)} MB`
}

const formatDate = (value) => new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
}).format(new Date(value))

const showError = (error) => {
    showNotification(
        'error',
        error?.response?.data?.messages
            ?? error?.response?.data?.errors
            ?? { request: [trans('An unexpected error occurred.')] },
    )
}

const showNotification = (type, messages) => {
    notificationType.value = type
    notificationMessages.value = messages
    notificationShow.value = true
}

const hideNotification = () => {
    notificationShow.value = false
}

const handleBeforeUnload = (event) => {
    if (!dirty.value) return

    event.preventDefault()
    event.returnValue = ''
}

const handleKeyboardSave = (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLocaleLowerCase() === 's') {
        event.preventDefault()
        saveFile()
    }
}

// While searching every matching folder is opened, and the manual expand/collapse
// state is restored once the search is cleared.
watch(search, (value, previous) => {
    const searching = value.trim() !== ''

    if (searching) {
        if (previous.trim() === '') {
            expandedBeforeSearch = new Set(expandedGroups.value)
        }

        expandedGroups.value = new Set(groupKeys.value)

        return
    }

    expandedGroups.value = expandedBeforeSearch ?? new Set()
    expandedBeforeSearch = null
    expandForFile(selectedFile.value)
    revealSelectedFile()
})

onMounted(() => {
    const requestedPath = new URLSearchParams(window.location.search).get('path')
    const requestedFile = requestedPath
        ? props.files.find((file) => file.path === requestedPath)
        : null

    if (requestedFile) {
        selectFile(requestedFile)
    } else if (tree.value.length === 1) {
        expandedGroups.value = new Set([tree.value[0].key])
    }

    stopInertiaGuard = router.on('before', () => {
        if (!dirty.value) return

        return window.confirm(trans('Discard your unsaved changes?'))
    })

    window.addEventListener('beforeunload', handleBeforeUnload)
    window.addEventListener('keydown', handleKeyboardSave)
})

onBeforeUnmount(() => {
    stopInertiaGuard?.()
    window.removeEventListener('beforeunload', handleBeforeUnload)
    window.removeEventListener('keydown', handleKeyboardSave)
})
</script>
