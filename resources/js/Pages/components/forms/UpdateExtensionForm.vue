<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10"
        :inert="showNewGreetingModal || showNewNameGreetingModal || showDeviceCreateModal || showDeviceAssignModal || showUpdatePasswordModal">
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
                                    @click="emit('close')">
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
                                    extension_uuid: options.item.extension_uuid ?? '',
                                    directory_first_name: options.item.directory_first_name ?? '',
                                    directory_last_name: options.item.directory_last_name ?? '',
                                    extension: options.item.extension ?? '',
                                    last_name: options.item.last_name ?? '',
                                    voicemail_mail_to: options.item.email ?? '',
                                    description: options.item.description ?? '',
                                    suspended: options.item.suspended ?? false,
                                    enabled: options.item.enabled ?? 'true',
                                    do_not_disturb: options.item.do_not_disturb ?? 'false',
                                    directory_visible: options.item.directory_visible ?? '',
                                    directory_exten_visible: options.item.directory_exten_visible ?? '',
                                    outbound_caller_id_number: options.item.outbound_caller_id_number_e164 ?? '',
                                    emergency_caller_id_number: options.item.emergency_caller_id_number_e164 ?? '',
                                    outbound_caller_id_name: options.item.outbound_caller_id_name ?? '',
                                    emergency_caller_id_name: options.item.emergency_caller_id_name ?? '',
                                    call_timeout: options.item.call_timeout ?? null,
                                    call_screen_enabled: options.item.call_screen_enabled ?? 'false',
                                    max_registrations: options.item.max_registrations ?? '',
                                    limit_max: options.item.limit_max ?? '',
                                    limit_destination: options.item.limit_destination ?? '',
                                    toll_allow: options.item.toll_allow ?? '',
                                    call_group: options.item.call_group ?? '',
                                    hold_music: options.item.hold_music ?? '',
                                    auth_acl: options.item.auth_acl ?? '',
                                    cidr: options.item.cidr ?? '',
                                    sip_force_contact: options.item.sip_force_contact ?? '',
                                    sip_force_expires: options.item.sip_force_expires ?? '',
                                    sip_bypass_media: options.item.sip_bypass_media ?? '',
                                    mwi_account: options.item.mwi_account ?? '',
                                    absolute_codec_string: options.item.absolute_codec_string ?? '',
                                    dial_string: options.item.dial_string ?? '',
                                    force_ping: options.item.force_ping ?? 'false',
                                    user_context: options.item.user_context ?? '',
                                    accountcode: options.item.accountcode ?? '',
                                    exclude_from_ringotel_stale_users: options.item?.mobile_app?.exclude_from_stale_report ?? false,
                                    recording: !!options.item.user_record,
                                    user_record: options.item.user_record ?? null,
                                    forward_all_enabled: props.options.item.forward_all_enabled ?? 'false',
                                    forward_all_action: props.options.item.forward_all_action ?? '',

                                    // only set forward_all_external_target when forwarding_action==='external'
                                    forward_all_external_target: props.options.item.forward_all_action === 'external'
                                        ? props.options.item.forward_all_target_extension ?? null
                                        : null,

                                    // for any other action, set forward_target
                                    forward_all_target: props.options.item.forward_all_action != 'external'
                                        ? { value: props.options.item.forward_all_target_uuid ?? null, extension: props.options.item.forward_all_target_extension ?? null, name: props.options.item.forward_all_target_name ?? null }
                                        : null,

                                    forward_busy_enabled: props.options.item.forward_busy_enabled ?? 'false',
                                    forward_busy_action: props.options.item.forward_busy_action ?? '',

                                    // only set forward_busy_external_target when action is 'external'
                                    forward_busy_external_target: props.options.item.forward_busy_action === 'external'
                                        ? props.options.item.forward_busy_target_extension ?? null
                                        : null,

                                    // for any other action, set forward_busy_target
                                    forward_busy_target: props.options.item.forward_busy_action != 'external'
                                        ? {
                                            value: props.options.item.forward_busy_target_uuid ?? null,
                                            extension: props.options.item.forward_busy_target_extension ?? null,
                                            name: props.options.item.forward_busy_target_name ?? null,
                                        }
                                        : null,

                                    forward_no_answer_enabled: props.options.item.forward_no_answer_enabled ?? 'false',
                                    forward_no_answer_action: props.options.item.forward_no_answer_action ?? '',

                                    forward_no_answer_external_target: props.options.item.forward_no_answer_action === 'external'
                                        ? props.options.item.forward_no_answer_target_extension ?? null
                                        : null,

                                    forward_no_answer_target: props.options.item.forward_no_answer_action != 'external'
                                        ? {
                                            value: props.options.item.forward_no_answer_target_uuid ?? null,
                                            extension: props.options.item.forward_no_answer_target_extension ?? null,
                                            name: props.options.item.forward_no_answer_target_name ?? null,
                                        }
                                        : null,


                                    forward_user_not_registered_enabled: props.options.item.forward_user_not_registered_enabled ?? 'false',
                                    forward_user_not_registered_action: props.options.item.forward_user_not_registered_action ?? '',

                                    forward_user_not_registered_external_target: props.options.item.forward_user_not_registered_action === 'external'
                                        ? props.options.item.forward_user_not_registered_target_extension ?? null
                                        : null,

                                    forward_user_not_registered_target: props.options.item.forward_user_not_registered_action != 'external'
                                        ? {
                                            value: props.options.item.forward_user_not_registered_target_uuid ?? null,
                                            extension: props.options.item.forward_user_not_registered_target_extension ?? null,
                                            name: props.options.item.forward_user_not_registered_target_name ?? null,
                                        }
                                        : null,



                                    follow_me_enabled: options.item.follow_me_enabled ?? 'false',
                                    // follow_me_destinations: options.item.follow_me_destinations ?? [],
                                    follow_me_destinations: (
                                        options.item.follow_me_destinations?.length > 0 &&
                                            options.item.follow_me_destinations[0].destination == options.item.extension
                                            ? options.item.follow_me_destinations.slice(1).map(dest => ({
                                                ...dest,
                                                delay: Math.max(0, (dest.delay ?? 0) - (options.item.follow_me_destinations[0].timeout ?? 0)),
                                            }))
                                            : (options.item.follow_me_destinations ?? [])
                                    ),
                                    follow_me_ring_my_phone_timeout: (
                                        options.item.follow_me_destinations?.length > 0 &&
                                            options.item.follow_me_destinations[0].destination == options.item.extension
                                            ? options.item.follow_me_destinations[0].timeout
                                            : 0
                                    ),

                                    voicemail_enabled: options.voicemail?.voicemail_enabled ?? 'false',
                                    voicemail_id: options.voicemail?.voicemail_id ?? '',
                                    voicemail_password: options.voicemail?.voicemail_password ?? options.item.voicemail,
                                    voicemail_description: options.voicemail?.voicemail_description ?? '',
                                    voicemail_transcription_enabled: options.voicemail?.voicemail_transcription_enabled ?? 'true',
                                    voicemail_file: options.voicemail?.voicemail_file === 'attach' ? 'attach' : '',
                                    voicemail_local_after_email: options.voicemail?.voicemail_local_after_email ?? 'true',
                                    voicemail_destinations: options.voicemail?.voicemail_destinations ?? [],
                                    greeting_id: options.voicemail?.greetings?.find(g => g.value === (options.voicemail.greeting_id ?? '')) ?? options.voicemail.greeting_id,
                                    voicemail_tutorial: options.voicemail?.voicemail_tutorial ?? 'false',
                                    voicemail_recording_instructions: options.voicemail?.voicemail_recording_instructions ?? 'true',
                                    voicemail_sms_to: options.voicemail?.voicemail_sms_to ?? '',


                                    //     ? options.item.user_groups.map(ug => ug.group_uuid)
                                    //     : []

                                }">

                                <template #empty>

                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical" @select="handleTabSelected">
                                                <FormTab name="page0" label="Basic Info" :elements="[
                                                    'basic_info_title',
                                                    'extension_clean',
                                                    'directory_first_name',
                                                    'directory_last_name',
                                                    'extension',
                                                    'extension_uuid',
                                                    'voicemail_mail_to',
                                                    'description',
                                                    'user_enabled',
                                                    'enabled',
                                                    'suspended',
                                                    'do_not_disturb',
                                                    'call_timeout',
                                                    'divider',
                                                    'divider1',
                                                    'divider2',
                                                    'recording',
                                                    'user_record',
                                                    'divider17',
                                                    'container_2',
                                                    'container_basic',
                                                    'submit_basic',

                                                ]" />
                                                <FormTab name="caller_id" label="Caller ID" :elements="[
                                                    'external_caller_id_title',
                                                    'emergency_caller_id_title',
                                                    'outbound_caller_id_number',
                                                    'outbound_caller_id_name',
                                                    'emergency_caller_id_number',
                                                    'emergency_caller_id_name',
                                                    'container_caller_id',
                                                    'submit_caller_id',

                                                ]"
                                                    :conditions="[() => options.permissions.manage_external_caller_id_number || options.permissions.manage_emergency_caller_id_number]" />
                                                <FormTab name="call_forward" label="Call Forward" :elements="[
                                                    'forward_all_calls_title',
                                                    'forward_all_enabled',
                                                    'token_title',
                                                    'forward_all_action',
                                                    'forward_all_target',
                                                    'forward_all_external_target',
                                                    'container_3',
                                                    'divider5',
                                                    'forward_busy_title',
                                                    'forward_busy_enabled',
                                                    'forward_busy_action',
                                                    'forward_busy_target',
                                                    'forward_busy_external_target',
                                                    'divider6',
                                                    'forward_no_answer_title',
                                                    'forward_no_answer_enabled',
                                                    'forward_no_answer_action',
                                                    'forward_no_answer_target',
                                                    'forward_no_answer_external_target',
                                                    'divider7',
                                                    'forward_user_not_registered_title',
                                                    'forward_user_not_registered_enabled',
                                                    'forward_user_not_registered_action',
                                                    'forward_user_not_registered_target',
                                                    'forward_user_not_registered_external_target',
                                                    'divider8',
                                                    'follow_me_title',
                                                    'follow_me_enabled',
                                                    'divider9',
                                                    'follow_me_destinations',
                                                    'members_title',
                                                    'selectedDestinations',
                                                    'follow_me_ring_my_phone_timeout',
                                                    'addFollowMeDestinationsButton',
                                                    'container_forward',
                                                    'submit_forward',

                                                ]" :conditions="[() => options.permissions.manage_forwarding]" />
                                                <FormTab name="voicemail" label="Voicemail" :elements="[
                                                    'voicemail_title',
                                                    'voicemail_enabled',
                                                    'voicemail_password',
                                                    'voicemail_description',
                                                    'voicemail_transcription_enabled',
                                                    'divider10',
                                                    'voicemail_file',
                                                    'divider11',
                                                    'voicemail_local_after_email',
                                                    'voicemail_destinations',
                                                    'divider12',
                                                    'voicemail_greetings_title',
                                                    'container_3',
                                                    'voicemail_action_buttons',
                                                    'greeting_id',
                                                    'delete_button',
                                                    'name_greeting_title',
                                                    'divider13',
                                                    'voicemail_name_action_buttons',
                                                    'divider14',
                                                    'voicemail_advanced_title',
                                                    'voicemail_tutorial',
                                                    'divider15',
                                                    'voicemail_recording_instructions',
                                                    'divider18',
                                                    'voicemail_sms_to',
                                                    'submit',
                                                    'container_voicemail',
                                                    'submit_voicemail',

                                                ]" />

                                                <FormTab name="devices" label="Devices" :elements="[
                                                    'devices_title',
                                                    'container1',
                                                    'assign_existing',
                                                    'device_table',
                                                    'container_devices',
                                                    'submit_devices',
                                                    'add_device',

                                                ]" />

                                                <FormTab name="mobile_app" label="Mobile App" :elements="[
                                                    'mobile_app_title',
                                                    'mobile_app_status',
                                                    'enable_mobile_app',
                                                    'enable_mobile_app_contact',
                                                    'mobile_app_connection',
                                                    'mobile_app_credentials',
                                                    'submit_enabling_mobile_app',
                                                    'reset_mobile_app_password',
                                                    'deactivate_mobile_app',
                                                    'activate_mobile_app',
                                                    'remove_mobile_app',
                                                    'mobile_app_loading',
                                                    'mobile_app_error',
                                                    'container2',
                                                    'container3',
                                                    'container4',
                                                    'container5',
                                                    'container_mobile_app',
                                                    'submit_mobile_app',

                                                ]" :conditions="[() => options.permissions.manage_mobile_app]" />

                                                <FormTab name="sip_credentials" label="SIP Credentials" :elements="[
                                                    'sip_credentials_title',
                                                    'show_sip_credentials',
                                                    'sip_credentials',
                                                    'regenerate_sip_credentials',
                                                    'edit_sip_password',
                                                    'submit_sip_credentials',

                                                ]" :conditions="[() => options.permissions.extension_password]" />

                                                <FormTab name="advanced" label="Advanced Settings" :elements="[
                                                    'advanced_title',
                                                    'directory_visible',
                                                    'directory_exten_visible',
                                                    'call_screen_enabled',
                                                    'max_registrations',
                                                    'limit_destination',
                                                    'toll_allow',
                                                    'call_group',
                                                    'limit_max',
                                                    'hold_music',
                                                    'cidr',
                                                    'sip_force_contact',
                                                    'sip_force_expires',
                                                    'sip_bypass_media',
                                                    'mwi_account',
                                                    'absolute_codec_string',
                                                    'dial_string',
                                                    'force_ping',
                                                    'user_context',
                                                    'accountcode',
                                                    'exclude_from_ringotel_stale_users',
                                                    'auth_acl',
                                                    'divider3',
                                                    'divider4',
                                                    'divider16',
                                                    'container_advanced',
                                                    'submit_advanced',

                                                ]" :conditions="[() => options.permissions.extension_advanced]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>

                                                <HiddenElement name="extension_uuid" :meta="true" />
                                                <StaticElement name="uuid_clean"
                                                    :conditions="[() => options.permissions.is_superadmin]">

                                                    <div class="mb-1">
                                                        <div class="text-sm font-medium text-gray-600 mb-1">
                                                            Unique ID
                                                        </div>

                                                        <div class="flex items-center group">
                                                            <span class="text-sm text-gray-900 select-all font-normal">
                                                                {{ options.item.extension_uuid }}
                                                            </span>
                                                            <button type="button"
                                                                @click="handleCopyToClipboard(options.item.extension_uuid)"
                                                                class="ml-2 p-1 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                                                title="Copy to clipboard">
                                                                <!-- Small Copy Icon -->
                                                                <ClipboardDocumentIcon
                                                                    class="h-4 w-4 text-gray-500 hover:text-gray-900  cursor-pointer" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </StaticElement>
                                                <StaticElement name="basic_info_title" tag="h4" content="Basic Info"
                                                    description="Fill in basic details to identify and describe this extension." />
                                                <TextElement name="directory_first_name" label="First Name"
                                                    placeholder="Enter First Name" :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" />
                                                <TextElement name="directory_last_name" label="Last Name"
                                                    placeholder="Enter Last Name" :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" />
                                                <TextElement name="extension" label="Extension"
                                                    placeholder="Enter Extension" :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        }
                                                    }" :disabled="!options.permissions.extension_extension" />

                                                <TextElement name="voicemail_mail_to" label="Email"
                                                    placeholder="Enter Email" :floating="false" :columns="{
                                                        container: 6,
                                                    }" />
                                                <TextElement name="description" label="Description"
                                                    placeholder="Enter Description" :floating="false" />

                                                <GroupElement name="container_2" />

                                                <ToggleElement name="suspended" text="Suspended"
                                                    description="Prevents users from making or receiving calls, except for emergency calls. Typically used for billing or policy-related suspensions."
                                                    :replace-class="{
                                                        'toggle.toggleOn': {
                                                            'form-bg-primary': 'bg-red-500',
                                                            'form-border-color-primary': 'border-red-500',
                                                            'form-color-on-primary': 'form-color-on-danger'

                                                        }
                                                    }" :disabled="!options.permissions.extension_suspended" />

                                                <StaticElement name="divider" tag="hr" />

                                                <ToggleElement name="do_not_disturb" text="Do Not Disturb"
                                                    true-value="true" false-value="false" :replace-class="{
                                                        'toggle.toggleOn': {
                                                            'form-bg-primary': 'bg-red-500',
                                                            'form-border-color-primary': 'border-red-500',
                                                            'form-color-on-primary': 'form-color-on-danger'

                                                        }
                                                    }"
                                                    :conditions="[(form$) => options.permissions.extension_do_not_disturb && form$.el$('suspended')?.value != true]" />

                                                <StaticElement name="divider1" tag="hr"
                                                    :conditions="[(form$) => options.permissions.extension_do_not_disturb && form$.el$('suspended')?.value != true]" />

                                                <ToggleElement name="enabled" text="Status" true-value="true"
                                                    false-value="false"
                                                    description="Activate or deactivate the extension. When deactivated, devices cannot connect and calls cannot be placed or received."
                                                    :conditions="[() => options.permissions.extension_enabled]" />

                                                <StaticElement name="divider2" tag="hr"
                                                    :conditions="[() => options.permissions.extension_enabled]" />

                                                <ToggleElement name="recording" text="Record Calls" :submit="false"
                                                    description="Activate or deactivate call recording for the extension."
                                                    :conditions="[() => options.permissions.extension_user_record]" />

                                                <RadiogroupElement name="user_record" :items="[
                                                    {
                                                        value: 'all',
                                                        label: 'All',
                                                    },
                                                    {
                                                        value: 'local',
                                                        label: 'Local',
                                                    },
                                                    {
                                                        value: 'outbound',
                                                        label: 'Outbound',
                                                    },
                                                    {
                                                        value: 'inbound',
                                                        label: 'Inbound',
                                                    },
                                                ]" label="Record" :conditions="[['recording', '==', true,],]" />

                                                <StaticElement name="divider17" tag="hr"
                                                    :conditions="[() => options.permissions.extension_user_record]" />

                                                <SelectElement name="call_timeout" :items="delayOptions" :search="true"
                                                    :native="false" label="Send unanswered calls to voicemail after"
                                                    input-type="search" allow-absent autocomplete="off" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }"
                                                    info="Set how many seconds to ring before redirecting unanswered calls to voicemail."
                                                    placeholder="Select option" :floating="false" />


                                                <GroupElement name="container_basic" />

                                                <ButtonElement name="submit_basic" button-label="Save" :submits="true"
                                                    align="right" />


                                                <!-- Caller ID Tab -->
                                                <StaticElement name="external_caller_id_title" tag="h4"
                                                    content="External Caller ID"
                                                    description="Define the External Caller ID that will be displayed on the recipient's device when dialing outside the company."
                                                    :conditions="[() => options.permissions.manage_external_caller_id_number]" />

                                                <SelectElement name="outbound_caller_id_number"
                                                    :items="options.phone_numbers" :search="true" :native="false"
                                                    input-type="search" autocomplete="off"
                                                    :conditions="[() => options.permissions.manage_external_caller_id_number]" />

                                                <TextElement name="outbound_caller_id_name" label="Name"
                                                    placeholder="Enter External Caller ID Name" :floating="false"
                                                    :conditions="[() => options.permissions.manage_external_caller_id_name]" />

                                                <StaticElement name="emergency_caller_id_title" tag="h4"
                                                    content="Emergency Caller ID"
                                                    description="Define the Emergency Caller ID that will be displayed when dialing emergency services."
                                                    :conditions="[() => options.permissions.manage_emergency_caller_id_number]" />

                                                <SelectElement name="emergency_caller_id_number"
                                                    :items="options.phone_numbers" :search="true" :native="false"
                                                    input-type="search" autocomplete="off"
                                                    :conditions="[() => options.permissions.manage_emergency_caller_id_number]" />

                                                <TextElement name="emergency_caller_id_name" label="Name"
                                                    placeholder="Enter Emergency Caller ID Name" :floating="false"
                                                    :conditions="[() => options.permissions.manage_emergency_caller_id_name]" />

                                                <GroupElement name="container_caller_id" />

                                                <ButtonElement name="submit_caller_id" button-label="Save"
                                                    :submits="true" align="right" />

                                                <!-- Call Forward Tab -->

                                                <StaticElement name="forward_all_calls_title" tag="h4"
                                                    content="Forward All Calls"
                                                    description="Instantly and unconditionally forward all incoming calls to another destination. No calls will ring to your phone until forwarding is disabled."
                                                    :conditions="[() => options.permissions.extension_forward_all]" />

                                                <ToggleElement name="forward_all_enabled" :labels="{
                                                    on: 'On',
                                                    off: 'Off',
                                                }" true-value="true" false-value="false"
                                                    :conditions="[() => options.permissions.extension_forward_all]" />

                                                <SelectElement name="forward_all_action"
                                                    :items="options.forwarding_types" :search="true" :native="false"
                                                    label="Choose Action" input-type="search" autocomplete="off"
                                                    placeholder="Choose Action" :floating="false" :strict="false"
                                                    :conditions="[['forward_all_enabled', '==', 'true'],]" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" @change="(newValue, oldValue, el$) => {
                                                        let forward_all_target = el$.form$.el$('forward_all_target')

                                                        // only clear when this isn’t the very first time (i.e. oldValue was set)
                                                        if (oldValue !== null && oldValue !== undefined) {
                                                            forward_all_target.clear();
                                                        }

                                                        forward_all_target.updateItems()
                                                    }" />
                                                <SelectElement name="forward_all_target" :items="async (query, input) => {
                                                    let forward_all_action = input.$parent.el$.form$.el$('forward_all_action');

                                                    try {
                                                        let response = await forward_all_action.$vueform.services.axios.post(
                                                            options.routes.get_routing_options,
                                                            { category: forward_all_action.value }
                                                        );
                                                        // console.log(response.data.options);
                                                        return response.data.options;
                                                    } catch (error) {
                                                        emit('error', error);
                                                        return [];  // Return an empty array in case of error
                                                    }
                                                }" :search="true" label-prop="name" :native="false" label="Target"
                                                    input-type="search" allow-absent :object="true"
                                                    :format-data="formatTarget" autocomplete="off"
                                                    placeholder="Choose Target" :floating="false" :strict="false"
                                                    :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" :conditions="[
                                                        ['forward_all_enabled', '==', 'true'],
                                                        ['forward_all_action', 'not_empty'],
                                                        ['forward_all_action', 'not_in', ['external']]
                                                    ]" />

                                                <TextElement name="forward_all_external_target" label="Target"
                                                    placeholder="Enter External Number" :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" :conditions="[
                                                        ['forward_all_enabled', '==', 'true'],
                                                        ['forward_all_action', 'not_empty'],
                                                        ['forward_all_action', 'in', ['external']]
                                                    ]" />
                                                <StaticElement name="divider5" tag="hr"
                                                    :conditions="[() => options.permissions.extension_forward_all]" />



                                                <StaticElement name="forward_busy_title" tag="h4"
                                                    content="When user is busy"
                                                    description="Automatically redirect incoming calls to a different destination when your line is busy or Do Not Disturb is active."
                                                    :conditions="[() => options.permissions.extension_forward_busy]" />

                                                <ToggleElement name="forward_busy_enabled" :labels="{
                                                    on: 'On',
                                                    off: 'Off',
                                                }" true-value="true" false-value="false"
                                                    :conditions="[() => options.permissions.extension_forward_busy]" />

                                                <SelectElement name="forward_busy_action"
                                                    :items="options.forwarding_types" :search="true" :native="false"
                                                    label="Choose Action" input-type="search" autocomplete="off"
                                                    placeholder="Choose Action" :floating="false" :strict="false"
                                                    :conditions="[['forward_busy_enabled', '==', 'true']]" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" @change="(newValue, oldValue, el$) => {
                                                        let forward_busy_target = el$.form$.el$('forward_busy_target');

                                                        // only clear when this isn’t the very first time (i.e. oldValue was set)
                                                        if (oldValue !== null && oldValue !== undefined) {
                                                            forward_busy_target.clear();
                                                        }

                                                        forward_busy_target.updateItems();
                                                    }" />

                                                <SelectElement name="forward_busy_target" :items="async (query, input) => {
                                                    let forward_busy_action = input.$parent.el$.form$.el$('forward_busy_action');

                                                    try {
                                                        let response = await forward_busy_action.$vueform.services.axios.post(
                                                            options.routes.get_routing_options,
                                                            { category: forward_busy_action.value }
                                                        );
                                                        // console.log(response.data.options);
                                                        return response.data.options;
                                                    } catch (error) {
                                                        emit('error', error);
                                                        return [];  // Return an empty array in case of error
                                                    }
                                                }" :search="true" label-prop="name" :native="false" label="Target"
                                                    input-type="search" allow-absent :object="true"
                                                    :format-data="formatTarget" autocomplete="off"
                                                    placeholder="Choose Target" :floating="false" :strict="false"
                                                    :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" :conditions="[
                                                        ['forward_busy_enabled', '==', 'true'],
                                                        ['forward_busy_action', 'not_empty'],
                                                        ['forward_busy_action', 'not_in', ['external']]
                                                    ]" />

                                                <TextElement name="forward_busy_external_target" label="Target"
                                                    placeholder="Enter External Number" :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" :conditions="[
                                                        ['forward_busy_enabled', '==', 'true'],
                                                        ['forward_busy_action', 'not_empty'],
                                                        ['forward_busy_action', 'in', ['external']]
                                                    ]" />
                                                <StaticElement name="divider6" tag="hr"
                                                    :conditions="[() => options.permissions.extension_forward_busy]" />

                                                <StaticElement name="forward_no_answer_title" tag="h4"
                                                    content="When user does not answer the call"
                                                    description="Automatically redirect incoming calls to another number if you do not answer within a set time."
                                                    :conditions="[() => options.permissions.extension_forward_no_answer]" />

                                                <ToggleElement name="forward_no_answer_enabled" :labels="{
                                                    on: 'On',
                                                    off: 'Off',
                                                }" true-value="true" false-value="false"
                                                    :conditions="[() => options.permissions.extension_forward_no_answer]" />

                                                <SelectElement name="forward_no_answer_action"
                                                    :items="options.forwarding_types" :search="true" :native="false"
                                                    label="Choose Action" input-type="search" autocomplete="off"
                                                    placeholder="Choose Action" :floating="false" :strict="false"
                                                    :conditions="[['forward_no_answer_enabled', '==', 'true']]"
                                                    :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" @change="(newValue, oldValue, el$) => {
                                                        let forward_no_answer_target = el$.form$.el$('forward_no_answer_target');

                                                        // only clear when this isn’t the very first time (i.e. oldValue was set)
                                                        if (oldValue !== null && oldValue !== undefined) {
                                                            forward_no_answer_target.clear();
                                                        }

                                                        forward_no_answer_target.updateItems();
                                                    }" />

                                                <SelectElement name="forward_no_answer_target" :items="async (query, input) => {
                                                    let forward_no_answer_action = input.$parent.el$.form$.el$('forward_no_answer_action');

                                                    try {
                                                        let response = await forward_no_answer_action.$vueform.services.axios.post(
                                                            options.routes.get_routing_options,
                                                            { category: forward_no_answer_action.value }
                                                        );
                                                        // console.log(response.data.options);
                                                        return response.data.options;
                                                    } catch (error) {
                                                        emit('error', error);
                                                        return [];  // Return an empty array in case of error
                                                    }
                                                }" :search="true" label-prop="name" :native="false" label="Target"
                                                    input-type="search" allow-absent :object="true"
                                                    :format-data="formatTarget" autocomplete="off"
                                                    placeholder="Choose Target" :floating="false" :strict="false"
                                                    :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" :conditions="[
                                                        ['forward_no_answer_enabled', '==', 'true'],
                                                        ['forward_no_answer_action', 'not_empty'],
                                                        ['forward_no_answer_action', 'not_in', ['external']]
                                                    ]" />

                                                <TextElement name="forward_no_answer_external_target" label="Target"
                                                    placeholder="Enter External Number" :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" :conditions="[
                                                        ['forward_no_answer_enabled', '==', 'true'],
                                                        ['forward_no_answer_action', 'not_empty'],
                                                        ['forward_no_answer_action', 'in', ['external']]
                                                    ]" />

                                                <StaticElement name="divider7" tag="hr"
                                                    :conditions="[() => options.permissions.extension_forward_no_answer]" />


                                                <StaticElement name="forward_user_not_registered_title" tag="h4"
                                                    content="When Device Is Not Registered (Internet Outage)"
                                                    description="Redirect calls to a different number if your device is not registered or unreachable."
                                                    :conditions="[() => options.permissions.extension_forward_not_registered]" />

                                                <ToggleElement name="forward_user_not_registered_enabled" :labels="{
                                                    on: 'On',
                                                    off: 'Off',
                                                }" true-value="true" false-value="false"
                                                    :conditions="[() => options.permissions.extension_forward_not_registered]" />

                                                <SelectElement name="forward_user_not_registered_action"
                                                    :items="options.forwarding_types" :search="true" :native="false"
                                                    label="Choose Action" input-type="search" autocomplete="off"
                                                    placeholder="Choose Action" :floating="false" :strict="false"
                                                    :conditions="[['forward_user_not_registered_enabled', '==', 'true']]"
                                                    :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" @change="(newValue, oldValue, el$) => {
                                                        let forward_user_not_registered_target = el$.form$.el$('forward_user_not_registered_target');

                                                        // only clear when this isn’t the very first time (i.e. oldValue was set)
                                                        if (oldValue !== null && oldValue !== undefined) {
                                                            forward_user_not_registered_target.clear();
                                                        }

                                                        forward_user_not_registered_target.updateItems();
                                                    }" />

                                                <SelectElement name="forward_user_not_registered_target" :items="async (query, input) => {
                                                    let forward_user_not_registered_action = input.$parent.el$.form$.el$('forward_user_not_registered_action');

                                                    try {
                                                        let response = await forward_user_not_registered_action.$vueform.services.axios.post(
                                                            options.routes.get_routing_options,
                                                            { category: forward_user_not_registered_action.value }
                                                        );
                                                        // console.log(response.data.options);
                                                        return response.data.options;
                                                    } catch (error) {
                                                        emit('error', error);
                                                        return [];  // Return an empty array in case of error
                                                    }
                                                }" :search="true" label-prop="name" :native="false" label="Target"
                                                    input-type="search" allow-absent :object="true"
                                                    :format-data="formatTarget" autocomplete="off"
                                                    placeholder="Choose Target" :floating="false" :strict="false"
                                                    :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" :conditions="[
                                                        ['forward_user_not_registered_enabled', '==', 'true'],
                                                        ['forward_user_not_registered_action', 'not_empty'],
                                                        ['forward_user_not_registered_action', 'not_in', ['external']]
                                                    ]" />

                                                <TextElement name="forward_user_not_registered_external_target"
                                                    label="Target" placeholder="Enter External Number" :floating="false"
                                                    :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" :conditions="[
                                                        ['forward_user_not_registered_enabled', '==', 'true'],
                                                        ['forward_user_not_registered_action', 'not_empty'],
                                                        ['forward_user_not_registered_action', 'in', ['external']]
                                                    ]" />

                                                <StaticElement name="divider8" tag="hr"
                                                    :conditions="[() => options.permissions.extension_forward_not_registered]" />


                                                <StaticElement name="follow_me_title" tag="h4" content="Call Sequence (Follow Me)"
                                                    description="Calls ring all your devices first, then your backup destinations one at a time until someone answers"
                                                    :conditions="[() => options.permissions.extension_call_sequence]" />

                                                <ToggleElement name="follow_me_enabled" :labels="{
                                                    on: 'On',
                                                    off: 'Off',
                                                }" true-value="true" false-value="false"
                                                    :conditions="[() => options.permissions.extension_call_sequence]" />

                                                <SelectElement name="follow_me_ring_my_phone_timeout"
                                                    :items="timeoutOptions" :search="true" :native="false"
                                                    label="Ring my devices first for" input-type="search" allow-absent
                                                    autocomplete="off" :columns="{

                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" size="sm"
                                                    info="How long to ring your devices before trying your other destinations or contacts."
                                                    placeholder="Select option" :floating="false"
                                                    :conditions="[['follow_me_enabled', '==', 'true']]" />


                                                <TagsElement name="selectedDestinations" :close-on-select="true"
                                                    :items="availableDestinations" :create="true" :search="true"
                                                    :groups="true" :native="false"
                                                    label="Add Backup Destinations or Contacts" input-type="search"
                                                    autocomplete="off"
                                                    placeholder="Search by name, extension, or enter a number"
                                                    :floating="false" :hide-selected="false" :object="true"
                                                    :group-hide-empty="true" :append-new-option="false" :submit="false"
                                                    description="Choose from the list of available options or enter an external number manually."
                                                    :conditions="[['follow_me_enabled', '==', 'true']]" />

                                                <ButtonElement @click="addSelectedDestinations"
                                                    name="addFollowMeDestinationsButton" button-label="Add to Sequence"
                                                    :secondary="true" align="center" :full="false"
                                                    :conditions="[['follow_me_enabled', '==', 'true']]" />

                                                <ListElement name="follow_me_destinations" :sort="true"
                                                    :controls="{ add: false }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-md' } }"
                                                    :conditions="[['follow_me_enabled', '==', 'true']]">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <HiddenElement name="destination" :meta="true" />
                                                            <StaticElement name="p_1" tag="p" :content="(el$) => {
                                                                const num = el$.parent.value.destination;
                                                                return getDestinationLabel(num);
                                                            }" :columns="{ default: { container: 8, }, sm: { container: 4, }, }"
                                                                label="Destination"
                                                                :attrs="{ class: 'text-base font-semibold' }" />

                                                            <SelectElement name="delay" :items="delayOptions"
                                                                :search="true" :native="false" label="Delay"
                                                                input-type="search" allow-absent autocomplete="off"
                                                                :columns="{
                                                                    default: {
                                                                        container: 6,
                                                                    },
                                                                    sm: {
                                                                        container: 4,
                                                                    },
                                                                }" size="sm"
                                                                info="How many seconds to wait before starting to ring this member."
                                                                placeholder="Select option" :floating="false" />


                                                            <SelectElement name="timeout" :items="timeoutOptions"
                                                                :search="true" :native="false" label="Ring for"
                                                                input-type="search" allow-absent autocomplete="off"
                                                                :columns="{
                                                                    default: {
                                                                        container: 6,
                                                                    },
                                                                    sm: {
                                                                        container: 4,
                                                                    },
                                                                }" size="sm"
                                                                info="How many seconds to keep ringing this member before giving up."
                                                                placeholder="Select option" :floating="false" />


                                                            <ToggleElement name="prompt" align="left" size="sm" true-value="1" false-value="false"
                                                                text="Enable answer confirmation"
                                                                description="This prevents voicemails and automated systems from answering a call."
                                                                info="Enable answer confirmation to prevent voicemails and automated systems from answering a call." />


                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <GroupElement name="container_forward" />

                                                <ButtonElement name="submit_forward" button-label="Save" :submits="true"
                                                    align="right" />



                                                <!-- Voicemail Tab -->

                                                <StaticElement name="voicemail_title" tag="h4" content="Voicemail"
                                                    description="Customize voicemail preferences" />
                                                <ToggleElement name="voicemail_enabled" text="Status" true-value="true"
                                                    false-value="false" default="true" />

                                                <HiddenElement name="voicemail_id" :meta="true"
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <TextElement name="voicemail_password" label="Password" :columns="{
                                                    sm: {
                                                        container: 6,
                                                    },
                                                }" :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <TextElement name="voicemail_description" label="Description"
                                                    placeholder="Enter Description" :floating="false"
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <ToggleElement name="voicemail_transcription_enabled"
                                                    text="Voicemail Transcription" true-value="true" false-value="false"
                                                    description="Convert voicemail messages to text using AI-powered transcription."
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <StaticElement name="divider10" tag="hr"
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <ToggleElement name="voicemail_file"
                                                    text="Attach File to Email Notifications" true-value="attach"
                                                    false-value=""
                                                    description="Attach voicemail recording file to the email notification."
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <StaticElement name="divider11" tag="hr"
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

                                                <StaticElement name="divider12" tag="hr" top="1" bottom="1"
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <!-- Voicemail Greetings -->
                                                <StaticElement name="voicemail_greetings_title" tag="h4"
                                                    content="Voicemail Greetings"
                                                    description="Customize the message that callers hear when they reach your voicemail."
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />


                                                <SelectElement name="greeting_id" :search="true" :native="false"
                                                    label="Select Greeting" :items="greetings" input-type="search"
                                                    autocomplete="off" placeholder="Select Greeting" :floating="false"
                                                    :object="true" :format-data="formatGreeting" :strict="false"
                                                    :columns="{
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
                                                        :conditions="[function (form$) { const val = form$.el$('greeting_id')?.value; return val?.value !== '0' && val?.value !== '-1' && val !== null; }]"
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
                                                        }"
                                                        :conditions="[function (form$) { const val = form$.el$('greeting_id')?.value; return val?.value !== '0' && val?.value !== '-1' && val !== null; }]"
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
                                                        }"
                                                        :conditions="[function (form$) { const val = form$.el$('greeting_id')?.value; return val?.value !== '0' && val?.value !== '-1' && val !== null; }]"
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


                                                <StaticElement name="divider14" tag="hr" top="1" bottom="1"
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />


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
                                                    description='Play a prompt instructing callers to "Record your message after the tone. Stop speaking to end the recording.'
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

                                                <GroupElement name="container_voicemail" />

                                                <ButtonElement name="submit_voicemail" button-label="Save"
                                                    :submits="true" align="right" />

                                                <!-- Devices tab-->
                                                <StaticElement name="devices_title" tag="h4" content="Assigned Devices"
                                                    description="View and manage devices assigned to this extension, or assign a new device." />


                                                <GroupElement name="container1" />

                                                <ButtonElement name="assign_existing" button-label="Assign Existing"
                                                    @click="handleAssignDeviceButtonClick" :loading="isModalLoading"
                                                    :columns="{
                                                        container: 6,
                                                    }" align="left" :secondary="true" />

                                                <ButtonElement name="add_device" button-label="Add Device" align="right"
                                                    @click="handleAddDeviceButtonClick" :loading="isModalLoading"
                                                    :conditions="[() => options.permissions.extension_device_create]"
                                                    :columns="{
                                                        container: 6,
                                                    }" />


                                                <StaticElement name="device_table">
                                                    <AssignedDevices :devices="devices" :loading="isDevicesLoading"
                                                        :permissions="options.permissions"
                                                        @edit-item="handleDeviceEditButtonClick"
                                                        @delete-item="handleUnassignDeviceButtonClick" />
                                                </StaticElement>

                                                <GroupElement name="container_devices" />

                                                <ButtonElement name="submit_devices" button-label="Save" :submits="true"
                                                    align="right" />

                                                <!-- Mobile App tab -->

                                                <StaticElement name="mobile_app_title" tag="h4"
                                                    content="Mobile App Settings"
                                                    description="Manage mobile app assigned to this extension" />


                                                <StaticElement name="mobile_app_status"
                                                    :conditions="[() => mobileAppOptions]">
                                                    <div v-if="mobileAppOptions?.mobile_app && mobileAppOptions?.mobile_app?.status == 1"
                                                        class="flex items-center gap-x-3">
                                                        <div
                                                            class="flex-none rounded-full bg-green-400/10 p-1 text-green-400">
                                                            <div class="size-2 rounded-full bg-current" />
                                                        </div>
                                                        <h1 class="flex gap-x-3 text-md">
                                                            <span class="font-semibold ">Mobile App Status:</span>
                                                            <Badge backgroundColor="bg-green-100"
                                                                textColor="text-green-700" :text="'Active'"
                                                                ringColor="ring-green-400/20"
                                                                class="px-2 py-1 text-xs font-semibold" />
                                                        </h1>
                                                    </div>
                                                    <div v-if="mobileAppOptions?.mobile_app && mobileAppOptions?.mobile_app?.status == -1"
                                                        class="flex items-center gap-x-3">
                                                        <div
                                                            class="flex-none rounded-full bg-green-400/10 p-1 text-green-400">
                                                            <div class="size-2 rounded-full bg-current" />
                                                        </div>
                                                        <h1 class="flex gap-x-3 text-md">
                                                            <span class="font-semibold ">Mobile App Status:</span>
                                                            <Badge backgroundColor="bg-blue-100"
                                                                textColor="text-blue-700" :text="'Contact Only'"
                                                                ringColor="ring-blue-400/20"
                                                                class="px-2 py-1 text-xs font-semibold" />
                                                        </h1>
                                                    </div>
                                                    <div v-if="!mobileAppOptions?.mobile_app"
                                                        class="flex items-center gap-x-3">
                                                        <div
                                                            class="flex-none rounded-full bg-gray-400/10 p-1 text-gray-400">
                                                            <div class="size-2 rounded-full bg-current" />
                                                        </div>
                                                        <h1 class="flex gap-x-3 text-md">
                                                            <span class="font-semibold ">Mobile App Status:</span>
                                                            <Badge backgroundColor="bg-gray-100"
                                                                textColor="text-gray-700" :text="'Not Enabled'"
                                                                ringColor="ring-gray-400/20"
                                                                class="px-2 py-1 text-xs font-semibold" />
                                                        </h1>
                                                    </div>
                                                </StaticElement>

                                                <ButtonElement name="enable_mobile_app" button-label="Enable"
                                                    label="Step 1: Enable Mobile App for Extension"
                                                    @click="handleMobileAppEnableButtonClick"
                                                    description="Allow this extension to sign in and use the mobile app."
                                                    :conditions="[() => !mobileAppOptions?.mobile_app && mobileAppOptions && !creatingInitiated]" />

                                                <GroupElement name="container2"
                                                    :conditions="[() => !mobileAppOptions?.mobile_app && mobileAppOptions]" />

                                                <ButtonElement name="enable_mobile_app_contact"
                                                    button-label="Add Contact"
                                                    label="OR Step 1: Add to Address Book (BLF)" :secondary="true"
                                                    @click="handleMobileAppContactButtonClick"
                                                    description="Create a new contact entry for this extension in the company address book."
                                                    :conditions="[() => !mobileAppOptions?.mobile_app && mobileAppOptions && !creatingInitiated]" />


                                                <SelectElement name="mobile_app_connection"
                                                    :items="mobileAppOptions?.connections" :search="true"
                                                    :native="false" label="Step 2: Select connection" label-prop="name"
                                                    value-prop="id" input-type="search" autocomplete="off"
                                                    :strict="false" :columns="{
                                                        sm: {
                                                            wrapper: 6,
                                                        },
                                                    }"
                                                    :conditions="[() => !!mobileAppOptions?.connections && creatingInitiated]" />

                                                <StaticElement name="mobile_app_credentials"
                                                    :conditions="[() => !!mobileApp]">
                                                    <div v-if="mobileApp && mobileApp.user"
                                                        class="flex bg-white p-6 rounded-lg shadow-md ">

                                                        <div class="grow">
                                                            <h3 class="text-lg font-semibold mb-4">Mobile App Details
                                                            </h3>
                                                            <ul class="mb-4 space-y-1 text-sm">
                                                                <li
                                                                    class="flex flex-col sm:flex-row sm:items-center mt-1 gap-1 text-sm ">
                                                                    <strong>Username:</strong> {{
                                                                        mobileApp.user.username }}
                                                                    <button type="button"
                                                                        @click="handleCopyToClipboard(mobileApp.user.username)">
                                                                        <ClipboardDocumentIcon
                                                                            class="h-5 w-5 text-blue-500 hover:text-blue-900 cursor-pointer" />
                                                                    </button>
                                                                </li>

                                                                <li
                                                                    class="flex flex-col sm:flex-row sm:items-center mt-1 gap-1 text-sm ">
                                                                    <strong>Domain:</strong> {{ mobileApp.user.domain }}
                                                                    <button type="button"
                                                                        @click="handleCopyToClipboard(mobileApp.user.domain)">
                                                                        <ClipboardDocumentIcon
                                                                            class="h-5 w-5 text-blue-500 hover:text-blue-900 cursor-pointer" />
                                                                    </button>
                                                                </li>

                                                                <li
                                                                    class="flex flex-col sm:flex-row sm:items-center mt-1 gap-1 text-sm ">
                                                                    <strong>Password:</strong>
                                                                    <span v-if="mobileApp.user.password"
                                                                        class="font-mono">{{
                                                                            mobileApp.user.password }}</span>
                                                                    <button v-if="mobileApp.user.password" type="button"
                                                                        @click="handleCopyToClipboard(mobileApp.user.password)">
                                                                        <ClipboardDocumentIcon
                                                                            class="h-5 w-5 text-blue-500 hover:text-blue-900 cursor-pointer" />
                                                                    </button>
                                                                    <a v-if="mobileApp.user.password_url"
                                                                        :href="mobileApp.user.password_url"
                                                                        target="_blank">Click here
                                                                        to get password</a>
                                                                    <span
                                                                        v-if="!mobileApp.user.password && !mobileApp.user.password_url"
                                                                        class="font-mono">**********</span>
                                                                </li>

                                                            </ul>
                                                        </div>

                                                        <div v-if="mobileApp.qrcode" class="">
                                                            <h4 class="text-md font-semibold mb-2">QR Code</h4>
                                                            <img :src="`data:image/png;base64,${mobileApp.qrcode}`"
                                                                alt="QR Code" class="w-30 h-30 border rounded" />
                                                            <!-- <p class="text-xs text-gray-400 mt-1">Scan this code in the
                                                                mobile app to sign in.</p> -->
                                                        </div>
                                                    </div>

                                                </StaticElement>

                                                <ButtonElement name="submit_enabling_mobile_app" button-label="Submit"
                                                    @click="handleMobileAppSubmitButtonClick"
                                                    :loading="isMobileAppLoading.submit"
                                                    :conditions="[() => !mobileAppOptions?.mobile_app && mobileAppOptions && creatingInitiated]" />

                                                <ButtonElement name="reset_mobile_app_password"
                                                    button-label="Reset Credentials" label="Reset Mobile App Login"
                                                    :loading="isMobileAppLoading.reset"
                                                    @click="handleMobileAppResetButtonClick"
                                                    description="Generate new app credentials and sign out all currently logged-in devices."
                                                    :conditions="[() => !!mobileAppOptions?.mobile_app && mobileAppOptions?.mobile_app?.status == 1]" />

                                                <GroupElement name="container3"
                                                    :conditions="[() => !!mobileAppOptions?.mobile_app && mobileAppOptions?.mobile_app?.status == 1]" />

                                                <ButtonElement name="deactivate_mobile_app" button-label="Deactivate"
                                                    label="Suspend Mobile App Access"
                                                    :loading="isMobileAppLoading.deactivate"
                                                    @click="handleMobileAppDeactivateButtonClick"
                                                    description="Prevent this extension from signing in to the mobile app. The user will remain visible in the address book."
                                                    :secondary="true"
                                                    :conditions="[() => !!mobileAppOptions?.mobile_app && mobileAppOptions?.mobile_app?.status == 1]" />


                                                <ButtonElement name="activate_mobile_app" button-label="Activate"
                                                    label="Activate Mobile App" :loading="isMobileAppLoading.activate"
                                                    @click="handleMobileAppActivateButtonClick"
                                                    description="Allow this extension to sign in and use the mobile app."
                                                    :conditions="[() => !!mobileAppOptions?.mobile_app && mobileAppOptions?.mobile_app?.status == -1]" />

                                                <GroupElement name="container4"
                                                    :conditions="[() => !!mobileAppOptions?.mobile_app && mobileAppOptions?.mobile_app?.status == -1]" />

                                                <ButtonElement name="remove_mobile_app" button-label="Remove"
                                                    label="Remove Mobile App" @click="handleMobileAppRemoveButtonClick"
                                                    :loading="isMobileAppLoading.remove"
                                                    description="Permanently delete the mobile app association for this extension."
                                                    :danger="true"
                                                    :conditions="[() => !!mobileAppOptions?.mobile_app]" />

                                                <GroupElement name="container5"
                                                    :conditions="[() => !!mobileAppOptions?.mobile_app]" />


                                                <StaticElement name="mobile_app_loading">
                                                    <div v-if="isMobileAppOptionsLoading"
                                                        class="text-center my-5 text-sm text-gray-500">
                                                        <div class="animate-pulse flex space-x-4">
                                                            <div class="flex-1 space-y-6 py-1">
                                                                <div class="h-2 bg-slate-200 rounded"></div>
                                                                <div class="h-2 bg-slate-200 rounded"></div>
                                                                <div class="h-2 bg-slate-200 rounded"></div>
                                                                <div class="h-2 bg-slate-200 rounded"></div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </StaticElement>

                                                <StaticElement name="mobile_app_error">
                                                    <div v-if="mobileAppError"
                                                        class="border-l-4 border-yellow-400 bg-yellow-50 p-4">
                                                        <div class="flex">
                                                            <div class="shrink-0">
                                                                <ExclamationTriangleIcon class="size-5 text-yellow-400"
                                                                    aria-hidden="true" />
                                                            </div>
                                                            <div class="ml-3">
                                                                <p class="text-sm text-yellow-700">
                                                                    {{ mobileAppError }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </StaticElement>

                                                <GroupElement name="container_mobile_app" />

                                                <ButtonElement name="submit_mobile_app" button-label="Save"
                                                    :submits="true" align="right" />


                                                <!-- SIP Credentials -->

                                                <StaticElement name="sip_credentials_title" tag="h4"
                                                    content="Show SIP Credentials" description="" />

                                                <ButtonElement name="show_sip_credentials" button-label="Show"
                                                    :conditions="[() => { return !sip_credentials }]"
                                                    @click="handleSipCredentialsButtonClick" label="SIP Credentials"
                                                    :loading="isSipCredentialsLoading" />

                                                <StaticElement name="sip_credentials"
                                                    :conditions="[() => { return !!sip_credentials }]">
                                                    <div class="space-y-8 sm:space-y-6">
                                                        <div>
                                                            <dt
                                                                class="text-sm font-medium text-gray-500 sm:w-40 sm:shrink-0">
                                                                Domain</dt>
                                                            <dd
                                                                class="flex flex-row items-center mt-1 gap-2 text-sm text-gray-900 sm:col-span-2">
                                                                {{ sip_credentials?.context }}

                                                                <div
                                                                    @click="handleCopyToClipboard(sip_credentials?.context)">
                                                                    <ClipboardDocumentIcon
                                                                        class="h-5 w-5 text-gray-500 hover:text-gray-900 cursor-pointer" />
                                                                </div>

                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt
                                                                class="text-sm font-medium text-gray-500 sm:w-40 sm:shrink-0">
                                                                Username</dt>
                                                            <dd
                                                                class="flex flex-row items-center mt-1 gap-2 text-sm text-gray-900 sm:col-span-2">
                                                                {{ sip_credentials?.extension }}

                                                                <div
                                                                    @click="handleCopyToClipboard(sip_credentials?.extension)">
                                                                    <ClipboardDocumentIcon
                                                                        class="h-5 w-5 text-gray-500 hover:text-gray-900 cursor-pointer" />
                                                                </div>

                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt
                                                                class="text-sm font-medium text-gray-500 sm:w-40 sm:shrink-0">
                                                                Password</dt>
                                                            <dd
                                                                class="flex flex-row items-center mt-1 gap-2 text-sm text-gray-900 sm:col-span-2">
                                                                {{ sip_credentials?.password }}

                                                                <div
                                                                    @click="handleCopyToClipboard(sip_credentials?.password)">
                                                                    <ClipboardDocumentIcon
                                                                        class="h-5 w-5 text-gray-500 hover:text-gray-900 cursor-pointer" />
                                                                </div>

                                                            </dd>
                                                        </div>

                                                    </div>
                                                </StaticElement>

                                                <ButtonElement name="regenerate_sip_credentials"
                                                    button-label="Regenerate"
                                                    :conditions="[() => { return !!sip_credentials }]"
                                                    @click="handleSipCredentialsRegenerateClick" :secondary="true"
                                                    :loading="isSipCredentialsRegenerateLoading" />

                                                <ButtonElement name="edit_sip_password" button-label="Edit"
                                                    :conditions="[() => { return !!sip_credentials }]"
                                                    @click="handleSipCredentialsEditClick" :secondary="true"
                                                     />

                                                <GroupElement name="container_sip_credentials" />

                                                <ButtonElement name="submit_sip_credentials" button-label="Save"
                                                    :submits="true" align="right" />

                                                <!-- Advaced settings -->

                                                <StaticElement name="advanced_title" tag="h4"
                                                    content="Advanced Settings" description=""
                                                    :conditions="[['voicemail_enabled', '==', 'true']]" />

                                                <ToggleElement name="directory_visible"
                                                    text="Show in company dial-by-name directory"
                                                    description="Controls whether this extension appears in the company’s dial-by-name directory. Hide extensions for devices (door phones, intercoms) or private users (e.g., executives)."
                                                    true-value="true" false-value="false"
                                                    :conditions="[() => options.permissions.extension_directory]" />

                                                <StaticElement name="divider3" tag="hr"
                                                    :conditions="[() => options.permissions.extension_directory]" />

                                                <ToggleElement name="directory_exten_visible"
                                                    text="Announce extension after name in directory"
                                                    description="Controls whether the extension number is played after the user’s name in the directory. Useful for making it easier for callers to reach the extension directly. Disable for privacy or security reasons."
                                                    true-value="true" false-value="false"
                                                    :conditions="[() => options.permissions.extension_directory]" />

                                                <StaticElement name="divider4" tag="hr"
                                                    :conditions="[() => options.permissions.extension_directory]" />

                                                <ToggleElement name="call_screen_enabled" text="Enable call screening"
                                                    description="You can use Call Screen to find out who’s calling and why before you pick up a call."
                                                    true-value="true" false-value="false"
                                                    :conditions="[() => options.permissions.extension_call_screen]" />

                                                <StaticElement name="divider16" tag="hr"
                                                    :conditions="[() => options.permissions.extension_call_screen]" />

                                                <TextElement name="max_registrations" input-type="number" :rules="[
                                                    'nullable',
                                                    'numeric',
                                                ]" autocomplete="off" label="Maximum registrations"
                                                    description="Enter the maximum registration allowed for this user"
                                                    :columns="{
                                                        default: {
                                                            wrapper: 6,
                                                        },
                                                        sm: {
                                                            container: 6,
                                                            wrapper: 4,
                                                        },
                                                    }"
                                                    :conditions="[() => options.permissions.extension_max_registrations]" />

                                                <TextElement name="limit_max" input-type="number" :rules="[
                                                    'nullable',
                                                    'numeric',
                                                ]" autocomplete="off" label="Max number of outbound calls"
                                                    description="Enter the max number of outgoing calls for this user."
                                                    :columns="{
                                                        default: {
                                                            wrapper: 6,
                                                        },
                                                        sm: {
                                                            container: 6,
                                                            wrapper: 4,
                                                        },
                                                    }" :conditions="[() => options.permissions.extension_limit]" />

                                                <TextElement name="limit_destination"
                                                    label="Hangup Cause when limit is reached" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        }
                                                    }"
                                                    description="Enter the destination to send the calls when the max number of outgoing calls has been reached."
                                                    :conditions="[() => options.permissions.extension_limit]" />

                                                <TextElement name="toll_allow" label="Toll Allow" :columns="{
                                                    sm: {
                                                        container: 6,
                                                    }
                                                }" description="Examples: domestic,international,local"
                                                    :conditions="[() => options.permissions.extension_toll]" />

                                                <TextElement name="call_group" label="Call Group"
                                                    description="A user in a call group can perform a call pickup (or an intercept) of a ringing phone belonging to another user who is also in the call group."
                                                    :columns="{
                                                        sm: {
                                                            wrapper: 6,
                                                        },
                                                    }"
                                                    :conditions="[() => options.permissions.extension_call_group]" />


                                                <SelectElement name="hold_music" :items="options.music_on_hold_options"
                                                    :groups="true" default="" :search="true" :native="false"
                                                    label="Select custom Music On Hold" input-type="search"
                                                    autocomplete="off" :strict="false" :columns="{
                                                        sm: {
                                                            wrapper: 6,
                                                        },
                                                    }"
                                                    :conditions="[() => options.permissions.extension_hold_music]" />

                                                <TextElement name="auth_acl" label="Auth ACL" :columns="{
                                                    sm: {
                                                        container: 6,
                                                    }
                                                }" />

                                                <TextElement name="cidr" label="CIDR" :columns="{
                                                    sm: {
                                                        container: 6,
                                                    }
                                                }" :conditions="[() => options.permissions.extension_cidr]" />


                                                <SelectElement name="sip_force_contact" :items="[
                                                    {
                                                        value: 'NDLB-connectile-dysfunction',
                                                        label: 'Rewrite Contact IP and Port',
                                                    },
                                                    {
                                                        value: 'NDLB-connectile-dysfunction-2.0',
                                                        label: 'Rewrite Contact IP and Port 2.0',
                                                    },
                                                    {
                                                        value: 'NDLB-tls-connectile-dysfunction',
                                                        label: 'Rewrite TLS Contact Port',
                                                    },
                                                ]" :search="true" :native="false" label="SIP Force Contact"
                                                    input-type="search" autocomplete="off"
                                                    :columns="{ sm: { container: 6 }, }" />

                                                <TextElement name="sip_force_expires" label="SIP Force Expires"
                                                    :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" />
                                                <SelectElement name="sip_bypass_media" :items="[
                                                    {
                                                        value: 'bypass-media',
                                                        label: 'Bypass Media',
                                                    },
                                                    {
                                                        value: 'bypass-media-after-bridge',
                                                        label: 'Bypass Media After Bridge',
                                                    },
                                                    {
                                                        value: 'proxy-media',
                                                        label: 'Proxy Media',
                                                    },
                                                ]" :search="true" :native="false" label="SIP Bypass Media "
                                                    input-type="search" autocomplete="off"
                                                    :columns="{ sm: { wrapper: 6, }, }" />

                                                <TextElement name="mwi_account" label="Monitor MWI Account"
                                                    description="MWI Account with user@domain of the voicemail to monitor." />

                                                <TextElement name="absolute_codec_string" label="Absolute Codec String"
                                                    description="Absolute Codec String for the extension"
                                                    :conditions="[() => options.permissions.extension_absolute_codec_string]" />

                                                <TextElement name="dial_string" label="Dial String"
                                                    :conditions="[() => options.permissions.extension_dial_string]" />

                                                <ToggleElement name="force_ping" text="Force ping"
                                                    description="Use OPTIONS to detect if extension is reachable"
                                                    true-value="true" false-value="false"
                                                    :conditions="[() => options.permissions.extension_force_ping]" />

                                                <TextElement name="user_context" label="Context" :columns="{
                                                    sm: {
                                                        container: 6,
                                                    },
                                                }" />

                                                <TextElement name="accountcode" label="Account Code" :columns="{
                                                    sm: {
                                                        container: 6,
                                                    },
                                                }" :conditions="[() => options.permissions.extension_accountcode]" />

                                                <ToggleElement name="exclude_from_ringotel_stale_users"
                                                    text="Exclude this user from the App Stale Users report"
                                                    description="If enabled, this user will not appear in the App Stale Users report, preventing them from being flagged as inactive." />


                                                <GroupElement name="container_advanced" />

                                                <ButtonElement name="submit_advanced" button-label="Save"
                                                    :submits="true" align="right" />



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

    <AddEditItemModal :customClass="'sm:max-w-xl'" :show="showNewGreetingModal" :header="''" :loading="isModalLoading"
        @close="handleModalClose">
        <template #modal-body>
            <NewGreetingForm :title="'New Voicemail Greeting'" :voices="options.voices" :speeds="options.speeds"
                :default_voice="options.default_voice" :phone_call_instructions="options.phone_call_instructions"
                :sample_message="options.sample_message" :routes="getRoutesForGreetingForm"
                @greeting-saved="handleGreetingSaved" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :customClass="'sm:max-w-xl'" :show="showNewNameGreetingModal" :header="''"
        :loading="isModalLoading" @close="handleModalClose">
        <template #modal-body>
            <NewGreetingForm :title="'New Recorded Name'" :voices="options.voices" :speeds="options.speeds"
                :default_voice="options.default_voice"
                :phone_call_instructions="options.phone_call_instructions_for_name" :sample_message="'John Dow'"
                :routes="getRoutesForNameForm" @greeting-saved="handleNameSaved" />
        </template>
    </AddEditItemModal>

    <CreateExtensionDeviceForm :show="showDeviceCreateModal" :extension="options.item" :options="deviceItemOptions"
        :loading="isModalLoading" :header="'Create New Device'" @close="showDeviceCreateModal = false"
        @error="emitErrorToParentFromChild" @success="emitSuccessToParentFromChild" @refresh-data="getDevices" />

    <UpdateExtensionDeviceForm :show="showDeviceUpdateModal" :options="deviceItemOptions" :loading="isModalLoading"
        :header="'Update Device Settings'" @close="showDeviceUpdateModal = false" @error="emitErrorToParentFromChild"
        @success="emitSuccessToParentFromChild" @refresh-data="getDevices" />

    <AssignExtensionDeviceForm :show="showDeviceAssignModal" :extension="options.item" :devices="options.all_devices"
        :options="deviceItemOptions" :loading="isModalLoading" :header="'Assign Existing Device'"
        @close="showDeviceAssignModal = false" @error="emitErrorToParentFromChild"
        @success="emitSuccessToParentFromChild" @refresh-data="getDevices" />

    <ConfirmationModal :show="showDeleteConfirmationModal" @close="showDeleteConfirmationModal = false"
        @confirm="confirmGreetingDeleteAction" :header="'Confirm Deletion'"
        :text="'This action will permanently delete this greeting. Are you sure you want to proceed?'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" />

    <ConfirmationModal :show="showUnassignConfirmationModal" @close="showUnassignConfirmationModal = false"
        @confirm="confirmUnassignAction" :header="'Confirm Unassigning Device'" :loading="isUnassignDeviceLoading"
        :text="'This action will unassign this device and keep it in your inventory. Are you sure you want to proceed?'"
        :confirm-button-label="'Unassign'" cancel-button-label="Cancel" />

    <ConfirmationModal :show="showDeleteNameConfirmationModal" @close="showDeleteNameConfirmationModal = false"
        @confirm="confirmDeleteNameAction" :header="'Confirm Deletion'"
        :text="'This action will permanently delete this greeting. Are you sure you want to proceed?'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" />

    <UpdateSipPasswordModal :show="showUpdatePasswordModal" :sip_credentials="sip_credentials" :extension_uuid="options?.item?.extension_uuid" 
        :route="options?.routes?.update_password_route" @close="showUpdatePasswordModal = false"
        @error="emitErrorToParentFromChild" @success="emitSuccessToParentFromChild" @refresh-data="handleSipCredentialsButtonClick" />

