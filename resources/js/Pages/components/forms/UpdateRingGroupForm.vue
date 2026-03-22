<template>
    <div>
        <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
            @response="handleResponse" :display-errors="false">
            <template #empty>

                <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                    <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                        <FormTabs view="vertical">
                            <FormTab name="page0" label="Settings" :elements="[
                                'ring_group_uuid',
                                'ring_group_uuid_clean',
                                'h4',
                                'ring_group_name',
                                'ring_group_extension',
                                'ring_group_greeting',
                                'ring_group_action_buttons',
                                'ring_group_strategy',
                                'container_3',
                                'divider1',
                                'divider2',
                                'divider3',
                                'container_4',
                                'h3_1',
                                'selectedMembers',
                                'secondaryButton_1',
                                'container_1',
                                'container_2',
                                'members',
                                'container_5',
                                'container_6',
                                'h3_2',
                                'timeout_action',
                                'timeout_target',
                                'container_7',
                                'container_8',
                                'ring_group_cid_name_prefix',
                                'ring_group_cid_number_prefix',
                                'ring_group_description',
                                'settings_submit'
                            ]" :conditions="[() => localOptions.permissions.manage_settings]" />
                            <FormTab name="page1" label="Call Forwarding" :elements="[
                                'h4_1',
                                'ring_group_forward_enabled',
                                'forward_action',
                                'forward_target',
                                'forward_external_target',
                                'call_forward_submit',
                            ]" :conditions="[() => localOptions.permissions.manage_forwarding]" />
                            <FormTab name="page2" label="Advanced" :elements="[
                                'h4_3',
                                'ring_group_caller_id_name',
                                'ring_group_caller_id_number',
                                'ring_group_distinctive_ring',
                                'container5',
                                'container6',
                                'ring_group_ringback',
                                'ring_group_call_forward_enabled',
                                'ring_group_follow_me_enabled',
                                'missed_call_notifications',
                                'ring_group_missed_call_data',
                                'forward_toll_allow',
                                'container',
                                'ring_group_context',
                                'advanced_submit'
                            ]" :conditions="[() => localOptions.permissions.manage_advanced]" />
                        </FormTabs>
                    </div>

                    <div
                        class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                        <FormElements>

                            <HiddenElement name="ring_group_uuid" :meta="true" />
                            <StaticElement name="h4" tag="h4" content="Settings"
                                description="Provide basic information about the ring group" />
                            <StaticElement name="ring_group_uuid_clean"
                                :conditions="[() => localOptions.permissions.is_superadmin]">

                                <div class="mb-1">
                                    <div class="text-sm font-medium text-gray-600 mb-1">
                                        Unique ID
                                    </div>

                                    <div class="flex items-center group">
                                        <span class="text-sm text-gray-900 select-all font-normal">
                                            {{ localOptions.ring_group.ring_group_uuid }}
                                        </span>

                                        <button type="button"
                                            @click="handleCopyToClipboard(localOptions.ring_group.ring_group_uuid)"
                                            class="ml-2 p-1 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                            title="Copy to clipboard">
                                            <ClipboardDocumentIcon
                                                class="h-4 w-4 text-gray-500 hover:text-gray-900  cursor-pointer" />
                                        </button>
                                    </div>
                                </div>

                            </StaticElement>
                            <TextElement name="ring_group_name" label="Name" :columns="{
                                sm: { container: 6, }, lg: { container: 6, },
                            }" placeholder="Enter Ring Group Name" :floating="false" />
                            <TextElement name="ring_group_extension" :columns="{
                                sm: { container: 6, }, lg: { container: 6, },
                            }" label="Extension" placeholder="Enter Extension" :floating="false" />

                            <SelectElement name="ring_group_greeting" :search="true" :native="false" label="Greeting"
                                :items="fetchGreetings" input-type="search" autocomplete="off"
                                placeholder="Select Greeting" :floating="false"
                                info="Enable this option so that callers hear a recorded greeting before they are connected to a group member."
                                :strict="false" :columns="{ sm: { container: 6, }, lg: { container: 6, }, }"
                                :conditions="[() => localOptions.permissions.manage_greeting]">
                                <template #after>
                                    <span v-if="greetingTranscription" class="text-xs italic">
                                        "{{ greetingTranscription }}"
                                    </span>
                                </template>
                            </SelectElement>

                            <GroupElement name="ring_group_action_buttons" :columns="{ container: 6 }"
                                :conditions="[() => localOptions.permissions.manage_greeting]">
                                <ButtonElement v-if="!isAudioPlaying" @click="playGreeting" :columns="{ container: 2 }"
                                    name="play_button" label="&nbsp;" :secondary="true"
                                    :conditions="[hasPlayableGreeting]"
                                    :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                    <PlayCircleIcon
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                </ButtonElement>

                                <ButtonElement v-if="isAudioPlaying" @click="pauseGreeting" name="pause_button"
                                    label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                    :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                    <PauseCircleIcon
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />
                                </ButtonElement>

                                <ButtonElement v-if="!isDownloading" @click="downloadGreeting" name="download_button"
                                    label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                    :conditions="[hasPlayableGreeting]"
                                    :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                    <CloudArrowDownIcon
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                </ButtonElement>

                                <ButtonElement v-if="isDownloading" name="download_spinner_button" label="&nbsp;"
                                    :secondary="true" :columns="{ container: 2 }"
                                    :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                    <Spinner :show="true"
                                        class="h-8 w-8 ml-0 mr-0 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                </ButtonElement>

                                <ButtonElement @click="editGreeting" name="edit_button" label="&nbsp;" :secondary="true"
                                    :columns="{ container: 2 }" :conditions="[hasPlayableGreeting]"
                                    :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                    <PencilSquareIcon
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                </ButtonElement>

                                <ButtonElement @click="deleteGreeting" name="delete_button" label="&nbsp;"
                                    :secondary="true" :columns="{ container: 2 }" :conditions="[hasPlayableGreeting]"
                                    :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                    <TrashIcon
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />
                                </ButtonElement>

                                <ButtonElement @click="handleNewGreetingButtonClick" name="add_button" label="&nbsp;"
                                    :secondary="true" :columns="{ container: 2 }"
                                    :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                    <PlusIcon
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                </ButtonElement>
                            </GroupElement>


                            <SelectElement name="ring_group_strategy" :search="true" :native="false"
                                label="Call Distribution" :items="localOptions.call_distributions" input-type="search"
                                autocomplete="off" placeholder="Select Call Distribution" :floating="false" info="Advanced (default): This option rings all phones at once, but each phone has its own thread. This is especially useful when there are multiple registrations for the same extension.
