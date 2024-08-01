<template>
    <div class="flex min-h-full flex-1 flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <img class="mx-auto h-10 w-auto" :src="logoUrl" />
            <h2 class="mt-6 text-center text-2xl font-bold leading-9 tracking-tight text-gray-900">Get Mobile App Password</h2>
        </div>

        <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-[480px]">
            <div class="bg-white px-6 py-12 shadow sm:rounded-lg sm:px-12">

                <form class="space-y-6" action="#" method="POST">
                    <div>
                        <strong>Display Name:</strong> {{ props.display_name }}
                    </div>

                    <div>
                        <strong>PBX Extension:</strong> {{ props.extension }}
                    </div>

                    <div>
                        <strong>Domain:</strong> {{ props.domain }}
                    </div>

                    <div>
                        <strong>Username:</strong> {{ props.username }}
                    </div>

                    <div v-if="hasPassword">
                        <strong>Password:</strong> {{ hasPassword }}
                    </div>

                    <div v-if="hasQR">
                        <img :src="'data:image/png;base64,' + hasQR" alt="QR" />
                    </div>

                    <div v-if="!(hasPassword && hasQR)">
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
                            Get Password
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    routes: Object,
    display_name: String,
    extension: String,
    domain: String,
    username: String,
})

const logoUrl = ref('/storage/logo.png');
const isLoading = ref(false);
const hasQR = ref(null);
const hasPassword = ref(null);

// Function to handle form submission
const submitForm = () => {
    isLoading.value = true;
    axios.post(`${props.routes.retrieve_password}`)
        .then((response) => {
            if(response.data.qrcode)
                hasQR.value = response.data.qrcode;
            if(response.data.password)
                hasPassword.value = response.data.password;
            isLoading.value = false;
        })
        .catch((error) => {
            console.error(error)
            isLoading.value = false;
        });
}
</script>
