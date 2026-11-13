<template>
    <section class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
        <button type="button" class="flex w-full items-center justify-between gap-4 rounded-lg px-4 py-3 text-left focus:outline-none focus:ring-2 focus:ring-indigo-500"
            :aria-expanded="open" :disabled="applying" aria-controls="switch-network-setup" @click="toggle">
            <span>
                <span class="block text-sm font-semibold text-gray-900">{{ $t('Network Setup') }}</span>
                <span class="mt-1 block text-sm text-gray-500">{{ $t('Choose this server’s local and public addresses. FS PBX creates the variables for you.') }}</span>
            </span>
            <ChevronUpIcon v-if="open" class="h-5 w-5 shrink-0 text-gray-500" />
            <ChevronDownIcon v-else class="h-5 w-5 shrink-0 text-gray-500" />
        </button>

        <div v-if="open" id="switch-network-setup" class="border-t border-gray-200 p-4">
            <div v-if="loading" role="status" class="space-y-3">
                <p class="text-sm text-gray-500">{{ $t('Reading this server’s network settings…') }}</p>
                <div class="h-9 max-w-xl rounded-md bg-gray-100" />
                <div class="h-9 max-w-xl rounded-md bg-gray-100" />
            </div>
            <template v-else>
                <div v-if="Object.keys(errors).length" role="alert" class="mb-4 rounded-md bg-rose-50 p-3 text-sm text-rose-800 ring-1 ring-rose-200">
                    <p v-for="(messages, key) in errors" :key="key">{{ Array.isArray(messages) ? messages[0] : messages }}</p>
                    <button v-if="!state" type="button" class="mt-2 font-semibold underline" @click="load">{{ $t('Retry') }}</button>
                </div>

                <div v-if="result" role="status" class="mb-4 rounded-md p-3 text-sm ring-1"
                    :class="result.synchronized ? 'bg-green-50 text-green-800 ring-green-200' : 'bg-amber-50 text-amber-800 ring-amber-200'">
                    <p class="font-semibold">{{ $t('Network settings saved for :hostname.', { hostname: result.hostname }) }}</p>
                    <p class="mt-1">{{ result.synchronized ? $t('XML synchronized. Restart affected SIP profiles during a maintenance window, then test calls.') : $t('XML synchronization failed. The settings are saved; correct the reported problem and use Sync XML to retry.') }}</p>
                    <p class="mt-1">{{ $t('Restarting a SIP profile interrupts its calls. Saving here does not restart profiles.') }}</p>
                    <a :href="routes.sip_status" class="mt-2 inline-block font-semibold underline">{{ $t('Open SIP Status') }}</a>
                </div>

                <template v-if="state">
                    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                        <div class="text-sm">
                            <p class="font-medium text-gray-700">{{ $t('Current server') }}: <code class="break-all">{{ state.hostname }}</code></p>
                            <p class="mt-1 max-w-2xl text-gray-500">{{ $t('For HA, open each server directly and run setup there. Each server must have a unique FreeSWITCH hostname.') }}</p>
                        </div>
                        <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500"
                            :disabled="applying" @click="load">{{ $t('Reload saved settings') }}</button>
                    </div>

                    <div v-show="!review" class="max-w-2xl">
                        <Vueform ref="form$" :endpoint="submitPreview" :validate="false" :validate-on="''" :display-errors="false"
                            @success="previewSuccess" @response="formResponse" @error="formError">
                            <template #empty>
                                <FormElements>
                                    <HiddenElement name="hostname" />
                                    <SelectElement name="layout" :label="$t('Network layout')" :native="false" :floating="false"
                                        :items="[
                                            { value: 'direct', label: $t('Direct public IP') },
                                            { value: 'nat', label: $t('Behind NAT or a cloud public-IP mapping') },
                                            { value: 'private', label: $t('Private network or VPN only') },
                                        ]" />
                                    <SelectElement name="local_ip" :label="$t('Local voice address')" :items="interfaceOptions" :native="false"
                                        :search="true" input-type="search" :strict="false" allow-absent :floating="false"
                                        :description="$t('Select the interface that carries calls. The address is saved explicitly and must be available before FreeSWITCH starts.')" />
                                    <TextElement name="public_ip" :label="$t('Public SIP address')" :floating="false" :conditions="[['layout', 'nat']]"
                                        :description="$t('Enter the public IPv4 address your router or cloud provider maps to this server. This does not create firewall or NAT rules.')" />
                                    <ToggleElement name="separate_media" :text="$t('Use a different public address for audio')" :conditions="[['layout', 'nat']]" />
                                    <TextElement name="media_ip" :label="$t('Public audio address')" :floating="false"
                                        :conditions="[['layout', 'nat'], ['separate_media', true]]" />
                                    <ButtonElement name="review" :button-label="$t('Review changes')" :submits="true" align="left" />
                                </FormElements>
                            </template>
                        </Vueform>
                    </div>

                    <div v-if="review">
                        <h3 class="text-sm font-semibold text-gray-900">{{ $t('Review network changes') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ $t('The three address variables will use Set, category IP Address, and orders 0, 10, and 20. Conflicting active definitions shown below will be disabled.') }}</p>
                        <div v-if="review.shared_rows" class="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200">
                            <p class="font-semibold">{{ $t('Shared address settings will change') }}</p>
                            <p class="mt-1">{{ $t('Rows marked All servers will become specific to this server or be disabled. Configure every other server before syncing or restarting it. Existing rows for other hostnames are preserved.') }}</p>
                        </div>
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                                <thead class="bg-gray-50 text-gray-600">
                                    <tr>
                                        <th scope="col" class="px-3 py-2 font-medium">{{ $t('Variable') }}</th>
                                        <th scope="col" class="px-3 py-2 font-medium">{{ $t('Current setting') }}</th>
                                        <th scope="col" class="px-3 py-2 font-medium">{{ $t('After applying') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(change, index) in review.changes" :key="change.id || index">
                                        <th scope="row" class="px-3 py-2 font-mono text-xs font-medium text-gray-900">{{ change.name }}</th>
                                        <td class="max-w-xs px-3 py-2 text-gray-600">
                                            <template v-if="change.before">
                                                <code class="block truncate text-xs" :title="change.before.var_value">{{ change.before.var_value || '—' }}</code>
                                                <span class="mt-1 block text-xs">{{ change.before.var_hostname || $t('All servers') }} · {{ change.before.var_enabled === 'true' ? $t('Enabled') : $t('Disabled') }}</span>
                                                <span class="block text-xs">{{ change.before.var_command || 'set' }} · {{ change.before.var_category }} · {{ $t('Order :order', { order: change.before.var_order ?? 0 }) }}</span>
                                            </template>
                                            <span v-else>{{ $t('New variable') }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-gray-900">
                                            <span v-if="change.action === 'disable'" class="font-medium text-amber-800">{{ $t('Disabled') }}</span>
                                            <template v-else>
                                                <code class="block text-xs">{{ change.value }}</code>
                                                <span class="mt-1 block text-xs text-gray-500">{{ change.hostname }} · {{ $t('Order :order', { order: change.order }) }}</span>
                                            </template>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <button type="button" :disabled="applying" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50" @click="apply">
                                {{ applying ? $t('Applying…') : $t('Apply network settings') }}
                            </button>
                            <button type="button" :disabled="applying" class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 disabled:opacity-50" @click="review = null">{{ $t('Back') }}</button>
                        </div>
                    </div>

                    <details class="mt-5 border-t border-gray-200 pt-3 text-sm">
                        <summary class="cursor-pointer font-medium text-gray-700">{{ $t('Loaded FreeSWITCH variables') }}</summary>
                        <p class="mt-2 max-w-2xl text-gray-500">{{ $t('These are loaded global values. Check SIP Status for the addresses used by running profiles; custom profiles may use other variables.') }}</p>
                        <dl class="mt-2 grid max-w-xl grid-cols-1 gap-2 sm:grid-cols-2">
                            <template v-for="(value, name) in state.loaded" :key="name">
                                <dt class="font-mono text-xs text-gray-600">{{ name }}</dt>
                                <dd class="break-all font-mono text-xs text-gray-900">{{ value || $t('Not set') }}</dd>
                            </template>
                        </dl>
                    </details>
                </template>
            </template>
        </div>
    </section>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue'
import axios from 'axios'
import { ChevronDownIcon, ChevronUpIcon } from '@heroicons/vue/24/outline'
import { trans } from '@i18n'

const props = defineProps({ routes: { type: Object, required: true } })
const emit = defineEmits(['saved'])
const open = ref(false)
const loading = ref(false)
const applying = ref(false)
const state = ref(null)
const errors = ref({})
const review = ref(null)
const result = ref(null)
const form$ = ref(null)
let reviewedData = null

const interfaceOptions = computed(() => (state.value?.interfaces || []).map(item => ({ value: item.address, label: item.label })))

const toggle = () => {
    open.value = !open.value
    if (open.value) load()
}

const load = async () => {
    loading.value = true
    errors.value = {}
    review.value = null
    try {
        const response = await axios.get(props.routes.network)
        state.value = response.data
    } catch (error) {
        state.value = null
        showError(error)
    } finally {
        loading.value = false
        await nextTick()
        form$.value?.update(state.value?.item || {})
        form$.value?.clean()
    }
}

const showError = (error) => {
    errors.value = error?.response?.data?.errors || { runtime: [trans('Unable to complete Network Setup. Refresh and try again.')] }
}

const submitPreview = async (formData, form) => {
    form.messageBag.clear()
    Object.values(form.elements$).forEach(element => element.messageBag?.clear())
    errors.value = {}
    result.value = null
    // Conditional fields are omitted by VueForm; normalize the hidden switch for Laravel.
    reviewedData = { ...form.requestData, separate_media: Boolean(form.requestData.separate_media) }
    return await axios.post(props.routes.network_preview, reviewedData)
}

const previewSuccess = (response) => { review.value = response.data }
const formResponse = (response, form) => {
    if (!response.data.errors) return
    errors.value = response.data.errors
    for (const [name, messages] of Object.entries(response.data.errors)) form.el$(name)?.messageBag.append(messages[0])
}
const formError = (error) => showError(error)

const apply = async () => {
    applying.value = true
    errors.value = {}
    try {
        const response = await axios.put(props.routes.network_update, { ...reviewedData, review_token: review.value.review_token })
        result.value = response.data
        emit('saved')
        await load()
        if (!response.data.synchronized) errors.value = response.data.errors
    } catch (error) {
        showError(error)
        // A rejected review must be generated again against current server state.
        review.value = null
    } finally {
        applying.value = false
    }
}
</script>
