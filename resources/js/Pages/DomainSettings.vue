<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>{{ domain.domain_description || domain.domain_name }} Settings</template>
            <template #subtitle>Manage domain overrides and revert settings back to global defaults.</template>

            <template #filters>
                <div class="relative min-w-64 mb-2 sm:mr-4">
                    <MagnifyingGlassIcon class="pointer-events-none absolute inset-y-0 left-3 h-5 w-5 text-gray-400" />
                    <input v-model="filterData.search" type="text" class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600" placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                </div>
                <select v-model="filterData.category" class="mb-2 sm:mr-4 rounded-md border-0 py-1.5 pl-3 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300">
                    <option value="">All categories</option>
                    <option v-for="category in options.categories" :key="category.value" :value="category.value">{{ category.label }}</option>
                </select>
                <select v-model="filterData.source" class="mb-2 sm:mr-4 rounded-md border-0 py-1.5 pl-3 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300">
                    <option value="all">All sources</option>
                    <option value="default">Defaults</option>
                    <option value="overrides">Overrides</option>
                </select>
                <select v-model="filterData.enabled" class="mb-2 sm:mr-4 rounded-md border-0 py-1.5 pl-3 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300">
                    <option value="all">Any status</option>
                    <option value="true">Enabled</option>
                    <option value="false">Disabled</option>
                </select>
            </template>

            <template #action>
                <div class="flex gap-2">
                    <a :href="routes.default_settings" class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Defaults</a>
                    <button type="button" class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50" @click="reloadSettings">Reload</button>
                    <button v-if="permissions.create" type="button" class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500" @click="openEditor()">Create</button>
                </div>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    :bulk-actions="bulkActions" :has-selected-items="selectedItems.length > 0"
                    @pagination-change-page="renderRequestedPage" @bulk-action="handleBulkActionRequest" />
            </template>

            <template #table-header>
                <TableColumnHeader class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900" :sortable="false">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                </TableColumnHeader>
                <TableColumnHeader header="Setting" field="subcategory" :sortedField="sortData.name" :sortOrder="sortData.order" @sort="handleSortRequest" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Default" field="default_value" :sortedField="sortData.name" :sortOrder="sortData.order" @sort="handleSortRequest" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Effective" field="effective_value" :sortedField="sortData.name" :sortOrder="sortData.order" @sort="handleSortRequest" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Source" field="source" :sortedField="sortData.name" :sortOrder="sortData.order" @sort="handleSortRequest" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Status" field="enabled" :sortedField="sortData.name" :sortOrder="sortData.order" @sort="handleSortRequest" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" :sortable="false" />
            </template>

            <template #current-selection>
                <tr v-if="selectedItems.length">
                    <td colspan="7" class="bg-indigo-50 px-4 py-2 text-center text-sm text-indigo-700">{{ selectedItems.length }} override(s) selected.</td>
                </tr>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.id">
                    <TableField class="px-4 py-2 text-sm text-gray-500">
                        <input v-if="row.domain_setting_uuid" v-model="selectedItems" type="checkbox" :value="row.domain_setting_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                    </TableField>
                    <TableField class="px-2 py-2 text-sm text-gray-700">
                        <div class="font-medium text-gray-900">{{ row.subcategory }}</div>
                        <div class="text-xs text-gray-500">{{ row.category_label }} · {{ row.type_label }}</div>
                        <div v-if="row.description" class="mt-1 max-w-md truncate text-xs text-gray-400">{{ row.description }}</div>
                    </TableField>
                    <TableField class="max-w-xs px-2 py-2 text-sm text-gray-500">
                        <span class="block truncate font-mono text-xs">{{ displayValue(row.default_value, row.is_secret) }}</span>
                    </TableField>
                    <TableField class="max-w-xs px-2 py-2 text-sm text-gray-700">
                        <span class="block truncate font-mono text-xs">{{ displayValue(row.effective_value, row.is_secret) }}</span>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm">
                        <span :class="sourceClass(row.source)" class="inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset">{{ row.source_label }}</span>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm">
                        <span :class="row.enabled ? 'bg-green-50 text-green-700 ring-green-600/20' : 'bg-rose-50 text-rose-700 ring-rose-600/20'" class="inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset">
                            {{ row.enabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-1 text-right text-sm">
                        <button v-if="permissions.create && !row.domain_setting_uuid" type="button" class="rounded-md px-2 py-1 text-indigo-600 hover:bg-indigo-50" @click="openEditor(row)">Override</button>
                        <button v-if="permissions.update && row.domain_setting_uuid" type="button" class="rounded-md px-2 py-1 text-indigo-600 hover:bg-indigo-50" @click="openEditor(row)">Edit</button>
                        <button v-if="permissions.destroy && row.domain_setting_uuid" type="button" class="rounded-md px-2 py-1 text-rose-600 hover:bg-rose-50" @click="confirmRevert([row.domain_setting_uuid])">Revert</button>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="!loading && data.data.length === 0" class="py-8 text-center text-sm text-gray-500">No settings found.</div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>

            <template #footer>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>
        </DataTable>
    </div>

    <SettingsEditModal :show="showEditor" mode="domain" :item="editorItem" :types="options.types" :route="editorRoute"
        :loading="editorLoading" @close="showEditor = false" @success="handleModalSuccess" @error="handleErrorResponse" />

    <AddEditItemModal :show="showCopyModal" header="Copy Domain Settings" @close="showCopyModal = false">
        <template #modal-body>
            <label class="block text-sm font-medium text-gray-700">Target</label>
            <select v-model="copyTarget" class="mt-2 block w-full rounded-md border-0 py-2 pl-3 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300">
                <option value="" disabled>Select target</option>
                <option v-for="target in options.domains" :key="target.value" :value="target.value">{{ target.label }}</option>
            </select>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300" @click="showCopyModal = false">Cancel</button>
                <button type="button" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white" @click="executeCopy">Copy</button>
            </div>
        </template>
    </AddEditItemModal>

    <ConfirmationModal :show="showConfirmModal" header="Confirm Revert" text="Selected domain override rows will be removed and defaults will take effect." confirm-button-label="Revert" cancel-button-label="Cancel" @close="showConfirmModal = false" @confirm="executeConfirmedAction" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages" @update:show="notificationShow = false" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import MainLayout from '../Layouts/MainLayout.vue'
import DataTable from './components/general/DataTable.vue'
import TableColumnHeader from './components/general/TableColumnHeader.vue'
import TableField from './components/general/TableField.vue'
import Paginator from './components/general/Paginator.vue'
import Loading from './components/general/Loading.vue'
import Notification from './components/notifications/Notification.vue'
import ConfirmationModal from './components/modal/ConfirmationModal.vue'
import AddEditItemModal from './components/modal/AddEditItemModal.vue'
import SettingsEditModal from './components/modal/SettingsEditModal.vue'
import { MagnifyingGlassIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
    domain: Object,
    routes: Object,
    permissions: Object,
    options: Object,
})

const data = ref({ data: [], links: [], total: 0, from: 0, to: 0, current_page: 1, last_page: 1 })
const loading = ref(false)
const currentPage = ref(1)
const selectedItems = ref([])
const selectPageItems = ref(false)
const showEditor = ref(false)
const editorLoading = ref(false)
const editorItem = ref({})
const editorRoute = ref(props.routes.store)
const showConfirmModal = ref(false)
const confirmedAction = ref(null)
const showCopyModal = ref(false)
const copyTarget = ref('')
const notificationShow = ref(false)
const notificationType = ref(null)
const notificationMessages = ref(null)

const filterData = ref({ search: '', category: '', source: 'all', enabled: 'all' })
const sortData = ref({ name: 'category', order: 'asc' })

const bulkActions = computed(() => {
    const actions = []
    if (props.permissions.update) actions.push({ id: 'bulk_toggle', label: 'Toggle', icon: 'PencilSquareIcon' })
    if (props.permissions.destroy) actions.push({ id: 'bulk_revert', label: 'Revert', icon: 'TrashIcon' })
    if (props.permissions.copy) actions.push({ id: 'bulk_copy', label: 'Copy', icon: 'DocumentDuplicateIcon' })
    return actions
})

onMounted(() => getData())

const getData = (page = 1) => {
    loading.value = true
    currentPage.value = Number(page) || 1
    let sort = sortData.value.name
    if (sortData.value.order === 'desc') sort = `-${sort}`
    axios.get(props.routes.data_route, { params: { filter: filterData.value, page: currentPage.value, sort } })
        .then(response => {
            data.value = response.data
            selectedItems.value = []
            selectPageItems.value = false
        })
        .catch(handleErrorResponse)
        .finally(() => loading.value = false)
}

const handleSearchButtonClick = () => getData(1)
const handleFiltersReset = () => {
    filterData.value = { search: '', category: '', source: 'all', enabled: 'all' }
    getData(1)
}
const renderRequestedPage = (url) => getData(new URL(url, window.location.origin).searchParams.get('page') ?? 1)
const handleSortRequest = (sort) => {
    sortData.value = sort
    getData(1)
}

const handleSelectPageItems = () => {
    selectedItems.value = selectPageItems.value
        ? data.value.data.map(row => row.domain_setting_uuid).filter(Boolean)
        : []
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
        getData(currentPage.value)
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
                getData(currentPage.value)
            })
            .catch(handleErrorResponse)
    }
    if (action === 'bulk_copy') {
        copyTarget.value = ''
        showCopyModal.value = true
    }
}

