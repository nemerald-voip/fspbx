<template>
    <MainLayout />

    <div class="m-3 space-y-4">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-accent-fg">Switch configuration</p>
                <h1 class="mt-1 text-2xl font-semibold text-heading">Switch Variables</h1>
                <p class="mt-1 text-sm text-muted">Manage FreeSWITCH preprocessor variables.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button v-if="permissions.update" type="button" class="inline-flex items-center gap-1.5 rounded-md bg-surface px-3 py-1.5 text-sm font-medium text-body shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2" @click="syncVariables">
                    <ArrowPathIcon class="h-4 w-4" /> Sync XML
                </button>
                <button v-if="permissions.create" type="button" class="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover" @click="openEditor()">
                    <PlusIcon class="h-4 w-4" /> New variable
                </button>
            </div>
        </header>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatTile label="Total variables" :value="stats.total" tone="gray" />
            <StatTile label="Host scoped" :value="stats.hostScoped" tone="amber" />
            <StatTile label="Enabled" :value="stats.enabled" tone="green" />
            <StatTile label="Disabled" :value="stats.disabled" tone="rose" />
        </div>

        <div class="flex flex-col gap-4 lg:flex-row">
            <aside class="lg:w-72 lg:shrink-0">
                <div class="rounded-lg bg-surface p-3 shadow-sm ring-1 ring-strong">
                    <div class="relative mb-3">
                        <MagnifyingGlassIcon class="pointer-events-none absolute inset-y-0 left-3 my-auto h-4 w-4 text-subtle" />
                        <input v-model="filterData.search" type="text" placeholder="Search variables..." class="block w-full rounded-md border-0 py-1.5 pl-9 text-sm text-heading ring-1 ring-inset ring-strong focus:ring-2 focus:ring-inset focus:ring-focus" />
                    </div>

                    <div class="mb-3 space-y-2">
                        <div>
                            <label class="block text-xs font-medium text-muted">Status</label>
                            <select v-model="filterData.enabled" class="mt-1 block w-full rounded-md border-0 py-1.5 pl-2 pr-8 text-sm text-heading ring-1 ring-inset ring-strong">
                                <option value="all">Any status</option>
                                <option value="true">Enabled</option>
                                <option value="false">Disabled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted">Command</label>
                            <select v-model="filterData.command" class="mt-1 block w-full rounded-md border-0 py-1.5 pl-2 pr-8 text-sm text-heading ring-1 ring-inset ring-strong">
                                <option value="all">Any command</option>
                                <option value="set">Set</option>
                                <option value="exec-set">Exec Set</option>
                            </select>
                        </div>
                    </div>

                    <p class="px-1 pb-1 text-xs font-medium uppercase tracking-wider text-subtle">Categories</p>
                    <nav class="max-h-[60vh] space-y-0.5 overflow-y-auto">
                        <button type="button" :class="categoryButtonClass('')" @click="selectedCategory = ''">
                            <span class="min-w-0 flex-1 truncate">All</span>
                            <span :class="categoryBadgeClass('')">{{ filteredRows.length }}</span>
                        </button>
                        <button v-for="cat in categoriesWithCounts" :key="cat.value" type="button" :class="categoryButtonClass(cat.value)" @click="selectedCategory = cat.value">
                            <span class="min-w-0 flex-1 truncate">{{ cat.label }}</span>
                            <span :class="categoryBadgeClass(cat.value)">{{ cat.count }}</span>
                        </button>
                        <p v-if="!categoriesWithCounts.length" class="px-3 py-2 text-xs text-subtle">No matching categories</p>
                    </nav>
                </div>
            </aside>

            <section class="min-w-0 flex-1">
                <div class="rounded-lg bg-surface shadow-sm ring-1 ring-strong">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-default px-4 py-3">
                        <div>
                            <h2 class="text-base font-semibold text-heading">{{ selectedCategoryLabel }}</h2>
                            <p class="text-xs text-muted">{{ displayedRows.length }} variable{{ displayedRows.length === 1 ? '' : 's' }} shown</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button v-if="displayedRows.length" type="button" class="text-xs text-muted hover:text-heading" @click="toggleSelectAllVisible">
                                {{ allVisibleSelected ? 'Clear selection' : 'Select visible' }}
                            </button>
                            <div v-if="selectedItems.length" class="flex items-center gap-1 rounded-md bg-accent-subtle px-2 py-1 text-xs font-medium text-accent-fg">
                                <span>{{ selectedItems.length }} selected</span>
                                <button v-if="permissions.update" type="button" class="rounded px-1.5 py-0.5 hover:bg-accent-subtle" @click="handleBulkActionRequest('bulk_toggle')">Toggle</button>
                                <button v-if="permissions.create" type="button" class="rounded px-1.5 py-0.5 hover:bg-accent-subtle" @click="handleBulkActionRequest('bulk_copy')">Copy</button>
                                <button v-if="permissions.destroy" type="button" class="rounded px-1.5 py-0.5 hover:bg-accent-subtle" @click="handleBulkActionRequest('bulk_delete')">Delete</button>
                            </div>
                        </div>
                    </header>

                    <div v-if="loading" class="px-4 py-12">
                        <Loading :show="true" :absolute="false" />
                    </div>

                    <ul v-else-if="displayedRows.length" class="divide-y divide-default">
                        <li v-for="row in displayedRows" :key="row.var_uuid" class="relative flex items-start gap-3 px-4 py-3 transition hover:bg-surface-2">
                            <input v-model="selectedItems" type="checkbox" :value="row.var_uuid" class="mt-1 h-4 w-4 rounded border-strong text-accent-fg focus:ring-focus" />

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <h3 class="text-sm font-semibold text-heading">{{ row.name || '-' }}</h3>
                                    <button type="button" class="inline-flex items-center rounded-md bg-accent-subtle px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-accent-fg ring-1 ring-inset ring-accent/20 hover:bg-accent-subtle" :title="`Filter by ${row.category_label}`" @click="selectedCategory = row.category">{{ row.category_label }}</button>
                                    <span class="text-xs text-subtle">/</span>
                                    <span class="text-xs text-muted">{{ row.command_label }}</span>
                                    <span v-if="row.hostname" class="rounded bg-surface-3 px-1.5 py-0.5 font-mono text-[11px] text-body">{{ row.hostname }}</span>
                                </div>
                                <p v-if="row.description" class="mt-1 text-xs text-muted">{{ row.description }}</p>

                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <div class="grid min-w-0 max-w-full grid-cols-[auto,minmax(0,1fr)] items-center gap-1.5">
                                        <span class="shrink-0 text-xs text-subtle">Value</span>
                                        <button type="button" class="min-w-0 max-w-full text-left" :title="valueTitle(row.value, row.is_secret)" aria-label="Copy value" @click.stop="copyValue(row.value)">
                                            <code class="block max-w-full truncate rounded bg-surface-3 px-2 py-0.5 font-mono text-xs text-heading ring-1 ring-transparent transition hover:bg-surface-3 hover:ring-strong">{{ truncatedValue(row.value, row.is_secret) }}</code>
                                        </button>
                                    </div>
                                    <span class="text-xs text-subtle">Order {{ row.order ?? 0 }}</span>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <button v-if="permissions.update" type="button" :class="statusClass(row)" :title="row.enabled ? 'Disable variable' : 'Enable variable'" @click="toggleStatus(row)">
                                    <span :class="['mr-1 inline-block h-1.5 w-1.5 rounded-full', row.enabled ? 'bg-success' : 'bg-danger']" />
                                    {{ row.enabled ? 'Enabled' : 'Disabled' }}
                                </button>
                                <span v-else :class="statusClass(row)">
                                    <span :class="['mr-1 inline-block h-1.5 w-1.5 rounded-full', row.enabled ? 'bg-success' : 'bg-danger']" />
                                    {{ row.enabled ? 'Enabled' : 'Disabled' }}
                                </span>
                                <button v-if="permissions.update" type="button" class="rounded-md px-2 py-1 text-xs font-medium text-accent-fg hover:bg-accent-subtle" @click="openEditor(row)">Edit</button>
                                <button v-if="permissions.destroy" type="button" class="rounded-md px-2 py-1 text-xs font-medium text-danger hover:bg-danger-subtle" @click="confirmDelete([row.var_uuid])">Delete</button>
                            </div>
                        </li>
                    </ul>

                    <div v-else class="px-4 py-12 text-center">
                        <p class="text-sm font-medium text-heading">No variables match your filters</p>
                        <p class="mt-1 text-xs text-muted">Try clearing search, status, or command filters.</p>
                        <button type="button" class="mt-3 rounded-md bg-surface px-3 py-1.5 text-xs font-medium text-body shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2" @click="handleFiltersReset">Reset filters</button>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <SwitchVariableEditModal :show="showEditor" :item="editorItem" :commands="options.commands" :categories="variableCategories" :route="editorRoute"
        :loading="editorLoading" @close="showEditor = false" @success="handleModalSuccess" @error="handleErrorResponse" />

    <ConfirmationModal :show="showConfirmModal" :header="confirmHeader" :text="confirmText" :confirm-button-label="confirmButtonLabel" cancel-button-label="Cancel" @close="showConfirmModal = false" @confirm="executeConfirmedAction" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages" @update:show="notificationShow = false" />
