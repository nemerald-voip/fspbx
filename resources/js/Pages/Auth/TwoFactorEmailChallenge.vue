<template>
    <Head title="Two-factor Email Confirmation" />

    <div class="flex min-h-full flex-1 flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <img class="mx-auto h-10 w-auto" :src="logoUrl" />
            <h2 class="mt-6 text-center text-xl font-bold leading-9 tracking-tight text-gray-900">Two-factor
                authentication code
            </h2>
        </div>

        <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-[480px]">
            <div class="bg-white px-6 py-12 shadow sm:rounded-lg sm:px-12">

                <div class="mb-4 text-sm text-center text-gray-600">
                    A one-time verification code has been sent to your email. Be sure to check your junk or spam folder.
                </div>

                <div v-if="status" class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
                    {{ status }}
                </div>

                <div v-if="errorMessage" class="mb-4 font-medium text-sm text-red-600 dark:text-red-400">
                    {{ errorMessage }}
                </div>

                <form class="space-y-6" action="#" method="POST">
                    <div>
                        <!-- <label for="code" class="block text-sm font-medium leading-6 text-gray-900">Enter your verification code</label> -->
                        <div class="mt-2">
                            <input v-model="form.code" id="code" name="code" type="text" required
                                :class="['block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm placeholder:text-gray-400 focus:ring-2 focus:ring-inset sm:text-sm sm:leading-6', { 'ring-1 ring-inset ring-red-600': errors.code, 'ring-1 ring-inset ring-gray-300': !errors.code }]"
                                placeholder="Enter your verification code" />
                        </div>
                        <!-- Error message for code -->
                        <div v-if="errors.code" class="mt-2 text-sm text-red-600">
                            {{ errors.code }}
                        </div>
                    </div>

                    <div class="flex items-center justify-start">
                        <div class="flex items-center">
                            <input v-model="form.remember" id="remember" name="remember" type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                            <label for="remember" class="ml-3 block text-sm leading-6 text-gray-900">Remember this device
                                for 30 days</label>
                        </div>

                    </div>



                    <div>
                        <button @click.prevent="submitForm" type="submit" :disabled="isLoading"
                            class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            <svg v-if="isLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                                </circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            Verify
                        </button>
                    </div>
                    <div>
                        <button @click.prevent="requestNewCode" type="submit"
                            class="flex w-full justify-center rounded-md bg-gray-100 px-3 py-1.5 ring-1 ring-gray-400 text-sm font-semibold leading-6 text-gray shadow-sm hover:bg-gray-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            <svg v-if="isLoadingNewCode" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                                </circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            Resend verification code?
                        </button>

                    </div>
                </form>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    code: '',
    remember: '',
    _token: null,
});

const props = defineProps({
    errors: Object,
    links: Object,
    status: String,
})

const logoUrl = ref('/storage/logo.png');
const isLoading = ref(false);
const isLoadingNewCode = ref(false);
const errorMessage = ref(null);


// Function to handle form submission
const submitForm = () => {

    isLoading.value = true;

    axios.get('csrf-token/refresh')
        .then((response) => {
            // Update the form's token value
            form._token = response.data.token;

            form.post(props.links['email-challenge'], {
                onFinish: () => {
                    isLoading.value = false;
                }
            });
        }).catch((error) => {
            errorMessage.value = "Invalid token. Refresh the page."
            isLoading.value = false; // Reset loading state on error
        });
}

// Function to handle new code request
const requestNewCode = () => {

    isLoadingNewCode.value = true;
    form.reset('code');

    axios.get('csrf-token/refresh')
        .then((response) => {
            // Update the form's token value
            form._token = response.data.token;

            form.put(props.links['email-challenge'], {
                onFinish: () => {
                    isLoadingNewCode.value = false;
                }
            });
        }).catch((error) => {
            errorMessage.value = "Invalid token. Refresh the page."
            isLoading.value = false; // Reset loading state on error
        });
}

</script>