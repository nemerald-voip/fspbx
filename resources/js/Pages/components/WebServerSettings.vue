<template>
    <div class="space-y-6">
        <Vueform class="min-w-0">
            <StaticElement name="web_server_title" tag="h4" :content="$t('Web Server')"
                :description="$t('Stricter HTTPS settings for the FS PBX web server. They are off by default and change nothing until you turn them on.')" />
        </Vueform>

        <div v-if="!state.helper_installed || state.site_ready === false" role="alert"
            class="flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" aria-hidden="true" />
            <p>{{ $t('This server has not been updated for this setting yet. Run the FS PBX update, then come back here.') }}</p>
        </div>

        <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h5 class="text-base font-semibold text-gray-900">{{ $t('Strict HTTPS') }}</h5>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ $t('Turns on the HSTS header, allows TLS 1.3 and limits the web server to modern ciphers. Web addresses on port 80 are redirected to HTTPS whether this is on or off.') }}
                    </p>
                </div>
                <Badge :text="state.enabled ? $t('On') : $t('Off')"
                    :backgroundColor="state.enabled ? 'bg-green-50' : 'bg-gray-50'"
                    :textColor="state.enabled ? 'text-green-700' : 'text-gray-600'"
                    :ringColor="state.enabled ? 'ring-green-600/20' : 'ring-gray-500/10'" />
            </div>

            <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div v-if="state.certificate.installed">
                    <dt class="font-medium text-gray-700">{{ $t('Certificate') }}</dt>
                    <dd class="text-gray-600">
                        {{ state.certificate.is_self_signed ? $t('Self-signed') : (state.certificate.issuer || '-') }}
                    </dd>
                </div>
                <div v-if="state.certificate.installed && state.certificate.valid_to">
                    <dt class="font-medium text-gray-700">{{ $t('Valid until') }}</dt>
                    <dd class="text-gray-600">{{ formatDate(state.certificate.valid_to) }}</dd>
                </div>
            </dl>

            <div v-if="!state.enabled && state.requires_confirmation" role="alert"
                class="mt-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">{{ $t('This server uses a self-signed certificate.') }}</p>
                <p class="mt-1">
                    {{ $t('Once HSTS is on, a browser that sees a certificate it does not trust shows an error that cannot be bypassed. Install a trusted certificate first (see Let\'s Encrypt on the SIP Status page).') }}
                </p>
                <label class="mt-3 flex items-start gap-2">
                    <input v-model="confirmSelfSigned" type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-600" />
                    <span>{{ $t('I understand, turn it on anyway.') }}</span>
                </label>
            </div>

            <div class="mt-4 rounded-md bg-gray-50 p-4 text-sm text-gray-700">
                <p class="font-medium">{{ $t('Before you turn it on') }}</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>{{ $t('Phones and other devices that fetch their configuration over HTTPS must support the ciphers above. Older firmware may fail to provision.') }}</li>
                    <li>{{ $t('Try one phone first. Turning the setting off again restores the previous settings straight away.') }}</li>
                    <li>{{ $t('Browsers remember HSTS for one year after the last visit.') }}</li>
                </ul>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" :disabled="!canSubmit"
                    class="rounded-md px-3 py-2 text-sm font-semibold text-white shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    :class="state.enabled ? 'bg-gray-700 hover:bg-gray-600 focus-visible:outline-gray-700' : 'bg-indigo-600 hover:bg-indigo-500 focus-visible:outline-indigo-600'"
                    @click="submit">
                    {{ buttonLabel }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import axios from 'axios'
import { trans } from '@i18n'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import Badge from '@generalComponents/Badge.vue'

const props = defineProps({
    routes: { type: Object, required: true },
    settings: { type: Object, required: true },
})

const emit = defineEmits(['error', 'success'])

const state = reactive({ ...props.settings })
const busy = ref(false)
const confirmSelfSigned = ref(false)

const ready = computed(() => state.helper_installed && state.site_ready !== false)
const needsConfirmation = computed(() => !state.enabled && state.requires_confirmation)
const canSubmit = computed(() => ready.value && !busy.value && (!needsConfirmation.value || confirmSelfSigned.value))
const buttonLabel = computed(() => {
    if (busy.value) return trans('Applying…')

    return state.enabled ? trans('Turn off') : trans('Turn on')
})

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : '-')

const submit = async () => {
    if (!canSubmit.value) return

    busy.value = true

    try {
        const { data } = await axios.put(props.routes.update, {
            enabled: !state.enabled,
            allow_self_signed: needsConfirmation.value && confirmSelfSigned.value,
        })

        Object.assign(state, data)
        confirmSelfSigned.value = false
        emit('success', 'success', data.messages)
    } catch (error) {
        emit('error', error)
    } finally {
        busy.value = false
    }
}
</script>
