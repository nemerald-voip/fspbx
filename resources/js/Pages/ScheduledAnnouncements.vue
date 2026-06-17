<template>
    <MainLayout />

    <div class="m-3">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="mb-6 mt-2 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Scheduled Announcements</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 sm:text-3xl">Scheduled Announcements</h1>
                    <p class="mt-1 text-sm text-gray-500">Build schedules with their recordings, extensions, announcement times, and exclusions in one window.</p>
                </div>
            </div>
        </div>

        <div class="mb-6 border-b border-gray-200 px-4 sm:px-6 lg:px-8">
            <nav class="-mb-px flex gap-0.5 overflow-x-auto sm:gap-2" aria-label="Tabs">
                <button v-for="tab in tabs" :key="tab.id" type="button"
                    :class="[
                        'group relative -mb-px inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-t-lg px-3 py-2.5 text-sm font-semibold tracking-tight transition-colors sm:gap-2.5 sm:px-6 sm:py-3.5 sm:text-base',
                        activeTab === tab.id
                            ? 'text-indigo-700'
                            : 'text-gray-500 hover:bg-gray-100 hover:text-gray-900'
                    ]"
                    @click="switchTab(tab.id)">
                    <component :is="tab.icon" class="h-4 w-4 sm:h-5 sm:w-5"
                        :class="activeTab === tab.id ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-600'" />
                    <span>{{ tab.label }}</span>
                    <span :class="[
                        'ml-0.5 rounded-full px-2 py-0.5 text-xs font-semibold sm:ml-1 sm:px-2.5',
                        activeTab === tab.id
                            ? 'bg-indigo-100 text-indigo-700'
                            : 'bg-gray-100 text-gray-600 group-hover:bg-gray-200'
                    ]">
                        {{ tab.count }}
                    </span>
                    <span v-if="activeTab === tab.id"
                        class="absolute inset-x-2 -bottom-px h-[3px] rounded-full bg-indigo-600 sm:inset-x-3"></span>
                </button>
            </nav>
        </div>

        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>{{ activeTabDefinition.label }}</template>

            <template #subtitle>{{ activeTabDefinition.subtitle }}</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="scheduled-announcements-search"
                        id="mobile-search-scheduled-announcements"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                    <input type="text" v-model="filterData.search" name="desktop-search-scheduled-announcements"
                        id="desktop-search-scheduled-announcements"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <button v-if="canCreateActiveTab" type="button" @click.prevent="handleCreateButtonClick"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Create
                </button>
            </template>

            <template #navigation>
                <Paginator :previous="pagination.previous" :next="pagination.next" :from="pagination.from"
                    :to="pagination.to" :total="pagination.total" :currentPage="pagination.page"
                    :lastPage="pagination.lastPage" :links="pagination.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>

            <template #table-header>
                <template v-if="activeTab === 'schedules'">
                    <TableColumnHeader
                        class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                        <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                        <div class="pl-4 flex items-center cursor-pointer select-none" @click="handleSortRequest('name')">
                            <span class="mr-2">Name</span>
                            <ChevronUpIcon v-if="sortData.name === 'name' && sortData.order === 'asc'"
                                class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'name' && sortData.order === 'desc'"
                                class="h-4 w-4 text-gray-500" />
                        </div>
                    </TableColumnHeader>
                    <TableColumnHeader header="Plays" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="To" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="When" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Status" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
                </template>

                <template v-else>
                    <TableColumnHeader header="Scheduled" class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Announcement" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Trigger" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Status" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Played" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Error / Note" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                </template>
            </template>

            <template v-if="activeTab === 'schedules' && selectPageItems" v-slot:current-selection>
                <td colspan="6">
                    <div class="text-sm text-center m-2">
                        <span class="font-semibold">{{ selectedItems.length }}</span> schedules are selected.
                        <button v-if="!selectAll && selectedItems.length !== activeRows.length"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleSelectAll">
                            Select all {{ activeRows.length }} schedules
                        </button>
                        <button v-if="selectAll"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleClearSelection">
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <template v-if="activeTab === 'schedules'">
                    <tr v-for="item in scheduleRows" :key="item.row.scheduled_announcement_schedule_uuid">
                        <TableField class="px-4 py-2 align-middle text-sm text-gray-500">
                            <div class="flex items-start">
                                <input v-model="selectedItems" type="checkbox" name="action_box[]"
                                    :value="item.row.scheduled_announcement_schedule_uuid"
                                    class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600">
                                <button type="button" class="ml-4 text-left"
                                    :class="{ 'cursor-pointer hover:text-indigo-600': permissions.update }"
                                    @click="permissions.update && handleEditButtonClick(item.row)">
                                    <span class="block font-medium text-gray-900">{{ item.row.name }}</span>
                                    <span v-if="item.row.description"
                                        class="block max-w-[16rem] truncate text-xs text-gray-400">{{ item.row.description }}</span>
                                </button>
                            </div>
                        </TableField>
                        <TableField class="px-2 py-2 align-middle text-sm text-gray-500">
                            <span class="block max-w-[12rem] truncate" :title="item.plays">{{ item.plays }}</span>
                        </TableField>
                        <TableField class="px-2 py-2 align-middle text-sm text-gray-500">
                            <span class="block max-w-[14rem] truncate" :title="item.to.full.join(', ')">
                                {{ item.to.first }}<span v-if="item.to.extra > 0" class="text-gray-400"> +{{ item.to.extra }}</span>
                            </span>
                        </TableField>
                        <TableField class="px-2 py-2 align-middle text-sm text-gray-500">
                            <template v-if="item.when.lines.length">
                                <div v-for="(line, i) in item.when.lines" :key="i" class="whitespace-nowrap">{{ line }}</div>
                                <div v-if="item.when.extra > 0" class="text-xs text-gray-400">+{{ item.when.extra }} more</div>
                                <div v-if="item.when.tz" class="truncate text-xs text-gray-400">{{ item.when.tz }}</div>
                            </template>
                            <span v-else class="text-gray-400">No times set</span>
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 align-middle text-sm text-gray-500">
                            <Badge :text="item.status.label" v-bind="item.status.props" />
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-1 align-middle text-sm text-gray-500">
                            <template #action-buttons>
                                <div class="flex items-center whitespace-nowrap justify-end">
                                    <PencilSquareIcon v-if="permissions.update" @click="handleEditButtonClick(item.row)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                        title="Edit" />
                                    <TrashIcon v-if="permissions.delete" @click="deleteRecord(item.row.scheduled_announcement_schedule_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                        title="Delete" />
                                </div>
                            </template>
                        </TableField>
                    </tr>
                </template>

                <template v-else>
                    <tr v-for="item in runRows" :key="item.row.scheduled_announcement_run_uuid">
                        <TableField class="px-4 py-2 align-middle text-sm text-gray-500">
                            <span class="block whitespace-nowrap text-gray-900">{{ item.scheduledRel }}</span>
                            <span class="block whitespace-nowrap text-xs text-gray-400">{{ item.scheduledAbs }}</span>
                        </TableField>
                        <TableField class="px-2 py-2 align-middle text-sm text-gray-500">
                            <span class="block max-w-[18rem] truncate text-gray-900">{{ item.announcement.name }}</span>
                            <span v-if="item.announcement.sub"
                                class="block max-w-[18rem] truncate text-xs text-gray-400">{{ item.announcement.sub }}</span>
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 align-middle text-sm text-gray-500">
                            <Badge :text="item.trigger.label" v-bind="item.trigger.props" />
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 align-middle text-sm text-gray-500">
                            <Badge :text="item.status.label" v-bind="item.status.props" />
                        </TableField>
                        <TableField class="px-2 py-2 align-middle text-sm text-gray-500">
                            <template v-if="item.played.ran">
                                <span class="block whitespace-nowrap text-gray-900">{{ item.played.late }}</span>
                                <span class="block whitespace-nowrap text-xs text-gray-400" :title="item.played.abs">{{ item.played.clock }}</span>
                            </template>
                            <span v-else class="text-gray-400">—</span>
                        </TableField>
                        <TableField class="max-w-xl px-2 py-2 align-middle text-sm text-gray-500">
                            <span class="block max-w-xl truncate" :title="item.row.error_text">{{ item.row.error_text }}</span>
                        </TableField>
                    </tr>
                </template>
            </template>

            <template #empty>
                <div v-if="activeRows.length === 0" class="text-center my-5">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                    <p class="mt-1 text-sm text-gray-500">Adjust your search and try again.</p>
                </div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>

            <template #footer>
                <Paginator :previous="pagination.previous" :next="pagination.next" :from="pagination.from"
                    :to="pagination.to" :total="pagination.total" :currentPage="pagination.page"
                    :lastPage="pagination.lastPage" :links="pagination.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>
        </DataTable>
    </div>

    <ConfirmationModal :show="confirmationModalTrigger" @close="handleConfirmationClose"
        @confirm="confirmAction" :header="confirmationHeader" :text="confirmationText"
        :confirm-button-label="confirmationButtonLabel" cancel-button-label="Cancel" />

    <ScheduledAnnouncementScheduleForm v-if="activeForm === 'schedules'" :show="showForm"
        :options="formOptions('schedules')" :loading="false" :mode="formMode" :header="formHeader"
        @close="handleFormClose" @error="handleErrorResponse" @success="showNotification" @refresh-data="fetchData" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import DataTable from './components/general/DataTable.vue'
