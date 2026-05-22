<template>
    <MainLayout />

    <div class="m-3 space-y-4">
        <!-- Header -->
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-indigo-600">Domain settings</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ domain.domain_description || domain.domain_name }}</h1>
                <p class="mt-1 text-sm text-gray-500">Override global defaults for this domain, or revert customizations back to the system default.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a :href="routes.domains" class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    <BuildingOffice2Icon class="h-4 w-4" /> Domains
                </a>
                <a :href="routes.default_settings" class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    <ArrowUturnLeftIcon class="h-4 w-4" /> Default Settings
                </a>
                <button type="button" class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50" @click="reloadSettings">
                    <ArrowPathIcon class="h-4 w-4" /> Reload
                </button>
                <button v-if="permissions.create" type="button" class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500" @click="openEditor()">
                    <PlusIcon class="h-4 w-4" /> New override
                </button>
            </div>
        </header>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatTile label="Total settings" :value="stats.total" tone="gray" />
            <StatTile label="Overridden" :value="stats.overrides" tone="amber" />
            <StatTile label="Domain only" :value="stats.custom" tone="purple" />
            <StatTile label="Disabled" :value="stats.disabled" tone="rose" />
        </div>

        <!-- Main two-column layout -->
        <div class="flex flex-col gap-4 lg:flex-row">
            <!-- Sidebar -->
            <aside class="lg:w-72 lg:shrink-0">
                <div class="rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-200">
                    <div class="relative mb-3">
                        <MagnifyingGlassIcon class="pointer-events-none absolute inset-y-0 left-3 h-4 w-4 my-auto text-gray-400" />
                        <input v-model="filterData.search" type="text" placeholder="Search settings..." class="block w-full rounded-md border-0 py-1.5 pl-9 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600" />
                    </div>

                    <div class="mb-3 space-y-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-500">Source</label>
                            <select v-model="filterData.source" class="mt-1 block w-full rounded-md border-0 py-1.5 pl-2 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300">
                                <option value="all">All sources</option>
                                <option value="default">Defaults</option>
                                <option value="overrides">Overrides &amp; custom</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500">Status</label>
                            <select v-model="filterData.enabled" class="mt-1 block w-full rounded-md border-0 py-1.5 pl-2 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300">
                                <option value="all">Any status</option>
                                <option value="true">Enabled</option>
                                <option value="false">Disabled</option>
                            </select>
                        </div>
                    </div>

                    <p class="px-1 pb-1 text-xs font-medium uppercase tracking-wider text-gray-400">Categories</p>
                    <nav class="max-h-[60vh] space-y-0.5 overflow-y-auto">
                        <button type="button" :class="categoryButtonClass('')" @click="selectedCategory = ''">
                            <span class="truncate">All</span>
                            <span :class="categoryBadgeClass('')">{{ filteredRows.length }}</span>
                        </button>
                        <button v-for="cat in categoriesWithCounts" :key="cat.value" type="button" :class="categoryButtonClass(cat.value)" @click="selectedCategory = cat.value">
                            <span class="truncate">{{ cat.label }}</span>
                            <span :class="categoryBadgeClass(cat.value)">{{ cat.count }}</span>
                        </button>
                        <p v-if="!categoriesWithCounts.length" class="px-3 py-2 text-xs text-gray-400">No matching categories</p>
                    </nav>
                </div>
            </aside>

            <!-- Main panel -->
            <section class="min-w-0 flex-1">
                <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">{{ selectedCategoryLabel }}</h2>
                            <p class="text-xs text-gray-500">{{ displayedRows.length }} setting{{ displayedRows.length === 1 ? '' : 's' }} shown</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button v-if="selectableRowUuids.length" type="button" class="text-xs text-gray-500 hover:text-gray-900" @click="toggleSelectAllVisible">
                                {{ allVisibleSelected ? 'Clear selection' : 'Select visible' }}
                            </button>
                            <div v-if="selectedItems.length" class="flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700">
                                <span>{{ selectedItems.length }} selected</span>
                                <button v-if="permissions.update" type="button" class="rounded px-1.5 py-0.5 hover:bg-indigo-100" @click="handleBulkActionRequest('bulk_toggle')">Toggle</button>
                                <button v-if="permissions.destroy" type="button" class="rounded px-1.5 py-0.5 hover:bg-indigo-100" @click="handleBulkActionRequest('bulk_revert')">Revert</button>
                                <button v-if="permissions.copy" type="button" class="rounded px-1.5 py-0.5 hover:bg-indigo-100" @click="handleBulkActionRequest('bulk_copy')">Copy</button>
                            </div>
                        </div>
                    </header>

                    <div v-if="loading" class="px-4 py-12">
                        <Loading :show="true" :absolute="false" />
                    </div>

                    <ul v-else-if="displayedRows.length" class="divide-y divide-gray-100">
                        <li v-for="row in displayedRows" :key="row.id" class="relative flex items-start gap-3 px-4 py-3 transition hover:bg-gray-50">
                            <span v-if="rowAccentColor(row)" :class="['pointer-events-none absolute inset-y-0 left-0 w-1', rowAccentColor(row)]" aria-hidden="true" />
                            <input v-if="row.domain_setting_uuid" v-model="selectedItems" type="checkbox" :value="row.domain_setting_uuid" class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                            <span v-else class="mt-1 inline-block h-4 w-4 shrink-0" />

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <h3 class="text-sm font-semibold text-gray-900">{{ formatLabel(row.subcategory) }}</h3>
                                    <button type="button" class="inline-flex items-center rounded-md bg-indigo-50 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-indigo-700 ring-1 ring-inset ring-indigo-600/20 hover:bg-indigo-100" :title="`Filter by ${row.category_label}`" @click="selectedCategory = row.category">{{ row.category_label }}</button>
                                    <span class="font-mono text-xs text-gray-400">{{ row.subcategory }}</span>
                                    <span class="text-xs text-gray-300">·</span>
                                    <span class="text-xs text-gray-500">{{ row.type_label }}</span>
                                </div>
                                <p v-if="row.description" class="mt-1 text-xs text-gray-500">{{ row.description }}</p>

                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <div class="inline-flex min-w-0 max-w-full items-center gap-1.5">
                                        <span class="shrink-0 text-xs text-gray-400">Value</span>
                                        <button type="button" class="min-w-0 max-w-full text-left" :title="valueTitle(row.effective_value, row.is_secret)" aria-label="Copy value" @click.stop="copyValue(row.effective_value)">
                                            <code class="block max-w-full truncate rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-800 ring-1 ring-transparent transition hover:bg-gray-200 hover:ring-gray-300">{{ truncatedValue(row.effective_value, row.is_secret) }}</code>
                                        </button>
                                    </div>
                                    <div v-if="row.source === 'override'" class="inline-flex min-w-0 max-w-full items-center gap-1.5 text-xs text-gray-400">
                                        <span class="shrink-0">default</span>
                                        <button type="button" class="min-w-0 max-w-full text-left" :title="valueTitle(row.default_value, row.is_secret)" aria-label="Copy default value" @click.stop="copyValue(row.default_value)">
                                            <code class="block max-w-full truncate rounded bg-gray-50 px-1.5 py-0.5 font-mono text-xs text-gray-500 line-through ring-1 ring-transparent transition hover:bg-gray-100 hover:ring-gray-300">{{ truncatedValue(row.default_value, row.is_secret) }}</code>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <span :class="['inline-flex rounded-md px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset', sourceClass(row.source)]">{{ row.source_label }}</span>
                                <button v-if="canToggleStatus(row)" type="button" :class="statusClass(row)" :title="row.enabled ? 'Turn off for this domain' : 'Turn on for this domain'" @click="toggleStatus(row)">
                                    <span :class="['mr-1 inline-block h-1.5 w-1.5 rounded-full', row.enabled ? 'bg-green-500' : 'bg-rose-500']" />
                                    {{ row.enabled ? 'On' : 'Off' }}
                                </button>
                                <span v-else :class="statusClass(row)">
                                    <span :class="['mr-1 inline-block h-1.5 w-1.5 rounded-full', row.enabled ? 'bg-green-500' : 'bg-rose-500']" />
                                    {{ row.enabled ? 'On' : 'Off' }}
                                </span>
                                <button v-if="permissions.create && !row.domain_setting_uuid" type="button" class="rounded-md px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50" @click="openEditor(row)">Override</button>
                                <button v-if="permissions.update && row.domain_setting_uuid" type="button" class="rounded-md px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50" @click="openEditor(row)">Edit</button>
                                <button v-if="permissions.destroy && row.domain_setting_uuid" type="button" class="rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50" @click="confirmRevert([row.domain_setting_uuid])">Revert</button>
                            </div>
                        </li>
                    </ul>

                    <div v-else class="px-4 py-12 text-center">
                        <p class="text-sm font-medium text-gray-900">No settings match your filters</p>
                        <p class="mt-1 text-xs text-gray-500">Try clearing search, source, or status filters.</p>
                        <button type="button" class="mt-3 rounded-md bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50" @click="handleFiltersReset">Reset filters</button>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <SettingsEditModal :show="showEditor" mode="domain" :item="editorItem" :types="options.types" :categories="options.categories" :route="editorRoute"
        :loading="editorLoading" @close="showEditor = false" @success="handleModalSuccess" @error="handleErrorResponse" />

    <AddEditItemModal :show="showCopyModal" header="Copy Domain Settings" @close="showCopyModal = false">
        <template #modal-body>
            <Vueform :endpoint="submitCopyForm" @success="handleCopySuccess" @error="handleErrorResponse" :display-errors="false">
                <template #empty>
                    <FormElements>
                        <SelectElement name="target_domain_uuid" label="Target" :items="options.domains"
                            :native="false" input-type="search" autocomplete="off" placeholder="Select target"
                            :strict="false" :floating="false" />
                        <ButtonElement name="submit" button-label="Copy" :submits="true" align="right" />
                    </FormElements>
                </template>
            </Vueform>
        </template>
    </AddEditItemModal>

    <ConfirmationModal :show="showConfirmModal" header="Confirm Revert" text="Selected domain override rows will be removed and defaults will take effect." confirm-button-label="Revert" cancel-button-label="Cancel" @close="showConfirmModal = false" @confirm="executeConfirmedAction" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages" @update:show="notificationShow = false" />
