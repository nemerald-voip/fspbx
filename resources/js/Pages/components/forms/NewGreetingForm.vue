<template>
    <div class="mt-8 shadow sm:rounded-md">
        <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
            <div>
                <h3 class="text-base font-semibold leading-6 text-gray-900">{{ title }}</h3>
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
            </div>

            <!-- Text-to-Speech Fields -->
            <div v-if="selectedGreetingMethod === 'text-to-speech'" class="grid grid-cols-6 gap-6">
                <div class="col-span-6">
                    <LabelInputOptional target="custom_greeting_message" label="Custom greeting message"
                        class="truncate" />
                    <div class="mt-2">
                        <Textarea v-model="greetingForm.input" id="custom_greeting_message"
                            :placeholder="sample_message" name="custom_greeting_message" rows="3"
                            :error="!!errors?.input" />
                    </div>
                    <div v-if="errors?.input" class="mt-2 text-xs text-red-600">
                        {{ errors.input[0] }}
                    </div>
                </div>

                <div class="col-span-3 sm:col-span-2 text-sm font-medium leading-6 text-gray-900">
                    <!-- <LabelInputOptional label="Voice" class="truncate mb-1" />
                    <ComboBox :options="voices" :search="true" :placeholder="'Choose voice'"
                        :selectedItem="default_voice ?? voices[0]?.value" @update:model-value="handleUpdateVoice"
                        :error="!!errors?.voice" />
                    <div v-if="errors?.voice" class="mt-2 text-xs text-red-600">
                        {{ errors.voice[0] }}
                    </div> -->

                    <Vueform>
                        <SelectElement name="voice" label-prop="name" :items="voices" :default="default_voice ?? voices[0]?.value" 
                        @change="handleUpdateVoice"
                        :search="true" :native="false" label="Voice" input-type="search" autocomplete="off" placeholder="Choose Voice"
                            :floating="false" />
                    </Vueform>

                </div>

                <div class="col-span-3 sm:col-span-2 text-sm font-medium leading-6 text-gray-900">
                    <!-- <LabelInputOptional label="Speed" class="truncate mb-1" />
                    <ComboBox :options="speeds" :search="true" :placeholder="'Choose Speed'" :selectedItem="'1.00'"
                        @update:model-value="handleUpdateSpeed" :error="!!errors?.speed" />
                    <div v-if="errors?.speed" class="mt-2 text-xs text-red-600">
                        {{ errors.speed[0] }}
                    </div> -->

                    <Vueform>
                        <SelectElement name="speed" label-prop="name" :items="speeds" :default="'1.00'" 
                        @change="handleUpdateSpeed"
                        :search="true" :native="false" label="Speed" input-type="search" autocomplete="off" placeholder="Choose Speed"
                            :floating="false" />
                    </Vueform>
                </div>

                <div class="content-end col-span-2 text-sm font-medium leading-6 text-gray-900">
                    <button @click.stop.prevent="generateGreeting" :class="{ 'mb-6': errors?.voice || errors?.speed }"
                        class="inline-flex justify-center rounded-md bg-white px-5 py-2 gap-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-200">
                        Generate
                        <Spinner class="ml-1" :color="'text-gray-700'" :show="isFormSubmiting" />
                        <!-- <PlayCircleIcon class="h-5 w-5 text-gray-500 hover:text-gray-700" /> -->
                    </button>
                </div>

                <div v-if="audioUrl" class="col-span-6 sm:col-span-4 mt-2">
                    <audio controls :src="audioUrl" class="w-full"></audio>
                </div>

                <div v-if="audioUrl" class="content-center col-span-2 text-sm font-medium leading-6 text-gray-900">
                    <button @click.stop.prevent="saveGreeting"
                        class="inline-flex justify-center rounded-md bg-indigo-600 px-5 py-2 gap-2 text-sm font-semibold text-white shadow-sm ring-1 ring-inset ring-indigo-300 hover:bg-indigo-500">
                        Apply
                        <Spinner class="ml-1" :color="'text-gray-700'" :show="isSaving" />
                        <!-- <PlayCircleIcon class="h-5 w-5 text-gray-500 hover:text-gray-700" /> -->
                    </button>
                </div>
            </div>

            <!-- Upload Fields -->
            <div v-if="selectedGreetingMethod === 'upload'" class="grid grid-cols-6 gap-6">
                <div class="col-span-6 sm:col-span-4">
                    <div>
                        <LabelInputOptional target="upload_file" label="Upload file" class="truncate" />
                        <div class="mt-2 flex rounded-md shadow-sm">
                            <button type="button" @click="browseFile"
                                class="relative -ml-px inline-flex items-center gap-x-1.5 rounded-l-md px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                Browse
                            </button>
                            <div class="relative flex flex-grow items-stretch focus-within:z-10">
                                <input type="text" name="upload_file" id="upload_file" :value="selectedFileName"
                                    disabled
                                    class="block w-full rounded-none rounded-r-md border-0 py-1.5 pl-3 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                    placeholder="No file selected" />
                            </div>
                        </div>
                        <input ref="fileInput" type="file" name="file" id="file" class="hidden"
                            @change="handleFileUpload" accept=".wav, .mp3, .m4a" />
                        <p class="mt-2 text-sm text-gray-500 " id="file_input_help">Supported formats: WAV,
                            MP3, or M4A</p>
                    </div>
                </div>

                <div v-if="fileToUpload" class="content-center col-span-2 text-sm font-medium leading-6 text-gray-900">
                    <button @click.prevent="uploadFile" :disabled="isFormSubmiting"
                        class="inline-flex justify-center rounded-md bg-indigo-600 px-5 py-2 gap-2 text-sm font-semibold text-white shadow-sm ring-1 ring-inset ring-indigo-300 hover:bg-indigo-500 disabled:bg-indigo-500">
                        Upload
                        <Spinner class="ml-1" :color="'text-gray-700'" :show="isFormSubmiting" />
                        <!-- <PlayCircleIcon class="h-5 w-5 text-gray-500 hover:text-gray-700" /> -->
                    </button>
                </div>
            </div>

            <!-- Phone Call Instructions -->
            <div v-if="selectedGreetingMethod === 'phone-call'" class="mt-3">
                <p class="text-sm text-gray-500">
                    To record a new greeting using your phone, follow these steps:
                </p>
                <ul class="mt-2 text-sm text-gray-500 list-disc pl-5">
                    <li v-for="(instruction, index) in phone_call_instructions" :key="index" v-html="instruction"></li>
                </ul>
            </div>

            <div v-if="errors?.server" class="grid grid-cols-6 gap-6">
                <div class="col-span-6 rounded-md bg-red-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <XCircleIcon class="h-5 w-5 text-red-400" aria-hidden="true" />
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">{{ errors.server[0] }}</h3>

                        </div>
                    </div>
                </div>
            </div>

            <div v-if="successMessage" class="grid grid-cols-6 gap-6">
                <div class="col-span-6 rounded-md bg-green-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <CheckCircleIcon class="h-5 w-5 text-green-400" aria-hidden="true" />
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-green-800">{{ successMessage }}</h3>


                        </div>
                    </div>
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
import Spinner from "@generalComponents/Spinner.vue";
import { XCircleIcon, CheckCircleIcon } from '@heroicons/vue/20/solid'


