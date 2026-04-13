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
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl sm:p-6">
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
                                    <svg class="animate-spin h-10 w-10 text-blue-600" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <div class="text-lg text-blue-600">Loading...</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false" :default="{
                                    items,
                                    voicemail_enabled: 'true',
                                    voicemail_transcription_enabled: 'true',
                                    voicemail_local_after_email: 'false',
                                    recording_enabled: false,
                                    user_record: 'all',
                                    voicemail_file_toggle: true,
                                }">
                                <template #empty>
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="general" label="General" :elements="generalElements" />
                                                <FormTab name="caller_id" label="Caller ID" :elements="callerIdElements" />
                                                <FormTab name="call_forward" label="Call Forward" :elements="callForwardElements" />
                                                <FormTab name="voicemail" label="Voicemail" :elements="voicemailElements" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <HiddenElement name="items" :meta="true" />
                                                
                                                <StaticElement name="general_title" tag="h4" content="General" />

                                                <CheckboxElement name="directory_first_name_checkbox" :submit="false" label="&nbsp;" :columns="{ container: 1 }" />
                                                <TextElement name="directory_first_name" label="First Name" :floating="false" :columns="{ container: 11 }" :disabled="[['directory_first_name_checkbox', false]]" />

                                                <CheckboxElement name="directory_last_name_checkbox" :submit="false" label="&nbsp;" :columns="{ container: 1 }" />
                                                <TextElement name="directory_last_name" label="Last Name" :floating="false" :columns="{ container: 11 }" :disabled="[['directory_last_name_checkbox', false]]" />

                                                <CheckboxElement name="voicemail_mail_to_checkbox" :submit="false" label="&nbsp;" :columns="{ container: 1 }" />
                                                <TextElement name="voicemail_mail_to" label="Email" :floating="false" :columns="{ container: 11 }" :disabled="[['voicemail_mail_to_checkbox', false]]" />

                                                <CheckboxElement name="description_checkbox" :submit="false" label="&nbsp;" :columns="{ container: 1 }" />
                                                <TextElement name="description" label="Description" :floating="false" :columns="{ container: 11 }" :disabled="[['description_checkbox', false]]" />

                                                <CheckboxElement v-if="permissions.extension_suspended" name="suspended_checkbox" :submit="false" :columns="{ container: 1 }" />
                                                <ToggleElement v-if="permissions.extension_suspended" name="suspended" text="Suspended" true-value="true" false-value="false" :columns="{ container: 11 }" :disabled="[['suspended_checkbox', false]]" />

                                                <CheckboxElement v-if="permissions.extension_do_not_disturb" name="do_not_disturb_checkbox" :submit="false" :columns="{ container: 1 }" />
                                                <ToggleElement v-if="permissions.extension_do_not_disturb" name="do_not_disturb" text="Do Not Disturb" true-value="true" false-value="false" :columns="{ container: 11 }" :disabled="[['do_not_disturb_checkbox', false]]" />

                                                <CheckboxElement v-if="permissions.extension_user_record" name="user_record_checkbox" :submit="false" :columns="{ container: 1 }" />
                                                <ToggleElement v-if="permissions.extension_user_record" name="recording_enabled" text="Record Calls" :submit="false" :disabled="[['user_record_checkbox', false]]" :columns="{ container: 11 }" />
                                                <RadiogroupElement v-if="permissions.extension_user_record" name="user_record" label="Record" :items="recordingOptions" :conditions="[['recording_enabled', '==', true]]" :disabled="[['user_record_checkbox', false]]" :columns="{ container: 12 }" />

                                                <CheckboxElement name="call_timeout_checkbox" :submit="false" label="&nbsp;" :columns="{ container: 1 }" />
                                                <SelectElement name="call_timeout" :items="delayOptions" :search="true" :native="false" label="Send unanswered calls to voicemail after" input-type="search" placeholder="Select option" :floating="false" :columns="{ container: 11 }" :disabled="[['call_timeout_checkbox', false]]" />

                                                <GroupElement name="general_footer" />
                                                <ButtonElement name="submit_general" button-label="Save" :submits="true" align="right" />

                                                <StaticElement name="caller_id_title" tag="h4" content="Caller ID" />

                                                <CheckboxElement v-if="permissions.manage_external_caller_id_number" name="outbound_caller_id_number_checkbox" :submit="false" label="&nbsp;" :columns="{ container: 1 }" />
                                                <SelectElement v-if="permissions.manage_external_caller_id_number" name="outbound_caller_id_number" :items="options.phone_numbers" :search="true" :native="false" label="External Caller ID" input-type="search" autocomplete="off" placeholder="Select number" :floating="false" :columns="{ container: 11 }" :disabled="[['outbound_caller_id_number_checkbox', false]]" />

                                                <CheckboxElement v-if="permissions.manage_emergency_caller_id_number" name="emergency_caller_id_number_checkbox" :submit="false" label="&nbsp;" :columns="{ container: 1 }" />
                                                <SelectElement v-if="permissions.manage_emergency_caller_id_number" name="emergency_caller_id_number" :items="options.phone_numbers" :search="true" :native="false" label="Emergency Caller ID" input-type="search" autocomplete="off" placeholder="Select number" :floating="false" :columns="{ container: 11 }" :disabled="[['emergency_caller_id_number_checkbox', false]]" />

                                                <GroupElement name="caller_id_footer" />
                                                <ButtonElement name="submit_caller_id" button-label="Save" :submits="true" align="right" />

                                                <StaticElement name="call_forward_title" tag="h4" content="Call Forward" />

                                                <CheckboxElement v-if="permissions.extension_forward_all" name="forward_all_checkbox" :submit="false" label="&nbsp;" :columns="{ container: 1 }" />
                                                <StaticElement v-if="permissions.extension_forward_all" name="forward_all_info" :columns="{ container: 11 }">
                                                    <div>
                                                        <div class="text-base font-semibold text-gray-800">Forward All Calls</div>
                                                        <div class="mt-1 text-sm text-gray-500">
                                                            Instantly and unconditionally forward all incoming calls to another destination. No calls will ring to your phone until forwarding is disabled.
                                                        </div>
                                                    </div>
                                                </StaticElement>
                                                <ToggleElement v-if="permissions.extension_forward_all" name="forward_all_enabled" :labels="{ on: 'On', off: 'Off' }" true-value="true" false-value="false" text="Enabled"
                                                    :columns="{ container: 12 }" :conditions="[['forward_all_checkbox', '==', true]]" />
                                                <SelectElement v-if="permissions.extension_forward_all" name="forward_all_action" :items="forwardingTypeOptions" :search="true"
                                                    :native="false" label="Choose Action" input-type="search" autocomplete="off" placeholder="Choose Action"
                                                    :floating="false" :strict="false" :columns="{ container: 12 }"
                                                    :conditions="[['forward_all_checkbox', '==', true]]"
                                                    @change="(newValue, oldValue, el$) => handleForwardActionChange('forward_all_target', newValue, oldValue, el$)" />
                                                <SelectElement v-if="permissions.extension_forward_all" name="forward_all_target" :items="(query, input) => getRoutingOptions('forward_all_action', input)"
                                                    :search="true" label-prop="name" :native="false" label="Target" input-type="search"
                                                    allow-absent :object="true" :format-data="formatTarget" autocomplete="off" placeholder="Choose Target"
                                                    :floating="false" :strict="false" :columns="{ container: 12 }"
                                                    :conditions="[['forward_all_checkbox', '==', true], ['forward_all_action', 'not_empty'], ['forward_all_action', 'not_in', ['external']]]" />
                                                <TextElement v-if="permissions.extension_forward_all" name="forward_all_external_target" label="Target" placeholder="Enter External Number"
                                                    :floating="false" :columns="{ container: 12 }"
                                                    :conditions="[['forward_all_checkbox', '==', true], ['forward_all_action', 'not_empty'], ['forward_all_action', 'in', ['external']]]" />

                                                <CheckboxElement v-if="permissions.extension_forward_busy" name="forward_busy_checkbox" :submit="false" label="&nbsp;" :columns="{ container: 1 }" />
                                                <StaticElement v-if="permissions.extension_forward_busy" name="forward_busy_info" :columns="{ container: 11 }">
                                                    <div>
                                                        <div class="text-base font-semibold text-gray-800">When user is busy</div>
                                                        <div class="mt-1 text-sm text-gray-500">
                                                            Automatically redirect incoming calls to a different destination when your line is busy or Do Not Disturb is active.
                                                        </div>
                                                    </div>
                                                </StaticElement>
                                                <ToggleElement v-if="permissions.extension_forward_busy" name="forward_busy_enabled" :labels="{ on: 'On', off: 'Off' }" true-value="true" false-value="false" text="Enabled"
                                                    :columns="{ container: 12 }" :conditions="[['forward_busy_checkbox', '==', true]]" />
                                                <SelectElement v-if="permissions.extension_forward_busy" name="forward_busy_action" :items="forwardingTypeOptions" :search="true"
                                                    :native="false" label="Choose Action" input-type="search" autocomplete="off" placeholder="Choose Action"
                                                    :floating="false" :strict="false" :columns="{ container: 12 }"
                                                    :conditions="[['forward_busy_checkbox', '==', true]]"
                                                    @change="(newValue, oldValue, el$) => handleForwardActionChange('forward_busy_target', newValue, oldValue, el$)" />
                                                <SelectElement v-if="permissions.extension_forward_busy" name="forward_busy_target" :items="(query, input) => getRoutingOptions('forward_busy_action', input)"
                                                    :search="true" label-prop="name" :native="false" label="Target" input-type="search"
                                                    allow-absent :object="true" :format-data="formatTarget" autocomplete="off" placeholder="Choose Target"
                                                    :floating="false" :strict="false" :columns="{ container: 12 }"
                                                    :conditions="[['forward_busy_checkbox', '==', true], ['forward_busy_action', 'not_empty'], ['forward_busy_action', 'not_in', ['external']]]" />
                                                <TextElement v-if="permissions.extension_forward_busy" name="forward_busy_external_target" label="Target" placeholder="Enter External Number"
                                                    :floating="false" :columns="{ container: 12 }"
                                                    :conditions="[['forward_busy_checkbox', '==', true], ['forward_busy_action', 'not_empty'], ['forward_busy_action', 'in', ['external']]]" />

                                                <CheckboxElement v-if="permissions.extension_forward_no_answer" name="forward_no_answer_checkbox" :submit="false" label="&nbsp;" :columns="{ container: 1 }" />
                                                <StaticElement v-if="permissions.extension_forward_no_answer" name="forward_no_answer_info" :columns="{ container: 11 }">
                                                    <div>
                                                        <div class="text-base font-semibold text-gray-800">When user does not answer the call</div>
                                                        <div class="mt-1 text-sm text-gray-500">
                                                            Automatically redirect incoming calls to another number if you do not answer within a set time.
                                                        </div>
                                                    </div>
                                                </StaticElement>
                                                <ToggleElement v-if="permissions.extension_forward_no_answer" name="forward_no_answer_enabled" :labels="{ on: 'On', off: 'Off' }" true-value="true" false-value="false" text="Enabled"
                                                    :columns="{ container: 12 }" :conditions="[['forward_no_answer_checkbox', '==', true]]" />
                                                <SelectElement v-if="permissions.extension_forward_no_answer" name="forward_no_answer_action" :items="forwardingTypeOptions" :search="true"
                                                    :native="false" label="Choose Action" input-type="search" autocomplete="off" placeholder="Choose Action"
                                                    :floating="false" :strict="false" :columns="{ container: 12 }"
                                                    :conditions="[['forward_no_answer_checkbox', '==', true]]"
                                                    @change="(newValue, oldValue, el$) => handleForwardActionChange('forward_no_answer_target', newValue, oldValue, el$)" />
                                                <SelectElement v-if="permissions.extension_forward_no_answer" name="forward_no_answer_target" :items="(query, input) => getRoutingOptions('forward_no_answer_action', input)"
                                                    :search="true" label-prop="name" :native="false" label="Target" input-type="search"
                                                    allow-absent :object="true" :format-data="formatTarget" autocomplete="off" placeholder="Choose Target"
                                                    :floating="false" :strict="false" :columns="{ container: 12 }"
                                                    :conditions="[['forward_no_answer_checkbox', '==', true], ['forward_no_answer_action', 'not_empty'], ['forward_no_answer_action', 'not_in', ['external']]]" />
                                                <TextElement v-if="permissions.extension_forward_no_answer" name="forward_no_answer_external_target" label="Target" placeholder="Enter External Number"
                                                    :floating="false" :columns="{ container: 12 }"
                                                    :conditions="[['forward_no_answer_checkbox', '==', true], ['forward_no_answer_action', 'not_empty'], ['forward_no_answer_action', 'in', ['external']]]" />

                                                <CheckboxElement v-if="permissions.extension_forward_not_registered" name="forward_user_not_registered_checkbox" :submit="false" label="&nbsp;" :columns="{ container: 1 }" />
                                                <StaticElement v-if="permissions.extension_forward_not_registered" name="forward_user_not_registered_info" :columns="{ container: 11 }">
                                                    <div>
                                                        <div class="text-base font-semibold text-gray-800">When Device Is Not Registered (Internet Outage)</div>
                                                        <div class="mt-1 text-sm text-gray-500">
                                                            Redirect calls to a different number if your device is not registered or unreachable.
                                                        </div>
                                                    </div>
                                                </StaticElement>
                                                <ToggleElement v-if="permissions.extension_forward_not_registered" name="forward_user_not_registered_enabled" :labels="{ on: 'On', off: 'Off' }" true-value="true" false-value="false" text="Enabled"
                                                    :columns="{ container: 12 }" :conditions="[['forward_user_not_registered_checkbox', '==', true]]" />
                                                <SelectElement v-if="permissions.extension_forward_not_registered" name="forward_user_not_registered_action" :items="forwardingTypeOptions" :search="true"
                                                    :native="false" label="Choose Action" input-type="search" autocomplete="off" placeholder="Choose Action"
                                                    :floating="false" :strict="false" :columns="{ container: 12 }"
                                                    :conditions="[['forward_user_not_registered_checkbox', '==', true]]"
                                                    @change="(newValue, oldValue, el$) => handleForwardActionChange('forward_user_not_registered_target', newValue, oldValue, el$)" />
                                                <SelectElement v-if="permissions.extension_forward_not_registered" name="forward_user_not_registered_target" :items="(query, input) => getRoutingOptions('forward_user_not_registered_action', input)"
                                                    :search="true" label-prop="name" :native="false" label="Target" input-type="search"
                                                    allow-absent :object="true" :format-data="formatTarget" autocomplete="off" placeholder="Choose Target"
                                                    :floating="false" :strict="false" :columns="{ container: 12 }"
                                                    :conditions="[['forward_user_not_registered_checkbox', '==', true], ['forward_user_not_registered_action', 'not_empty'], ['forward_user_not_registered_action', 'not_in', ['external']]]" />
                                                <TextElement v-if="permissions.extension_forward_not_registered" name="forward_user_not_registered_external_target" label="Target"
                                                    placeholder="Enter External Number" :floating="false" :columns="{ container: 12 }"
                                                    :conditions="[['forward_user_not_registered_checkbox', '==', true], ['forward_user_not_registered_action', 'not_empty'], ['forward_user_not_registered_action', 'in', ['external']]]" />

                                                <GroupElement name="call_forward_footer" />
                                                <ButtonElement name="submit_call_forward" button-label="Save" :submits="true" align="right" />

                                                <StaticElement name="voicemail_title" tag="h4" content="Voicemail" />

                                                <CheckboxElement v-if="permissions.manage_voicemail" name="voicemail_enabled_checkbox" :submit="false" :columns="{ container: 1 }" />
                                                <ToggleElement v-if="permissions.manage_voicemail" name="voicemail_enabled" text="Voicemail Enabled" true-value="true" false-value="false" :columns="{ container: 11 }" :disabled="[['voicemail_enabled_checkbox', false]]" />

                                                <CheckboxElement v-if="permissions.manage_voicemail" name="voicemail_password_checkbox" :submit="false" label="&nbsp;" :columns="{ container: 1 }" />
                                                <TextElement v-if="permissions.manage_voicemail" name="voicemail_password" label="Voicemail Password" :floating="false" :columns="{ container: 11 }" :disabled="[['voicemail_password_checkbox', false]]" />

                                                <CheckboxElement v-if="permissions.manage_voicemail" name="voicemail_description_checkbox" :submit="false" label="&nbsp;" :columns="{ container: 1 }" />
                                                <TextElement v-if="permissions.manage_voicemail" name="voicemail_description" label="Voicemail Description" :floating="false" :columns="{ container: 11 }" :disabled="[['voicemail_description_checkbox', false]]" />

                                                <CheckboxElement v-if="permissions.manage_voicemail_transcription" name="voicemail_transcription_enabled_checkbox" :submit="false" :columns="{ container: 1 }" />
                                                <ToggleElement v-if="permissions.manage_voicemail_transcription" name="voicemail_transcription_enabled" text="Voicemail Transcription" true-value="true" false-value="false" :columns="{ container: 11 }" :disabled="[['voicemail_transcription_enabled_checkbox', false]]" />

                                                <CheckboxElement v-if="permissions.manage_voicemail" name="voicemail_file_checkbox" :submit="false" :columns="{ container: 1 }" />
                                                <ToggleElement v-if="permissions.manage_voicemail" name="voicemail_file_toggle" text="Attach File To Email Notifications" :submit="false" :columns="{ container: 11 }" :disabled="[['voicemail_file_checkbox', false]]" />

                                                <CheckboxElement v-if="permissions.manage_voicemail_auto_delete" name="voicemail_local_after_email_checkbox" :submit="false" :columns="{ container: 1 }" />
                                                <ToggleElement v-if="permissions.manage_voicemail_auto_delete" name="voicemail_local_after_email" text="Automatically Delete Voicemail After Email" true-value="false" false-value="true" :columns="{ container: 11 }" :disabled="[['voicemail_local_after_email_checkbox', false]]" />

                                                <GroupElement name="voicemail_footer" />
                                                <ButtonElement name="submit_voicemail" button-label="Save" :submits="true" align="right" />
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
</template>