</template>

<script setup>
import { ref, computed, watch, reactive } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/solid";
import { PlusIcon, TrashIcon, PencilSquareIcon } from '@heroicons/vue/20/solid'
import { PlayCircleIcon, CloudArrowDownIcon, PauseCircleIcon } from '@heroicons/vue/24/solid';
import Spinner from "@generalComponents/Spinner.vue";
import NewGreetingForm from './NewGreetingForm.vue';
import AddEditItemModal from "../modal/AddEditItemModal.vue";
import ConfirmationModal from "../modal/ConfirmationModal.vue";
import UpdateExtensionDeviceForm from "../forms/UpdateExtensionDeviceForm.vue";
import CreateExtensionDeviceForm from "../forms/CreateExtensionDeviceForm.vue";
import AssignExtensionDeviceForm from "../forms/AssignExtensionDeviceForm.vue";
import UpdateSipPasswordModal from "../modal/UpdateSipPasswordModal.vue";
import Badge from "@generalComponents/Badge.vue";
import AssignedDevices from "../AssignedDevices.vue";
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import { ExclamationTriangleIcon } from '@heroicons/vue/20/solid'


const emit = defineEmits(['close', 'error', 'success', 'refresh-data'])

const props = defineProps({
    show: Boolean,
    options: Object,
    header: String,
    loading: Boolean,
});

