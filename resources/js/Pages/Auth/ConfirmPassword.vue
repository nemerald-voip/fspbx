<template>
    <Head :title="$t('Confirm your password')" />
    <main class="mx-auto max-w-md px-5 py-12">
        <h1 class="text-xl font-bold">{{ $t('Confirm your password') }}</h1>
        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <label class="block text-sm">{{ $t('Current password') }}
                <input v-model="form.password" autofocus type="password" autocomplete="current-password" class="mt-1 block w-full rounded-md border-gray-300" />
            </label>
            <p v-if="form.errors.password" role="alert" class="text-sm text-red-700">{{ form.errors.password }}</p>
            <button :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">{{ $t('Confirm password') }}</button>
        </form>
    </main>
</template>

<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3'

const form = useForm({ password: '', _token: usePage().props.csrf_token })
function submit() {
    form.post('/user/confirm-password', { onFinish: () => form.reset('password') })
}
</script>
