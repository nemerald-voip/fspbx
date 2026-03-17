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
                                @error="handleError" @response="handleResponse" :display-errors="false" :default="{
                                    voicemail_id: options.item?.voicemail_id ?? '',
                                    voicemail_password: options.item?.voicemail_password ?? null,
                                    voicemail_mail_to: options.item?.voicemail_mail_to,
                                    domain_uuid: options.item?.domain_uuid,
                                    voicemail_enabled: options.item?.voicemail_enabled ?? 'false',
                                    voicemail_description: options.item?.voicemail_description ?? '',
                                    voicemail_transcription_enabled: options.item?.voicemail_transcription_enabled ?? 'true',
                                    voicemail_file: options.item?.voicemail_file === 'attach' ? 'attach' : '',
                                    voicemail_local_after_email: options.item?.voicemail_local_after_email ?? 'true',
                                    voicemail_destinations: options.item?.voicemail_destinations ?? [],
                                    greeting_id: options.item?.greeting_id ?? null,
                                    voicemail_tutorial: options.item?.voicemail_tutorial ?? 'false',
                                    voicemail_recording_instructions: options.item?.voicemail_recording_instructions ?? 'true',
                                    voicemail_sms_to: options.item?.voicemail_sms_to ?? '',
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
                                                    'voicemail_destinations',
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
                                                    'name_greeting_title',
                                                    'voicemail_name_action_buttons',
                                                ]" />

                                                <FormTab name="advanced" label="Advanced" :elements="[
                                                    'keys_container',
                                                    'keys_title',
                                                    'add_key',
                                                    'device_keys',
                                                    'advanced',
                                                    'keys_container2',
                                                    'submit_keys',

                                                ]" />
                                                <!-- <FormTab name="cloud_provisioning" label="Cloud Provisioning" :elements="[
                                                    'cloud_provisioning_title',
                                                    'cloud_provisioning_status',
                                                    'cloud_provisioning_register',
                                                    'cloud_provisioning_deregister',
                                                    'cloud_provisioning_refresh',
                                                    'cloud_provisioning_retry',
                                                    'cloud_provisioning_container',
                                                    'provisioning_loading',
                                                    'cloud_provisioning_reset',
                                                    'cloud_container',
                                                    'submit_cloud',

                                                ]"
                                                    :conditions="[() => options?.permissions?.manage_device_cloud_provisioning_settings && options.cloud_provider_available]" /> -->


                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>

                                                <StaticElement name="voicemail_title" tag="h4" content="Voicemail"
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

                                                <TagsElement name="voicemail_destinations" :search="true"
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
                                                    :format-data="formatGreeting" :strict="false" :columns="{
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
                                                }" label="Recorded Name" info="Used only in Dial by Name directory"
                                                    :conditions="[['voicemail_enabled', '==', 'true']]">
                                                    <div class="pt-2 flex items-center  whitespace-nowrap space-x-2">
                                                        <!-- <p>Recorded Name:</p> -->
                                                        <Badge v-if="recorded_name == 'Custom recording'"
                                                            :text="recorded_name" backgroundColor="bg-green-50"
                                                            textColor="text-green-700" ringColor="ring-green-600/20" />

                                                        <Badge v-if="recorded_name == 'System Default'"
                                                            :text="recorded_name" backgroundColor="bg-blue-50"
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
                                                        :conditions="[function () { return recorded_name == 'Custom recording' }]"
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
                                                        :conditions="[function () { return recorded_name == 'Custom recording' }]"
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
                                                        :conditions="[function () { return recorded_name == 'Custom recording' }]"
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

                                                <NewGreetingForm :header="'New Voicemail Greeting'"
                                                    :show="showNewGreetingModal" @close="showNewGreetingModal = false"
                                                    :voices="options.voices" :speeds="options.speeds"
                                                    :default_voice="options.default_voice"
                                                    :phone_call_instructions="options.phone_call_instructions"
                                                    :sample_message="options.sample_message"
                                                    :routes="getRoutesForGreetingForm" :loading="isModalLoading"
                                                    @error="emitErrorToParentFromChild"
                                                    @success="emitSuccessToParentFromChild"
                                                    @upload-success="handleSuccesfulGreetingUpload" />

                                                <NewGreetingForm :header="'New Recorded Name'"
                                                    :show="showNewNameGreetingModal"
                                                    @close="showNewNameGreetingModal = false" :voices="options.voices"
                                                    :speeds="options.speeds" :default_voice="options.default_voice"
                                                    :phone_call_instructions="options.phone_call_instructions_for_name"
                                                    :sample_message="options.sample_message"
                                                    :routes="getRoutesForNameForm" :loading="isModalLoading"
                                                    @error="emitErrorToParentFromChild"
                                                    @success="emitSuccessToParentFromChild"
                                                    @upload-success="handleSuccesfulNameUpload"  />

                                                <ConfirmationModal :show="showDeleteConfirmationModal"
                                                    @close="showDeleteConfirmationModal = false"
                                                    @confirm="confirmGreetingDeleteAction" :header="'Confirm Deletion'"
                                                    :text="'This action will permanently delete this greeting. Are you sure you want to proceed?'"
                                                    :confirm-button-label="'Delete'" cancel-button-label="Cancel" />


                                                <ConfirmationModal :show="showDeleteNameConfirmationModal"
                                                    @close="showDeleteNameConfirmationModal = false"
                                                    @confirm="confirmDeleteNameAction" :header="'Confirm Deletion'"
                                                    :text="'This action will permanently delete this greeting. Are you sure you want to proceed?'"
                                                    :confirm-button-label="'Delete'" cancel-button-label="Cancel" />


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
const recorded_name = ref(props.options?.recorded_name)
const showDeleteConfirmationModal = ref(false);
const showDeleteNameConfirmationModal = ref(false);
const isModalLoading = ref(false);
const availableGreetings = ref(null)


const emit = defineEmits(['close', 'error', 'success', 'refresh-data', 'open-new-greeting-form'])


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
        recorded_name.value = newVal;
    },
    { immediate: true } // Forces this to run instantly 
)

const handleNewGreetingButtonClick = () => {
    showNewGreetingModal.value = true;
};

const handleNewNameGreetingButtonClick = () => {
    showNewNameGreetingModal.value = true;
};

const handleSuccesfulGreetingUpload = async (greeting_id) => {
    await form$.value.el$('greeting_id').updateItems()
    form$.value.update({
        greeting_id: greeting_id,
    })

};

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

const formatGreeting = (name, value) => {
    return { [name]: value?.value ?? null } // must return an object
}

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
                    greeting_id: -1,
                })

                emit('success', 'success', response.data.messages);

                console.log(response.data.messages)
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
                link.download = response.data.file_name || 'recorded_name.wav';

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
    showDeleteNameConfirmationModal.value = true; // Show confirmation modal
};


const confirmDeleteNameAction = () => {
    axios
        .post(props.options.routes.delete_recorded_name_route, { voicemail_id: form$.value.data.voicemail_id })
        .then((response) => {
            if (response.data.success) {
                recorded_name.value = 'System Default';
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
