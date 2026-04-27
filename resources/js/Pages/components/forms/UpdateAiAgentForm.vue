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
                                                description="Configure the settings for this AI agent." />

                                            <HiddenElement name="ai_agent_uuid" />

                                            <ToggleElement name="agent_enabled" label="Enabled"
                                                true-value="true" false-value="false" />

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

                            <div v-if="!loading" class="mt-6 space-y-4 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6 shadow sm:rounded-md">
                                <div>
                                    <h4 class="text-base font-semibold text-gray-900">Knowledge Base</h4>
                                    <p class="text-sm text-gray-500">Upload files, add URLs, or paste text. The agent will be able to reference these in conversations.</p>
                                </div>

                                <div v-if="kbDocsList.length > 0" class="border border-gray-200 rounded-md divide-y divide-gray-200 bg-white">
                                    <div v-for="doc in kbDocsList" :key="doc.kb_document_uuid"
                                        class="flex items-center justify-between px-3 py-2 text-sm">
                                        <div class="flex items-center space-x-3 min-w-0">
                                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                                :class="{
                                                    'bg-blue-50 text-blue-700 ring-blue-700/10': doc.document_type === 'file',
                                                    'bg-green-50 text-green-700 ring-green-700/10': doc.document_type === 'url',
                                                    'bg-amber-50 text-amber-700 ring-amber-700/10': doc.document_type === 'text',
                                                }">{{ doc.document_type }}</span>
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-900 truncate">{{ doc.name }}</div>
                                                <div v-if="doc.url" class="text-xs text-gray-500 truncate">{{ doc.url }}</div>
                                                <div v-else-if="doc.file_size" class="text-xs text-gray-500">{{ formatBytes(doc.file_size) }}</div>
                                            </div>
                                        </div>
                                        <button type="button" @click="deleteKbDoc(doc)"
                                            class="ml-3 text-rose-600 hover:text-rose-800 disabled:opacity-50"
                                            :disabled="kbBusy">
                                            <TrashIcon class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>
                                <div v-else class="text-sm text-gray-500 italic">No knowledge base documents yet.</div>

                                <div class="border-t border-gray-200 pt-4">
                                    <div class="flex space-x-2 mb-3">
                                        <button type="button" v-for="t in ['file','url','text']" :key="t"
                                            @click="kbType = t"
                                            class="px-3 py-1 text-sm rounded-md ring-1 ring-inset"
                                            :class="kbType === t ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50'">
                                            {{ t === 'file' ? 'Upload file' : t === 'url' ? 'Add URL' : 'Add text' }}
                                        </button>
                                    </div>

                                    <div class="space-y-2">
                                        <input type="text" v-model="kbName" placeholder="Display name"
                                            class="block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm" />

                                        <input v-if="kbType === 'file'" type="file" ref="kbFileInput"
                                            accept=".pdf,.txt,.docx,.html,.htm,.epub,.md"
                                            class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100" />

                                        <input v-if="kbType === 'url'" type="url" v-model="kbUrl"
                                            placeholder="https://example.com/help.html"
                                            class="block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm" />

                                        <textarea v-if="kbType === 'text'" v-model="kbText" rows="4"
                                            placeholder="Paste the text snippet the agent should know about..."
                                            class="block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm" />

                                        <div v-if="kbError" class="text-sm text-rose-600">{{ kbError }}</div>

                                        <div class="flex justify-end">
                                            <button type="button" @click="addKbDoc" :disabled="kbBusy"
                                                class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50">
                                                {{ kbBusy ? 'Adding…' : 'Add to knowledge base' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import axios from "axios";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon, TrashIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    show: Boolean,
    options: Object,
    header: {
        type: String,
        default: 'Edit AI Agent',
    },
    loading: Boolean,
});

const emit = defineEmits(['close', 'error', 'success', 'refresh-data']);

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
    ai_agent_uuid: props.options?.item?.ai_agent_uuid ?? '',
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

    return await form$.$vueform.services.axios.put(
        props.options.routes.update_route,
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
    emit('refresh-data');
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

// ----- Knowledge Base -----
const kbDocsList = ref([]);
const kbType = ref('file');
const kbName = ref('');
const kbUrl = ref('');
const kbText = ref('');
const kbFileInput = ref(null);
const kbBusy = ref(false);
const kbError = ref(null);

watch(() => props.options?.kb_documents, (docs) => {
    kbDocsList.value = Array.isArray(docs) ? [...docs] : [];
}, { immediate: true });

const formatBytes = (bytes) => {
    if (!bytes) return '';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let n = bytes;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return n.toFixed(n >= 10 || i === 0 ? 0 : 1) + ' ' + units[i];
};

const addKbDoc = async () => {
    kbError.value = null;

    if (!kbName.value.trim()) {
        kbError.value = 'Please enter a display name.';
        return;
    }

    const storeRoute = props.options?.routes?.kb_store_route;
    if (!storeRoute) {
        kbError.value = 'Knowledge base endpoint not available.';
        return;
    }

    const fd = new FormData();
    fd.append('document_type', kbType.value);
    fd.append('name', kbName.value);

    if (kbType.value === 'file') {
        const file = kbFileInput.value?.files?.[0];
        if (!file) {
            kbError.value = 'Please choose a file.';
            return;
        }
        fd.append('file', file);
    } else if (kbType.value === 'url') {
        if (!kbUrl.value.trim()) {
            kbError.value = 'Please enter a URL.';
            return;
        }
        fd.append('url', kbUrl.value);
    } else {
        if (!kbText.value.trim()) {
            kbError.value = 'Please enter some text.';
            return;
        }
        fd.append('text', kbText.value);
    }

    kbBusy.value = true;
    try {
        const res = await axios.post(storeRoute, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        kbDocsList.value.unshift(res.data.kb_document);
        kbName.value = '';
        kbUrl.value = '';
        kbText.value = '';
        if (kbFileInput.value) kbFileInput.value.value = '';
        emit('success', 'success', res.data.messages);
    } catch (err) {
        const msg = err.response?.data?.errors?.server?.[0]
            || err.response?.data?.message
            || err.message
            || 'Failed to add knowledge base document.';
        kbError.value = msg;
    } finally {
        kbBusy.value = false;
    }
};

const deleteKbDoc = async (doc) => {
    if (!confirm('Remove "' + doc.name + '" from the knowledge base?')) return;
    kbBusy.value = true;
    try {
        await axios.delete(doc.delete_route);
        kbDocsList.value = kbDocsList.value.filter(d => d.kb_document_uuid !== doc.kb_document_uuid);
        emit('success', 'success', { success: ['Knowledge base document removed.'] });
    } catch (err) {
        emit('error', err);
    } finally {
        kbBusy.value = false;
    }
};
</script>
