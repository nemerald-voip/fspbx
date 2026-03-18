<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10"
            :inert="showNewGreetingModal || showDeleteConfirmationModal || showAddKeyModal || showEditKeyModal">
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
                                    @click="handleClose">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full h-full">
                                <div class="flex justify-center items-center space-x-3">
                                    <div>
                                        <svg class="animate-spin h-10 w-10 text-blue-600"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                        </svg>
                                    </div>
                                    <div class="text-lg text-blue-600 m-auto">Loading...</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                :default="defaultFormData" :float-placeholders="false">

                                <template #empty>
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="settings" label="Settings" :elements="[
                                                    'settings_title',
                                                    'uuid_clean',
                                                    'ivr_menu_enabled',
                                                    'ivr_menu_name',
                                                    'ivr_menu_extension',
                                                    'ivr_menu_description',
                                                    'divider1',
                                                    'ivr_greetings_title',
                                                    'ivr_menu_greet_long',
                                                    'ivr_action_buttons',
                                                    'greeting_description',
                                                    'divider2',
                                                    'input_handling_title',
                                                    'repeat_prompt',
                                                    'exit_action',
                                                    'exit_target_uuid',
                                                    'container_settings',
                                                    'submit_settings',
                                                ]" />

                                                <FormTab name="keys" label="Keys" :elements="[
                                                    'keys_title',
                                                    'ivr_keys',
                                                    'add_key_button',
                                                ]" :conditions="[() => !!options?.item?.ivr_menu_uuid]" />

                                                <FormTab name="advanced" label="Advanced" :elements="[
                                                    'advanced_title',
                                                    'caller_id_prefix',
                                                    'digit_length',
                                                    'prompt_timeout',
                                                    'pin',
                                                    'ring_back_tone',
                                                    'ring_back_tone_actions',
                                                    'invalid_input_message',
                                                    'invalid_input_actions',
                                                    'exit_message',
                                                    'exit_message_actions',
                                                    'direct_dial',
                                                    'voicemail_play_recording_instructions',
                                                    'container_advanced',
                                                    'submit_advanced',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>

                                                <HiddenElement name="ivr_menu_uuid" :meta="true" />

                                                <StaticElement name="settings_title" tag="h4" content="Settings"
                                                    description="Configure the main settings for this virtual receptionist." />

                                                <StaticElement name="uuid_clean"
                                                    :conditions="[() => options?.permissions?.is_superadmin ?? false]">
                                                    <div class="mb-1">
                                                        <div class="text-sm font-medium text-gray-600 mb-1">
                                                            Unique ID
                                                        </div>

                                                        <div class="flex items-center group">
                                                            <span class="text-sm text-gray-900 select-all font-normal">
                                                                {{ options?.item?.ivr_menu_uuid ?? null }}
                                                            </span>

                                                            <button type="button"
                                                                @click="handleCopyToClipboard(options?.item?.ivr_menu_uuid ?? null)"
                                                                class="ml-2 p-1 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                                                title="Copy to clipboard">
                                                                <ClipboardDocumentIcon
                                                                    class="h-4 w-4 text-gray-500 hover:text-gray-900 cursor-pointer" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </StaticElement>

                                                <ToggleElement name="ivr_menu_enabled" text="Status" true-value="true"
                                                    false-value="false" default="true" />

                                                <TextElement name="ivr_menu_name" label="Name"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="ivr_menu_extension" label="Extension"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="ivr_menu_description" label="Description"
                                                    placeholder="Enter description" :floating="false" />

                                                <StaticElement name="divider1" tag="hr" />

                                                <StaticElement name="ivr_greetings_title" tag="h4"
                                                    content="Audio Prompt"
                                                    description="Customize the audio callers hear when they reach your virtual receptionist." />

                                                <SelectElement name="ivr_menu_greet_long" :search="true" :native="false"
                                                    label="Select Prompt" :items="fetchGreetings" input-type="search"
                                                    autocomplete="off" placeholder="Select Prompt" :floating="false"
                                                    :strict="false" :columns="{ sm: { container: 6 } }">
                                                    <template #after>
                                                        <span v-if="greetingTranscription" class="text-xs italic">
                                                            {{ greetingTranscription }}
                                                        </span>
                                                    </template>
                                                </SelectElement>

                                                <GroupElement name="ivr_action_buttons" :columns="{ container: 6 }">
                                                    <ButtonElement v-if="!isAudioPlaying" @click="playGreeting"
                                                        name="play_button" label="&nbsp;" :secondary="true"
                                                        :columns="{ container: 2 }" :conditions="[hasPlayableGreeting]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PlayCircleIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>

                                                    <ButtonElement v-if="isAudioPlaying" @click="pauseGreeting"
                                                        name="pause_button" label="&nbsp;" :secondary="true"
                                                        :columns="{ container: 2 }"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PauseCircleIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>

                                                    <ButtonElement v-if="!isDownloading" @click="downloadGreeting"
                                                        name="download_button" label="&nbsp;" :secondary="true"
                                                        :columns="{ container: 2 }" :conditions="[hasPlayableGreeting]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <CloudArrowDownIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>

                                                    <ButtonElement v-if="isDownloading" name="download_spinner_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <Spinner :show="true"
                                                            class="h-8 w-8 ml-0 mr-0 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400" />
                                                    </ButtonElement>

                                                    <ButtonElement @click="deleteGreeting" name="delete_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :conditions="[hasPlayableGreeting]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <TrashIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>

                                                    <ButtonElement @click="handleNewGreetingButtonClick"
                                                        name="add_button" label="&nbsp;" :secondary="true"
                                                        :columns="{ container: 2 }"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PlusIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>
                                                </GroupElement>



                                                <StaticElement name="divider2" tag="hr" />

                                                <StaticElement name="input_handling_title" tag="h4"
                                                    content="No Input / Invalid Input"
                                                    description="Choose how many attempts callers get and where they should be routed if no valid input is received." />

                                                <SelectElement name="repeat_prompt"
                                                    :items="options.prompt_repeat_options" :search="true"
                                                    :native="false" label="Attempts Allowed" input-type="search"
                                                    autocomplete="off" value-prop="value" label-prop="label"
                                                    placeholder="Select number of attempts"
                                                    :columns="{ sm: { wrapper: 6 } }" />

                                                <SelectElement name="exit_action" :items="options.routing_types"
                                                    label-prop="name" :search="true" :native="false"
                                                    label="After Max Attempts, Route To" input-type="search"
                                                    autocomplete="off" placeholder="Choose Action" :strict="false"
                                                    :columns="{ sm: { container: 6 } }"
                                                    @change="handleExitActionChange" />

                                                <SelectElement name="exit_target_uuid" :items="fetchExitTargets"
                                                    :search="true" :native="false" label="Target" input-type="search"
                                                    autocomplete="off" label-prop="name" allow-absent :object="true"
                                                    :format-data="formatTarget" placeholder="Choose Target"
                                                    :floating="false" :strict="false"
                                                    :columns="{ sm: { container: 6 } }" :conditions="[
                                                        ['exit_action', 'not_empty'],
                                                        ['exit_action', 'not_in', ['check_voicemail', 'company_directory', 'hangup']]
                                                    ]" />


                                                <GroupElement name="container_settings" />
                                                <ButtonElement name="submit_settings" button-label="Save"
                                                    :submits="true" align="right" />

                                                <!-- Keys -->
                                                <StaticElement name="keys_title" tag="h4" content="Keys"
                                                    description="Manage keypad options for this virtual receptionist." />

                                                <StaticElement name="ivr_keys">
                                                    <IvrOptions v-model="localIvrOptions"
                                                        :routingTypes="options.routing_types"
                                                        :optionsUrl="options.routes.get_routing_options"
                                                        @add-key="handleAddKey" @delete-key="handleDeleteKeyRequest"
                                                        @edit-key="handleEditKey" :isDeleting="showKeyDeletingStatus" />
                                                </StaticElement>

                                                

                                                <!-- Advanced -->
                                                <StaticElement name="advanced_title" tag="h4" content="Advanced"
                                                    description="Set advanced settings for this virtual receptionist." />

                                                <TextElement name="caller_id_prefix" label="Caller ID Name Prefix"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="digit_length" label="Digit Length"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="prompt_timeout" label="Input Timeout (ms)"
                                                    placeholder="3000" :columns="{ sm: { container: 6 } }"
                                                    description="How long to wait for caller input before counting it as no input." />

                                                <TextElement name="pin" label="PIN Number"
                                                    :columns="{ sm: { container: 6 } }"
                                                    description="Use a PIN to protect this menu from unauthorized access." />

                                                <SelectElement name="ring_back_tone" :items="options.ring_back_tones"
                                                    :search="true" :native="false" label="Ring Back Tone" :groups="true"
                                                    input-type="search" autocomplete="off"
                                                    placeholder="Choose an option" :strict="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="ring_back_tone_actions" :columns="{ container: 6 }">
                                                    <ButtonElement v-if="!isRingBackTonePlaying"
                                                        @click="playRingBackTone" name="play_ringback_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :conditions="[hasPlayableRingBackTone]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PlayCircleIcon
                                                            class="h-8 w-8 shrink-0 py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 cursor-pointer" />
                                                    </ButtonElement>

                                                    <ButtonElement v-if="isRingBackTonePlaying"
                                                        @click="pauseRingBackTone" name="pause_ringback_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PauseCircleIcon
                                                            class="h-8 w-8 shrink-0 py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 cursor-pointer" />
                                                    </ButtonElement>
                                                </GroupElement>

                                                <SelectElement name="invalid_input_message" :items="options.sounds"
                                                    :groups="true" :search="true" :native="false"
                                                    label="Invalid Input Message" input-type="search" autocomplete="off"
                                                    placeholder="Choose an option" :strict="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="invalid_input_actions" :columns="{ container: 6 }">
                                                    <ButtonElement v-if="!isInvalidInputMessageAudioPlaying"
                                                        @click="playInvalidInputMessage" name="play_invalid_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :conditions="[hasPlayableInvalidInputMessage]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PlayCircleIcon
                                                            class="h-8 w-8 shrink-0 py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 cursor-pointer" />
                                                    </ButtonElement>

                                                    <ButtonElement v-if="isInvalidInputMessageAudioPlaying"
                                                        @click="pauseInvalidInputMessage" name="pause_invalid_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PauseCircleIcon
                                                            class="h-8 w-8 shrink-0 py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 cursor-pointer" />
                                                    </ButtonElement>
                                                </GroupElement>

                                                <SelectElement name="exit_message" :items="options.sounds"
                                                    :groups="true" :search="true" :native="false" label="Exit Message"
                                                    input-type="search" autocomplete="off"
                                                    placeholder="Choose an option" :strict="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="exit_message_actions" :columns="{ container: 6 }">
                                                    <ButtonElement v-if="!isExitMessageAudioPlaying"
                                                        @click="playExitMessage" name="play_exit_button" label="&nbsp;"
                                                        :secondary="true" :columns="{ container: 2 }"
                                                        :conditions="[hasPlayableExitMessage]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PlayCircleIcon
                                                            class="h-8 w-8 shrink-0 py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 cursor-pointer" />
                                                    </ButtonElement>

                                                    <ButtonElement v-if="isExitMessageAudioPlaying"
                                                        @click="pauseExitMessage" name="pause_exit_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PauseCircleIcon
                                                            class="h-8 w-8 shrink-0 py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 cursor-pointer" />
                                                    </ButtonElement>
                                                </GroupElement>

                                                <ToggleElement name="direct_dial" text="Enable Direct Dialing"
                                                    true-value="true" false-value="false"
                                                    description="Allows callers to dial extensions directly." />

                                                <ToggleElement name="voicemail_play_recording_instructions"
                                                    text="Play Recording Instructions" true-value="true"
                                                    false-value="false"
                                                    description='Play a prompt instructing callers to "Record your message after the tone. Stop speaking to end the recording."'
                                                    :conditions="[() => options.permissions.manage_voicemail_recording_instructions]" />

                                                <GroupElement name="container_advanced" />
                                                <ButtonElement name="submit_advanced" button-label="Save"
                                                    :submits="true" align="right" />


                                                <NewGreetingForm :header="'New Greeting Message'"
                                                    :show="showNewGreetingModal" @close="showNewGreetingModal = false"
                                                    :voices="options.voices" :speeds="options.speeds"
                                                    :default_voice="options.default_voice"
                                                    :phone_call_instructions="options.phone_call_instructions"
                                                    :sample_message="options.sample_message"
                                                    :routes="getRoutesForGreetingForm"
                                                    @error="emitErrorToParentFromChild"
                                                    @success="emitSuccessToParentFromChild"
                                                    @saved="handleNewGreetingAdded" />

                                                <AddEditItemModal :customClass="'sm:max-w-lg'" :show="showAddKeyModal"
                                                    :header="'Add Virtual Receptionist Key'" :loading="loadingModal"
                                                    @close="handleModalClose">
                                                    <template #modal-body>
                                                        <CreateVirtualReceptionistKeyForm :options="options"
                                                            :errors="formErrorsFromAxios"
                                                            :is-submitting="submittingKeyCreate"
                                                            @submit="handleCreateKeyRequest" @error="handleKeyFormError"
                                                            @cancel="handleModalClose" />
                                                    </template>
                                                </AddEditItemModal>

                                                <AddEditItemModal :customClass="'sm:max-w-lg'" :show="showEditKeyModal"
                                                    :header="'Edit Virtual Receptionist Key'" :loading="loadingModal"
                                                    @close="handleModalClose">
                                                    <template #modal-body>
                                                        <UpdateVirtualReceptionistKeyForm :options="options"
                                                            :errors="formErrorsFromAxios" :selected-key="selectedKey"
                                                            :is-submitting="submittingKeyUpdate"
                                                            @submit="handleUpdateKeyRequest" @error="handleKeyFormError"
                                                            @cancel="handleModalClose" />
                                                    </template>
                                                </AddEditItemModal>
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

    <ConfirmationModal :show="showDeleteConfirmationModal" @close="showDeleteConfirmationModal = false"
        @confirm="confirmGreetingDeleteAction" :header="'Confirm Deletion'"
        :text="'This action will permanently delete this greeting. Are you sure you want to proceed?'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" />