const copied = ref({ uuid: false })

async function copy(value, key) {
  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(value ?? '')
    } else {
      const ta = document.createElement('textarea')
      ta.value = value ?? ''
      ta.style.position = 'fixed'
      ta.style.opacity = '0'
      document.body.appendChild(ta)
      ta.select()
      document.execCommand('copy')
      document.body.removeChild(ta)
    }
    copied.value[key] = true
    setTimeout(() => (copied.value[key] = false), 800)
  } catch (e) {
    console.error('Failed to copy:', e)
  }
}

const form$ = ref(null)
const showResetConfirmationModal = ref(false);
const isDevicesLoading = ref(false)
const isMobileAppOptionsLoading = ref(false)
const mobileAppError = ref(false)
const isSipCredentialsLoading = ref(false)
const isSipCredentialsRegenerateLoading = ref(false)
const isUnassignDeviceLoading = ref(false)
const showDeleteConfirmationModal = ref(false)
const showUnassignConfirmationModal = ref(false)
const showDeleteNameConfirmationModal = ref(false)
const showDeviceUpdateModal = ref(false)
const showDeviceCreateModal = ref(false)
const showDeviceAssignModal = ref(false)
const showUpdatePasswordModal = ref(false)
const devices = ref([])
const sip_credentials = ref(null)
const mobileApp = ref(null)
const mobileAppOptions = ref(null)
const showApiTokenModal = ref(false)
const isDownloading = ref(false);
const isNameAudioPlaying = ref(false);
const isNameDownloading = ref(false);
const currentNameAudio = ref(null);
const showNewGreetingModal = ref(false);
const showNewNameGreetingModal = ref(false);
const isModalLoading = ref(false);
const greetings = ref(props.options?.voicemail?.greetings)
const recorded_name = ref(props.options?.recorded_name)
const deviceItemOptions = ref(null)
const confirmUnassignAction = ref(null)
const creatingInitiated = ref(false)
const isMobileAppLoading = reactive({
    submit: false,
    reset: false,
    activate: false,
    deactivate: false,
    remove: false,
})
const mobileAppContactOnly = ref(false)

