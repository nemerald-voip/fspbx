<template>
    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
        <aside class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
            <nav class="space-y-1">
                <a v-for="item in localOptions.navigation" :key="item.name" href="#"
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
                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                        <div class="flex justify-between items-center">
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Settings</h3>

                            <Toggle label="Status" v-model="form.voicemail_enabled" />

                            <!-- <p class="mt-1 text-sm text-gray-500"></p> -->
                        </div>

                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6" v-if="localOptions.permissions.is_superadmin">
                                <div class="block text-sm font-medium leading-6 text-gray-900">
                                    Unique ID
                                </div>
                                <div class="mt-1 flex items-center group">
                                    <span class="text-sm text-gray-900 select-all font-normal">
                                        {{ options.voicemail.voicemail_uuid }}
                                    </span>
                                    <button type="button"
                                        @click="handleCopyToClipboard(options.voicemail.voicemail_uuid)"
                                        class="ml-2 p-1 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                        title="Copy to clipboard">
                                        <!-- Small Copy Icon -->
                                        <ClipboardDocumentIcon
                                            class="h-4 w-4 text-gray-500 hover:text-gray-900  cursor-pointer" />
                                    </button>
                                </div>
                            </div>
                       <div class="col-span-3 sm:col-span-2">
                                <LabelInputRequired target="voicemail_id" label="Voicemail Extension"
                                    class="truncate" />
                                <InputField v-model="form.voicemail_id" type="text" name="voicemail_id"
                                    id="voicemail_id" class="mt-2" :error="!!errors?.voicemail_id" />
                                <div v-if="errors?.voicemail_id" class="mt-2 text-xs text-red-600">
                                    {{ errors.voicemail_id[0] }}
                                </div>
                            </div>

                            <div class="col-span-3 sm:col-span-2">
                                <LabelInputOptional target="voicemail_password" label="Password" class="truncate" />
                                <InputFieldWithIcon v-model="form.voicemail_password" id="voicemail_password"
                                    name="voicemail_password" type="text" autocomplete="shut-up-google"
                                    :error="!!errors?.voicemail_password" class="password-field">
                                    <template #icon>
                                        <VisibilityIcon @click="togglePasswordVisibility"
                                            class="h-8 w-8 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                            aria-hidden="true" />
                                    </template>
                                </InputFieldWithIcon>
                                <div v-if="errors?.voicemail_password" class="mt-2 text-xs text-red-600">
                                    {{ errors.voicemail_password[0] }}
                                </div>
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <LabelInputOptional target="voicemail_mail_to" label="Email address" class="truncate" />
                                <InputField v-model="form.voicemail_mail_to" type="text" name="voicemail_mail_to"
                                    id="voicemail_mail_to" class="mt-2" :error="!!errors?.voicemail_mail_to" />
                                <div v-if="errors?.voicemail_mail_to" class="mt-2 text-xs text-red-600">
                                    {{ errors.voicemail_mail_to[0] }}
                                </div>
                            </div>

                            <div class="col-span-6">
                                <LabelInputOptional target="voicemail_description" label="Description"
                                    class="truncate" />
                                <div class="mt-2">
                                    <Textarea v-model="form.voicemail_description" id="voicemail_description"
                                        name="voicemail_description" rows="2"
                                        :error="!!errors?.voicemail_description" />
                                </div>
                                <div v-if="errors?.voicemail_description" class="mt-2 text-xs text-red-600">
                                    {{ errors.voicemail_description[0] }}
                                </div>
                            </div>

                            <div class="divide-y divide-gray-200 col-span-6">

                                <Toggle v-if="localOptions.permissions.manage_voicemail_transcription"
                                    label="Voicemail Transcription"
                                    description="Convert voicemail messages to text using AI-powered transcription."
                                    v-model="form.voicemail_transcription_enabled" customClass="py-4" />

                                <Toggle label="Attach File to Email Notifications"
                                    description="Attach voicemail recording file to the email notification."
                                    v-model="form.voicemail_email_attachment" customClass="py-4" />

                                <Toggle v-if="localOptions.permissions.manage_voicemail_auto_delete"
                                    label="Automatically Delete Voicemail After Email"
                                    description="Remove voicemail from the cloud once the email is sent."
                                    v-model="form.voicemail_delete" customClass="py-4" />

                            </div>

                            <div v-if="localOptions.permissions.manage_voicemail_copies"
                                class="col-span-4 text-sm font-medium leading-6 text-gray-900">
                                <LabelInputOptional label="Copy Voicemail to
                                    Other Extensions" class="truncate mb-1" />

                                <ComboBox :options="localOptions.all_voicemails" :search="true" multiple
                                    :placeholder="'Enter name or extension'"
                                    :selectedItem="localOptions.voicemail_copies"
                                    @update:model-value="handleUpdateCopyToField" />
                                <div class="mt-1 text-sm text-gray-500">
                                    Automatically send a copy of the voicemail to selected additional extensions.

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


            <div v-if="activeTab === 'greetings'">
                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Greetings</h3>
                            <p class="mt-1 text-sm text-gray-500">Customize the message that callers hear when they
                                reach
                                your voicemail.</p>
                        </div>

                        <div class="grid grid-cols-6 gap-6">

                            <div class="col-span-6 sm:col-span-3 text-sm font-medium leading-6 text-gray-900">
                                <LabelInputOptional label="Select greeting" class="truncate mb-1" />


                                <ComboBox :options="localOptions.greetings" :search="false"
                                    :placeholder="'Select greeting'" :selectedItem="form.greeting_id"
                                    @update:model-value="handleUpdateGreetingField" />



                                <!-- <div class="mt-1 text-sm text-gray-500">
                                    Customize the message that callers hear when they reach your voicemail.
                                </div> -->


                            </div>

                            <div class="content-end col-span-2 pb-1 text-sm font-medium leading-6 text-gray-900">
                                <div class="flex items-center whitespace-nowrap gap-2">
                                    <!-- Play Button -->
                                    <PlayCircleIcon v-if="form.greeting_id > 0 && !isAudioPlaying" @click="playGreeting"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                    <!-- Pause Button -->
                                    <PauseCircleIcon v-if="form.greeting_id > 0 && isAudioPlaying"
                                        @click="pauseGreeting"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />

                                    <CloudArrowDownIcon v-if="form.greeting_id > 0 && !isDownloading"
                                        @click="downloadGreeting"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                    <Spinner :show="isDownloading"
                                        class="h-8 w-8 ml-0 mr-0 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                    <!-- Delete Button -->
                                    <TrashIcon v-if="form.greeting_id > 0" @click="deleteGreeting"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />

                                    <PlusIcon @click="toggleGreetingForm"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                </div>

                            </div>

                            <div v-if="greetingDescription" class="col-span-6">
                                <p class="text-xs text-gray-500 italic"
                                    v-html="`&quot;${decodedGreetingDescription}&quot;`"></p>

                            </div>


                            <!-- Recorded Name -->
                            <div class="mt-1 col-span-6 text-sm font-medium leading-6 text-gray-900">
                                <div class="flex items-center  whitespace-nowrap space-x-2">
                                    <p>Recorded Name:</p>
                                    <Badge v-if="localOptions.recorded_name == 'Custom recording'"
                                        :text="localOptions.recorded_name" backgroundColor="bg-green-50"
                                        textColor="text-green-700" ringColor="ring-green-600/20" />

                                    <Badge v-if="localOptions.recorded_name == 'System Default'"
                                        :text="localOptions.recorded_name" backgroundColor="bg-blue-50"
                                        textColor="text-blue-700" ringColor="ring-blue-600/20" />

                                    <!-- Play Button -->
                                    <PlayCircleIcon
                                        v-if="!isNameAudioPlaying && localOptions.recorded_name === 'Custom recording'"
                                        @click="playRecordedName"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                    <!-- Pause Button -->
                                    <PauseCircleIcon
                                        v-if="isNameAudioPlaying && localOptions.recorded_name === 'Custom recording'"
                                        @click="pauseRecordedName"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />

                                    <!-- Download Button -->
                                    <CloudArrowDownIcon
                                        v-if="localOptions.recorded_name === 'Custom recording' && !isNameDownloading"
                                        @click="downloadRecordedName"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                    <!-- Spinner -->
                                    <Spinner :show="isNameDownloading"
                                        class="h-8 w-8 ml-0 mr-0 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                    <!-- Delete Button -->
                                    <TrashIcon v-if="localOptions.recorded_name === 'Custom recording'"
                                        @click="deleteRecordedName"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />

                                    <PlusIcon @click="toggleNameForm"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                </div>

                            </div>




                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
                        <button type="submit"
                            class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Save</button>
                    </div>
                </div>

                <!-- New Greeting Form -->
                <NewGreetingForm v-if="showGreetingForm" :title="'New Voicemail Greeting'" :voices="localOptions.voices"
                    :default_voice="localOptions.default_voice" :speeds="localOptions.speeds"
                    :phone_call_instructions="localOptions.phone_call_instructions"
                    :sample_message="localOptions.sample_message" :routes="getRoutesForGreetingForm"
                    @greeting-saved="handleGreetingSaved" />

                <!-- Recorded Name Form -->
                <NewGreetingForm v-if="showNameForm" :title="'New Recorded Name'" :voices="localOptions.voices"
                    :default_voice="localOptions.default_voice" :speeds="localOptions.speeds"
                    :phone_call_instructions="localOptions.phone_call_instructions_for_name" sample_message="John Dow"
                    :routes="getRoutesForNameForm" @greeting-saved="handleNameSaved" />
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

                            <Toggle v-if="localOptions.permissions.manage_voicemail_recording_instructions"
                                label="Play Recording Instructions" description='Play a prompt instructing callers to "Record your message after the tone. Stop
                                        speaking to end the recording."'
                                v-model="form.voicemail_play_recording_instructions" customClass="py-4" />

                        </div>

                        <div class="col-span-6 sm:col-span-3"  v-if="localOptions.permissions.manage_voicemail_mobile_notifications" >
                            <LabelInputOptional target="voicemail_sms_to" label="Mobile Number to Receive Voicemail Notifications" class="truncate" />
                            <InputField v-model="form.voicemail_sms_to" type="text" name="voicemail_sms_to"
                                id="voicemail_sms_to" class="mt-2" :error="!!errors?.voicemail_sms_to" />
                            <div v-if="errors?.voicemail_sms_to" class="mt-2 text-xs text-red-600">
                                {{ errors.voicemail_sms_to[0] }}
                            </div>
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

    <DeleteConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmDeleteAction" />

    <DeleteConfirmationModal :show="confirmationModalTriggerForName" @close="confirmationModalTriggerforName = false"
        @confirm="confirmDeleteNameAction" />