Sequential Ring: This option rings one phone at a time in a specific order.
Simultaneous Ring: This option rings all phones at once.
Random Ring: This option rings one phone at a time in a random order.
Rollover: This option rings each phone one at a time, but it skips busy phones." :strict="false"
                                :columns="{ sm: { container: 6, }, lg: { container: 6, }, }" />

                            <GroupElement name="container_3"
                                :conditions="[() => localOptions.permissions.destination_view]" />
                            <StaticElement name="divider1" tag="hr"
                                :conditions="[() => localOptions.permissions.destination_view]" />
                            <GroupElement name="container_4"
                                :conditions="[() => localOptions.permissions.destination_view]" />

                            <StaticElement name="h3_1" tag="h4" content="Members"
                                description="Manage members of this ring group"
                                :conditions="[() => localOptions.permissions.destination_view]" />

                            <TagsElement name="selectedMembers" :close-on-select="false" :items="availableMembers"
                                :create="true" :search="true" :groups="true" :native="false" label="Add Member(s)"
                                input-type="search" autocomplete="off" placeholder="Search by name or extension"
                                :floating="false" :hide-selected="false" :object="true" :group-hide-empty="true"
                                :append-new-option="false" :submit="false"
                                description="Choose from the list of available options or enter an external number manually."
                                :conditions="[() => localOptions.permissions.destination_create, () => localOptions.permissions.destination_view]" />

                            <ButtonElement @click="addSelectedMembers" name="secondaryButton_1"
                                button-label="Add Selected Members" :secondary="true" align="center" :full="false"
                                :conditions="[() => localOptions.permissions.destination_create, () => localOptions.permissions.destination_view]" />

                            <GroupElement name="container_1"
                                :conditions="[() => localOptions.permissions.destination_view]" />
                            <GroupElement name="container_2"
                                :conditions="[() => localOptions.permissions.destination_view]" />

                            <ListElement name="members" :sort="true"
                                :controls="{ add: false, remove: localOptions.permissions.destination_delete, sort: localOptions.permissions.destination_update }"
                                :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-md' } }"
                                :conditions="[() => localOptions.permissions.destination_view]">
                                <template #default="{ index }">
                                    <ObjectElement :name="index">
                                        <HiddenElement name="ring_group_destination_uuid" :meta="true" />
                                        <HiddenElement name="destination_number" :meta="true" />
                                        <StaticElement name="p_1" tag="div"
                                            :columns="{ default: { container: 8 }, sm: { container: 4 } }" :label="(el$) => {
                                                const isSusp = el$.parent.value.suspended;

                                                let html = `Member`;

                                                if (isSusp) {
                                                    html += `
                                                        <span class='ml-2 px-2 py-0.5 text-xs rounded font-semibold
                                                                    bg-red-50 text-red-400 ring-1 ring-red-200/20'>
                                                            Suspended
                                                        </span>`;
                                                }

                                                return html;
                                            }" :content="(el$) => {
                                                const label = getMemberLabel(el$.parent.value.destination_number);

                                                return `<span class='text-base font-semibold'>${label}</span>`;
                                            }" />

                                        <SelectElement name="destination_delay" :items="delayOptions" :search="true"
                                            :native="false" label="Delay" input-type="search" allow-absent
                                            autocomplete="off"
                                            :columns="{ default: { container: 6, }, sm: { container: 4, }, }" size="sm"
                                            :conditions="[['ring_group_strategy', 'not_in', ['sequence', 'rollover', 'random']]]"
                                            info="How many seconds to wait before starting to ring this member."
                                            placeholder="Select option" :floating="false"
                                            :disabled="() => { return !localOptions.permissions.destination_update }" />


                                        <SelectElement name="destination_timeout" :items="timeoutOptions" :search="true"
                                            :native="false" label="Ring for" input-type="search" allow-absent
                                            autocomplete="off"
                                            :columns="{ default: { container: 6, }, sm: { container: 4, }, }" size="sm"
                                            info="How many seconds to keep ringing this member before giving up."
                                            placeholder="Select option" :floating="false"
                                            :disabled="() => { return !localOptions.permissions.destination_update }" />

                                        <GroupElement name="container"
                                            :columns="{ default: { container: 6, }, sm: { container: 4, }, }"
                                            :conditions="[['ring_group_strategy', 'in', ['sequence', 'rollover', 'random']]]" />
                                        <GroupElement name="container"
                                            :columns="{ default: { container: 12, }, sm: { container: 4, }, }"
                                            :conditions="[['ring_group_strategy', 'not_in', ['sequence', 'rollover', 'random']]]" />
                                        <ToggleElement name="destination_prompt"
                                            :columns="{ default: { container: 6, }, sm: { container: 4, }, }"
                                            align="left" label="Confirm Answer" size="sm"
                                            info="Enable answer confirmation to prevent voicemails and automated systems from answering a call."
                                            :disabled="() => { return !localOptions.permissions.destination_update }" />

                                        <ToggleElement name="destination_enabled"
                                            :columns="{ default: { container: 5 }, sm: { container: 4 } }" size="sm"
                                            label="Active"
                                            :disabled="(el$) => el$.parent.value.suspended || !localOptions.permissions.destination_update" />
                                    </ObjectElement>
                                </template>
                            </ListElement>

                            <GroupElement name="container_5" />
                            <StaticElement name="divider2" tag="hr" />
                            <GroupElement name="container_6" />
                            <StaticElement name="h3_2" tag="h4" content="When no one in ring group answers"
                                description="Forward calls to" />
                            <SelectElement name="timeout_action" :items="localOptions.routing_types" label-prop="name"
                                :search="true" :native="false" label="Choose Action" input-type="search"
                                autocomplete="off" placeholder="Choose Action" :floating="false" :strict="false"
                                :columns="{ sm: { container: 6, }, }" @change="(newValue, oldValue, el$) => {
                                    let timeout_target = el$.form$.el$('timeout_target')

                                    if (oldValue !== null && oldValue !== undefined) {
                                        timeout_target.clear();
                                    }

                                    timeout_target.updateItems()
                                }" />
                            <SelectElement name="timeout_target" :items="async (query, input) => {
                                let timeout_action = input.$parent.el$.form$.el$('timeout_action');

                                try {
                                    let response = await timeout_action.$vueform.services.axios.post(
                                        options.routes.get_routing_options,
                                        { category: timeout_action.value }
                                    );
                                    return response.data.options;
                                } catch (error) {
                                    emits('error', error);
                                    return [];  // Return an empty array in case of error
                                }
                            }" :search="true" label-prop="name" :native="false" label="Target" input-type="search"
                                allow-absent :object="true" :format-data="formatTarget" autocomplete="off"
                                placeholder="Choose Target" :floating="false" :strict="false"
                                :columns="{ sm: { container: 6, }, }" :conditions="[
                                    ['timeout_action', 'not_empty'],
                                    ['timeout_action', 'not_in', ['check_voicemail', 'company_directory', 'hangup']]
                                ]" />


                            <GroupElement name="container_7" />
                            <StaticElement name="divider3" tag="hr" />
                            <GroupElement name="container_8" />
                            <TextElement name="ring_group_cid_name_prefix" label="Caller ID Name Prefix"
                                info="Prepend text to the caller’s name when routing through this ring group (e.g. ‘Sales:’ to display ‘Sales: Jane Smith’.)."
                                :columns="{ sm: { container: 6, }, }"
                                :conditions="[() => localOptions.permissions.manage_cid_name_prefix]" />
                            <TextElement name="ring_group_cid_number_prefix" label="Caller ID Number Prefix"
                                info="Prepend text to the caller’s number when routing through this ring group (e.g. ‘555#2135551234’)."
                                :columns="{ sm: { container: 6, }, }"
                                :conditions="[() => localOptions.permissions.manage_cid_number_prefix]" />
                            <TextareaElement name="ring_group_description" label="Description" :rows="2" />

                            <ButtonElement name="settings_submit" button-label="Save" :submits="true" align="right" />

                            <!-- Call Forwarding -->
                            <StaticElement name="h4_1" tag="h4" content="Call Forwarding"
                                description="Automatically forward all calls for this ring group to another destination." />
                            <ToggleElement name="ring_group_forward_enabled" :labels="{ on: 'On', off: 'Off', }" />

                            <SelectElement name="forward_action" :items="localOptions.forwarding_types" :search="true"
                                :native="false" label="Choose Action" input-type="search" autocomplete="off"
                                placeholder="Choose Action" :floating="false" :strict="false"
                                :conditions="[['ring_group_forward_enabled', '==', true],]"
                                :columns="{ sm: { container: 6, }, }" @change="(newValue, oldValue, el$) => {
                                    let forward_target = el$.form$.el$('forward_target')

                                    if (oldValue !== null && oldValue !== undefined) {
                                        forward_target.clear();
                                    }

                                    forward_target.updateItems()
                                }" />
                            <SelectElement name="forward_target" :items="async (query, input) => {
                                let forward_action = input.$parent.el$.form$.el$('forward_action');

                                try {
                                    let response = await forward_action.$vueform.services.axios.post(
                                        options.routes.get_routing_options,
                                        { category: forward_action.value }
                                    );
                                    return response.data.options;
                                } catch (error) {
                                    emits('error', error);
                                    return [];  // Return an empty array in case of error
                                }
                            }" :search="true" label-prop="name" :native="false" label="Target" input-type="search"
                                allow-absent :object="true" :format-data="formatTarget" autocomplete="off"
                                placeholder="Choose Target" :floating="false" :strict="false"
                                :columns="{ sm: { container: 6, }, }" :conditions="[
                                    ['ring_group_forward_enabled', '==', true],
                                    ['forward_action', 'not_empty'],
                                    ['forward_action', 'not_in', ['external']]
                                ]" />

                            <TextElement name="forward_external_target" label="Target"
                                placeholder="Enter External Number" :floating="false"
                                :columns="{ sm: { container: 6, }, }" :conditions="[
                                    ['ring_group_forward_enabled', '==', true],
                                    ['forward_action', 'not_empty'],
                                    ['forward_action', 'in', ['external']]
                                ]" />

                            <ButtonElement name="call_forward_submit" button-label="Save" :submits="true"
                                align="right" />


                            <!-- Advanced tab -->
                            <StaticElement name="h4_3" tag="h4" content="Advanced"
                                description="Manage ring group's advanced settings" />
                            <TextElement name="ring_group_caller_id_name" label="Outbound Caller ID Name"
                                description="Set the caller ID name for outbound external calls."
                                :columns="{ sm: { container: 6, }, }"
                                :conditions="[() => localOptions.permissions.manage_cid_name]" />
                            <TextElement name="ring_group_caller_id_number" label="Outbound Caller ID Number"
                                description="Set the caller ID number for outbound external calls."
                                :columns="{ sm: { container: 6, }, }"
                                :conditions="[() => localOptions.permissions.manage_cid_number]" />
                            <TextElement name="ring_group_distinctive_ring" label="Distinctive Ring"
                                :columns="{ sm: { container: 6, }, }" />
                            <GroupElement name="container5" size="sm" />
                            <SelectElement name="ring_group_ringback" :items="localOptions.ring_back_tones"
                                :groups="true" default="${us-ring}" :search="true" :native="false" label="Ringback Tone"
                                input-type="search" autocomplete="off" :strict="false"
                                description="Specify the sound or tone the caller hears while waiting for the destination to answer the call."
                                :columns="{ sm: { container: 6, }, }" />
                            <ToggleElement name="ring_group_call_forward_enabled" align="left"
                                label="Allow Member Call Forwarding Rules"
                                info="Enable per‑member call forwarding rules when Advanced call distribution is selected for the ring group."
                                default="true" />
                            <ToggleElement name="ring_group_follow_me_enabled" align="left"
                                label="Allow Member Sequential Ring Rules"
                                info="Enable per‑member call sequential routing rules when Advanced call distribution is selected for the ring group."
                                default="true" />
                            <ToggleElement name="missed_call_notifications" align="left"
                                label="Enable Missed Call Notifications" default="true"
                                :columns="{ md: { container: 4, }, sm: { container: 6, }, default: { container: 6, }, }"
                                :conditions="[() => localOptions.permissions.manage_missed_call]" />
                            <TextElement name="ring_group_missed_call_data" label="Notification Email"
                                :columns="{ sm: { container: 6, }, }"
                                :conditions="[['missed_call_notifications', '==', true,], () => localOptions.permissions.manage_missed_call]" />

                            <GroupElement name="container6" size="sm" />
                            <TextElement name="forward_toll_allow" label="Forward Toll Allow"
                                :columns="{ sm: { container: 6, }, }"
                                :conditions="[() => localOptions.permissions.manage_forwarding_toll_allow]" />
                            <GroupElement name="container" />
                            <TextElement name="ring_group_context" label="Context" :columns="{ sm: { container: 6, }, }"
                                :conditions="[() => localOptions.permissions.manage_context]" />

                            <ButtonElement name="advanced_submit" button-label="Save" :submits="true" align="right" />

                        </FormElements>
                    </div>
                </div>
            </template>
        </Vueform>
    </div>

    <UpdateGreetingModal :greeting="greetingLabel" :show="showEditModal" :loading="isGreetingUpdating"
        @confirm="handleGreetingUpdate" @close="showEditModal = false" />

    <NewGreetingForm :header="'New Greeting Message'" :show="showNewGreetingModal" @close="showNewGreetingModal = false"
        :voices="localOptions.voices" :speeds="localOptions.speeds" :default_voice="localOptions.default_voice"
        :phone_call_instructions="localOptions.phone_call_instructions" :sample_message="localOptions.sample_message"
        :routes="getRoutesForGreetingForm" @error="emitErrorToParentFromChild" @success="emitSuccessToParentFromChild"
        @saved="handleNewGreetingAdded" />

    <ConfirmationModal :show="showGreetingDeleteConfirmationModal" @close="showGreetingDeleteConfirmationModal = false"
        @confirm="confirmGreetingDeleteAction" :header="'Confirm Deletion'"
        :text="'This action will permanently delete this greeting. Are you sure you want to proceed?'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" />