</template>

<script setup>
import { ref, computed, watch, reactive, onBeforeUnmount } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import {
    XMarkIcon,
    PlayCircleIcon,
    PauseCircleIcon,
    CloudArrowDownIcon,
    TrashIcon,
    PlusIcon,
} from "@heroicons/vue/24/solid";
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import Spinner from "@generalComponents/Spinner.vue";
import ConfirmationModal from "../modal/ConfirmationModal.vue";
import NewGreetingForm from './NewGreetingForm.vue';
import IvrOptions from "../general/IvrOptions.vue";
import AddEditItemModal from "../modal/AddEditItemModal.vue";
import CreateVirtualReceptionistKeyForm from "../forms/CreateVirtualReceptionistKeyForm.vue";
import UpdateVirtualReceptionistKeyForm from "../forms/UpdateVirtualReceptionistKeyForm.vue";

const props = defineProps({
    show: Boolean,
    options: Object,
    header: String,
    loading: Boolean,
});

const emit = defineEmits(['close', 'error', 'success', 'refresh-data']);

const form$ = ref(null);
const availableGreetings = ref(null);
const showNewGreetingModal = ref(false);
const showDeleteConfirmationModal = ref(false);
const isDownloading = ref(false);
const isAudioPlaying = ref(false);
const currentAudio = ref(null);
const currentAudioGreeting = ref(null);

