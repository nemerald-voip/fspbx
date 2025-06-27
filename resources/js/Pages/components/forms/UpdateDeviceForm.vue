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
                                    device_address: options.item?.device_address ?? null,
                                    device_template: options.item?.device_template ?? null,
                                    device_profile_uuid: options.item?.device_profile_uuid,
                                    domain_uuid: options.item?.domain_uuid,
                                }">

                                <template #empty>

                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical" @select="handleTabSelected">
                                                <FormTab name="page0" label="Device Settings" :elements="[
                                                    'h4',
                                                    'device_address',
                                                    'device_template',
                                                    'device_profile_uuid',
                                                    'domain_uuid',

                                                    'container_3',
                                                    'submit',

                                                ]" />
                                                <FormTab name="page1" label="Keys" :elements="[
                                                    'password_reset',
                                                    'security_title',
                                                    'keys_container',
                                                    'keys_title',
                                                    'assign_existing',
                                                    'add_key',
                                                    'device_keys',

                                                ]" />
                                                <FormTab name="api_tokens" label="API Keys" :elements="[
                                                    'html',
                                                    'add_token',
                                                    'token_title',


                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>

                                                <StaticElement name="h4" tag="h4" content="Device Settings" />

                                                <TextElement name="device_address" label="MAC Address"
                                                    placeholder="Enter MAC address" :floating="false" />

                                                <SelectElement name="device_template" :items="options.templates"
                                                    :search="true" :native="false" label="Device Template"
                                                    input-type="search" autocomplete="off" label-prop="name"
                                                    value-prop="value" :floating="false"
                                                    placeholder="Select Template" />

                                                <SelectElement name="device_profile_uuid" :items="options.profiles"
                                                    :search="true" :native="false" label="Device Profile"
                                                    input-type="search" autocomplete="off" label-prop="name"
                                                    value-prop="value" placeholder="Select Profile (Optional)"
                                                    :floating="false" />

                                                <SelectElement name="domain_uuid" :items="options.domains"
                                                    :search="true" :native="false" label="Assigned To (Account)"
                                                    input-type="search" autocomplete="off" label-prop="name"
                                                    value-prop="value" placeholder="Select Account" :floating="false" />

                                                <GroupElement name="container_3" />

                                                <ButtonElement name="submit" button-label="Save" :submits="true"
                                                    align="right" />


                                                <!-- Lines tab-->
                                                <StaticElement name="keys_title" tag="h4" content="Device Keys"
                                                    description="Assign functions to the device keys." />


                                                <GroupElement name="keys_container" />

                                                <ListElement name="device_keys" :sort="true" size="sm"
                                                    store-order="line_number"
                                                    :controls="{ add: options.permissions.device_key_create, remove: options.permissions.destination_delete, sort: options.permissions.destination_update }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-md' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <TextElement name="line_number" label="Key" :rules="[
                                                                'nullable',
                                                                'numeric',
                                                            ]" autocomplete="off" :columns="{

                                                                sm: {
                                                                    container: 1,
                                                                },
                                                            }" />

                                                            <SelectElement name="select" label="Function"
                                                                :items="options.line_key_types" :search="true"
                                                                label-prop="name" :native="false" input-type="search"
                                                                autocomplete="off" :columns="{

                                                                    sm: {
                                                                        container: 3,
                                                                    },
                                                                }" placeholder="Choose Function" :floating="false" />

                                                            <SelectElement name="select_1" label="Ext/Number"
                                                                :items="options.extensions" label-prop="name"
                                                                :search="true" :native="false" input-type="search"
                                                                autocomplete="off" :columns="{

                                                                    sm: {
                                                                        container: 4,
                                                                    },
                                                                }" placeholder="Choose Ext/Number" :floating="false" />

                                                            <TextElement name="text" label="Display Name" :columns="{

                                                                default: {
                                                                    container: 10,
                                                                },
                                                                sm: {
                                                                    container: 3,
                                                                },
                                                            }" placeholder="Display Name" :floating="false" />

                                                            <StaticElement label="&nbsp;" name="key_table" :columns="{

                                                                default: {
                                                                    container: 1,
                                                                },
                                                                sm: {
                                                                    container: 1,
                                                                },
                                                            }">
                                                                <div
                                                                    class="text-sm font-medium leading-6 text-gray-900 text-end">
                                                                    
                                                                    <Menu as="div"
                                                                        class="relative inline-block text-left">
                                                                        <div>
                                                                            <MenuButton
                                                                                class="flex items-center rounded-full bg-gray-100 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-100">
                                                                                <span class="sr-only">Open
                                                                                    options</span>
                                                                                <EllipsisVerticalIcon
                                                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-500 hover:bg-gray-200 hover:text-gray-900 active:bg-gray-300 active:duration-150 cursor-pointer"
                                                                                    aria-hidden="true" />
                                                                            </MenuButton>
                                                                        </div>

                                                                        <transition
                                                                            enter-active-class="transition ease-out duration-100"
                                                                            enter-from-class="transform opacity-0 scale-95"
                                                                            enter-to-class="transform opacity-100 scale-100"
                                                                            leave-active-class="transition ease-in duration-75"
                                                                            leave-from-class="transform opacity-100 scale-100"
                                                                            leave-to-class="transform opacity-0 scale-95">
                                                                            <MenuItems
                                                                                class="absolute right-0 z-10 mt-2 w-36 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                                                                <div class="py-1">
                                                                                    <MenuItem v-slot="{ active }">
                                                                                    <a href="#"
                                                                                        @click.prevent="showLineAdvSettings(index)"
                                                                                        :class="[active ? 'bg-gray-100 text-gray-900' : 'text-gray-700', 'block px-4 py-2 text-sm']">Advanced</a>
                                                                                    </MenuItem>
                                                                                    <MenuItem v-slot="{ active }">
                                                                                    <a href="#"
                                                                                        @click.prevent="deleteLineKey(index)"
                                                                                        :class="[active ? 'bg-gray-100 text-gray-900' : 'text-gray-700', 'block px-4 py-2 text-sm']">Delete</a>
                                                                                    </MenuItem>

                                                                                </div>
                                                                            </MenuItems>
                                                                        </transition>
                                                                    </Menu>

                                                                </div>
                                                            </StaticElement>
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <!-- <ButtonElement name="add_key" button-label="Add Key" align="right"
                                                    @click="handleAddKeyButtonClick" :loading="isModalLoading"
                                                    :conditions="[() => options.permissions.device_key_create]"/>


                                                <StaticElement name="key_table">
                                                    <DeviceKeys :keys="options.lines" :loading="isKeysLoading"
                                                        :permissions="options.permissions"
                                                        @edit-item="handleKeyEditButtonClick"
                                                        @delete-item="handleDeleteKeyButtonClick" />
                                                </StaticElement> -->

                                                <GroupElement name="container_devices" />

                                                <ButtonElement name="submit_devices" button-label="Save" :submits="true"
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

    <AddEditItemModal :show="isLineAdvSettingsModalShown" :header="'Edit SIP Settings'" @close="handleModalClose">
        <template #modal-body>
            <!-- <div class="bg-white px-4 py-6 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 ">
                    <div>
                        <LabelInputOptional :target="'server_address'" :label="'Domain'" />
                        <div class="mt-2">
                            <InputField v-model="form.lines[activeLineIndex].server_address" type="text"
                                name="server_address" placeholder="Enter domain" />
                        </div>
                    </div>

                    <div>
                        <LabelInputOptional :target="'server_address_primary'" :label="'Primary Server Address'" />
                        <div class="mt-2">
                            <InputField v-model="form.lines[activeLineIndex].server_address_primary" type="text"
                                name="server_address_primary" placeholder="Enter primary server address" />
                        </div>
                    </div>

                    <div>
                        <LabelInputOptional :target="'server_address_secondary'" :label="'Secondary Server Address'" />
                        <div class="mt-2">
                            <InputField v-model="form.lines[activeLineIndex].server_address_secondary" type="text"
                                name="server_address_secondary" placeholder="Enter secondary server address" />
                        </div>
                    </div>

                    <div>
                        <LabelInputOptional :target="'sip_port'" :label="'SIP Port'" />
                        <div class="mt-2">
                            <InputField v-model="form.lines[activeLineIndex].sip_port" type="number" name="sip_port"
                                placeholder="Enter SIP port" />
                        </div>
                    </div>

                    <div>
                        <LabelInputOptional :target="'sip_transport'" :label="'SIP Transport'" />
                        <div class="mt-2">
                            <ComboBox :options="options.sip_transport_types"
                                :selectedItem="form.lines[activeLineIndex].sip_transport" :search="true"
                                placeholder="Choose SIP transport"
                                @update:model-value="(value) => handleSipTransportUpdate(value, activeLineIndex)" />
                        </div>
                    </div>

                    <div>
                        <LabelInputOptional :target="'register_expires'" :label="'Register Expires (Seconds)'" />
                        <div class="mt-2">
                            <InputField v-model="form.lines[activeLineIndex].register_expires" type="number"
                                name="register_expires" placeholder="Enter expiry time (seconds)" />
                        </div>
                    </div>

                    <button @click.prevent="handleModalClose"
                        class="flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 ">
                        Close
                    </button>
                </div>

            </div> -->

        </template>

    </AddEditItemModal>