import TableColumnHeader from './components/general/TableColumnHeader.vue'
import TableField from './components/general/TableField.vue'
import Paginator from './components/general/Paginator.vue'
import ConfirmationModal from './components/modal/ConfirmationModal.vue'
import Loading from './components/general/Loading.vue'
import Notification from './components/notifications/Notification.vue'
import MainLayout from '../Layouts/MainLayout.vue'
import Badge from '@generalComponents/Badge.vue'
import ScheduledAnnouncementScheduleForm from './components/forms/ScheduledAnnouncementScheduleBuilderForm.vue'
import {
    ChevronDownIcon,
    ChevronUpIcon,
    MagnifyingGlassIcon,
    PencilSquareIcon,
    TrashIcon,
} from '@heroicons/vue/24/solid'
import {
    CalendarDaysIcon,
    ClockIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    routes: { type: Object, required: true },
    permissions: { type: Object, required: true },
    timezone: { type: String, default: 'UTC' },
    timezones: { type: Array, default: () => [] },
})

const routes = props.routes
const permissions = props.permissions
const timezone = props.timezone
const timezones = props.timezones

const activeTab = ref('schedules')
const activeForm = ref('schedules')
const showForm = ref(false)
const formMode = ref('create')
const selectedItem = ref(null)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(50)
const selectAll = ref(false)
const selectedItems = ref([])
const selectPageItems = ref(false)
const confirmationModalTrigger = ref(false)
const confirmAction = ref(null)
const confirmationHeader = ref('Are you sure?')
const confirmationText = ref('')
const confirmationButtonLabel = ref('Continue')
const notificationType = ref(null)
const notificationMessages = ref(null)
const notificationShow = ref(false)

