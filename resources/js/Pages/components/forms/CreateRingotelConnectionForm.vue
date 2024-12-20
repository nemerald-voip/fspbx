<template>
    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
        <!-- <aside class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
            <nav class="space-y-1">
                <a v-for="item in options.navigation" :key="item.name" href="#"
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
        </aside> -->

        <form @submit.prevent="submitForm" class="sm:px-6 lg:col-span-12 lg:px-0">
                    

                    <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired target="connection_name" label="Connection Name"
                                        class="truncate mb-1" />
                                    <InputField v-model="form.connection_name" type="text" name="connection_name"
                                        id="connection_name" class="mt-1" :error="!!errors?.organization_name" :placeholder="'Enter connection name'"/>
                                    <div v-if="errors?.connection_name" class="mt-2 text-xs text-red-600">
                                        {{ errors.connection_name[0] }}
                                    </div>
                                </div>



                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired label="Protocol" class="truncate mb-1" />
                                    <ComboBox :options="options.protocols" :search="true" :placeholder="'Select protocol'"
                                        :error="errors?.protocol && errors.protocol.length > 0" :selectedItem="options.default_protocol"
                                        @update:model-value="handleUpdateProtocolField" />
                                    <div v-if="errors?.protocol" class="mt-2 text-xs text-red-600">
                                        {{ errors.protocol[0] }}
                                    </div>

                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired target="domain" label="Domain or IP Address"
                                        class="truncate mb-1" />
                                    <InputField v-model="form.domain" type="text" name="domain"
                                        id="domain" class="mt-1" :error="!!errors?.domain" />
                                    <div v-if="errors?.domain" class="mt-2 text-xs text-red-600">
                                        {{ errors.domain[0] }}
                                    </div>
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired target="domain" label="Port"
                                        class="truncate mb-1" />
                                    <InputField v-model="form.port" type="text" name="port"
                                        id="port" class="mt-1" :error="!!errors?.port" />
                                    <div v-if="errors?.port" class="mt-2 text-xs text-red-600">
                                        {{ errors.port[0] }}
                                    </div>
                                </div>



                                <!-- <div class="divide-y divide-gray-200 col-span-6">

                                    <Toggle label="Secure User Credentials"
                                        description="When enabled, users will receive a one-time link to access their app password instead of plain text."
                                        v-model="form.dont_send_user_credentials" customClass="py-4" />

                                </div> -->



                            </div>


            <!-- <div class="px-4 py-3 text-right sm:px-6">
                <button type="submit"
                    class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                    ref="saveButtonRef" :disabled="isSubmitting">
                    <Spinner :show="isSubmitting" />
                    Save
                </button>
            </div> -->
            <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                <button type="sumbit" :disabled="isSubmitting" class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto" @click="open = false">
                    <Spinner :show="isSubmitting" />
                    Save
                </button>
                <button type="button" @click="emits('cancel')" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Cancel</button>
              </div>
        </form>
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


const props = defineProps({
    // items: Object,
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();

const form = reactive({
    // items: props.items,
    org_id: props.options.orgId,
    connection_name: null,
    protocol: null,
    domain: props.options.model.domain_name,
    port: props.options.default_port,
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel', 'domain-selected']);


// Initialize activeTab with the currently active tab from props
const activeTab = ref(props.options.navigation.find(item => item.slug)?.slug || props.options.navigation[0].slug);


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