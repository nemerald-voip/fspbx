<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="handleClose">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                        <DialogPanel
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="handleClose">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full h-full">
                                <div class="flex justify-center items-center space-x-3">
                                    <div>
                                        <svg class="animate-spin h-10 w-10 text-blue-600"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                        </svg>
                                    </div>
                                    <div class="text-lg text-blue-600 m-auto">Loading...</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                :default="defaultFormData" :float-placeholders="false">

                                <template #empty>
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="settings" label="Settings" :elements="[
                                                    'settings_title',
                                                    'ivr_menu_enabled',
                                                    'ivr_menu_name',
                                                    'ivr_menu_extension',
                                                    'ivr_menu_description',
                                                    'container_settings',
                                                    'submit_settings',
                                                ]" />

                                                <FormTab name="advanced" label="Advanced" :elements="[
                                                    'advanced_title',
                                                    'caller_id_prefix',
                                                    'digit_length',
                                                    'prompt_timeout',
                                                    'pin',
                                                    'ring_back_tone',
                                                    'ring_back_tone_actions',
                                                    'invalid_input_message',
                                                    'invalid_input_actions',
                                                    'exit_message',
                                                    'exit_message_actions',
                                                    'direct_dial',
                                                    'container_advanced',
                                                    'submit_advanced',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <StaticElement name="settings_title" tag="h4" content="Settings"
                                                    description="Configure the main settings for this virtual receptionist." />

                                                <HiddenElement name="ivr_menu_enabled" :meta="true" default="true" />

                                                <TextElement name="ivr_menu_name" label="Name"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="ivr_menu_extension" label="Extension"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="ivr_menu_description" label="Description"
                                                    placeholder="Enter description" :floating="false" />

                                                <GroupElement name="container_settings" />
                                                <ButtonElement name="submit_settings" button-label="Save"
                                                    :submits="true" align="right" />

                                                <StaticElement name="advanced_title" tag="h4" content="Advanced"
                                                    description="Set advanced settings for this virtual receptionist." />

                                                <TextElement name="caller_id_prefix" label="Caller ID Name Prefix"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="digit_length" label="Digit Length"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="prompt_timeout" label="Input Timeout (ms)"
                                                    placeholder="3000" :columns="{ sm: { container: 6 } }"
                                                    description="How long to wait for caller input before counting it as no input." />

                                                <TextElement name="pin" label="PIN Number"
                                                    :columns="{ sm: { container: 6 } }"
                                                    description="Use a PIN to protect this menu from unauthorized access." />

                                                <SelectElement name="ring_back_tone" :items="options.ring_back_tones"
                                                    :search="true" :native="false" label="Ring Back Tone" :groups="true"
                                                    input-type="search" autocomplete="off"
                                                    placeholder="Choose an option" :strict="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="ring_back_tone_actions" :columns="{ container: 6 }">
                                                    <ButtonElement v-if="!isRingBackTonePlaying"
                                                        @click="playRingBackTone" name="play_ringback_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :conditions="[hasPlayableRingBackTone]"
                                                        :remove-classes="buttonClassRemovals">
                                                        <PlayCircleIcon
                                                            class="h-8 w-8 shrink-0 py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 cursor-pointer" />
                                                    </ButtonElement>

                                                    <ButtonElement v-if="isRingBackTonePlaying"
                                                        @click="pauseRingBackTone" name="pause_ringback_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :remove-classes="buttonClassRemovals">
                                                        <PauseCircleIcon
                                                            class="h-8 w-8 shrink-0 py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 cursor-pointer" />
                                                    </ButtonElement>
                                                </GroupElement>

                                                <SelectElement name="invalid_input_message" :items="options.sounds"
                                                    :groups="true" :search="true" :native="false"
                                                    label="Invalid Input Message" input-type="search" autocomplete="off"
                                                    placeholder="Choose an option" :strict="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="invalid_input_actions" :columns="{ container: 6 }">
                                                    <ButtonElement v-if="!isInvalidInputMessageAudioPlaying"
                                                        @click="playInvalidInputMessage" name="play_invalid_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :conditions="[hasPlayableInvalidInputMessage]"
                                                        :remove-classes="buttonClassRemovals">
                                                        <PlayCircleIcon
                                                            class="h-8 w-8 shrink-0 py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 cursor-pointer" />
                                                    </ButtonElement>

                                                    <ButtonElement v-if="isInvalidInputMessageAudioPlaying"
                                                        @click="pauseInvalidInputMessage" name="pause_invalid_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :remove-classes="buttonClassRemovals">
                                                        <PauseCircleIcon
                                                            class="h-8 w-8 shrink-0 py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 cursor-pointer" />
                                                    </ButtonElement>
                                                </GroupElement>

                                                <SelectElement name="exit_message" :items="options.sounds"
                                                    :groups="true" :search="true" :native="false" label="Exit Message"
                                                    input-type="search" autocomplete="off"
                                                    placeholder="Choose an option" :strict="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="exit_message_actions" :columns="{ container: 6 }">
                                                    <ButtonElement v-if="!isExitMessageAudioPlaying"
                                                        @click="playExitMessage" name="play_exit_button" label="&nbsp;"
                                                        :secondary="true" :columns="{ container: 2 }"
                                                        :conditions="[hasPlayableExitMessage]"
                                                        :remove-classes="buttonClassRemovals">
                                                        <PlayCircleIcon
                                                            class="h-8 w-8 shrink-0 py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 cursor-pointer" />
                                                    </ButtonElement>

                                                    <ButtonElement v-if="isExitMessageAudioPlaying"
                                                        @click="pauseExitMessage" name="pause_exit_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :remove-classes="buttonClassRemovals">
                                                        <PauseCircleIcon
                                                            class="h-8 w-8 shrink-0 py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 cursor-pointer" />
                                                    </ButtonElement>
                                                </GroupElement>

                                                <ToggleElement name="direct_dial" text="Enable Direct Dialing"
                                                    true-value="true" false-value="false"
                                                    description="Allows callers to dial extensions directly." />

                                                <GroupElement name="container_advanced" />
                                                <ButtonElement name="submit_advanced" button-label="Save"
                                                    :submits="true" align="right" />
                                            </FormElements>
                                        </div>
                                    </div>
                                </template>
                            </Vueform>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref, computed, onBeforeUnmount } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import {
    XMarkIcon,
    PlayCircleIcon,
    PauseCircleIcon,
} from "@heroicons/vue/24/solid";

