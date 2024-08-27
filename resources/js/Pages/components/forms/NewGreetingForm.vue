<template>
    <div class="mt-8 shadow sm:rounded-md">
        <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
            <div>
                <h3 class="text-base font-semibold leading-6 text-gray-900">New greeting</h3>
                <p class="mt-1 text-sm text-gray-500">Select a method for creating a new greeting.</p>
            </div>

            <div class="grid grid-cols-6 gap-6">
                <div class="col-span-6 ">
                    <fieldset>
                        <div class="mt-3 space-y-6 sm:flex sm:items-center sm:space-x-10 sm:space-y-0">
                            <div class="flex items-center">
                                <input id="greeting-ai" name="method" type="radio"
                                    :checked="selectedGreetingMethod === 'text-to-speech'"
                                    @click="selectedGreetingMethod = 'text-to-speech'"
                                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                <label for="greeting-ai"
                                    class="ml-3 block text-sm font-medium leading-6 text-gray-900">Text-to-speech</label>
                            </div>
                            <div class="flex items-center">
                                <input id="greeting-upload" name="method" type="radio"
                                    :checked="selectedGreetingMethod === 'upload'"
                                    @click="selectedGreetingMethod = 'upload'"
                                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                <label for="greeting-upload"
                                    class="ml-3 block text-sm font-medium leading-6 text-gray-900">Upload</label>
                            </div>
                            <div class="flex items-center">
                                <input id="greeting-call" name="method" type="radio"
                                    :checked="selectedGreetingMethod === 'phone-call'"
                                    @click="selectedGreetingMethod = 'phone-call'"
                                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                                <label for="greeting-call"
                                    class="ml-3 block text-sm font-medium leading-6 text-gray-900">Phone call</label>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <div class="col-span-6">
                    <LabelInputOptional target="custom_greeting_message" label="Custom greeting message" class="truncate" />
                    <div class="mt-2">
                        <Textarea v-model="greetingForm.input" id="custom_greeting_message"
                            placeholder="Thank you for calling. Please, leave us a message and will call you back as soon as possible"
                            name="custom_greeting_message" rows="3" :error="!!errors?.input" />
                    </div>
                    <div v-if="errors?.input" class="mt-2 text-xs text-red-600">
                        {{ errors.input[0] }}
                    </div>
                </div>

                <div class="col-span-3 sm:col-span-2 text-sm font-medium leading-6 text-gray-900">
                    <LabelInputOptional label="Voice" class="truncate mb-1" />
                    <ComboBox :options="voices" :search="true" :placeholder="'Choose voice'"
                        :selectedItem="voices[0]?.value" @update:model-value="handleUpdateVoice" :error="!!errors?.voice"/>
                    <div v-if="errors?.voice" class="mt-2 text-xs text-red-600">
                        {{ errors.voice[0] }}
                    </div>
                </div>

                <div class="col-span-3 sm:col-span-2 text-sm font-medium leading-6 text-gray-900">
                    <LabelInputOptional label="Speed" class="truncate mb-1" />
                    <ComboBox :options="speeds" :search="true" :placeholder="'Choose Speed'" :selectedItem="'1.0'"
                        @update:model-value="handleUpdateSpeed" :error="!!errors?.spped" />
                    <div v-if="errors?.speed" class="mt-2 text-xs text-red-600">
                        {{ errors.speed[0] }}
                    </div>
                </div>

                <div class="content-end col-span-2 text-sm font-medium leading-6 text-gray-900">
                    <button @click.prevent="generateGreeting"
                        :class="{'mb-6': errors?.voice || errors?.speed}"
                        class="inline-flex justify-center rounded-md bg-white px-5 py-2 gap-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-200">
                        <Spinner class="ml-1" :color="'text-gray-700'" :show="isFormSubmiting" />
                        Play
                        <PlayCircleIcon class="h-5 w-5 text-gray-500 hover:text-gray-700" />
                    </button>
                </div>

                <div v-if="errors?.server" class="col-span-6 mt-2 text-xs text-red-600">
                        {{ errors.server[0] }}
                    </div>

            </div>
        </div>
    </div>

</template>
  
<script setup>
import { ref, reactive } from 'vue';
import { usePage } from '@inertiajs/vue3';

import ComboBox from '../general/ComboBox.vue';
import Textarea from '@generalComponents/Textarea.vue';
import LabelInputOptional from '../general/LabelInputOptional.vue';
import { PlayCircleIcon } from '@heroicons/vue/24/solid';
import Spinner from "@generalComponents/Spinner.vue";

const props = defineProps({
    voices: Object,
    speeds: Object,
});

const page = usePage();

const selectedGreetingMethod = ref('text-to-speech');
const isFormSubmiting = ref(null);
const errors = ref(null);

const greetingForm = reactive({
    input: null,
    voice: props.voices[0].value,
    speed: '1.0',
    _token: page.props.csrf_token,
});

const handleUpdateVoice = (voice) => {
    greetingForm.voice = voice.value;
};

const handleUpdateSpeed = (speed) => {
    greetingForm.speed = speed.value;
};

const generateGreeting = () => {
    // Functionality to generate greeting
    console.log(greetingForm);

    isFormSubmiting.value = true;
    errors.value = null;

    axios.post('/text-to-speech', greetingForm)
        .then((response) => {
            isFormSubmiting.value = false;
            showNotification('success', response.data.messages);
        }).catch((error) => {
            isFormSubmiting.value = false;
            handleFormErrorResponse(error);
        });
};

const handleFormErrorResponse = (error) => {
    if (error.request?.status == 419) {
        showNotification('error', { request: ["Session expired. Reload the page"] });
    } else if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        errors.value = error.response.data.errors;
        console.log(error.response.data.errors);
    } else if (error.request) {
        // The request was made but no response was received
        console.log(error.request);
    } else {
        // Something happened in setting up the request that triggered an Error
        console.log(error.message);
    }
}



</script>
  