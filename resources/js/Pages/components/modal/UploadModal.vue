<template>
    <TransitionRoot as="template" :show="show">
        <Dialog as="div" class="relative z-10">
            <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
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
                            class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <DialogTitle as="h3" class="pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <div class="mt-4 pb-4">
                                <DragDropUpload @file-selected="onFileSelected" />
                            </div>

                            <div v-if="localErrors && Object.keys(localErrors).length > 0"
                                class="rounded-md bg-red-50 p-4">
                                <div class="flex">
                                    <div class="shrink-0">
                                        <XCircleIcon class="size-5 text-red-400" aria-hidden="true" />
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-red-800">There were errors with your
                                            submission</h3>
                                        <div class="mt-2 text-sm text-red-700">
                                            <ul role="list" class="list-disc space-y-1 pl-5">
                                                <li v-for="(error, index) in localErrors" :key="index">
                                                    <span>{{ error[0] }}</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">

                                <button type="button"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto"
                                    @click="emit('close')" ref="cancelButtonRef">Cancel</button>

                                <button type="button" :disabled="isSubmitting"
                                    class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 sm:ml-3 sm:w-auto focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                                    @click="uploadFile()">
                                    Upload
                                    <Spinner class="ml-2" :color="'text-gray-700'" :show="isSubmitting" />
                                </button>
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
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import Spinner from "@generalComponents/Spinner.vue";
import DragDropUpload from "@generalComponents/DragDropUpload.vue";
import { XMarkIcon } from "@heroicons/vue/24/solid";
import { XCircleIcon } from '@heroicons/vue/20/solid'


const props = defineProps({
    show: Boolean,
    header: String,
    errors: [Object, null],
    isSubmitting: {
        type: Boolean,
        default: false, // Default value for loading
    },
});

const emit = defineEmits(['close', 'upload'])

// Store the selected file from the DragDropUpload component
const selectedFile = ref(null)

const localErrors = ref(props.errors);

// Watch for changes in the errors prop and update localErrors accordingly
watch(
  () => props.errors,
  (newErrors) => {
    localErrors.value = newErrors;
  }
);

function onFileSelected(file) {
    localErrors.value = null;
    selectedFile.value = file
}

function uploadFile() {
    emit('upload', selectedFile.value);
}
</script>