</template>

<script setup>
import { reactive, ref, watch, computed } from "vue";
import { usePage } from '@inertiajs/vue3';

import ComboBox from "../general/ComboBox.vue";
import InputField from "../general/InputField.vue";
import InputFieldWithIcon from "@generalComponents/InputFieldWithIcon.vue";
import Popover from "@generalComponents/Popover.vue";
import Textarea from "@generalComponents/Textarea.vue";
import VisibilityIcon from "@icons/VisibilityIcon.vue";
import Toggle from "@generalComponents/Toggle.vue";
import DeleteConfirmationModal from "../modal/DeleteConfirmationModal.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";
import Badge from "@generalComponents/Badge.vue";
import Spinner from "@generalComponents/Spinner.vue";
import { InformationCircleIcon } from "@heroicons/vue/24/outline";
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import { PlusIcon, TrashIcon } from '@heroicons/vue/20/solid'
import { PlayCircleIcon, CloudArrowDownIcon, PauseCircleIcon } from '@heroicons/vue/24/solid';
import NewGreetingForm from './NewGreetingForm.vue';
import { Cog6ToothIcon, MusicalNoteIcon, AdjustmentsHorizontalIcon } from '@heroicons/vue/24/outline';
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";


const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});


// Initialize activeTab with the currently active tab from props
const activeTab = ref(props.options.navigation.find(item => item.slug)?.slug || props.options.navigation[0].slug);
const showGreetingForm = ref(false);
const showNameForm = ref(false);
const selectedGreetingMethod = ref('text-to-speech');
const isDownloading = ref(false);
const confirmationModalTrigger = ref(false);
const confirmationModalTriggerForName = ref(false);

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emits('success', 'success', { message: ['Copied to clipboard.'] });
    }).catch((error) => {
        // Handle the error case
        emits('error', { response: { data: { errors: { request: ['Failed to copy to clipboard.'] } } } });
    });
}