</template>

<script setup>
import { onMounted, reactive, ref, watch, computed } from "vue";
import ConfirmationModal from "../modal/ConfirmationModal.vue";
import UpdateGreetingModal from "../modal/UpdateGreetingModal.vue";
import Spinner from "@generalComponents/Spinner.vue";
import { PlusIcon, TrashIcon, PencilSquareIcon } from '@heroicons/vue/20/solid'
import { PlayCircleIcon, CloudArrowDownIcon, PauseCircleIcon } from '@heroicons/vue/24/solid';
import NewGreetingForm from './NewGreetingForm.vue';
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";

function toBool(v) {
    return v === true || v === 'true' || v === 1 || v === '1';
}

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emits('success', 'success', { message: ['Copied to clipboard.'] });
    }).catch((error) => {
        emits('error', { response: { data: { errors: { request: ['Failed to copy to clipboard.'] } } } });
    });
}

const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const form$ = ref(null)
const isDownloading = ref(false);
const showNewGreetingModal = ref(false);
const showGreetingDeleteConfirmationModal = ref(false);
const showEditModal = ref(false);
const isGreetingUpdating = ref(false);
const greetingLabel = ref(null);
const availableGreetings = ref(null);

const emits = defineEmits(['close', 'error', 'success', 'refresh-data']);

