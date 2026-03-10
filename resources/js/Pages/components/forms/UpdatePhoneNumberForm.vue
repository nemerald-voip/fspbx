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

                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

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


                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false" :default="{
                                    destination_enabled: options.item?.destination_enabled ?? false,
                                    destination_prefix: options.item?.destination_prefix ?? null,
                                    destination_number: options.item?.destination_number ?? null,
                                    domain_uuid: options.item?.domain_uuid,
                                    routing_options: options.item.routing_options ?? [],
                                    destination_description: options.item?.destination_description ?? null,
                                    destination_record: options.item?.destination_record ?? 'false',
                                    destination_type_fax: options.item?.destination_type_fax ?? 'false',
                                    fax_uuid: options.item?.fax_uuid ?? null,
                                    destination_cid_name_prefix: options.item?.destination_cid_name_prefix ?? null,
                                    destination_accountcode: options.item?.destination_accountcode ?? null,
                                    destination_hold_music: options.item.destination_hold_music ?? '',
                                    destination_distinctive_ring: options.item?.destination_distinctive_ring ?? null,
                                }">

                                <template #empty>

                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical" @select="handleTabSelected">
                                                <FormTab name="page0" label="Settings" :elements="[
                                                    'h4',
                                                    'uuid_clean',
                                                    'destination_enabled',
                                                    'destination_prefix',
                                                    'destination_number',
                                                    'destination_description',
                                                    'routing_container',
                                                    'call_routing_title',
                                                    'routing_options',
                                                    'container_3',
                                                    'submit',

                                                ]" />
                                                <FormTab name="page1" label="Advanced" :elements="[
                                                    'destination_record',
                                                    'destination_type_fax',
                                                    'fax_uuid',
                                                    'destination_cid_name_prefix',
                                                    'destination_accountcode',
                                                    'destination_distinctive_ring',
                                                    'destination_hold_music',
                                                    'domain_uuid',
                                                    'advanced_container2',
                                                    'submit_advanced'
                                                ]" />

                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>

                                                <StaticElement name="h4" tag="h4" content="Settings" />

                                                <StaticElement name="uuid_clean" :conditions="[() => options.permissions.is_superadmin]">
                                                    <div class="mb-1">
                                                        <div class="text-sm font-medium text-gray-600 mb-1">                                                            Unique ID
                                                        </div>
                                                        <div class="flex items-center group">
                                                            <span class="text-sm text-gray-900 select-all font-normal">
                                                                {{ options.item.destination_uuid }}
                                                            </span>
                                                            <button type="button"
                                                                @click="handleCopyToClipboard(options.item.destination_uuid)"
                                                                class="ml-2 p-1 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                                                title="Copy to clipboard">
                                                                <ClipboardDocumentIcon class="h-4 w-4 text-gray-500 hover:text-gray-900  cursor-pointer" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </StaticElement>

                                                <ToggleElement name="destination_enabled" text="Status"
                                                    true-value="true" false-value="false" />

                                                <TextElement name="destination_prefix" label="Country Code"
                                                    :floating="false" :columns="{
                                                        default: {
                                                            container: 6,
                                                        },
                                                        sm: {
                                                            container: 3,
                                                        },
                                                        lg: {
                                                            container: 2,
                                                        }
                                                    }" />
                                                <TextElement name="destination_number" label="Phone Number" :columns="{
                                                    sm: {
                                                        container: 8,
                                                    },
                                                    sm: {
                                                        container: 5,
                                                    },
                                                }" />
                                                <TextElement name="destination_description" label="Description"
                                                    placeholder="Enter Description" :floating="false" />



                                                <GroupElement name="routing_container" />

                                                <StaticElement name="call_routing_title" tag="h4" content="Call routing"
                                                    description="Ensure calls are routed to the right team every time. Select a routing option below to fit your business needs." />

                                                <ListElement name="routing_options" :sort="true" size="sm"
                                                    :controls="{ add: true, remove: true, sort: true }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-md' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">

                                                            <SelectElement name="type" :items="options.routing_types"
                                                                label-prop="name" :search="true" :native="false"
                                                                label="Choose Action" input-type="search"
                                                                autocomplete="off" placeholder="Choose Action"
                                                                :floating="false" :strict="false" :columns="{
                                                                    sm: {
                                                                        container: 6,
                                                                    },
                                                                }" @change="(newValue, oldValue, el$) => {
                                                                    let extension = el$.form$.el$('routing_options.' + index + '.extension')

                                                                    // only clear when this isn’t the very first time (i.e. oldValue was set)
                                                                    if (oldValue !== null && oldValue !== undefined) {
                                                                        extension.clear();
                                                                    }

                                                                    // fallback_target.clear()
                                                                    extension.updateItems()
                                                                }" />


                                                            <SelectElement name="extension" :items="async (query, input) => {
                                                                let type = input.$parent.el$.form$.el$('routing_options.' + index + '.type');

                                                                try {
                                                                    let response = await type.$vueform.services.axios.post(
                                                                        options.routes.get_routing_options,
                                                                        { category: type.value }
                                                                    );
                                                                    // let option = el$.form$.el$('routing_options.' + index + '.option')

                                                                    return response?.data?.options;
                                                                } catch (error) {
                                                                    emit('error', error);
                                                                    return [];  // Return an empty array in case of error
                                                                }

                                                            }" :search="true" label-prop="name" :native="false"
                                                                value-prop="extension" label="Target"
                                                                input-type="search" allow-absent autocomplete="off"
                                                                placeholder="Choose Target" :floating="false"
                                                                :strict="false" :columns="{

                                                                    sm: {
                                                                        container: 6,
                                                                    },
                                                                }" :conditions="[

                                                                    ['routing_options.' + index + '.type', 'not_empty'],
                                                                    ['routing_options.' + index + '.type', 'not_in', ['check_voicemail', 'company_directory', 'hangup']]
                                                                ]" />



                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <GroupElement name="container_3" />

                                                <ButtonElement name="submit" button-label="Save" :submits="true"
                                                    align="right" />


                                                <ToggleElement name="destination_record" text="Record Inbound Calls"
                                                    description="Enable this setting to automatically record all inbound calls for this phone number. Once activated, every incoming call will be captured and stored for future reference, ensuring that no important conversation is missed. Note: Ensure compliance with local call recording laws before enabling."
                                                    true-value="true" false-value="false"
                                                    :conditions="[() => options?.permissions?.manage_recording_setting]" />

                                                <ToggleElement name="destination_type_fax" text="Enable Fax Machine"
                                                    description="Activate this setting if calls will be routed directly to a physical fax machine. This ensures proper handling of fax transmissions."
                                                    :true-value="'1'" />
                                                <SelectElement name="fax_uuid" :items="options.faxes" :search="true"
                                                    :native="false" label="Fax detection" input-type="search"
                                                    autocomplete="off" />
                                                <TextElement name="destination_cid_name_prefix"
                                                    label="Caller ID name prefix" />
                                                <TextElement name="destination_accountcode" label="Account code"
                                                    :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" />
                                                <TextElement name="destination_distinctive_ring"
                                                    label="Distinctive ring" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" />

                                                <SelectElement name="destination_hold_music"
                                                    :items="options.music_on_hold_options" :groups="true" default=""
                                                    :search="true" :native="false" label="Select custom Music On Hold"
                                                    input-type="search" autocomplete="off" :strict="false" :columns="{
                                                        sm: {
                                                            wrapper: 6,
                                                        },
                                                    }"
                                                    :conditions="[() => options.permissions.destination_hold_music]" />

                                                <SelectElement name="domain_uuid" :items="options.domains"
                                                    :search="true" :native="false" label="Assigned To (Account)"
                                                    :conditions="[() => options?.permissions?.manage_destination_domain]"
                                                    input-type="search" autocomplete="off" />

                                                <GroupElement name="advanced_container2" />

                                                <ButtonElement name="submit_advanced" button-label="Save"
                                                    :submits="true" align="right" />



                                            </FormElements>
                                        </div>
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
import { ref } from "vue";

