<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" :inert="showApiTokenModal">
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
                                    forward_all_enabled: props.options.item.forward_all_enabled ?? 'false',
                                    forward_all_action: props.options.item.forward_all_action ?? '',

                                    // only set forward_external_target when forwarding_action==='external'
                                    forward_external_target: props.options.item.forward_all_action === 'external'
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

                                    // groups: options.item.user_groups
                                    //     ? options.item.user_groups.map(ug => ug.group_uuid)
                                    //     : []

                                }">

                                <template #empty>

                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical" @select="handleTabSelected">
                                                <FormTab name="page0" label="Basic Info" :elements="[
                                                    'basic_info_title',
                                                    'directory_first_name',
                                                    'directory_last_name',
                                                    'extension',
                                                    'voicemail_mail_to',
                                                    'description',
                                                    'user_enabled',
                                                    'enabled',
                                                    'suspended',
                                                    'directory_visible',
                                                    'directory_exten_visible',
                                                    'do_not_disturb',
                                                    'divider',
                                                    'divider1',
                                                    'divider2',
                                                    'divider3',
                                                    'container_2',
                                                    'container_3',
                                                    'submit',

                                                ]" />
                                                <FormTab name="caller_id" label="Caller ID" :elements="[
                                                    'external_caller_id_title',
                                                    'emergency_caller_id_title',
                                                    'outbound_caller_id_number',
                                                    'emergency_caller_id_number',
                                                    'container_3',
                                                    'submit',

                                                ]" />
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
                                                    'container_3',
                                                    'submit',

                                                ]" />
                                                <FormTab name="voicemail" label="Voicemail" :elements="[
                                                    'voicemail_title',
                                                    'voicemail_enabled',
                                                    'voicemail_password',
                                                    'voicemail_mail_to2',
                                                    'voicemail_description',
                                                    'voicemail_transcription_enabled',
                                                    'divider10',
                                                    'voicemail_attach_file',
                                                    'divider11',
                                                    'voicemail_local_after_email',
                                                    'voicemail_destinations',
                                                    'divider12',
                                                    'voicemail_greetings_title',
                                                    'container_3',
                                                    'submit',

                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>

                                                <HiddenElement name="extension_uuid" :meta="true" />

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
                                                    }" />
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
                                                    }" />

                                                <StaticElement name="divider" tag="hr" />

                                                <ToggleElement name="do_not_disturb" text="Do Not Disturb" true-value="true"
                                                    false-value="false" :replace-class="{
                                                        'toggle.toggleOn': {
                                                            'form-bg-primary': 'bg-red-500',
                                                            'form-border-color-primary': 'border-red-500',
                                                            'form-color-on-primary': 'form-color-on-danger'

                                                        }
                                                    }" />

                                                <StaticElement name="divider1" tag="hr" />

                                                <ToggleElement name="enabled" text="Status" true-value="true"
                                                    false-value="false"
                                                    description="Activate or deactivate the extension. When deactivated, devices cannot connect and calls cannot be placed or received." />

                                                <StaticElement name="divider2" tag="hr" />

                                                <ToggleElement name="directory_visible"
                                                    text="Show in company dial-by-name directory"
                                                    description="Controls whether this extension appears in the company’s dial-by-name directory. Hide extensions for devices (door phones, intercoms) or private users (e.g., executives)."
                                                    true-value="true" false-value="false" />

                                                <StaticElement name="divide3" tag="hr" />

                                                <ToggleElement name="directory_exten_visible"
                                                    text="Announce extension after name in directory"
                                                    description="Controls whether the extension number is played after the user’s name in the directory. Useful for making it easier for callers to reach the extension directly. Disable for privacy or security reasons."
                                                    true-value="true" false-value="false" />


                                                <!-- Caller ID Tab -->
                                                <StaticElement name="external_caller_id_title" tag="h4"
                                                    content="External Caller ID"
                                                    description="Define the External Caller ID that will be displayed on the recipient's device when dialing outside the company." />

                                                <SelectElement name="outbound_caller_id_number"
                                                    :items="options.phone_numbers" :search="true" :native="false"
                                                    input-type="search" autocomplete="off" />
                                                <StaticElement name="emergency_caller_id_title" tag="h4"
                                                    content="Emergency Caller ID"
                                                    description="Define the Emergency Caller ID that will be displayed when dialing emergency services." />

                                                <SelectElement name="emergency_caller_id_number"
                                                    :items="options.phone_numbers" :search="true" :native="false"
                                                    input-type="search" autocomplete="off" />


                                                <!-- Call Forward Tab -->

                                                <StaticElement name="forward_all_calls_title" tag="h4"
                                                    content="Forward All Calls"
                                                    description="Instantly and unconditionally forward all incoming calls to another destination. No calls will ring to your phone until forwarding is disabled." />
                                                <ToggleElement name="forward_all_enabled" :labels="{
                                                    on: 'On',
                                                    off: 'Off',
                                                }" true-value="true" false-value="false" />

                                                <SelectElement name="forward_all_action" :items="options.forwarding_types"
                                                    :search="true" :native="false" label="Choose Action" input-type="search"
                                                    autocomplete="off" placeholder="Choose Action" :floating="false"
                                                    :strict="false" :conditions="[['forward_all_enabled', '==', 'true'],]"
                                                    :columns="{
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
                                                    placeholder="Choose Target" :floating="false" :strict="false" :columns="{
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
                                                <StaticElement name="divider5" tag="hr" />



                                                <StaticElement name="forward_busy_title" tag="h4"
                                                    content="When user is busy"
                                                    description="Automatically redirect incoming calls to a different destination when your line is busy or Do Not Disturb is active." />

                                                <ToggleElement name="forward_busy_enabled" :labels="{
                                                    on: 'On',
                                                    off: 'Off',
                                                }" true-value="true" false-value="false" />

                                                <SelectElement name="forward_busy_action" :items="options.forwarding_types"
                                                    :search="true" :native="false" label="Choose Action" input-type="search"
                                                    autocomplete="off" placeholder="Choose Action" :floating="false"
                                                    :strict="false" :conditions="[['forward_busy_enabled', '==', 'true']]"
                                                    :columns="{
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
                                                    placeholder="Choose Target" :floating="false" :strict="false" :columns="{
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
                                                <StaticElement name="divider6" tag="hr" />

                                                <StaticElement name="forward_no_answer_title" tag="h4"
                                                    content="When user does not answer the call"
                                                    description="Automatically redirect incoming calls to another number if you do not answer within a set time." />

                                                <ToggleElement name="forward_no_answer_enabled" :labels="{
                                                    on: 'On',
                                                    off: 'Off',
                                                }" true-value="true" false-value="false" />

                                                <SelectElement name="forward_no_answer_action"
                                                    :items="options.forwarding_types" :search="true" :native="false"
                                                    label="Choose Action" input-type="search" autocomplete="off"
                                                    placeholder="Choose Action" :floating="false" :strict="false"
                                                    :conditions="[['forward_no_answer_enabled', '==', 'true']]" :columns="{
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
                                                    placeholder="Choose Target" :floating="false" :strict="false" :columns="{
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

                                                <StaticElement name="divider7" tag="hr" />


                                                <StaticElement name="forward_user_not_registered_title" tag="h4"
                                                    content="When Device Is Not Registered (Internet Outage)"
                                                    description="Redirect calls to a different number if your device is not registered or unreachable." />

                                                <ToggleElement name="forward_user_not_registered_enabled" :labels="{
                                                    on: 'On',
                                                    off: 'Off',
                                                }" true-value="true" false-value="false" />

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
                                                    placeholder="Choose Target" :floating="false" :strict="false" :columns="{
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

                                                <StaticElement name="divider8" tag="hr" />


                                                <StaticElement name="follow_me_title" tag="h4" content="Call Sequence"
                                                    description="Calls ring all your devices first, then your backup destinations one at a time until someone answers" />

                                                <ToggleElement name="follow_me_enabled" :labels="{
                                                    on: 'On',
                                                    off: 'Off',
                                                }" true-value="true" false-value="false" />

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
                                                            }"
                                                                :columns="{ default: { container: 8, }, sm: { container: 4, }, }"
                                                                label="Destination"
                                                                :attrs="{ class: 'text-base font-semibold' }" />

                                                            <SelectElement name="delay" :items="delayOptions" :search="true"
                                                                :native="false" label="Delay" input-type="search"
                                                                allow-absent autocomplete="off" :columns="{
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


                                                            <ToggleElement name="prompt" align="left" size="sm"
                                                                text="Enable answer confirmation"
                                                                description="This prevents voicemails and automated systems from answering a call."
                                                                info="Enable answer confirmation to prevent voicemails and automated systems from answering a call." />


                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>



                                                <!-- Voicemail Tab -->

                                                <StaticElement name="voicemail_title" tag="h4" content="Voicemail"
                                                    description="Customize voicemail preferences" />
                                                <ToggleElement name="voicemail_enabled" text="Status" true-value="true"
                                                    false-value="false" default="true" />
                                                <TextElement name="voicemail_mail_to2" label="Email Address" :columns="{
                                                    sm: {
                                                        container: 6,
                                                    },
                                                }" placeholder="Enter Email" :floating="false" :disabled="true" />
                                                <TextElement name="voicemail_password" label="Password" :columns="{
                                                    sm: {
                                                        container: 6,
                                                    },
                                                }" />
                                                <TextElement name="voicemail_description" label="Description"
                                                    placeholder="Enter Description" :floating="false" />
                                                <ToggleElement name="voicemail_transcription_enabled"
                                                    text="Voicemail Transcription" true-value="true" false-value="false"
                                                    description="Convert voicemail messages to text using AI-powered transcription." />
                                                <StaticElement name="divider10" tag="hr" />
                                                <ToggleElement name="voicemail_attach_file"
                                                    text="Attach File to Email Notifications" true-value="true"
                                                    false-value="false"
                                                    description="Attach voicemail recording file to the email notification." />
                                                <StaticElement name="divider11" tag="hr" />
                                                <ToggleElement name="voicemail_local_after_email"
                                                    text="Automatically Delete Voicemail After Email" true-value="false"
                                                    false-value="true"
                                                    description="Remove voicemail from the cloud once the email is sent." />
                                                <TagsElement name="voicemail_destinations" :search="true" :items="[
                                                    {
                                                        value: 0,
                                                        label: 'Label',
                                                    },
                                                ]" label="Copy Voicemail to Other Extensions" input-type="search" autocomplete="off"
                                                    description="Automatically send a copy of the voicemail to selected additional extensions."
                                                    :floating="false" placeholder="Enter name or extension" />
                                                <StaticElement name="divider12" tag="hr" top="1" bottom="1" />
                                                <StaticElement name="voicemail_greetings_title" tag="h4"
                                                    content="Voicemail Greetings"
                                                    description="Customize the message that callers hear when they reach your voicemail." />

                                                <GroupElement name="container_3" />

                                                <ButtonElement name="submit" button-label="Save" :submits="true"
                                                    align="right" />



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

    <CreateApiTokenModal :show="showApiTokenModal" :options="options" @close="showApiTokenModal = false"
        @error="emitErrorToParentFromChild" @success="emitSuccessToParentFromChild" @refresh-data="getTokens" />


    <ConfirmationModal :show="showDeleteConfirmationModal" @close="showDeleteConfirmationModal = false"
        @confirm="confirmDeleteAction" :header="'Confirm Deletion'" :loading="isDeleteTokenLoading"
        :text="'This action will permanently delete the selected API Key. Are you sure you want to proceed?'"
        confirm-button-label="Delete" cancel-button-label="Cancel" />
