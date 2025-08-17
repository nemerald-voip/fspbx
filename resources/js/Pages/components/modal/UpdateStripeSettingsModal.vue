<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="div" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                        <DialogPanel
                            class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">


                            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                                @response="handleResponse" :display-errors="false"
                                :default="{ 
                                    uuid: uuid ?? null,
                                    status: isEnabled ?? false, 
                                    sandbox_secret_key: settings.sandbox_secret_key ?? null, 
                                    sandbox_publishable_key: settings.sandbox_publishable_key ?? null,
                                    live_mode_secret_key: settings.live_mode_secret_key ?? null,
                                    live_mode_publishable_key: settings.live_mode_publishable_key ?? null,
                                    }">
                                <StaticElement name="title" tag="h3" content="Stripe Settings"
                                    description="Please fill out the following fields to configure your gateway settings." />
                                    <HiddenElement name="uuid" :meta="true" />
                                <RadiogroupElement name="status" label="Enable/Disable Gateway" :rules="[
                                    'required',
                                ]" :items="{true: 'On', false: 'Off',}" />
                                <TextElement name="sandbox_secret_key" label="Sandbox Secret Key"
                                    description="Provide the secret key for the sandbox environment."
                                    placeholder="Enter Sandbox Secret Key" :floating="false"/>
                                <TextElement name="sandbox_publishable_key" label="Sandbox Publishable Key"
                                    description="Enter the publishable key for the sandbox environment."
                                    placeholder="Enter Sandbox Publishable Key" :floating="false"/>
                                <TextElement name="live_mode_secret_key" label="Live Mode Secret Key"
                                    description="Insert the secret key for the live mode."
                                    placeholder="Enter Live Mode Secret Key" :floating="false"/>
                                <TextElement name="live_mode_publishable_key" label="Live Mode Publishable Key"
                                    description="Provide the publishable key for the live mode."
                                    placeholder="Enter Live Mode Publishable Key" :floating="false"/>
                                <ButtonElement @click="emit('close')" name="cancel" button-label="Cancel" :secondary="true"
                                    :columns="{
                                        container: 6,
                                    }" :full="true" />
                                <ButtonElement name="submit" button-label="Submit" :submits="true" :full="true"
                                    align="center" :columns="{
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
import { ref } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'

const emit = defineEmits(['close', 'confirm'])

const form$ = ref(null)

const props = defineProps({
    show: Boolean,
    settings: Object,
    uuid: String,
    isEnabled: Boolean,
    route: String,
});


const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData

    console.log(requestData);
    return await form$.$vueform.services.axios.put(props.route, requestData)
};

</script>
