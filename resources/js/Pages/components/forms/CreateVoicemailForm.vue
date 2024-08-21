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
                </a>
            </nav>
        </aside>

        <div class="space-y-6 sm:px-6 lg:col-span-9 lg:px-0">
            <form v-if="activeTab === 'settings'" action="#" method="POST">
                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                        <div class="flex justify-between items-center">
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Settings</h3>

                            <Toggle label="Status" :enabled="form.voicemail_enabled"
                                @update:status="handleEnabledSettingUpdate" />

                            <!-- <p class="mt-1 text-sm text-gray-500"></p> -->
                        </div>

                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-3 sm:col-span-2">
                                <LabelInputOptional target="voicemail_id" label="Voicemail Extension" class="truncate" />
                                <InputField type="text" name="voicemail_id" id="voicemail_id" class="mt-2" />
                            </div>

                            <div class="col-span-3 sm:col-span-2">
                                <LabelInputOptional target="voicemail_password" label="PIN" class="truncate" />
                                <InputField type="password" name="voicemail_password" id="voicemail_password" class="mt-2" />
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <LabelInputOptional target="voicemail_mail_to" label="Email address" class="truncate" />
                                <InputField type="text" name="voicemail_mail_to" id="voicemail_mail_to" class="mt-2" />
                            </div>

                            <div class="col-span-6">
                                <LabelInputOptional target="voicemail_description" label="Description" class="truncate" />
                                <div class="mt-2">
                                    <Textarea id="voicemail_description" name="voicemail_description" rows="2" />
                                </div>
                            </div>

                            <div class="divide-y divide-gray-200 col-span-6">

                                <Toggle label="Voicemail Transcription"
                                    description="Convert voicemail messages to text using AI-powered transcription."
                                    :enabled="form.voicemail_transcription_enabled"
                                    @update:status="handleTranscriptionSettingUpdate" customClass="py-4" />

                                <Toggle label="Email Notifications"
                                    description="Receive an email when a new voicemail is received."
                                    :enabled="form.voicemail_email_notification_enabled"
                                    @update:status="handleEmailNotificationSettingUpdate" customClass="py-4" />

                                <Toggle label="Automatically Delete Voicemail After Email"
                                    description="Remove voicemail from the cloud once the email is sent."
                                    :enabled="form.voicemail_delete" @update:status="handleEmailNotificationSettingUpdate"
                                    customClass="py-4" />

                            </div>

                            <div class="col-span-4 text-sm font-medium leading-6 text-gray-900">
                                <LabelInputOptional label="Copy Voicemail to
                                    Other Extensions" class="truncate mb-1" />

                                <ComboBox :options="options.extensions" :search="true" multiple
                                    :placeholder="'Enter name or extension'"
                                    @update:model-value="handleUpdateCopyToField" />
                                <div class="mt-1 text-sm text-gray-500">
                                    Automatically send a copy of the voicemail to selected additional extensions.

                                </div>

                            </div>

                        </div>





                    </div>
                    <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
                        <button type="submit"
                            class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Save</button>
                    </div>
                </div>
            </form>


            <form v-if="activeTab === 'greetings'" action="#" method="POST">
                <div class="shadow sm:overflow-hidden sm:rounded-md">
                    <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Greetings</h3>
                            <p class="mt-1 text-sm text-gray-500">Create custom greetings or upload a file</p>
                        </div>

                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-3">
                                <label for="first-name" class="block text-sm font-medium leading-6 text-gray-900">First
                                    name</label>
                                <input type="text" name="first-name" id="first-name" autocomplete="given-name"
                                    class="mt-2 block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <label for="last-name" class="block text-sm font-medium leading-6 text-gray-900">Last
                                    name</label>
                                <input type="text" name="last-name" id="last-name" autocomplete="family-name"
                                    class="mt-2 block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                            </div>

                            <div class="col-span-6 sm:col-span-4">
                                <label for="email-address" class="block text-sm font-medium leading-6 text-gray-900">Email
                                    address</label>
                                <input type="text" name="email-address" id="email-address" autocomplete="email"
                                    class="mt-2 block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <label for="country"
                                    class="block text-sm font-medium leading-6 text-gray-900">Country</label>
                                <select id="country" name="country" autocomplete="country-name"
                                    class="mt-2 block w-full rounded-md border-0 bg-white py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                    <option>United States</option>
                                    <option>Canada</option>
                                    <option>Mexico</option>
                                </select>
                            </div>

                            <div class="col-span-6">
                                <label for="street-address" class="block text-sm font-medium leading-6 text-gray-900">Street
                                    address</label>
                                <input type="text" name="street-address" id="street-address" autocomplete="street-address"
                                    class="mt-2 block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                            </div>

                            <div class="col-span-6 sm:col-span-6 lg:col-span-2">
                                <label for="city" class="block text-sm font-medium leading-6 text-gray-900">City</label>
                                <input type="text" name="city" id="city" autocomplete="address-level2"
                                    class="mt-2 block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                            </div>

                            <div class="col-span-6 sm:col-span-3 lg:col-span-2">
                                <label for="region" class="block text-sm font-medium leading-6 text-gray-900">State /
                                    Province</label>
                                <input type="text" name="region" id="region" autocomplete="address-level1"
                                    class="mt-2 block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                            </div>

                            <div class="col-span-6 sm:col-span-3 lg:col-span-2">
                                <label for="postal-code" class="block text-sm font-medium leading-6 text-gray-900">ZIP /
                                    Postal code</label>
                                <input type="text" name="postal-code" id="postal-code" autocomplete="postal-code"
                                    class="mt-2 block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
                        <button type="submit"
                            class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Save</button>
                    </div>
                </div>
            </form>

            <form v-if="activeTab === 'advanced'" action="#" method="POST">
                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Advanced</h3>
                            <p class="mt-1 text-sm text-gray-500">Set advanced settings for this voicemail
                            </p>
                        </div>

                        <div class="divide-y divide-gray-200 col-span-6">

                            <SwitchGroup as="div" class="flex items-center justify-between py-4">
                                <span class="flex flex-grow flex-col">
                                    <SwitchLabel as="span" class="text-sm font-medium leading-6 text-gray-900" passive>
                                        Play Voicemail Tutorial</SwitchLabel>
                                    <SwitchDescription as="span" class="pr-2 text-sm text-gray-500">
                                        Provide user with a guided tutorial when accessing voicemail for the first time.
                                    </SwitchDescription>
                                </span>
                                <Switch v-model="voicemail_tutorial"
                                    :class="[voicemail_tutorial ? 'bg-indigo-600' : 'bg-gray-200', 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2']">
                                    <span aria-hidden="true"
                                        :class="[voicemail_tutorial ? 'translate-x-5' : 'translate-x-0', 'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out']" />
                                </Switch>
                            </SwitchGroup>

                            <SwitchGroup as="div" class="flex items-center justify-between py-4">
                                <span class="flex flex-grow flex-col">
                                    <SwitchLabel as="span" class="text-sm font-medium leading-6 text-gray-900" passive>
                                        Play Recording Instructions</SwitchLabel>
                                    <SwitchDescription as="span" class="pr-2 text-sm text-gray-500">
                                        Play a prompt instructing callers to "Record your message after the tone. Stop
                                        speaking to end the recording."
                                    </SwitchDescription>
                                </span>
                                <Switch v-model="voicemail_play_recording_instructions"
                                    :class="[voicemail_play_recording_instructions ? 'bg-indigo-600' : 'bg-gray-200', 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2']">
                                    <span aria-hidden="true"
                                        :class="[voicemail_play_recording_instructions ? 'translate-x-5' : 'translate-x-0', 'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out']" />
                                </Switch>
                            </SwitchGroup>

                        </div>

                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-3 sm:col-span-2">
                                <div class="flex items-center gap-1">
                                    <label for="voicemail_alternate_greet_id"
                                        class="block text-sm font-medium leading-6 text-gray-900">Announce Voicemail
                                        Extension as
                                    </label>
                                    <Popover>
                                        <template v-slot:popover-button>
                                            <InformationCircleIcon class="h-5 w-5 text-blue-500" />
                                        </template>
                                        <template v-slot:popover-panel>
                                            <div>The parameter allows you to override the voicemail extension number spoken
                                                by the system in the voicemail greeting. This controls system greetings that
                                                read back an extension number, not user recorded greetings.</div>
                                        </template>
                                    </Popover>
                                </div>

                                <input type="text" name="voicemail_alternate_greet_id" id="voicemail_alternate_greet_id"
                                    class="mt-2 block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                            </div>

                        </div>


                    </div>
                    <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
                        <button type="submit"
                            class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from "vue";
