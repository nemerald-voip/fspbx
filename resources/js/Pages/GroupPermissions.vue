<template>
    <MainLayout />

    <div class="m-3 space-y-4">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-medium uppercase text-accent-fg">Group access</p>
                <h1 class="mt-1 text-2xl font-semibold text-heading">Permissions: {{ group.group_name }}</h1>
                <p v-if="group.group_description" class="mt-1 text-sm text-muted">{{ group.group_description }}</p>
                <p v-else class="mt-1 text-sm text-muted">Manage assigned permissions for this group.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a :href="routes.groups" class="inline-flex items-center gap-1.5 rounded-md bg-surface px-3 py-1.5 text-sm font-medium text-body shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    <ArrowUturnLeftIcon class="h-4 w-4" /> Groups
                </a>
                <button v-if="permissions.members" type="button" class="inline-flex items-center gap-1.5 rounded-md bg-surface px-3 py-1.5 text-sm font-medium text-body shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2" @click="showMembersModal = true">
                    <UsersIcon class="h-4 w-4" /> Members
                </button>
                <button v-if="permissions.reload" type="button" class="inline-flex items-center gap-1.5 rounded-md bg-surface px-3 py-1.5 text-sm font-medium text-body shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2" @click="reloadPermissions">
                    <ArrowPathIcon class="h-4 w-4" /> Reload
                </button>
            </div>
        </header>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatTile label="Total permissions" :value="stats.total" tone="gray" />
            <StatTile label="Assigned" :value="stats.assigned" tone="green" />
            <StatTile label="Unassigned" :value="stats.unassigned" tone="rose" />
            <StatTile label="Applications" :value="stats.applications" tone="indigo" />
        </div>

        <div class="flex flex-col gap-4 lg:flex-row">
            <aside class="lg:w-72 lg:shrink-0">
                <div class="rounded-lg bg-surface p-3 shadow-sm ring-1 ring-strong">
                    <div class="relative mb-3">
                        <MagnifyingGlassIcon class="pointer-events-none absolute inset-y-0 left-3 my-auto h-4 w-4 text-subtle" />
                        <input v-model="filterData.search" type="text" placeholder="Search permissions..." class="block w-full rounded-md border-0 py-1.5 pl-9 text-sm text-heading ring-1 ring-inset ring-strong focus:ring-2 focus:ring-inset focus:ring-focus" />
                    </div>

                    <div class="mb-3">
                        <label class="block text-xs font-medium text-muted">Assignment</label>
                        <select v-model="filterData.assignment" class="mt-1 block w-full rounded-md border-0 py-1.5 pl-2 pr-8 text-sm text-heading ring-1 ring-inset ring-strong">
                            <option value="all">Any assignment</option>
                            <option value="assigned">Assigned</option>
                            <option value="unassigned">Unassigned</option>
                        </select>
                    </div>

                    <p class="px-1 pb-1 text-xs font-medium uppercase text-subtle">Applications</p>
                    <nav class="max-h-[60vh] space-y-0.5 overflow-y-auto">
                        <button type="button" :class="applicationButtonClass('')" @click="selectedApplication = ''">
                            <span class="min-w-0 flex-1 truncate">All</span>
                            <span :class="applicationBadgeClass('')">{{ filteredRows.length }}</span>
                        </button>
                        <button v-for="application in applicationsWithCounts" :key="application.value" type="button" :class="applicationButtonClass(application.value)" @click="selectedApplication = application.value">
                            <span class="min-w-0 flex-1 truncate">{{ application.label }}</span>
                            <span :class="applicationBadgeClass(application.value)">{{ application.count }}</span>
                        </button>
                        <p v-if="!applicationsWithCounts.length" class="px-3 py-2 text-xs text-subtle">No matching applications</p>
                    </nav>
                </div>
            </aside>

            <section class="min-w-0 flex-1">
                <div class="rounded-lg bg-surface shadow-sm ring-1 ring-strong">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-default px-4 py-3">
                        <div>
                            <h2 class="text-base font-semibold text-heading">{{ selectedApplicationLabel }}</h2>
                            <p class="text-xs text-muted">{{ displayedRows.length }} permission{{ displayedRows.length === 1 ? '' : 's' }} shown</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button v-if="displayedRows.length" type="button" class="text-xs text-muted hover:text-heading" @click="toggleSelectAllVisible">
                                {{ allVisibleSelected ? 'Clear selection' : 'Select visible' }}
                            </button>
                            <div v-if="selectedItems.length" class="flex flex-wrap items-center gap-1 rounded-md bg-accent-subtle px-2 py-1 text-xs font-medium text-accent-fg">
                                <span>{{ selectedItems.length }} selected</span>
                                <button v-if="permissions.assign" type="button" class="rounded px-1.5 py-0.5 hover:bg-accent-subtle" @click="setAssignments(selectedItems, true)">Assign</button>
                                <button v-if="permissions.remove" type="button" class="rounded px-1.5 py-0.5 hover:bg-accent-subtle" @click="setAssignments(selectedItems, false)">Unassign</button>
                            </div>
                        </div>
                    </header>

                    <div v-if="loading" class="px-4 py-12">
                        <Loading :show="true" :absolute="false" />
                    </div>

                    <div v-else-if="sectionedRows.length" class="divide-y divide-default">
                        <section v-for="section in sectionedRows" :key="section.application" class="divide-y divide-default">
                            <header class="bg-surface-2 px-4 py-2">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="text-sm font-semibold text-heading">{{ section.application }}</h3>
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs text-muted">{{ section.assigned }} of {{ section.rows.length }}</span>
                                        <button
                                            v-if="canToggleSection"
                                            type="button"
                                            role="switch"
                                            :aria-checked="section.state === 'all'"
                                            :title="sectionToggleTitle(section)"
                                            :class="['relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-1', sectionTrackClass(section)]"
                                            @click="toggleSection(section)"
                                        >
                                            <span :class="['inline-block h-3.5 w-3.5 transform rounded-full bg-surface shadow transition', sectionThumbClass(section)]" />
                                        </button>
                                    </div>
                                </div>
                            </header>

                            <div class="grid grid-cols-1 gap-px bg-surface-3 md:grid-cols-2 xl:grid-cols-3">
                                <div v-for="row in section.rows" :key="row.permission_name" class="flex items-center gap-3 bg-surface px-4 py-2.5 transition hover:bg-surface-2">
                                    <input v-model="selectedItems" type="checkbox" :value="row.permission_name" class="h-4 w-4 rounded border-strong text-accent-fg focus:ring-focus" />

                                    <h4 class="min-w-0 flex-1 truncate text-sm font-medium text-heading" :title="row.permission_name">
                                        {{ formatPermissionLabel(row.permission_name) }}
                                    </h4>

                                    <button
                                        v-if="canToggleRow(row)"
                                        type="button"
                                        role="switch"
                                        :aria-checked="row.assigned"
                                        :title="row.assigned ? 'Unassign permission' : 'Assign permission'"
                                        :class="['relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-1', row.assigned ? 'bg-accent' : 'bg-surface-3']"
                                        @click="setAssignments([row.permission_name], !row.assigned)"
                                    >
                                        <span :class="['inline-block h-3.5 w-3.5 transform rounded-full bg-surface shadow transition', row.assigned ? 'translate-x-[18px]' : 'translate-x-0.5']" />
                                    </button>
                                    <span
                                        v-else
                                        :title="row.assigned ? 'Assigned' : 'Unassigned'"
                                        :class="['relative inline-flex h-5 w-9 shrink-0 items-center rounded-full opacity-60', row.assigned ? 'bg-accent' : 'bg-surface-3']"
                                    >
                                        <span :class="['inline-block h-3.5 w-3.5 transform rounded-full bg-surface shadow', row.assigned ? 'translate-x-[18px]' : 'translate-x-0.5']" />
                                    </span>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div v-else class="px-4 py-12 text-center">
                        <p class="text-sm font-medium text-heading">No permissions match your filters</p>
                        <p class="mt-1 text-xs text-muted">Try clearing search or assignment filters.</p>
                        <button type="button" class="mt-3 rounded-md bg-surface px-3 py-1.5 text-xs font-medium text-body shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2" @click="handleFiltersReset">Reset filters</button>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages" @update:show="notificationShow = false" />

    <GroupMembersModal :show="showMembersModal" :group="group" :routes="routes"
        @close="showMembersModal = false" @error="handleErrorResponse" @success="showNotification" />