// Watch for changes in the prop and update the ref
watch(
    () => props.options?.voicemail?.greetings,
    (newVal) => {
        greetings.value = newVal
    }
)

// Watch for changes in the prop and update the ref
watch(
    () => props.options?.recorded_name,
    (newVal) => {
        recorded_name.value = newVal
    }
)

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            // Modal just opened
            sip_credentials.value = null;
            // Reset any other refs as needed
        }
    }
);

const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData
    // console.log(requestData);

    if (!form$.el$('recording').value) {
        requestData['user_record'] = null;
    }

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

const availableDestinations = computed(() => {
    const membersField = form$.value?.el$('follow_me_destinations');
    const currentMembers = membersField?.value || [];

    const selectedDestinations = currentMembers.map(m => m.destination);

    return props.options.follow_me_destination_options.map(group => ({
        label: group.groupLabel,
        items: group.groupOptions.filter(opt =>
            !selectedDestinations.includes(opt.destination)
        ),
    }));
});

const formatTarget = (name, value) => {
    return { [name]: value?.extension ?? null } // must return an object
}

const formatGreeting = (name, value) => {
    return { [name]: value?.value ?? null } // must return an object
}


const addSelectedDestinations = () => {
    // console.log(form$.value.el$('selectedDestinations').value);
    const selectedItems = form$.value.el$('selectedDestinations').value.map(item => {
        return {
            uuid: item.destination ? item.value : null,              // if a destination exists, use the item.value as uuid; otherwise, uuid is null
            destination: item.destination ? item.destination : item.label,  // if item.destination exists, use it; otherwise, use the label
            type: item.type ? item.type : "other",                     // if type exists, use it; else default to "other"
            delay: "0",
            timeout: "30",
            prompt: false,
        }
    });

    const currentMembers = form$.value.el$('follow_me_destinations').value

    form$.value.update({
        follow_me_destinations: [...currentMembers, ...selectedItems]
    })

    form$.value.el$('selectedDestinations').update([]); // clear selection
};

