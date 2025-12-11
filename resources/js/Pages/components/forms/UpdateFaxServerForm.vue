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
                                    fax_uuid: options.item.fax_uuid,
                                    fax_name: options.item.fax_name,
                                    fax_email: options.item.fax_email,
                                    emailList: options.item.fax_email
                                        ? options.item.fax_email.split(',').map(email => ({ email: email.trim() }))
                                        : [],
                                    fax_extension: options.item.fax_extension,
                                    fax_caller_id_name: options.item.fax_caller_id_name,
                                    fax_caller_id_number: options.item.fax_caller_id_number,
                                    fax_description: options.item.fax_description,
                                    fax_forward_number: options.item.fax_forward_number,
                                    fax_prefix: options.item.fax_prefix,
                                    fax_send_channels: options.item.fax_send_channels,
                                    fax_toll_allow: options.item.fax_toll_allow,

                                    authorized_emails: options.item.allowed_emails
                                        ? options.item.allowed_emails.map(email => ({ email: email.email }))
                                        : [],

                                    authorized_domains: options.item.allowed_domain_names
                                        ? options.item.allowed_domain_names.map(domain => ({ email: domain.domain }))
                                        : [],

                                    locations: options.item.locations
                                        ? options.item.locations.map(l => l.location_uuid)
                                        : [],
                                }">

                                <template #empty>

                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical" @select="handleTabSelected">
                                                <FormTab name="page0" label="Settings" :elements="[
                                                    'h4',
                                                    'fax_name',
                                                    'fax_uuid',
                                                    'fax_uuid_clean',
                                                    'fax_extension',
                                                    'fax_caller_id_name',
                                                    'fax_caller_id_number',
                                                    'fax_description',
                                                    'fax_recipients_title',
                                                    'fax_recipients',
                                                    'emailList',
                                                    'authorized_domains_title',
                                                    'authorized_emails_title',
                                                    'authorized_emails',
                                                    'authorized_domains',
                                                    'container_3',
                                                    'submit',
                                                    'divider',
                                                    'divider2',
                                                    'fax_recipients_container',
                                                    'authorized_domains_container',

                                                ]" />
                                                <FormTab name="advanced" label="Advanced" :elements="[
                                                    'fax_forward_number',
                                                    'fax_prefix',
                                                    'fax_toll_allow',
                                                    'fax_send_channels',
                                                    'advanced_title',
                                                    'locations',
                                                    'advanced_container',
                                                    'advanced_submit'

                                                ]" />

                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>

                                                <HiddenElement name="fax_uuid" :meta="true" />
                                                <HiddenElement name="fax_email" :meta="true" />
                                                <StaticElement name="fax_uuid_clean"
                                                    :conditions="[() => options.permissions.is_superadmin]">

                                                    <div class="mb-1">
                                                        <div class="text-sm font-medium text-gray-600 mb-1">
                                                            Unique ID
                                                        </div>

                                                        <div class="flex items-center group">
                                                            <span class="text-sm text-gray-900 select-all font-normal">
                                                                {{ options.item.fax_uuid }}
                                                            </span>
                                                            <button type="button"
                                                                @click="handleCopyToClipboard(options.item.fax_uuid)"
                                                                class="ml-2 p-1 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                                                title="Copy to clipboard">
                                                                <!-- Small Copy Icon -->
                                                                <ClipboardDocumentIcon
                                                                    class="h-4 w-4 text-gray-500 hover:text-gray-900  cursor-pointer" />
                                                            </button>
                                                        </div>
                                                    </div>

                                                </StaticElement>
                                                <StaticElement name="h4" tag="h4" content="Settings" />


                                                <TextElement name="fax_name" label="Name" placeholder="Enter name"
                                                    :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" />
                                                <TextElement name="fax_extension" label="Extension"
                                                    placeholder="Enter extension" :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" />



                                                <TextElement name="fax_caller_id_name" label="Caller ID Name"
                                                    placeholder="Enter caller ID name" :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" />
                                                <SelectElement name="fax_caller_id_number" label="Caller ID Number"
                                                    :items="options.phone_numbers" :search="true" :native="false"
                                                    input-type="search" autocomplete="off" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" />

                                                <TextElement name="fax_description" label="Description" :columns="{
                                                    sm: {
                                                        container: 12,
                                                    },
                                                }" placeholder="Enter description" :floating="false" />

                                                <StaticElement name="advanced_title" tag="h4" content="Advanced" />


                                                <TextElement name="fax_forward_number"
                                                    placeholder="Enter forward number" :floating="false"
                                                    label="Forward Number" :columns="{
                                                        sm: {
                                                            container: 12,
                                                            wrapper: 6,
                                                        },
                                                    }"
                                                    description="Enter the forward number here. Used to forward the fax to a registered extension or external number." />

                                                <TextElement name="fax_prefix" placeholder="Enter prefix"
                                                    :floating="false" label="Number Prefix" :columns="{
                                                        sm: {
                                                            container: 12,
                                                            wrapper: 6,
                                                        },
                                                    }"
                                                    description="The prefix specified here will be prepended to all outbound fax calls." />

                                                <TextElement name="fax_toll_allow" label="Toll Allow" :floating="false"
                                                    :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }"
                                                    description="Enter the toll allow value here. (Examples: domestic,international,local)" />
                                                <TextElement name="fax_send_channels" label="Number of Channels"
                                                    :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" description="Enter the maximum number of channels to use." />

                                                <TagsElement name="locations" :close-on-select="false" :search="true"
                                                    label-prop="name" value-prop="location_uuid" :items="locations"
                                                    :track-by="['name', 'description']"
                                                    label="Locations"
                                                    input-type="search" autocomplete="off" placeholder="Select Locations"
                                                    :floating="false" :loading="isLocationsLoading"
                                                    description="Assign one or more locations. If none are selected, this resource is visible to all users." />

                                                <GroupElement name="advanced_container" />

                                                <ButtonElement name="advanced_submit" button-label="Save"
                                                    :submits="true" align="right" />

                                                <StaticElement name="fax_recipients_title" tag="h4"
                                                    content="Forward incoming faxes to email" top="3"
                                                    description="Add up to 5 email addresses to automatically forward every incoming fax." />

                                                <ListElement name="emailList" :initial="0" :submit="false" :sort="true"
                                                    @change="handleEmailListChange">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <TextElement name="email" placeholder="Enter email address"
                                                                :floating="false" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <GroupElement name="fax_recipients_container" />

                                                <StaticElement name="divider" tag="hr" />

                                                <StaticElement name="authorized_domains_title" tag="h4"
                                                    content="Domains allowed to use email-to-fax" top="2"
                                                    description="This feature allows accepting faxes from specific email domains. All messages sent from addresses on those domains will be allowed. You may enter multiple domains." />


                                                <ListElement name="authorized_domains" :initial="0" :sort="true">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <TextElement name="email"
                                                                placeholder="Enter domain name (example.com)"
                                                                :floating="false" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <GroupElement name="authorized_domains_container" />

                                                <StaticElement name="divider2" tag="hr" />

                                                <StaticElement name="authorized_emails_title" tag="h4"
                                                    content="Additional authorized email addresses for email-to-fax"
                                                    top="2"
                                                    description="Enter any trusted email addresses not covered by authorized domains." />


                                                <ListElement name="authorized_emails" :initial="0" :sort="true"
                                                    :key="'email-' + Math.random().toString(20)">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <TextElement name="email" placeholder="Enter email address"
                                                                :floating="false" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>


                                                <GroupElement name="container_3" />

                                                <ButtonElement name="submit" button-label="Save" :submits="true"
                                                    align="right" />
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

