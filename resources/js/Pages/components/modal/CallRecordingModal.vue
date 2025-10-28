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
                                            ]" />
                                            <FormTab name="page1" label="Recap" :elements="[]" />
                                        </FormTabs>

                                        <FormElements>
                                            <ButtonElement name="transcribeButton" button-label="Transcribe" :loading="isRequestingTranscription" @click="requestTranscription"
                                                :secondary="true" />
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
import { ref } from 'vue'

import { Dialog, DialogPanel, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/solid";
import AudioPlayer from "@generalComponents/AudioPlayer.vue"


const emit = defineEmits(['close', 'confirm', 'success', 'error', 'refresh-data'])

const props = defineProps({
    options: Object,
    show: Boolean,
    loading: Boolean,
});


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

</script>
<style>
div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}
</style>