</template>

<script setup>
import { computed, h, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import MainLayout from '../Layouts/MainLayout.vue'
import Loading from './components/general/Loading.vue'
import Notification from './components/notifications/Notification.vue'
import ConfirmationModal from './components/modal/ConfirmationModal.vue'
import SwitchVariableEditModal from './components/modal/SwitchVariableEditModal.vue'
import { ArrowPathIcon, MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/24/outline'

const StatTile = (props) => {
    const toneMap = {
        gray: 'text-heading',
        amber: 'text-warning',
        green: 'text-success',
        rose: 'text-danger',
    }
    return h('div', { class: 'rounded-lg bg-surface p-3 shadow-sm ring-1 ring-strong' }, [
        h('p', { class: 'text-xs text-muted' }, props.label),
        h('p', { class: ['mt-0.5 text-xl font-semibold', toneMap[props.tone] || toneMap.gray] }, String(props.value ?? 0)),
    ])
}
StatTile.props = ['label', 'value', 'tone']

const props = defineProps({
    routes: Object,
    permissions: Object,
    options: Object,
})

const allRows = ref([])
const loading = ref(false)
const selectedItems = ref([])
const showEditor = ref(false)
const editorLoading = ref(false)
const editorItem = ref({})
const editorRoute = ref(props.routes.store)
const showConfirmModal = ref(false)
const confirmedAction = ref(null)
const confirmHeader = ref('Confirm Action')
const confirmText = ref('')
const confirmButtonLabel = ref('Continue')
const notificationShow = ref(false)
const notificationType = ref(null)
const notificationMessages = ref(null)

const filterData = ref({ search: '', enabled: 'all', command: 'all' })
const selectedCategory = ref('')
const variableCategories = ref(props.options?.categories || [])

const categoryLabelMap = computed(() => {
    const map = {}
    for (const cat of variableCategories.value || []) map[cat.value] = cat.label
    return map
})

const stats = computed(() => {
    const total = allRows.value.length
    const hostScoped = allRows.value.filter(r => r.hostname).length
    const enabled = allRows.value.filter(r => r.enabled).length
    const disabled = total - enabled
    return { total, hostScoped, enabled, disabled }
})

const filteredRows = computed(() => {
    const search = filterData.value.search.trim().toLowerCase()
    return allRows.value.filter(row => {
        if (filterData.value.enabled !== 'all') {
            const wantEnabled = filterData.value.enabled === 'true'
            if (Boolean(row.enabled) !== wantEnabled) return false
        }
        if (filterData.value.command !== 'all' && row.command !== filterData.value.command) return false
        if (search) {
            const hay = [row.category, row.name, row.value, row.command, row.hostname, row.description]
                .filter(v => v !== null && v !== undefined)
                .join(' ')
                .toLowerCase()
            if (!hay.includes(search)) return false
        }
        return true
    })
})

const categoriesWithCounts = computed(() => {
    const counts = new Map()
    for (const row of filteredRows.value) {
        const key = row.category || ''
        counts.set(key, (counts.get(key) || 0) + 1)
    }
    return Array.from(counts.entries())
        .map(([value, count]) => ({ value, label: categoryLabelMap.value[value] || formatLabel(value) || 'Uncategorized', count }))
        .sort((a, b) => a.label.localeCompare(b.label))
})

const displayedRows = computed(() => {
    const rows = selectedCategory.value
        ? filteredRows.value.filter(row => row.category === selectedCategory.value)
        : filteredRows.value
    return [...rows].sort((a, b) => {
        const ca = String(a.category || '').toLowerCase()
        const cb = String(b.category || '').toLowerCase()
        if (ca !== cb) return ca.localeCompare(cb)
        const oa = Number(a.order ?? 0)
        const ob = Number(b.order ?? 0)
        if (oa !== ob) return oa - ob
        const na = String(a.name || '').toLowerCase()
        const nb = String(b.name || '').toLowerCase()
        return na.localeCompare(nb)
    })
})

const selectedCategoryLabel = computed(() => {
    if (!selectedCategory.value) return 'All variables'
    return categoryLabelMap.value[selectedCategory.value] || formatLabel(selectedCategory.value)
})

const allVisibleSelected = computed(() => {
    if (!displayedRows.value.length) return false
    return displayedRows.value.every(row => selectedItems.value.includes(row.var_uuid))
})

watch([selectedCategory, () => filterData.value.search, () => filterData.value.enabled, () => filterData.value.command], () => {
    selectedItems.value = []
})

watch(categoriesWithCounts, (cats) => {
    if (!selectedCategory.value) return
    if (!cats.find(c => c.value === selectedCategory.value)) selectedCategory.value = ''
})

onMounted(() => getData())

const getData = () => {
    loading.value = true
    axios.get(props.routes.data_route, { params: { per_page: 5000, page: 1 } })
        .then(response => {
            allRows.value = response.data?.data || []
            selectedItems.value = []
        })
        .catch(handleErrorResponse)
        .finally(() => loading.value = false)
}

const handleFiltersReset = () => {
    filterData.value = { search: '', enabled: 'all', command: 'all' }
    selectedCategory.value = ''
}

const toggleSelectAllVisible = () => {
    const ids = displayedRows.value.map(r => r.var_uuid)
    if (allVisibleSelected.value) {
        selectedItems.value = selectedItems.value.filter(uuid => !ids.includes(uuid))
    } else {
        const set = new Set(selectedItems.value)
        ids.forEach(uuid => set.add(uuid))
        selectedItems.value = Array.from(set)
    }
}

const openEditor = (row = null) => {
    editorLoading.value = true
    showEditor.value = true
    axios.post(props.routes.item_options, row ? { itemUuid: row.var_uuid } : {})
        .then(response => {
            variableCategories.value = response.data.categories || variableCategories.value
            editorItem.value = response.data.item
            editorRoute.value = response.data.item.var_uuid
                ? props.routes.update.replace('__VARIABLE__', response.data.item.var_uuid)
                : props.routes.store
        })
        .catch(error => {
            showEditor.value = false
            handleErrorResponse(error)
        })
        .finally(() => editorLoading.value = false)
}

const confirmDelete = (items = selectedItems.value) => {
    confirmHeader.value = 'Confirm Deletion'
    confirmText.value = 'Selected variables will be permanently deleted.'
    confirmButtonLabel.value = 'Delete'
    confirmedAction.value = () => axios.post(props.routes.bulk_delete, { items }).then(response => {
        showNotification('success', response.data.messages)
        getData()
    })
    showConfirmModal.value = true
}

const confirmCopy = () => {
    confirmHeader.value = 'Confirm Copy'
    confirmText.value = 'Selected variables will be copied.'
    confirmButtonLabel.value = 'Copy'
    confirmedAction.value = () => axios.post(props.routes.bulk_copy, { items: selectedItems.value }).then(response => {
        showNotification('success', response.data.messages)
        getData()
    })
    showConfirmModal.value = true
}

const executeConfirmedAction = () => {
    if (!confirmedAction.value) return
    confirmedAction.value().catch(handleErrorResponse).finally(() => showConfirmModal.value = false)
}

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') confirmDelete()
    if (action === 'bulk_copy') confirmCopy()
    if (action === 'bulk_toggle') {
        axios.post(props.routes.bulk_toggle, { items: selectedItems.value })
            .then(response => {
                showNotification('success', response.data.messages)
                getData()
            })
            .catch(handleErrorResponse)
    }
}

const toggleStatus = (row) => {
    axios.post(props.routes.bulk_toggle, { items: [row.var_uuid] })
        .then(response => {
            showNotification('success', response.data.messages)
            getData()
        })
        .catch(handleErrorResponse)
}

const syncVariables = () => {
    axios.post(props.routes.sync)
        .then(response => showNotification('success', response.data.messages))
        .catch(handleErrorResponse)
}

const handleModalSuccess = (messages) => {
    showNotification('success', messages)
    getData()
}

const showNotification = (type, messages) => {
    notificationType.value = type
    notificationMessages.value = messages
    notificationShow.value = true
}

const handleErrorResponse = (error) => {
    showNotification('error', error?.response?.data?.messages || error?.response?.data?.errors || { error: ['Request failed.'] })
}

const copyValue = async (value) => {
    try {
        await writeClipboardText(value === null || value === undefined ? '' : String(value))
        showNotification('success', { success: ['Value copied.'] })
    } catch (error) {
        showNotification('error', { error: ['Unable to copy value.'] })
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

const VALUE_TRUNCATE_AT = 160

const displayValue = (value, secret = false) => {
    if (value === null || value === undefined || value === '') return '-'
    return secret ? '********' : String(value)
}

const fullValue = (value, secret = false) => {
    if (secret) return '********'
    if (value === null || value === undefined || value === '') return ''
    return String(value)
}

const truncatedValue = (value, secret = false) => {
    const text = displayValue(value, secret)
    if (text.length > VALUE_TRUNCATE_AT) return text.slice(0, VALUE_TRUNCATE_AT) + '...'
    return text
}

const valueTitle = (value, secret = false) => {
    if (secret) return 'Copy value'
    return fullValue(value, secret) || 'Copy value'
}

const formatLabel = (value) => {
    if (!value) return ''
    return String(value).replace(/[_-]+/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

const categoryButtonClass = (value) => {
    const active = selectedCategory.value === value
    return [
        'flex w-full items-center justify-between gap-2 rounded-md px-3 py-1.5 text-left text-sm transition',
        active ? 'bg-accent-subtle font-semibold text-accent-fg' : 'text-body hover:bg-surface-2',
    ]
}

const categoryBadgeClass = (value) => {
    const active = selectedCategory.value === value
    return [
        'inline-flex min-w-6 shrink-0 items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-medium tabular-nums',
        active ? 'bg-accent-subtle text-accent-fg' : 'bg-surface-3 text-body',
    ]
}

const statusClass = (row) => [
    'inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset transition',
    props.permissions?.update ? 'hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-offset-1' : '',
    row.enabled ? 'bg-success-subtle text-success ring-success/20' : 'bg-danger-subtle text-danger ring-danger/20',
]
</script>
