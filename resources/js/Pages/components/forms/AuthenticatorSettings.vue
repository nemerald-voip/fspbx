<template>
    <div class="space-y-5">
        <!-- On -->
        <template v-if="state === 'enabled'">
            <div class="flex flex-wrap items-center gap-4 rounded-lg border border-gray-200 bg-white px-5 py-4">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-700">
                    <ShieldCheckIcon class="size-6" aria-hidden="true" />
                </div>
                <div class="min-w-0 flex-1 basis-72">
                    <p class="text-sm font-semibold text-gray-900">{{ $t('Two-factor authentication is on') }}</p>
                    <p class="mt-0.5 text-sm text-gray-600">
                        {{ authenticator.confirmed_at
                            ? $t('Turned on :date. Every new sign-in asks for a code after your password.', { date: authenticator.confirmed_at })
                            : $t('Every new sign-in asks for a code after your password.') }}
                    </p>
                </div>
            </div>

            <div>
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $t("Ways to confirm it's you") }}</h3>
                <ul role="list" class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                            <DevicePhoneMobileIcon class="size-5" aria-hidden="true" />
                        </div>
                        <div class="min-w-0 flex-1 basis-72">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-gray-900">
                                {{ $t('Authenticator app') }}
                                <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">{{ $t('Asked first') }}</span>
                            </p>
                            <p class="mt-0.5 text-sm text-gray-500">{{ $t('Six-digit codes from an app on your phone, such as Google Authenticator or Microsoft Authenticator.') }}</p>
                        </div>
                        <button type="button" class="danger-button" @click="open('disable')">{{ $t('Turn off') }}</button>
                    </li>
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                            <TicketIcon class="size-5" aria-hidden="true" />
                        </div>
                        <div class="min-w-0 flex-1 basis-72">
                            <p class="text-sm font-semibold text-gray-900">{{ $t('Recovery codes') }}</p>
                            <p class="mt-0.5 text-sm text-gray-500">{{ $t("Single-use codes for when you don't have your phone. Generating new codes cancels the old ones.") }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="secondary-button" @click="open('codes')">{{ $t('View codes') }}</button>
                            <button type="button" class="secondary-button" @click="open('regenerate')">{{ $t('Generate new') }}</button>
                        </div>
                    </li>
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                            <EnvelopeIcon class="size-5" aria-hidden="true" />
                        </div>
                        <div class="min-w-0 flex-1 basis-72">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-gray-900">
                                {{ $t('Email code') }}
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ $t('Backup') }}</span>
                            </p>
                            <p class="mt-0.5 text-sm text-gray-500">{{ $t('At sign-in, choose Email me a code to get one at :email.', { email }) }}</p>
                        </div>
                        <span class="text-sm text-gray-500">{{ $t('Always available') }}</span>
                    </li>
                </ul>
            </div>
        </template>

        <!-- Started but not confirmed -->
        <div v-else-if="state === 'pending'"
            class="flex flex-wrap items-start gap-4 rounded-lg border border-amber-200 bg-amber-50 px-5 py-4">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                <ExclamationTriangleIcon class="size-6" aria-hidden="true" />
            </div>
            <div class="min-w-0 flex-1 basis-72">
                <p class="text-sm font-semibold text-amber-900">{{ $t("Setup isn't finished") }}</p>
                <p class="mt-0.5 text-sm text-amber-800">{{ $t("Your authenticator isn't being used yet. Finish setup by entering a code from the app, or discard it and start again later.") }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="primary-button" @click="open('setup')">{{ $t('Finish setup') }}</button>
                    <button type="button" class="secondary-button" @click="open('discard')">{{ $t('Discard setup') }}</button>
                </div>
            </div>
        </div>

        <!-- Off -->
        <template v-else>
            <div class="flex flex-wrap items-center gap-4 rounded-lg border border-gray-200 bg-white px-5 py-4">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500">
                    <ShieldExclamationIcon class="size-6" aria-hidden="true" />
                </div>
                <div class="min-w-0 flex-1 basis-72">
                    <p class="text-sm font-semibold text-gray-900">{{ $t('Two-factor authentication is off') }}</p>
                    <p class="mt-0.5 text-sm text-gray-600">{{ $t("Setup takes about a minute. You'll need your phone.") }}</p>
                </div>
                <button type="button" class="primary-button" @click="open('setup')">{{ $t('Set up authenticator') }}</button>
            </div>
            <ol role="list" class="grid gap-3 sm:grid-cols-3">
                <li v-for="(item, index) in steps" :key="item.title"
                    class="flex gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3.5">
                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-xs font-bold text-indigo-700">{{ index + 1 }}</span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ item.title }}</p>
                        <p class="mt-0.5 text-sm text-gray-500">{{ item.text }}</p>
                    </div>
                </li>
            </ol>
            <p class="text-sm text-gray-500">{{ $t('Until you set one up, sign-in may still ask for an emailed code if your administrator requires it.') }}</p>
        </template>

        <AuthenticatorModal :show="showModal" :intent="intent" :email="email" :issuer="authenticator.issuer" :directory-managed="directoryManaged"
            @close="showModal = false" @changed="emit('changed')"
            @success="messages => emit('success', messages)" @error="messages => emit('error', messages)" />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { trans } from '@i18n'
import {
    DevicePhoneMobileIcon, EnvelopeIcon, ExclamationTriangleIcon, ShieldCheckIcon, ShieldExclamationIcon, TicketIcon,
} from '@heroicons/vue/24/outline'
import AuthenticatorModal from '../modal/AuthenticatorModal.vue'

const props = defineProps({
    authenticator: { type: Object, default: () => ({ state: 'off', confirmed_at: null }) },
    email: String,
    directoryManaged: Boolean,
})
const emit = defineEmits(['changed', 'success', 'error'])

const state = computed(() => props.authenticator?.state ?? 'off')
const showModal = ref(false)
const intent = ref('setup')
const steps = [
    { title: trans('Confirm your password'), text: trans('So only you can change how you sign in.') },
    { title: trans('Scan a QR code'), text: trans('With any authenticator app, then enter its code.') },
    { title: trans('Save recovery codes'), text: trans('Use one if you lose your phone.') },
]

function open(value) {
    intent.value = value
    showModal.value = true
}
</script>

<style scoped>
.primary-button { @apply inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50; }
.secondary-button { @apply inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50; }
.danger-button { @apply inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-red-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:cursor-not-allowed disabled:opacity-50; }
</style>
