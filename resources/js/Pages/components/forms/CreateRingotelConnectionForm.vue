<template>
    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
        <aside class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
            <nav class="space-y-1">
                <a v-for="item in options.conn_navigation" :key="item.name" href="#"
                    :class="[activeTab === item.slug ? 'bg-gray-200 text-indigo-700 hover:bg-gray-100 hover:text-indigo-700' : 'text-gray-900 hover:bg-gray-200 hover:text-gray-900', 'group flex items-center rounded-md px-3 py-2 text-sm font-medium']"
                    @click.prevent="setActiveTab(item.slug)" :aria-current="item.current ? 'page' : undefined">
                    <component :is="iconComponents[item.icon]"
                        :class="[item.current ? 'text-indigo-500 group-hover:text-indigo-500' : 'text-gray-400 group-hover:text-gray-500', '-ml-1 mr-3 h-6 w-6 flex-shrink-0']"
                        aria-hidden="true" />
                    <span class="truncate">{{ item.name }}</span>
                    <ExclamationCircleIcon
                        v-if="((errors?.device_address || errors?.device_template) && item.slug === 'settings')"
                        class="ml-2 h-5 w-5 text-red-500" aria-hidden="true" />

                </a>
            </nav>
        </aside>

        <div class="space-y-6 sm:px-6 lg:col-span-9 lg:px-0">
            <form @submit.prevent="submitForm">
                <div v-if="activeTab === 'settings'">
                    <div class="shadow sm:rounded-md">
                        <div class="space-y-6 bg-gray-100 px-4 py-6 sm:p-6">
                            <div class="flex justify-between items-center">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">Connection Details</h3>

                                <!-- <Toggle label="Status" v-model="" /> -->

                                <!-- <p class="mt-1 text-sm text-gray-500"></p> -->
                            </div>

                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired target="connection_name" label="Connection Name"
                                        class="truncate mb-1" />
                                    <InputField v-model="form.connection_name" type="text" name="connection_name"
                                        id="connection_name" class="mt-1" :error="!!errors?.organization_name"
                                        :placeholder="'Enter connection name'" />
                                    <div v-if="errors?.connection_name" class="mt-2 text-xs text-red-600">
                                        {{ errors.connection_name[0] }}
                                    </div>
                                </div>



                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired label="Protocol" class="truncate mb-1" />
                                    <ComboBox :options="options.protocols" :search="true" :placeholder="'Select protocol'"
                                        :error="errors?.protocol && errors.protocol.length > 0"
                                        :selectedItem="options.settings.mobile_app_conn_protocol"
                                        @update:model-value="handleUpdateProtocolField" />
                                    <div v-if="errors?.protocol" class="mt-2 text-xs text-red-600">
                                        {{ errors.protocol[0] }}
                                    </div>

                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired target="domain" label="Domain or IP Address"
                                        class="truncate mb-1" />
                                    <InputField v-model="form.domain" type="text" name="domain"
                                        :placeholder="'Enter domain or IP'" id="domain" class="mt-1"
                                        :error="!!errors?.domain" />
                                    <div v-if="errors?.domain" class="mt-2 text-xs text-red-600">
                                        {{ errors.domain[0] }}
                                    </div>
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired target="domain" label="Port" class="truncate mb-1" />
                                    <InputField v-model="form.port" type="text" name="port" :placeholder="'Enter port'"
                                        id="port" class="mt-1" :error="!!errors?.port" />
                                    <div v-if="errors?.port" class="mt-2 text-xs text-red-600">
                                        {{ errors.port[0] }}
                                    </div>
                                </div>

                                <div class="divide-y divide-gray-200 col-span-6 ">

                                    <Toggle label="Do not verify server certificate" description=""
                                        v-model="form.dont_send_user_credentials" customClass="py-4" />

                                    <Toggle label="Disable SRTP" description=""
                                        v-model="form.disable_srtp" customClass="py-4" />

                                </div>
                            </div>


                            <div class="w-full border-t border-gray-300" />

                            <div class="flex justify-between items-center">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">Outbound Proxy Settings</h3>
                            </div>


                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6">
                                    <LabelInputRequired target="proxy" label="Address"
                                        class="truncate mb-1" />
                                    <InputField v-model="form.proxy" type="text" name="proxy"
                                        id="proxy" class="mt-1" :error="!!errors?.organization_name"
                                        :placeholder="'Enter proxy address'" />
                                    <div v-if="errors?.proxy" class="mt-2 text-xs text-red-600">
                                        {{ errors.proxy[0] }}
                                    </div>
                                </div>

                            </div>



                            <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                <button type="sumbit" :disabled="isSubmitting"
                                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto"
                                    @click="open = false">
                                    <Spinner :show="isSubmitting" />
                                    Save
                                </button>
                                <button type="button" @click="emits('cancel')"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from "vue";
