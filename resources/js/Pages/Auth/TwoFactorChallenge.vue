<template>
    <Head :title="$t('Two-factor authentication')" />
    <main class="mx-auto flex max-w-[480px] flex-col items-center px-4 py-12">
        <img class="h-10 w-auto" src="/storage/logo.png" :alt="$t('Logo')" />
        <h1 class="mt-6 text-center text-2xl font-bold leading-9 tracking-tight text-gray-900">{{ $t('Two-factor authentication') }}</h1>
        <section class="mt-8 w-full rounded-lg bg-white px-6 py-8 shadow sm:px-10">
            <div class="flex items-start gap-3.5">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                    <component :is="modeIcon" class="size-6" aria-hidden="true" />
                </div>
                <p class="pt-0.5 text-sm text-gray-600">{{ instruction }}</p>
            </div>
            <p v-if="status" role="status" class="mt-4 text-sm text-green-700">{{ status }}</p>
            <form @submit.prevent="verify" class="mt-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-900" for="challenge-code">{{ label }}</label>
                    <input id="challenge-code" ref="input" v-model="code" :inputmode="mode === 'recovery' ? 'text' : 'numeric'"
                        type="text" autocomplete="one-time-code" autofocus spellcheck="false"
                        :placeholder="mode === 'recovery' ? '' : '000000'" :maxlength="mode === 'recovery' ? undefined : 6"
                        :aria-invalid="!!error" aria-describedby="challenge-error"
                        :class="['mt-1.5 block w-full rounded-md border-0 font-mono text-gray-900 shadow-sm ring-1 ring-inset placeholder:text-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600',
                            mode === 'recovery' ? 'py-2 text-base' : 'py-1.5 text-center text-2xl tracking-[0.4em]',
                            error ? 'ring-red-600' : 'ring-gray-300']" />
                    <p id="challenge-error" role="alert" class="mt-1.5 text-sm text-red-700">{{ error }}</p>
                </div>
                <label v-if="mode === 'email' && canRemember" class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="remember" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                    {{ $t('Remember this device for 7 days') }}
                </label>
                <button type="submit" :disabled="busy" class="w-full rounded-md bg-indigo-600 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50">
                    {{ $t('Verify') }}
                </button>
                <p v-if="mode === 'email'" class="text-center text-sm text-gray-500">
                    {{ $t("Didn't get it?") }}
                    <button type="button" :disabled="busy" class="font-semibold text-indigo-600 hover:text-indigo-500 disabled:opacity-50" @click="emailCode">{{ $t('Send a new code') }}</button>
                </p>
            </form>

            <template v-if="authenticatorEnabled">
                <div class="mb-4 mt-7 flex items-center gap-3" aria-hidden="true">
                    <span class="h-px flex-1 bg-gray-200" />
                    <span class="text-xs text-gray-500">{{ $t('Other ways to sign in') }}</span>
                    <span class="h-px flex-1 bg-gray-200" />
                </div>
                <ul role="list" :aria-label="$t('Other ways to sign in')" class="space-y-2">
                    <li v-for="option in otherOptions" :key="option.key">
                        <button type="button" :disabled="busy" @click="option.select"
                            class="flex w-full items-center gap-3 rounded-md bg-white px-3 py-2.5 text-left ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 disabled:opacity-50">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-gray-100 text-gray-600">
                                <component :is="option.icon" class="size-5" aria-hidden="true" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-gray-900">{{ option.label }}</span>
                                <span class="block text-xs text-gray-500">{{ option.description }}</span>
                            </span>
                            <ChevronRightIcon class="size-5 shrink-0 text-gray-400" aria-hidden="true" />
                        </button>
                    </li>
                </ul>
            </template>
        </section>
        <p class="mt-6 text-sm text-gray-500">
            {{ $t('Not you?') }}
            <a href="/login" class="font-semibold text-indigo-600 no-underline hover:text-indigo-500">{{ $t('Sign in as someone else') }}</a>
        </p>
    </main>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { trans } from '@i18n'
import { DevicePhoneMobileIcon, EnvelopeIcon, TicketIcon } from '@heroicons/vue/24/outline'
import { ChevronRightIcon } from '@heroicons/vue/20/solid'

const props = defineProps({
    method: { type: String, default: 'authenticator' },
    authenticatorEnabled: { type: Boolean, default: true },
    canRemember: Boolean,
    status: String,
})
const page = usePage()
const mode = ref(props.method)
const code = ref('')
const error = ref('')
const remember = ref(false)
const busy = ref(false)
const input = ref(null)
const modeIcon = computed(() => ({ recovery: TicketIcon, email: EnvelopeIcon })[mode.value] ?? DevicePhoneMobileIcon)
// Every other way to finish signing in stays visible, so nobody has to hunt for it.
const otherOptions = computed(() => [
    { key: 'authenticator', icon: DevicePhoneMobileIcon, label: trans('Use your authenticator app'),
        description: trans('Six-digit code from your phone'), select: () => setMode('authenticator') },
    { key: 'recovery', icon: TicketIcon, label: trans('Use a recovery code'),
        description: trans('One of the codes you saved during setup'), select: () => setMode('recovery') },
    { key: 'email', icon: EnvelopeIcon, label: trans('Email me a code'),
        description: trans('Sent to your sign-in email address'), select: emailCode },
].filter(option => option.key !== mode.value))
const label = computed(() => mode.value === 'recovery' ? trans('Recovery code') : trans('Verification code'))
const instruction = computed(() => {
    if (mode.value === 'email') return trans('A one-time verification code has been sent to your email. Be sure to check your junk or spam folder.')
    if (mode.value === 'recovery') return trans('Enter one of the recovery codes you saved when you set up your authenticator.')
    return trans('Enter the six-digit code from your authenticator app.')
})

function setMode(value) {
    mode.value = value
    code.value = ''
    error.value = ''
    nextTick(() => input.value?.focus())
}
watch(() => props.method, setMode)

function verify() {
    const recovery = mode.value === 'recovery'
    busy.value = true
    error.value = ''
    router.post(mode.value === 'email' ? '/email-challenge' : '/two-factor-challenge', {
        [recovery ? 'recovery_code' : 'code']: code.value,
        remember: props.canRemember && remember.value,
        _token: page.props.csrf_token,
    }, {
        onError: errors => { error.value = errors.recovery_code || errors.code },
        onFinish: () => { busy.value = false },
    })
}

function emailCode() {
    busy.value = true
    router.put('/email-challenge', { _token: page.props.csrf_token }, {
        onSuccess: () => setMode('email'),
        onFinish: () => { busy.value = false },
    })
}
</script>
