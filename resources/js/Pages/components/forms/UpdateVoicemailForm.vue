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
                            class="relative transform  rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl sm:p-6">

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
                                        <svg class="animate-spin  h-10 w-10 text-blue-600"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4">
                                            </circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                    </div>
                                    <div class="text-lg text-blue-600 m-auto">Loading...</div>
                                </div>
                            </div>


                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                :float-placeholders="true" @error="handleError" @response="handleResponse"
                                :display-errors="false" :default="{
                                    voicemail_id: options.item?.voicemail_id ?? '',
                                    voicemail_password: options.item?.voicemail_password ?? null,
                                    voicemail_mail_to: options.item?.voicemail_mail_to,
                                    domain_uuid: options.item?.domain_uuid,
                                    voicemail_enabled: options.item?.voicemail_enabled ?? 'false',
                                    voicemail_description: options.item?.voicemail_description ?? '',
                                    voicemail_transcription_enabled: options.item?.voicemail_transcription_enabled ?? 'true',
                                    voicemail_file: options.item?.voicemail_file === 'attach' ? 'attach' : '',
                                    voicemail_local_after_email: options.item?.voicemail_local_after_email ?? 'true',
                                    voicemail_copies: options.voicemail_copies ?? [],
                                    greeting_id: options.item?.greeting_id ?? null,
                                    voicemail_tutorial: options.item?.voicemail_tutorial ?? 'false',
                                    voicemail_recording_instructions: options.item?.voicemail_recording_instructions ?? 'true',
                                    voicemail_sms_to: options.item?.voicemail_sms_to ?? '',
                                    voicemail_alternate_greet_id: options.item?.voicemail_alternate_greet_id ?? '',

                                    // VM escalation
                                    vm_notify_profile: {
                                        enabled: options.vm_notify_profile?.enabled ?? false,
                                        name: options.vm_notify_profile?.name ?? '',
                                        description: options.vm_notify_profile?.description ?? '',
                                        outbound_cid_mode: options.vm_notify_profile?.outbound_cid_mode ?? 'default',
                                        caller_id_number: options.vm_notify_profile?.caller_id_number ?? '',
                                        caller_id_name: options.vm_notify_profile?.caller_id_name ?? '',
                                        retry_count: options.vm_notify_profile?.retry_count ?? 2,
                                        retry_delay_minutes: options.vm_notify_profile?.retry_delay_minutes ?? 5,
                                        priority_delay_minutes: options.vm_notify_profile?.priority_delay_minutes ?? 1,
                                        email_success: options.vm_notify_profile?.email_success ?? [],
                                        email_fail: options.vm_notify_profile?.email_fail ?? [],
                                        email_attach: options.vm_notify_profile?.email_attach ?? false,
                                        selected_recipients: [],
                                        recipients: (options.vm_notify_profile?.recipients ?? []).map((recipient) => ({
                                            vm_notify_profile_recipient_uuid: recipient.vm_notify_profile_recipient_uuid ?? null,
                                            recipient_type: recipient.recipient_type ?? null,
                                            extension_uuid: recipient.extension_uuid ?? null,
                                            phone_number: recipient.phone_number ?? null,
                                            display_name: recipient.display_name ?? null,
                                            priority: recipient.priority ?? 0,
                                            sort_order: recipient.sort_order ?? 0,
                                            enabled: recipient.enabled ?? true,
                                        })),
                                    },
                                }">

                                <template #empty>

                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="settings" label="Settings" :elements="[
                                                    'voicemail_title',
                                                    'uuid_clean',
                                                    'voicemail_enabled',
                                                    'voicemail_id',
                                                    'voicemail_password',
                                                    'voicemail_mail_to',
                                                    'voicemail_description',
                                                    'voicemail_transcription_enabled',
                                                    'voicemail_file',
                                                    'voicemail_local_after_email',
                                                    'voicemail_copies',
                                                    'divider1',
                                                    'divider2',
                                                    'container_settings',
                                                    'submit_settings'

                                                ]" />
                                                <FormTab name="greetings" label="Greetings" :elements="[
                                                    'voicemail_greetings_title',
                                                    'greeting_id',
                                                    'voicemail_action_buttons',
                                                    'divider13',
                                                    'voicemail_name_title',
                                                    'name_greeting_title',
                                                    'voicemail_name_action_buttons',
                                                    'submit_greetings',
                                                ]" :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <FormTab name="advanced" label="Advanced" :elements="[
                                                    'voicemail_advanced_title',
                                                    'voicemail_tutorial',
                                                    'divider15',
                                                    'voicemail_recording_instructions',
                                                    'divider18',
                                                    'voicemail_sms_to',
                                                    'voicemail_alternate_greet_id',
                                                    'container_advanced',
                                                    'submit_advanced',

                                                ]" :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <FormTab name="escalation" label="Escalation" :elements="[
                                                    'vm_notify_profile',
                                                    'submit_escalation',
                                                ]" :conditions="[['voicemail_enabled', '==', 'true']]" />

                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>

                                                <StaticElement name="voicemail_title" tag="h4" content="Settings"
                                                    description="Customize voicemail preferences" />
                                                <StaticElement name="uuid_clean"
                                                    :conditions="[() => options?.permissions?.is_superadmin ?? false]">

                                                    <div class="mb-1">
                                                        <div class="text-sm font-medium text-gray-600 mb-1">
                                                            Unique ID
                                                        </div>

                                                        <div class="flex items-center group">
                                                            <span class="text-sm text-gray-900 select-all font-normal">
                                                                {{ options?.item?.voicemail_uuid ?? null }}
                                                            </span>

                                                            <button type="button"
                                                                @click="handleCopyToClipboard(options?.item?.voicemail_uuid ?? null)"
                                                                class="ml-2 p-1 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                                                title="Copy to clipboard">
                                                                <!-- Small Copy Icon -->
                                                                <ClipboardDocumentIcon
                                                                    class="h-4 w-4 text-gray-500 hover:text-gray-900  cursor-pointer" />
                                                            </button>
                                                        </div>
                                                    </div>

                                                </StaticElement>


                                                <ToggleElement name="voicemail_enabled" text="Status" true-value="true"
                                                    false-value="false" default="true" />


                                                <TextElement name="voicemail_id" label="Voicemail Extension" :columns="{
                                                    sm: {
                                                        container: 6,
                                                    },
                                                }" :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <TextElement name="voicemail_password" label="Password" :columns="{
                                                    sm: {
                                                        container: 6,
                                                    },
                                                }" :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <TextElement name="voicemail_mail_to" label="Email Address"
                                                    placeholder="Enter email address" :floating="false"
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <TextElement name="voicemail_description" label="Description"
                                                    placeholder="Enter description" :floating="false"
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <ToggleElement name="voicemail_transcription_enabled"
                                                    text="Voicemail Transcription" true-value="true" false-value="false"
                                                    description="Convert voicemail messages to text using AI-powered transcription."
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <StaticElement name="divider1" tag="hr"
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <ToggleElement name="voicemail_file"
                                                    text="Attach File to Email Notifications" true-value="attach"
                                                    false-value=""
                                                    description="Attach voicemail recording file to the email notification."
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <StaticElement name="divider2" tag="hr"
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <ToggleElement name="voicemail_local_after_email"
                                                    text="Automatically Delete Voicemail After Email" true-value="false"
                                                    false-value="true"
                                                    description="Remove voicemail from the cloud once the email is sent."
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <TagsElement name="voicemail_copies" :search="true"
                                                    :items="options.all_voicemails"
                                                    label="Copy Voicemail to Other Extensions" input-type="search"
                                                    autocomplete="off"
                                                    description="Automatically send a copy of the voicemail to selected additional extensions."
                                                    :floating="false" placeholder="Enter name or extension"
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <GroupElement name="container_settings" />

                                                <ButtonElement name="submit_settings" button-label="Save"
                                                    :submits="true" align="right" />


                                                <!-- Voicemail Greetings -->
                                                <StaticElement name="voicemail_greetings_title" tag="h4"
                                                    content="Greetings"
                                                    description="Customize the message that callers hear when they reach your voicemail."
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />


                                                <SelectElement name="greeting_id" :search="true" :native="false"
                                                    label="Select Greeting" :items="fetchGreetings" input-type="search"
                                                    autocomplete="off" placeholder="Select Greeting" :floating="false"
                                                    :strict="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        }
                                                    }" :conditions="[['voicemail_enabled', '==', 'true']]">
                                                    <template #after>
                                                        <span v-if="greetingTranscription" class="text-xs italic">
                                                            "{{ greetingTranscription }}"
                                                        </span>
                                                    </template>
                                                </SelectElement>

                                                <GroupElement name="voicemail_action_buttons"
                                                    :columns="{ container: 6, }"
                                                    :conditions="[['voicemail_enabled', '==', 'true']]">

                                                    <ButtonElement v-if="!isAudioPlaying" @click="playGreeting"
                                                        :columns="{
                                                            container: 2,
                                                        }" name="play_button" label="&nbsp;" :secondary="true"
                                                        :conditions="[hasPlayableGreeting]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PlayCircleIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>


                                                    <ButtonElement v-if="isAudioPlaying" @click="pauseGreeting"
                                                        name="pause_button" label="&nbsp;" :secondary="true" :columns="{
                                                            container: 2,
                                                        }"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PauseCircleIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />

                                                    </ButtonElement>

                                                    <ButtonElement v-if="!isDownloading" @click="downloadGreeting"
                                                        name="download_button" label="&nbsp;" :secondary="true"
                                                        :columns="{
                                                            container: 2,
                                                        }" :conditions="[hasPlayableGreeting]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <CloudArrowDownIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                                    </ButtonElement>

                                                    <ButtonElement v-if="isDownloading" name="download_spinner_button"
                                                        label="&nbsp;" :secondary="true" :columns="{
                                                            container: 2,
                                                        }"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <Spinner :show="true"
                                                            class="h-8 w-8 ml-0 mr-0 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                                    </ButtonElement>

                                                    <!-- <ButtonElement @click="editGreeting" name="edit_button" label="&nbsp;"
                                                        :secondary="true" :columns="{
                                                            container: 2,
                                                        }"
                                                        :conditions="[function (form$) { const val = form$.el$('greeting_id')?.value; return val?.value !== '0' && val?.value !== '-1' && val !== null; }]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PencilSquareIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                                    </ButtonElement> -->

                                                    <ButtonElement @click="deleteGreeting" name="delete_button"
                                                        label="&nbsp;" :secondary="true" :columns="{
                                                            container: 2,
                                                        }" :conditions="[hasPlayableGreeting]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <TrashIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />

                                                    </ButtonElement>

                                                    <ButtonElement @click="handleNewGreetingButtonClick"
                                                        name="add_button" label="&nbsp;" :secondary="true" :columns="{
                                                            container: 2,
                                                        }"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PlusIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                                    </ButtonElement>

                                                </GroupElement>


                                                <StaticElement name="divider13" top="1"
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />


                                                <StaticElement name="voicemail_name_title" tag="h4"
                                                    content="Dial-by-name Directory Name"
                                                    description="Set the recorded name used by the dial-by-name directory to help callers locate this mailbox."
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <StaticElement name="name_greeting_title" :columns="{
                                                    sm: {
                                                        container: 3,
                                                    },
                                                    md: {
                                                        container: 2,
                                                    },
                                                    lg: {
                                                        container: 3,
                                                    },
                                                }" label="Recorded Name" info=""
                                                    :conditions="[['voicemail_enabled', '==', 'true']]">
                                                    <div class="pt-2 flex items-center  whitespace-nowrap space-x-2">
                                                        <!-- <p>Recorded Name:</p> -->
                                                        <Badge v-if="recordedName == 'Custom recording'"
                                                            :text="recordedName" backgroundColor="bg-green-50"
                                                            textColor="text-green-700" ringColor="ring-green-600/20" />

                                                        <Badge v-if="recordedName == 'System Default'"
                                                            :text="recordedName" backgroundColor="bg-blue-50"
                                                            textColor="text-blue-700" ringColor="ring-blue-600/20" />

                                                    </div>
                                                </StaticElement>


                                                <GroupElement name="voicemail_name_action_buttons"
                                                    :conditions="[['voicemail_enabled', '==', 'true']]"
                                                    :columns="{ container: 6, }">

                                                    <ButtonElement v-if="!isNameAudioPlaying" @click="playRecordedName"
                                                        :columns="{
                                                            container: 2,
                                                        }" name="play_name_button" label="&nbsp;" :secondary="true"
                                                        :conditions="[function () { return recordedName == 'Custom recording' }]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PlayCircleIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>


                                                    <ButtonElement v-if="isNameAudioPlaying" @click="pauseRecordedName"
                                                        name="pause_name_button" label="&nbsp;" :secondary="true"
                                                        :columns="{
                                                            container: 2,
                                                        }"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PauseCircleIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />

                                                    </ButtonElement>

                                                    <ButtonElement v-if="!isNameDownloading"
                                                        @click="downloadRecordedName" name="download_name_button"
                                                        label="&nbsp;" :secondary="true" :columns="{
                                                            container: 2,
                                                        }"
                                                        :conditions="[function () { return recordedName == 'Custom recording' }]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <CloudArrowDownIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                                    </ButtonElement>

                                                    <ButtonElement v-if="isNameDownloading"
                                                        name="download_name_spinner_button" label="&nbsp;"
                                                        :secondary="true" :columns="{
                                                            container: 2,
                                                        }"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <Spinner :show="true"
                                                            class="h-8 w-8 ml-0 mr-0 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                                    </ButtonElement>

                                                    <!-- <ButtonElement @click="editGreeting" name="edit_button" label="&nbsp;"
                                                        :secondary="true" :columns="{
                                                            container: 2,
                                                        }"
                                                        :conditions="[function (form$) { const val = form$.el$('greeting_id')?.value; return val?.value !== '0' && val?.value !== '-1' && val !== null; }]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PencilSquareIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                                    </ButtonElement> -->

                                                    <ButtonElement @click="deleteRecordedName" name="delete_name_button"
                                                        label="&nbsp;" :secondary="true" :columns="{
                                                            container: 2,
                                                        }"
                                                        :conditions="[function () { return recordedName == 'Custom recording' }]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <TrashIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />

                                                    </ButtonElement>

                                                    <ButtonElement @click="handleNewNameGreetingButtonClick"
                                                        name="add_name_button" label="&nbsp;" :secondary="true"
                                                        :columns="{
                                                            container: 2,
                                                        }"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PlusIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                                    </ButtonElement>

                                                </GroupElement>

                                                <ButtonElement name="submit_greetings" button-label="Save"
                                                    :submits="true" align="right" />


                                                <!-- Voicemail Advanced -->
                                                <StaticElement name="voicemail_advanced_title" tag="h4"
                                                    content="Advanced"
                                                    description="Set advanced settings for this voicemail."
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />


                                                <ToggleElement name="voicemail_tutorial" text="Play Voicemail Tutorial"
                                                    true-value="true" false-value="false"
                                                    description="Provide user with a guided tutorial when accessing voicemail for the first time."
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <StaticElement name="divider15" tag="hr"
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <ToggleElement name="voicemail_recording_instructions"
                                                    text="Play Recording Instructions" true-value="true"
                                                    false-value="false"
                                                    description='Play a prompt instructing callers to "Record your message after the tone. Stop speaking to end the recording."'
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <StaticElement name="divider18" tag="hr" :conditions="[
                                                    function (form$) {
                                                        return form$.el$('voicemail_enabled')?.value == 'true' && options.permissions.manage_voicemail_mobile_notifications
                                                    }
                                                ]" />

                                                <TextElement name="voicemail_sms_to"
                                                    label="Mobile Number to Receive Voicemail Notifications" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" :conditions="[
                                                        function (form$) {
                                                            return form$.el$('voicemail_enabled')?.value == 'true' && options.permissions.manage_voicemail_mobile_notifications
                                                        }
                                                    ]" />

                                                <TextElement name="voicemail_alternate_greet_id"
                                                    label="Announce Voicemail Extension as"
                                                    description="The parameter allows you to override the voicemail extension number spoken by the system in the voicemail greeting. This controls system greetings that read back an extension number, not user recorded greetings."
                                                    :floating="false" placeholder="Enter extension" :columns="{
                                                        sm: {
                                                            wrapper: 6,
                                                        },
                                                    }" :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <GroupElement name="container_advanced" />

                                                <ButtonElement name="submit_advanced" button-label="Save"
                                                    :submits="true" align="right" />

                                                <NewGreetingForm :header="'New Voicemail Greeting'"
                                                    :show="showNewGreetingModal" @close="showNewGreetingModal = false"
                                                    :voices="options.voices" :speeds="options.speeds"
                                                    :default_voice="options.default_voice"
                                                    :phone_call_instructions="options.phone_call_instructions"
                                                    :sample_message="options.sample_message"
                                                    :routes="getRoutesForGreetingForm"
                                                    @error="emitErrorToParentFromChild"
                                                    @success="emitSuccessToParentFromChild"
                                                    @saved="handleNewGreetingAdded" />

                                                <NewGreetingForm :header="'New Recorded Name'"
                                                    :show="showNewNameGreetingModal"
                                                    @close="showNewNameGreetingModal = false" :voices="options.voices"
                                                    :speeds="options.speeds" :default_voice="options.default_voice"
                                                    :phone_call_instructions="options.phone_call_instructions_for_name"
                                                    :sample_message="options.sample_message"
                                                    :routes="getRoutesForNameForm" @error="emitErrorToParentFromChild"
                                                    @success="emitSuccessToParentFromChild"
                                                    @saved="handleNewNameAdded" />

                                                <ConfirmationModal :show="showDeleteConfirmationModal"
                                                    @close="showDeleteConfirmationModal = false"
                                                    @confirm="confirmGreetingDeleteAction" :header="'Confirm Deletion'"
                                                    :text="'This action will permanently delete this greeting. Are you sure you want to proceed?'"
                                                    :confirm-button-label="'Delete'" cancel-button-label="Cancel" />


                                                <ConfirmationModal :show="showDeleteNameConfirmationModal"
                                                    @close="showDeleteNameConfirmationModal = false"
                                                    @confirm="confirmDeleteNameAction" :header="'Confirm Deletion'"
                                                    :text="'This action will permanently delete the custom recorded name. Are you sure you want to proceed?'"
                                                    :confirm-button-label="'Delete'" cancel-button-label="Cancel" />


                                                <!-- Escalation -->
                                                <ObjectElement name="vm_notify_profile">
                                                    <StaticElement name="voicemail_escalation_title" tag="h4"
                                                        content="Voicemail Escalation"
                                                        description="Call designated recipients when a new voicemail arrives until someone accepts responsibility."
                                                        :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                    <ToggleElement name="enabled" text="Enable Voicemail Escalation"
                                                        :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                    <TextElement name="name" label="Rule Name"
                                                        placeholder="Enter escalation rule name" :floating="false"
                                                        :columns="{ sm: { container: 6 } }"
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <TextElement name="description" label="Description"
                                                        placeholder="Enter description" :floating="false"
                                                        :columns="{ sm: { container: 6 } }"
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <SelectElement name="outbound_cid_mode"
                                                        label="Outbound Caller ID Mode" :items="[
                                                            { value: 'default', label: 'Default' },
                                                            { value: 'mailbox', label: 'Mailbox' },
                                                        ]" :search="true" :native="false" input-type="search"
                                                        autocomplete="off" placeholder="Select Caller ID Mode"
                                                        :floating="false" :columns="{ sm: { wrapper: 6 } }"
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <StaticElement name="caller_id_note" tag="blockquote"
                                                        content="<div>Think of this as the Caller ID of the Voicemail Notification, not the Caller ID for the original caller. This is the caller ID that will be displayed on the recipient&#39;s phone (if supported). <br><strong>Default</strong>: Transmits the Caller ID set below. <br><strong>Mailbox</strong>: Transmits the CID of the extension associated with the mailbox that you are monitoring.</div>"
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />


                                                    <TextElement name="caller_id_number" label="Caller ID Number"
                                                        placeholder="Enter caller ID number" :floating="false"
                                                        :columns="{ sm: { container: 6 } }" :conditions="[
                                                            ['voicemail_enabled', '==', 'true'],
                                                            ['vm_notify_profile.enabled', '==', true],
                                                            ['vm_notify_profile.outbound_cid_mode', '==', 'default']
                                                        ]" />

                                                    <TextElement name="caller_id_name" label="Caller ID Name"
                                                        placeholder="Defaults to mailbox name if blank"
                                                        :floating="false" :columns="{ sm: { container: 6 } }"
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <StaticElement name="divider_vm_notify_1" tag="hr"
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <TextElement name="retry_count" input-type="number"
                                                        label="Retry Count" placeholder="2" :floating="false"
                                                        info="How many times to cycle through calling everyone in the Recipients list again before stopping, if no one accepts the voicemail after the first cycle. A 0 setting means do not retry – only call everyone once then stop. The default setting is 2, meaning the system will make a maximum of 3 total attempts."
                                                        :columns="{ sm: { container: 4 } }"
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <TextElement name="retry_delay_minutes" input-type="number"
                                                        label="Retry Delay (Minutes)" placeholder="5" :floating="false"
                                                        info='How long to wait (in minutes) before retrying, after calling all recipients, if no one accepts the voicemail. (Default = 5 Min.)'
                                                        :columns="{ sm: { container: 4 } }"
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <TextElement name="priority_delay_minutes" input-type="number"
                                                        label="Priority Delay (Minutes)" placeholder="1"
                                                        :floating="false"
                                                        info="How long to wait (in minutes) after trying to call all recipients with the same priority setting before moving on to call the next priority group. This only has an effect if you have recipients with different priority levels."
                                                        :columns="{ sm: { container: 4 } }"
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <GroupElement name="container_vm_notify_1"
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <StaticElement name="divider_vm_notify_2" tag="hr"
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <GroupElement name="container_vm_notify_2"
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <StaticElement name="recipients_title" tag="h4" content="Recipients"
                                                        description="Add internal extensions or external phone numbers. Recipients with the same priority are called at the same time."
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <TagsElement name="selected_recipients" :close-on-select="false"
                                                        :items="availableEscalationRecipients" :create="true"
                                                        :search="true" :groups="true" :native="false"
                                                        label="Add Recipient(s)" input-type="search" autocomplete="off"
                                                        placeholder="Search by name or extension, or enter external number"
                                                        :floating="false" :hide-selected="false" :object="true"
                                                        :group-hide-empty="true" :append-new-option="false"
                                                        :submit="false"
                                                        description="Choose internal extensions or enter an external number manually."
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <ButtonElement @click="addSelectedEscalationRecipients"
                                                        name="add_selected_recipients"
                                                        button-label="Add Selected Recipients" :secondary="true"
                                                        align="center" :full="false"
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <ListElement name="recipients" :sort="true"
                                                        :controls="{ add: false, remove: true, sort: true }"
                                                        :add-classes="{
                                                            ListElement: {
                                                                listItem: 'bg-white p-3 mb-3 border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow'
                                                            }
                                                        }"
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]">
                                                        <template #default="{ index }">
                                                            <!-- sm:items-center forces the 3 columns to vertically center nicely -->
                                                            <ObjectElement :name="index"
                                                                :add-classes="{ ElementLayout: { innerWrapper: 'sm:items-center' } }">
                                                                <HiddenElement name="vm_notify_profile_recipient_uuid"
                                                                    :meta="true" />
                                                                <HiddenElement name="recipient_type" :meta="true" />
                                                                <HiddenElement name="extension_uuid" :meta="true" />
                                                                <HiddenElement name="phone_number" :meta="true" />
                                                                <HiddenElement name="sort_order" :meta="true" />

                                                                <!-- 1. Formatted Recipient Display -->
                                                                <StaticElement name="recipient_label" tag="div"
                                                                    :columns="{ default: { container: 12 }, sm: { container: 5 } }"
                                                                    :content="(el$) => {
                                                                        const row = el$.parent.value;
                                                                        const label = getEscalationRecipientLabel(row);
                                                                        const isInternal = row.recipient_type === 'extension';

                                                                        const icon = isInternal
                                                                            ? `<svg class='w-5 h-5 text-indigo-600' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'></path></svg>`
                                                                            : `<svg class='w-5 h-5 text-emerald-600' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'></path></svg>`;

                                                                        return `
                        <div class='flex items-center space-x-3'>
                            <div class='flex-shrink-0 w-10 h-10 ${isInternal ? 'bg-indigo-50 border-indigo-100' : 'bg-emerald-50 border-emerald-100'} rounded-full flex items-center justify-center border'>
                                ${icon}
                            </div>
                            <div class='flex flex-col min-w-0'>
                                <span class='text-base font-bold text-gray-900 truncate'>${label}</span>
                                <span class='text-xs font-medium text-gray-500'>${isInternal ? 'Internal Extension' : 'External Number'}</span>
                            </div>
                        </div>
                    `;
                                                                    }" />

                                                                <!-- 2. Inline Priority Input -->
                                                                <TextElement name="priority" input-type="number"
                                                                    label="Priority" :columns="{
                                                                        default: { container: 6, label: 4, wrapper: 5 },
                                                                        sm: { container: 4, label: 4, wrapper: 5 }
                                                                    }" :add-classes="{

                                                                        ElementLabel: {
                                                                            container: 'items-center !pt-0 !pb-0', // Centers the text vertically
                                                                            wrapper: 'font-medium text-sm text-gray-500 whitespace-nowrap'
                                                                        },
                                                                        TextElement: { input: 'text-center py-1' }
                                                                    }" />

                                                                <!-- 3. Right-aligned Active Toggle -->
                                                                <ToggleElement name="enabled" text="Active"
                                                                    :columns="{ default: { container: 6 }, sm: { container: 3 } }"
                                                                    size="md" :add-classes="{
                                                                        ElementLayout: {
                                                                            container: 'flex items-center justify-end mt-3 sm:mt-0',
                                                                            innerWrapper: 'flex items-center'
                                                                        }
                                                                    }" />
                                                            </ObjectElement>
                                                        </template>
                                                    </ListElement>
                                                    <StaticElement name="divider_vm_notify_3" tag="hr"
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <TagsElement name="email_success"
                                                        label="Success Notification Emails" :create="true"
                                                        :search="true" allow-absent placeholder="Add email address"
                                                        :floating="false"
                                                        description="Send an email when someone accepts responsibility for the voicemail."
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <TagsElement name="email_fail" label="Failure Notification Emails"
                                                        :create="true" :search="true" allow-absent
                                                        :add-option-on="['enter', 'space', 'tab', ';', ',']"
                                                        placeholder="Add email address" :floating="false"
                                                        description="Send an email when no one accepts responsibility for the voicemail."
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                    <ToggleElement name="email_attach"
                                                        text="Attach Voicemail to Completion Emails"
                                                        :conditions="[['voicemail_enabled', '==', 'true'], ['vm_notify_profile.enabled', '==', true]]" />

                                                </ObjectElement>

                                                <ButtonElement name="submit_escalation" button-label="Save"
                                                    :submits="true" align="right"
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

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
import { ref, watch, computed, onBeforeUnmount } from "vue";
import NewGreetingForm from './NewGreetingForm.vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import {
    XMarkIcon,
    PlayCircleIcon,
    PauseCircleIcon,
    CloudArrowDownIcon,
    TrashIcon,
    PlusIcon
} from "@heroicons/vue/24/solid";
import Spinner from "@generalComponents/Spinner.vue";
import Badge from "@generalComponents/Badge.vue";
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import ConfirmationModal from "../modal/ConfirmationModal.vue";


