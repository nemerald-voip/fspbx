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
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-3xl sm:p-6">
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
                                    <div class="space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6 shadow sm:rounded-md">
                                        <FormElements>
                                            <StaticElement name="settings_title" tag="h4" content="AI Agent Settings"
                                                description="Configure the main settings for this AI agent." />

                                            <HiddenElement name="agent_enabled" :meta="true" default="true" />

                                            <TextElement name="agent_name" label="Name"
                                                placeholder="e.g. Customer Support Agent"
                                                :columns="{ sm: { container: 6 } }" />

                                            <TextElement name="agent_extension" label="Extension"
                                                :columns="{ sm: { container: 6 } }" />

                                            <TextElement name="description" label="Description"
                                                placeholder="Enter description" :floating="false" />

                                            <TextareaElement name="system_prompt" label="System Prompt"
                                                placeholder="You are a helpful customer support agent for our company..."
                                                :rows="4"
                                                description="Instructions that define the AI agent's behavior and personality." />

                                            <TextareaElement name="first_message" label="First Message"
                                                placeholder="Hello! How can I help you today?"
                                                :rows="2"
                                                description="The first message the AI agent will speak when a call connects." />

                                            <SelectElement name="voice_id" :items="voiceOptions"
                                                :search="true" :native="false" label="Voice"
                                                input-type="search" autocomplete="off"
                                                placeholder="Choose a voice"
                                                :columns="{ sm: { container: 6 } }" />

                                            <SelectElement name="language" :items="languageOptions"
                                                :search="true" :native="false" label="Language"
                                                input-type="search" autocomplete="off"
                                                placeholder="Choose a language"
                                                :columns="{ sm: { container: 6 } }" />

                                            <ButtonElement name="submit" button-label="Save"
                                                :submits="true" align="right" />
                                        </FormElements>
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
import { ref, computed } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    show: Boolean,
    options: Object,
    header: {
        type: String,
        default: 'New AI Agent',
    },
    loading: Boolean,
});

const emit = defineEmits(['close', 'error', 'success', 'created']);

const form$ = ref(null);

const voiceOptions = computed(() => {
    return (props.options?.voices ?? []).reduce((acc, voice) => {
        acc[voice.value] = voice.label;
        return acc;
    }, {});
});

const languageOptions = computed(() => {
    return (props.options?.languages ?? []).reduce((acc, lang) => {
        acc[lang.value] = lang.label;
        return acc;
    }, {});
});

const defaultFormData = computed(() => ({
    agent_name: props.options?.item?.agent_name ?? '',
    agent_extension: props.options?.item?.agent_extension ?? '',
    description: props.options?.item?.description ?? '',
    agent_enabled: props.options?.item?.agent_enabled ?? 'true',
    system_prompt: props.options?.item?.system_prompt ?? '',
    first_message: props.options?.item?.first_message ?? '',
    voice_id: props.options?.item?.voice_id ?? null,
    language: props.options?.item?.language ?? 'en',
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
            form$.messageBag.append('Couldn\'t submit form');
            break;
    }
};

const handleClose = () => {
    emit('close');
};
</script>
