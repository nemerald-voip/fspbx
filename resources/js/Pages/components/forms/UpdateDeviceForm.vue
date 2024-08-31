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
                    <ExclamationCircleIcon v-if="((errors?.voicemail_id || errors?.voicemail_password) && item.slug === 'settings') ||
                        (errors?.voicemail_alternate_greet_id && item.slug === 'advanced')"
                        class="ml-2 h-5 w-5 text-red-500" aria-hidden="true" />

                </a>
            </nav>
        </aside>

        <form @submit.prevent="submitForm" class="space-y-6 sm:px-6 lg:col-span-9 lg:px-0">
            <div v-if="activeTab === 'settings'">
                <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
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
                        <!-- <p class="mt-3 text-sm leading-6 text-gray-600">Assign the extension to which the messages should be
                    forwarded.</p> -->
                    </div>


                    <div v-if="page.props.auth.can.device_edit_line" class="sm:col-span-12">
                        <LabelInputOptional :target="'extension'" :label="'Assigned Extension'" />
                        <div class="mt-2">
                            <ComboBox :options="options.extensions" :selectedItem="form.extension" :search="true"
                                :placeholder="'Choose extension'" @update:model-value="handleExtensionUpdate" />
                        </div>
                        <!-- <p class="mt-3 text-sm leading-6 text-gray-600">Assign the extension to which the messages should be
                    forwarded.</p> -->
                    </div>

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
                    <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Line Keys</h3>
                            <p class="mt-1 text-sm text-gray-500">Assign functions to the line keys for this device.</p>
                        </div>

                        <div class="grid grid-cols-12 gap-6">

                            <template v-for="(row, index) in options.lines" :key="row.device_line_uuid">
                                <div class="pt-2 text-sm font-medium leading-6 text-gray-900">
                                    {{ index + 1 }}
                                </div>

                                <div class="col-span-5 text-sm font-medium leading-6 text-gray-900">
                                    <ComboBox :options="options.line_key_types" :selectedItem="form.extension"
                                        :search="true" :placeholder="'Choose key type'"
                                        @update:model-value="handleExtensionUpdate" />
                                </div>

                                <div class="col-span-5 text-sm font-medium leading-6 text-gray-900">
                                    <ComboBox :options="options.extensions" :selectedItem="form.extension" :search="true"
                                        :placeholder="'Choose extension'" @update:model-value="handleExtensionUpdate" />
                                </div>

                                <div class="text-sm font-medium leading-6 text-gray-900">
                                    <EllipsisVerticalIcon @click="handleEditRequest(row.device_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-500 hover:bg-gray-200 hover:text-gray-900 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                </div>
                            </template>

                        </div>
                    </div>

                    <div class="border-t mt-4 sm:mt-4 ">
                        <div class="mt-4 sm:mt-4 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                            <button type="submit"
                                class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                                ref="saveButtonRef" :disabled="isSubmitting">
                                <Spinner :show="isSubmitting" />
                                Save
                            </button>
                            <button type="button"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                                @click="emits('cancel')" ref="cancelButtonRef">Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>

<script setup>
import { reactive, ref } from "vue";
import { usePage } from '@inertiajs/vue3';


import SelectBox from "../general/SelectBox.vue";
import ComboBox from "../general/ComboBox.vue";
import InputField from "../general/InputField.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";
import Spinner from "../general/Spinner.vue";
import DataTable from "@generalComponents/DataTable.vue";
import TableColumnHeader from "@generalComponents/TableColumnHeader.vue";
import TableField from "@generalComponents/TableField.vue";
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
    extension: props.item.device_label,
    domain_uuid: props.item.domain_uuid,
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
    form.device_template = newSelectedItem.value
}

const handleProfileUpdate = (newSelectedItem) => {
    form.device_profile_uuid = newSelectedItem.value
}

const handleExtensionUpdate = (newSelectedItem) => {
    form.extension = newSelectedItem.value
}

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

</script>