</template>

<script setup>
import { computed, h, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import MainLayout from '../Layouts/MainLayout.vue'
import Loading from './components/general/Loading.vue'
import Notification from './components/notifications/Notification.vue'
import ConfirmationModal from './components/modal/ConfirmationModal.vue'
import AddEditItemModal from './components/modal/AddEditItemModal.vue'
import SettingsEditModal from './components/modal/SettingsEditModal.vue'
import { MagnifyingGlassIcon, ArrowPathIcon, ArrowUturnLeftIcon, PlusIcon, BuildingOffice2Icon } from '@heroicons/vue/24/outline'

const StatTile = (props) => {
    const toneMap = {
        gray: 'text-gray-900',
        amber: 'text-amber-600',
        purple: 'text-purple-600',
        rose: 'text-rose-600',
    }
    return h('div', { class: 'rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-200' }, [
        h('p', { class: 'text-xs text-gray-500' }, props.label),
        h('p', { class: ['mt-0.5 text-xl font-semibold', toneMap[props.tone] || toneMap.gray] }, String(props.value ?? 0)),
    ])
}
StatTile.props = ['label', 'value', 'tone']

const props = defineProps({
    domain: Object,
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
const showCopyModal = ref(false)
const notificationShow = ref(false)
const notificationType = ref(null)
const notificationMessages = ref(null)

const defaultFilters = () => ({ search: '', source: 'overrides', enabled: 'all' })
const filterData = ref(defaultFilters())
const selectedCategory = ref('')

const categoryLabelMap = computed(() => {
    const map = {}
    for (const cat of props.options?.categories || []) map[cat.value] = cat.label
    return map
})

const stats = computed(() => {
    const total = allRows.value.length
    const overrides = allRows.value.filter(r => r.source === 'override').length
    const custom = allRows.value.filter(r => r.source === 'custom').length
    const disabled = allRows.value.filter(r => !r.enabled).length
    return { total, overrides, custom, disabled }
})

// Rows after sidebar filters (search/source/enabled) but BEFORE category filter
const filteredRows = computed(() => {
    const search = filterData.value.search.trim().toLowerCase()
    return allRows.value.filter(row => {
        if (filterData.value.source !== 'all') {
            if (filterData.value.source === 'overrides') {
                if (row.source !== 'override' && row.source !== 'custom') return false
            } else if (row.source !== filterData.value.source) return false
        }
        if (filterData.value.enabled !== 'all') {
            const wantEnabled = filterData.value.enabled === 'true'
            if (Boolean(row.enabled) !== wantEnabled) return false
        }
        if (search) {
            const hay = [row.category, row.subcategory, row.type, row.effective_value, row.default_value, row.description]
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
        .map(([value, count]) => ({ value, label: categoryLabelMap.value[value] || value || 'Uncategorized', count }))
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
        const sa = String(a.subcategory || '').toLowerCase()
        const sb = String(b.subcategory || '').toLowerCase()
        return sa.localeCompare(sb)
    })
})

const selectedCategoryLabel = computed(() => {
    if (!selectedCategory.value) return 'All settings'
    return categoryLabelMap.value[selectedCategory.value] || selectedCategory.value
})

const selectableRowUuids = computed(() => displayedRows.value.map(r => r.domain_setting_uuid).filter(Boolean))

const allVisibleSelected = computed(() => {
    if (!selectableRowUuids.value.length) return false
    return selectableRowUuids.value.every(uuid => selectedItems.value.includes(uuid))
})

watch([selectedCategory, () => filterData.value.search, () => filterData.value.source, () => filterData.value.enabled], () => {
    selectedItems.value = []
})

// Auto-jump out of an empty category when filters narrow results
watch(categoriesWithCounts, (cats) => {
    if (!selectedCategory.value) return
    if (!cats.find(c => c.value === selectedCategory.value)) selectedCategory.value = ''
})

onMounted(() => getData())

const getData = (silent = false) => {
    if (!silent) loading.value = true
    axios.get(props.routes.data_route, { params: { per_page: 5000, page: 1 } })
        .then(response => {
            allRows.value = response.data?.data || []
            if (!silent) selectedItems.value = []
        })
        .catch(handleErrorResponse)
        .finally(() => {
            if (!silent) loading.value = false
        })
}

const handleFiltersReset = () => {
    filterData.value = defaultFilters()
    selectedCategory.value = ''
}

const toggleSelectAllVisible = () => {
    if (allVisibleSelected.value) {
        selectedItems.value = selectedItems.value.filter(uuid => !selectableRowUuids.value.includes(uuid))
    } else {
        const set = new Set(selectedItems.value)
        selectableRowUuids.value.forEach(uuid => set.add(uuid))
        selectedItems.value = Array.from(set)
    }
}

const openEditor = (row = null) => {
    editorLoading.value = true
    showEditor.value = true
    const payload = row ? {
        domain_setting_uuid: row.domain_setting_uuid,
        default_setting_uuid: row.default_setting_uuid,
        default_value: row.default_value,
    } : {}
    axios.post(props.routes.item_options, payload)
        .then(response => {
            editorItem.value = response.data.item
            editorRoute.value = response.data.item.domain_setting_uuid
                ? props.routes.update.replace('__SETTING__', response.data.item.domain_setting_uuid)
                : props.routes.store
        })
        .catch(error => {
            showEditor.value = false
            handleErrorResponse(error)
        })
        .finally(() => editorLoading.value = false)
}

const confirmRevert = (items = selectedItems.value) => {
    confirmedAction.value = () => axios.post(props.routes.bulk_revert, { items }).then(response => {
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
    if (action === 'bulk_revert') confirmRevert()
    if (action === 'bulk_toggle') {
        axios.post(props.routes.bulk_toggle, { items: selectedItems.value })
            .then(response => {
                showNotification('success', response.data.messages)
                getData()
            })
            .catch(handleErrorResponse)
    }
    if (action === 'bulk_copy') {
        showCopyModal.value = true
    }
}

const canToggleStatus = (row) => {
    if (row.domain_setting_uuid) return props.permissions?.update
    return props.permissions?.create
}

const toggleStatus = (row) => {
    const wasEnabled = row.enabled
    row.enabled = !row.enabled

    const request = row.domain_setting_uuid
        ? axios.post(props.routes.bulk_toggle, { items: [row.domain_setting_uuid] })
        : axios.post(props.routes.store, {
            domain_setting_category: row.category,
            domain_setting_subcategory: row.subcategory,
            domain_setting_name: row.type,
            domain_setting_value: row.effective_value,
            domain_setting_order: row.order,
            domain_setting_enabled: !row.enabled,
            domain_setting_description: row.description,
        })

    request
        .then(response => {
            showNotification('success', response.data.messages)
            getData(true)
        })
        .catch(error => {
            row.enabled = wasEnabled
            handleErrorResponse(error)
        })
}

const submitCopyForm = async (FormData, form) => {
    return await form.$vueform.services.axios.post(props.routes.copy, {
        ...form.requestData,
        items: selectedItems.value,
    })
}

const handleCopySuccess = (response) => {
    showCopyModal.value = false
    showNotification('success', response.data.messages)
}

const reloadSettings = () => {
    axios.post(props.routes.reload)
        .then(response => {
            showNotification('success', response.data.messages)
            getData()
        })
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
    if (value === null || value === undefined || value === '') return '—'
    return secret ? '••••••••' : String(value)
}

const fullValue = (value, secret = false) => {
    if (secret) return '••••••••'
    if (value === null || value === undefined || value === '') return ''
    return String(value)
}

const truncatedValue = (value, secret = false) => {
    const text = displayValue(value, secret)
    if (text.length > VALUE_TRUNCATE_AT) return text.slice(0, VALUE_TRUNCATE_AT) + '…'
    return text
}

const valueTitle = (value, secret = false) => {
    if (secret) return 'Copy value'
    return fullValue(value, secret) || 'Copy value'
}

const formatLabel = (value) => {
    if (!value) return '—'
    return String(value).replace(/[_-]+/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

const sourceClass = (source) => {
    if (source === 'override') return 'bg-amber-50 text-amber-700 ring-amber-600/20'
    if (source === 'custom') return 'bg-purple-50 text-purple-700 ring-purple-600/20'
    return 'bg-slate-50 text-slate-600 ring-slate-500/20'
}

const rowAccentColor = (row) => {
    if (row.source === 'override') return 'bg-amber-400'
    if (row.source === 'custom') return 'bg-purple-400'
    return ''
}

const categoryButtonClass = (value) => {
    const active = selectedCategory.value === value
    return [
        'flex w-full items-center justify-between gap-2 rounded-md px-3 py-1.5 text-left text-sm transition',
        active ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-gray-700 hover:bg-gray-50',
    ]
}

const categoryBadgeClass = (value) => {
    const active = selectedCategory.value === value
    return [
        'inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
        active ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600',
    ]
}

const statusClass = (row) => [
    'inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset transition',
    canToggleStatus(row) ? 'hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-offset-1' : '',
    row.enabled ? 'bg-green-50 text-green-700 ring-green-600/20' : 'bg-rose-50 text-rose-700 ring-rose-600/20',
]
</script>