const setActiveTab = (tabSlug) => {
    activeTab.value = tabSlug;
};

const showPassword = ref(false);

const toggleGreetingForm = () => {
    showGreetingForm.value = !showGreetingForm.value;
    showNameForm.value = false;
};

const toggleNameForm = () => {
    showGreetingForm.value = false;
    showNameForm.value = !showNameForm.value;
};

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
    'Cog6ToothIcon': Cog6ToothIcon,
    'MusicalNoteIcon': MusicalNoteIcon,
    'AdjustmentsHorizontalIcon': AdjustmentsHorizontalIcon,
};


const page = usePage();

// Make a local reactive copy of options to manipulate in this component
const localOptions = reactive({ ...props.options });

// Watch for changes in props.options and update localOptions accordingly
watch(() => props.options, (newOptions) => {
    Object.assign(localOptions, newOptions);
});

const form = reactive({
    voicemail_enabled: props.options.voicemail.voicemail_enabled,
    voicemail_id: props.options.voicemail.voicemail_id,
    voicemail_password: props.options.voicemail.voicemail_password,
    voicemail_mail_to: props.options.voicemail.voicemail_mail_to,
    voicemail_sms_to: props.options.voicemail.voicemail_sms_to,
    voicemail_description: props.options.voicemail.voicemail_description,
    voicemail_transcription_enabled: props.options.voicemail.voicemail_transcription_enabled === "true",
    voicemail_email_attachment: props.options.voicemail.voicemail_file === "attach",
    voicemail_delete: props.options.voicemail.voicemail_local_after_email === "false",
    voicemail_tutorial: props.options.voicemail.voicemail_tutorial === "true",
    voicemail_play_recording_instructions: props.options.voicemail.voicemail_recording_instructions === "true",
    voicemail_copies: props.options.voicemail_copies,
    voicemail_alternate_greet_id: props.options.voicemail.voicemail_alternate_greet_id,
    voicemail_enabled: props.options.voicemail.voicemail_enabled === "true",
    update_route: props.options.routes.update_route,
    greeting_id: props.options.voicemail.greeting_id,
    _token: page.props.csrf_token,
})

