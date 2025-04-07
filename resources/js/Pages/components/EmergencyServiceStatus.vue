<template>
    <div class="grid grid-cols-12 gap-6 pb-6 px-4">
        <div class="col-span-6 sm:col-span-3">
            <div class="flex items-start">
                <div v-if="loading">
                    <Spinner :show="loading" />
                </div>
                <div v-else>
                    <div v-if="status == true" class="shrink-0">
                        <CheckCircleIcon class="size-6 text-green-400" aria-hidden="true" />
                    </div>
                    <div v-else class="shrink-0">
                        <ExclamationCircleIcon class="size-6 text-rose-400" aria-hidden="true" />
                    </div>
                </div>
                <div class="ml-3 w-0 flex-1 pt-0.5">
                    <p class="text-sm font-medium text-gray-900">Emergency Call monitoring service</p>
                </div>

            </div>

        </div>
        <!-- <div class="col-span-6 sm:col-span-3">
            <button v-if="status == true" type="button"
                @click="stopService"
                class="inline-flex items-center gap-x-1.5 rounded-md bg-rose-600 px-4 py-1 text-sm font-semibold text-white shadow-sm hover:bg-rose-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:rose-indigo-600">
                <XMarkIcon class="-ml-0.5 size-5" aria-hidden="true" />
                Stop
            </button>

            <button v-else type="button"
                @click="startService"
                class="inline-flex items-center gap-x-1.5 rounded-md bg-green-600 px-4 py-1 text-sm font-semibold text-white shadow-sm hover:bg-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600">
                <PlayIcon class="-ml-0.5 size-5" aria-hidden="true" />
                Start
            </button>
        </div> -->

    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { PlayIcon } from "@heroicons/vue/24/outline";
import { XMarkIcon } from "@heroicons/vue/24/outline";
import { CheckCircleIcon, ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import Spinner from "@generalComponents/Spinner.vue";


const props = defineProps({
    routes: Object,
})

const status = ref(null);
const loading = ref(true);
const error = ref(null);

const checkStatus = async () => {
    loading.value = true;
    try {
        const response = await axios.post(props.routes.emergency_calls_service_status);
        status.value = response.data.status; 
        // console.log(response.data.status);
        error.value = null;
    } catch (err) {
        error.value = err.response?.data?.message || err.message;
        logger(error.value);
    } finally {
        loading.value = false;
    }
};


onMounted(() => {
    checkStatus();
});

</script>