const filterData = ref({
    search: null,
})
const appliedSearch = ref(null)

const sortData = ref({
    name: 'name',
    order: 'asc',
})

const data = ref({
    schedules: [],
    events: [],
    exceptions: [],
    runs: [],
    extensions: [],
    recordings: [],
    timezones,
})

const tabMetadata = {
    schedules: {
        label: 'Schedules',
        singular: 'Schedule',
        subtitle: 'Define each schedule with its recording, extensions, announcement times, and exclusions.',
        icon: CalendarDaysIcon,
        rows: 'schedules',
    },
    runs: {
        label: 'Runs',
        singular: 'Run',
        subtitle: 'Review recent scheduler decisions and execution logs.',
        icon: ClockIcon,
        rows: 'runs',
    },
}

const tabs = computed(() => Object.entries(tabMetadata).map(([id, meta]) => ({
    id,
    label: meta.label,
    icon: meta.icon,
    count: data.value[meta.rows]?.length ?? 0,
})))

const activeTabDefinition = computed(() => tabMetadata[activeTab.value])
const canCreateActiveTab = computed(() => permissions.create && activeTab.value !== 'runs')
const activeRows = computed(() => {
    const rows = data.value[activeTabDefinition.value.rows] ?? []
    const search = appliedSearch.value?.trim().toLowerCase()

    const filtered = search
        ? rows.filter((row) => JSON.stringify(row).toLowerCase().includes(search))
        : rows

    if (activeTab.value !== 'schedules') {
        return filtered
    }

    return [...filtered].sort((a, b) => compareRows(a, b))
})