</template>

<script setup>
import { ref, computed } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/solid";
import ConfirmationModal from "./../modal/ConfirmationModal.vue";
import CreateApiTokenModal from "./../modal/CreateApiTokenModal.vue"


const emit = defineEmits(['close', 'error', 'success', 'refresh-data'])

const props = defineProps({
    show: Boolean,
    options: Object,
    header: String,
    loading: Boolean,
});

const form$ = ref(null)
const showResetConfirmationModal = ref(false);
const isTokensLoading = ref(false)
const isDeleteTokenLoading = ref(false)
const showDeleteConfirmationModal = ref(false)
const tokens = ref([])
const addTokenButtonLoading = ref(false)
const showApiTokenModal = ref(false)
const confirmDeleteAction = ref(null);


const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData
    console.log(requestData);

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

// const addSelectedDestinations = () => {
//     showResetConfirmationModal.value = true;
// };

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


// const confirmResetPassword = async () => {
//     showResetConfirmationModal.value = false;

//     try {
//         await form$.value.$vueform.services.axios.post(
//             props.options.routes.password_reset,
//             {
//                 email: props.options.item.voicemail_mail_to,
//             }
//         );

//         emit("success", "success", { success: ["Password reset email sent successfully."] });
//     } catch (error) {
//         emit("error", error);
//     }
// };

