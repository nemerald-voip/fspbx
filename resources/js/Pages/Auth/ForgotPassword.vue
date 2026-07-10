<template>
    <div class="flex min-h-full flex-1 flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <img class="mx-auto h-10 w-auto" :src="logoUrl" />
            <h2 class="mt-6 text-center text-2xl font-bold leading-9 tracking-tight text-heading">Reset Password
            </h2>
        </div>

        <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-[480px]">
            <div class="bg-surface px-6 py-12 shadow sm:rounded-lg sm:px-12">

                <div class="mb-4 text-sm text-body">
                    Forgot your password? No problem. Just let us know your email address and we will email you a
                    password reset link that will allow you to choose a new one.
                </div>

                <div v-if="status" class="mb-4 font-medium text-sm text-success">
                    {{ status }}
                </div>

                <form class="space-y-6" action="#" method="POST">
                    <div>
                        <label for="user_email" class="block text-sm font-medium leading-6 text-heading">Email
                            address</label>
                        <div class="mt-2">
                            <input v-model="form.user_email" id="user_email" name="user_email" type="email"
                                autocomplete="email" required
                                :class="['block w-full rounded-md border-0 py-1.5 text-heading shadow-sm placeholder:text-subtle focus:ring-2 focus:ring-inset sm:text-sm sm:leading-6', { 'ring-1 ring-inset ring-danger': errors.email, 'ring-1 ring-inset ring-strong': !errors.email }]" />
                        </div>
                        <!-- Error message for user_email -->
                        <div v-if="errors.email" class="mt-2 text-sm text-danger">
                            {{ errors.email }}
                        </div>
                    </div>

                    <div>
                        <button @click.prevent="submitForm" type="submit"
                            class="flex w-full justify-center rounded-md bg-accent px-3 py-1.5 text-sm font-semibold leading-6 text-on-accent shadow-sm hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                            <svg v-if="isLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-on-accent"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                                </circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            Email Password Reset Link
                        </button>
                    </div>
                </form>

                <div class="mt-5 flex justify-center">
                    <div class="text-sm leading-6">
                        <Link :href="links['login']" class=" font-semibold text-accent-fg hover:text-accent-fg">
                        Back to Log In
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
    status: String,
    errors: Object,
    links: Object,
})

const form = useForm({
    user_email: '',
    _token: '',
});

const logoUrl = ref('/storage/logo.png');
const isLoading = ref(false);



// Function to handle form submission
const submitForm = () => {

    isLoading.value = true;

    // form.post(props.links['password-email'],{
    //     onFinish: () => {
    //         isLoading.value = false;
    //     },
    //     onError: (errors) => {
    //         // console.log(errors);
    //     },
    // }
    // );
// };

    axios.get('csrf-token/refresh')
        .then((response) => {
            // Update the form's token value
            form._token = response.data.token;

            form.post(props.links['password-email'], {
                onFinish: () => {
                    isLoading.value = false;
                },
                onError: (errors) => {
                    // console.log(errors);
                },
            });
        }).catch((error) => {
            errorMessage.value = "Invalid token. Refresh the page."
            isLoading.value = false; // Reset loading state on error
        });


}

</script>