</template>

<script setup>
import { computed, h, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import MainLayout from '../Layouts/MainLayout.vue'
import Loading from './components/general/Loading.vue'
import Notification from './components/notifications/Notification.vue'
import GroupMembersModal from './components/modal/GroupMembersModal.vue'
import { ArrowPathIcon, ArrowUturnLeftIcon, MagnifyingGlassIcon, UsersIcon } from '@heroicons/vue/24/outline'

const StatTile = (props) => {
    const toneMap = {
        gray: 'text-heading',
        green: 'text-success',
        rose: 'text-danger',
        indigo: 'text-accent-fg',
    }

    return h('div', { class: 'rounded-lg bg-surface p-3 shadow-sm ring-1 ring-strong' }, [
        h('p', { class: 'text-xs text-muted' }, props.label),
        h('p', { class: ['mt-0.5 text-xl font-semibold', toneMap[props.tone] || toneMap.gray] }, String(props.value ?? 0)),
    ])
}
StatTile.props = ['label', 'value', 'tone']

const props = defineProps({
    group: Object,
    routes: Object,
    permissions: Object,
})

const loading = ref(false)
const allRows = ref([])
const selectedItems = ref([])
const selectedApplication = ref('')
const filterData = ref({ search: '', assignment: 'all' })
const showMembersModal = ref(false)
const notificationShow = ref(false)
const notificationType = ref(null)
const notificationMessages = ref(null)

const stats = computed(() => {
    const total = allRows.value.length
    const assigned = allRows.value.filter(row => row.assigned).length
    const applications = new Set(allRows.value.map(row => row.application_name || 'Uncategorized')).size

    return {
        total,
        assigned,
        unassigned: total - assigned,
        applications,
    }
})

const filteredRows = computed(() => {
    const search = filterData.value.search.trim().toLowerCase()

    return allRows.value.filter(row => {
        if (filterData.value.assignment === 'assigned' && !row.assigned) return false
        if (filterData.value.assignment === 'unassigned' && row.assigned) return false

        if (search) {
            const haystack = [row.application_name, row.permission_name, formatPermissionLabel(row.permission_name)]
                .filter(Boolean)
                .join(' ')
                .toLowerCase()

            if (!haystack.includes(search)) return false
        }

        return true
    })
})

const applicationsWithCounts = computed(() => {
    const counts = new Map()

    for (const row of filteredRows.value) {
        const application = row.application_name || 'Uncategorized'
        counts.set(application, (counts.get(application) || 0) + 1)
    }

    return Array.from(counts.entries())
        .map(([value, count]) => ({ value, label: value, count }))
        .sort((a, b) => a.label.localeCompare(b.label))
})

const displayedRows = computed(() => {
    const rows = selectedApplication.value
        ? filteredRows.value.filter(row => (row.application_name || 'Uncategorized') === selectedApplication.value)
        : filteredRows.value

    return [...rows].sort((a, b) => {
        const appA = String(a.application_name || '').toLowerCase()
        const appB = String(b.application_name || '').toLowerCase()
        if (appA !== appB) return appA.localeCompare(appB)

        return String(a.permission_name || '').localeCompare(String(b.permission_name || ''))
    })
})

const sectionedRows = computed(() => {
    const sections = new Map()

    for (const row of displayedRows.value) {
        const application = row.application_name || 'Uncategorized'

        if (!sections.has(application)) {
            sections.set(application, [])
        }

        sections.get(application).push(row)
    }

    return Array.from(sections.entries()).map(([application, rows]) => {
        const assigned = rows.filter(row => row.assigned).length
        let state = 'none'
        if (assigned === rows.length) state = 'all'
        else if (assigned > 0) state = 'some'

        return { application, rows, assigned, state }
    })
})

const canToggleSection = computed(() => Boolean(props.permissions?.assign && props.permissions?.remove))

const selectedApplicationLabel = computed(() => selectedApplication.value || 'All permissions')

const allVisibleSelected = computed(() => {
    if (!displayedRows.value.length) return false
    return displayedRows.value.every(row => selectedItems.value.includes(row.permission_name))
})

watch([selectedApplication, () => filterData.value.search, () => filterData.value.assignment], () => {
    selectedItems.value = []
})

watch(applicationsWithCounts, (applications) => {
    if (!selectedApplication.value) return
    if (!applications.find(application => application.value === selectedApplication.value)) selectedApplication.value = ''
})

onMounted(() => getData())

const getData = () => {
    loading.value = true

    axios.get(props.routes.data_route)
        .then(response => {
            allRows.value = response.data?.data || []
            selectedItems.value = []
        })
        .catch(handleErrorResponse)
        .finally(() => loading.value = false)
}

const handleFiltersReset = () => {
    filterData.value = { search: '', assignment: 'all' }
    selectedApplication.value = ''
}

const toggleSelectAllVisible = () => {
    const names = displayedRows.value.map(row => row.permission_name)

    if (allVisibleSelected.value) {
        selectedItems.value = selectedItems.value.filter(permissionName => !names.includes(permissionName))
        return
    }

    const set = new Set(selectedItems.value)
    names.forEach(permissionName => set.add(permissionName))
    selectedItems.value = Array.from(set)
}

const setAssignments = (items, assigned) => {
    if (!items.length) return

    axios.post(props.routes.toggle, { items, assigned })
        .then(response => {
            const changed = new Set(items)
            allRows.value = allRows.value.map(row =>
                changed.has(row.permission_name) ? { ...row, assigned } : row
            )
            selectedItems.value = selectedItems.value.filter(name => !changed.has(name))
            showNotification('success', response.data.messages)
        })
        .catch(handleErrorResponse)
}

const reloadPermissions = () => {
    axios.post(props.routes.reload)
        .then(response => showNotification('success', response.data.messages))
        .catch(handleErrorResponse)
}

const canToggleRow = (row) => row.assigned ? props.permissions?.remove : props.permissions?.assign

const formatPermissionLabel = (permissionName) => {
    if (!permissionName) return '-'
    return String(permissionName)
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, character => character.toUpperCase())
}

