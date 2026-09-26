<template>
    <Head :title="$t('Reset Password')" />
    <div class="flex min-h-full flex-1 flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <img class="mx-auto h-10 w-auto" :src="logoUrl" :alt="$t('Logo')" />
            <h2 class="mt-6 text-center text-2xl font-bold leading-9 tracking-tight text-gray-900">{{ $t('Reset Password') }}
            </h2>
        </div>

        <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-[480px]">
            <div class="bg-white px-6 py-12 shadow sm:rounded-lg sm:px-12">

                <form class="space-y-6" action="#" method="POST">
                    <div>
                        <label for="user_email" class="block text-sm font-medium leading-6 text-gray-900">{{ $t('Email address') }}</label>
                        <div class="mt-2">
                            <input v-model="form.user_email" id="user_email" name="user_email" type="email"
                                autocomplete="email" required
                                :class="['block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm placeholder:text-gray-400 focus:ring-2 focus:ring-inset sm:text-sm sm:leading-6', { 'ring-1 ring-inset ring-red-600': errors.user_email, 'ring-1 ring-inset ring-gray-300': !errors.user_email }]" />
                        </div>
                        <!-- Error message for user_email -->
                        <div v-if="errors.user_email" class="mt-2 text-sm text-red-600">
                            {{ errors.user_email }}
                        </div>
                        <!-- Error message for email -->
                        <div v-if="errors.email" class="mt-2 text-sm text-red-600">
                            {{ errors.email }}
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium leading-6 text-gray-900">{{ $t('Password') }}</label>
                        <div class="mt-2">
                            <input v-model="form.password" id="password" name="password" type="password"
                                autocomplete="current-password" required
                                :class="['block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm placeholder:text-gray-400 focus:ring-2 focus:ring-inset sm:text-sm sm:leading-6', { 'ring-1 ring-inset ring-red-600': errors.password, 'ring-1 ring-inset ring-gray-300': !errors.password }]" />
                        </div>
                        <!-- Error message for password -->
                        <div v-if="errors.password" class="mt-2 text-sm text-red-600">
                            {{ errors.password }}
                        </div>
                    </div>

                    <div>
                        <label for="password_confirmation"
                            class="block text-sm font-medium leading-6 text-gray-900">{{ $t('Confirm Password') }}</label>
                        <div class="mt-2">
                            <input v-model="form.password_confirmation" id="password_confirmation"
                                name="password_confirmation" type="password" autocomplete="current-password" required
                                :class="['block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm placeholder:text-gray-400 focus:ring-2 focus:ring-inset sm:text-sm sm:leading-6', { 'ring-1 ring-inset ring-red-600': errors.password_confirmation, 'ring-1 ring-inset ring-gray-300': !errors.password_confirmation }]" />
                        </div>
                        <!-- Error message for password -->
                        <div v-if="errors.password_confirmation" class="mt-2 text-sm text-red-600">
                            {{ errors.password_confirmation }}
                        </div>

                        <ul class="list-disc ml-5 mt-3 space-y-0.5 text-xs">
                            <li>{{ $t('At least 10 characters') }}</li>
                            <li>{{ $t('Includes letters') }}</li>
                            <li>{{ $t('Has both uppercase and lowercase letters') }}</li>
                            <li>{{ $t('Has at least one number (0–9)') }}</li>
                            <li>{{ $t('Has at least one symbol (e.g., ! @ # $ % ^ & * ( ) _ +)') }}</li>
                            <li>{{ $t('Not a known compromised password (checked automatically)') }}</li>
                        </ul>

                    </div>


                    <div>
                        <button @click.prevent="submitForm" type="submit"
                            class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            <svg v-if="isLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4">
                                </circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            {{ $t('Reset Password') }}
                        </button>
                    </div>
                </form>

                <div class="mt-5 flex justify-center">
                    <div class="text-sm leading-6">
                        <Link :href="links['login']" class=" font-semibold text-indigo-600 hover:text-indigo-500">
                        {{ $t('Back to Log In') }}
                        </Link>
                    </div>
                </div>

            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from "@inertiajs/vue3";
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    errors: Object,
    links: Object,
    user_email: String,
    token: String,
})

const form = useForm({
    token: props.token,
    user_email: props.user_email,
    password: '',
    password_confirmation: '',
});

const logoUrl = ref('/storage/logo.png');
const isLoading = ref(false);



// Function to handle form submission
const submitForm = () => {

    isLoading.value = true;

    form.post(props.links['password-update'], {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
            isLoading.value = false;
        },
        onError: (errors) => {
            // console.log(errors);
        },
    });

}

</script>