const currentInvalidInputMessageAudio = ref(null);
const isInvalidInputMessageAudioPlaying = ref(false);

const currentExitMessageAudio = ref(null);
const isExitMessageAudioPlaying = ref(false);

const currentRingBackToneAudio = ref(null);
const isRingBackTonePlaying = ref(false);

const localIvrOptions = ref(props.options?.item?.options ?? []);
const showKeyDeletingStatus = ref(false);
const selectedKey = ref(null);
const showEditKeyModal = ref(false);
const showAddKeyModal = ref(false);
const loadingModal = ref(false);
const submittingKeyUpdate = ref(false);
const submittingKeyCreate = ref(false);
const formErrorsFromAxios = ref({});

watch(
    () => props.options?.item?.options,
    (newVal) => {
        localIvrOptions.value = newVal ?? [];
    },
    { immediate: true }
);

const defaultFormData = computed(() => ({
    ivr_menu_uuid: props.options?.item?.ivr_menu_uuid ?? '',
    ivr_menu_name: props.options?.item?.ivr_menu_name ?? '',
    ivr_menu_extension: props.options?.item?.ivr_menu_extension ?? '',
    ivr_menu_description: props.options?.item?.ivr_menu_description ?? '',
    ivr_menu_enabled: props.options?.item?.ivr_menu_enabled ?? 'true',
    ivr_menu_greet_long: props.options?.item?.ivr_menu_greet_long ?? null,
    repeat_prompt: props.options?.item?.repeat_prompt ?? props.options?.item?.ivr_menu_max_timeouts ?? '3',
    prompt_timeout: props.options?.item?.ivr_menu_timeout ?? '3000',
    direct_dial: props.options?.item?.ivr_menu_direct_dial ?? 'false',
    caller_id_prefix: props.options?.item?.ivr_menu_cid_prefix ?? '',
    pin: props.options?.item?.ivr_menu_pin_number ?? '',
    digit_length: props.options?.item?.ivr_menu_digit_len ?? '5',
    ring_back_tone: props.options?.item?.ivr_menu_ringback ?? '${us-ring}',
    invalid_input_message: props.options?.item?.ivr_menu_invalid_sound ?? '',
    exit_message: props.options?.item?.ivr_menu_exit_sound ?? 'silence_stream://100',
    exit_action: props.options?.item?.exit_action ?? '',
    exit_target_uuid: props.options?.item?.exit_action && !['check_voicemail', 'company_directory', 'hangup'].includes(props.options?.item?.exit_action)
        ? {
            value: props.options?.item?.exit_target_uuid ?? null,
            extension: props.options?.item?.exit_target_extension ?? null,
            name: props.options?.item?.exit_target_name ?? null,
        }
        : null,
    voicemail_play_recording_instructions: props.options?.item?.voicemail_play_recording_instructions ?? 'false',
}));

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emit('success', 'success', { message: ['Copied to clipboard.'] });
    }).catch(() => {
        emit('error', { response: { data: { errors: { request: ['Failed to copy to clipboard.'] } } } });
    });
};