function getDestinationLabel(destination) {
    // console.log(destination);
    // Find the member option based on the extension number.
    const allFollowMeDestinationOptions = props.options?.follow_me_destination_options?.flatMap(group => group.groupOptions);
    const dest = allFollowMeDestinationOptions.find(opt => opt.destination === destination);
    // If found, return the full label; otherwise, return the extension.
    return dest ? dest.label : destination;
};

const handleDeviceEditButtonClick = (itemUuid) => {
    showDeviceUpdateModal.value = true
    getDeviceItemOptions(itemUuid);
}

const getDeviceItemOptions = (itemUuid = null) => {
    const payload = itemUuid ? { 'itemUuid': itemUuid } : {}; // Conditionally add itemUuid to payload
    isModalLoading.value = true
    axios.post(props.options.routes.device_item_options, payload)
        .then((response) => {
            deviceItemOptions.value = response.data;
            // console.log(deviceItemOptions.value);

        }).catch((error) => {
            emit('error', error)
        }).finally(() => {
            isModalLoading.value = false
        })
}


const handleAddDeviceButtonClick = () => {
    showDeviceCreateModal.value = true
    getDeviceItemOptions();
}

const handleAssignDeviceButtonClick = () => {
    showDeviceAssignModal.value = true
    getDeviceItemOptions();
}

