<template>
    <section class="mb-5 rounded-lg border border-gray-200 bg-white shadow-sm" aria-labelledby="scheduled-job-server-heading">
        <div class="flex flex-wrap items-start justify-between gap-3 px-4 py-3"
            :class="{ 'border-b border-gray-200': !compact }">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <ServerStackIcon class="h-5 w-5 text-gray-500" aria-hidden="true" />
                    <h3 id="scheduled-job-server-heading" class="text-sm font-semibold text-gray-900">
                        {{ $t('Scheduled job server') }}
                    </h3>
                    <span :class="statusBadgeClass">
                        <span class="h-2 w-2 rounded-full" :class="statusDotClass" />
                        {{ statusLabel }}
                    </span>
                </div>
                <p class="mt-1 max-w-3xl text-xs text-gray-500">{{ state.reason }}</p>
                <p class="mt-1 max-w-3xl text-xs text-gray-500">
                    <template v-if="compact">
                        {{ $t('Directory synchronization runs here. Add a second server for redundancy to choose which one runs scheduled jobs.') }}
                    </template>
                    <template v-else>
                        {{ $t('Only the selected server runs directory synchronization and other coordinated scheduled jobs, so the same records are never written twice.') }}
                    </template>
                </p>
            </div>
            <button type="button" class="secondary-button" :disabled="statusLoading" @click="loadStatus()">
                <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': statusLoading }" aria-hidden="true" />
                {{ $t('Refresh status') }}
            </button>
        </div>

        <dl v-if="!compact" class="grid gap-px bg-gray-200 sm:grid-cols-3">
            <div class="bg-gray-50 px-4 py-3">
                <dt class="text-xs font-medium text-gray-500">{{ $t('Job owner') }}</dt>
                <dd class="mt-1 truncate text-sm font-semibold text-gray-900" :title="ownerNode?.endpoint">
                    {{ nodeLabel(ownerNode, state.configured) }}
                </dd>
                <p class="mt-0.5 text-[11px] text-gray-500">{{ $t('The only server allowed to run these jobs.') }}</p>
                <p v-if="ownerNode" class="mt-0.5 truncate font-mono text-[11px] text-gray-400" :title="ownerNode.id">{{ ownerNode.id }}</p>
            </div>
            <div class="bg-gray-50 px-4 py-3">
                <dt class="text-xs font-medium text-gray-500">{{ $t('This server') }}</dt>
                <dd class="mt-1 truncate text-sm font-semibold text-gray-900" :title="localNode?.endpoint">
                    {{ nodeLabel(localNode, state.this_node) }}
                </dd>
                <p class="mt-0.5 text-[11px] text-gray-500">{{ $t('The server that answered this page.') }}</p>
                <p v-if="localNode" class="mt-0.5 truncate font-mono text-[11px] text-gray-400" :title="localNode.id">{{ localNode.id }}</p>
            </div>
            <div class="bg-gray-50 px-4 py-3">
                <dt class="text-xs font-medium text-gray-500">{{ $t('Ownership version') }}</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ state.generation ?? 0 }}</dd>
                <p class="mt-0.5 text-[11px] text-gray-500">
                    {{ $t('Counts up on every transfer, so work queued by a previous owner is rejected instead of running twice.') }}
                </p>
            </div>
        </dl>

        <div v-if="state.handoff" class="border-t border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            <div class="flex flex-wrap items-center gap-2 font-medium">
                <ArrowRightIcon class="h-4 w-4 shrink-0" aria-hidden="true" />
                {{ $t('Transfer in progress') }}: {{ nodeLabel(nodeById(state.handoff.from_node_id), state.handoff.from_node_id) }}
                <span aria-hidden="true">&rarr;</span>
                {{ nodeLabel(nodeById(state.handoff.to_node_id), state.handoff.to_node_id) }}
            </div>
            <p class="mt-1 text-xs">
                {{ $t('The current owner is finishing its running jobs. The new owner starts once the change reaches it, so there is a short pause instead of an overlap.') }}
            </p>
        </div>
        <p v-else-if="awaitingReplication" class="border-t border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800" role="status">
            {{ $t('Transfer accepted. Waiting for database replication to deliver the new owner to this server. Use Refresh status to check again.') }}
        </p>

        <div v-if="state.legacy_match" class="border-t border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-medium">{{ $t('This installation still selects the owner by hostname or IP address.') }}</p>
            <p class="mt-1 text-xs">
                {{ $t('That value matches one approved server. Select it below and transfer ownership to store its verified database identity instead. Nothing moves and no jobs are interrupted.') }}
            </p>
        </div>

        <div v-if="runningExecutions.length" class="border-t border-gray-200 px-4 py-3">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $t('Running now') }}</h4>
            <ul class="mt-2 space-y-1.5">
                <li v-for="execution in runningExecutions" :key="execution.id" class="flex flex-wrap items-center gap-x-2 text-xs text-gray-700">
                    <span class="h-2 w-2 rounded-full bg-blue-500" />
                    <span class="font-medium">{{ executionLabel(execution) }}</span>
                    <span>{{ $t('Running on :node', { node: nodeLabel(nodeById(execution.node_id), execution.node_id) }) }}</span>
                    <span class="text-gray-400">{{ formatDate(execution.started_at) }}</span>
                </li>
            </ul>
        </div>

        <div v-if="manage" class="space-y-4 border-t border-gray-200 px-4 py-4">
            <div v-if="!state.secret_configured && !compact" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                <p class="font-medium">{{ $t('Servers cannot verify each other yet.') }}</p>
                <p class="mt-1 text-xs">
                    {{ $t('Create the shared coordination secret on one server only, wait for it to replicate, then discover the other server. Two separate secrets prevent the pair from talking.') }}
                </p>
                <button type="button" class="mt-2 secondary-button" :disabled="working" @click="rotateSecret">
                    {{ $t('Create coordination secret') }}
                </button>
            </div>

            <div v-if="approvedNodes.length" class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div>
                    <label for="scheduled-job-owner" class="block text-xs font-medium text-gray-700">{{ $t('Transfer ownership to') }}</label>
                    <select id="scheduled-job-owner" v-model="selectedOwner" :disabled="working"
                        class="mt-1 block w-full rounded-md border-gray-300 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        aria-describedby="scheduled-job-owner-help"
                        @focus="ownerFocused = true" @blur="ownerFocused = false">
                        <option value="">{{ $t('Select an approved server') }}</option>
                        <option v-for="node in approvedNodes" :key="node.id" :value="node.id">
                            {{ nodeOptionLabel(node) }}
                        </option>
                    </select>
                    <p id="scheduled-job-owner-help" class="mt-1 text-xs text-gray-500">
                        {{ $t('The current owner finishes its running jobs before the new server takes over.') }}
                    </p>
                </div>
                <button type="button" class="primary-button lg:mb-6" :disabled="!canTransfer" @click="transferOwner">
                    {{ working ? $t('Working…') : $t('Transfer ownership') }}
                </button>
            </div>

            <details class="rounded-md border border-gray-200 bg-gray-50">
                <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-gray-800">
                    {{ compact ? $t('Add a second server') : $t('Manage scheduled-job nodes') }}
                </summary>
                <div class="space-y-4 border-t border-gray-200 p-3">
                    <p class="text-xs text-gray-600">
                        {{ $t('Discovery only lists candidates. Every server must pass a signed identity check over HTTPS and be approved by a super administrator before it can run scheduled jobs.') }}
                    </p>

                    <div v-if="!state.secret_configured" class="rounded-md border border-gray-200 bg-white p-3">
                        <p class="text-xs font-medium text-gray-900">{{ $t('Step 1: create the shared coordination secret') }}</p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $t('Create it on one server only and wait for it to replicate. Two separate secrets prevent the pair from talking.') }}
                        </p>
                        <button type="button" class="mt-2 secondary-button" :disabled="working" @click="rotateSecret">
                            {{ $t('Create coordination secret') }}
                        </button>
                    </div>

                    <div>
                        <label for="scheduled-job-manual-endpoint" class="block text-xs font-medium text-gray-700">
                            {{ $t('Direct HTTPS address (optional)') }}
                        </label>
                        <div class="mt-1 flex flex-col gap-2 sm:flex-row">
                            <input id="scheduled-job-manual-endpoint" v-model.trim="manualEndpoint" type="url"
                                :placeholder="$t('https://server-address')"
                                class="min-w-0 flex-1 rounded-md border-gray-300 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <button type="button" class="secondary-button" :disabled="working || !state.secret_configured" @click="discoverNodes">
                                {{ discovering ? $t('Discovering…') : $t('Discover servers') }}
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ state.secret_configured
                                ? $t('Leave this empty to check the hosts already listed in PostgreSQL replication. Enter an address to reach a replacement server directly.')
                                : $t('Create the coordination secret first. Discovery needs it to sign the identity check.') }}
                        </p>
                    </div>

                    <div v-if="candidates.length">
                        <h5 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $t('Discovered servers') }}</h5>
                        <ul class="mt-2 divide-y divide-gray-200 rounded-md border border-gray-200 bg-white">
                            <li v-for="candidate in candidates" :key="candidate.endpoint" class="flex flex-wrap items-center justify-between gap-3 px-3 py-2.5">
                                <div class="min-w-0 text-xs">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-gray-900">{{ candidate.hostname || candidate.endpoint }}</span>
                                        <span :class="candidate.reachable && !candidate.duplicate_identity ? 'text-green-700' : 'text-rose-700'">
                                            {{ candidateStatus(candidate) }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 truncate text-gray-500">{{ candidate.endpoint }}</p>
                                    <p v-if="candidate.system_identifier" class="mt-0.5 truncate font-mono text-gray-400">{{ candidate.system_identifier }}</p>
                                    <p v-if="candidate.message" class="mt-1 text-amber-700">{{ candidate.message }}</p>
                                </div>
                                <button v-if="candidate.reachable && !candidate.duplicate_identity && !candidate.registered"
                                    type="button" class="secondary-button" :disabled="working" @click="approveNode(candidate)">
                                    {{ $t('Approve') }}
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div v-if="state.nodes?.length">
                        <h5 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $t('Registered servers') }}</h5>
                        <ul class="mt-2 divide-y divide-gray-200 rounded-md border border-gray-200 bg-white">
                            <li v-for="node in state.nodes" :key="node.id" class="flex flex-wrap items-center justify-between gap-3 px-3 py-2.5">
                                <div class="min-w-0 text-xs">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-gray-900">{{ node.hostname }}</span>
                                        <span v-if="node.local" class="text-indigo-700">{{ $t('This server') }}</span>
                                        <span v-if="node.selected" class="text-green-700">{{ $t('Job owner') }}</span>
                                        <span v-if="node.status === 'unapproved'" class="text-amber-700">{{ $t('Not approved') }}</span>
                                        <span v-else-if="node.status === 'retired'" class="text-gray-500">{{ $t('Retired') }}</span>
                                        <span v-else :class="node.reachable ? 'text-green-700' : 'text-rose-700'">
                                            {{ node.reachable ? $t('Reachable') : $t('Unreachable') }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 truncate text-gray-500">{{ node.endpoint }}</p>
                                    <p class="mt-0.5 truncate font-mono text-gray-400">{{ node.id }}</p>
                                </div>
                                <button v-if="node.status === 'approved' && !node.selected" type="button" class="danger-link"
                                    :disabled="working" :title="$t('Stop using this server for scheduled jobs')" @click="retireNode(node)">
                                    {{ $t('Retire') }}
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div v-if="state.secret_configured" class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 pt-3">
                        <p class="text-xs text-gray-500">{{ $t('Rotate the secret after replacing or retiring a server.') }}</p>
                        <button type="button" class="text-xs font-medium text-gray-600 hover:text-gray-900" :disabled="working" @click="rotateSecret">
                            {{ $t('Rotate coordination secret') }}
                        </button>
                    </div>
                </div>
            </details>

            <details v-if="ownerNode && forceTargetNodeId && forceTargetNodeId !== state.effective_owner" class="rounded-md border border-rose-200 bg-rose-50">
                <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-rose-800">{{ $t('Forced takeover') }}</summary>
                <div class="space-y-3 border-t border-rose-200 p-3 text-xs text-rose-900">
                    <p>
                        {{ $t('A forced takeover skips the safe handoff. Use it only when the current owner is powered off or cut off from the network, and keep it that way until replication is verified. If both servers run jobs at once, replication can stall and needs manual repair.') }}
                    </p>
                    <p v-if="forceTargetNodeId !== state.this_node" class="font-medium">
                        {{ $t('Ownership can only be forced from the server that is taking over. Open this page on that server.') }}
                    </p>
                    <div>
                        <label for="fenced-owner-endpoint" class="block font-medium">{{ $t('Type the old owner address to confirm') }}</label>
                        <input id="fenced-owner-endpoint" v-model.trim="fencedEndpoint" type="text" :placeholder="ownerNode.endpoint"
                            class="mt-1 block w-full rounded-md border-rose-300 py-1.5 text-sm focus:border-rose-500 focus:ring-rose-500" />
                    </div>
                    <label class="flex items-start gap-2">
                        <input v-model="fenceConfirmed" type="checkbox" class="mt-0.5 rounded border-rose-300 text-rose-600 focus:ring-rose-600" />
                        <span>{{ $t('I confirm the old owner is powered off or network-fenced.') }}</span>
                    </label>
                    <button type="button" class="danger-button" :disabled="!canForce" @click="forceTakeover">
                        {{ $t('Force takeover') }}
                    </button>
                </div>
            </details>
        </div>
    </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import { ArrowPathIcon, ArrowRightIcon, ServerStackIcon } from '@heroicons/vue/24/outline'
import { trans } from '@i18n'

const props = defineProps({
    initialState: { type: Object, default: () => ({}) },
    routes: { type: Object, required: true },
    manage: { type: Boolean, default: false },
})
const emit = defineEmits(['success', 'error'])

const attentionSources = ['inconsistent', 'database', 'unrecognized', 'retired', 'legacy', 'identity_mismatch']

const state = ref({ ...props.initialState })
const selectedOwner = ref(props.initialState?.effective_owner || '')
const ownerFocused = ref(false)
const working = ref(false)
const statusLoading = ref(false)
const discovering = ref(false)
const manualEndpoint = ref('')
const candidates = ref([])
const fencedEndpoint = ref('')
const fenceConfirmed = ref(false)
const transferRequest = ref(null)
const acknowledgedHandoff = ref(null)
const awaitingReplication = computed(() => acknowledgedHandoff.value
    && state.value.generation <= acknowledgedHandoff.value.expected_generation
    && acknowledgedHandoff.value.from_node_id !== acknowledgedHandoff.value.to_node_id)

const messageBag = message => ({ server: [message] })
const approvedNodes = computed(() => (state.value.nodes || []).filter(node => node.status === 'approved'))
const ownerNode = computed(() => nodeById(state.value.effective_owner))
const localNode = computed(() => nodeById(state.value.this_node))
const runningExecutions = computed(() => state.value.executions || [])
const forceTargetNodeId = computed(() => state.value.handoff?.to_node_id || selectedOwner.value || '')
const selectionDirty = computed(() => selectedOwner.value !== (state.value.effective_owner || ''))
const canTransfer = computed(() => selectedOwner.value && (selectionDirty.value || state.value.legacy_match || transferRequest.value)
    && !working.value && !state.value.handoff && !awaitingReplication.value)
const canForce = computed(() => forceTargetNodeId.value && forceTargetNodeId.value !== state.value.effective_owner
    && forceTargetNodeId.value === state.value.this_node
    && !working.value && fenceConfirmed.value && fencedEndpoint.value === (ownerNode.value?.endpoint || ''))

// A standalone install has nothing to coordinate: collapse the ownership detail
// so the panel stops reading like a half-finished configuration.
const needsAttention = computed(() => attentionSources.includes(state.value.source)
    || Boolean(state.value.handoff)
    || (Boolean(ownerNode.value) && ownerNode.value.reachable === false))
const compact = computed(() => state.value.source === 'standalone' && !needsAttention.value)

const statusLabel = computed(() => {
    if (state.value.status === 'draining') return trans('Draining')
    if (state.value.handoff) return trans('Transfer in progress')
    if (ownerNode.value && !ownerNode.value.reachable) return trans('Unreachable')
    if (compact.value) return trans('Single server')
    if (state.value.active) return trans('Active on this server')
    if (state.value.status === 'standby') return trans('Standby')
    return trans('Ownership unknown')
})
const statusDotClass = computed(() => state.value.status === 'draining'
    ? 'bg-blue-500'
    : ownerNode.value && !ownerNode.value.reachable ? 'bg-rose-500'
    : state.value.active ? 'bg-green-500' : state.value.status === 'standby' ? 'bg-gray-400' : 'bg-amber-500')
const statusBadgeClass = computed(() => [
    'inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset',
    state.value.active ? 'bg-green-50 text-green-700 ring-green-600/20'
        : state.value.status === 'draining' ? 'bg-blue-50 text-blue-700 ring-blue-600/20'
            : ownerNode.value && !ownerNode.value.reachable ? 'bg-rose-50 text-rose-700 ring-rose-600/20'
            : 'bg-gray-50 text-gray-700 ring-gray-300',
])

watch(() => props.initialState, value => applyState(value), { deep: true })
watch(selectedOwner, () => { transferRequest.value = null })

function nodeById(id) {
    return (state.value.nodes || []).find(node => node.id === id) || null
}

function nodeLabel(node, fallback = '') {
    if (node) return node.hostname || node.endpoint || node.id
    return fallback || trans('Not selected')
}

function nodeOptionLabel(node) {
    const marks = []
    if (node.local) marks.push(trans('this server'))
    if (node.selected) marks.push(trans('current owner'))
    if (!node.reachable) marks.push(trans('unreachable'))
    return marks.length ? `${node.hostname} (${marks.join(', ')})` : node.hostname
}

function candidateStatus(candidate) {
    if (candidate.duplicate_identity) return trans('Duplicate identity')
    if (!candidate.reachable) return trans('Unreachable')
    if (candidate.registered === 'approved') return trans('Approved')
    if (candidate.registered === 'retired') return trans('Retired')
    return trans('Verified')
}

function executionLabel(execution) {
    return execution.job_type === 'ldap_directory_sync' ? trans('LDAP directory sync') : execution.job_type
}

const formatDate = value => value ? new Date(value).toLocaleString() : ''

function applyState(value) {
    const preserveSelection = ownerFocused.value || selectionDirty.value || transferRequest.value || awaitingReplication.value
    state.value = { ...(value || {}) }
    if (!preserveSelection) selectedOwner.value = state.value.effective_owner || ''
}

async function loadStatus() {
    if (!props.routes.active_node_status || statusLoading.value) return
    statusLoading.value = true
    try {
        const response = await axios.get(props.routes.active_node_status)
        applyState(response.data.active_node)
    } catch (error) {
        emit('error', error.response?.data?.errors || messageBag(error.response?.data?.message || error.message))
    } finally {
        statusLoading.value = false
    }
}

async function run(action) {
    if (working.value) return
    working.value = true
    try {
        const response = await action()
        if (response.data.active_node) applyState(response.data.active_node)
        if (response.data.message) emit('success', messageBag(response.data.message))
        return response
    } catch (error) {
        if (error.response?.data?.active_node) applyState(error.response.data.active_node)
        emit('error', error.response?.data?.errors || messageBag(error.response?.data?.message || error.message))
        return null
    } finally {
        working.value = false
    }
}

async function transferOwner() {
    transferRequest.value ||= {
        target_node: selectedOwner.value,
        expected_generation: state.value.generation,
        idempotency_key: createIdempotencyUuid(),
    }
    const response = await run(() => axios.put(props.routes.active_node, transferRequest.value))
    if (response) {
        acknowledgedHandoff.value = response.data.handoff
        transferRequest.value = null
    }
}

function createIdempotencyUuid() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID()
    const bytes = new Uint8Array(16)
    if (window.crypto?.getRandomValues) window.crypto.getRandomValues(bytes)
    else for (let index = 0; index < bytes.length; index += 1) bytes[index] = Math.floor(Math.random() * 256)
    bytes[6] = (bytes[6] & 0x0f) | 0x40
    bytes[8] = (bytes[8] & 0x3f) | 0x80
    const hex = [...bytes].map(value => value.toString(16).padStart(2, '0')).join('')
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
}