const fetchGreetings = async () => {
    const route = props.options?.routes?.get_greetings_route;

    if (!route) {
        availableGreetings.value = [];
        return [];
    }

    try {
        const response = await axios.get(route);
        availableGreetings.value = response.data;
        return response.data;
    } catch (error) {
        emit('error', error);
        availableGreetings.value = [];
        return [];
    }
};

const greetingTranscription = computed(() => {
    const selectedId = form$.value?.data?.ivr_menu_greet_long ?? null;
    if (!selectedId || !availableGreetings.value) return null;

    const selectedItem = availableGreetings.value.find(
        (item) => String(item.value) === String(selectedId)
    );

    return selectedItem?.description || null;
});

const formatTarget = (name, value) => {
    return { [name]: value?.extension ?? null };
};

const submitForm = async (FormData, form$) => {
    const requestData = form$.requestData;

    if (requestData.exit_target_uuid && typeof requestData.exit_target_uuid === 'object') {
        requestData.exit_target_extension = requestData.exit_target_uuid.extension ?? null;
        requestData.exit_target_uuid = requestData.exit_target_uuid.value ?? null;
    } else {
        requestData.exit_target_extension = null;
    }

    return await form$.$vueform.services.axios.put(
        props.options.routes.update_route,
        requestData
    );
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear();

    if (el$.children$) {
        Object.values(el$.children$).forEach(childEl$ => {
            clearErrorsRecursive(childEl$);
        });
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach(el$ => {
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

    switch (details.type) {
        case 'prepare':
            form$.messageBag.append('Could not prepare form');
            break;
        case 'submit':
            emit('error', error);
            break;
        case 'cancel':
            form$.messageBag.append('Request cancelled');
            break;
        case 'other':
            form$.messageBag.append('Couldn’t submit form');
            break;
    }
};

const handleClose = () => {
    stopGreetingAudio();
    stopInvalidInputMessageAudio();
    stopExitMessageAudio();
    stopRingBackToneAudio();
    emit('close');
};

onBeforeUnmount(() => {
    stopGreetingAudio();
    stopInvalidInputMessageAudio();
    stopExitMessageAudio();
    stopRingBackToneAudio();
});

const getSelectedGreetingId = () => {
    return form$.value?.data?.ivr_menu_greet_long ?? null;
};

const hasPlayableGreeting = (form$) => {
    const val = form$?.el$('ivr_menu_greet_long')?.value ?? null;
    return val !== null && val !== '';
};

const handleNewGreetingButtonClick = () => {
    stopGreetingAudio();
    showNewGreetingModal.value = true;
};

const handleNewGreetingAdded = async (greetingId) => {
    stopGreetingAudio();

    await form$.value.el$('ivr_menu_greet_long').updateItems();
    form$.value.update({
        ivr_menu_greet_long: greetingId,
    });

    if (props.options?.item?.ivr_menu_uuid && props.options?.routes?.apply_greeting_route) {
        axios.post(props.options.routes.apply_greeting_route, {
            ivr: props.options.item.ivr_menu_uuid,
            file_name: greetingId,
        }).then((response) => {
            if (response.data.success) {
                emit('success', 'success', response.data.messages);
            }
        }).catch((error) => {
            emit('error', error);
        });
    }
};

const playGreeting = () => {
    const greeting = getSelectedGreetingId();
    if (!greeting) return;

    if (currentAudio.value && currentAudioGreeting.value === greeting) {
        if (currentAudio.value.paused) {
            currentAudio.value.play();
            isAudioPlaying.value = true;
        }
        return;
    }

    stopGreetingAudio();

    axios.post(props.options.routes.greeting_route, { file_name: greeting })
        .then((response) => {
            if (response.data.success) {
                isAudioPlaying.value = true;
                currentAudio.value = new Audio(response.data.file_url);
                currentAudioGreeting.value = greeting;

                currentAudio.value.play().catch(() => {
                    isAudioPlaying.value = false;
                    emit('error', { message: 'Audio playback failed' });
                });

                currentAudio.value.addEventListener('ended', () => {
                    isAudioPlaying.value = false;
                });
            }
        })
        .catch((error) => {
            emit('error', error);
        });
};

const pauseGreeting = () => {
    if (currentAudio.value) {
        currentAudio.value.pause();
        isAudioPlaying.value = false;
    }
};

const stopGreetingAudio = () => {
    if (currentAudio.value) {
        currentAudio.value.pause();
        currentAudio.value.currentTime = 0;
        currentAudio.value = null;
    }

    isAudioPlaying.value = false;
    currentAudioGreeting.value = null;
};

const downloadGreeting = () => {
    isDownloading.value = true;

    const greeting = getSelectedGreetingId();
    if (!greeting) {
        isDownloading.value = false;
        return;
    }

    axios.post(props.options.routes.greeting_route, { file_name: greeting })
        .then((response) => {
            if (response.data.success) {
                const downloadUrl = `${response.data.file_url}?download=true`;

                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = response.data.file_name || 'greeting.wav';

                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        })
        .catch((error) => {
            emit('error', error);
        })
        .finally(() => {
            isDownloading.value = false;
        });
};

const deleteGreeting = () => {
    stopGreetingAudio();
    showDeleteConfirmationModal.value = true;
};

const confirmGreetingDeleteAction = async () => {
    const greetingId = getSelectedGreetingId();

    if (!greetingId) {
        showDeleteConfirmationModal.value = false;
        return;
    }

    axios.post(props.options.routes.delete_greeting_route, { file_name: greetingId })
        .then(async (response) => {
            if (response.data.success) {
                if (availableGreetings.value) {
                    availableGreetings.value = availableGreetings.value.filter(
                        (greeting) => String(greeting.value) !== String(greetingId)
                    );
                }

                await form$.value.el$('ivr_menu_greet_long').clear();
                await form$.value.el$('ivr_menu_greet_long').updateItems();

                form$.value.update({
                    ivr_menu_greet_long: null,
                });

                emit('success', 'success', response.data.messages);
            }
        })
        .catch((error) => {
            emit('error', error);
        })
        .finally(() => {
            showDeleteConfirmationModal.value = false;
        });
};

const getRoutesForGreetingForm = computed(() => {
    const routes = props.options?.routes ?? {};

    return {
        ...routes,
        text_to_speech_route: routes.text_to_speech_route ?? null,
        upload_greeting_route: routes.upload_greeting_route ?? null,
    };
});

const emitErrorToParentFromChild = (error) => {
    emit('error', error);
};

const emitSuccessToParentFromChild = (message) => {
    emit('success', 'success', message);
};

const handleExitActionChange = (newValue, oldValue, el$) => {
    const target = el$.form$.el$('exit_target_uuid');

    if (oldValue !== null && oldValue !== undefined) {
        target.clear();
    }

    target.updateItems();
};

const fetchExitTargets = async (query, input) => {
    const action = input.$parent.el$.form$.el$('exit_action')?.value;

    if (!action || ['check_voicemail', 'company_directory', 'hangup'].includes(action)) {
        return [];
    }

    try {
        const response = await axios.post(
            props.options.routes.get_routing_options,
            { category: action }
        );
        return response.data.options;
    } catch (error) {
        emit('error', error);
        return [];
    }
};

const getSoundFileUrl = async (fileName) => {
    if (!fileName) return null;
    const response = await axios.post(props.options.routes.ivr_message_route, { file_name: fileName });
    return response.data?.success ? response.data.file_url : null;
};

const stopInvalidInputMessageAudio = () => {
    if (currentInvalidInputMessageAudio.value) {
        currentInvalidInputMessageAudio.value.pause();
        currentInvalidInputMessageAudio.value.currentTime = 0;
        currentInvalidInputMessageAudio.value = null;
    }
    isInvalidInputMessageAudioPlaying.value = false;
};

const stopExitMessageAudio = () => {
    if (currentExitMessageAudio.value) {
        currentExitMessageAudio.value.pause();
        currentExitMessageAudio.value.currentTime = 0;
        currentExitMessageAudio.value = null;
    }
    isExitMessageAudioPlaying.value = false;
};

const stopRingBackToneAudio = () => {
    if (currentRingBackToneAudio.value) {
        currentRingBackToneAudio.value.pause();
        currentRingBackToneAudio.value.currentTime = 0;
        currentRingBackToneAudio.value = null;
    }
    isRingBackTonePlaying.value = false;
};

const hasPlayableInvalidInputMessage = (form$) => !!form$?.el$('invalid_input_message')?.value;
const hasPlayableExitMessage = (form$) => !!form$?.el$('exit_message')?.value;
const hasPlayableRingBackTone = (form$) => {
    const val = form$?.el$('ring_back_tone')?.value ?? '';
    return !!val && (val.endsWith('.wav') || val.endsWith('.mp3'));
};

const playInvalidInputMessage = async () => {
    stopExitMessageAudio();
    stopRingBackToneAudio();

    if (currentInvalidInputMessageAudio.value && currentInvalidInputMessageAudio.value.paused) {
        currentInvalidInputMessageAudio.value.play();
        isInvalidInputMessageAudioPlaying.value = true;
        return;
    }

    try {
        const fileName = form$.value?.data?.invalid_input_message;
        const fileUrl = await getSoundFileUrl(fileName);
        if (!fileUrl) return;

        stopInvalidInputMessageAudio();

        currentInvalidInputMessageAudio.value = new Audio(fileUrl);
        isInvalidInputMessageAudioPlaying.value = true;

        currentInvalidInputMessageAudio.value.play().catch(() => {
            isInvalidInputMessageAudioPlaying.value = false;
            emit('error', { message: 'Audio playback failed' });
        });

        currentInvalidInputMessageAudio.value.addEventListener('ended', () => {
            isInvalidInputMessageAudioPlaying.value = false;
        });
    } catch (error) {
        emit('error', error);
    }
};

const pauseInvalidInputMessage = () => {
    if (currentInvalidInputMessageAudio.value) {
        currentInvalidInputMessageAudio.value.pause();
        isInvalidInputMessageAudioPlaying.value = false;
    }
};

const playExitMessage = async () => {
    stopInvalidInputMessageAudio();
    stopRingBackToneAudio();

    if (currentExitMessageAudio.value && currentExitMessageAudio.value.paused) {
        currentExitMessageAudio.value.play();
        isExitMessageAudioPlaying.value = true;
        return;
    }

    try {
        const fileName = form$.value?.data?.exit_message;
        const fileUrl = await getSoundFileUrl(fileName);
        if (!fileUrl) return;

        stopExitMessageAudio();

        currentExitMessageAudio.value = new Audio(fileUrl);
        isExitMessageAudioPlaying.value = true;

        currentExitMessageAudio.value.play().catch(() => {
            isExitMessageAudioPlaying.value = false;
            emit('error', { message: 'Audio playback failed' });
        });

        currentExitMessageAudio.value.addEventListener('ended', () => {
            isExitMessageAudioPlaying.value = false;
        });
    } catch (error) {
        emit('error', error);
    }
};

const pauseExitMessage = () => {
    if (currentExitMessageAudio.value) {
        currentExitMessageAudio.value.pause();
        isExitMessageAudioPlaying.value = false;
    }
};

const playRingBackTone = async () => {
    stopInvalidInputMessageAudio();
    stopExitMessageAudio();

    if (currentRingBackToneAudio.value && currentRingBackToneAudio.value.paused) {
        currentRingBackToneAudio.value.play();
        isRingBackTonePlaying.value = true;
        return;
    }

    try {
        const filePath = form$.value?.data?.ring_back_tone;
        if (!filePath) return;

        const fileName = filePath.substring(filePath.lastIndexOf('/') + 1);
        const response = await axios.post(props.options.routes.greeting_route, { file_name: fileName });

        if (!response.data?.success) return;

        stopRingBackToneAudio();

        currentRingBackToneAudio.value = new Audio(response.data.file_url);
        isRingBackTonePlaying.value = true;

        currentRingBackToneAudio.value.play().catch(() => {
            isRingBackTonePlaying.value = false;
            emit('error', { message: 'Audio playback failed' });
        });

        currentRingBackToneAudio.value.addEventListener('ended', () => {
            isRingBackTonePlaying.value = false;
        });
    } catch (error) {
        emit('error', error);
    }
};

const pauseRingBackTone = () => {
    if (currentRingBackToneAudio.value) {
        currentRingBackToneAudio.value.pause();
        isRingBackTonePlaying.value = false;
    }
};

const handleAddKey = () => {
    formErrorsFromAxios.value = {};
    showAddKeyModal.value = true;
};

const handleEditKey = (option) => {
    formErrorsFromAxios.value = {};

    const matchedKey = props.options?.item?.options?.find(
        (ivr) => ivr.ivr_menu_option_uuid === option.ivr_menu_option_uuid
    );

    if (matchedKey) {
        selectedKey.value = matchedKey;
        showEditKeyModal.value = true;
    } else {
        emit('error', { response: { data: { errors: { request: ['Matching key not found.'] } } } });
    }
};

const handleCreateKeyRequest = (payload) => {
    submittingKeyCreate.value = true;
    formErrorsFromAxios.value = {};

    axios.post(props.options.routes.create_key_route, payload)
        .then((response) => {
            emit('success', 'success', response.data.messages);
            emit('refresh-data', props.options?.item?.ivr_menu_uuid);
            handleModalClose();
        })
        .catch((error) => {
            formErrorsFromAxios.value = error?.response?.data?.errors ?? {};
            emit('error', error);
        })
        .finally(() => {
            submittingKeyCreate.value = false;
        });
};

const handleUpdateKeyRequest = (payload) => {
    submittingKeyUpdate.value = true;
    formErrorsFromAxios.value = {};

    axios.put(props.options.routes.update_key_route, payload)
        .then((response) => {
            emit('success', 'success', response.data.messages);
            emit('refresh-data', props.options?.item?.ivr_menu_uuid);
            handleModalClose();
        })
        .catch((error) => {
            formErrorsFromAxios.value = error?.response?.data?.errors ?? {};
            emit('error', error);
        })
        .finally(() => {
            submittingKeyUpdate.value = false;
        });
};

const handleDeleteKeyRequest = (key) => {
    showKeyDeletingStatus.value = true;

    axios.post(props.options.routes.delete_key_route, key)
        .then((response) => {
            emit('success', 'success', response.data.messages);
            emit('refresh-data', props.options?.item?.ivr_menu_uuid);
        })
        .catch((error) => {
            emit('error', error);
        })
        .finally(() => {
            showKeyDeletingStatus.value = false;
        });
};

const handleModalClose = () => {
    showEditKeyModal.value = false;
    showAddKeyModal.value = false;
};

const handleKeyFormError = (error) => {
    emit('error', error);
};
</script>

<style>
div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}
</style>