const fetchGreetings = async (query, input) => {
    const route = props.options?.routes?.greeting_route;

    if (!route) {
        availableGreetings.value = [
            { value: '0', label: 'None' }
        ];
        return availableGreetings.value;
    }

    try {
        const response = await axios.get(route);
        availableGreetings.value = response.data;
        return response.data;
    } catch (error) {
        console.error("Failed to load greetings async", error);
        availableGreetings.value = [
            { value: '0', label: 'None' }
        ];
        return availableGreetings.value;
    }
};

const greetingTranscription = computed(() => {
    const selectedId = form$.value?.data?.ring_group_greeting ?? null;
    if (!selectedId) return null;
    if (!availableGreetings.value) return null;

    const selectedItem = availableGreetings.value.find(
        (item) => String(item.value) === String(selectedId)
    );
    return selectedItem?.description || null;
})

const getSelectedGreetingFileName = () => {
    return form$.value?.data?.ring_group_greeting ?? null;
};

const hasPlayableGreeting = (form$) => {
    const val = form$?.el$('ring_group_greeting')?.value ?? null;
    return val !== '0' && val !== '-1' && val !== null && val !== '';
};

const allMemberOptions = props.options.member_options.flatMap(group => group.groupOptions);

function isSuspended(extension) {
    const match = allMemberOptions.find(opt => opt.destination === extension);
    return match?.suspended === true;
}