async function discoverNodes() {
    discovering.value = true
    const response = await run(() => axios.post(props.routes.node_discover, { endpoint: manualEndpoint.value || null }))
    if (response) candidates.value = response.data.candidates || []
    discovering.value = false
}

async function approveNode(candidate) {
    const response = await run(() => axios.post(props.routes.node_approve.replace('__NODE__', candidate.system_identifier), {
        endpoint: candidate.endpoint,
    }))
    if (response) await discoverNodes()
}

async function retireNode(node) {
    await run(() => axios.post(props.routes.node_retire.replace('__NODE__', node.registry_uuid)))
}

async function rotateSecret() {
    await run(() => axios.post(props.routes.coordination_secret_rotate))
}

async function forceTakeover() {
    if (!canForce.value) return
    const response = await run(() => state.value.handoff
        ? axios.post(props.routes.handoff_force.replace('__HANDOFF__', state.value.handoff.id), {
            fenced_endpoint: fencedEndpoint.value,
            confirmed: true,
        })
        : axios.post(props.routes.active_node_force, {
            target_node: forceTargetNodeId.value,
            expected_generation: state.value.generation,
            fenced_endpoint: fencedEndpoint.value,
            confirmed: true,
        }))
    if (response) {
        fenceConfirmed.value = false
        fencedEndpoint.value = ''
        selectedOwner.value = response.data.active_node?.effective_owner || selectedOwner.value
    }
}

</script>

<style scoped>
.primary-button { @apply inline-flex items-center justify-center gap-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50; }
.secondary-button { @apply inline-flex items-center justify-center gap-1.5 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50; }
.danger-button { @apply inline-flex items-center justify-center rounded-md bg-rose-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-rose-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600 disabled:cursor-not-allowed disabled:opacity-50; }
.danger-link { @apply rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-rose-600 disabled:cursor-not-allowed disabled:opacity-50; }
</style>