<script setup>
import { computed, ref } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { XMarkIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    show: Boolean,
    options: {
        type: Object,
        default: () => ({}),
    },
    header: String,
    loading: Boolean,
});

const emit = defineEmits(['close', 'error', 'success', 'refresh-data']);

const form$ = ref(null);
const permissions = computed(() => props.options.permissions ?? {});

const generalElements = [
    'general_title',
    'items',
    'directory_first_name_checkbox',
    'directory_first_name',
    'directory_last_name_checkbox',
    'directory_last_name',
    'voicemail_mail_to_checkbox',
    'voicemail_mail_to',
    'description_checkbox',
    'description',
    'suspended_checkbox',
    'suspended',
    'do_not_disturb_checkbox',
    'do_not_disturb',
    'user_record_checkbox',
    'recording_enabled',
    'user_record',
    'call_timeout_checkbox',
    'call_timeout',
    'general_footer',
    'submit_general',
];

const callerIdElements = [
    'caller_id_title',
    'outbound_caller_id_number_checkbox',
    'outbound_caller_id_number',
    'emergency_caller_id_number_checkbox',
    'emergency_caller_id_number',
    'caller_id_footer',
    'submit_caller_id',
];

const callForwardElements = [
    'call_forward_title',
    'forward_all_checkbox',
    'forward_all_info',
    'forward_all_enabled',
    'forward_all_action',
    'forward_all_target',
    'forward_all_external_target',
    'forward_busy_checkbox',
    'forward_busy_info',
    'forward_busy_enabled',
    'forward_busy_action',
    'forward_busy_target',
    'forward_busy_external_target',
    'forward_no_answer_checkbox',
    'forward_no_answer_info',
    'forward_no_answer_enabled',
    'forward_no_answer_action',
    'forward_no_answer_target',
    'forward_no_answer_external_target',
    'forward_user_not_registered_checkbox',
    'forward_user_not_registered_info',
    'forward_user_not_registered_enabled',
    'forward_user_not_registered_action',
    'forward_user_not_registered_target',
    'forward_user_not_registered_external_target',
    'call_forward_footer',
    'submit_call_forward',
];

