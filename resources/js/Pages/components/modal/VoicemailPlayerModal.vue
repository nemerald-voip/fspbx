<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="emit('close')">
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

                            <!-- Close Button -->
                            <div class="absolute right-0 top-0 pr-4 pt-4">
                                <button type="button" class="rounded-md bg-white text-gray-400 hover:text-gray-500"
                                    @click="emit('close')">
                                    <XMarkIcon class="h-6 w-6" />
                                </button>
                            </div>

                            <!-- Header -->
                            <div class="mb-6">
                                <h1 class="text-2xl font-bold text-gray-900">Voicemail Message</h1>
                                <p class="text-sm text-gray-500">{{ item?.start_date }}</p>

                                <dl class="mt-4 grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2 text-sm">
                                    <div class="flex gap-2">
                                        <dt class="font-medium text-gray-500">From:</dt>
                                        <dd class="text-gray-900 font-semibold">{{ item?.caller_id_name }} ({{ item?.caller_id_number_formatted }})</dd>
                                    </div>
                                    <div class="flex gap-2">
                                        <dt class="font-medium text-gray-500">To:</dt>
                                        <dd class="text-gray-900">Extension {{ item?.caller_destination_formatted }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Player Area -->
                            <div v-if="loading" class="py-12 flex justify-center items-center gap-3 text-blue-600">
                                <Spinner :show="true" class="h-8 w-8" />
                                <span>Loading audio...</span>
                            </div>

                            <div v-else class="space-y-6">
                                <AudioPlayer 
                                    v-if="audioUrl" 
                                    :url="audioUrl"
                                    :download-url="downloadUrl"
                                    :file-name="fileName" 
                                />

                                <!-- Transcription Block -->
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Transcription</h3>
                                    <p class="text-gray-700 leading-relaxed italic">
                                        {{ item?.transcription || 'No transcription available for this message.' }}
                                    </p>
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
import { ref, watch } from 'vue'
import { Dialog, DialogPanel, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/outline";
import AudioPlayer from "@generalComponents/AudioPlayer.vue"
import Spinner from "@generalComponents/Spinner.vue";
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    messageUuid: String,
    routes: Object
})

const emit = defineEmits(['close', 'error'])

const loading = ref(true)
const item = ref(null)
const audioUrl = ref('')
const downloadUrl = ref('')
const fileName = ref('')

const fetchMessageDetails = () => {
    if (!props.messageUuid) return
    loading.value = true
    
    axios.get(props.routes.recording_route, { params: { item_uuid: props.messageUuid } })
        .then((response) => {
            item.value = response.data.item
            audioUrl.value = response.data.audio_url
            downloadUrl.value = response.data.download_url
            fileName.value = response.data.filename
        })
        .catch((error) => {
            emit('error', error)
            emit('close')
        })
        .finally(() => {
            loading.value = false
        })
}

watch(() => props.show, (newVal) => {
    if (newVal) fetchMessageDetails()
})
</script>