const greetingDescription = computed(() => {
    // Find the greeting object in the array whose value matches the selected greeting
    const selected = localOptions.greetings.find(
        (greeting) => greeting.value === form.greeting_id
    );
    return selected ? selected.description : null;
});

const decodedGreetingDescription = computed(() => {
    // Create a temporary DOM element (textarea works well for this)
    const txt = document.createElement("textarea");
    txt.innerHTML = greetingDescription.value; // greetingDescription comes from your computed/watched property
    return txt.value;
});

const emits = defineEmits(['submit', 'cancel', 'error', 'success']);

const submitForm = () => {
    // Normalize voicemail_copies to an array of values
    form.voicemail_copies = form.voicemail_copies.map(item => {
        // If it's an object, return the 'value', otherwise, return the item itself
        return typeof item === 'object' ? item.value : item;
    });

    emits('submit', form); // Emit the event with the form data
}

const handleUpdateCopyToField = (voicemails) => {
    form.voicemail_copies = voicemails.map(voicemail => voicemail.value);
}

const handleUpdateGreetingField = (greeting) => {
    form.greeting_id = greeting.value;
    currentAudio.value = false;
}

// Handler for the greeting-saved event
const handleGreetingSaved = ({ greeting_id, greeting_name, description }) => {
    // Add the new greeting to the localOptions.greetings array
    localOptions.greetings.push({ value: String(greeting_id), name: greeting_name, description: description });

    // Sort the greetings array by greeting_id
    localOptions.greetings.sort((a, b) => Number(a.value) - Number(b.value));

    // Update the selected greeting ID
    form.greeting_id = String(greeting_id);

    currentAudio.value = null;
};

// Handler for the greeting-saved event
const handleNameSaved = ({ greeting_id, greeting_name }) => {
    localOptions.recorded_name = 'Custom recording';
    currentNameAudio.value = null;
};

const currentAudio = ref(null);
const isAudioPlaying = ref(false);

const playGreeting = () => {
    // Check if there's already an audio object and it is paused
    if (currentAudio.value && currentAudio.value.paused) {
        currentAudio.value.play();
        isAudioPlaying.value = true;
        return;
    }

    axios.post(props.options.routes.greeting_route, { greeting_id: form.greeting_id })
        .then((response) => {
            // Stop the currently playing audio (if any)
            if (currentAudio.value) {
                currentAudio.value.pause();
                currentAudio.value.currentTime = 0; // Reset the playback position
            }
            if (response.data.success) {
                isAudioPlaying.value = true;

                currentAudio.value = new Audio(response.data.file_url);
                currentAudio.value.play();

                // Add an event listener for when the audio ends
                currentAudio.value.addEventListener("ended", () => {
                    isAudioPlaying.value = false;
                });
            }

        }).catch((error) => {
            emits('error', error);
        });
};

const downloadGreeting = () => {
    isDownloading.value = true; // Start the spinner

    axios.post(props.options.routes.greeting_route, { greeting_id: form.greeting_id })
        .then((response) => {
            if (response.data.success) {
                // Create a URL with the download parameter set to true
                const downloadUrl = `${response.data.file_url}?download=true`;

                // Create an invisible link element
                const link = document.createElement('a');
                link.href = downloadUrl;

                // Use the filename or a default name
                const fileName = response.data.file_name || 'greeting.wav';
                link.download = fileName;

                // Append the link to the body
                document.body.appendChild(link);

                // Trigger the download by programmatically clicking the link
                link.click();

                // Remove the link after the download starts
                document.body.removeChild(link);
            }
        })
        .catch((error) => {
            emits('error', error);
        })
        .finally(() => {
            isDownloading.value = false; // Stop the spinner after download completes
        });
};