const paginatedRows = computed(() => {
    const page = Math.min(currentPage.value, pagination.value.lastPage)
    const start = (page - 1) * pageSize.value
    return activeRows.value.slice(start, start + pageSize.value)
})

const pagination = computed(() => {
    const total = activeRows.value.length
    const lastPage = Math.max(Math.ceil(total / pageSize.value), 1)
    const page = Math.min(currentPage.value, lastPage)
    const from = total === 0 ? 0 : ((page - 1) * pageSize.value) + 1
    const to = total === 0 ? 0 : Math.min(page * pageSize.value, total)

    return {
        page,
        total,
        lastPage,
        from,
        to,
        previous: page > 1 ? pageUrl(page - 1) : null,
        next: page < lastPage ? pageUrl(page + 1) : null,
        links: paginationLinks(page, lastPage),
    }
})

const scheduleRows = computed(() => {
    if (activeTab.value !== 'schedules') return []

    return paginatedRows.value.map((row) => ({
        row,
        plays: recordingLabel(row),
        to: extensionSummary(row),
        when: whenSummary(row),
        status: scheduleStatus(row),
    }))
})

const runRows = computed(() => {
    if (activeTab.value !== 'runs') return []

    return paginatedRows.value.map((row) => ({
        row,
        scheduledRel: relativeTime(row.scheduled_for),
        scheduledAbs: formatDate(row.scheduled_for),
        announcement: runAnnouncement(row),
        trigger: runTrigger(row),
        status: { label: row.status || '-', props: statusBadgeProps(row.status) },
        played: runPlayed(row),
    }))
})

const formHeader = computed(() => {
    const singular = tabMetadata[activeForm.value]?.singular || 'Item'

    if (formMode.value === 'create') {
        return `Create ${singular}`
    }

    const name = selectedItem.value?.name
        || selectedItem.value?.exception_date
        || selectedItem.value?.time_of_day
        || 'Loading...'

    return `Update ${singular} - ${name}`
})

onMounted(fetchData)

function routeFor(key, uuid) {
    return routes[key].replace('__UUID__', uuid)
}

function switchTab(tab) {
    if (activeTab.value === tab) return

    activeTab.value = tab
    filterData.value.search = null
    appliedSearch.value = null
    currentPage.value = 1
    sortData.value = { name: 'name', order: 'asc' }
    handleClearSelection()
    handleFormClose()
}

async function handleSearchButtonClick() {
    appliedSearch.value = filterData.value.search
    currentPage.value = 1
    handleClearSelection()
    await fetchData()
}

async function handleFiltersReset() {
    filterData.value.search = null
    appliedSearch.value = null
    currentPage.value = 1
    handleClearSelection()
    await fetchData()
}

async function fetchData() {
    loading.value = true

    try {
        const response = await axios.get(routes.data_route)
        data.value = {
            ...data.value,
            ...response.data,
        }
        currentPage.value = 1
        handleClearSelection()
    } catch (error) {
        handleErrorResponse(error)
    } finally {
        loading.value = false
    }
}

function handleCreateButtonClick() {
    if (!canCreateActiveTab.value) return

    activeForm.value = activeTab.value
    selectedItem.value = null
    formMode.value = 'create'
    showForm.value = true
}

function handleEditButtonClick(row) {
    activeForm.value = activeTab.value
    selectedItem.value = row
    formMode.value = 'update'
    showForm.value = true
}

function handleFormClose() {
    showForm.value = false
    formMode.value = 'create'
    selectedItem.value = null
}