const handleUnassignDeviceButtonClick = (uuid) => {
    showUnassignConfirmationModal.value = true;
    confirmUnassignAction.value = () => executeBulkUnassign([uuid]);
};

const executeBulkUnassign = async (items) => {
    isUnassignDeviceLoading.value = true;

    const extension_uuid = props.options.item.extension_uuid;

    try {
        const response = await axios.post(
            props.options.routes.device_bulk_unassign,
            {
                items,              // array of device UUIDs
                extension_uuid      // the extension UUID to unassign
            }
        );
        emit('success', 'success', response.data.messages);
        getDevices();
    } catch (error) {
        emit('error', error);
    } finally {
        // hide both the delete and the confirmation modals
        handleModalClose();
        isUnassignDeviceLoading.value = false;
    }
};

const handleTabSelected = (activeTab, previousTab) => {
    if (activeTab.name == 'devices') {
        getDevices()
    }
    if (activeTab.name == 'mobile_app') {
        mobileAppOptions.value = null
        creatingInitiated.value = false
        mobileApp.value = null
        getMobileAppOptions()
    }

}

const getDevices = async () => {
    isDevicesLoading.value = true
    axios.get(props.options.routes.devices)
        .then((response) => {
            devices.value = response.data.data;
            // console.log(devices.value);

        }).catch((error) => {
            emit('error', error)
        }).finally(() => {
            isDevicesLoading.value = false
        });
}

