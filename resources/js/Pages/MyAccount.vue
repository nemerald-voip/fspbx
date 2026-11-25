<template>
    <Head :title="$t('My Account')" />
    <PageWithSideMenu :title="$t('My Account')" :navigation="navigation" :pages="pages" :header-icon="UserCircleIcon"
        :initial-menu-option="initialMenuOption">
        <template #default="{ selectedMenuOption }">

            <!-- PROFILE -->
            <section v-show="selectedMenuOption === 'profile'" aria-labelledby="my-account-profile" class="space-y-5">
                <div>
                    <h2 id="my-account-profile" class="text-xl font-semibold text-gray-900">{{ $t('Profile') }}</h2>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">{{ $t('Your name and the email address you sign in with.') }}</p>
                </div>

                <template v-if="profile.directoryManaged || profile.emailManaged">
                    <div class="flex items-start gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3.5 text-sm text-indigo-800">
                        <LockClosedIcon class="size-5 shrink-0" aria-hidden="true" />
                        <p>{{ directoryNote }}</p>
                    </div>
                    <dl class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white text-sm">
                        <div v-if="profile.directoryManaged" class="flex flex-wrap gap-x-4 gap-y-1 px-5 py-3.5">
                            <dt class="w-40 text-gray-500">{{ $t('Name') }}</dt>
                            <dd class="font-medium text-gray-900">{{ fullName }}</dd>
                        </div>
                        <div v-if="profile.emailManaged" class="flex flex-wrap gap-x-4 gap-y-1 px-5 py-3.5">
                            <dt class="w-40 text-gray-500">{{ $t('Sign-in email') }}</dt>
                            <dd class="font-medium text-gray-900">{{ profile.user_email }}</dd>
                        </div>
                    </dl>
                </template>

                <MyAccountForm v-if="!profile.directoryManaged" :title="$t('Name')" :save="saveName" :button="$t('Save')"
                    :message="$t('Name updated.')" @saved="refreshProfile" @success="notify('success', $event)"
                    @error="notify('error', $event)">
                    <TextElement name="first_name" :default="profile.first_name" :label="$t('First name')"
                        autocomplete="given-name" :floating="false" :columns="{ sm: { container: 6 } }" />
                    <TextElement name="last_name" :default="profile.last_name" :label="$t('Last name')"
                        autocomplete="family-name" :floating="false" :columns="{ sm: { container: 6 } }" />
                </MyAccountForm>

                <MyAccountForm v-if="!profile.emailManaged" :title="$t('Sign-in email')"
                    :description="$t('Used for sign-in, password resets, and email verification codes.')"
                    :save="saveEmail" :button="$t('Update email')" :message="$t('Sign-in email updated.')"
                    @saved="refreshProfile" @success="notify('success', $event)" @error="notify('error', $event)">
                    <TextElement name="user_email" :default="profile.user_email" :label="$t('Email address')"
                        autocomplete="email" :floating="false" :columns="{ sm: { container: 6 } }" />
                    <TextElement name="current_password" id="email-current-password" input-type="password"
                        :label="$t('Current password')" :description="$t('Needed to change your sign-in email.')"
                        autocomplete="current-password" :floating="false" :columns="{ sm: { container: 6 } }" />
                </MyAccountForm>
            </section>

            <!-- PASSWORD -->
            <section v-show="selectedMenuOption === 'password'" aria-labelledby="my-account-password" class="space-y-5">
                <div>
                    <h2 id="my-account-password" class="text-xl font-semibold text-gray-900">{{ $t('Password') }}</h2>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">{{ $t("Choose a password you don't use on any other site.") }}</p>
                </div>

                <div v-if="profile.directoryManaged"
                    class="flex items-start gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3.5 text-sm text-indigo-800">
                    <LockClosedIcon class="size-5 shrink-0" aria-hidden="true" />
                    <p>{{ $t('Change your password in your connected directory.') }}</p>
                </div>

                <MyAccountForm v-else :save="savePassword" :button="$t('Change password')" :message="$t('Password updated.')"
                    @success="notify('success', $event)" @error="notify('error', $event)">
                    <TextElement name="current_password" input-type="password" :label="$t('Current password')"
                        autocomplete="current-password" :floating="false" :columns="{ sm: { container: 12, wrapper: 6 } }" />
                    <TextElement name="password" input-type="password" :label="$t('New password')"
                        autocomplete="new-password" :floating="false" :columns="{ sm: { container: 6 } }" />
                    <TextElement name="password_confirmation" input-type="password" :label="$t('Confirm new password')"
                        autocomplete="new-password" :floating="false" :columns="{ sm: { container: 6 } }" />
                </MyAccountForm>
            </section>

            <!-- TWO-FACTOR AUTHENTICATION -->
            <section v-show="selectedMenuOption === 'two_factor'" aria-labelledby="my-account-two-factor" class="space-y-5">
                <div>
                    <h2 id="my-account-two-factor" class="text-xl font-semibold text-gray-900">{{ $t('Two-factor authentication') }}</h2>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">{{ $t("Ask for a code from your phone after your password, so a leaked password isn't enough to get into your account.") }}</p>
                </div>
                <AuthenticatorSettings :authenticator="authenticator" :email="profile.user_email"
                    :directory-managed="profile.directoryManaged" @changed="refreshAuthenticator"
                    @success="notify('success', $event)" @error="notify('error', $event)" />
            </section>
        </template>

        <template #overlays>
            <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
                @update:show="notificationShow = false" />
        </template>
    </PageWithSideMenu>