const props = defineProps({
    show: Boolean,
    options: Object,
    header: String,
    loading: Boolean,
});

const form$ = ref(null)
const isDownloading = ref(false);
const isNameAudioPlaying = ref(false);
const isNameDownloading = ref(false);
const currentNameAudio = ref(null);
const showNewGreetingModal = ref(false);
const showNewNameGreetingModal = ref(false);
const recordedName = ref(props.options?.recorded_name)
const showDeleteConfirmationModal = ref(false);
const showDeleteNameConfirmationModal = ref(false);
const availableGreetings = ref(null)


const emit = defineEmits(['close', 'error', 'success', 'refresh-data'])


const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emit('success', 'success', { message: ['Copied to clipboard.'] });
    }).catch((error) => {
        // Handle the error case
        emit('error', { response: { data: { errors: { request: ['Failed to copy to clipboard.'] } } } });
    });
}

// Automatically fetches data for the SelectElement
const fetchGreetings = async (query, input) => {
    const route = props.options?.routes?.get_greetings_route;

    if (!route) {
        availableGreetings.value = [
            { value: '0', label: 'None' },
            { value: '-1', label: 'System Default' }
        ];
        return availableGreetings.value;
    }

    try {
        const response = await axios.get(route);

        // SAVE the fetched array to our local ref!
        availableGreetings.value = response.data;

        return response.data;
    } catch (error) {
        console.error("Failed to load greetings async", error);
        availableGreetings.value = [
            { value: '0', label: 'None' },
            { value: '-1', label: 'System Default' }
        ];
        return availableGreetings.value;
    }
};