const applicationButtonClass = (value) => {
    const active = selectedApplication.value === value

    return [
        'flex w-full items-center justify-between gap-2 rounded-md px-3 py-1.5 text-left text-sm transition',
        active ? 'bg-accent-subtle font-semibold text-accent-fg' : 'text-body hover:bg-surface-2',
    ]
}

const applicationBadgeClass = (value) => {
    const active = selectedApplication.value === value

    return [
        'inline-flex min-w-6 shrink-0 items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-medium tabular-nums',
        active ? 'bg-accent-subtle text-accent-fg' : 'bg-surface-3 text-body',
    ]
}

const toggleSection = (section) => {
    if (!canToggleSection.value) return

    const targetAssigned = section.state !== 'all'
    const items = section.rows
        .filter(row => row.assigned !== targetAssigned)
        .map(row => row.permission_name)

    setAssignments(items, targetAssigned)
}

const sectionToggleTitle = (section) => {
    if (section.state === 'all') return `Unassign all in ${section.application}`
    if (section.state === 'some') return `Unassign all in ${section.application} (partially assigned)`
    return `Assign all in ${section.application}`
}

const sectionTrackClass = (section) => {
    if (section.state === 'all') return 'bg-accent'
    if (section.state === 'some') return 'bg-accent'
    return 'bg-surface-3'
}

const sectionThumbClass = (section) => {
    if (section.state === 'all') return 'translate-x-[18px]'
    if (section.state === 'some') return 'translate-x-[9px]'
    return 'translate-x-0.5'
}

const showNotification = (type, messages) => {
    notificationType.value = type
    notificationMessages.value = messages
    notificationShow.value = true
}

const handleErrorResponse = (error) => {
    showNotification('error', error?.response?.data?.messages || error?.response?.data?.errors || { error: ['Request failed.'] })
}
</script>