const voicemailElements = [
    'voicemail_title',
    'voicemail_enabled_checkbox',
    'voicemail_enabled',
    'voicemail_password_checkbox',
    'voicemail_password',
    'voicemail_description_checkbox',
    'voicemail_description',
    'voicemail_transcription_enabled_checkbox',
    'voicemail_transcription_enabled',
    'voicemail_file_checkbox',
    'voicemail_file_toggle',
    'voicemail_local_after_email_checkbox',
    'voicemail_local_after_email',
    'voicemail_footer',
    'submit_voicemail',
];

const recordingOptions = [
    { value: 'all', label: 'All' },
    { value: 'local', label: 'Local' },
    { value: 'outbound', label: 'Outbound' },
    { value: 'inbound', label: 'Inbound' },
];

const delayOptions = Array.from({ length: 21 }, (_, i) => {
    const seconds = i * 5;
    const rings = Math.round(seconds / 5);

    return {
        value: String(seconds),
        label: `${rings} ${rings === 1 ? 'Ring' : 'Rings'} (${seconds}s)`,
    };
});

const callForwardCheckboxNames = [
    'forward_all_checkbox',
    'forward_busy_checkbox',
    'forward_no_answer_checkbox',
    'forward_user_not_registered_checkbox',
];

const forwardingTypeOptions = computed(() =>
    (props.options.forwarding_types ?? []).map((option) => ({
        value: option?.value ?? '',
        label: option?.label ?? option?.name ?? option?.value ?? '',
    }))
);