const handleAddTokenButtonClick = () => {
    showApiTokenModal.value = true
}

const handleTabSelected = (activeTab, previousTab) => {
    if (activeTab.name == 'api_tokens') {
        getTokens()
    }
}

const getTokens = async () => {
    isTokensLoading.value = true
    axios.get(props.options.routes.tokens, {
        params: {
            uuid: props.options.item.user_uuid
        }
    })
        .then((response) => {
            tokens.value = response.data.data;
            // console.log(tokens.value);

        }).catch((error) => {
            emit('error', error)
        }).finally(() => {
            isTokensLoading.value = false
        });
}

// const handleUpdateTokenButtonClick = async uuid => {
//     updateTokenButtonLoading.value = true;
//     try {
//         await getHolidayItemOptions(uuid);
//         showUpdateTokenModal.value = true;
//     } catch (err) {
//         handleModalClose();
//         emit('error', err);
//     } finally {
//         updateTokenButtonLoading.value = false;
//     }
// };


const handleDeleteTokenButtonClick = (uuid) => {
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
};


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

const executeBulkDelete = async (items) => {
    isDeleteTokenLoading.value = true;

    try {
        const response = await axios.post(
            props.options.routes.token_bulk_delete,
            { items }
        );
        emit('success', 'success', response.data.messages);
        getTokens();
    } catch (error) {
        emit('error', error);
    } finally {
        // hide both the delete and the confirmation modals
        handleModalClose();
        isDeleteTokenLoading.value = false;
    }
};

const handleModalClose = () => {
    showResetConfirmationModal.value = false;
    showApiTokenModal.value = false
    showDeleteConfirmationModal.value = false;
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