function formOptions(tab) {
    return {
        item: activeForm.value === tab ? selectedItem.value : null,
        timezone,
        timezones: data.value.timezones?.length ? data.value.timezones : timezones,
        schedules: data.value.schedules,
        recordings: data.value.recordings,
        extensions: data.value.extensions,
        voices: data.value.voices,
        speeds: data.value.speeds,
        default_voice: data.value.default_voice,
        phone_call_instructions: data.value.phone_call_instructions,
        sample_message: data.value.sample_message,
        routes: {
            ...routes,
            ...formRoutes(tab),
        },
    }
}

function formRoutes(tab) {
    const item = selectedItem.value

    if (tab === 'schedules') {
        return {
            store_route: routes.schedule_store,
            update_route: item ? routeFor('schedule_update', item.scheduled_announcement_schedule_uuid) : null,
        }
    }

    if (tab === 'events') {
        return {
            store_route: routes.event_store,
            update_route: item ? routeFor('event_update', item.scheduled_announcement_event_uuid) : null,
        }
    }

    return {
        store_route: routes.exception_store,
        update_route: item ? routeFor('exception_update', item.scheduled_announcement_exception_uuid) : null,
    }
}

async function deleteRecord(uuid) {
    showConfirmation({
        header: 'Confirm Deletion',
        text: 'This action will permanently delete the selected schedule.',
        button: 'Delete',
        action: () => executeDelete([uuid]),
    })
}

async function executeDelete(uuids = selectedItems.value) {
    try {
        const responses = await Promise.all(
            uuids.map((uuid) => axios.delete(routeFor('schedule_destroy', uuid)))
        )
        handleConfirmationClose()
        handleClearSelection()
        showNotification('success', responses.at(-1)?.data?.messages ?? { server: ['Schedule deleted.'] })
        await fetchData()
    } catch (error) {
        handleConfirmationClose()
        handleClearSelection()
        handleErrorResponse(error)
    }
}

function handleSortRequest(column) {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === 'asc' ? 'desc' : 'asc'
    } else {
        sortData.value.name = column
        sortData.value.order = 'asc'
    }

    currentPage.value = 1
    handleClearSelection()
}

function compareRows(a, b) {
    const direction = sortData.value.order === 'desc' ? -1 : 1
    const column = sortData.value.name
    const first = sortableValue(a, column)
    const second = sortableValue(b, column)

    return String(first).localeCompare(String(second), undefined, { numeric: true, sensitivity: 'base' }) * direction
}

function sortableValue(row, column) {
    if (column === 'name') return row.name ?? row.event?.schedule?.name ?? row.status ?? ''
    return row[column] ?? ''
}

function pageUrl(page) {
    return `${window.location.pathname}?page=${page}`
}

function paginationLinks(page, lastPage) {
    const links = [{ url: page > 1 ? pageUrl(page - 1) : null, label: '&laquo; Previous', active: false }]

    for (let i = 1; i <= lastPage; i += 1) {
        links.push({ url: pageUrl(i), label: String(i), active: i === page })
    }

    links.push({ url: page < lastPage ? pageUrl(page + 1) : null, label: 'Next &raquo;', active: false })

    return links
}

function renderRequestedPage(url) {
    if (!url) return

    const urlObj = new URL(url, window.location.origin)
    currentPage.value = Number(urlObj.searchParams.get('page') ?? 1)
    handleClearSelection()
}

const bulkActions = computed(() => {
    if (activeTab.value !== 'schedules' || !permissions.delete) return []

    return [{ id: 'bulk_delete', label: 'Delete', icon: 'TrashIcon' }]
})

function handleSelectPageItems() {
    selectedItems.value = selectPageItems.value
        ? paginatedRows.value.map((item) => item.scheduled_announcement_schedule_uuid)
        : []
    selectAll.value = false
}

function handleSelectAll() {
    selectedItems.value = activeRows.value.map((item) => item.scheduled_announcement_schedule_uuid)
    selectAll.value = true
    selectPageItems.value = true
}

function handleClearSelection() {
    selectedItems.value = []
    selectPageItems.value = false
    selectAll.value = false
}

function handleBulkActionRequest(action) {
    if (action !== 'bulk_delete') return

    showConfirmation({
        header: 'Confirm Deletion',
        text: 'This action will permanently delete the selected schedule(s).',
        button: 'Delete',
        action: () => executeDelete(),
    })
}