const pauseGreeting = () => {
    if (currentAudio.value) {
        currentAudio.value.pause();
        isAudioPlaying.value = false;
    }
};


const deleteGreeting = () => {
    // Ensure a greeting is selected before attempting to delete
    if (form.greeting_id <= 0) {
        return; // Exit if no valid greeting is selected
    }

    // Show the confirmation modal
    confirmationModalTrigger.value = true;
};

const confirmDeleteAction = () => {
    axios
        .post(props.options.routes.delete_greeting_route, { greeting_id: form.greeting_id })
        .then((response) => {
            if (response.data.success) {
                // Remove the deleted greeting from the localOptions.greetings array
                localOptions.greetings = localOptions.greetings.filter(
                    (greeting) => greeting.value !== String(form.greeting_id)
                );

                // Reset the selected greeting ID
                form.greeting_id = '-1'; // Or set it to another default if needed

                console.log(response.data);
                // Notify the parent component or show a local success message
                emits('success', 'success', response.data.messages); // Or handle locally
            }
        })
        .catch((error) => {
            emits('error', error); // Emit an error event if needed
        })
        .finally(() => {
            confirmationModalTrigger.value = false; // Close the confirmation modal
        });
};


// Add variables for recorded name functionality
const isNameAudioPlaying = ref(false);
const isNameDownloading = ref(false);
const currentNameAudio = ref(null);

// Methods for recorded name
const playRecordedName = () => {
    if (currentNameAudio.value && currentNameAudio.value.paused) {
        currentNameAudio.value.play();
        isNameAudioPlaying.value = true;
        return;
    }

    axios.post(props.options.routes.recorded_name_route, { voicemail_id: form.voicemail_id })
        .then((response) => {
            if (currentNameAudio.value) {
                currentNameAudio.value.pause();
                currentNameAudio.value.currentTime = 0;
            }
            if (response.data.success) {
                isNameAudioPlaying.value = true;

                // Add a cache-busting query parameter to the file URL
                const fileUrlWithCacheBuster = `${response.data.file_url}?t=${new Date().getTime()}`;

                currentNameAudio.value = new Audio(fileUrlWithCacheBuster);
                currentNameAudio.value.play();

                currentNameAudio.value.addEventListener("ended", () => {
                    isNameAudioPlaying.value = false;
                });
            }
        }).catch((error) => {
            emits('error', error);
        });
};

const pauseRecordedName = () => {
    if (currentNameAudio.value) {
        currentNameAudio.value.pause();
        isNameAudioPlaying.value = false;
    }
};

const downloadRecordedName = () => {
    isNameDownloading.value = true;

    axios.post(props.options.routes.recorded_name_route, { voicemail_id: form.voicemail_id })
        .then((response) => {
            if (response.data.success) {
                const downloadUrl = `${response.data.file_url}?download=true`;

                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = response.data.file_name || 'recorded_name.wav';

                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        })
        .catch((error) => {
            emits('error', error);
        })
        .finally(() => {
            isNameDownloading.value = false;
        });
};

const deleteRecordedName = () => {
    confirmationModalTriggerForName.value = true; // Show confirmation modal
};

const confirmDeleteNameAction = () => {
    axios
        .post(props.options.routes.delete_recorded_name_route, { voicemail_id: form.voicemail_id })
        .then((response) => {
            if (response.data.success) {
                localOptions.recorded_name = 'System Default';
                emits('success', 'success', response.data.messages);
            }
        })
        .catch((error) => {
            emits('error', error);
        })
        .finally(() => {
            confirmationModalTriggerForName.value = false;
        });
};



// Computed property or method to dynamically set routes based on the form type
const getRoutesForGreetingForm = computed(() => {
    // Return routes specifically for the greeting form
    return {
        ...localOptions.routes,
        text_to_speech_route: localOptions.routes.text_to_speech_route,
        upload_greeting_route: localOptions.routes.upload_greeting_route
    };
});

const getRoutesForNameForm = computed(() => {
    // Return routes specifically for the name form
    return {
        ...localOptions.routes,
        text_to_speech_route: localOptions.routes.text_to_speech_route_for_name,
        upload_greeting_route: localOptions.routes.upload_greeting_route_for_name,
    };
});


</script>

<style>
div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}
</style>