const helperFieldNames = computed(() => new Set([
    'recording_enabled',
    'voicemail_file_toggle',
    ...generalElements.filter((name) => name.endsWith('_checkbox')),
    ...callerIdElements.filter((name) => name.endsWith('_checkbox')),
    ...callForwardCheckboxNames,
    ...voicemailElements.filter((name) => name.endsWith('_checkbox')),
]));

const formatTarget = (name, value) => {
    return { [name]: value?.extension ?? null };
};

const handleForwardActionChange = (targetName, newValue, oldValue, el$) => {
    const target = el$.form$.el$(targetName);

    if (!target) {
        return;
    }

    if (oldValue !== null && oldValue !== undefined) {
        target.clear();
    }

    target.updateItems();
};

const getRoutingOptions = async (actionName, input) => {
    const action = input.$parent.el$.form$.el$(actionName);

    if (!action?.value) {
        return [];
    }

    try {
        const response = await action.$vueform.services.axios.post(
            props.options.routes.get_routing_options,
            { category: action.value }
        );

        return response.data.options;
    } catch (error) {
        emit('error', error);
        return [];
    }
};

const collectActiveFieldNames = (elements) => {
    const names = [];

    Object.values(elements ?? {}).forEach((el$) => {
        if (!el$?.isStatic && !el$?.isDisabled && el$?.name) {
            names.push(el$.name);
        }

        if (el$?.children$) {
            names.push(...collectActiveFieldNames(el$.children$));
        }
    });

    return names;
};