const memberItems = props.options.ring_group.destinations?.map(dest => {
    const match = allMemberOptions.find(opt => opt.destination === dest.destination_number);

    return {
        ring_group_destination_uuid: dest.ring_group_destination_uuid,
        destination_number: dest.destination_number,
        destination_delay: dest.destination_delay,
        destination_timeout: dest.destination_timeout,
        destination_prompt: !!dest.destination_prompt,
        destination_enabled: toBool(dest.destination_enabled),
        type: match?.type || null,
        suspended: dest.suspended === true,
    }
})

onMounted(() => {
    form$.value.update({
        ring_group_uuid: props.options.ring_group.ring_group_uuid ?? null,
        ring_group_name: props.options.ring_group.ring_group_name ?? null,
        ring_group_extension: props.options.ring_group.ring_group_extension ?? null,
        ring_group_greeting: props.options.ring_group.ring_group_greeting ?? null,
        ring_group_strategy: props.options.ring_group.ring_group_strategy
            ? props.options.call_distributions.find(rp => rp.value === props.options.ring_group.ring_group_strategy)?.value || 'enterprise'
            : 'enterprise',
        timeout_action: props.options.ring_group.timeout_action ?? null,
        timeout_target: { value: props.options.ring_group.timeout_target_uuid ?? null, extension: props.options.ring_group.timeout_target_extension ?? null, name: props.options.ring_group.timeout_target_name ?? null },
        members: memberItems,
        ring_group_forward_enabled: props.options.ring_group.ring_group_forward_enabled === 'true',
        forward_action: props.options.ring_group.forward_action ?? null,
        forward_external_target: props.options.ring_group.forward_action === 'external'
            ? props.options.ring_group.forward_target_extension ?? null
            : null,
        forward_target: props.options.ring_group.forward_action != 'external'
            ? { value: props.options.ring_group.forward_target_uuid ?? null, extension: props.options.ring_group.forward_target_extension ?? null, name: props.options.ring_group.forward_target_name ?? null }
            : null,
        ring_group_caller_id_name: props.options.ring_group.ring_group_caller_id_name ?? null,
        ring_group_caller_id_number: props.options.ring_group.ring_group_caller_id_number ?? null,
        ring_group_cid_name_prefix: props.options.ring_group.ring_group_cid_name_prefix ?? null,
        ring_group_cid_number_prefix: props.options.ring_group.ring_group_cid_number_prefix ?? null,
        ring_group_distinctive_ring: props.options.ring_group.ring_group_distinctive_ring ?? null,
        ring_group_ringback: props.options.ring_group.ring_group_ringback ?? null,
        ring_group_call_forward_enabled: props.options.ring_group.ring_group_call_forward_enabled === 'true',
        ring_group_follow_me_enabled: props.options.ring_group.ring_group_follow_me_enabled === 'true',
        missed_call_notifications: props.options.ring_group.ring_group_missed_call_app === 'email',
        ring_group_missed_call_data: props.options.ring_group.ring_group_missed_call_data ?? null,
        forward_toll_allow: props.options.ring_group.ring_group_forward_toll_allow ?? null,
        ring_group_context: props.options.ring_group.ring_group_context ?? null,
        ring_group_description: props.options.ring_group.ring_group_description ?? null,
    })

    form$.value.clean()
})