import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/solid";
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";

const props = defineProps({
    show: Boolean,
    options: Object,
    header: String,
    loading: Boolean,
});

const form$ = ref(null)

const emit = defineEmits(['close', 'error', 'success', 'refresh-data'])

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emit('success', 'success', { message: ['Copied to clipboard.'] });
    }).catch((error) => {
        emit('error', { response: { data: { errors: { request: ['Failed to copy to clipboard.'] } } } });
    });
}

const formatTarget = (name, value) => {
    return { [name]: value?.extension ?? null } // must return an object
}

const handleTabSelected = (activeTab, previousTab) => {
    // if (activeTab.name == 'cloud_provisioning') {
    //     getCloudProvisioningStatus();
    // }
}



const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData
    // console.log(requestData);

    // Using form$.data will INCLUDE conditional elements and it
    // will submit the form as "Content-Type: application/json".
    // const data = form$.data

    return await form$.$vueform.services.axios.put(props.options.routes.update_route, requestData)
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

    emit('success', 'success', response.data.messages);
    emit('close');
    emit('refresh-data');
}

const handleError = (error, details, form$) => {
    form$.messageBag.clear() // clear message bag

    switch (details.type) {
        // Error occured while preparing elements (no submit happened)
        case 'prepare':
            console.log(error) // Error object

            form$.messageBag.append('Could not prepare form')
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

            form$.messageBag.append('Request cancelled')
            break

        // Some other errors happened (no response object)
        case 'other':
            console.log(error) // Error object

            form$.messageBag.append('Couldn\'t submit form')
            break
    }
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