</template>

<script setup>
import { reactive, ref, onMounted } from "vue";
import { usePage } from '@inertiajs/vue3';

import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import Spinner from "../general/Spinner.vue";
import { PlusIcon } from "@heroicons/vue/24/solid";
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import AddEditItemModal from "../modal/AddEditItemModal.vue";
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import { Cog6ToothIcon, AdjustmentsHorizontalIcon, EllipsisVerticalIcon, CloudIcon } from '@heroicons/vue/24/outline';
import axios from "axios";
import { XMarkIcon } from "@heroicons/vue/24/solid";
import DeviceKeys from "../DeviceKeys.vue";


const props = defineProps({
    show: Boolean,
    options: Object,
    header: String,
    loading: Boolean,
});

const page = usePage();
const isKeysLoading = ref(false)
const isModalLoading = ref(false)

// const form = reactive({
//     device_address: props.item.device_address,
//     device_template: props.item.device_template,
//     device_profile_uuid: props.item.device_profile_uuid,
//     device_provisioning: false,
//     // extension: props.item.device_label,
//     lines: props.options.lines,
//     domain_uuid: props.item.domain_uuid,
//     _token: page.props.csrf_token,
// })

const emit = defineEmits(['close', 'error', 'success', 'refresh-data'])

const isCloudProvisioned = ref({
    isLoading: false,
    status: false,
    error: null,
    message: null
});
const isProvisioningAllowed = ref(false);
const isLineAdvSettingsModalShown = ref(false);


