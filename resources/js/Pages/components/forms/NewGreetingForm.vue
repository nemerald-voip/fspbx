<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-50" @close="closeModal">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>
            <div class="fixed inset-0 z-50 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                        <DialogPanel
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:p-6">

                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header || title }}
                            </DialogTitle>
                            <p class="mt-1 mb-6 text-sm text-gray-500">Select a method for creating a new greeting.</p>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="closeModal">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <Vueform ref="form$" :endpoint="false">

                                <!-- Responsive Radio Group -->
                                <RadiogroupElement name="greeting_method" :items="[
                                    { value: 'text-to-speech', label: 'Text-to-speech' },
                                    { value: 'upload', label: 'Upload' },
                                    { value: 'phone-call', label: 'Phone Call' },
                                ]" default="text-to-speech" :columns="12" :remove-class="{
                                    wrapper: ['flex-col', 'space-y-1', 'space-y-2', 'space-y-3', 'space-y-4']
                                }" :add-class="{
                                    wrapper: 'flex-col sm:flex-row flex-wrap gap-4 sm:gap-6 sm:space-y-0 mt-2 mb-4'
                                }" />

                                <!-- Text-to-Speech Fields -->
                                <TextareaElement name="input" label="Custom greeting message"
                                    :placeholder="sample_message" :rows="3" :columns="12" :floating="false"
                                    :error="errors?.input ? errors.input[0] : null"
                                    :conditions="[['greeting_method', '==', 'text-to-speech']]" />

                                <SelectElement name="voice" label="Voice" :items="voices"
                                    :search="true" :native="false" input-type="search"
                                    autocomplete="off" placeholder="Choose Voice" :floating="false"
                                    :default="props.default_voice" :columns="{
                                        sm: {
                                            container: 6,
                                        },
                                    }" :error="errors?.voice ? errors.voice[0] : null"
                                    :conditions="[['greeting_method', '==', 'text-to-speech']]" />

                                <SelectElement name="speed" label="Speed" :items="speeds"
                                   :search="true" :native="false" input-type="search" :default="'1.00'"
                                    autocomplete="off" placeholder="Choose Speed" :floating="false" :columns="{
                                        sm: {
                                            container: 6,
                                        },
                                    }" :error="errors?.speed ? errors.speed[0] : null"
                                    :conditions="[['greeting_method', '==', 'text-to-speech']]" />

                                <ButtonElement name="generate" label="&nbsp;" @click="generateGreeting"
                                    :secondary="true" :loading="isFormSubmiting" :submits="false" :full="true"
                                    :columns="{ container: 12, sm: 4 }"
                                    :conditions="[['greeting_method', '==', 'text-to-speech']]">
                                    Generate
                                </ButtonElement>

                                <StaticElement name="audio_player" v-if="audioUrl" :columns="{ container: 12, sm: 8 }"
                                    :conditions="[['greeting_method', '==', 'text-to-speech']]">
                                    
                                        <audio controls :src="audioUrl" class="w-full h-10"></audio>
                                </StaticElement>

                                <ButtonElement name="save" v-if="audioUrl" @click="saveGreeting"
                                    :loading="isSaving" :submits="false" :full="true"
                                    :columns="{ container: 12, sm: 4 }"
                                    :conditions="[['greeting_method', '==', 'text-to-speech']]">
                                    Apply
                                </ButtonElement>

                                <!-- Upload Fields -->
                                <FileElement name="upload_file" label="" accept=".wav, .mp3, .m4a"
                                    description="Supported formats: WAV, MP3, or M4A" :upload-temp-endpoint="false"
                                    :remove-temp-endpoint="false" :remove-endpoint="false" :drop="true" :add-classes="{
                                        FilePreview: {
                                            wrapper: 'bg-teal-50 border border-teal-200 rounded-md p-2 mt-3 shadow-sm',
                                            filenameStatic: 'font-semibold text-teal-700 truncate',
                                            remove: 'bg-teal-200 hover:bg-teal-300 text-teal-800 rounded-full p-1 ml-4'
                                        }
                                    }" @change="handleVueformFileUpload" :columns="{ container: 12, sm: 8 }"
                                    :error="errors?.file ? errors.file[0] : null"
                                    :conditions="[['greeting_method', '==', 'upload']]" />

                                <ButtonElement name="upload_btn" label="&nbsp;" @click="uploadFile"
                                    :loading="isFormSubmiting" :disabled="!fileToUpload || isFormSubmiting"
                                    :submits="false" :full="true" :columns="{ container: 12, sm: 4 }"
                                    :conditions="[['greeting_method', '==', 'upload']]">
                                    Upload
                                </ButtonElement>

                                <!-- Phone Call Instructions -->
                                <StaticElement name="phone_instructions" :columns="12"
                                    :conditions="[['greeting_method', '==', 'phone-call']]">
                                    <div class="mt-3">
                                        <p class="text-sm text-gray-500">
                                            To record a new greeting using your phone, follow these steps:
                                        </p>
                                        <ul class="mt-2 text-sm text-gray-500 list-disc pl-5">
                                            <li v-for="(instruction, index) in phone_call_instructions" :key="index"
                                                v-html="instruction" />
                                        </ul>
                                    </div>
                                </StaticElement>

                                <!-- Server Error Alert -->
                                <StaticElement name="server_error" v-if="errors?.server" :columns="12">
                                    <div class="rounded-md bg-red-50 p-4 mt-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <XCircleIcon class="h-5 w-5 text-red-400" aria-hidden="true" />
                                            </div>
                                            <div class="ml-3">
                                                <h3 class="text-sm font-medium text-red-800">{{ errors.server[0] }}
                                                </h3>
                                            </div>
                                        </div>
                                    </div>
                                </StaticElement>

                                <!-- Success Message Alert -->
                                <StaticElement name="success_message" v-if="successMessage" :columns="12">
                                    <div class="rounded-md bg-green-50 p-4 mt-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <CheckCircleIcon class="h-5 w-5 text-green-400" aria-hidden="true" />
                                            </div>
                                            <div class="ml-3">
                                                <h3 class="text-sm font-medium text-green-800">{{ successMessage }}
                                                </h3>
                                            </div>
                                        </div>
                                    </div>
                                </StaticElement>

                            </Vueform>

                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { XCircleIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    show: Boolean, // Required for modal toggle
    title: String,
    header: String,
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

const form$ = ref(null);

const emit = defineEmits(['close', 'error', 'success', 'saved']);
const page = usePage();

// UI State Variables
const isFormSubmiting = ref(false);
const isSaving = ref(false);
const errors = ref(null);
const successMessage = ref(null);
const audioUrl = ref(null);
const applyUrl = ref(null);
const newGreetingFileName = ref(null);
const fileToUpload = ref(null);
const voicemail_uuid = ref(null);

const closeModal = () => {
    emit('close');
};

const handleVueformFileUpload = (newValue) => {
    if (newValue instanceof File) {
        fileToUpload.value = newValue;
    } else {
        fileToUpload.value = null; // Clears the button if the user clicks the "X" to remove the file
    }
};

const uploadFile = () => {
    if (!props.routes?.upload_greeting_route) {
        errors.value = { server: ["Configuration error: Upload route is missing."] };
        return;
    }

    isFormSubmiting.value = true;
    successMessage.value = null;
    errors.value = {};

    const uploadPayload = new FormData();
    uploadPayload.append('file', fileToUpload.value);
    uploadPayload.append('_token', page.props.csrf_token);

    axios.post(props.routes.upload_greeting_route, uploadPayload, {
        headers: { 'Content-Type': 'multipart/form-data' }
    })
        .then(response => {
            isFormSubmiting.value = false;
            if (response.data.success) {
                fileToUpload.value = null;
                closeModal()
                emit('saved', String(response?.data?.greeting_id) ?? null)
                emit('success', response.data.messages)
            }
        })
        .catch(error => {
            isFormSubmiting.value = false;
            handleFormErrorResponse(error);
        });
};

const generateGreeting = () => {
    if (!props.routes?.text_to_speech_route) {
        errors.value = { server: ["Configuration error: Text-to-speech route is missing."] };
        return;
    }

    isFormSubmiting.value = true;
    errors.value = null;
    audioUrl.value = null;

    const generatePayload = {
        input: form$.value?.data?.input ?? null,
        voice: form$.value?.data?.voice ?? null,
        speed: form$.value?.data?.speed ?? null,
        _token: page.props.csrf_token
    };

    axios.post(props.routes.text_to_speech_route, generatePayload)
        .then((response) => {
            isFormSubmiting.value = false;
            if (response.data.success) {
                audioUrl.value = response.data.file_url;
                applyUrl.value = response.data.apply_url;
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
    isSaving.value = true;
    errors.value = null;

    const payload = {
        input: form$.value?.data?.input ?? null,
        file_name: newGreetingFileName.value,
        _token: page.props.csrf_token,
    };

    if (voicemail_uuid.value) {
        payload.voicemail_uuid = voicemail_uuid.value;
    }

    axios.post(applyUrl.value, payload)
        .then((response) => {
            isSaving.value = false;
            if (response.data.success) {
                audioUrl.value = null;
                closeModal()
                emit('saved', String(response?.data?.greeting_id) ?? null)
                emit('success', response.data.messages)
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
        if (error.response.data && error.response.data.errors) {
            errors.value = error.response.data.errors;
        }
        if (error.response.data && error.response.data.message) {
            errors.value = { server: [error.response.data.message] };
        }
    } else {
        console.log(error.message);
    }
}
</script>