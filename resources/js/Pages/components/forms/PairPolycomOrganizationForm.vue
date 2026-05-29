<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-20">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-20 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                        <DialogPanel
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                Connect to existing ZTP Organization
                            </DialogTitle>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full h-full py-6">
                                <div class="flex items-center justify-center space-x-3">
                                    <svg class="h-10 w-10 animate-spin text-blue-600"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <div class="m-auto text-lg text-blue-600">Loading...</div>
                                </div>
                            </div>

                            <Vueform v-else ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false" :default="{
                                    provider: selectedProvider,
                                }">
                                <StaticElement name="intro">
                                    <p class="text-sm text-gray-500">
                                        Select the organization you want to connect to from the dropdown below.
                                    </p>
                                </StaticElement>

                                <HiddenElement name="provider" :meta="true" />

                                <SelectElement name="org_id" label="Organization" :items="organizationOptions"
                                    label-prop="name" value-prop="value" :search="true" :native="false"
                                    input-type="search" autocomplete="off" placeholder="Select an organization"
                                    :rules="['required']" />

                                <GroupElement name="buttons" />

                                <ButtonElement name="cancel" button-label="Cancel" :secondary="true"
                                    @click="emit('close')" :columns="{ container: 6 }" />

                                <ButtonElement name="submit" button-label="Submit" :submits="true" align="right"
                                    :columns="{ container: 6 }" />
                            </Vueform>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed, ref } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    orgs: [Array, Object, null],
    selectedProvider: [String, null],
    show: Boolean,
    loading: Boolean,
    route: [String, null],
});

const emit = defineEmits(['close', 'error', 'success', 'refresh-data']);

const form$ = ref(null)

const organizationOptions = computed(() => {
    if (Array.isArray(props.orgs)) {
        return props.orgs
    }

    return Object.values(props.orgs ?? {})
})

const submitForm = async (FormData, form$) => {
    return await form$.$vueform.services.axios.post(props.route, form$.requestData)
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear()

    if (el$.children$) {
        Object.values(el$.children$).forEach(childEl$ => {
            clearErrorsRecursive(childEl$)
        })
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach(el$ => {
        clearErrorsRecursive(el$)
    })

    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0])
            }
        })
    }
}

const handleSuccess = (response) => {
    emit('success', response.data.messages);
    emit('close');
    emit('refresh-data', props.selectedProvider);
}

const handleError = (error, details, form$) => {
    form$.messageBag.clear()

    switch (details.type) {
        case 'prepare':
            form$.messageBag.append('Could not prepare form')
            break
        case 'submit':
            emit('error', error);
            break
        case 'cancel':
            form$.messageBag.append('Request cancelled')
            break
        case 'other':
            form$.messageBag.append('Couldn\'t submit form')
            break
    }
}
</script>
