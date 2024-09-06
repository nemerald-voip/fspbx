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
                <div class="bg-gray-100 px-4 py-6 grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                    <div class="sm:col-span-12">
                        <div class="rounded-md bg-blue-50 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <InformationCircleIcon class="h-5 w-5 text-blue-400" aria-hidden="true" />
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700">Select a new option to apply to all items, use the
                                        clear
                                        button to unset current settings, or leave unchanged to keep existing values </p>

                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="sm:col-span-12">
                        <LabelInputRequired :target="'template'" :label="'Device Template'" />
                        <div class="mt-2">
                            <ComboBox :options="options.templates" :selectedItem="form.device_template" :search="true"
                                :showUndo="form.device_template === 'NULL'"
                                :placeholder="placeholderText('device_template')" @update:model-value="handleTemplateUpdate"
                                :error="errors?.device_template && errors.device_template.length > 0" />
                        </div>
                        <!-- <p class="mt-3 text-sm leading-6 text-gray-600">Select a new common template for all selected devices</p> -->
                        <div v-if="errors?.device_template" class="mt-2 text-sm text-red-600">
                            {{ errors.device_template[0] }}
                        </div>
                    </div>

                    <div class="sm:col-span-12">
                        <LabelInputOptional :target="'profile'" :label="'Device Profile'" />
                        <div class="mt-2">
                            <ComboBox :options="options.profiles" :selectedItem="form.device_profile_uuid" :search="true"
                                :showClear="true" :showUndo="form.device_profile_uuid === 'NULL'"
                                :placeholder="placeholderText('device_profile_uuid')"
                                @update:model-value="handleProfileUpdate" />
                        </div>
                        <!-- <p class="mt-3 text-sm leading-6 text-gray-600">Assign the extension to which the messages should be
                    forwarded.</p> -->
                    </div>

                    <div v-if="page.props.auth.can.domain_select && page.props.auth.can.device_edit_domain"
                        class="sm:col-span-12">
                        <LabelInputRequired :target="'domain'" :label="'Owned By (Company Name)'" />
                        <div class="mt-2">
                            <ComboBox :options="options.domains" :selectedItem="form.domain_uuid" :search="true"
                                :showUndo="form.domain_uuid === 'NULL'" :placeholder="placeholderText('domain_uuid')"
                                @update:model-value="handleDomainUpdate"
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
                            <div class="rounded-md bg-yellow-50 p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <ExclamationTriangleIcon class="h-5 w-5 text-yellow-400" aria-hidden="true" />
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-yellow-800">Attention needed</h3>
                                        <div class="mt-2 text-sm text-yellow-700">
                                            <p>If you make any changes here, all existing line keys will be replaced with
                                                your new selection.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                                                <EllipsisVerticalIcon class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-500 hover:bg-gray-200 hover:text-gray-900 active:bg-gray-300 active:duration-150 cursor-pointer" aria-hidden="true" />
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
    items: Object,
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();

const form = reactive({
    items: props.items,
    device_template: null,
    device_profile_uuid: null,
    extension: null,
    domain_uuid: null,
    lines: [],
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

const handleProfileUpdate = (newSelectedItem) => {
    form.device_profile_uuid = newSelectedItem.value
}

const handleExtensionUpdate = (newSelectedItem, index) => {
    form.lines[index].user_id = newSelectedItem.value;
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