const props = defineProps({
    show: Boolean,
    options: Object,
    header: {
        type: String,
        default: 'New Virtual Receptionist',
    },
    loading: Boolean,
});

const emit = defineEmits(['close', 'error', 'success', 'created']);

const form$ = ref(null);

const currentInvalidInputMessageAudio = ref(null);
const currentInvalidInputMessageFile = ref(null);
const isInvalidInputMessageAudioPlaying = ref(false);

const currentExitMessageAudio = ref(null);
const currentExitMessageFile = ref(null);
const isExitMessageAudioPlaying = ref(false);

const currentRingBackToneAudio = ref(null);
const currentRingBackToneFile = ref(null);
const isRingBackTonePlaying = ref(false);

const buttonClassRemovals = {
    ButtonElement: {
        button_secondary: ['form-bg-btn-secondary'],
        button: ['form-border-width-btn'],
        button_enabled: ['focus:form-ring'],
        button_md: ['form-p-btn'],
    },
};

const defaultFormData = computed(() => ({
    ivr_menu_name: props.options?.item?.ivr_menu_name ?? '',
    ivr_menu_extension: props.options?.item?.ivr_menu_extension ?? '',
    ivr_menu_description: props.options?.item?.ivr_menu_description ?? '',
    ivr_menu_enabled: props.options?.item?.ivr_menu_enabled ?? 'true',
    prompt_timeout: props.options?.item?.ivr_menu_timeout ?? '3000',
    direct_dial: props.options?.item?.ivr_menu_direct_dial ?? 'false',
    caller_id_prefix: props.options?.item?.ivr_menu_cid_prefix ?? '',
    pin: props.options?.item?.ivr_menu_pin_number ?? '',
    digit_length: props.options?.item?.ivr_menu_digit_len ?? '5',
    ring_back_tone: props.options?.item?.ivr_menu_ringback ?? '${us-ring}',
    invalid_input_message: props.options?.item?.ivr_menu_invalid_sound ?? 'ivr/ivr-that_was_an_invalid_entry.wav',
    exit_message: props.options?.item?.ivr_menu_exit_sound ?? 'silence_stream://100',
}));