const getMobileAppOptions = async () => {
    isMobileAppOptionsLoading.value = true
    mobileAppError.value = false
    axios.post(props.options.routes.mobile_app_options,
        {
            extension_uuid: props.options.item.extension_uuid,
        }
    )
        .then((response) => {
            mobileAppOptions.value = response.data;
            // console.log(mobileAppOptions.value);
            form$.value.el$('mobile_app_connection').update(mobileAppOptions?.value?.connections[0]?.id)

        }).catch((error) => {
            emit('error', error)
            mobileAppError.value = error?.response?.data?.errors?.error[0] ?? null
        }).finally(() => {
            isMobileAppOptionsLoading.value = false
        });
}

const handleMobileAppEnableButtonClick = async () => {
    creatingInitiated.value = true
    mobileAppContactOnly.value = false
}

const handleMobileAppContactButtonClick = async () => {
    creatingInitiated.value = true
    mobileAppContactOnly.value = true
}

const handleMobileAppSubmitButtonClick = async () => {
    isMobileAppLoading.submit = true
    axios.post(props.options.routes.create_mobile_app,
        {
            extension_uuid: props.options.item.extension_uuid,
            connection: form$.value.el$('mobile_app_connection').value,
            org_id: mobileAppOptions.value.org_id,
            app_domain: mobileAppOptions.value.app_domain,
            status: mobileAppContactOnly.value ? -1 : 1,
        }
    )
        .then((response) => {
            if (!mobileAppContactOnly.value) {
                mobileApp.value = response.data;
            }

            getMobileAppOptions()
            creatingInitiated.value = false

        }).catch((error) => {
            emit('error', error)
        }).finally(() => {
            isMobileAppLoading.submit = false
        });
}

