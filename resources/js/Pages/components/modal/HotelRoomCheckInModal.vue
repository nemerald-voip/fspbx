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
                            class="relative transform  rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-xl sm:p-6">

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">{{ $t('Close') }}</span>
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
                                    <div class="text-lg text-blue-600 m-auto">{{ $t('Loading...') }}</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false" :default="{
                                    uuid: options?.item?.uuid ?? null,
                                    occupancy_status: options?.item?.occupancy_status ?? 'Checked in',
                                    room_name: options?.item?.room_name ?? null,
                                    housekeeping_status: options?.item?.housekeeping_status ?? null,
                                    guest_first_name: options?.item?.guest_first_name ?? null,
                                    guest_last_name: options?.item?.guest_last_name ?? null,
                                    arrival_date: options?.item?.arrival_date ?? null,
                                    departure_date: options?.item?.departure_date ?? null,
                                }" @mounted="(form) => form.disableValidation()" @submit="clearServerFormErrors">
                                <StaticElement name="title" tag="h4" :content="$t('Guest Check In Form')"
                                    :description="$t('Please fill out the following information to complete the guest details.')" />
                                <HiddenElement name="uuid" :meta="true" />
                                <HiddenElement name="occupancy_status" :meta="true" />

                                <TextElement name="room_name" :label="$t('Room')" :floating="false" :disabled="true"/>

                                <SelectElement name="housekeeping_status" :items="options.housekeeping_options" :search="true" :native="false" :label="$t('Room Status')" input-type="search" autocomplete="off"
                                    :placeholder="$t('Select room status')" :floating="false"
                                    :description="$t('Select the current status of the room.')" />

                                <TextElement name="guest_first_name" :label="$t('First Name')"
                                    :description="$t('Enter your first name.')" :placeholder="$t('Enter guest\'s first name')"
                                    :floating="false" />

                                <TextElement name="guest_last_name" :label="$t('Last Name')"
                                    :description="$t('Enter your last name.')" :placeholder="$t('Enter guest\'s last name')"
                                    :floating="false" />

                                <DateElement name="arrival_date" :label="$t('Arrival Date')" :time="true"
                                    :description="$t('Select arrival date and time.')"  />

                                <DateElement name="departure_date" :label="$t('Expected Departure Date')" :time="true"
                                    :description="$t('Select expected departure date and time.')" />

                                <GroupElement name="container_3" />
                                <ButtonElement name="reset" :button-label="$t('Cancel')" :secondary="true" :resets="true"
                                    @click="emit('close')" :columns="{
                                        container: 6,
                                    }" />
                                <ButtonElement name="submit" :button-label="$t('Save')" :submits="true" align="right" :columns="{
                                    container: 6,
                                }" />
                            </Vueform>
                        </DialogPanel>


                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { clearServerFormErrors } from '../../../composables/serverFormErrors.js';
import { trans } from '@i18n';
import { ref } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/solid";

const emit = defineEmits(['close', 'confirm', 'success', 'error', 'refresh-data'])

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
});

const form$ = ref(null)

const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData

    // console.log(requestData);
    return await form$.$vueform.services.axios.post(props.options.routes.store_route, requestData)
};

function clearErrorsRecursive(el$) {
    // clear this element’s errors
    el$.messageBag?.clear()

    // if it has child elements, recurse into each
    if (el$.children$) {
        Object.values(el$.children$).forEach(childEl$ => {
            clearErrorsRecursive(childEl$)
        })
    }
}

const handleResponse = (response, form$) => {
    // Clear form including nested elements 
    Object.values(form$.elements$).forEach(el$ => {
        clearErrorsRecursive(el$)
    })

    // Display custom errors for elements
    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0])
            }
        })
    }
}

const handleSuccess = (response, form$) => {
    // console.log(response) // axios response
    // console.log(response.status) // HTTP status code
    // console.log(response.data) // response data

    emit('success', response.data.messages);
    emit('close');
    emit('refresh-data');
}

const handleError = (error, details, form$) => {
    form$.messageBag.clear() // clear message bag

    switch (details.type) {
        // Error occured while preparing elements (no submit happened)
        case 'prepare':
            console.log(error) // Error object

            form$.messageBag.append(trans('Could not prepare form'))
            break

        // Error occured because response status is outside of 2xx
        case 'submit':
            emit('error', error);
            console.log(error) // AxiosError object
            // console.log(error.response) // axios response
            // console.log(error.response.status) // HTTP status code
            // console.log(error.response.data) // response data

            // console.log(error.response.data.errors)


            break

        // Request cancelled (no response object)
        case 'cancel':
            console.log(error) // Error object

            form$.messageBag.append(trans('Request cancelled'))
            break

        // Some other errors happened (no response object)
        case 'other':
            console.log(error) // Error object

            form$.messageBag.append(trans('Couldn\'t submit form'))
            break
    }
}



</script>