const props = defineProps({
    title: String,
    voices: Object,
    default_voice: {
        type: String,
        default: null
    },
    speeds: Object,
    routes: Object,
    sample_message: String,
    phone_call_instructions: Object,
});

const emits = defineEmits(['greeting-saved']);

const page = usePage();

const selectedGreetingMethod = ref('text-to-speech');
const isFormSubmiting = ref(null);
const isSaving = ref(null);
const errors = ref(null);
const successMessage = ref(null);
const audioUrl = ref(null);
const applyUrl = ref(null);
const newGreetingFileName = ref(null);
const selectedFileName = ref('');
const fileToUpload = ref(null);
const voicemail_uuid = ref(null);

const greetingForm = reactive({
    input: null,
    voice: props.default_voice ?? props.voices[0].value,
    speed: '1.0',
    _token: page.props.csrf_token,
});

const handleUpdateVoice = (newValue, oldValue, el$) => {
    greetingForm.voice = newValue
};

const handleUpdateSpeed = (newValue, oldValue, el$) => {
    greetingForm.speed = newValue
};

const browseFile = () => {
    document.getElementById('file').click();
};

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        selectedFileName.value = file.name;
        fileToUpload.value = file;
    }
    event.target.value = null;
};

const uploadFile = () => {
    isFormSubmiting.value = true;
    successMessage.value = null;
    errors.value = {};

    const formData = new FormData();
    formData.append('file', fileToUpload.value);
    formData.append('_token', page.props.csrf_token);

    axios.post(props.routes.upload_greeting_route, formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    })
        .then(response => {
            isFormSubmiting.value = false;
            if (response.data.success) {
                emits('greeting-saved', {
                    greeting_id: response.data.greeting_id,
                    greeting_name: response.data.greeting_name
                });
                selectedFileName.value = '';
                fileToUpload.value = null;
                successMessage.value = response.data.messages.success;

                // Dismiss success message after 5 seconds
                setTimeout(() => {
                    successMessage.value = null;
                }, 5000);
            }
        })
        .catch(error => {
            isFormSubmiting.value = false;
            handleFormErrorResponse(error);
        });
};