const delayOptions = Array.from({ length: 21 }, (_, i) => {
    const seconds = i * 5;
    const rings = Math.round(seconds / 5);
    return { value: String(seconds), label: `${rings} ${rings === 1 ? 'Ring' : 'Rings'} (${seconds}s)` };
});

const timeoutOptions = Array.from({ length: 21 }, (_, i) => {
    const seconds = i * 5;
    const rings = Math.round(seconds / 5);
    return { value: String(seconds), label: `${rings} ${rings === 1 ? 'Ring' : 'Rings'} (${seconds}s)` };
});

const availableMembers = computed(() => {
    const membersField = form$.value?.el$('members');
    const currentMembers = membersField?.value || [];
    const selectedDestinations = currentMembers.map(m => m.destination_number);

    return props.options.member_options.map(group => ({
        label: group.groupLabel,
        items: group.groupOptions.filter(opt => !selectedDestinations.includes(opt.destination)),
    }));
});

const addSelectedMembers = () => {
    const selectedItems = form$.value.el$('selectedMembers').value.map(item => {
        return {
            ring_group_destination_uuid: item.destination ? item.value : null,
            destination_number: item.destination ? item.destination : item.label,
            type: item.type ? item.type : "other",
            destination_delay: "0",
            destination_timeout: "25",
            destination_prompt: false,
            destination_enabled: true
        }
    });

    const currentMembers = form$.value.el$('members').value

    form$.value.update({
        members: [...currentMembers, ...selectedItems]
    })

    form$.value.el$('selectedMembers').update([]);
};