const handleMobileAppRemoveButtonClick = async () => {
    isMobileAppLoading.remove = true
    axios.post(props.options.routes.delete_mobile_app,
        {
            mobile_app_user_uuid: mobileAppOptions?.value?.mobile_app?.mobile_app_user_uuid,
            org_id: mobileAppOptions?.value?.mobile_app?.org_id,
            user_id: mobileAppOptions?.value?.mobile_app?.user_id
        }
    )
        .then((response) => {
            emit('success', 'success', response.data.messages);

            getMobileAppOptions()

        }).catch((error) => {
            emit('error', error)
        }).finally(() => {
            isMobileAppLoading.remove = false
            mobileApp.value = false
        });
}

const handleMobileAppResetButtonClick = async () => {
    isMobileAppLoading.reset = true
    axios.post(props.options.routes.reset_mobile_app,
        {
            extension_uuid: props.options.item.extension_uuid,
            email: props.options.item.email,
            org_id: mobileAppOptions?.value?.mobile_app?.org_id,
            user_id: mobileAppOptions?.value?.mobile_app?.user_id
        }
    )
        .then((response) => {
            mobileApp.value = response.data;
            // console.log(mobileApp.value);

            emit('success', 'success', response.data.messages);

            getMobileAppOptions()

        }).catch((error) => {
            emit('error', error)
        }).finally(() => {
            isMobileAppLoading.reset = false
        });
}

const handleMobileAppDeactivateButtonClick = async () => {
    isMobileAppLoading.deactivate = true
    axios.post(props.options.routes.deactivate_mobile_app,
        {
            mobile_app_user_uuid: mobileAppOptions?.value?.mobile_app?.mobile_app_user_uuid,
            ext: props.options.item.extension,
            org_id: mobileAppOptions?.value?.mobile_app?.org_id,
            conn_id: mobileAppOptions?.value?.mobile_app?.conn_id,
            user_id: mobileAppOptions?.value?.mobile_app?.user_id
        }
    )
        .then((response) => {

            emit('success', 'success', response.data.messages);

            getMobileAppOptions()

        }).catch((error) => {
            emit('error', error)
        }).finally(() => {
            isMobileAppLoading.deactivate = false
            mobileApp.value = null
        });
}

const handleMobileAppActivateButtonClick = async () => {
    mobileApp.value = null
    isMobileAppLoading.activate = true
    axios.post(props.options.routes.activate_mobile_app,
        {
            extension_uuid: props.options.item.extension_uuid,
            email: props.options.item.email,
            org_id: mobileAppOptions?.value?.mobile_app?.org_id,
            user_id: mobileAppOptions?.value?.mobile_app?.user_id
        }
    )
        .then((response) => {
            mobileApp.value = response.data;
            // console.log(mobileApp.value);

            emit('success', 'success', response.data.messages);

            getMobileAppOptions()

        }).catch((error) => {
            emit('error', error)
        }).finally(() => {
            isMobileAppLoading.activate = false
        });
}


const handleSipCredentialsButtonClick = async () => {
    sip_credentials.value = null
    isSipCredentialsLoading.value = true
    axios.get(props.options.routes.sip_credentials)
        .then((response) => {
            sip_credentials.value = response.data.data;
            // console.log(sip_credentials.value);

        }).catch((error) => {
            emit('error', error)
        }).finally(() => {
            isSipCredentialsLoading.value = false
        });
}

const handleSipCredentialsRegenerateClick = async () => {
    isSipCredentialsRegenerateLoading.value = true
    axios.get(props.options.routes.regenerate_sip_credentials)
        .then((response) => {
            sip_credentials.value = response.data.data;
            // console.log(sip_credentials.value);

        }).catch((error) => {
            emit('error', error)
        }).finally(() => {
            isSipCredentialsRegenerateLoading.value = false
        });
}

const handleSipCredentialsEditClick = () => {
    showUpdatePasswordModal.value = true
}

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emit('success', 'success', { message: ['Copied to clipboard.'] });
    }).catch((error) => {
        // Handle the error case
        emit('error', { response: { data: { errors: { request: ['Failed to copy to clipboard.'] } } } });
    });
}



const emitErrorToParentFromChild = (error) => {
    emit('error', error);
}

const emitSuccessToParentFromChild = (message) => {
    emit('success', 'success', message);
}

const delayOptions = Array.from({ length: 21 }, (_, i) => {
    const seconds = i * 5; // 0, 5, 10, ..., 100
    const rings = Math.round(seconds / 5); // 1 ring = ~5 seconds
    return {
        value: String(seconds),
        label: `${rings} ${rings === 1 ? 'Ring' : 'Rings'} (${seconds}s)`
    };
});

const timeoutOptions = Array.from({ length: 21 }, (_, i) => {
    const seconds = i * 5; // 0, 5, 10, ..., 100
    const rings = Math.round(seconds / 5);
    return {
        value: String(seconds),
        label: `${rings} ${rings === 1 ? 'Ring' : 'Rings'} (${seconds}s)`
    };
});

const handleNewGreetingButtonClick = () => {
    showNewGreetingModal.value = true;
};

const handleNewNameGreetingButtonClick = () => {
    showNewNameGreetingModal.value = true;
};

const greetingTranscription = computed(() => {
    // Check that the ref is assigned and has a `value` property
    return form$?.value?.data?.greeting_id?.description || null;
})

// Handler for the greeting-saved event
const handleGreetingSaved = ({ greeting_id, greeting_name, description }) => {

    // Add the new greeting to the greetings.value array
    greetings.value.push({ value: String(greeting_id), label: greeting_name, description: description });

    // Sort the greetings array by greeting_id
    greetings.value.sort((a, b) => Number(a.value) - Number(b.value));

    // Update the selected greeting ID
    form$.value.update({
        greeting_id: {
            value: String(greeting_id),
            label: greeting_name,
            description: description
        }
    })

    currentAudio.value = null;

    showNewGreetingModal.value = false;

    emit('success', 'success', { message: ['New greeting has been successfully added.'] });
};


const currentAudio = ref(null);
const isAudioPlaying = ref(false);
const currentAudioGreeting = ref(null);

const playGreeting = () => {
    const greeting = form$.value.data.greeting_id.value;

    if (!greeting) return; // No greeting selected

    // If there's already an audio playing for the SAME greeting
    if (currentAudio.value && currentAudio.value.src && currentAudioGreeting.value === greeting) {
        if (currentAudio.value.paused) {
            currentAudio.value.play();
            isAudioPlaying.value = true;
        }
        return; // Same greeting, don't reload
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
            if (currentAudio.value) {
                currentAudio.value.pause();
                currentAudio.value.currentTime = 0;
            }
            if (response.data.success) {
                isAudioPlaying.value = true;

                currentAudio.value = new Audio(response.data.file_url);
                currentAudioGreeting.value = greeting;
                currentAudio.value.play().catch(() => {
                    isAudioPlaying.value = false;
                    emit('error', { message: 'Audio playback failed' });
                });

                currentAudio.value.addEventListener("ended", () => {
                    isAudioPlaying.value = false;
                });
            }
        }).catch((error) => {
            emit('error', error);
        });
};



const downloadGreeting = () => {
    isDownloading.value = true; // Start the spinner

    const greeting = form$.value.data.greeting_id.value;

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

const confirmGreetingDeleteAction = () => {
    axios
        .post(props.options.routes.delete_greeting_route, { greeting_id: form$.value.data.greeting_id.value })
        .then((response) => {
            if (response.data.success) {
                // Remove the deleted greeting from the greetings.value array
                greetings.value = greetings.value.filter(
                    (greeting) => greeting.value !== String(form$.value.el$('greeting_id').value.value)
                );

                // Reset the selected greeting ID
                form$.value.el$('greeting_id').update(greetings.value);

                form$.value.el$('greeting_id').clear()

                // Notify the parent component or show a local success message
                emit('success', 'success', response.data.messages);
            }
        })
        .catch((error) => {
            emit('error', error); // Emit an error event if needed
        })
        .finally(() => {
            showDeleteConfirmationModal.value = false; // Close the confirmation modal
        });
};


// Handler for the greeting-saved event
const handleNameSaved = ({ greeting_id, greeting_name }) => {
    recorded_name.value = 'Custom recording';
    currentNameAudio.value = null;

    showNewNameGreetingModal.value = false;

    emit('success', 'success', { message: ['New recorded name has been successfully added.'] });
};

// Methods for recorded name
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


// Computed property or method to dynamically set routes based on the form type
const getRoutesForGreetingForm = computed(() => {
    // Return routes specifically for the greeting form
    return {
        ...props.options.routes,
        text_to_speech_route: props.options.routes.text_to_speech_route,
        upload_greeting_route: props.options.routes.upload_greeting_route
    };
});

const getRoutesForNameForm = computed(() => {
    // Return routes specifically for the name form
    return {
        ...props.options.routes,
        text_to_speech_route: props.options.routes.text_to_speech_route_for_name,
        upload_greeting_route: props.options.routes.upload_greeting_route_for_name,
    };
});

const handleModalClose = () => {
    showResetConfirmationModal.value = false;
    showApiTokenModal.value = false
    showDeleteConfirmationModal.value = false;
    showNewGreetingModal.value = false
    showNewNameGreetingModal.value = false
    showDeleteNameConfirmationModal.value = false;
    showUnassignConfirmationModal.value = false
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

<style>
div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}
</style>