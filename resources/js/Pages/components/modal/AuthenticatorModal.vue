<template>
    <AddEditItemModal :show="show" :header="header" :loading="step === 'loading'" custom-class="sm:max-w-lg"
        @close="close">
        <template #modal-body>
            <div v-if="intent === 'setup'" class="-mt-2 mb-5">
                <p class="text-sm text-gray-500">{{ setupStepLabel }}</p>
                <div class="mt-3 flex gap-1.5" aria-hidden="true">
                    <span v-for="n in 3" :key="n" class="h-1 flex-1 rounded-full"
                        :class="n <= setupStepNumber ? 'bg-indigo-600' : 'bg-gray-200'" />
                </div>
            </div>
            <p v-if="notice" role="status" class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                {{ notice }}
            </p>

            <!-- Confirm the password and, for destructive actions, the action itself. -->
            <Vueform v-if="step === 'confirm'" :key="`confirm-${intent}`" :endpoint="submitConfirm"
                :display-errors="false" @mounted="form => form.disableValidation()" @submit="clearServerFormErrors"
                @response="showServerFormErrors" @success="advance" @error="failed">
                <StaticElement name="intro">
                    <div v-if="warning" class="flex gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full"
                            :class="danger ? 'bg-red-100' : 'bg-amber-100'">
                            <ExclamationTriangleIcon class="size-6" :class="danger ? 'text-red-600' : 'text-amber-600'"
                                aria-hidden="true" />
                        </div>
                        <p class="pt-0.5 text-sm text-gray-600">{{ warning }}</p>
                    </div>
                    <p v-else class="text-sm text-gray-600">{{ intro }}</p>
                </StaticElement>
                <TextElement name="password" id="authenticator-current-password" input-type="password" :label="$t('Current password')"
                    autocomplete="current-password" :floating="false" :conditions="[() => needsPassword]"
                    :description="directoryManaged ? $t('Use your directory password.') : undefined" />
                <ButtonElement name="cancel" :button-label="$t('Cancel')" :secondary="true" @click="close"
                    :columns="{ container: 6 }" />
                <ButtonElement name="submit" :button-label="confirmLabel" :submits="true" :danger="danger"
                    align="right" :columns="{ container: 6 }" />
            </Vueform>

            <!-- Scan the QR code and prove the app works. -->
            <Vueform v-else-if="step === 'scan'" key="scan" :endpoint="submitCode" :display-errors="false"
                @mounted="form => form.disableValidation()" @submit="clearServerFormErrors"
                @response="showServerFormErrors" @success="advance" @error="failed">
                <StaticElement name="instructions">
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-gray-600">
                        <li>{{ $t('Open an authenticator app on your phone, such as Google Authenticator or Microsoft Authenticator.') }}</li>
                        <li>{{ $t('Add an account and scan this QR code.') }}</li>
                        <li>{{ $t('Enter the six-digit code the app shows.') }}</li>
                    </ol>
                    <div class="mt-5 flex justify-center">
                        <img :src="qrImage" :alt="$t('Authenticator setup QR code')" width="184" height="184"
                            class="rounded-lg border border-gray-200 bg-white p-3" />
                    </div>
                    <p class="mt-5 text-sm text-gray-900">{{ $t("Can't scan? Enter this setup key instead") }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <code class="flex-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 font-mono text-[15px] tracking-wider text-gray-900">{{ groupedKey }}</code>
                        <button type="button" class="secondary-button" @click="copy('key', setupKey)">
                            <CheckIcon v-if="copied === 'key'" class="size-4 text-green-600" aria-hidden="true" />
                            <ClipboardDocumentIcon v-else class="size-4" aria-hidden="true" />
                            {{ copied === 'key' ? $t('Copied') : $t('Copy') }}
                        </button>
                    </div>
                </StaticElement>
                <TextElement name="code" :label="$t('Code from your app')" autocomplete="one-time-code"
                    placeholder="000000" :floating="false" :attrs="{ inputmode: 'numeric', maxlength: 6 }"
                    :columns="{ sm: { container: 12, wrapper: 6 } }"
                    :add-classes="{ TextElement: { input: 'font-mono text-lg tracking-widest text-center' } }" />
                <ButtonElement name="cancel" :button-label="$t('Cancel')" :secondary="true" @click="close"
                    :columns="{ container: 6 }" />
                <ButtonElement name="submit" :button-label="$t('Verify and turn on')" :submits="true" align="right"
                    :columns="{ container: 6 }" />
            </Vueform>

            <!-- Show recovery codes once setup finishes, or when asked for. -->
            <Vueform v-else-if="step === 'codes'" key="codes">
                <StaticElement name="codes">
                    <div v-if="intent === 'setup'"
                        class="mb-4 flex gap-2.5 rounded-md border border-green-200 bg-green-50 px-3 py-2.5 text-sm text-green-800">
                        <CheckCircleIcon class="size-5 shrink-0" aria-hidden="true" />
                        <p><span class="font-semibold">{{ $t('Your authenticator is on.') }}</span>
                            {{ $t('Next time you sign in, enter the code from your app.') }}</p>
                    </div>
                    <p class="text-sm text-gray-600">{{ $t('If you lose your phone, each code lets you sign in once. Keep them in a password manager or somewhere only you can reach.') }}</p>
                    <ul class="mt-3 grid grid-cols-1 gap-x-6 gap-y-1.5 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-sm text-gray-900 sm:grid-cols-2">
                        <li v-for="item in codes" :key="item">{{ item }}</li>
                    </ul>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="secondary-button" @click="copy('codes', codes.join('\n'))">
                            <CheckIcon v-if="copied === 'codes'" class="size-4 text-green-600" aria-hidden="true" />
                            <ClipboardDocumentIcon v-else class="size-4" aria-hidden="true" />
                            {{ copied === 'codes' ? $t('Copied') : $t('Copy codes') }}
                        </button>
                        <button type="button" class="secondary-button" @click="download">
                            <ArrowDownTrayIcon class="size-4" aria-hidden="true" />
                            {{ $t('Download .txt') }}
                        </button>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">{{ $t('You can view these again from My Account. Generating new codes cancels this set.') }}</p>
                </StaticElement>
                <ButtonElement name="done" :button-label="intent === 'setup' ? $t('I\'ve saved my codes') : $t('Done')"
                    align="right" @click="close" />
            </Vueform>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import axios from 'axios'
import { computed, ref, watch } from 'vue'
import { trans } from '@i18n'
import { ArrowDownTrayIcon, ClipboardDocumentIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { CheckCircleIcon, CheckIcon } from '@heroicons/vue/20/solid'
import AddEditItemModal from './AddEditItemModal.vue'
import { clearServerFormErrors, showServerFormErrors } from '../../../composables/serverFormErrors'

const props = defineProps({
    show: Boolean,
    // setup, codes, regenerate, disable, or discard
    intent: String,
    email: String,
    issuer: String,
    directoryManaged: Boolean,
})
const emit = defineEmits(['close', 'changed', 'success', 'error'])

const ENDPOINT = '/user/two-factor-authentication'
const RECOVERY_URL = '/user/two-factor-recovery-codes'

const step = ref('loading')
const nextStep = ref(null)
const needsPassword = ref(true)
const notice = ref('')
const qrImage = ref('')
const setupKey = ref('')
const codes = ref([])
const copied = ref(null)

const danger = computed(() => ['disable', 'discard'].includes(props.intent))
const header = computed(() => ({
    setup: trans('Set up authenticator'),
    codes: trans('Recovery codes'),
    regenerate: step.value === 'codes' ? trans('New recovery codes') : trans('Generate new recovery codes?'),
    disable: trans('Turn off two-factor authentication?'),
    discard: trans('Discard authenticator setup?'),
})[props.intent])
const intro = computed(() => props.intent === 'codes'
    ? trans('Confirm your password to see your recovery codes.')
    : trans("You're about to change how you sign in, so first confirm it's you."))
const warning = computed(() => ({
    regenerate: trans('Your current recovery codes stop working as soon as new ones are created.'),
    disable: trans('Your authenticator app and recovery codes will stop working. Sign-in will only ask for your password, plus an emailed code if your administrator requires one.'),
    discard: trans('The setup key you added to your authenticator app will stop working. You can start setup again at any time.'),
})[props.intent])
const confirmLabel = computed(() => ({
    regenerate: trans('Generate new codes'),
    disable: trans('Turn off'),
    discard: trans('Discard setup'),
})[props.intent] ?? trans('Continue'))
const setupStepNumber = computed(() => ({ confirm: 1, scan: 2, codes: 3 })[step.value] ?? 1)
const setupStepLabel = computed(() => ({
    1: trans('Step 1 of 3: Confirm your password'),
    2: trans('Step 2 of 3: Connect your app'),
    3: trans('Step 3 of 3: Save your recovery codes'),
})[setupStepNumber.value])
const groupedKey = computed(() => setupKey.value.match(/.{1,4}/g)?.join(' ') ?? '')

watch(() => props.show, open => { if (open) start() })

// Fortify remembers a password confirmation for a while, so only ask when it has lapsed.
async function start() {
    step.value = 'loading'
    nextStep.value = null
    notice.value = ''
    qrImage.value = ''
    setupKey.value = ''
    codes.value = []
    copied.value = null
    try {
        needsPassword.value = !(await axios.get('/user/confirmed-password-status')).data.confirmed
    } catch {
        needsPassword.value = true
    }
    if (needsPassword.value || !['setup', 'codes'].includes(props.intent)) {
        step.value = 'confirm'
        return
    }
    try {
        await runIntent()
        step.value = nextStep.value
    } catch (error) {
        failed(error)
        if (step.value !== 'confirm') close()
    }
}

// Does the work for the current intent and records which screen comes next.
async function runIntent() {
    if (props.intent === 'setup') {
        const response = await axios.post(ENDPOINT)
        emit('changed')
        const [qr, key] = await Promise.all([axios.get('/user/two-factor-qr-code'), axios.get('/user/two-factor-secret-key')])
        qrImage.value = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(qr.data.svg)
        setupKey.value = key.data.secretKey
        nextStep.value = 'scan'
        return response
    }
    if (props.intent === 'disable' || props.intent === 'discard') {
        const response = await axios.delete(ENDPOINT)
        emit('changed')
        nextStep.value = 'done'
        return response
    }
    if (props.intent === 'regenerate') await axios.post(RECOVERY_URL)
    const response = await axios.get(RECOVERY_URL)
    codes.value = response.data
    nextStep.value = 'codes'
    return response
}

async function submitConfirm(_, form) {
    notice.value = ''
    if (needsPassword.value) {
        await axios.post('/user/confirm-password', { password: form.requestData.password })
    }
    return runIntent()
}

async function submitCode(_, form) {
    const response = await axios.post('/user/confirmed-two-factor-authentication', { code: form.requestData.code })
    emit('changed')
    try {
        codes.value = (await axios.get(RECOVERY_URL)).data
        nextStep.value = 'codes'
    } catch {
        emit('success', { success: [trans('Authenticator turned on. Select View codes to save your recovery codes.')] })
        nextStep.value = 'done'
    }
    return response
}

// Switch screens only after VueForm has finished with the submitting form.
function advance() {
    if (nextStep.value === 'done') {
        if (props.intent === 'disable') emit('success', { success: [trans('Two-factor authentication is off.')] })
        if (props.intent === 'discard') emit('success', { success: [trans('Authenticator setup discarded.')] })
        close()
        return
    }
    step.value = nextStep.value
}

// VueForm calls this after it finishes with the submitting form, so switching screens here is safe.
function failed(error) {
    const status = error?.response?.status
    if (status === 423) {
        // The password confirmation lapsed mid-flow; ask for it again.
        needsPassword.value = true
        notice.value = trans('Confirm your password to continue.')
        step.value = 'confirm'
        return
    }
    if (status === 422) return
    emit('error', { error: [error?.response?.data?.message || trans('The request failed. Please try again.')] })
}

async function copy(what, text) {
    try {
        await navigator.clipboard.writeText(text)
        copied.value = what
        setTimeout(() => { if (copied.value === what) copied.value = null }, 2000)
    } catch {
        emit('error', { error: [trans('Could not copy to the clipboard. Select the text and copy it instead.')] })
    }
}

function download() {
    const lines = [
        trans(':issuer recovery codes for :email', { issuer: props.issuer, email: props.email }),
        trans('Each code can be used once.'),
        '',
        ...codes.value,
        '',
    ]
    const url = URL.createObjectURL(new Blob([lines.join('\n')], { type: 'text/plain' }))
    const link = document.createElement('a')
    link.href = url
    link.download = 'recovery-codes.txt'
    link.click()
    setTimeout(() => URL.revokeObjectURL(url), 0)
}

function close() {
    emit('close')
}
</script>

<style scoped>
.secondary-button { @apply inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50; }
</style>