// Watch for changes in the prop and update the ref
watch(
    () => props.options?.recorded_name,
    (newVal) => {
        recordedName.value = newVal;
    },
    { immediate: true } // Forces this to run instantly 
)

const handleNewGreetingButtonClick = () => {
    stopGreetingAudio()
    showNewGreetingModal.value = true;
};

const handleNewNameGreetingButtonClick = () => {
    stopRecordedNameAudio();
    showNewNameGreetingModal.value = true;
};

const handleNewGreetingAdded = async (greeting_id) => {
    await form$.value.el$('greeting_id').updateItems()
    form$.value.update({
        greeting_id: greeting_id,
    })
};

const handleNewNameAdded = async () => {
    stopRecordedNameAudio()
    recordedName.value = 'Custom recording'
}

const currentAudio = ref(null);
const isAudioPlaying = ref(false);
const currentAudioGreeting = ref(null);

const getSelectedGreetingId = () => {
    return form$.value?.data?.greeting_id ?? null;
};

const playGreeting = () => {
    const greeting = getSelectedGreetingId();
    if (!greeting) return;

    // If there's already an audio playing for the SAME greeting
    if (currentAudio.value && currentAudioGreeting.value === greeting) {
        if (currentAudio.value.paused) {
            currentAudio.value.play();
            isAudioPlaying.value = true;
        }
        return;
    }

    // Otherwise, stop the old audio
    if (currentAudio.value) {
        currentAudio.value.pause();
        currentAudio.value.currentTime = 0;
        currentAudio.value = null;
    }

    isAudioPlaying.value = false;

    axios.post(props.options.routes.greeting_route, { greeting_id: greeting })
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

const downloadGreeting = () => {
    isDownloading.value = true; // Start the spinner

    const greeting = getSelectedGreetingId();

    if (!greeting) {
        isDownloading.value = false;
        return; // No greeting selected, stop
    }

    axios.post(props.options.routes.greeting_route, { greeting_id: greeting })
        .then((response) => {
            if (response.data.success) {
                // Create a URL with the download parameter set to true
                const downloadUrl = `${response.data.file_url}?download=true`;

                // Create an invisible link element
                const link = document.createElement('a');
                link.href = downloadUrl;

                // Use the filename or a default name
                const fileName = response.data.file_name;
                link.download = fileName || 'greeting.wav';

                // Append the link to the body
                document.body.appendChild(link);

                // Trigger the download
                link.click();

                // Remove the link
                document.body.removeChild(link);
            }
        })
        .catch((error) => {
            emit('error', error);
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

const editGreeting = () => {
    if (form$.value.data.greeting_id) {
        greetingLabel.value = form$.value.data.greeting_id;
        showEditModal.value = true;
    }
};

const deleteGreeting = () => {
    stopGreetingAudio()
    // Show the confirmation modal
    showDeleteConfirmationModal.value = true;
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

const greetingTranscription = computed(() => {
    // 1. Get the currently selected ID from the form data
    const selectedId = form$.value?.data?.greeting_id ?? null;
    if (!selectedId) return null;

    if (!availableGreetings.value) {
        return null
    }

    // 3. Find the matching item in our local array
    const selectedItem = availableGreetings.value.find(
        (item) => String(item.value) === String(selectedId)
    );

    // 4. Return the description
    return selectedItem?.description || null;
});

const hasPlayableGreeting = (form$) => {
    const val = form$?.el$('greeting_id')?.value ?? null;
    return val !== '0' && val !== '-1' && val !== null;
};

const confirmGreetingDeleteAction = async () => {
    const greetingId = getSelectedGreetingId();

    if (!greetingId) {
        showDeleteConfirmationModal.value = false;
        return;
    }

    axios
        .post(props.options.routes.delete_greeting_route, { greeting_id: greetingId })
        .then(async (response) => {
            if (response.data.success) {
                if (availableGreetings.value) {
                    availableGreetings.value = availableGreetings.value.filter(
                        (greeting) => String(greeting.value) !== String(greetingId)
                    );
                }

                await form$.value.el$('greeting_id').clear();
                await form$.value.el$('greeting_id').updateItems();

                form$.value.update({
                    greeting_id: '-1',
                })

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

const playRecordedName = () => {
    if (currentNameAudio.value && currentNameAudio.value.paused) {
        currentNameAudio.value.play();
        isNameAudioPlaying.value = true;
        return;
    }

    axios.post(props.options.routes.recorded_name_route, { voicemail_id: form$.value.data.voicemail_id })
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
            emit('error', error);
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

    axios.post(props.options.routes.recorded_name_route, { voicemail_id: form$.value.data.voicemail_id })
        .then((response) => {
            if (response.data.success) {
                const downloadUrl = `${response.data.file_url}?download=true`;

                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = response.data.file_name || 'recordedName.wav';

                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        })
        .catch((error) => {
            emit('error', error);
        })
        .finally(() => {
            isNameDownloading.value = false;
        });
};


const deleteRecordedName = () => {
    stopRecordedNameAudio()
    showDeleteNameConfirmationModal.value = true
};


const confirmDeleteNameAction = () => {
    stopRecordedNameAudio();
    axios
        .post(props.options.routes.delete_recorded_name_route, { voicemail_id: form$.value.data.voicemail_id })
        .then((response) => {
            if (response.data.success) {
                recordedName.value = 'System Default';
                emit('success', 'success', response.data.messages);
            }
        })
        .catch((error) => {
            emit('error', error);
        })
        .finally(() => {
            showDeleteNameConfirmationModal.value = false;
        });
};

const stopRecordedNameAudio = () => {
    if (currentNameAudio.value) {
        currentNameAudio.value.pause()
        currentNameAudio.value.currentTime = 0
        currentNameAudio.value = null
    }

    isNameAudioPlaying.value = false
}

// Computed property or method to dynamically set routes based on the form type
const getRoutesForGreetingForm = computed(() => {
    const routes = props.options?.routes ?? {}

    return {
        ...routes,
        text_to_speech_route: routes.text_to_speech_route ?? null,
        upload_greeting_route: routes.upload_greeting_route ?? null,
    }
})

const getRoutesForNameForm = computed(() => {
    const routes = props.options?.routes ?? {}

    return {
        ...routes,
        text_to_speech_route: routes.text_to_speech_route_for_name ?? null,
        upload_greeting_route: routes.upload_greeting_route_for_name ?? null,
    }
})

const emitErrorToParentFromChild = (error) => {
    emit('error', error);
}

const emitSuccessToParentFromChild = (message) => {
    emit('success', 'success', message);
}

const escalationMemberOptions = computed(() => {
    return props.options?.escalation_member_options ?? props.options?.member_options ?? [];
});

const allEscalationMemberOptions = computed(() => {
    return escalationMemberOptions.value.flatMap(group => group.groupOptions ?? []);
});

const availableEscalationRecipients = computed(() => {
    const recipientsField = form$.value?.el$('vm_notify_profile.recipients');
    const currentRecipients = recipientsField?.value || [];

    const selectedKeys = currentRecipients.map((recipient) => {
        if (recipient.extension_uuid) {
            return `extension:${recipient.extension_uuid}`;
        }

        return `phone:${recipient.phone_number}`;
    });

    return escalationMemberOptions.value.map(group => ({
        label: group.groupLabel,
        items: (group.groupOptions ?? []).filter(option => {
            const key = option.type === 'extension'
                ? `extension:${option.value}`
                : `phone:${option.destination ?? option.label}`;

            return !selectedKeys.includes(key);
        }),
    }));
});

const getEscalationRecipientLabel = (row) => {
    if (row.recipient_type === 'extension') {
        const match = allEscalationMemberOptions.value.find(
            (option) => String(option.value) === String(row.extension_uuid)
        );

        return match?.label ?? row.display_name ?? row.extension_uuid ?? 'Extension';
    }

    return row.display_name || row.phone_number || 'External Number';
};

const addSelectedEscalationRecipients = () => {
    const selectedItems = form$.value?.el$('vm_notify_profile.selected_recipients')?.value ?? [];
    const currentRecipients = form$.value?.el$('vm_notify_profile.recipients')?.value ?? [];

    const normalizedRecipients = selectedItems.map((item, index) => {
        const isInternal = item.type === 'extension' || !!item.destination;

        return {
            vm_notify_profile_recipient_uuid: null,
            recipient_type: isInternal ? 'extension' : 'external_number',
            extension_uuid: isInternal ? item.value : null,
            phone_number: isInternal ? null : item.label,
            display_name: item.label ?? item.destination ?? null,
            priority: 0,
            sort_order: currentRecipients.length + index,
            enabled: true,
        };
    });

    form$.value.update({
        vm_notify_profile: {
            ...form$.value.data.vm_notify_profile,
            recipients: [...currentRecipients, ...normalizedRecipients],
            selected_recipients: [],
        },
    });
};

const handleClose = () => {
    stopGreetingAudio()
    stopRecordedNameAudio()
    emit('close')
}

onBeforeUnmount(() => {
    stopGreetingAudio()
    stopRecordedNameAudio()
})


const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData
    // console.log(requestData);

    // Using form$.data will INCLUDE conditional elements and it
    // will submit the form as "Content-Type: application/json".
    // const data = form$.data

    return await form$.$vueform.services.axios.put(props.options.routes.update_route, requestData)
};


function clearErrorsRecursive(el$) {
    // clear this element’s errors
    el$.messageBag?.clear()

    // if it has child elements, recurse into each
    if (el$.children$) {
        Object.values(el$.children$).forEach(childEl$ => {
            clearErrorsRecursive(childEl$)
        })
    }
}

const handleResponse = (response, form$) => {
    // Clear form including nested elements 
    Object.values(form$.elements$).forEach(el$ => {
        clearErrorsRecursive(el$)
    })

    // Display custom errors for elements
    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0])
            }
        })
    }
}

const handleSuccess = (response, form$) => {
    // console.log(response) // axios response
    // console.log(response.status) // HTTP status code
    // console.log(response.data) // response data

    emit('success', 'success', response.data.messages);
    emit('close');
    emit('refresh-data');
}

const handleError = (error, details, form$) => {
    form$.messageBag.clear() // clear message bag

    switch (details.type) {
        // Error occured while preparing elements (no submit happened)
        case 'prepare':
            console.log(error) // Error object

            form$.messageBag.append('Could not prepare form')
            break

        // Error occured because response status is outside of 2xx
        case 'submit':
            emit('error', error);
            console.log(error) // AxiosError object
            // console.log(error.response) // axios response
            // console.log(error.response.status) // HTTP status code
            // console.log(error.response.data) // response data

            // console.log(error.response.data.errors)


            break

        // Request cancelled (no response object)
        case 'cancel':
            console.log(error) // Error object

            form$.messageBag.append('Request cancelled')
            break

        // Some other errors happened (no response object)
        case 'other':
            console.log(error) // Error object

            form$.messageBag.append('Couldn\'t submit form')
            break
    }
}


</script>
