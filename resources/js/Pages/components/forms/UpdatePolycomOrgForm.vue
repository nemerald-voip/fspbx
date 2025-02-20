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
                    <ExclamationCircleIcon v-if="((errors?.organization_name) && item.slug === 'organization') ||
                        (errors?.voicemail_alternate_greet_id && item.slug === 'provisioning')"
                                           class="ml-2 h-5 w-5 text-red-500" aria-hidden="true" />

                </a>
            </nav>
        </aside>

        <div class="space-y-6 sm:px-6 lg:col-span-9 lg:px-0">
            <form @submit.prevent="submitForm">
                <div v-if="activeTab === 'organization'">
                    <div class="shadow sm:rounded-md">
                        <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                            <div class="flex justify-between items-center">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">Organization Details</h3>
                            </div>

                            <div class="grid grid-cols-1 gap-6">
                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired target="organization_name" label="Organization Name"
                                                        class="truncate" />
                                    <InputField v-model="form.organization_name" type="text" name="organization_name"
                                                id="organization_name" class="mt-2" :error="!!errors?.organization_name" />
                                    <div v-if="errors?.organization_name" class="mt-2 text-xs text-red-600">
                                        {{ errors.organization_name[0] }}
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
                            <button type="submit"
                                    class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                                    ref="saveButtonRef" :disabled="isSubmitting">
                                <Spinner :show="isSubmitting" />
                                Save
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'provisioning'">
                    <div class="shadow sm:rounded-md">
                        <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                            <div class="r">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">Provisioning</h3>
                                <p class="mt-3 text-sm leading-6 text-gray-600">Configure a provisioning server.</p>
                            </div>

                            <div class="grid grid-cols-1 gap-6">
                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputOptional target="version" label="Address"
                                                        class="truncate" />
                                    <InputField v-model="form.provisioning_server_address" type="text" name="provisioning_address"
                                                id="provisioning_address" class="mt-2" :error="!!errors?.provisioning_server_address" />
                                    <div v-if="errors?.provisioning_server_address" class="mt-2 text-xs text-red-600">
                                        {{ errors.provisioning_server_address[0] }}
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-3 sm:col-span-3">
                                    <LabelInputOptional target="version" label="Username"
                                                        class="truncate" />
                                    <InputField v-model="form.provisioning_server_username" type="text" name="provisioning_server_username"
                                                id="provisioning_server_username" class="mt-2" :error="!!errors?.provisioning_server_username" />
                                    <div v-if="errors?.provisioning_server_username" class="mt-2 text-xs text-red-600">
                                        {{ errors.provisioning_server_username[0] }}
                                    </div>
                                </div>
                                <div class="col-span-3 sm:col-span-3">
                                    <LabelInputOptional target="version" label="Password"
                                                        class="truncate" />
                                    <InputField v-model="form.provisioning_server_password" type="text" name="provisioning_server_password"
                                                id="provisioning_server_password" class="mt-2" :error="!!errors?.provisioning_server_password" />
                                    <div v-if="errors?.provisioning_server_password" class="mt-2 text-xs text-red-600">
                                        {{ errors.provisioning_server_password[0] }}
                                    </div>
                                </div>
                            </div>

                            <div class="divide-y divide-gray-200 col-span-6">
                                <Toggle label="Polling"
                                        description="Enable provisioning server polling."
                                        v-model="form.provisioning_polling" customClass="py-4" />
                            </div>

                            <div class="divide-y divide-gray-200 col-span-6">
                                <Toggle label="Quick Setup"
                                        description="Enable the quick setup option for phones."
                                        v-model="form.provisioning_quick_setup" customClass="py-4" />
                            </div>
                            <div class="w-full border-t border-gray-300" />
                            <div class="">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">DHCP</h3>
                                <p class="mt-3 text-sm leading-6 text-gray-600">Configure DHCP options to determine boot behavior.</p>
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <LabelInputOptional label="Boot Server Option" class="truncate mb-1" />
                                <ComboBox :options="options.dhcp_boot_server_option_list" :search="true" :placeholder="'Select'"
                                          :error="errors?.dhcp_boot_server_option && errors.dhcp_boot_server_option.length > 0" :selectedItem="form.dhcp_boot_server_option" :allowEmpty="true"
                                          @update:model-value="handleUpdateBootServerOptionField" />
                                <div v-if="errors?.dhcp_boot_server_option" class="mt-2 text-xs text-red-600">
                                    {{ errors.dhcp_boot_server_option[0] }}
                                </div>
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <LabelInputOptional label="Option 60 Type" class="truncate mb-1" />
                                <ComboBox :options="options.dhcp_option_60_type_list" :search="true" :placeholder="'Select'"
                                          :error="errors?.dhcp_option_60_type && errors.dhcp_option_60_type.length > 0" :selectedItem="form.dhcp_option_60_type" :allowEmpty="true"
                                          @update:model-value="handleUpdateOption60TypeField" />
                                <div v-if="errors?.dhcp_option_60_type" class="mt-2 text-xs text-red-600">
                                    {{ errors.dhcp_option_60_type[0] }}
                                </div>
                            </div>
                            <div class="w-full border-t border-gray-300" />
                            <div class="justify-between items-center">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">Software</h3>
                                <p class="mt-3 text-sm leading-6 text-gray-600">Configure the software that will be loaded during provisioning.</p>
                            </div>

                            <div class="grid grid-cols-1 gap-6">
                                <div class="col-span-6 sm:col-span-3">
                                    <InputField v-model="form.software_version" type="text" name="software_version"
                                                id="software_version" class="mt-2" :error="!!errors?.software_version" />
                                    <div v-if="errors?.software_version" class="mt-2 text-xs text-red-600">
                                        {{ errors.software_version[0] }}
                                    </div>
                                </div>
                            </div>
                            <div class="w-full border-t border-gray-300" />
                            <div class="justify-between items-center">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">Localization</h3>
                                <p class="mt-3 text-sm leading-6 text-gray-600">Specify the operating locale for this profile.</p>
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <LabelInputOptional label="Localization" class="truncate mb-1" />
                                <ComboBox :options="options.locales" :search="true" :placeholder="'Select'"
                                          :error="errors?.localization_language && errors.localization_language.length > 0" :selectedItem="form.localization_language" :allowEmpty="true"
                                          @update:model-value="handleUpdateLocalizationLanguageField" />
                                <div v-if="errors?.localization_language" class="mt-2 text-xs text-red-600">
                                    {{ errors.localization_language[0] }}
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
                            <button type="submit"
                                    class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                                    ref="saveButtonRef" :disabled="isSubmitting">
                                <Spinner :show="isSubmitting" />
                                Save
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>