const submitForm = async (FormData, form$) => {
    const requestData = form$.requestData;

    return await form$.$vueform.services.axios.post(
        props.options.routes.store_route,
        requestData
    );
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear();

    if (el$.children$) {
        Object.values(el$.children$).forEach(childEl$ => {
            clearErrorsRecursive(childEl$);
        });
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach(el$ => {
        clearErrorsRecursive(el$);
    });

    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0]);
            }
        });
    }
};

const handleSuccess = (response) => {
    emit('success', 'success', response.data.messages);
    emit('created', response.data.item_uuid);
};

const handleError = (error, details, form$) => {
    form$.messageBag.clear();

    switch (details.type) {
        case 'prepare':
            form$.messageBag.append('Could not prepare form');
            break;
        case 'submit':
            emit('error', error);
            break;
        case 'cancel':
            form$.messageBag.append('Request cancelled');
            break;
        case 'other':
            form$.messageBag.append('Couldn’t submit form');
            break;
    }
};

const handleClose = () => {
    stopInvalidInputMessageAudio();
    stopExitMessageAudio();
    stopRingBackToneAudio();
    emit('close');
};

const getSoundFileUrl = async (fileName) => {
    if (!fileName) return null;
    const response = await axios.post(props.options.routes.ivr_message_route, { file_name: fileName });
    return response.data?.success ? response.data.file_url : null;
};

const stopInvalidInputMessageAudio = () => {
    if (currentInvalidInputMessageAudio.value) {
        currentInvalidInputMessageAudio.value.pause();
        currentInvalidInputMessageAudio.value.currentTime = 0;
        currentInvalidInputMessageAudio.value = null;
    }

    currentInvalidInputMessageFile.value = null;
    isInvalidInputMessageAudioPlaying.value = false;
};

const stopExitMessageAudio = () => {
    if (currentExitMessageAudio.value) {
        currentExitMessageAudio.value.pause();
        currentExitMessageAudio.value.currentTime = 0;
        currentExitMessageAudio.value = null;
    }

    currentExitMessageFile.value = null;
    isExitMessageAudioPlaying.value = false;
};

const stopRingBackToneAudio = () => {
    if (currentRingBackToneAudio.value) {
        currentRingBackToneAudio.value.pause();
        currentRingBackToneAudio.value.currentTime = 0;
        currentRingBackToneAudio.value = null;
    }

    currentRingBackToneFile.value = null;
    isRingBackTonePlaying.value = false;
};

const hasPlayableInvalidInputMessage = (form$) => !!form$?.el$('invalid_input_message')?.value;

const hasPlayableExitMessage = (form$) => !!form$?.el$('exit_message')?.value;

const hasPlayableRingBackTone = (form$) => {
    const val = form$?.el$('ring_back_tone')?.value ?? '';
    return !!val && (val.endsWith('.wav') || val.endsWith('.mp3'));
};