function getMemberLabel(destination) {
    const member = allMemberOptions.find(opt => opt.destination === destination);
    return member ? member.label : destination;
};

const handleNewGreetingButtonClick = () => {
    stopGreetingAudio();
    showNewGreetingModal.value = true;
};

const localOptions = reactive({ ...props.options });

watch(() => props.options, (newOptions) => {
    Object.assign(localOptions, newOptions);
});

const formatTarget = (name, value) => {
    return { [name]: value?.extension ?? null }
}

const submitForm = async (FormData, form$) => {
    const requestData = form$.requestData;

    return await form$.$vueform.services.axios.put(
        localOptions.routes.update_route,
        requestData
    );
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear()

    if (el$.children$) {
        Object.values(el$.children$).forEach(childEl$ => {
            clearErrorsRecursive(childEl$)
        })
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach(el$ => {
        clearErrorsRecursive(el$)
    })

    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0])
            }
        })
    }
}

const handleSuccess = (response, form$) => {
    emits('success', 'success', response.data.messages);
    emits('close');
    emits('refresh-data');
}

const handleError = (error, details, form$) => {
    form$.messageBag.clear()

    switch (details.type) {
        case 'prepare':
            form$.messageBag.append('Could not prepare form')
            break
        case 'submit':
            emits('error', error);
            break
        case 'cancel':
            form$.messageBag.append('Request cancelled')
            break
        case 'other':
            form$.messageBag.append('Couldn\'t submit form')
            break
    }
}

const emitErrorToParentFromChild = (error) => {
    emits('error', error);
}

const emitSuccessToParentFromChild = (message) => {
    emits('success', 'success', message);
}

const handleNewGreetingAdded = async (greeting_id) => {
    await form$.value.el$('ring_group_greeting').updateItems()
    form$.value.update({
        ring_group_greeting: greeting_id,
    })
    showNewGreetingModal.value = false;
};

const currentAudio = ref(null);
const isAudioPlaying = ref(false);
const currentAudioGreeting = ref(null);

