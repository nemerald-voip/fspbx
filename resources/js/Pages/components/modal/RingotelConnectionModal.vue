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
                            class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl  sm:p-6">
                            <div>

                                <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">

                                    <div class="space-y-6 sm:px-6 lg:col-span-12 lg:px-0">


                                                <div class="flex justify-between items-center">
                                                    <h3 class="text-base font-semibold leading-6 text-gray-900">Connection
                                                        Details</h3>

                                                    <!-- <Toggle label="Status" v-model="" /> -->

                                                    <!-- <p class="mt-1 text-sm text-gray-500"></p> -->
                                                </div>
                                                <div class="col-span-6 sm:col-span-6">
                                                        <LabelInputRequired target="connection_name"
                                                            label="Connection Name" class="truncate" />
                                                        <InputField v-model="form.connection_name" type="text"
                                                            name="connection_name" id="connection_name" class="mt-2"
                                                            :error="!!errors?.connection_name" />
                                                        <div v-if="errors?.connection_name"
                                                            class="mt-2 text-xs text-red-600">
                                                            {{ errors.connection_name[0] }}
                                                        </div>
                                                    </div>
                                                    <div class="col-span-6 sm:col-span-3">
                                                        <LabelInputRequired target="connection_name"
                                                            label="Connection Name" class="truncate" />
                                                        <InputField v-model="form.connection_name" type="text"
                                                            name="connection_name" id="connection_name" class="mt-2"
                                                            :error="!!errors?.connection_name" />
                                                        <div v-if="errors?.connection_name"
                                                            class="mt-2 text-xs text-red-600">
                                                            {{ errors.connection_name[0] }}
                                                        </div>
                                                    </div>
                                                    <div class="col-span-6 sm:col-span-3">
                                                        <LabelInputRequired target="connection_name"
                                                            label="Connection Name" class="truncate" />
                                                        <InputField v-model="form.connection_name" type="text"
                                                            name="connection_name" id="connection_name" class="mt-2"
                                                            :error="!!errors?.connection_name" />
                                                        <div v-if="errors?.connection_name"
                                                            class="mt-2 text-xs text-red-600">
                                                            {{ errors.connection_name[0] }}
                                                        </div>
                                                    </div>
                                                    <div class="col-span-6 sm:col-span-3">
                                                        <LabelInputRequired target="connection_name"
                                                            label="Connection Name" class="truncate" />
                                                        <InputField v-model="form.connection_name" type="text"
                                                            name="connection_name" id="connection_name" class="mt-2"
                                                            :error="!!errors?.connection_name" />
                                                        <div v-if="errors?.connection_name"
                                                            class="mt-2 text-xs text-red-600">
                                                            {{ errors.connection_name[0] }}
                                                        </div>
                                                    </div>
                                                    

                                                <div class="grid grid-cols-6 gap-6">
                                                    <div class="col-span-6 sm:col-span-3">
                                                        <LabelInputRequired target="connection_name"
                                                            label="Connection Name" class="truncate" />
                                                        <InputField v-model="form.connection_name" type="text"
                                                            name="connection_name" id="connection_name" class="mt-2"
                                                            :error="!!errors?.connection_name" />
                                                        <div v-if="errors?.connection_name"
                                                            class="mt-2 text-xs text-red-600">
                                                            {{ errors.connection_name[0] }}
                                                        </div>
                                                    </div>

                                                    <div class="col-span-6 sm:col-span-3">
                                                        <LabelInputRequired target="organization_domain"
                                                            label="Unique Organization Domain" class="truncate" />
                                                        <InputField v-model="form.organization_domain" type="text"
                                                            name="organization_domain" id="organization_domain" class="mt-2"
                                                            :error="!!errors?.organization_domain" />
                                                        <div v-if="errors?.organization_domain"
                                                            class="mt-2 text-xs text-red-600">
                                                            {{ errors.organization_domain[0] }}
                                                        </div>
                                                    </div>

                                                    <!-- <div class="col-span-6 sm:col-span-3">
                                                        <LabelInputRequired label="Region" class="truncate mb-1" />

                                                        <ComboBox :options="options.regions" :search="true"
                                                            :placeholder="'Select region'"
                                                            :error="errors?.region && errors.region.length > 0"
                                                            :selectedItem="form.region"
                                                            @update:model-value="handleUpdateRegionField" />
                                                        <div v-if="errors?.region" class="mt-2 text-xs text-red-600">
                                                            {{ errors.region[0] }}
                                                        </div>
                                                        <p class="mt-3 text-sm leading-6 text-gray-600">Choose the region
                                                            closest to your users
                                                            location. You won't be able to change it later.</p>

                                                    </div> -->

                                                    <!-- <div class="col-span-6 sm:col-span-3">
                                                        <LabelInputRequired label="Package" class="truncate mb-1" />

                                                        <ComboBox :options="options.packages" :search="true"
                                                            :placeholder="'Select package'"
                                                            :error="errors?.package && errors.package.length > 0"
                                                            :selectedItem="form.package"
                                                            @update:model-value="handleUpdatePackageField" />
                                                        <div v-if="errors?.package" class="mt-2 text-xs text-red-600">
                                                            {{ errors.package[0] }}
                                                        </div>
                                                        <p class="mt-3 text-sm leading-6 text-gray-600">The selected package
                                                            defines the
                                                            available features.</p>

                                                    </div> -->

                                                    <!-- <div class="divide-y divide-gray-200 col-span-6">

                                                        <Toggle label="Secure User Credentials"
                                                            description="When enabled, users will receive a one-time link to access their app password instead of plain text."
                                                            v-model="form.dont_send_user_credentials" customClass="py-4" />

                                                    </div> -->



                                                </div>

                                            

                                    </div>
                                </div>

                            </div>
                            <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                                <button type="button"
                                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                                    @click="updateGreeting">
                                    Save
                                    <Spinner class="ml-2" :color="'text-gray-700'" :show="loading" />
                                </button>
                                <button type="button"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                                    @click="emit('close')">Cancel</button>
                            </div>
                        </DialogPanel>


                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { reactive, ref, watch } from "vue";
import { usePage } from '@inertiajs/vue3';

import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import Spinner from "@generalComponents/Spinner.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import InputField from "../general/InputField.vue";
import ComboBox from "../general/ComboBox.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";

const page = usePage();

const emit = defineEmits(['close', 'confirm'])

const props = defineProps({
    show: Boolean,
    greeting: Object,
    loading: {
        type: Boolean,
        default: false, // Default value for loading
    },
});

const errors = ref(null);


watch(() => props.greeting, (newGreeting) => {
    greeting_name.value = newGreeting?.name || '';
});

const form = reactive({
    connection_name: null,
    organization_domain: null,
    region: null,
    package: null,
    // dont_send_user_credentials: props.options.dont_send_user_credentials === "true",
    domain_uuid: null,
    _token: page.props.csrf_token,
})

const updateGreeting = () => {
    emit('confirm', { ...props.greeting, name: greeting_name.value });
    emit('close');
};

</script>