const emit = defineEmits(['close', 'error', 'success', 'refresh-data'])

const props = defineProps({
    show: Boolean,
    options: Object,
    header: String,
    loading: Boolean,
});

const form$ = ref(null)
const locations = ref([])
const isLocationsLoading = ref(false)

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emit('success', 'success', { message: ['Copied to clipboard.'] });
    }).catch((error) => {
        // Handle the error case
        emit('error', { response: { data: { errors: { request: ['Failed to copy to clipboard.'] } } } });
    });
}

const handleEmailListChange = (newValue, oldValue, el$) => {
    // Basic email regex pattern
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    const emails = newValue
        .map(item => (item && typeof item === 'object' ? item.email : null)) // Check item is an object
        .filter(email => !!email && emailRegex.test(email))
        .join(',');

    el$.form$.el$('fax_email').update(emails)
}


const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData
    // console.log(requestData);

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

const handleTabSelected = (activeTab, previousTab) => {
    if (activeTab.name == 'advanced') {
        getLocations()
    }
}

const getLocations = async () => {
    isLocationsLoading.value = true
    axios.get(props.options.routes.locations, {
        params: {
            domain_uuid: props.options.item.domain_uuid
        }
    })
        .then((response) => {
            locations.value = response.data;
            // console.log(locations.value);

        }).catch((error) => {
            emit('error', error)
        }).finally(() => {
            isLocationsLoading.value = false
        });
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