function showConfirmation({ header, text, button, action }) {
    confirmationHeader.value = header
    confirmationText.value = text
    confirmationButtonLabel.value = button
    confirmAction.value = action
    confirmationModalTrigger.value = true
}

function handleConfirmationClose() {
    confirmationModalTrigger.value = false
    confirmAction.value = null
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : ''
}

const WEEKDAY_LABELS = { 1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat', 7: 'Sun' }

function recordingLabel(row) {
    const value = row.recording_filename
    if (!value || value === '0' || value === '-1') return '—'

    const match = (data.value.recordings ?? []).find((item) => String(item.value) === String(value))
    return match?.label ?? value
}

function extensionLabelFor(uuid) {
    const ext = (data.value.extensions ?? []).find((item) => item.extension_uuid === uuid)
    if (!ext) return null

    return ext.effective_caller_id_name
        ? `${ext.extension} - ${ext.effective_caller_id_name}`
        : String(ext.extension)
}

function extensionSummary(row) {
    const uuids = row.extension_uuids ?? []
    if (!uuids.length) return { first: '—', extra: 0, full: [] }

    const labels = uuids.map((uuid) => extensionLabelFor(uuid) ?? uuid)
    return { first: labels[0], extra: labels.length - 1, full: labels }
}

function formatWeekdays(days) {
    const sorted = [...(days ?? [])].map(Number).filter(Boolean).sort((a, b) => a - b)
    if (!sorted.length) return 'No days'
    if (sorted.length === 7) return 'Every day'

    const consecutive = sorted.every((day, i) => i === 0 || day === sorted[i - 1] + 1)
    if (consecutive && sorted.length > 2) {
        return `${WEEKDAY_LABELS[sorted[0]]}–${WEEKDAY_LABELS[sorted[sorted.length - 1]]}`
    }

    return sorted.map((day) => WEEKDAY_LABELS[day]).join(', ')
}

function formatTimeOfDay(value) {
    if (!value) return ''

    const match = String(value).match(/^(\d{1,2}):(\d{2})/)
    if (!match) return String(value)

    let hour = Number(match[1])
    const minute = match[2]
    const suffix = hour >= 12 ? 'PM' : 'AM'
    hour = ((hour + 11) % 12) + 1

    return `${hour}:${minute} ${suffix}`
}

function whenSummary(row) {
    const events = (row.events ?? []).filter((event) => event.enabled !== false)
    const lines = events
        .map((event) => [formatWeekdays(event.weekdays), formatTimeOfDay(event.time_of_day)].filter(Boolean).join(' · '))
        .filter(Boolean)

    const visible = lines.slice(0, 2)
    return {
        lines: visible,
        extra: Math.max(lines.length - visible.length, 0),
        tz: row.timezone || timezone,
    }
}

function parseLocalDate(value) {
    if (!value) return null

    const date = new Date(`${String(value).slice(0, 10)}T00:00:00`)
    return Number.isNaN(date.getTime()) ? null : date
}

function formatShortDate(value) {
    const date = parseLocalDate(value)
    return date ? date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : ''
}

function scheduleStatus(row) {
    const gray = enabledBadgeProps(false)
    const green = enabledBadgeProps(true)
    const yellow = { backgroundColor: 'bg-yellow-50', textColor: 'text-yellow-700', ringColor: 'ring-yellow-600/20' }

    if (!row.enabled) return { label: 'Disabled', props: gray }

    const today = new Date()
    today.setHours(0, 0, 0, 0)
    const starts = parseLocalDate(row.starts_on)
    const ends = parseLocalDate(row.ends_on)

    if (ends && ends < today) return { label: 'Ended', props: gray }
    if (starts && starts > today) return { label: `Starts ${formatShortDate(row.starts_on)}`, props: yellow }

    return { label: 'Active', props: green }
}

function relativeTime(value) {
    if (!value) return ''

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return ''

    const diffMs = date.getTime() - Date.now()
    const mins = Math.round(Math.abs(diffMs) / 60000)
    if (mins < 1) return 'just now'

    let magnitude
    if (mins < 60) magnitude = `${mins}m`
    else if (mins < 1440) magnitude = `${Math.round(mins / 60)}h`
    else magnitude = `${Math.round(mins / 1440)}d`

    return diffMs > 0 ? `in ${magnitude}` : `${magnitude} ago`
}

function formatClock(value) {
    if (!value) return ''

    const date = new Date(value)
    return Number.isNaN(date.getTime())
        ? ''
        : date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
}

function latenessLabel(row) {
    if (!row.executed_at || !row.scheduled_for) return 'done'

    const exec = new Date(row.executed_at).getTime()
    const sched = new Date(row.scheduled_for).getTime()
    if (Number.isNaN(exec) || Number.isNaN(sched)) return 'done'

    const seconds = Math.round((exec - sched) / 1000)
    if (Math.abs(seconds) <= 5) return 'on time'

    const abs = Math.abs(seconds)
    let magnitude
    if (abs < 60) magnitude = `${abs}s`
    else if (abs < 3600) magnitude = `${Math.round(abs / 60)}m`
    else magnitude = `${Math.round(abs / 3600)}h`

    return seconds < 0 ? `${magnitude} early` : `${magnitude} late`
}

function scheduleNameForRun(row) {
    if (row.event?.schedule?.name) return row.event.schedule.name

    const uuid = row.scheduled_announcement_schedule_uuid
    if (uuid) {
        const match = (data.value.schedules ?? []).find((schedule) => schedule.scheduled_announcement_schedule_uuid === uuid)
        if (match?.name) return match.name
    }

    return row.manual ? 'Manual run' : '—'
}

function runAnnouncement(row) {
    const name = scheduleNameForRun(row)
    const time = row.event?.time_of_day ? formatTimeOfDay(row.event.time_of_day) : ''
    const recording = recordingLabel(row)
    const sub = [time, recording !== '—' ? recording : null].filter(Boolean).join(' · ')

    return { name, sub }
}

function runTrigger(row) {
    return row.manual
        ? { label: 'Manual', props: { backgroundColor: 'bg-purple-50', textColor: 'text-purple-700', ringColor: 'ring-purple-600/20' } }
        : { label: 'Auto', props: { backgroundColor: 'bg-gray-50', textColor: 'text-gray-700', ringColor: 'ring-gray-600/20' } }
}

function runPlayed(row) {
    if (!row.executed_at) return { ran: false, late: '', clock: '', abs: '' }

    return {
        ran: true,
        late: latenessLabel(row),
        clock: formatClock(row.executed_at),
        abs: formatDate(row.executed_at),
    }
}

const hideNotification = () => {
    notificationShow.value = false
    notificationType.value = null
    notificationMessages.value = null
}

const showNotification = (type, messages = null) => {
    notificationType.value = type
    notificationMessages.value = messages
    notificationShow.value = true
}

const handleErrorResponse = (error) => {
    if (error.response) {
        showNotification('error', error.response.data.errors || error.response.data.messages || { request: [error.message] })
    } else if (error.request) {
        showNotification('error', { request: [error.request] })
    } else {
        showNotification('error', { request: [error.message] })
    }
}

const enabledBadgeProps = (enabled) => enabled
    ? {
        backgroundColor: 'bg-green-50',
        textColor: 'text-green-700',
        ringColor: 'ring-green-600/20',
    }
    : {
        backgroundColor: 'bg-gray-50',
        textColor: 'text-gray-700',
        ringColor: 'ring-gray-600/20',
    }

const statusBadgeProps = (status) => {
    if (['executed', 'success'].includes(status)) {
        return enabledBadgeProps(true)
    }

    if (['missed', 'failed'].includes(status)) {
        return {
            backgroundColor: 'bg-red-50',
            textColor: 'text-red-700',
            ringColor: 'ring-red-600/20',
        }
    }

    if (String(status || '').startsWith('skipped')) {
        return {
            backgroundColor: 'bg-yellow-50',
            textColor: 'text-yellow-700',
            ringColor: 'ring-yellow-600/20',
        }
    }

    return {
        backgroundColor: 'bg-gray-50',
        textColor: 'text-gray-700',
        ringColor: 'ring-gray-600/20',
    }
}
</script>