const executeCopy = () => {
    axios.post(props.routes.copy, { items: selectedItems.value, target_domain_uuid: copyTarget.value })
        .then(response => {
            showCopyModal.value = false
            showNotification('success', response.data.messages)
        })
        .catch(handleErrorResponse)
}

const reloadSettings = () => {
    axios.post(props.routes.reload)
        .then(response => {
            showNotification('success', response.data.messages)
            getData(currentPage.value)
        })
        .catch(handleErrorResponse)
}

const handleModalSuccess = (messages) => {
    showNotification('success', messages)
    getData(currentPage.value)
}

const showNotification = (type, messages) => {
    notificationType.value = type
    notificationMessages.value = messages
    notificationShow.value = true
}

const handleErrorResponse = (error) => {
    showNotification('error', error.response?.data?.messages || error.response?.data?.errors || { error: ['Request failed.'] })
}

const displayValue = (value, secret = false) => {
    if (value === null || value === undefined || value === '') return 'null'
    return secret ? '********' : String(value)
}

const sourceClass = (source) => {
    if (source === 'override') return 'bg-amber-50 text-amber-700 ring-amber-600/20'
    if (source === 'custom') return 'bg-purple-50 text-purple-700 ring-purple-600/20'
    return 'bg-slate-50 text-slate-700 ring-slate-600/20'
}
</script>
