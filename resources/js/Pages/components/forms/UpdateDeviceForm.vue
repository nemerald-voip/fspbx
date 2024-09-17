<template>
    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
        <aside class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
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
        </aside>

        <form @submit.prevent="submitForm" class="sm:px-6 lg:col-span-9 lg:px-0">
            <div v-if="activeTab === 'settings'">
                <div class="bg-gray-100  px-4 py-6 grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                    <div class="sm:col-span-12">
                        <LabelInputRequired :target="'device_address'" :label="'MAC Address'" />
                        <div class="mt-2">
                            <InputField v-model="form.device_address" type="text" name="device_address"
                                placeholder="Enter MAC Address" :disabled="!page.props.auth.can.device_edit_address"
                                :error="errors?.device_address && errors.device_address.length > 0" />
                        </div>
                        <div v-if="errors?.device_address" class="mt-2 text-sm text-red-600">
                            {{ errors.device_address[0] }}
                        </div>
                    </div>


                    <div v-if="page.props.auth.can.device_edit_template" class="sm:col-span-12">
                        <LabelInputRequired :target="'template'" :label="'Device Template'" />
                        <div class="mt-2">
                            <ComboBox :options="options.templates" :selectedItem="form.device_template" :search="true"
                                :placeholder="'Choose template'" @update:model-value="handleTemplateUpdate"
                                :error="errors?.device_template && errors.device_template.length > 0" />
                        </div>
                        <!-- <p class="mt-3 text-sm leading-6 text-gray-600">Assign the extension to which the messages should be
                    forwarded.</p> -->
                        <div v-if="errors?.device_template" class="mt-2 text-sm text-red-600">
                            {{ errors.device_template[0] }}
                        </div>
                    </div>

                    <div class="sm:col-span-12">
                        <LabelInputOptional :target="'profile'" :label="'Device Profile'" />
                        <div class="mt-2">
                            <ComboBox :options="options.profiles" :selectedItem="form.device_profile_uuid" :search="true"
                                :placeholder="'Choose profile'" @update:model-value="handleProfileUpdate" />
                        </div>
                    </div>

                    <!-- 
                    <div v-if="page.props.auth.can.device_edit_line" class="sm:col-span-12">
                        <LabelInputOptional :target="'extension'" :label="'Assigned Extension'" />
                        <div class="mt-2">
                            <ComboBox :options="options.extensions" :selectedItem="form.extension" :search="true"
                                :placeholder="'Choose extension'" @update:model-value="handleExtensionUpdate" />
                        </div>
                    </div> -->

                    <div v-if="page.props.auth.can.domain_select && page.props.auth.can.device_edit_domain"
                        class="sm:col-span-12">
                        <LabelInputRequired :target="'domain'" :label="'Owned By (Company Name)'" />
                        <div class="mt-2">
                            <ComboBox :options="options.domains" :selectedItem="form.domain_uuid" :search="true"
                                :placeholder="'Choose company'" @update:model-value="handleDomainUpdate"
                                :error="errors?.domain_uuid && errors.domain_uuid.length > 0" />
                        </div>
                        <div v-if="errors?.domain_uuid" class="mt-2 text-sm text-red-600">
                            {{ errors.domain_uuid[0] }}
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'lines'">
                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-100 px-4 py-6 sm:p-6">
                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Line Keys</h3>
                            <p class="mt-1 text-sm text-gray-500">Assign functions to the line keys for this device.</p>
                        </div>

                        <div class="grid grid-cols-12 gap-6">

                            <template v-for="(row, index) in form.lines" :key="row.device_line_uuid">
                                <div class="pt-2 text-sm font-medium leading-6 text-gray-900">
                                    {{ index + 1 }}
                                </div>

                                <div class="col-span-3 text-sm font-medium leading-6 text-gray-900">
                                    <ComboBox :options="options.line_key_types" :selectedItem="row.line_type_id"
                                        :search="true" :placeholder="'Choose key type'"
                                        @update:model-value="(value) => handleKeyTypeUpdate(value, index)" />
                                </div>

                                <div class="col-span-4 text-sm font-medium leading-6 text-gray-900">
                                    <ComboBox :options="options.extensions" :selectedItem="row.user_id" :search="true"
                                        :placeholder="'Choose extension'"
                                        @update:model-value="(value) => handleExtensionUpdate(value, index)" />
                                </div>

                                <div class="col-span-3 text-sm font-medium leading-6 text-gray-900">
                                    <InputField v-model="row.display_name" type="text" name="ip_address"
                                        placeholder="Enter display name"
                                        :error="errors?.display_name && errors.display_name.length > 0" />
                                </div>

                                <div class="text-sm font-medium leading-6 text-gray-900">
                                    <!-- <EllipsisVerticalIcon @click="handleEditRequest(row.device_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-500 hover:bg-gray-200 hover:text-gray-900 active:bg-gray-300 active:duration-150 cursor-pointer" /> -->

                                    <Menu as="div" class="relative inline-block text-left">
                                        <div>
                                            <MenuButton
                                                class="flex items-center rounded-full bg-gray-100 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-100">
                                                <span class="sr-only">Open options</span>
                                                <EllipsisVerticalIcon
                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-500 hover:bg-gray-200 hover:text-gray-900 active:bg-gray-300 active:duration-150 cursor-pointer"
                                                    aria-hidden="true" />
                                            </MenuButton>
                                        </div>

                                        <transition enter-active-class="transition ease-out duration-100"
                                            enter-from-class="transform opacity-0 scale-95"
                                            enter-to-class="transform opacity-100 scale-100"
                                            leave-active-class="transition ease-in duration-75"
                                            leave-from-class="transform opacity-100 scale-100"
                                            leave-to-class="transform opacity-0 scale-95">
                                            <MenuItems
                                                class="absolute right-0 z-10 mt-2 w-36 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                                <div class="py-1">
                                                    <MenuItem v-slot="{ active }">
                                                    <a href="#" @click.prevent="showLineAdvSettings(index)"
                                                        :class="[active ? 'bg-gray-100 text-gray-900' : 'text-gray-700', 'block px-4 py-2 text-sm']">Advanced</a>
                                                    </MenuItem>
                                                    <MenuItem v-slot="{ active }">
                                                    <a href="#" @click.prevent="deleteLineKey(index)"
                                                        :class="[active ? 'bg-gray-100 text-gray-900' : 'text-gray-700', 'block px-4 py-2 text-sm']">Delete</a>
                                                    </MenuItem>

                                                </div>
                                            </MenuItems>
                                        </transition>
                                    </Menu>

                                </div>

                            </template>

                        </div>

                        <div
                            class="flex justify-center bg-gray-100 px-4 py-4 text-center text-sm font-medium text-indigo-500 hover:text-indigo-700 sm:rounded-b-lg">
                            <button href="#" @click.prevent="addNewLineKey" class="flex items-center gap-2">
                                <PlusIcon class="h-6 w-6 text-black-500 hover:text-black-900 active:h-8 active:w-8 " />
                                <span>
                                    Add new line key
                                </span>
                            </button>
                        </div>

                    </div>


                </div>
            </div>

            <div class="bg-gray-100 px-4 py-3 text-right sm:px-6">

                <button type="submit"
                    class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                    ref="saveButtonRef" :disabled="isSubmitting">
                    <Spinner :show="isSubmitting" />
                    Save
                </button>

            </div>

        </form>
    </div>

    <AddEditItemModal :show="isLineAdvSettingsModalShown" :header="'Edit SIP Settings'" @close="handleModalClose">
        <template #modal-body>
            <div class="bg-white px-4 py-6 sm:px-6 lg:px-8">
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

            </div>

        </template>

    </AddEditItemModal>
</template>

<script setup>
import { reactive, ref } from "vue";
import { usePage } from '@inertiajs/vue3';


import ComboBox from "../general/ComboBox.vue";
import InputField from "../general/InputField.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";
import Spinner from "../general/Spinner.vue";
import { PlusIcon } from "@heroicons/vue/24/solid";
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import AddEditItemModal from "../modal/AddEditItemModal.vue";
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import { Cog6ToothIcon, AdjustmentsHorizontalIcon, EllipsisVerticalIcon } from '@heroicons/vue/24/outline';


const props = defineProps({
    item: Object,
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();

const form = reactive({
    device_address: props.item.device_address,
    device_template: props.item.device_template,
    device_profile_uuid: props.item.device_profile_uuid,
    // extension: props.item.device_label,
    lines: props.options.lines,
    domain_uuid: props.item.domain_uuid,
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel', 'domain-selected']);

const isLineAdvSettingsModalShown = ref(false);

// Initialize activeTab with the currently active tab from props
const activeTab = ref(props.options.navigation.find(item => item.slug)?.slug || props.options.navigation[0].slug);

const submitForm = () => {
    emits('submit', form); // Emit the event with the form data
}

const handleTemplateUpdate = (newSelectedItem) => {
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


</script>