const handleAddKeyButtonClick = () => {
    showDeviceCreateModal.value = true
    getDeviceItemOptions();
}

const handleKeyEditButtonClick = (itemUuid) => {
    showDeviceUpdateModal.value = true
    getDeviceItemOptions(itemUuid);
}

const handleDeleteKeyButtonClick = (uuid) => {
    showUnassignConfirmationModal.value = true;
    confirmUnassignAction.value = () => executeBulkUnassign([uuid]);
};

const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData
    // console.log(requestData);

    return await form$.$vueform.services.axios.put(props.options.routes.update_route, requestData)
};

const handleTemplateUpdate = (newSelectedItem) => {
    isProvisioningAllowed.value = form.device_provisioning = newSelectedItem.value.toLowerCase().includes('poly') || newSelectedItem.value.toLowerCase().includes('polycom')
    form.device_template = newSelectedItem.value
}

const handleProfileUpdate = (newSelectedItem) => {
    form.device_profile_uuid = newSelectedItem.value
}

const handleExtensionUpdate = (newSelectedItem, index) => {
    form.lines[index].user_id = newSelectedItem.value;
    form.lines[index].display_name = newSelectedItem.value;
};

const handleKeyTypeUpdate = (newSelectedItem, index) => {
    const newValue = newSelectedItem.value === 'sharedline' ? 'true' : null;

    // Only update if the value is different
    if (form.lines[index].shared_line !== newValue) {
        form.lines[index].shared_line = newValue;
    }
};