import { usePage } from '@inertiajs/vue3';


import SelectBox from "../general/SelectBox.vue";
import ComboBox from "../general/ComboBox.vue";
import InputField from "../general/InputField.vue";
import Popover from "@generalComponents/Popover.vue";
import Textarea from "@generalComponents/Textarea.vue";
import Toggle from "@generalComponents/Toggle.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";
import Spinner from "../general/Spinner.vue";
import VoicemailIcon from "../icons/VoicemailIcon.vue"
import { Switch, SwitchDescription, SwitchGroup, SwitchLabel } from '@headlessui/vue'
import { InformationCircleIcon } from "@heroicons/vue/24/outline";



//Delete next line
import { Cog6ToothIcon, MusicalNoteIcon, AdjustmentsHorizontalIcon } from '@heroicons/vue/24/outline';


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

const voicemail_tutorial = ref(false)
const voicemail_play_recording_instructions = ref(true)


// Map icon names to their respective components
const iconComponents = {
    'Cog6ToothIcon': Cog6ToothIcon,
    'MusicalNoteIcon': MusicalNoteIcon,
    'AdjustmentsHorizontalIcon': AdjustmentsHorizontalIcon,
};


const page = usePage();

const form = reactive({
    voicemail_enabled: true,
    voicemail_transcription_enabled: true,
    voicemail_email_notification_enabled: true,
    voicemail_delete: false,
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel']);

const submitForm = () => {
    emits('submit', form); // Emit the event with the form data
}

const handleTranscriptionSettingUpdate = (newSelectedItem) => {
    form.voicemail_transcription_enabled = newSelectedItem.value
}

const handleEmailNotificationSettingUpdate = (newSelectedItem) => {
    form.voicemail_email_notification_enabled = newSelectedItem.value
}

const handleEnabledSettingUpdate = (newSelectedItem) => {
    form.voicemail_enabled = newSelectedItem.value
}


const handleUpdateCopyToField = (newSelectedItem) => {
    filterData.value.entity = newSelectedItem.value;
    filterData.value.entityType = newSelectedItem.type;
}
</script>