<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10">
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
                            class="relative transform  rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl sm:p-6">

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full h-full">
                                <div class="flex justify-center items-center space-x-3">
                                    <div>
                                        <svg class="animate-spin  h-10 w-10 text-blue-600"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4">
                                            </circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                    </div>
                                    <div class="text-lg text-blue-600 m-auto">Loading...</div>
                                </div>
                            </div>

                            <div v-if="!loading" class="flex-col space-y-5">
                                <div>
                                    <div class="space-y-1">
                                        <!-- Title -->
                                        <h1 class="text-2xl font-bold text-gray-900">
                                            {{ capitalizeFirstLetter(options.item?.direction) }} Call
                                        </h1>

                                        <!-- When -->
                                        <p class="text-sm text-gray-500">
                                            On {{ options.item?.start_date }} at {{ options.item?.start_time }}
                                        </p>

                                        <!-- Parties -->
                                        <dl class="text-sm text-gray-700">
                                            <div class="flex gap-2">
                                                <dt class="font-medium text-gray-500 w-12">From:</dt>
                                                <dd class="flex-1">
                                                    <span v-if="options?.item?.direction === 'outbound'">
                                                        <!-- extension name if present, else caller name -->
                                                        {{ options.item?.extension?.name_formatted ||
                                                            options.item?.caller_id_name }}
                                                        <span v-if="options.item?.caller_id_number_formatted"
                                                            class="text-gray-500">
                                                            - {{ options.item?.caller_id_number_formatted }}
                                                        </span>
                                                    </span>
                                                    <span v-else>
                                                        {{ options.item?.caller_id_name }}
                                                        <span v-if="options.item?.caller_id_number_formatted"
                                                            class="text-gray-500">
                                                            - {{ options.item?.caller_id_number_formatted }}
                                                        </span>
                                                    </span>
                                                </dd>
                                            </div>

                                            <div class="flex gap-2">
                                                <dt class="font-medium text-gray-500 w-12">To:</dt>
                                                <dd class="flex-1">
                                                    <span v-if="options?.item?.direction === 'outbound'">
                                                        {{ options.item?.caller_destination_formatted }}
                                                    </span>
                                                    <span v-else>
                                                        <!-- inbound destination is usually the extension (callee) -->
                                                        {{ options.item?.extension?.name_formatted ||
                                                            options.item?.caller_destination_formatted }}
                                                    </span>
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>


                                <AudioPlayer v-if="!loading" :url="props.options?.audio_url"
                                    :download-url="props.options?.download_url" :file-name="props.options?.filename" />

                                <Vueform>
                                    <template #empty>
                                        <FormTabs>
                                            <FormTab name="page0" label="Transcript" :elements="[
                                                'transcribeButton',
                                                'transcript'
                                            ]" />
                                            <FormTab name="page1" label="Recap" :elements="[]" />
                                        </FormTabs>

                                        <FormElements>
                                            <ButtonElement name="transcribeButton" button-label="Transcribe"
                                                :loading="isRequestingTranscription" @click="requestTranscription"
                                                :secondary="true" />

                                            <StaticElement name=transcript>
                                                <div class="inline-flex items-center gap-2 rounded-full px-3 py-1 ring-1"
                                                    :class="{
                                                        'bg-yellow-50 text-yellow-800 ring-yellow-200': status === 'pending' || status === 'queued',
                                                        'bg-sky-50 text-sky-800 ring-sky-200': status === 'processing',
                                                        'bg-emerald-50 text-emerald-800 ring-emerald-200': status === 'completed',
                                                        'bg-rose-50 text-rose-800 ring-rose-200': status === 'failed'
                                                    }">
                                                    <span class="h-2 w-2 rounded-full" :class="{
                                                        'bg-yellow-500': status === 'pending' || status === 'queued',
                                                        'bg-sky-500': status === 'processing',
                                                        'bg-emerald-500': status === 'completed',
                                                        'bg-rose-500': status === 'failed'
                                                    }"></span>
                                                    <span class="font-medium capitalize">{{ status }}</span>
                                                </div>


                                                <!-- TRANSCRIPT -->
                                                <div v-if="hasTranscript" class="mt-4">
                                                    <h2 class="text-sm font-semibold text-gray-500 mb-2">Conversation
                                                    </h2>

                                                    <div class="space-y-6">
                                                        <div v-for="(g, i) in grouped" :key="i"
                                                            class="flex items-start gap-4">

                                                            <!-- Avatar / Icon -->
                                                            <div class="shrink-0 h-8 w-8 grid place-items-center rounded-full"
                                                                :class="getSpeakerAvatarClasses(g.speaker)">

                                                                <!-- The initial is the speaker label itself, e.g., 'A' -->
                                                                <span class="text-sm font-semibold">
                                                                    {{ g.speaker }}
                                                                </span>
                                                            </div>

                                                            <!-- Speaker and Text Content -->
                                                            <div class="flex-1">
                                                                <div class="flex items-baseline gap-2">
                                                                    <!-- The label is "Speaker " + the speaker label, e.g., "Speaker A" -->
                                                                    <p class="font-bold text-gray-900">
                                                                        Speaker {{ g.speaker }}
                                                                    </p>
                                                                    <!-- Timestamp -->
                                                                    <span class="text-xs text-gray-400">
                                                                        {{ msToClock(g.start) }}
                                                                    </span>
                                                                </div>
                                                                <!-- The transcribed text -->
                                                                <p class="mt-1 text-gray-700 leading-relaxed">
                                                                    {{g.chunks.map(c => c.text).join(' ')}}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </StaticElement>

                                        </FormElements>
                                    </template>
                                </Vueform>


                            </div>

                            <!-- <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                                @response="handleResponse" :display-errors="false" :default="{
                                    // user_uuid: options.item.user_uuid,
                                }">
                                
                            </Vueform> -->
                        </DialogPanel>


                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'