const generateGreeting = () => {
    // Functionality to generate greeting
    isFormSubmiting.value = true;
    errors.value = null;
    audioUrl.value = null; // Reset audio URL

    axios.post(props.routes.text_to_speech_route, greetingForm)
        .then((response) => {
            isFormSubmiting.value = false;
            // console.log(response.data);
            if (response.data.success) {
                audioUrl.value = response.data.file_url; // Set the audio URL
                applyUrl.value = response.data.apply_url; // Set the audio URL
                newGreetingFileName.value = response.data.file_name;
                if (response.data.voicemail_uuid) {
                    voicemail_uuid.value = response.data.voicemail_uuid;
                }
            }
        }).catch((error) => {
            isFormSubmiting.value = false;
            handleFormErrorResponse(error);
        });
};

const saveGreeting = () => {
    // Functionality to save generated greeting
    isSaving.value = true;
    errors.value = null;

    // Create the payload containing greetingForm.input and newGreetingFileName
    const payload = {
        input: greetingForm.input,
        file_name: newGreetingFileName.value, // using .value because it's a ref
        _token: page.props.csrf_token, // Include CSRF token if needed
    };

    // Add voicemail_id if it exists
    if (voicemail_uuid.value) {
        payload.voicemail_uuid = voicemail_uuid.value;
    }

    axios.post(applyUrl.value, payload)
        .then((response) => {
            isSaving.value = false;
            // console.log(response.data);
            if (response.data.success) {
                // Emit the event with the greeting_id and greeting_name
                emits('greeting-saved', {
                    greeting_id: response.data.greeting_id,
                    greeting_name: response.data.greeting_name,
                    description: response.data.description
                });
                audioUrl.value = false;
                successMessage.value = response.data.messages.success[0];
                // Dismiss success message after 5 seconds
                setTimeout(() => {
                    successMessage.value = null;
                }, 5000);
            }

        }).catch((error) => {
            isSaving.value = false;
            handleFormErrorResponse(error);
        });
};


const handleFormErrorResponse = (error) => {
    if (error.request?.status == 419) {
        errors.value = { request: ["Session expired. Reload the page"] };
    } else if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        if (error.response.data && error.response.data.errors) {
            errors.value = error.response.data.errors;
        }

        if (error.response.data && error.response.data.message) {
            errors.value = { server: [error.response.data.message] };
        }
    } else if (error.request) {
        // The request was made but no response was received
        console.log(error.request);
    } else {
        // Something happened in setting up the request that triggered an Error
        console.log(error.message);
    }
}



</script>