const submitForm = async (FormData, form$) => {
    const requestData = { ...form$.requestData };
    const activeFieldNames = collectActiveFieldNames(form$.elements$);

    if (form$.el$('user_record_checkbox')?.value && !form$.el$('recording_enabled')?.value) {
        requestData.user_record = null;
    }

    if (form$.el$('voicemail_file_checkbox')?.value) {
        requestData.voicemail_file = form$.el$('voicemail_file_toggle')?.value ? 'attach' : '';
    }

    delete requestData.recording_enabled;
    delete requestData.voicemail_file_toggle;

    if (form$.el$('user_record_checkbox')?.value) {
        activeFieldNames.push('user_record');
    }

    if (form$.el$('voicemail_file_checkbox')?.value) {
        activeFieldNames.push('voicemail_file');
    }

    const filteredRequestData = Object.fromEntries(
        Object.entries(requestData).filter(([key]) =>
            activeFieldNames.includes(key) && !helperFieldNames.value.has(key)
        )
    );

    return await form$.$vueform.services.axios.post(
        props.options.routes.bulk_update_route,
        filteredRequestData
    );
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear();

    if (el$.children$) {
        Object.values(el$.children$).forEach((childEl$) => {
            clearErrorsRecursive(childEl$);
        });
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach((el$) => {
        clearErrorsRecursive(el$);
    });

    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0]);
            }
        });
    }
};

const handleSuccess = (response) => {
    emit('success', 'success', response.data.messages);
    emit('close');
    emit('refresh-data');
};

const handleError = (error, details, form$) => {
    form$.messageBag.clear();

    if (details.type === 'submit') {
        emit('error', error);
        return;
    }

    if (details.type === 'prepare') {
        form$.messageBag.append('Could not prepare form');
        return;
    }

    if (details.type === 'cancel') {
        form$.messageBag.append('Request cancelled');
        return;
    }

    form$.messageBag.append("Couldn't submit form");
};
</script>