import { usePage } from '@inertiajs/vue3';


import ComboBox from "../general/ComboBox.vue";
import InputField from "../general/InputField.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";
import Spinner from "../general/Spinner.vue";
import { InformationCircleIcon } from '@heroicons/vue/20/solid'
import { Cog6ToothIcon, AdjustmentsHorizontalIcon, EllipsisVerticalIcon } from '@heroicons/vue/24/outline';
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import { PlusIcon, ExclamationTriangleIcon } from "@heroicons/vue/24/solid";
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import Toggle from "@generalComponents/Toggle.vue";


const props = defineProps({
    // items: Object,
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();

const form = reactive({
    // items: props.items,
    org_id: props.options.settings.orgId,
    connection_name: props.options.settings.suggested_connection_name,
    protocol: props.options.settings.mobile_app_conn_protocol,
    domain: props.options.model.domain_name,
    port: props.options.settings.connection_port,
    dont_verify_server_certificate: props.options.settings.dont_verify_server_certificate === "true",
    disable_srtp: props.options.settings.disable_srtp === "true", 
    proxy: props.options.settings.mobile_app_proxy,
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel', 'domain-selected']);


// Initialize activeTab with the currently active tab from props
const activeTab = ref(props.options.conn_navigation.find(item => item.slug)?.slug || props.options.conn_navigation[0].slug);


const submitForm = () => {
    // console.log(form);
    emits('submit', form); // Emit the event with the form data
}

const handleTemplateUpdate = (newSelectedItem) => {
    form.device_template = newSelectedItem.value;
}

const handleUpdateProtocolField = (selected) => {
    form.protocol = selected.value;
}

const handleExtensionUpdate = (newSelectedItem, index) => {
    form.lines[index].user_id = newSelectedItem.value;
    form.lines[index].display_name = newSelectedItem.value;
}

const handleDomainUpdate = (newSelectedItem) => {
    form.domain_uuid = newSelectedItem.value;
    form.device_profile_uuid = "NULL";
    form.extension = "NULL";
    if (newSelectedItem.value !== "NULL") {
        emits('domain-selected', newSelectedItem.value); // Emit 'domain-selected' event when the domain is updated
    }
}

// Function to determine placeholder based on the current value
function placeholderText(fieldName) {
    const fieldValue = form[fieldName];
    if (fieldValue === null) {
        return "Keep existing setting";
    } else if (fieldValue === "NULL") {
        return "Current settings will be cleared";
    } else {
        return fieldValue; // Use the actual value as the placeholder
    }
}

const handleKeyTypeUpdate = (newSelectedItem, index) => {
    const newValue = newSelectedItem.value === 'sharedline' ? 'true' : null;

    // Only update if the value is different
    if (form.lines[index].shared_line !== newValue) {
        form.lines[index].shared_line = newValue;
    }
};


const addNewLineKey = () => {
    // console.log(props.options);
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

const iconComponents = {
    'Cog6ToothIcon': Cog6ToothIcon,
    'AdjustmentsHorizontalIcon': AdjustmentsHorizontalIcon,
};

const setActiveTab = (tabSlug) => {
    activeTab.value = tabSlug;
};

const deleteLineKey = (index) => {
    form.lines.splice(index, 1);  // Remove the line key at the specified index
};


</script>