</template>

<script setup>
import axios from 'axios'
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { trans } from '@i18n'
import { KeyIcon, LockClosedIcon, ShieldCheckIcon, UserCircleIcon, UserIcon } from '@heroicons/vue/24/outline'
import PageWithSideMenu from '../Layouts/PageWithSideMenu.vue'
import MyAccountForm from './components/forms/MyAccountForm.vue'
import AuthenticatorSettings from './components/forms/AuthenticatorSettings.vue'
import Notification from './components/notifications/Notification.vue'

const props = defineProps({
    profile: Object,
    authenticator: Object,
    initial_section: { type: String, default: '' },
})

const pages = [
    { name: trans('Dashboard'), href: '/dashboard', current: false },
    { name: trans('My Account'), href: '/my-account', current: true },
]

const navigation = computed(() => [
    { key: 'profile', name: trans('Profile'), icon: UserIcon },
    { key: 'password', name: trans('Password'), icon: KeyIcon },
    {
        key: 'two_factor',
        name: trans('Two-factor authentication'),
        icon: ShieldCheckIcon,
        meta: { enabled: trans('On'), pending: trans('Unfinished') }[props.authenticator?.state] ?? trans('Off'),
    },
])
const initialMenuOption = ['profile', 'password', 'two_factor'].includes(props.initial_section)
    ? props.initial_section : 'profile'

const fullName = computed(() => [props.profile.first_name, props.profile.last_name].filter(Boolean).join(' '))
const directoryNote = computed(() => {
    if (props.profile.directoryManaged && props.profile.emailManaged) {
        return trans("Your name, sign-in email, and password come from your organization's directory. Ask your administrator if something needs to change.")
    }
    if (props.profile.directoryManaged) {
        return trans("Your name and password come from your organization's directory. Ask your administrator if something needs to change.")
    }
    return trans("Your sign-in email comes from your organization's directory. Ask your administrator if it needs to change.")
})

const saveName = data => axios.put('/user/profile-information', { ...data, section: 'name' })
const saveEmail = data => axios.put('/user/profile-information', { ...data, section: 'email' })
const savePassword = data => axios.put('/user/password', data)
const refreshProfile = () => router.reload({ only: ['profile', 'auth'] })
const refreshAuthenticator = () => router.reload({ only: ['authenticator'] })

const notificationShow = ref(false)
const notificationType = ref(null)
const notificationMessages = ref(null)
function notify(type, messages) {
    notificationType.value = type
    notificationMessages.value = messages
    notificationShow.value = true
}
</script>