const playGreeting = () => {
    const greeting = getSelectedGreetingFileName();
    if (!greeting) return;

    if (currentAudio.value && currentAudioGreeting.value === greeting) {
        if (currentAudio.value.paused) {
            currentAudio.value.play();
            isAudioPlaying.value = true;
        }
        return;
    }

    if (currentAudio.value) {
        currentAudio.value.pause();
        currentAudio.value.currentTime = 0;
        currentAudio.value = null;
    }

    isAudioPlaying.value = false;

    const fileUrl = localOptions.routes.serve_greeting_route.replace(':file_name', encodeURIComponent(greeting));

    currentAudio.value = new Audio(fileUrl);
    currentAudioGreeting.value = greeting;
    isAudioPlaying.value = true;

    currentAudio.value.play().catch(() => {
        isAudioPlaying.value = false;
        emits('error', { message: 'Audio playback failed' });
    });

    currentAudio.value.addEventListener('ended', () => {
        isAudioPlaying.value = false;
    });

    currentAudio.value.addEventListener('error', () => {
        isAudioPlaying.value = false;
        emits('error', { message: 'File not found or failed to load audio' });
    });
};

const downloadGreeting = () => {
    isDownloading.value = true;
    const greeting = getSelectedGreetingFileName();

    if (!greeting) {
        isDownloading.value = false;
        return;
    }

    const downloadUrl =
        localOptions.routes.serve_greeting_route.replace(':file_name', encodeURIComponent(greeting))
        + `?download=true&v=${Date.now()}`;

    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = greeting || 'greeting.wav';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    isDownloading.value = false;
};

const pauseGreeting = () => {
    if (currentAudio.value) {
        currentAudio.value.pause();
        isAudioPlaying.value = false;
    }
};

const stopGreetingAudio = () => {
    if (currentAudio.value) {
        currentAudio.value.pause()
        currentAudio.value.currentTime = 0
        currentAudio.value = null
    }

    isAudioPlaying.value = false
    currentAudioGreeting.value = null
}

const editGreeting = () => {
    const selectedId = getSelectedGreetingFileName();
    if (selectedId && availableGreetings.value) {
        const selectedItem = availableGreetings.value.find(
            (item) => String(item.value) === String(selectedId)
        );
        if (selectedItem) {
            greetingLabel.value = selectedItem; // Send full object to modal
            showEditModal.value = true;
        }
    }
};

const handleGreetingUpdate = async (updatedGreeting) => {
    const newName = updatedGreeting?.label?.trim();

    if (!newName) {
        emit('error', {
            response: {
                data: {
                    errors: {
                        request: ['Greeting name cannot be empty.']
                    }
                }
            }
        });
        return;
    }
    isGreetingUpdating.value = true;

    axios
        .post(localOptions.routes.update_greeting_route, {
            file_name: updatedGreeting.value,
            new_name: updatedGreeting.label
        })
        .then(async (response) => {
            if (response.data.success) {

                form$.value.el$('ring_group_greeting').clear()

                await form$.value.el$('ring_group_greeting').updateItems();

                form$.value.update({
                    ring_group_greeting: updatedGreeting.value,
                });

                emits('success', 'success', response.data.messages);
            }
        })
        .catch((error) => {
            emits('error', error);
        })
        .finally(() => {
            isGreetingUpdating.value = false;
            showEditModal.value = false;
        });
};

const deleteGreeting = () => {
    stopGreetingAudio();
    showGreetingDeleteConfirmationModal.value = true;
};

const confirmGreetingDeleteAction = async () => {
    const fileName = getSelectedGreetingFileName();

    if (!fileName) {
        showGreetingDeleteConfirmationModal.value = false;
        return;
    }

    axios
        .post(localOptions.routes.delete_greeting_route, { file_name: fileName })
        .then(async (response) => {
            if (response.data.success) {
                stopGreetingAudio();

                if (availableGreetings.value) {
                    availableGreetings.value = availableGreetings.value.filter(
                        (greeting) => String(greeting.value) !== String(fileName)
                    );
                }

                form$.value.update({
                    ring_group_greeting: '0',
                });

                await form$.value.el$('ring_group_greeting').updateItems();

                emits('success', 'success', response.data.messages);
            }
        })
        .catch((error) => {
            emits('error', error);
        })
        .finally(() => {
            showGreetingDeleteConfirmationModal.value = false;
        });
};

const getRoutesForGreetingForm = computed(() => {
    const routes = localOptions.routes ?? {}
    return {
        ...routes,
        text_to_speech_route: routes.text_to_speech_route ?? null,
        upload_greeting_route: routes.upload_greeting_route ?? null,
    };
});

</script>

<style scoped>
.password-field {
    -webkit-text-security: disc;
    -moz-text-security: disc;
}
</style>