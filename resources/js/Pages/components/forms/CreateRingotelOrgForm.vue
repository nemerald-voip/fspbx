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
                        (errors?.voicemail_alternate_greet_id && item.slug === 'advanced')" class="ml-2 h-5 w-5 text-red-500"
                        aria-hidden="true" />

                </a>
            </nav>
        </aside>

        <form @submit.prevent="submitForm" class="space-y-6 sm:px-6 lg:col-span-9 lg:px-0">
            <div v-if="activeTab === 'organization'">
                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                        <div class="flex justify-between items-center">
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Organization Details</h3>

                            <Toggle label="Status" v-model="form.voicemail_enabled" />

                            <!-- <p class="mt-1 text-sm text-gray-500"></p> -->
                        </div>

                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-3">
                                <LabelInputRequired target="organization_name" label="Organization Name" class="truncate" />
                                <InputField v-model="form.organization_name" type="text" name="organization_name" id="organization_name"
                                    class="mt-2" :error="!!errors?.organization_name" />
                                <div v-if="errors?.organization_name" class="mt-2 text-xs text-red-600">
                                    {{ errors.organization_name[0] }}
                                </div>
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <LabelInputRequired target="organization_domain" label="Unique Organization Domain" class="truncate" />
                                <InputField v-model="form.organization_domain" type="text" name="organization_domain"
                                    id="organization_domain" class="mt-2" :error="!!errors?.organization_domain" />
                                <div v-if="errors?.organization_domain" class="mt-2 text-xs text-red-600">
                                    {{ errors.organization_domain[0] }}
                                </div>
                            </div>

                            <div 
                                class="col-span-6 sm:col-span-3">
                                <LabelInputOptional label="Region" class="truncate mb-1" />

                                <ComboBox :options="options.regions" :search="true" 
                                    :placeholder="'Select region'"
                                    @update:model-value="handleUpdateCopyToField" />

                            </div>

                            <div class="divide-y divide-gray-200 col-span-6">

                                <Toggle 
                                    label="Secure User Credentials"
                                    description="When enabled, users will receive a one-time link to access their app password instead of plain text."
                                    v-model="form.voicemail_transcription_enabled" customClass="py-4" />

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


            <div v-if="activeTab === 'advanced'" action="#" method="POST">
                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Advanced</h3>
                            <p class="mt-1 text-sm text-gray-500">Set advanced settings for this voicemail
                            </p>
                        </div>

                        <div class="divide-y divide-gray-200 col-span-6">

                            <Toggle label="Play Voicemail Tutorial"
                                description="Provide user with a guided tutorial when accessing voicemail for the first time."
                                v-model="form.voicemail_tutorial" customClass="py-4" />

                            <Toggle v-if="options.permissions.manage_voicemail_recording_instructions"
                                label="Play Recording Instructions" description='Play a prompt instructing callers to "Record your message after the tone. Stop
                                        speaking to end the recording."'
                                v-model="form.voicemail_play_recording_instructions" customClass="py-4" />

                        </div>

                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-3 sm:col-span-2">
                                <div class="flex items-center gap-1">
                                    <LabelInputOptional target="voicemail_alternate_greet_id" label="Announce Voicemail
                                        Extension as" />

                                    <Popover>
                                        <template v-slot:popover-button>
                                            <InformationCircleIcon class="h-5 w-5 text-blue-500" />
                                        </template>
                                        <template v-slot:popover-panel>
                                            <div>The parameter allows you to override the voicemail extension number
                                                spoken
                                                by the system in the voicemail greeting. This controls system greetings
                                                that
                                                read back an extension number, not user recorded greetings.</div>
                                        </template>
                                    </Popover>
                                </div>

                                <InputField v-model="form.voicemail_alternate_greet_id" type="text"
                                    name="voicemail_alternate_greet_id" :error="!!errors?.voicemail_alternate_greet_id"
                                    id="voicemail_alternate_greet_id" class="mt-2" />

                                <div v-if="errors?.voicemail_alternate_greet_id" class="mt-2 text-xs text-red-600">
                                    {{ errors.voicemail_alternate_greet_id[0] }}
                                </div>

                            </div>

                        </div>


                    </div>
                    <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
                        <button type="submit"
                            class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Save</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>

<script setup>
import { reactive, ref } from "vue";
import { usePage } from '@inertiajs/vue3';


import ComboBox from "../general/ComboBox.vue";
import InputField from "../general/InputField.vue";
import InputFieldWithIcon from "@generalComponents/InputFieldWithIcon.vue";
import Popover from "@generalComponents/Popover.vue";
import Textarea from "@generalComponents/Textarea.vue";
import SyncAltIcon from "@icons/SyncAltIcon.vue";
import Toggle from "@generalComponents/Toggle.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";
import Spinner from "@generalComponents/Spinner.vue";
import { Switch, SwitchDescription, SwitchGroup, SwitchLabel } from '@headlessui/vue'
import { InformationCircleIcon } from "@heroicons/vue/24/outline";
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import { BuildingOfficeIcon } from '@heroicons/vue/24/outline';


const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});


// Initialize activeTab with the currently active tab from props
const activeTab = ref(props.options.navigation.find(item => item.slug)?.slug || props.options.navigation[0].slug);

const setActiveTab = (tabSlug) => {
    activeTab.value = tabSlug;
};

const showPassword = ref(false);

const togglePasswordVisibility = () => {
  showPassword.value = !showPassword.value;
  const passwordInput = document.getElementById("voicemail_password");
  if (showPassword.value) {
    passwordInput.style.webkitTextSecurity = "none"; // Show text
  } else {
    passwordInput.style.webkitTextSecurity = "disc"; // Mask text
  }
};

// Map icon names to their respective components
const iconComponents = {
    'SyncAltIcon': SyncAltIcon,
    'BuildingOfficeIcon': BuildingOfficeIcon,
};


const page = usePage();

const form = reactive({
    voicemail_enabled: true,
    organization_name: props.options.model.domain_description,
    // voicemail_id: props.options.voicemail.voicemail_id,
    // voicemail_password: props.options.voicemail.voicemail_password,
    // voicemail_mail_to: null,
    // voicemail_description: null,
    // voicemail_transcription_enabled: props.options.voicemail.voicemail_transcription_enabled === "true",
    // voicemail_email_attachment: props.options.voicemail.voicemail_file === "attach",
    // voicemail_delete: props.options.voicemail.voicemail_local_after_email === "false",
    // voicemail_tutorial: props.options.voicemail.voicemail_tutorial === "true",
    // voicemail_play_recording_instructions: props.options.voicemail.voicemail_recording_instructions === "true",
    // voicemail_copies: null,
    // voicemail_alternate_greet_id: null,
    // voicemail_enabled: props.options.voicemail.voicemail_enabled === "true",
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel']);

const submitForm = () => {
    emits('submit', form); // Emit the event with the form data
}


</script>