const addNewLineKey = () => {
    // console.log(form.lines);
    // Define the new line key object with default values
    const newLineKey = {
        line_number: form.lines.length + 1, // Increment line number based on the array length
        user_id: null,                      // Set initial user_id to null or any default value
        display_name: '',                   // Set initial display_name to an empty string
        shared_line: null,                  // Set initial shared_line to null or any default value
        device_line_uuid: null
    };

    // Push the new line key to the form.lines array
    form.lines.push(newLineKey);
};

const handleDomainUpdate = (newSelectedItem) => {
    form.domain_uuid = newSelectedItem.value;
    form.device_profile_uuid = null;
    form.extension = null;
    if (newSelectedItem.value !== "NULL") {
        emits('domain-selected', newSelectedItem.value); // Emit 'domain-selected' event when the domain is updated
    }
}

const iconComponents = {
    'Cog6ToothIcon': Cog6ToothIcon,
    'AdjustmentsHorizontalIcon': AdjustmentsHorizontalIcon,
    'CloudIcon': CloudIcon,
};


const setActiveTab = (tabSlug) => {
    activeTab.value = tabSlug;
};

const deleteLineKey = (index) => {
    form.lines.splice(index, 1);  // Remove the line key at the specified index
};

const activeLineIndex = ref(null);

const showLineAdvSettings = (index) => {
    activeLineIndex.value = index;
    isLineAdvSettingsModalShown.value = true;
};

const handleModalClose = () => {
    isLineAdvSettingsModalShown.value = false;
};

const handleSipTransportUpdate = (newSelectedItem, index) => {
    form.lines[index].sip_transport = newSelectedItem.value;
};

const handleTabSelected = (activeTab, previousTab) => {
    if (activeTab.name == 'api_tokens') {
        getTokens()
    }
}

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

// onMounted(() => {
//     isProvisioningAllowed.value = form.device_template.toLowerCase().includes('poly') || form.device_template.toLowerCase().includes('polycom')
//     fetchProvisioningStatus();
// });

// const fetchProvisioningStatus = () => {
//     isCloudProvisioned.value.isLoading = true;
//     axios.post(page.props.routes.cloud_provisioning_status, {
//         'items': [props.item.device_uuid]
//     })
//         .then(response => {
//             const device = response.data.devicesData.find(d => d.device_uuid === props.item.device_uuid);
//             if (device) {
//                 if (device.status === 'provisioned') {
//                     isCloudProvisioned.value.status = true;
//                     isCloudProvisioned.value.error = null;
//                     form.device_provisioning = true;
//                 } else {
//                     isCloudProvisioned.value.status = false;
//                     isCloudProvisioned.value.error = device.error;
//                     form.device_provisioning = false;
//                 }
//             } else {
//                 isCloudProvisioned.value.status = false;
//                 isCloudProvisioned.value.error = 'Not found';
//                 form.device_provisioning = false;
//             }
//         })
//         .catch(error => {
//             console.error(error);
//             isCloudProvisioned.value.status = false;
//             form.device_provisioning = false;
//             isCloudProvisioned.value.error = error.response?.data?.error;
//         })
//         .finally(() => {
//             isCloudProvisioned.value.isLoading = false;
//         });
// };
</script>