<script setup>
import { reactive, ref, watch } from "vue";
import { usePage } from '@inertiajs/vue3';


import ComboBox from "../general/ComboBox.vue";
import InputField from "../general/InputField.vue";
import SyncAltIcon from "@icons/SyncAltIcon.vue";
import Toggle from "@generalComponents/Toggle.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";
import Spinner from "@generalComponents/Spinner.vue";
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import { BuildingOfficeIcon } from '@heroicons/vue/24/outline';
import LabelInputOptional from "../general/LabelInputOptional.vue";

const loadingModal = ref(false);

const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    activeTab: String,
    errors: Object,
});

// Initialize activeTab with the currently active tab from props
const activeTab = ref(props.activeTab || props.options.navigation[0].slug);

// Watch for changes in the activeTab prop and update the local activeTab
watch(
    () => props.activeTab,
    (newValue) => {
        activeTab.value = newValue || props.options.navigation[0].slug;
    }
);

const setActiveTab = (tabSlug) => {
    activeTab.value = tabSlug;
};

// Map icon names to their respective components
const iconComponents = {
    'SyncAltIcon': SyncAltIcon,
    'BuildingOfficeIcon': BuildingOfficeIcon,
};

const page = usePage();

const form = reactive({
    organization_id: props.options.organization.id,
    organization_name: props.options.organization.name,
    provisioning_server_address: props.options.organization.template.provisioning.server.address,
    provisioning_server_username: props.options.organization.template.provisioning.server.username,
    provisioning_server_password: props.options.organization.template.provisioning.server.password,
    dhcp_boot_server_option: props.options.organization.template.dhcp.bootServerOption,
    dhcp_option_60_type: props.options.organization.template.dhcp.option60Type,
    software_version: props.options.organization.template.software.version,
    localization_language: props.options.organization.template.localization.language,
    provisioning_polling: props.options.organization.template.provisioning.polling,
    provisioning_quick_setup: props.options.organization.template.provisioning.quickSetup,
    domain_uuid: props.options.model.domain_uuid,
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel', 'error', 'success', 'clear-errors']);

const submitForm = () => {
    emits('submit', form); // Emit the event with the form data
}

const handleUpdateLocalizationLanguageField = (selected) => {
    form.localization_language = selected.value;
}

const handleUpdateOption60TypeField = (selected) => {
    form.dhcp_option_60_type = selected.value;
}

const handleUpdateBootServerOptionField = (selected) => {
    form.dhcp_boot_server_option = selected.value;
}

</script>
