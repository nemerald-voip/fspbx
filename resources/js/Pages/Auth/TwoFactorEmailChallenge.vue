<template>

    <Head title="Two-factor Email Confirmation" />

    <div class="flex min-h-full flex-1 flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <img class="mx-auto h-10 w-auto" :src="logoUrl" />
            <h2 class="mt-6 text-center text-2xl font-bold leading-9 tracking-tight text-gray-900">Two-factor Email Confirmation
            </h2>
        </div>

        <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-[480px]">
            <div class="bg-white px-6 py-12 shadow sm:rounded-lg sm:px-12">

                <div class="mb-4 text-sm text-gray-600">
                    Please confirm access to your account by entering the code sent to your email.
                </div>


                <form class="space-y-6" action="#" method="POST">
                    <div>
                        <label for="code" class="block text-sm font-medium leading-6 text-gray-900">Code</label>
                        <div class="mt-2">
                            <input v-model="form.code" id="code" name="code" type="text"
                                required
                                :class="['block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm placeholder:text-gray-400 focus:ring-2 focus:ring-inset sm:text-sm sm:leading-6']" />
                        </div>
                        <!-- Error message for code -->
                        <!-- <div v-if="errors.code" class="mt-2 text-sm text-red-600">
                            {{ errors.code }}
                        </div> -->
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
                            Email Password Reset Link
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</template>

<script setup>
import { nextTick, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';


const form = useForm({
    code: '',
});

const props = defineProps({
    errors: Object,
})

const logoUrl = ref('/storage/logo.png');
const isLoading = ref(false);
const codeInput = ref(null);


const submit = () => {
    form.post(route('two-factor.login'));
};
</script>