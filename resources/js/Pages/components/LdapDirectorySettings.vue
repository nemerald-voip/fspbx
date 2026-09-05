<template>
    <div>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">{{ $t('Directory Services') }}</h2>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">
                    {{ $t('Connect Microsoft Active Directory or another LDAP-compatible directory to import users and groups on a schedule, so accounts stay in step with your directory.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="secondary-button" :disabled="refreshing" @click="refreshDirectories">
                    <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': refreshing }" aria-hidden="true" />
                    {{ $t('Refresh') }}
                </button>
                <button v-if="permissions.create" type="button" class="primary-button" @click="startCreate">
                    <PlusIcon class="h-4 w-4" aria-hidden="true" />
                    {{ $t('Add Directory') }}
                </button>
            </div>
        </div>

        <ScheduledJobServerControl :initial-state="activeNode" :routes="settings.routes"
            :manage="permissions.manage_active_node" @success="messages => emit('success', messages)"
            @error="messages => emit('error', messages)" />

        <div v-if="!directories.length"
            class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-6 py-12 text-center">
            <ServerStackIcon class="mx-auto h-9 w-9 text-gray-400" aria-hidden="true" />
            <h3 class="mt-3 text-sm font-semibold text-gray-900">{{ $t('No directory connections') }}</h3>
            <p class="mx-auto mt-1 max-w-md text-sm text-gray-500">
                {{ $t('Add a connection to import users and groups from your directory. You choose which attributes map to local user fields, whether missing extensions are created, and how often the import runs.') }}
            </p>
            <button v-if="permissions.create" type="button" class="primary-button mx-auto mt-4" @click="startCreate">
                <PlusIcon class="h-4 w-4" aria-hidden="true" />
                {{ $t('Add Directory') }}
            </button>
        </div>

        <div v-else class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">{{ $t('Directory') }}</th>
                        <th class="px-4 py-3">{{ $t('Connection') }}</th>
                        <th class="px-4 py-3">{{ $t('Last Sync') }}</th>
                        <th class="px-4 py-3">{{ $t('Imported') }}</th>
                        <th class="px-4 py-3 text-right"><span class="sr-only">{{ $t('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-for="directory in directories" :key="directory.directory_uuid" class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <button v-if="permissions.update" type="button"
                                class="text-left font-medium text-indigo-700 hover:text-indigo-900"
                                @click="startEdit(directory)">
                                {{ directory.name }}
                            </button>
                            <span v-else class="font-medium text-gray-900">{{ directory.name }}</span>
                            <div class="mt-0.5 max-w-xs truncate text-xs text-gray-500" :title="directoryTypeLabel(directory.type)">
                                {{ directoryTypeLabel(directory.type) }}
                            </div>
                            <div class="mt-0.5 max-w-xs truncate text-xs text-gray-500" :title="directory.hosts">
                                {{ directory.hosts }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                                <span class="h-2 w-2 rounded-full" :class="statusColor(directory.connection_status)" />
                                {{ connectionLabel(directory.connection_status) }}
                            </div>
                            <div class="mt-1 text-xs text-gray-500"
                                :title="directory.enabled ? $t('Scheduled synchronization is active.') : $t('Scheduled synchronization is paused. You can still run it manually.')">
                                {{ directory.enabled ? $t('Enabled') : $t('Disabled') }}
                            </div>
                            <p v-if="directory.connection_status === 'failed' && directory.connection_message"
                                class="mt-1 max-w-xs truncate text-xs text-red-600" :title="directory.connection_message">
                                {{ directory.connection_message }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <div v-if="directory.latest_sync_run?.status === 'running'"
                                class="inline-flex items-center gap-1.5 font-medium"
                                :class="isRunStale(directory.latest_sync_run) ? 'text-amber-700' : 'text-blue-700'">
                                <span class="h-2 w-2 rounded-full"
                                    :class="isRunStale(directory.latest_sync_run) ? 'bg-amber-500' : 'bg-blue-500'" />
                                {{ runningRunLabel(directory.latest_sync_run) }}
                            </div>
                            <div v-else class="inline-flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full" :class="statusColor(directory.last_sync_status)" />
                                {{ syncStatusLabel(directory.last_sync_status) }}
                            </div>
                            <div v-if="directory.last_sync_at" class="mt-1 text-xs">{{ formatDate(directory.last_sync_at) }}</div>
                            <p v-if="directory.last_sync_status === 'failed' && directory.last_sync_message"
                                class="mt-1 max-w-xs truncate text-xs text-red-600" :title="directory.last_sync_message">
                                {{ directory.last_sync_message }}
                            </p>
                            <div v-else class="mt-1 text-xs text-gray-500">{{ scheduleLabel(directory) }}</div>
                            <div v-if="directory.latest_sync_run?.node_name && directory.latest_sync_run?.status !== 'running'"
                                class="mt-1 text-xs text-gray-500">
                                {{ $t('Ran on :node', { node: directory.latest_sync_run.node_name }) }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ directory.directory_users_count }} {{ $t('users') }}
                            <div class="text-xs">{{ directory.directory_groups_count }} {{ $t('groups') }}</div>
                            <div v-if="directory.owned_users_count" class="mt-1 text-xs text-gray-500">
                                {{ $t(':count local users created', { count: directory.owned_users_count }) }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                                <button v-if="permissions.update" type="button" class="row-action"
                                    :title="$t('Edit connection, attribute mapping, and schedule')" :aria-label="$t('Edit')"
                                    @click="startEdit(directory)">
                                    <PencilSquareIcon class="h-5 w-5" aria-hidden="true" />
                                </button>
                                <button v-if="permissions.test" type="button" class="row-action"
                                    :title="$t('Test the connection and bind credentials')" :aria-label="$t('Test')"
                                    :disabled="busy === directory.directory_uuid"
                                    @click="runDirectoryAction(directory, 'test')">
                                    <SignalIcon class="h-5 w-5"
                                        :class="{ 'animate-pulse': busy === directory.directory_uuid && busyAction === 'test' }"
                                        aria-hidden="true" />
                                </button>
                                <button v-if="permissions.sync && directory.enabled" type="button" class="row-action"
                                    :title="$t('Import users and groups now')" :aria-label="$t('Sync')"
                                    :disabled="busy === directory.directory_uuid"
                                    @click="runDirectoryAction(directory, 'sync')">
                                    <ArrowPathIcon class="h-5 w-5"
                                        :class="{ 'animate-spin': busy === directory.directory_uuid && busyAction === 'sync' }"
                                        aria-hidden="true" />
                                </button>
                                <button v-if="permissions.delete" type="button" class="row-action-danger"
                                    :title="$t('Delete this connection and its imported users')" :aria-label="$t('Delete')"
                                    @click="requestDelete(directory)">
                                    <TrashIcon class="h-5 w-5" aria-hidden="true" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <LdapDirectoryForm :show="showDirectoryModal" :directory="selectedDirectory" :settings="settings"
            :mapping-data="mappingData" :mappings-loading="mappingsLoading" @close="closeDirectoryModal"
            @success="messages => emit('success', messages)" @error="messages => emit('error', messages)"
            @refresh="refreshDirectories" />

        <ConfirmationModal :show="showDeleteConfirmation" :header="$t('Delete directory connection')"
            :text="deleteConfirmationText"
            :loading="deleteBusy" :confirm-button-label="$t('Delete')" :cancel-button-label="$t('Cancel')"
            @close="cancelDelete" @confirm="confirmDelete" />
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import {
    ArrowPathIcon,
    PlusIcon,
    ServerStackIcon,
    SignalIcon,
} from '@heroicons/vue/24/outline'
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/solid'
import { trans, transChoice } from '@i18n'
import LdapDirectoryForm from './forms/LdapDirectoryForm.vue'
import ScheduledJobServerControl from './ScheduledJobServerControl.vue'
import ConfirmationModal from './modal/ConfirmationModal.vue'

const props = defineProps({ settings: { type: Object, required: true } })
const emit = defineEmits(['success', 'error'])
const directoryRows = ref([...(props.settings?.directories ?? [])])
const directories = computed(() => directoryRows.value)
const activeNode = ref({ ...(props.settings?.active_node ?? {}) })
const permissions = computed(() => props.settings?.permissions ?? {})
const busy = ref(null)
const busyAction = ref(null)
const refreshing = ref(false)
const showDirectoryModal = ref(false)
const selectedDirectory = ref(null)
const mappingData = ref(null)
const mappingsLoading = ref(false)
const showDeleteConfirmation = ref(false)
const pendingDelete = ref(null)
const deleteBusy = ref(false)
const deleteConfirmationText = computed(() => {
    const userCount = Number(pendingDelete.value?.owned_users_count ?? 0)
    const groupCount = Number(pendingDelete.value?.directory_groups_count ?? 0)
    const preserved = trans('Existing local users, extensions, roles, and history will be preserved.')
    const users = transChoice(':count imported user|:count imported users', userCount, { count: userCount })
    const groups = transChoice(':count imported group|:count imported groups', groupCount, { count: groupCount })

    return `${trans('Delete this directory connection, :users, and :groups?', { users, groups })} ${preserved}`
})

watch(() => props.settings?.directories, value => {
    directoryRows.value = [...(value ?? [])]
})

watch(() => props.settings?.active_node, value => {
    activeNode.value = { ...(value ?? {}) }
}, { deep: true })

const messageBag = message => ({ server: [message] })
const formatDate = value => value ? new Date(value).toLocaleString() : '—'
const directoryTypeLabel = type => type === 'ldap' ? trans('Generic LDAP') : trans('Microsoft Active Directory')

const connectionLabel = status => {
    if (status === 'connected') return trans('Connected')
    if (status === 'failed') return trans('Connection failed')

    return trans('Not tested')
}

const syncStatusLabel = status => {
    if (status === 'completed') return trans('Completed')
    if (status === 'failed') return trans('Failed')

    return trans('Never synchronized')
}

const scheduleLabel = directory => {
    if (!directory.enabled) return trans('Manual synchronization only')
    const minutes = Number(directory.sync_interval_minutes ?? 0)
    if (minutes <= 0) return trans('Manual synchronization only')

    return directory.next_sync_at
        ? trans('Every :count min, next :time', { count: minutes, time: formatDate(directory.next_sync_at) })
        : trans('Every :count min', { count: minutes })
}
const statusColor = status => ['connected', 'completed'].includes(status)
    ? 'bg-green-500'
    : status === 'failed' ? 'bg-red-500' : 'bg-gray-400'

const isRunStale = run => {
    if (run?.execution) {
        return run.execution.status !== 'running' || new Date(run.execution.expires_at).getTime() <= Date.now()
    }
    const startedAt = run?.started_at ? new Date(run.started_at).getTime() : 0

    return Boolean(startedAt && Date.now() - startedAt > 15 * 60 * 1000)
}

const runningRunLabel = run => {
    const node = run?.node_name || trans('Unknown node')

    return isRunStale(run)
        ? trans('Stale on :node', { node })
        : trans('Running on :node', { node })
}

const startCreate = () => {
    selectedDirectory.value = null
    mappingData.value = null
    showDirectoryModal.value = true
}

const startEdit = directory => {
    selectedDirectory.value = { ...directory }
    mappingData.value = null
    showDirectoryModal.value = true
    if (permissions.value.map_groups) loadMappings()
}

const closeDirectoryModal = () => {
    showDirectoryModal.value = false
    selectedDirectory.value = null
    mappingData.value = null
}

const loadMappings = async () => {
    if (!selectedDirectory.value?.routes?.mappings) return
    mappingsLoading.value = true
    try {
        const response = await axios.get(selectedDirectory.value.routes.mappings)
        mappingData.value = response.data
    } catch (error) {
        emit('error', error.response?.data?.errors || messageBag(error.response?.data?.message || error.message))
    } finally {
        mappingsLoading.value = false
    }
}

const refreshDirectories = async () => {
    if (!props.settings?.routes?.index || refreshing.value) return

    refreshing.value = true
    try {
        const response = await axios.get(props.settings.routes.index)
        directoryRows.value = response.data.directories ?? []
        activeNode.value = { ...(response.data.active_node ?? {}) }
    } catch (error) {
        emit('error', error.response?.data?.errors || messageBag(error.response?.data?.message || error.message))
    } finally {
        refreshing.value = false
    }
}

const runDirectoryAction = async (directory, action) => {
    busy.value = directory.directory_uuid
    busyAction.value = action
    try {
        const response = await axios.post(directory.routes[action])
        emit('success', messageBag(response.data.message))
        refreshDirectories()
    } catch (error) {
        emit('error', error.response?.data?.errors || messageBag(error.response?.data?.message || error.message))
    } finally {
        busy.value = null
        busyAction.value = null
    }
}

const requestDelete = directory => {
    pendingDelete.value = directory
    showDeleteConfirmation.value = true
}

const cancelDelete = () => {
    showDeleteConfirmation.value = false
    pendingDelete.value = null
}

const confirmDelete = async () => {
    if (!pendingDelete.value) return
    deleteBusy.value = true
    try {
        const response = await axios.delete(pendingDelete.value.routes.destroy)
        emit('success', messageBag(response.data.message))
        cancelDelete()
        refreshDirectories()
    } catch (error) {
        emit('error', error.response?.data?.errors || messageBag(error.response?.data?.message || error.message))
    } finally {
        deleteBusy.value = false
    }
}
</script>

<style scoped>
.primary-button { @apply inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50; }
.secondary-button { @apply inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50; }
.row-action { @apply inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-indigo-600 active:bg-gray-300 disabled:cursor-not-allowed disabled:opacity-50; }
.row-action-danger { @apply inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-400 transition hover:bg-red-50 hover:text-red-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-red-600 active:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50; }
</style>