const playInvalidInputMessage = async () => {
    stopExitMessageAudio();
    stopRingBackToneAudio();

    try {
        const fileName = form$.value?.data?.invalid_input_message;
        if (!fileName) return;

        if (
            currentInvalidInputMessageAudio.value &&
            currentInvalidInputMessageFile.value === fileName
        ) {
            if (currentInvalidInputMessageAudio.value.paused) {
                currentInvalidInputMessageAudio.value.play();
                isInvalidInputMessageAudioPlaying.value = true;
            }
            return;
        }

        stopInvalidInputMessageAudio();

        const fileUrl = await getSoundFileUrl(fileName);
        if (!fileUrl) return;

        currentInvalidInputMessageAudio.value = new Audio(fileUrl);
        currentInvalidInputMessageFile.value = fileName;
        isInvalidInputMessageAudioPlaying.value = true;

        currentInvalidInputMessageAudio.value.play().catch(() => {
            isInvalidInputMessageAudioPlaying.value = false;
            emit('error', { message: 'Audio playback failed' });
        });

        currentInvalidInputMessageAudio.value.addEventListener('ended', () => {
            isInvalidInputMessageAudioPlaying.value = false;
        });
    } catch (error) {
        emit('error', error);
    }
};

const pauseInvalidInputMessage = () => {
    if (currentInvalidInputMessageAudio.value) {
        currentInvalidInputMessageAudio.value.pause();
        isInvalidInputMessageAudioPlaying.value = false;
    }
};

const playExitMessage = async () => {
    stopInvalidInputMessageAudio();
    stopRingBackToneAudio();

    try {
        const fileName = form$.value?.data?.exit_message;
        if (!fileName) return;

        if (
            currentExitMessageAudio.value &&
            currentExitMessageFile.value === fileName
        ) {
            if (currentExitMessageAudio.value.paused) {
                currentExitMessageAudio.value.play();
                isExitMessageAudioPlaying.value = true;
            }
            return;
        }

        stopExitMessageAudio();

        const fileUrl = await getSoundFileUrl(fileName);
        if (!fileUrl) return;

        currentExitMessageAudio.value = new Audio(fileUrl);
        currentExitMessageFile.value = fileName;
        isExitMessageAudioPlaying.value = true;

        currentExitMessageAudio.value.play().catch(() => {
            isExitMessageAudioPlaying.value = false;
            emit('error', { message: 'Audio playback failed' });
        });

        currentExitMessageAudio.value.addEventListener('ended', () => {
            isExitMessageAudioPlaying.value = false;
        });
    } catch (error) {
        emit('error', error);
    }
};

const pauseExitMessage = () => {
    if (currentExitMessageAudio.value) {
        currentExitMessageAudio.value.pause();
        isExitMessageAudioPlaying.value = false;
    }
};

const playRingBackTone = async () => {
    stopInvalidInputMessageAudio();
    stopExitMessageAudio();

    try {
        const filePath = form$.value?.data?.ring_back_tone;
        if (!filePath) return;

        const fileName = filePath.substring(filePath.lastIndexOf('/') + 1);

        if (
            currentRingBackToneAudio.value &&
            currentRingBackToneFile.value === fileName
        ) {
            if (currentRingBackToneAudio.value.paused) {
                currentRingBackToneAudio.value.play();
                isRingBackTonePlaying.value = true;
            }
            return;
        }

        stopRingBackToneAudio();

        const response = await axios.post(props.options.routes.greeting_route, { file_name: fileName });
        if (!response.data?.success) return;

        currentRingBackToneAudio.value = new Audio(response.data.file_url);
        currentRingBackToneFile.value = fileName;
        isRingBackTonePlaying.value = true;

        currentRingBackToneAudio.value.play().catch(() => {
            isRingBackTonePlaying.value = false;
            emit('error', { message: 'Audio playback failed' });
        });

        currentRingBackToneAudio.value.addEventListener('ended', () => {
            isRingBackTonePlaying.value = false;
        });
    } catch (error) {
        emit('error', error);
    }
};

const pauseRingBackTone = () => {
    if (currentRingBackToneAudio.value) {
        currentRingBackToneAudio.value.pause();
        isRingBackTonePlaying.value = false;
    }
};

onBeforeUnmount(() => {
    stopInvalidInputMessageAudio();
    stopExitMessageAudio();
    stopRingBackToneAudio();
});
</script>

<style>
div[data-lastpass-icon-root] {
    display: none !important;
}

div[data-lastpass-root] {
    display: none !important;
}
</style>