import { Dialog, DialogPanel, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/solid";
import AudioPlayer from "@generalComponents/AudioPlayer.vue"


const emit = defineEmits(['close', 'confirm', 'success', 'error', 'refresh-data'])

const props = defineProps({
    options: Object,
    show: Boolean,
    loading: Boolean,
});

const status = computed(() =>
    props.options?.transcription?.status ?? null
)
const utterances = computed(() => props.options?.transcription?.utterances ?? [])
const hasTranscript = computed(() => status.value === 'completed' && utterances.value.length > 0)


const isRequestingTranscription = ref(null)

const requestTranscription = async () => {
    isRequestingTranscription.value = true
    try {
        const { data } = await axios.post(
            props.options.routes.transcribe_route,
            {
                uuid: props.options?.item?.xml_cdr_uuid ?? null,
                domain_uuid: props.options?.item?.domain_uuid ?? null,
                // options: overrides,                      // optional provider overrides
            },
        )
        // policy.value = data
        console.log(data);
        emit('success', 'success', data.messages)
        return data
    } catch (err) {
        console.log(err);
        emit('error', err);
        return []
    } finally {
        isRequestingTranscription.value = false
    }
}


function capitalizeFirstLetter(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}

function msToClock(ms) {
    const s = Math.max(0, Math.round(ms / 1000))
    const m = Math.floor(s / 60), r = s % 60
    return `${m}:${String(r).padStart(2, '0')}`
}


const getSpeakerAvatarClasses = (speakerLabel) => {
    const colorMap = {
        A: 'bg-indigo-100 text-indigo-800',
        B: 'bg-emerald-100 text-emerald-800',
        C: 'bg-amber-100 text-amber-800',
        D: 'bg-fuchsia-100 text-fuchsia-800',
        // Add more speakers as needed
    };
    // Return the specific color or a default slate color if the speaker is not in the map
    return colorMap[speakerLabel] || 'bg-slate-100 text-slate-800';
};

// Group consecutive lines by speaker (cleaner bubbles)
const grouped = computed(() => {
    const out = []
    let cur = null
    for (const u of utterances.value) {
        if (!cur || cur.speaker !== u.speaker) {
            cur = { speaker: u.speaker, start: u.start, end: u.end, chunks: [u] }
            out.push(cur)
        } else {
            cur.chunks.push(u)
            cur.end = u.end
        }
    }
    return out
})

</script>
<style>
div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}
</style>